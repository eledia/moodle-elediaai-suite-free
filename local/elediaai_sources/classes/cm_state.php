<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_elediaai_sources;

/**
 * Site-local index truth per course module.
 *
 * Records what was last sent where, under which source id and with which
 * content hash. The row lifecycle carries the meaning: a successful upsert
 * writes it, a successful removal deletes it — **absence of a row means "not
 * in the index"**, which lets status displays be honest instead of guessing.
 *
 * The stored source id freezes the tenant of the ingest moment, so a later
 * wwwroot change cannot strand documents: cleanup deletes by the id that was
 * actually written, not by one derived today.
 *
 * A failed upsert only updates status and error: hash and ingest time keep
 * describing what is actually in the index (the previous content), because
 * the failed attempt never got there.
 *
 * A destination that confirms asynchronously ({@see sink\status_reporting_sink})
 * adds a fourth state between sending and knowing (ai_suite#232): an accepted
 * upsert is "queued". The content hash then names the set last *accepted*,
 * so unchanged content is not sent again while the pipeline still works on
 * it; the ingest time is only set once the destination confirms. Each sent
 * (sub-)document gets a row of its own in `local_elediaai_sources_docstate`
 * with the job id and the status read back later; the module counts as
 * indexed when all of them succeeded and as failed as soon as one failed.
 *
 * Deliberately separate from {@see activity_gate}'s table, where a row means
 * "a teacher decided". Mixing decisions with state would make both unreadable.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cm_state {
    /** @var string Backing table. */
    private const TABLE = 'local_elediaai_sources_cmstate';

    /** @var string A successful upsert reached the index. */
    public const STATUS_SUCCESS = 'success';

    /** @var string The last attempt failed; the index holds the previous content, if any. */
    public const STATUS_ERROR = 'error';

    /**
     * @var string Worked through, nothing to send: no extractor, empty content, or only files that may not go.
     *
     * Holds nothing in the index. It exists so "tried, nothing there" can be
     * told from "never tried" -- a row missing for an included module is
     * what the reconcile catches up on (#32).
     */
    public const STATUS_EMPTY = 'empty';

    /**
     * @var string Accepted by an asynchronous destination; its processing is not confirmed yet.
     *
     * Covers both "waiting" and "being processed" in the pipeline; which of
     * the two it is, the document rows tell.
     */
    public const STATUS_QUEUED = 'queued';

    /** @var string Per-document table for asynchronous destinations. */
    private const DOC_TABLE = 'local_elediaai_sources_docstate';

    /** @var string Pipeline status: waiting for processing or for a retry. */
    public const REMOTE_QUEUED = 'queued';

    /** @var string Pipeline status: being processed. */
    public const REMOTE_RUNNING = 'running';

    /** @var string Pipeline status: processed and in the index. */
    public const REMOTE_SUCCEEDED = 'succeeded';

    /** @var string Pipeline status: the last processing attempt failed. */
    public const REMOTE_FAILED = 'failed';

    /** @var string Pipeline status: removed through the API later on. */
    public const REMOTE_DELETED = 'deleted';

    /** @var string Pipeline status: no stored history for the id. */
    public const REMOTE_UNKNOWN = 'unknown';

    /** @var string[] Pipeline statuses that are still open and worth asking about again. */
    public const REMOTE_INFLIGHT = [self::REMOTE_QUEUED, self::REMOTE_RUNNING, self::REMOTE_UNKNOWN];

    /**
     * @var int Seconds after acceptance a still open document is given up on as unconfirmed.
     *
     * Only counts down while the status API answers (OPEN-001): an outage of
     * the status API leaves the state as it is. Counted as a failed attempt,
     * so it is retried within the normal budget (OPEN-002).
     */
    public const UNCONFIRMED_AFTER = 86400;

    /**
     * @var string[] Pipeline error types that a re-send cannot fix (OPEN-003).
     *
     * Everything else, unknown types included, counts as retryable: a wrong
     * "retryable" costs at most a few daily re-sends, a wrong "permanent"
     * hides a fixable error for good.
     */
    public const PERMANENT_ERROR_TYPES = [
        'ParsingError',
        'ValidationError',
        'EmptyContentError',
        'PermanentEmbeddingError',
        'ContentTypeNotSupportedError',
        'DocumentTooLargeError',
        'CleaningError',
        'ChunkingError',
    ];

    /** @var string Error type of a running job replaced by a newer waiting one; not an error (REQ-010). */
    public const ERRORTYPE_SUPERSEDED = 'Superseded';

    /** @var string Error type this plugin records when the pipeline never confirmed a document. */
    public const ERRORTYPE_UNCONFIRMED = 'Unconfirmed';

    /** @var int Seconds before the first status check after a failed one. */
    private const BACKOFF_BASE = 300;

    /** @var int Longest wait between two failed status checks. */
    private const BACKOFF_MAX = 3600;

    /** @var int Jitter added to the backoff, in percent at most. */
    private const BACKOFF_JITTER = 20;

    /**
     * @var int Consecutive failures after which a module is left alone.
     *
     * The reconcile runs every quarter of an hour. Without a limit, a document
     * the destination will never accept — a malformed activity, a service that
     * answers 502 for this one payload — is retried indefinitely and keeps its
     * course permanently "not finished". Five attempts is enough to ride out a
     * restart or a brief outage; what fails five times in a row needs a human,
     * not a sixth attempt. A forced reindex clears the counter.
     */
    public const MAX_ATTEMPTS = 5;

    /**
     * Hash of one final document, as it goes over the wire.
     *
     * The title is part of it because it is part of the payload (AI-75): a
     * file renamed inside a Folder changes nothing about its bytes, and
     * without the title in the hash the index would keep citing the old name
     * forever. The cost is one re-send per document after the upgrade that
     * introduced the title — visible in the logs, and self-healing through the
     * quarter-hourly reconcile.
     *
     * @param string $sourceid The document's source id.
     * @param string $contenttype The MIME type.
     * @param string $content The final (mutated, truncated) content.
     * @param string $title The original file name or document title.
     * @return string sha1 hex digest.
     */
    public static function document_hash(
        string $sourceid,
        string $contenttype,
        string $content,
        string $title = ''
    ): string {
        return sha1($sourceid . '|' . $contenttype . '|' . $title . '|' . $content);
    }

    /**
     * Aggregate hash of a module's document set.
     *
     * @param string[] $documenthashes Per-document hashes, in send order.
     * @return string sha1 hex digest.
     */
    public static function aggregate_hash(array $documenthashes): string {
        return sha1(implode('|', $documenthashes));
    }

    /**
     * Whether the index already holds, or is about to hold, exactly this content.
     *
     * True only when the last successful ingest used the same destination,
     * the same source id (which covers the tenant) and the same content hash,
     * and the course's recorded embedding model still matches the
     * destination's. A model change makes every module "changed", so the
     * course-level reindex that follows re-embeds instead of skipping.
     *
     * The destination is passed in rather than resolved here, so the caller's
     * (possibly injected) sink is authoritative.
     *
     * A queued module counts as current too: the destination accepted this
     * very content and is still working on it. Sending it again would only
     * restart the pipeline's attempts (ai_suite#232, TD-004).
     *
     * @param int $cmid The course module id.
     * @param string $sourceid The module-level source id as built now.
     * @param string $contenthash The aggregate hash of the current content.
     * @param string $sinkid The destination the caller would send to.
     * @param string|null $sinkmodel That destination's embedding model.
     * @return bool
     */
    public static function is_current(
        int $cmid,
        string $sourceid,
        string $contenthash,
        string $sinkid,
        ?string $sinkmodel
    ): bool {
        global $DB;

        $row = $DB->get_record(self::TABLE, ['cmid' => $cmid]);
        if (!$row || !in_array($row->laststatus, [self::STATUS_SUCCESS, self::STATUS_QUEUED], true)) {
            return false;
        }

        $matches = (string) $row->sink === $sinkid
            && (string) $row->sourceid === $sourceid
            && (string) $row->contenthash === $contenthash;
        if (!$matches) {
            return false;
        }

        $coursemodel = $DB->get_field(
            'local_elediaai_sources_course',
            'embeddingmodel',
            ['courseid' => $row->courseid]
        );
        return $coursemodel !== false
            && (string) $coursemodel === (string) ($sinkmodel ?? '');
    }

    /**
     * Record a successful ingest of the module's current document set.
     *
     * Used for destinations that answer with the result itself. Document rows
     * left from an asynchronous destination describe a send this one has
     * just replaced, so they go.
     *
     * @param int $courseid The course id.
     * @param int $cmid The course module id.
     * @param string $sourceid The module-level source id.
     * @param string $contenthash The aggregate content hash.
     * @param string $sinkid The destination that was written to.
     * @return void
     */
    public static function record_success(
        int $courseid,
        int $cmid,
        string $sourceid,
        string $contenthash,
        string $sinkid
    ): void {
        global $DB;

        $DB->delete_records(self::DOC_TABLE, ['cmid' => $cmid]);
        self::upsert($courseid, $cmid, [
            'sink' => $sinkid,
            'sourceid' => $sourceid,
            'contenthash' => $contenthash,
            'laststatus' => self::STATUS_SUCCESS,
            'lasterror' => null,
            'attempts' => 0,
            'timeingested' => time(),
        ]);
    }

    /**
     * Record that an asynchronous destination accepted the module's document set.
     *
     * Replaces the module's document rows in one transaction with the set
     * just accepted, in send order, and marks the module queued. The hash is
     * written now, because it names what the destination holds or is about
     * to hold ({@see is_current()}); the ingest time waits for the
     * confirmation. The failure counter is left alone: only a confirmed
     * success resets it, or a document that fails in the pipeline every time
     * would be re-sent for ever.
     *
     * @param int $courseid The course id.
     * @param int $cmid The course module id.
     * @param string $sourceid The module-level source id.
     * @param string $contenthash The aggregate content hash.
     * @param string $sinkid The destination that accepted the set.
     * @param array $documents The accepted documents in send order, each
     *        ['sourceid' => string, 'jobid' => ?string].
     * @return void
     */
    public static function record_queued(
        int $courseid,
        int $cmid,
        string $sourceid,
        string $contenthash,
        string $sinkid,
        array $documents
    ): void {
        global $DB;

        $now = time();
        $transaction = $DB->start_delegated_transaction();

        $DB->delete_records(self::DOC_TABLE, ['cmid' => $cmid]);
        $ordinal = 0;
        $seen = [];
        foreach ($documents as $document) {
            $docsourceid = (string) $document['sourceid'];
            if ($docsourceid === '' || isset($seen[$docsourceid])) {
                continue;
            }
            $seen[$docsourceid] = true;
            // A row left behind under the same id by another module cannot
            // exist (the id carries the cmid), but one from a reset course
            // could; the unique index would refuse the insert.
            $DB->delete_records(self::DOC_TABLE, ['sourceid' => $docsourceid]);
            $jobid = trim((string) ($document['jobid'] ?? ''));
            $DB->insert_record(self::DOC_TABLE, (object) [
                'courseid' => $courseid,
                'cmid' => $cmid,
                'sourceid' => $docsourceid,
                'ordinal' => $ordinal++,
                'jobid' => $jobid === '' ? null : \core_text::substr($jobid, 0, 64),
                'remotestatus' => self::REMOTE_QUEUED,
                'errortype' => null,
                'errormessage' => null,
                'attemptcount' => 0,
                'maxattempts' => 0,
                'nextretryat' => 0,
                'statuscheckedat' => 0,
                'timelastcheck' => 0,
                'nextcheck' => 0,
                'checkfailures' => 0,
                'checkerror' => null,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }

        $existing = $DB->get_record(self::TABLE, ['cmid' => $cmid]);
        if ($existing) {
            $existing->courseid = $courseid;
            $existing->sink = $sinkid;
            $existing->sourceid = $sourceid;
            $existing->contenthash = $contenthash;
            $existing->laststatus = self::STATUS_QUEUED;
            $existing->lasterror = null;
            $existing->timemodified = $now;
            $DB->update_record(self::TABLE, $existing);
        } else {
            $DB->insert_record(self::TABLE, (object) [
                'courseid' => $courseid,
                'cmid' => $cmid,
                'sink' => $sinkid,
                'sourceid' => $sourceid,
                'contenthash' => $contenthash,
                'laststatus' => self::STATUS_QUEUED,
                'attempts' => 0,
                'lasterror' => null,
                'timeingested' => 0,
                'timemodified' => $now,
            ]);
        }

        $transaction->allow_commit();
    }

    /**
     * Record a failed attempt without overwriting what the index still holds.
     *
     * Document rows of an earlier accepted set go: the module is no longer
     * waiting for them, and nobody would ask about them again (TD-006).
     *
     * @param int $courseid The course id.
     * @param int $cmid The course module id.
     * @param string $sourceid The module-level source id.
     * @param string $error The error message.
     * @param string $sinkid The destination that was attempted.
     * @return void
     */
    public static function record_error(
        int $courseid,
        int $cmid,
        string $sourceid,
        string $error,
        string $sinkid
    ): void {
        global $DB;

        $DB->delete_records(self::DOC_TABLE, ['cmid' => $cmid]);
        $existing = $DB->get_record(self::TABLE, ['cmid' => $cmid]);
        if ($existing) {
            $existing->laststatus = self::STATUS_ERROR;
            $existing->lasterror = $error;
            $existing->attempts = (int) $existing->attempts + 1;
            $existing->timemodified = time();
            $DB->update_record(self::TABLE, $existing);
            return;
        }

        self::upsert($courseid, $cmid, [
            'sink' => $sinkid,
            'sourceid' => $sourceid,
            'contenthash' => '',
            'laststatus' => self::STATUS_ERROR,
            'lasterror' => $error,
            'attempts' => 1,
            'timeingested' => 0,
        ]);
    }

    /**
     * Record that the module had nothing to send.
     *
     * A row that says the index holds the module's content is left as it is:
     * a module that became empty was not removed from the index before, and
     * the state has to keep describing what is there. A queued row is such a
     * row too: the destination accepted content it is still processing.
     *
     * @param int $courseid The course id.
     * @param int $cmid The course module id.
     * @param string $sourceid The module-level source id.
     * @param string $reason Why there was nothing to send.
     * @param string $sinkid The destination that was attempted.
     * @return void
     */
    public static function record_empty(
        int $courseid,
        int $cmid,
        string $sourceid,
        string $reason,
        string $sinkid
    ): void {
        global $DB;

        $existing = self::get($cmid);
        $holding = [self::STATUS_SUCCESS, self::STATUS_QUEUED];
        if ($existing !== null && in_array($existing->laststatus, $holding, true)) {
            return;
        }

        $DB->delete_records(self::DOC_TABLE, ['cmid' => $cmid]);
        self::upsert($courseid, $cmid, [
            'sink' => $sinkid,
            'sourceid' => $sourceid,
            'contenthash' => '',
            'laststatus' => self::STATUS_EMPTY,
            'lasterror' => $reason,
            'attempts' => 0,
            'timeingested' => 0,
        ]);
    }

    /**
     * Whether the index may hold documents of the module.
     *
     * A success certainly, an error possibly (a failed update leaves the
     * previous content); an "empty" row never.
     *
     * @param int $cmid The course module id.
     * @return bool
     */
    public static function may_hold_documents(int $cmid): bool {
        $row = self::get($cmid);
        return $row !== null && $row->laststatus !== self::STATUS_EMPTY;
    }

    /**
     * Whether the module has spent its retry budget.
     *
     * Only a failing module can be exhausted: a success resets the counter, so
     * a module that is in the index never blocks itself.
     *
     * @param int $cmid The course module id.
     * @return bool
     */
    public static function is_exhausted(int $cmid): bool {
        global $DB;

        $row = $DB->get_record(self::TABLE, ['cmid' => $cmid], 'laststatus, attempts');
        return $row
            && $row->laststatus === self::STATUS_ERROR
            && (int) $row->attempts >= self::MAX_ATTEMPTS;
    }

    /**
     * Give the module a fresh retry budget.
     *
     * Called for a forced reindex: an administrator who triggers one by hand
     * has usually just fixed the cause, and would otherwise be told nothing
     * happened.
     *
     * @param int $cmid The course module id.
     * @return void
     */
    public static function reset_attempts(int $cmid): void {
        global $DB;
        $DB->set_field(self::TABLE, 'attempts', 0, ['cmid' => $cmid]);
    }

    /**
     * Per-course counts of what the index actually holds.
     *
     * One grouped query instead of a read per course: the status displays walk
     * every course on the site.
     *
     * Queued modules are counted apart from failures (REQ-012): waiting for
     * the pipeline is not an error.
     *
     * @param string $sinkid Only count rows written to this destination.
     * @param int|null $courseid Only this course; every course when null.
     * @return array<int, array{success: int, queued: int, retryable: int, exhausted: int}>
     */
    public static function summary_by_course(string $sinkid, ?int $courseid = null): array {
        global $DB;

        $coursefilter = '';
        $courseparams = [];
        if ($courseid !== null) {
            $coursefilter = 'AND courseid = :courseid';
            $courseparams['courseid'] = $courseid;
        }

        $sql = "SELECT courseid,
                       SUM(CASE WHEN laststatus = :ok THEN 1 ELSE 0 END) AS successes,
                       SUM(CASE WHEN laststatus = :queued THEN 1 ELSE 0 END) AS queueds,
                       SUM(CASE WHEN laststatus = :err1 AND attempts < :max1 THEN 1 ELSE 0 END) AS retryables,
                       SUM(CASE WHEN laststatus = :err2 AND attempts >= :max2 THEN 1 ELSE 0 END) AS exhausteds
                  FROM {" . self::TABLE . "}
                 WHERE sink = :sink
                       {$coursefilter}
              GROUP BY courseid";

        $summary = [];
        $rows = $DB->get_records_sql($sql, [
            'ok' => self::STATUS_SUCCESS,
            'queued' => self::STATUS_QUEUED,
            'err1' => self::STATUS_ERROR,
            'err2' => self::STATUS_ERROR,
            'max1' => self::MAX_ATTEMPTS,
            'max2' => self::MAX_ATTEMPTS,
            'sink' => $sinkid,
        ] + $courseparams);
        foreach ($rows as $row) {
            $summary[(int) $row->courseid] = [
                'success' => (int) $row->successes,
                'queued' => (int) $row->queueds,
                'retryable' => (int) $row->retryables,
                'exhausted' => (int) $row->exhausteds,
            ];
        }
        return $summary;
    }

    /**
     * Forget a module's state after its documents left the index.
     *
     * @param int $cmid The course module id.
     * @return void
     */
    public static function forget(int $cmid): void {
        global $DB;
        $DB->delete_records(self::DOC_TABLE, ['cmid' => $cmid]);
        $DB->delete_records(self::TABLE, ['cmid' => $cmid]);
    }

    /**
     * Forget a module's state only when it describes the given destination.
     *
     * Clearing a destination the site already left must not discard what is
     * known about the current one: after a switch the row usually names the
     * NEW destination, and dropping it would make freshly ingested content
     * look absent.
     *
     * @param int $cmid The course module id.
     * @param string $sinkid The destination that was just cleared.
     * @return void
     */
    public static function forget_for_sink(int $cmid, string $sinkid): void {
        global $DB;
        if ($DB->record_exists(self::TABLE, ['cmid' => $cmid, 'sink' => $sinkid])) {
            $DB->delete_records(self::DOC_TABLE, ['cmid' => $cmid]);
        }
        $DB->delete_records(self::TABLE, ['cmid' => $cmid, 'sink' => $sinkid]);
    }

    /**
     * Forget every state row of a course.
     *
     * @param int $courseid The course id.
     * @return void
     */
    public static function forget_course(int $courseid): void {
        global $DB;
        $DB->delete_records(self::DOC_TABLE, ['courseid' => $courseid]);
        $DB->delete_records(self::TABLE, ['courseid' => $courseid]);
    }

    /**
     * The state row of a module, or null.
     *
     * @param int $cmid The course module id.
     * @return \stdClass|null
     */
    public static function get(int $cmid): ?\stdClass {
        global $DB;
        $row = $DB->get_record(self::TABLE, ['cmid' => $cmid]);
        return $row ?: null;
    }

    /**
     * All state rows of a course, keyed by cmid.
     *
     * @param int $courseid The course id.
     * @return array<int, \stdClass>
     */
    public static function for_course(int $courseid): array {
        global $DB;

        $rows = [];
        foreach ($DB->get_records(self::TABLE, ['courseid' => $courseid]) as $row) {
            $rows[(int) $row->cmid] = $row;
        }
        return $rows;
    }

    /**
     * Whether the module ran out of attempts because the pipeline refused its content.
     *
     * Such a module is sent again as soon as its content changes (OPEN-004):
     * a teacher who replaces an encrypted PDF with a readable one must not
     * need a forced reindex for it. A module exhausted by transport failures
     * keeps the old rule and waits for a forced reindex.
     *
     * @param int $cmid The course module id.
     * @return bool
     */
    public static function exhausted_by_pipeline(int $cmid): bool {
        global $DB;

        if (!self::is_exhausted($cmid)) {
            return false;
        }

        [$insql, $params] = $DB->get_in_or_equal(self::PERMANENT_ERROR_TYPES, SQL_PARAMS_NAMED, 'et');
        $params['cmid'] = $cmid;
        $params['failed'] = self::REMOTE_FAILED;
        return $DB->record_exists_select(
            self::DOC_TABLE,
            "cmid = :cmid AND remotestatus = :failed AND errortype {$insql}",
            $params
        );
    }

    /**
     * Whether the state row names exactly this content, whatever its status.
     *
     * @param int $cmid The course module id.
     * @param string $sourceid The module-level source id as built now.
     * @param string $contenthash The aggregate hash of the current content.
     * @param string $sinkid The destination the caller would send to.
     * @return bool
     */
    public static function holds_hash(int $cmid, string $sourceid, string $contenthash, string $sinkid): bool {
        $row = self::get($cmid);
        return $row !== null
            && (string) $row->sink === $sinkid
            && (string) $row->sourceid === $sourceid
            && (string) $row->contenthash === $contenthash;
    }

    /**
     * Whether modules of the course wait for the destination's confirmation.
     *
     * @param int $courseid The course id.
     * @param string $sinkid The destination.
     * @return bool
     */
    public static function has_queued_modules(int $courseid, string $sinkid): bool {
        global $DB;
        return $DB->record_exists(self::TABLE, [
            'courseid' => $courseid,
            'sink' => $sinkid,
            'laststatus' => self::STATUS_QUEUED,
        ]);
    }

    /**
     * Whether a pipeline error type is one a re-send cannot fix (OPEN-003).
     *
     * @param string|null $errortype The pipeline's error_type.
     * @return bool
     */
    public static function is_permanent_error_type(?string $errortype): bool {
        return $errortype !== null && in_array($errortype, self::PERMANENT_ERROR_TYPES, true);
    }

    /**
     * The documents of a course still waiting for the destination's confirmation.
     *
     * "Still waiting" means: the module is queued and the document's pipeline
     * status is open (queued, running, unknown or not asked yet). Finished
     * documents, successful or failed, are never asked about again (D-b,
     * TD-020); neither are success rows from before ai_suite#232, which have
     * no document rows at all (REQ-009).
     *
     * @param int $courseid The course id.
     * @param string $sinkid The active destination.
     * @param int|null $dueby Only documents whose next check is due by then; all when null.
     * @return array<int, \stdClass> Document rows keyed by id, ordered by module and send order.
     */
    public static function open_documents(int $courseid, string $sinkid, ?int $dueby = null): array {
        global $DB;

        [$sql, $params] = self::open_documents_sql($sinkid, $dueby);
        $params['courseid'] = $courseid;
        return $DB->get_records_sql(
            "SELECT d.* {$sql} AND d.courseid = :courseid ORDER BY d.cmid, d.ordinal, d.id",
            $params
        );
    }

    /**
     * Whether the course has documents waiting for the destination's confirmation.
     *
     * @param int $courseid The course id.
     * @param string $sinkid The active destination.
     * @return bool
     */
    public static function has_open_documents(int $courseid, string $sinkid): bool {
        global $DB;

        [$sql, $params] = self::open_documents_sql($sinkid, null);
        $params['courseid'] = $courseid;
        return $DB->record_exists_sql("SELECT 1 {$sql} AND d.courseid = :courseid", $params);
    }

    /**
     * Courses with open documents whose next check is due, the longest waiting first.
     *
     * One grouped query: with nothing in flight it is the only thing the
     * status task does.
     *
     * @param string $sinkid The active destination.
     * @param int $now The current time.
     * @param int $limit At most this many courses.
     * @return int[] Course ids.
     */
    public static function courses_due(string $sinkid, int $now, int $limit): array {
        global $DB;

        [$sql, $params] = self::open_documents_sql($sinkid, $now);
        $rows = $DB->get_records_sql(
            "SELECT d.courseid, MIN(d.nextcheck) AS firstdue {$sql}
           GROUP BY d.courseid
           ORDER BY MIN(d.nextcheck) ASC, d.courseid ASC",
            $params,
            0,
            $limit
        );
        return array_map('intval', array_keys($rows));
    }

    /**
     * The FROM/WHERE part shared by the open-document queries.
     *
     * @param string $sinkid The active destination.
     * @param int|null $dueby Only documents whose next check is due by then.
     * @return array{0: string, 1: array} SQL fragment and parameters.
     */
    private static function open_documents_sql(string $sinkid, ?int $dueby): array {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal(self::REMOTE_INFLIGHT, SQL_PARAMS_NAMED, 'rs');
        $params['sink'] = $sinkid;
        $params['queued'] = self::STATUS_QUEUED;
        $due = '';
        if ($dueby !== null) {
            $due = 'AND d.nextcheck <= :dueby';
            $params['dueby'] = $dueby;
        }

        $sql = "FROM {" . self::DOC_TABLE . "} d
                JOIN {" . self::TABLE . "} s ON s.cmid = d.cmid
               WHERE s.sink = :sink
                 AND s.laststatus = :queued
                 AND d.remotestatus {$insql}
                 {$due}";
        return [$sql, $params];
    }

    /**
     * Since when the status of documents in flight cannot be read, and why.
     *
     * For the status report: a lasting outage of the status API leaves
     * documents queued without an automatic change (OPEN-001), so it has to
     * be visible somewhere other than the course pages.
     *
     * @param string $sinkid The active destination.
     * @return array{since: int, reason: string}|null Null when every check succeeds.
     */
    public static function status_check_problem(string $sinkid): ?array {
        global $DB;

        [$sql, $params] = self::open_documents_sql($sinkid, null);
        $sql .= ' AND d.checkfailures > 0';
        $since = $DB->get_field_sql(
            "SELECT MIN(CASE WHEN d.statuscheckedat > 0 THEN d.statuscheckedat ELSE d.timecreated END) {$sql}",
            $params
        );
        if (empty($since)) {
            return null;
        }
        $latest = $DB->get_records_sql("SELECT d.id, d.checkerror {$sql} ORDER BY d.timelastcheck DESC, d.id DESC", $params, 0, 1);
        $row = reset($latest);
        return [
            'since' => (int) $since,
            'reason' => $row ? (string) $row->checkerror : '',
        ];
    }

    /**
     * Note that a status check of these documents starts now.
     *
     * Written for every attempt, successful or not, so the throttle of the
     * course page also holds while the status API fails (TD-022).
     *
     * @param int[] $ids Document row ids.
     * @param int $now The current time.
     * @return void
     */
    public static function mark_checking(array $ids, int $now): void {
        global $DB;

        if ($ids === []) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal(array_values($ids), SQL_PARAMS_NAMED, 'id');
        $DB->set_field_select(self::DOC_TABLE, 'timelastcheck', $now, "id {$insql}", $params);
    }

    /**
     * Take over a successful status answer and settle the modules it concerns.
     *
     * Per document: the pipeline status, job id, error and retry time are
     * stored; the check bookkeeping is reset. A row that was replaced since it
     * was read (a newer send, another id or job id) is left alone, so a check
     * racing a send never writes old news over a new set (TD-015). A running
     * job that ended as "Superseded" because a newer version waits is no
     * error (REQ-010): the document is still queued.
     *
     * Per module, once its documents are updated, {@see settle_module()}
     * decides the module's state.
     *
     * @param \stdClass[] $docrows The document rows as read before asking.
     * @param array<string, array> $remote Normalised status entries keyed by source id.
     * @param int|null $retryafter Seconds the destination asked to wait before the next check.
     * @param int|null $now The current time; time() when null.
     * @return array<int, string> Outcome per touched module, see {@see settle_module()}.
     */
    public static function apply_remote_statuses(
        array $docrows,
        array $remote,
        ?int $retryafter = null,
        ?int $now = null
    ): array {
        global $DB;

        $now ??= time();
        $nextcheck = $now + max(0, (int) ($retryafter ?? 0));
        $known = [
            self::REMOTE_QUEUED,
            self::REMOTE_RUNNING,
            self::REMOTE_SUCCEEDED,
            self::REMOTE_FAILED,
            self::REMOTE_DELETED,
            self::REMOTE_UNKNOWN,
        ];

        $touched = [];
        $successat = [];
        foreach ($docrows as $row) {
            $entry = $remote[(string) $row->sourceid] ?? null;
            if (!is_array($entry)) {
                continue;
            }

            $current = $DB->get_record(self::DOC_TABLE, ['id' => $row->id]);
            if (!$current || (string) ($current->jobid ?? '') !== (string) ($row->jobid ?? '')) {
                continue;
            }

            $status = (string) ($entry['status'] ?? self::REMOTE_UNKNOWN);
            $errortype = isset($entry['error_type']) ? (string) $entry['error_type'] : null;
            $errormessage = isset($entry['error_message']) ? (string) $entry['error_message'] : null;
            if ($status === self::REMOTE_FAILED && $errortype === self::ERRORTYPE_SUPERSEDED) {
                $status = self::REMOTE_QUEUED;
                $errortype = null;
                $errormessage = null;
            }
            if (!in_array($status, $known, true)) {
                $status = self::REMOTE_UNKNOWN;
            }
            if ($status === self::REMOTE_SUCCEEDED) {
                $errortype = null;
                $errormessage = null;
            }

            $jobid = trim((string) ($entry['last_job_id'] ?? ''));
            if ($jobid !== '') {
                $current->jobid = \core_text::substr($jobid, 0, 64);
            }
            $current->remotestatus = $status;
            $current->errortype = $errortype === null ? null : \core_text::substr($errortype, 0, 100);
            $current->errormessage = $errormessage;
            $current->attemptcount = (int) ($entry['attempt_count'] ?? 0);
            $current->maxattempts = (int) ($entry['max_attempts'] ?? 0);
            $current->nextretryat = (int) ($entry['next_retry_at'] ?? 0);
            $current->statuscheckedat = $now;
            $current->timelastcheck = $now;
            $current->nextcheck = $nextcheck;
            $current->checkfailures = 0;
            $current->checkerror = null;
            $current->timemodified = $now;
            $DB->update_record(self::DOC_TABLE, $current);

            $cmid = (int) $current->cmid;
            $touched[$cmid] = true;
            if ($status === self::REMOTE_SUCCEEDED) {
                $successat[$cmid] = max($successat[$cmid] ?? 0, (int) ($entry['last_success_at'] ?? 0));
            }
        }

        $outcome = [];
        foreach (array_keys($touched) as $cmid) {
            $outcome[$cmid] = self::settle_module($cmid, $successat[$cmid] ?? 0, $now);
        }
        return $outcome;
    }

    /**
     * Decide a queued module's state from its documents.
     *
     * In this order (D-a, TD-008 to TD-010):
     * - one document deleted: the whole module is forgotten, so the reconcile
     *   finds it again and sends it in full (the self-healing of #32);
     * - one document failed: the module fails with the reason of the first
     *   failure in send order. It is retryable within the budget unless a
     *   failure is one a re-send cannot fix; then the budget is spent;
     * - one document still open a day after acceptance, although the API
     *   answered: the module fails as unconfirmed, retryable;
     * - all documents succeeded: the module is indexed;
     * - otherwise it stays queued.
     *
     * Only a module that is still queued is touched, in one transaction, so a
     * send that replaced it in the meantime wins.
     *
     * @param int $cmid The course module id.
     * @param int $successat Latest confirmed success time from the answer; 0 when none.
     * @param int $now The current time.
     * @return string One of 'deleted', 'error', 'unconfirmed', 'success', 'queued', 'unchanged'.
     */
    private static function settle_module(int $cmid, int $successat, int $now): string {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        $state = $DB->get_record(self::TABLE, ['cmid' => $cmid]);
        $docs = $DB->get_records(self::DOC_TABLE, ['cmid' => $cmid], 'ordinal ASC, id ASC');
        if (!$state || $state->laststatus !== self::STATUS_QUEUED || !$docs) {
            $transaction->allow_commit();
            return 'unchanged';
        }

        $byremote = static function (string $status) use ($docs): array {
            return array_values(array_filter($docs, static fn($d) => $d->remotestatus === $status));
        };

        if ($byremote(self::REMOTE_DELETED) !== []) {
            self::forget($cmid);
            $transaction->allow_commit();
            return 'deleted';
        }

        $failed = $byremote(self::REMOTE_FAILED);
        if ($failed !== []) {
            $first = $failed[0];
            $reason = trim((string) ($first->errormessage ?? ''));
            if ($reason === '') {
                $reason = trim((string) ($first->errortype ?? ''));
            }
            if ($reason === '') {
                $reason = get_string('status_failed_noreason', 'local_elediaai_sources');
            }
            $permanent = false;
            foreach ($failed as $doc) {
                $permanent = $permanent || self::is_permanent_error_type($doc->errortype);
            }

            $state->laststatus = self::STATUS_ERROR;
            $state->lasterror = $reason;
            $state->attempts = $permanent
                ? max(self::MAX_ATTEMPTS, (int) $state->attempts)
                : (int) $state->attempts + 1;
            $state->timemodified = $now;
            $DB->update_record(self::TABLE, $state);
            $transaction->allow_commit();
            return 'error';
        }

        $unconfirmed = array_filter($docs, static function ($d) use ($now): bool {
            return in_array($d->remotestatus, self::REMOTE_INFLIGHT, true)
                && (int) $d->statuscheckedat >= $now
                && (int) $d->timecreated <= $now - self::UNCONFIRMED_AFTER;
        });
        if ($unconfirmed !== []) {
            foreach ($unconfirmed as $doc) {
                $DB->set_field(self::DOC_TABLE, 'errortype', self::ERRORTYPE_UNCONFIRMED, ['id' => $doc->id]);
            }
            $state->laststatus = self::STATUS_ERROR;
            $state->lasterror = get_string('status_unconfirmed', 'local_elediaai_sources');
            $state->attempts = (int) $state->attempts + 1;
            $state->timemodified = $now;
            $DB->update_record(self::TABLE, $state);
            $transaction->allow_commit();
            return 'unconfirmed';
        }

        if (count($byremote(self::REMOTE_SUCCEEDED)) === count($docs)) {
            $state->laststatus = self::STATUS_SUCCESS;
            $state->lasterror = null;
            $state->attempts = 0;
            $state->timeingested = $successat > 0 ? $successat : $now;
            $state->timemodified = $now;
            $DB->update_record(self::TABLE, $state);
            $transaction->allow_commit();
            return 'success';
        }

        $transaction->allow_commit();
        return 'queued';
    }

    /**
     * Record a failed status check without touching any status.
     *
     * A failing status API means "status not available" (AC-005): the module
     * and its documents keep their last known status and reason, and the
     * confirmation timeout does not run meanwhile (OPEN-001). Only the check
     * bookkeeping changes: the failure count, its reason, and when to try
     * again -- exponential backoff with jitter, at least what Retry-After asked
     * for (REQ-005).
     *
     * @param \stdClass[] $docrows The document rows the failed check was about.
     * @param string $reason Short reason, e.g. "HTTP 503".
     * @param int|null $retryafter Seconds from a Retry-After header.
     * @param int|null $now The current time; time() when null.
     * @return void
     */
    public static function defer_status_check(
        array $docrows,
        string $reason,
        ?int $retryafter = null,
        ?int $now = null
    ): void {
        global $DB;

        $now ??= time();
        $reason = \core_text::substr(trim($reason) !== '' ? trim($reason) : '-', 0, 255);
        foreach ($docrows as $row) {
            $current = $DB->get_record(self::DOC_TABLE, ['id' => $row->id]);
            if (!$current) {
                continue;
            }
            $failures = (int) $current->checkfailures + 1;
            $delay = (int) round(self::backoff_delay($failures) * (1 + random_int(0, self::BACKOFF_JITTER) / 100));
            if ($retryafter !== null) {
                $delay = max($delay, $retryafter);
            }

            $current->checkfailures = $failures;
            $current->checkerror = $reason;
            $current->timelastcheck = $now;
            $current->nextcheck = $now + $delay;
            $current->timemodified = $now;
            $DB->update_record(self::DOC_TABLE, $current);
        }
    }

    /**
     * Backoff before the next check after this many failures in a row, without jitter.
     *
     * Five minutes, doubling, at most an hour.
     *
     * @param int $failures Consecutive failed checks, at least 1.
     * @return int Seconds.
     */
    public static function backoff_delay(int $failures): int {
        $failures = max(1, $failures);
        $delay = self::BACKOFF_BASE * (2 ** min($failures - 1, 10));
        return (int) min($delay, self::BACKOFF_MAX);
    }

    /**
     * The document rows of a course, grouped by module, in send order.
     *
     * @param int $courseid The course id.
     * @return array<int, \stdClass[]> Rows keyed by cmid.
     */
    public static function documents_by_module(int $courseid): array {
        global $DB;

        $grouped = [];
        $rows = $DB->get_records(self::DOC_TABLE, ['courseid' => $courseid], 'cmid ASC, ordinal ASC, id ASC');
        foreach ($rows as $row) {
            $grouped[(int) $row->cmid][] = $row;
        }
        return $grouped;
    }

    /**
     * The source ids of the module's last accepted document set, in send order.
     *
     * Only a destination that confirms asynchronously leaves document rows,
     * and a failed attempt drops them; an empty answer therefore means "not
     * known", not "nothing in the index" (ai_suite#242).
     *
     * @param int $cmid The course module id.
     * @return string[]
     */
    public static function sent_source_ids(int $cmid): array {
        global $DB;

        $ids = $DB->get_fieldset_sql(
            "SELECT sourceid FROM {" . self::DOC_TABLE . "} WHERE cmid = :cmid ORDER BY ordinal ASC, id ASC",
            ['cmid' => $cmid]
        );
        return array_values(array_map('strval', $ids));
    }

    /**
     * Course counters for the display: indexed, being processed, failed, nothing to index.
     *
     * Computed from rows the caller already holds, so the page needs no
     * second query.
     *
     * @param \stdClass[] $rows Module state rows.
     * @return array{indexed: int, processing: int, error: int, empty: int}
     */
    public static function counts_from_rows(array $rows): array {
        $counts = ['indexed' => 0, 'processing' => 0, 'error' => 0, 'empty' => 0];
        foreach ($rows as $row) {
            switch ($row->laststatus) {
                case self::STATUS_SUCCESS:
                    $counts['indexed']++;
                    break;
                case self::STATUS_QUEUED:
                    $counts['processing']++;
                    break;
                case self::STATUS_ERROR:
                    $counts['error']++;
                    break;
                case self::STATUS_EMPTY:
                    $counts['empty']++;
                    break;
            }
        }
        return $counts;
    }

    /**
     * Course counters for one destination.
     *
     * @param int $courseid The course id.
     * @param string $sinkid Only rows written to this destination.
     * @return array{indexed: int, processing: int, error: int, empty: int}
     */
    public static function course_counts(int $courseid, string $sinkid): array {
        return self::counts_from_rows(array_filter(
            self::for_course($courseid),
            static fn($row) => (string) $row->sink === $sinkid
        ));
    }

    /**
     * Insert or update the module's row.
     *
     * @param int $courseid The course id.
     * @param int $cmid The course module id.
     * @param array $values Field values without courseid/cmid/timemodified.
     * @return void
     */
    private static function upsert(int $courseid, int $cmid, array $values): void {
        global $DB;

        $values['courseid'] = $courseid;
        $values['timemodified'] = time();

        $existing = $DB->get_record(self::TABLE, ['cmid' => $cmid]);
        if ($existing) {
            $DB->update_record(self::TABLE, (object) (['id' => $existing->id, 'cmid' => $cmid] + $values));
            return;
        }
        $DB->insert_record(self::TABLE, (object) (['cmid' => $cmid] + $values));
    }
}

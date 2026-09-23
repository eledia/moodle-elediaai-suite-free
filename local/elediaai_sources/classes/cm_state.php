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
     * Whether the index already holds exactly this content for the module.
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
        if (!$row || $row->laststatus !== self::STATUS_SUCCESS) {
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
     * Record a failed attempt without overwriting what the index still holds.
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
     * @param string $sinkid Only count rows written to this destination.
     * @return array<int, array{success: int, retryable: int, exhausted: int}>
     */
    public static function summary_by_course(string $sinkid): array {
        global $DB;

        $sql = "SELECT courseid,
                       SUM(CASE WHEN laststatus = :ok THEN 1 ELSE 0 END) AS successes,
                       SUM(CASE WHEN laststatus = :err1 AND attempts < :max1 THEN 1 ELSE 0 END) AS retryables,
                       SUM(CASE WHEN laststatus = :err2 AND attempts >= :max2 THEN 1 ELSE 0 END) AS exhausteds
                  FROM {" . self::TABLE . "}
                 WHERE sink = :sink
              GROUP BY courseid";

        $summary = [];
        $rows = $DB->get_records_sql($sql, [
            'ok' => self::STATUS_SUCCESS,
            'err1' => self::STATUS_ERROR,
            'err2' => self::STATUS_ERROR,
            'max1' => self::MAX_ATTEMPTS,
            'max2' => self::MAX_ATTEMPTS,
            'sink' => $sinkid,
        ]);
        foreach ($rows as $row) {
            $summary[(int) $row->courseid] = [
                'success' => (int) $row->successes,
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

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

use local_elediaai_sources\sink\sink;

/**
 * Deletes the destination has not carried out yet, kept until it does.
 *
 * A delete can fail for reasons that pass: the pipeline refuses it with `409`
 * while a job for the same source id is still queued or running, a gateway
 * answers `5xx`, the network is down. Before this queue such a delete was
 * reported once in the cron log and then forgotten (ai_suite#242, #243): the
 * documents of a deleted activity, a deleted course or a removed folder file
 * stayed in the index, and the tutor could still cite them.
 *
 * Every entry names one delete exactly as it goes over the wire: a source id
 * and its scope (`exact` for one document, `prefix` for a module and all its
 * sub-documents), the destination and the tenant it was meant for. The
 * scheduled {@see task\delete_queue_task} retries due entries with an
 * exponential backoff and a limited number of attempts. Only transient
 * failures are retried (network, `409`, `429`, `5xx`); any other answer, and
 * the last allowed attempt, leave the entry as failed, where it stays visible
 * in the health report and on the reindex page instead of vanishing.
 *
 * A `404` is not "already deleted": the contract asks for `200` on a missing
 * document, so a `404` means the endpoint is wrong and counts as a failure
 * without retry (api-specification.md, Delete Document).
 *
 * Two rules keep a late delete from removing content that came back:
 *
 * - A prefix delete is only ever queued for a module that no longer exists
 *   (deleted module, deleted course). A module that still exists converges
 *   through the reconcile instead; a queued prefix delete would also remove
 *   whatever it sends later.
 * - An exact delete is dropped when the same source id is sent again before
 *   the delete went through ({@see cancel()}), or when it is part of the
 *   module's current document set by the time the delete is due.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_queue {
    /** @var string Backing table. */
    private const TABLE = 'local_elediaai_sources_delq';

    /** @var string Delete exactly this document. */
    public const SCOPE_EXACT = 'exact';

    /** @var string Delete the document and every sub-document beneath it. */
    public const SCOPE_PREFIX = 'prefix';

    /** @var string Waiting for its next attempt. */
    public const STATUS_PENDING = 'pending';

    /** @var string Given up: not retryable, or out of attempts. Kept for the report. */
    public const STATUS_FAILED = 'failed';

    /**
     * @var int Attempts before an entry is given up.
     *
     * With the backoff below the last attempt falls about 45 hours after the
     * first: long enough to ride out a weekend night's outage of the pipeline,
     * short enough that a permanent problem shows up as failed within two days.
     */
    public const MAX_ATTEMPTS = 10;

    /** @var int Seconds before the second attempt; doubles with every further one. */
    public const BACKOFF_BASE = 300;

    /** @var int Longest wait between two attempts. */
    public const BACKOFF_MAX = 43200;

    /** @var int Entries handled per task run. */
    public const BATCH = 200;

    /**
     * Put a delete into the queue, or refresh the entry that already names it.
     *
     * With `$failure` the call records a delete that was just tried and
     * failed: that attempt counts, and its answer decides whether and when
     * the next one comes. Without it the entry is due at once.
     *
     * An entry for the same source id and scope is replaced, not doubled:
     * deleting a course queues what the module rows know, and a module's own
     * delete may have queued the same id a moment earlier.
     *
     * @param string $sourceid The source id to delete.
     * @param string $scope SCOPE_EXACT or SCOPE_PREFIX.
     * @param string $sinkid The destination the delete is meant for.
     * @param int $courseid The course the documents belonged to, for the report.
     * @param int $cmid The course module, for the report and the guards.
     * @param array|null $failure The transport result of a failed first attempt.
     * @return void
     */
    public static function enqueue(
        string $sourceid,
        string $scope,
        string $sinkid,
        int $courseid,
        int $cmid,
        ?array $failure = null
    ): void {
        global $DB;

        if ($sourceid === '') {
            return;
        }
        if (!in_array($scope, [self::SCOPE_EXACT, self::SCOPE_PREFIX], true)) {
            throw new \coding_exception('Unknown delete scope: ' . $scope);
        }

        $now = time();
        $DB->delete_records(self::TABLE, ['sourceid' => $sourceid, 'scope' => $scope]);
        $entry = (object) [
            'sourceid' => $sourceid,
            'scope' => $scope,
            'sink' => $sinkid,
            'tenant' => tenant::id(),
            'courseid' => $courseid,
            'cmid' => $cmid,
            'status' => self::STATUS_PENDING,
            'attempts' => 0,
            'nextattempt' => $now,
            'lasthttpcode' => 0,
            'lasterror' => null,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $entry->id = $DB->insert_record(self::TABLE, $entry);

        if ($failure !== null) {
            self::record_failure($entry, $failure, $now);
        }
    }

    /**
     * Drop pending exact deletes of documents that are being sent again.
     *
     * Called before a document set goes out: a file that was removed and
     * then put back under the same id must not be deleted by the delete
     * that was queued in between.
     *
     * @param string[] $sourceids The source ids about to be sent.
     * @return void
     */
    public static function cancel(array $sourceids): void {
        global $DB;

        $sourceids = array_values(array_unique(array_filter(array_map('strval', $sourceids))));
        if ($sourceids === []) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($sourceids, SQL_PARAMS_NAMED, 'sid');
        $params['scope'] = self::SCOPE_EXACT;
        $DB->delete_records_select(self::TABLE, "scope = :scope AND sourceid {$insql}", $params);
    }

    /**
     * Work through the entries that are due.
     *
     * Nothing is attempted while the destination is not configured; that is
     * not a failed attempt and must not use up the budget. Entries written
     * for another destination are dropped without a request, the same rule
     * the weekly cleanup follows: the plugin never reaches into a destination
     * the site has left.
     *
     * @param sink $sink The active destination.
     * @param int|null $now The current time; time() when null.
     * @param int $limit Entries handled at most.
     * @return array{configured: bool, due: int, deleted: int, retry: int, failed: int, dropped: int}
     */
    public static function process_due(sink $sink, ?int $now = null, int $limit = self::BATCH): array {
        global $DB;

        $now = $now ?? time();
        $summary = ['configured' => true, 'due' => 0, 'deleted' => 0, 'retry' => 0, 'failed' => 0, 'dropped' => 0];

        if (!$sink->is_configured()) {
            $summary['configured'] = false;
            return $summary;
        }

        $entries = $DB->get_records_select(
            self::TABLE,
            'status = :pending AND nextattempt <= :now',
            ['pending' => self::STATUS_PENDING, 'now' => $now],
            'nextattempt ASC, id ASC',
            '*',
            0,
            $limit
        );
        $summary['due'] = count($entries);

        $currenttenant = tenant::id();
        foreach ($entries as $entry) {
            if ((string) $entry->sink !== $sink::id() || self::superseded($entry)) {
                $DB->delete_records(self::TABLE, ['id' => $entry->id]);
                $summary['dropped']++;
                continue;
            }

            // The service checks the source id against the tenant of the
            // current wwwroot and would answer 422 for good. Saying why here
            // is more use than that answer.
            if ((string) $entry->tenant !== $currenttenant) {
                self::give_up($entry, get_string('deletequeue_tenantchanged', 'local_elediaai_sources', (object) [
                    'old' => (string) $entry->tenant,
                    'new' => $currenttenant,
                ]), 0, $now);
                $summary['failed']++;
                continue;
            }

            $result = $sink->delete((string) $entry->sourceid, (string) $entry->scope);
            if (!empty($result['success'])) {
                $DB->delete_records(self::TABLE, ['id' => $entry->id]);
                mtrace(get_string('deletionsuccess', 'local_elediaai_sources', $entry->sourceid));
                $summary['deleted']++;
                continue;
            }

            if (self::record_failure($entry, $result, $now) === self::STATUS_PENDING) {
                $summary['retry']++;
            } else {
                $summary['failed']++;
            }
        }

        return $summary;
    }

    /**
     * Whether a failed delete is worth another attempt.
     *
     * Retried are the answers that pass by themselves: no answer at all
     * (network, timeout), `409` (a job for the id is still queued or
     * running), `429` (rate limit) and `5xx`. Everything else is an answer
     * about the request itself (`400`, `401`, `403`, `404`, `422`) and would
     * be the same next time.
     *
     * @param array $result The transport result row.
     * @return bool
     */
    public static function is_retryable(array $result): bool {
        $code = (int) ($result['http_code'] ?? 0);
        return $code === 0 || $code === 409 || $code === 429 || $code >= 500;
    }

    /**
     * Seconds to wait after the given number of failed attempts.
     *
     * 5, 10, 20, 40 minutes and so on, capped at twelve hours.
     *
     * @param int $attempts Failed attempts so far, at least 1.
     * @return int
     */
    public static function backoff_delay(int $attempts): int {
        $exponent = max(0, min(20, $attempts - 1));
        return (int) min(self::BACKOFF_MAX, self::BACKOFF_BASE * (2 ** $exponent));
    }

    /**
     * Counters for the health report.
     *
     * @return array{pending: int, failed: int, oldest: int} Oldest is the creation
     *         time of the oldest pending entry, 0 when none is pending.
     */
    public static function counts(): array {
        global $DB;

        $pending = $DB->count_records(self::TABLE, ['status' => self::STATUS_PENDING]);
        $failed = $DB->count_records(self::TABLE, ['status' => self::STATUS_FAILED]);
        $oldest = $pending > 0
            ? (int) $DB->get_field_select(self::TABLE, 'MIN(timecreated)', 'status = :pending', [
                'pending' => self::STATUS_PENDING,
            ])
            : 0;

        return ['pending' => $pending, 'failed' => $failed, 'oldest' => $oldest];
    }

    /**
     * The entries for the report, failed ones first, then by next attempt.
     *
     * @param int $limit Rows at most.
     * @return \stdClass[]
     */
    public static function entries(int $limit = 50): array {
        global $DB;

        $sql = "SELECT *
                  FROM {" . self::TABLE . "}
              ORDER BY CASE WHEN status = :failed THEN 0 ELSE 1 END, nextattempt ASC, id ASC";
        return array_values($DB->get_records_sql($sql, ['failed' => self::STATUS_FAILED], 0, $limit));
    }

    /**
     * Count a failed attempt and schedule the next one, or give up.
     *
     * @param \stdClass $entry The queue row.
     * @param array $result The transport result row.
     * @param int $now The current time.
     * @return string The entry's new status.
     */
    private static function record_failure(\stdClass $entry, array $result, int $now): string {
        global $DB;

        $code = (int) ($result['http_code'] ?? 0);
        $error = self::describe($result);
        $entry->attempts = (int) $entry->attempts + 1;

        if (!self::is_retryable($result) || $entry->attempts >= self::MAX_ATTEMPTS) {
            self::give_up($entry, $error, $code, $now);
            mtrace(get_string('deletequeue_gaveup', 'local_elediaai_sources', (object) [
                'source_id' => $entry->sourceid,
                'error' => $error,
            ]));
            return self::STATUS_FAILED;
        }

        $entry->status = self::STATUS_PENDING;
        $entry->nextattempt = $now + self::backoff_delay((int) $entry->attempts);
        $entry->lasthttpcode = $code;
        $entry->lasterror = $error;
        $entry->timemodified = $now;
        $DB->update_record(self::TABLE, $entry);
        mtrace(get_string('deletequeue_retry', 'local_elediaai_sources', (object) [
            'source_id' => $entry->sourceid,
            'error' => $error,
            'next' => userdate($entry->nextattempt),
        ]));
        return self::STATUS_PENDING;
    }

    /**
     * Mark the entry as given up, keeping it for the report.
     *
     * @param \stdClass $entry The queue row.
     * @param string $error Why.
     * @param int $code The last HTTP status, 0 when none.
     * @param int $now The current time.
     * @return void
     */
    private static function give_up(\stdClass $entry, string $error, int $code, int $now): void {
        global $DB;

        $entry->status = self::STATUS_FAILED;
        $entry->nextattempt = 0;
        $entry->lasthttpcode = $code;
        $entry->lasterror = $error;
        $entry->timemodified = $now;
        $DB->update_record(self::TABLE, $entry);
    }

    /**
     * Whether the entry no longer describes something to delete.
     *
     * An exact delete of a document that belongs to the module's current set
     * again would remove fresh content. A prefix delete of a module that
     * exists would do the same for the whole module; the queue only takes
     * prefix deletes of deleted modules, so this is a safety net.
     *
     * @param \stdClass $entry The queue row.
     * @return bool
     */
    private static function superseded(\stdClass $entry): bool {
        global $DB;

        if ((string) $entry->scope === self::SCOPE_EXACT) {
            return $DB->record_exists('local_elediaai_sources_docstate', ['sourceid' => $entry->sourceid]);
        }
        return (int) $entry->cmid > 0 && $DB->record_exists('course_modules', ['id' => (int) $entry->cmid]);
    }

    /**
     * A short description of a failed delete.
     *
     * @param array $result The transport result row.
     * @return string
     */
    private static function describe(array $result): string {
        $code = (int) ($result['http_code'] ?? 0);
        $error = trim((string) ($result['error'] ?? ''));
        if ($code > 0) {
            $text = 'HTTP ' . $code;
            return $error !== '' ? $text . ': ' . \core_text::substr($error, 0, 200) : $text;
        }
        return $error !== '' ? \core_text::substr($error, 0, 255) : get_string('deletequeue_noanswer', 'local_elediaai_sources');
    }
}

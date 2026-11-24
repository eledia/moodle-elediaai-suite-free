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
use local_elediaai_sources\sink\sink_manager;
use local_elediaai_sources\sink\status_reporting_sink;

/**
 * Reads the processing status of accepted documents back from the destination.
 *
 * The pipeline's 202 only means "job stored" (ai_suite#232). This service
 * asks, per course, for the documents still in flight, takes the answer over
 * into the module state and carries the course flags along. Two callers share
 * it so that mapping, timeout and backoff exist exactly once:
 *
 * - the scheduled task {@see task\status_sync_task}, every five minutes, for
 *   every course whose next check is due ({@see run_due()});
 * - the course page, right after it rendered and on the "Refresh status"
 *   button, for one course ({@see sync_course()} with $ondemand).
 *
 * Only documents in flight are asked about; finished ones never again (D-b).
 * With nothing in flight there is no request at all. One request carries at
 * most 500 ids, so a course costs ceil(open / 500) requests per run (AC-007).
 * The page is throttled per course on the server: at most one check every
 * {@see ONDEMAND_MIN_INTERVAL} seconds, none during a failure backoff, and
 * never two at once (TD-022).
 *
 * A destination without a status API -- LiteRAG -- makes all of this a no-op.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class status_sync {
    /** @var int Seconds between two checks of one course from the course page (OPEN-011). */
    public const ONDEMAND_MIN_INTERVAL = 30;

    /** @var int Requests one check from the course page may make; the task does the rest. */
    public const ONDEMAND_MAX_BATCHES = 4;

    /** @var int Courses the task checks per run at most. */
    public const MAX_COURSES_PER_RUN = 200;

    /** @var int Seconds the task spends per run at most. */
    public const RUN_BUDGET_SECONDS = 120;

    /** @var string Lock type for "this course is being checked". */
    private const LOCK_TYPE = 'local_elediaai_sources_statussync';

    /** @var \core\lock\lock_factory|null Lock factory for course checks, shared within the request. */
    private static ?\core\lock\lock_factory $lockfactory = null;

    /** @var sink The destination asked. */
    private sink $sink;

    /**
     * Constructor.
     *
     * @param sink|null $sink Injected destination, for tests; the configured one when null.
     */
    public function __construct(?sink $sink = null) {
        $this->sink = $sink ?? sink_manager::active();
    }

    /**
     * Whether the destination reports a processing status at all.
     *
     * @return bool
     */
    public function applicable(): bool {
        return $this->sink instanceof status_reporting_sink && $this->sink->is_configured();
    }

    /**
     * Check the documents of one course that are still in flight.
     *
     * For the task ($ondemand false) only documents whose backoff has run out
     * are asked about. For the course page ($ondemand true) all documents in
     * flight are, but only when the course is not throttled; a throttled call
     * is no error, it simply asks nothing.
     *
     * @param int $courseid The course id.
     * @param bool $ondemand Whether a person asked from the course page.
     * @param int|null $now The current time; time() when null.
     * @return array{applicable: bool, nothinginflight: bool, throttled: bool, unavailable: bool,
     *               refreshed: bool, requests: int, checked: int, changed: int, nextallowed: int, error: string}
     */
    public function sync_course(int $courseid, bool $ondemand = false, ?int $now = null): array {
        $now ??= time();
        $result = [
            'applicable' => $this->applicable(),
            'nothinginflight' => false,
            'throttled' => false,
            'unavailable' => false,
            'refreshed' => false,
            'requests' => 0,
            'checked' => 0,
            'changed' => 0,
            'nextallowed' => 0,
            'error' => '',
        ];
        if (!$result['applicable']) {
            $result['nothinginflight'] = true;
            return $result;
        }

        $sinkid = $this->sink::id();
        $rows = cm_state::open_documents($courseid, $sinkid, $ondemand ? null : $now);
        if ($rows === []) {
            $result['nothinginflight'] = !cm_state::has_open_documents($courseid, $sinkid);
            return $result;
        }

        if ($ondemand) {
            $nextallowed = self::next_allowed($rows);
            if ($nextallowed > $now) {
                $result['throttled'] = true;
                $result['nextallowed'] = $nextallowed;
                return $result;
            }
        }

        $lock = self::lock_factory()->get_lock('course' . $courseid, 0);
        if (!$lock) {
            // Someone else is checking this course right now: another tab, or
            // the task and a click at the same moment.
            $result['throttled'] = true;
            $result['nextallowed'] = $now + self::ONDEMAND_MIN_INTERVAL;
            return $result;
        }

        try {
            cm_state::mark_checking(array_keys($rows), $now);

            $chunks = array_chunk($rows, status_reporting_sink::MAX_BATCH, true);
            if ($ondemand) {
                $chunks = array_slice($chunks, 0, self::ONDEMAND_MAX_BATCHES);
            }

            foreach ($chunks as $chunk) {
                $sourceids = array_values(array_map(static fn($row) => (string) $row->sourceid, $chunk));
                $answer = $this->sink->document_status($sourceids);
                $result['requests']++;

                if (empty($answer['available'])) {
                    // Status not available: nothing changes but the backoff
                    // of this chunk. The rest waits for the next run.
                    $reason = trim((string) ($answer['error'] ?? ''));
                    if ($reason === '') {
                        $reason = 'HTTP ' . (int) ($answer['http_code'] ?? 0);
                    }
                    cm_state::defer_status_check($chunk, $reason, $answer['retryafter'] ?? null, $now);
                    $result['unavailable'] = true;
                    $result['error'] = $reason;
                    break;
                }

                $outcome = cm_state::apply_remote_statuses(
                    $chunk,
                    (array) ($answer['documents'] ?? []),
                    $answer['retryafter'] ?? null,
                    $now
                );
                $result['checked'] += count($chunk);
                $result['changed'] += count(array_filter(
                    $outcome,
                    static fn(string $state) => !in_array($state, ['queued', 'unchanged'], true)
                ));
            }

            if ($result['checked'] > 0) {
                course_state::refresh_from_module_state($courseid);
            }
        } finally {
            $lock->release();
        }

        $result['refreshed'] = !$result['unavailable'] && $result['checked'] > 0;
        $result['nothinginflight'] = !cm_state::has_open_documents($courseid, $sinkid);
        $result['nextallowed'] = $now + self::ONDEMAND_MIN_INTERVAL;
        return $result;
    }

    /**
     * Check every course whose next check is due, the longest waiting first.
     *
     * Bounded by a number of courses and a time budget; what is left over
     * comes next run. With nothing in flight this is one query and no
     * request.
     *
     * @param int $maxcourses Courses per run at most.
     * @param int $budgetseconds Seconds per run at most.
     * @return array{applicable: bool, nothinginflight: bool, courses: int, requests: int,
     *               checked: int, changed: int, unavailable: int}
     */
    public function run_due(
        int $maxcourses = self::MAX_COURSES_PER_RUN,
        int $budgetseconds = self::RUN_BUDGET_SECONDS
    ): array {
        $summary = [
            'applicable' => $this->applicable(),
            'nothinginflight' => true,
            'courses' => 0,
            'requests' => 0,
            'checked' => 0,
            'changed' => 0,
            'unavailable' => 0,
        ];
        if (!$summary['applicable']) {
            return $summary;
        }

        $start = time();
        $courseids = cm_state::courses_due($this->sink::id(), $start, $maxcourses);
        $summary['nothinginflight'] = $courseids === [];

        foreach ($courseids as $courseid) {
            if (time() - $start >= $budgetseconds) {
                break;
            }
            $result = $this->sync_course($courseid, false);
            $summary['courses']++;
            $summary['requests'] += $result['requests'];
            $summary['checked'] += $result['checked'];
            $summary['changed'] += $result['changed'];
            $summary['unavailable'] += $result['unavailable'] ? 1 : 0;
        }
        return $summary;
    }

    /**
     * The factory of the "course is being checked" locks.
     *
     * One instance per request: some lock backends (PostgreSQL advisory
     * locks) are re-entrant within one database session and only tell a held
     * lock apart within the same factory.
     *
     * @return \core\lock\lock_factory
     */
    public static function lock_factory(): \core\lock\lock_factory {
        return self::$lockfactory ??= \core\lock\lock_config::get_lock_factory(self::LOCK_TYPE);
    }

    /**
     * When the course page may ask again for these documents.
     *
     * The later of: the last attempt plus the minimum interval, and -- while a
     * failure backoff runs -- the earliest next check of the failing rows.
     * Retry-After is part of that next check.
     *
     * @param \stdClass[] $rows The course's documents in flight.
     * @return int Unix time; 0 when nothing holds the check back.
     */
    private static function next_allowed(array $rows): int {
        $lastcheck = 0;
        $backoff = null;
        foreach ($rows as $row) {
            $lastcheck = max($lastcheck, (int) $row->timelastcheck);
            if ((int) $row->checkfailures > 0) {
                $backoff = $backoff === null ? (int) $row->nextcheck : min($backoff, (int) $row->nextcheck);
            }
        }

        $allowed = $lastcheck > 0 ? $lastcheck + self::ONDEMAND_MIN_INTERVAL : 0;
        return max($allowed, (int) ($backoff ?? 0));
    }
}

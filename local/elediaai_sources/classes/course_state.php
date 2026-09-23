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

use local_elediaai_sources\sink\sink_manager;

/**
 * Tracks per-course ingestion state and reconciles it with the marking.
 *
 * The marking ({@see course_gate::should_ingest()}) expresses the *desired*
 * state; this class records the *actual* state and acts on the transitions.
 *
 * Two questions are recorded separately, because they have different readers
 * and different answers:
 *
 * - `ingested` — is at least one document of this course in the index? This is
 *   what a consumer such as the tutor asks before grounding its answers in
 *   course sources.
 * - `pending` — is anything still outstanding? This is what the reconcile
 *   asks before doing more work.
 *
 * A partly ingested course has both set. Folding them into one flag was the
 * bug this split fixes: a single failing document made the whole course count
 * as absent, so the tutor fell back to general knowledge even though most of
 * the course was already in the index — permanently, because that document
 * kept failing.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_state {
    /** @var string Backing table. */
    private const TABLE = 'local_elediaai_sources_course';

    /**
     * Whether at least one document of the course is in the *current* index.
     *
     * "Current" is what makes a target switch work without extra machinery: a
     * row counts only when the destination, embedding model and tenant it was
     * written with still match the configured ones. Change any of them and
     * every course diverges, so the existing reconcile re-ingests it.
     *
     * @param int $courseid The course id.
     * @return bool
     */
    public static function is_ingested(int $courseid): bool {
        $record = self::record($courseid);
        return $record !== null && (int) $record->ingested === 1;
    }

    /**
     * Which of these courses have an index, asked in one query.
     *
     * {@see is_ingested()} answers for one course and is right where one
     * course is meant. A chat turn has to ask for a whole selection at once --
     * a category can hold three hundred courses, and three hundred round trips
     * per question is not a thing to do.
     *
     * @param int[] $courseids The courses to ask about; an empty list asks nothing.
     * @return int[] Those of them that are ingested, ascending.
     */
    public static function ingested_within(array $courseids): array {
        global $DB;

        $courseids = array_values(array_unique(array_filter(
            array_map('intval', $courseids),
            static fn(int $id): bool => $id > 0
        )));
        if ($courseids === []) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
        $params['ingested'] = 1;
        $found = $DB->get_fieldset_select(
            self::TABLE,
            'courseid',
            "courseid {$insql} AND ingested = :ingested",
            $params
        );

        $ids = array_map('intval', $found);
        sort($ids);
        return $ids;
    }

    /**
     * Whether documents of the course are still outstanding.
     *
     * Read separately from {@see is_ingested()}: a partly ingested course
     * answers true to both, and the reconcile must keep working on it while
     * consumers already use what is there.
     *
     * @param int $courseid The course id.
     * @return bool
     */
    public static function has_pending(int $courseid): bool {
        $record = self::record($courseid);
        return $record !== null && (int) $record->pending === 1;
    }

    /**
     * Whether the course has been worked through for the current target.
     *
     * Distinct from {@see is_ingested()} on purpose: a course whose activities
     * are all empty or unsupported has nothing in the index and never will,
     * but it *is* reconciled. Asking "is it ingested?" instead would queue it
     * again on every run, which is the trap the old single flag fell into from
     * the other side.
     *
     * @param int $courseid The course id.
     * @return bool
     */
    public static function is_reconciled(int $courseid): bool {
        $record = self::record($courseid);
        return $record !== null && (int) $record->pending === 0;
    }

    /**
     * Whether any state is recorded for the current target.
     *
     * @param int $courseid The course id.
     * @return bool
     */
    private static function has_state(int $courseid): bool {
        return self::record($courseid) !== null;
    }

    /**
     * The course's state row, but only when it describes the current target.
     *
     * A row written for another destination, model or tenant describes an
     * index this site no longer uses, so it answers nothing about today.
     *
     * @param int $courseid The course id.
     * @return \stdClass|null
     */
    private static function record(int $courseid): ?\stdClass {
        global $DB;

        $record = $DB->get_record(self::TABLE, ['courseid' => $courseid]);
        if (!$record) {
            return null;
        }

        $target = self::current_target();
        $matches = (string) $record->sink === $target['sink']
            && (string) $record->embeddingmodel === $target['embeddingmodel']
            && (string) $record->tenant === $target['tenant'];

        return $matches ? $record : null;
    }

    /**
     * The state a course would be ingested into right now.
     *
     * Read fresh on every call rather than cached: saving the settings changes
     * the destination and immediately triggers the reconcile callback in the
     * same request, where a cached value would still be the old one.
     *
     * @return array{sink: string, embeddingmodel: string, tenant: string}
     */
    private static function current_target(): array {
        $sink = sink_manager::active();
        return [
            'sink' => $sink::id(),
            'embeddingmodel' => (string) ($sink->embedding_model() ?? ''),
            'tenant' => tenant::id(),
        ];
    }

    /**
     * Record the course's state for the current target.
     *
     * The target is always recorded, including when nothing reached the index:
     * "worked through, nothing to send" is a real state and must be
     * distinguishable from "never touched", or the course is queued again on
     * every run. Only {@see mark_purged()} clears the target.
     *
     * @param int $courseid The course id.
     * @param bool $ingested Whether at least one document is in the index.
     * @param bool $pending Whether documents are still outstanding.
     * @return void
     */
    public static function set_ingested(int $courseid, bool $ingested, bool $pending = false): void {
        self::write($courseid, $ingested, $pending, self::current_target());
    }

    /**
     * Record that the course belongs to no index any more.
     *
     * Clearing the target is what makes the row invisible to {@see record()},
     * so the course reads as "never touched" — which after a purge it
     * effectively is.
     *
     * @param int $courseid The course id.
     * @return void
     */
    public static function mark_purged(int $courseid): void {
        self::write($courseid, false, false, ['sink' => '', 'embeddingmodel' => '', 'tenant' => '']);
    }

    /**
     * Write the state row.
     *
     * @param int $courseid The course id.
     * @param bool $ingested Whether at least one document is in the index.
     * @param bool $pending Whether documents are still outstanding.
     * @param array{sink: string, embeddingmodel: string, tenant: string} $target The target to record.
     * @return void
     */
    private static function write(int $courseid, bool $ingested, bool $pending, array $target): void {
        global $DB;

        $values = [
            'ingested' => $ingested ? 1 : 0,
            'pending' => $pending ? 1 : 0,
            'sink' => $target['sink'],
            'embeddingmodel' => $target['embeddingmodel'],
            'tenant' => $target['tenant'],
            'timemodified' => time(),
        ];

        try {
            $existing = $DB->get_record(self::TABLE, ['courseid' => $courseid]);
            if ($existing) {
                $DB->update_record(self::TABLE, (object) (['id' => $existing->id] + $values));
                return;
            }
            $DB->insert_record(self::TABLE, (object) (['courseid' => $courseid] + $values));
            return;
        } catch (\dml_exception $e) {
            // A parallel cron worker may have inserted the row after our read.
            foreach ($values as $field => $value) {
                $DB->set_field(self::TABLE, $field, $value, ['courseid' => $courseid]);
            }
        }
    }

    /**
     * Reconcile one course: act on the difference between marking and state.
     *
     * After a target switch a marked course counts as not ingested and is
     * re-ingested into the new destination. An *un-marked* course is then not
     * purged from the old destination either — deliberately: at switch time the
     * old backend is often unreachable, and a failing purge must not block the
     * switch. Clearing the old destination is a manual job.
     *
     * @param int $courseid The course id.
     * @param ingestion_manager|null $manager Optional injected manager (tests).
     * @return string One of 'reindexed', 'partial', 'reindex_failed', 'purged', 'noop'.
     */
    public static function reconcile(int $courseid, ?ingestion_manager $manager = null): string {
        $desired = course_gate::should_ingest($courseid);

        if (!$desired) {
            if (!self::has_state($courseid)) {
                return 'noop';
            }
            $manager ??= new ingestion_manager();
            $manager->purge_course($courseid);
            self::mark_purged($courseid);
            return 'purged';
        }

        // Finished for this target — nothing left to do, whether or not the
        // course actually put anything in the index.
        if (self::is_reconciled($courseid)) {
            return 'noop';
        }

        $manager ??= new ingestion_manager();

        $wasingested = self::is_ingested($courseid);
        $results = $manager->reindex_course($courseid);
        // Unchanged modules report 'skipped' although their documents are in
        // the index, so this run's results alone understate the course. The
        // module state is asked as well (AI-80).
        $ingested = self::has_success_result($results) || $wasingested
            || self::has_indexed_documents($courseid);
        $pending = self::has_retryable_error($results);

        self::set_ingested($courseid, $ingested, $pending);

        if ($pending) {
            return $ingested ? 'partial' : 'reindex_failed';
        }
        // Nothing outstanding. Anything that failed has spent its budget; a
        // course that simply had nothing to send counts as done.
        if (!$ingested && self::has_error_result($results)) {
            return 'reindex_failed';
        }
        return 'reindexed';
    }

    /**
     * Whether the module state records documents of the course in the index.
     *
     * Counts successful module rows written to the active destination. This
     * is the index truth the per-module path maintains; the course flag is
     * derived from it where the two could otherwise drift apart (AI-80).
     *
     * @param int $courseid The course id.
     * @return bool
     */
    public static function has_indexed_documents(int $courseid): bool {
        global $DB;

        return $DB->record_exists('local_elediaai_sources_cmstate', [
            'courseid' => $courseid,
            'laststatus' => cm_state::STATUS_SUCCESS,
            'sink' => sink_manager::active()::id(),
        ]);
    }

    /**
     * Raise the course flag once a single module has reached the index.
     *
     * The event-driven path (ingest_module_task) only writes module state.
     * Without this, a course reconciled while it was still empty stays
     * "worked through, nothing in the index" forever: the reconcile skips it,
     * and consumers such as the tutor never ground in its content (AI-80).
     *
     * Deliberately one-way. Lowering the flag from module state is not safe
     * yet: courses ingested before the module state table existed have
     * documents in the index but no module rows.
     *
     * Only a course that already has a row for the current target is touched.
     * A course without one has never been reconciled for this target; the
     * reconcile will pick it up and index all of it, which a flag set here
     * would prevent.
     *
     * @param int $courseid The course id.
     * @return bool True when the flag was raised.
     */
    public static function sync_from_index(int $courseid): bool {
        $record = self::record($courseid);
        if ($record === null || (int) $record->ingested === 1) {
            return false;
        }
        if (!course_gate::should_ingest($courseid) || !self::has_indexed_documents($courseid)) {
            return false;
        }

        self::set_ingested($courseid, true, (int) $record->pending === 1);
        return true;
    }

    /**
     * Repair courses whose flag says "not ingested" although documents are indexed.
     *
     * Heals courses that fell into the gap before {@see sync_from_index()}
     * existed. One query for the candidates; nothing is re-sent.
     *
     * @return int Number of courses repaired.
     */
    public static function repair_ingested_flags(): int {
        global $DB;

        $sink = sink_manager::active();
        if (!$sink->is_configured()) {
            return 0;
        }

        $sql = "SELECT DISTINCT c.courseid
                  FROM {" . self::TABLE . "} c
                  JOIN {local_elediaai_sources_cmstate} s ON s.courseid = c.courseid
                 WHERE c.ingested = 0
                   AND c.sink = :coursesink
                   AND s.sink = :modulesink
                   AND s.laststatus = :ok";
        $courseids = $DB->get_fieldset_sql($sql, [
            'coursesink' => $sink::id(),
            'modulesink' => $sink::id(),
            'ok' => cm_state::STATUS_SUCCESS,
        ]);

        $repaired = 0;
        foreach ($courseids as $courseid) {
            if (self::sync_from_index((int) $courseid)) {
                $repaired++;
            }
        }
        return $repaired;
    }

    /**
     * Whether any module failed in this run, retryable or not.
     *
     * @param array<int, array> $results Result rows from ingestion_manager.
     * @return bool
     */
    private static function has_error_result(array $results): bool {
        foreach ($results as $result) {
            if (($result['status'] ?? '') === 'error') {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether any module reached the index in this run.
     *
     * @param array<int, array> $results Result rows from ingestion_manager.
     * @return bool
     */
    private static function has_success_result(array $results): bool {
        foreach ($results as $result) {
            if (($result['status'] ?? '') === 'success') {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether any module failed and is still worth retrying.
     *
     * A module that has spent its retry budget is reported as skipped, not as
     * an error, so it does not keep the course pending forever. Skipped
     * modules are not errors either: an empty or unsupported course would
     * otherwise be queued for good.
     *
     * @param array<int, array> $results Result rows from ingestion_manager.
     * @return bool
     */
    private static function has_retryable_error(array $results): bool {
        foreach ($results as $result) {
            if (($result['status'] ?? '') !== 'error') {
                continue;
            }
            $cmid = (int) ($result['cmid'] ?? 0);
            // A cmid of 0 means the whole run failed before reaching any
            // module — an unconfigured destination, for instance. Worth retrying.
            if ($cmid === 0 || !cm_state::is_exhausted($cmid)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Queue a reconcile task for every course whose marking and index state
     * diverge. Shared by the nightly task and the admin-setting callbacks, so
     * that changing a central list (pilot courses / category allow-list) takes
     * effect promptly rather than only at the next nightly run.
     *
     * A course that is ingested but still has outstanding documents is queued
     * too: marking and state agree there, yet the work is not finished.
     *
     * @return int Number of reconcile tasks queued.
     */
    public static function queue_divergent_reconciles(): int {
        global $DB;

        if (!sink_manager::active()->is_configured()) {
            return 0;
        }

        $queued = 0;
        $courses = $DB->get_recordset('course', null, 'id', 'id');
        foreach ($courses as $course) {
            $courseid = (int) $course->id;
            // Mirror what reconcile() would decide, so no task is queued for a
            // course that would immediately return 'noop'.
            if (course_gate::should_ingest($courseid)) {
                if (self::is_reconciled($courseid)) {
                    continue;
                }
            } else if (!self::has_state($courseid)) {
                continue;
            }
            $task = new task\reconcile_course_task();
            $task->set_custom_data(['courseid' => $courseid]);
            \core\task\manager::queue_adhoc_task($task, true);
            $queued++;
        }
        $courses->close();

        return $queued;
    }

    /**
     * Count courses that are allowed for ingestion but hold nothing yet.
     *
     * Counts the courses the status panel calls "waiting". A partly ingested
     * course is not one of them — see {@see partial_ingestion_count()}.
     *
     * @return int Number of courses waiting for their initial indexing.
     */
    public static function pending_ingestion_count(): int {
        global $DB;

        $pending = 0;
        $courses = $DB->get_recordset('course', null, 'id', 'id');
        foreach ($courses as $course) {
            $courseid = (int) $course->id;
            // Waiting means holding nothing yet and not yet worked through.
            // A partly ingested course holds content and belongs to
            // {@see partial_ingestion_count()}; a course with nothing to send
            // is finished, not waiting.
            $waiting = course_gate::should_ingest($courseid)
                && !self::is_ingested($courseid)
                && !self::is_reconciled($courseid);
            if ($waiting) {
                $pending++;
            }
        }
        $courses->close();

        return $pending;
    }

    /**
     * Count courses that are in the index but not completely.
     *
     * "Not completely" means either documents are still outstanding, or some
     * have spent their retry budget and were given up on. Both are worth
     * showing: the first resolves itself, the second needs a human.
     *
     * @return int Number of partly indexed courses.
     */
    public static function partial_ingestion_count(): int {
        global $DB;

        $summary = cm_state::summary_by_course(sink_manager::active()::id());

        $partial = 0;
        $courses = $DB->get_recordset('course', null, 'id', 'id');
        foreach ($courses as $course) {
            $courseid = (int) $course->id;
            if (!course_gate::should_ingest($courseid) || !self::is_ingested($courseid)) {
                continue;
            }
            $counts = $summary[$courseid] ?? ['retryable' => 0, 'exhausted' => 0];
            if (self::has_pending($courseid) || $counts['retryable'] > 0 || $counts['exhausted'] > 0) {
                $partial++;
            }
        }
        $courses->close();

        return $partial;
    }

    /**
     * Queue indexing for every course that is allowed but not finished.
     *
     * Covers both the courses that hold nothing yet and those still carrying
     * outstanding documents — the admin action "index released courses now"
     * should finish the job, not only start it. Unlike
     * {@see queue_divergent_reconciles()}, this intentionally does not queue
     * purge tasks.
     *
     * @return int Number of indexing tasks queued.
     */
    public static function queue_pending_ingestions(): int {
        global $DB;

        if (!sink_manager::active()->is_configured()) {
            return 0;
        }

        $queued = 0;
        $courses = $DB->get_recordset('course', null, 'id', 'id');
        foreach ($courses as $course) {
            $courseid = (int) $course->id;
            if (!course_gate::should_ingest($courseid)) {
                continue;
            }
            if (self::is_reconciled($courseid)) {
                continue;
            }
            $task = new task\reconcile_course_task();
            $task->set_custom_data(['courseid' => $courseid]);
            \core\task\manager::queue_adhoc_task($task, true);
            $queued++;
        }
        $courses->close();

        return $queued;
    }

    /**
     * Forget a course's state row (e.g. when the course is deleted).
     *
     * @param int $courseid The course id.
     * @return void
     */
    public static function forget(int $courseid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['courseid' => $courseid]);
    }
}

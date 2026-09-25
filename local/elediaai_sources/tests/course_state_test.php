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
 * Unit tests for course ingestion-state reconciliation.
 *
 * Uses empty courses so reconcile makes no HTTP calls (an empty course has no
 * modules to upsert or delete), letting us assert the state-transition logic
 * directly.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_sources\course_state
 */
final class course_state_test extends \advanced_testcase {
    /**
     * Configure the API so the manager is "configured".
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('sink_ingestionapi_baseurl', 'http://localhost:8001', 'local_elediaai_sources');
        set_config('sink_ingestionapi_apikey', 'k', 'local_elediaai_sources');
    }

    /**
     * Enabling a previously-unindexed course reconciles it and records state.
     *
     * The course is empty, so nothing reaches the index and `ingested` stays
     * false — that flag now answers "is anything of this course in the index?",
     * which for an empty course is no. What must be true is that the course
     * counts as worked through.
     */
    public function test_reconcile_enables(): void {
        $cat = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $cat->id]);
        set_config('enabledcategories', (string) $cat->id, 'local_elediaai_sources');

        $this->assertFalse(course_state::is_ingested((int) $course->id));

        $action = course_state::reconcile((int) $course->id);

        $this->assertSame('reindexed', $action);
        $this->assertTrue(course_state::is_reconciled((int) $course->id));
        $this->assertFalse(course_state::has_pending((int) $course->id));
    }

    /**
     * Failed reindex attempts report the failure and do not mark a course as
     * indexed.
     */
    public function test_reconcile_does_not_mark_failed_reindex_as_ingested(): void {
        $cat = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $cat->id]);
        set_config('enabledcategories', (string) $cat->id, 'local_elediaai_sources');

        $manager = new class ([[
            'cmid' => 17,
            'success' => false,
            'status' => 'error',
            'message' => 'transport failed',
        ]]) extends ingestion_manager {
            /** @var array<int, array> */
            private array $results;

            /**
             * Constructor.
             *
             * @param array<int, array> $results Result rows.
             */
            public function __construct(array $results) {
                $this->results = $results;
            }

            /**
             * Return injected reindex results.
             *
             * @param int $courseid Course id.
             * @param bool $force Unused in the double.
             * @return array<int, array>
             */
            public function reindex_course(int $courseid, bool $force = false): array {
                return $this->results;
            }
        };

        $this->assertSame('reindex_failed', course_state::reconcile((int) $course->id, $manager));
        $this->assertFalse(course_state::is_ingested((int) $course->id));
    }

    /**
     * Un-marking an indexed course purges it and clears state.
     */
    public function test_reconcile_disables_and_purges(): void {
        $cat = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $cat->id]);

        // Pretend it is currently indexed.
        course_state::set_ingested((int) $course->id, true);
        // Not marked (no categories enabled) → desired = false.
        set_config('enabledcategories', '', 'local_elediaai_sources');

        $action = course_state::reconcile((int) $course->id);

        $this->assertSame('purged', $action);
        $this->assertFalse(course_state::is_ingested((int) $course->id));
    }

    /**
     * No state change → no action.
     */
    public function test_reconcile_noop(): void {
        $course = $this->getDataGenerator()->create_course();
        // Desired false (opt-in default) and state false (default) → noop.
        $this->assertSame('noop', course_state::reconcile((int) $course->id));
    }

    /**
     * queue_divergent_reconciles() queues a task only where marking and state
     * diverge.
     */
    public function test_queue_divergent_reconciles(): void {
        global $DB;
        $cat = $this->getDataGenerator()->create_category();
        set_config('enabledcategories', (string) $cat->id, 'local_elediaai_sources');

        // Marked but not yet ingested → diverges → should be queued.
        $marked = $this->getDataGenerator()->create_course(['category' => $cat->id]);
        // Unmarked and not ingested → matches → not queued.
        $this->getDataGenerator()->create_course();

        $DB->delete_records(
            'task_adhoc',
            ['classname' => '\\local_elediaai_sources\\task\\reconcile_course_task']
        );

        $queued = course_state::queue_divergent_reconciles();

        $this->assertSame(1, $queued);
        $tasks = $DB->get_records(
            'task_adhoc',
            ['classname' => '\\local_elediaai_sources\\task\\reconcile_course_task']
        );
        $this->assertCount(1, $tasks);
        $this->assertEquals($marked->id, json_decode(reset($tasks)->customdata)->courseid);
    }

    /**
     * The recorded state carries the destination it was written for.
     */
    public function test_set_ingested_records_the_target(): void {
        global $DB, $CFG;

        set_config('sink', 'ingestionapi', 'local_elediaai_sources');
        $course = $this->getDataGenerator()->create_course();

        course_state::set_ingested((int) $course->id, true);

        $row = $DB->get_record('local_elediaai_sources_course', ['courseid' => $course->id]);
        $this->assertSame('ingestionapi', $row->sink);
        $this->assertSame(tenant::from_url($CFG->wwwroot), $row->tenant);
        // Neither destination reports a model today; the column stays empty
        // rather than carrying an invented value.
        $this->assertSame('', $row->embeddingmodel);
    }

    /**
     * A course that holds nothing keeps its destination on record.
     *
     * "Worked through, nothing in the index" must stay distinguishable from
     * "never touched", otherwise every reconcile run picks the course up again.
     */
    public function test_set_not_ingested_keeps_the_target(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        course_state::set_ingested((int) $course->id, true);
        course_state::set_ingested((int) $course->id, false);

        $row = $DB->get_record('local_elediaai_sources_course', ['courseid' => $course->id]);
        $this->assertSame(0, (int) $row->ingested);
        $this->assertSame('ingestionapi', $row->sink);
        $this->assertTrue(course_state::is_reconciled((int) $course->id));
    }

    /**
     * A purge clears the destination fields: the course is then in no index.
     */
    public function test_mark_purged_clears_the_target(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        course_state::set_ingested((int) $course->id, true);
        course_state::mark_purged((int) $course->id);

        $row = $DB->get_record('local_elediaai_sources_course', ['courseid' => $course->id]);
        $this->assertSame(0, (int) $row->ingested);
        $this->assertSame('', $row->sink);
        $this->assertSame('', $row->tenant);
        $this->assertFalse(course_state::is_reconciled((int) $course->id));
    }

    /**
     * Switching the destination makes an ingested course diverge again.
     */
    public function test_switching_the_target_makes_courses_diverge(): void {
        set_config('sink', 'ingestionapi', 'local_elediaai_sources');
        $course = $this->getDataGenerator()->create_course();

        course_state::set_ingested((int) $course->id, true);
        $this->assertTrue(course_state::is_ingested((int) $course->id));

        set_config('sink', 'literag', 'local_elediaai_sources');
        $this->assertFalse(course_state::is_ingested((int) $course->id));

        // Switching back finds the course where it was left.
        set_config('sink', 'ingestionapi', 'local_elediaai_sources');
        $this->assertTrue(course_state::is_ingested((int) $course->id));
    }

    /**
     * A tenant change has the same effect: the site moved, so its content is
     * filed under a different key at the destination.
     */
    public function test_changing_the_tenant_makes_courses_diverge(): void {
        global $CFG;

        $course = $this->getDataGenerator()->create_course();
        course_state::set_ingested((int) $course->id, true);
        $this->assertTrue(course_state::is_ingested((int) $course->id));

        $CFG->wwwroot = 'https://moved.example.org';
        $this->assertFalse(course_state::is_ingested((int) $course->id));
    }

    /**
     * A diverged course is queued for reconcile like any other.
     *
     * The divergence here is a tenant change rather than a target switch, so
     * the destination stays configured — see the next test for what happens
     * when it is not.
     */
    public function test_diverged_course_is_queued(): void {
        global $CFG;

        $cat = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $cat->id]);
        set_config('enabledcategories', (string) $cat->id, 'local_elediaai_sources');
        set_config('sink', 'ingestionapi', 'local_elediaai_sources');

        course_state::set_ingested((int) $course->id, true);
        $this->assertSame(0, course_state::queue_divergent_reconciles());

        $CFG->wwwroot = 'https://moved.example.org';
        $this->assertSame(1, course_state::queue_divergent_reconciles());
    }

    /**
     * Switching to a destination that is not configured queues nothing.
     *
     * The courses have diverged all the same; there is simply nowhere to send
     * them yet. Once the destination is configured, the ordinary reconcile
     * picks them up — which is why nothing needs to be remembered here.
     */
    public function test_switch_to_unconfigured_target_queues_nothing(): void {
        $cat = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $cat->id]);
        set_config('enabledcategories', (string) $cat->id, 'local_elediaai_sources');
        set_config('sink', 'ingestionapi', 'local_elediaai_sources');

        course_state::set_ingested((int) $course->id, true);

        // LiteRAG is not installed here, so the destination is unconfigured.
        set_config('sink', 'literag', 'local_elediaai_sources');

        $this->assertFalse(course_state::is_ingested((int) $course->id));
        $this->assertSame(0, course_state::queue_divergent_reconciles());
    }

    /**
     * A released course with nothing to send counts as reconciled.
     *
     * An empty course, or one whose activities have no extractor, puts nothing
     * in the index and never will. It must still be recorded as worked
     * through, otherwise every reconcile run queues it again for a result that
     * cannot change.
     */
    public function test_course_with_nothing_to_send_is_reconciled(): void {
        $cat = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $cat->id]);
        set_config('enabledcategories', (string) $cat->id, 'local_elediaai_sources');

        // No modules at all, so the manager reports no results.
        $manager = $this->manager_with([]);

        $this->assertSame('reindexed', course_state::reconcile((int) $course->id, $manager));
        $this->assertFalse(course_state::is_ingested((int) $course->id));
        $this->assertFalse(course_state::has_pending((int) $course->id));

        // The decisive part: it is not picked up again.
        $this->assertSame(0, course_state::queue_divergent_reconciles());
        $this->assertSame('noop', course_state::reconcile((int) $course->id, $manager));
    }

    /**
     * A manager double returning fixed reindex results.
     *
     * @param array<int, array> $results Result rows.
     * @return ingestion_manager
     */
    private function manager_with(array $results): ingestion_manager {
        return new class ($results) extends ingestion_manager {
            /** @var array<int, array> */
            private array $results;

            /**
             * Constructor.
             *
             * @param array<int, array> $results Result rows.
             */
            public function __construct(array $results) {
                $this->results = $results;
            }

            /**
             * Return injected reindex results.
             *
             * @param int $courseid Course id.
             * @param bool $force Unused in the double.
             * @return array<int, array>
             */
            public function reindex_course(int $courseid, bool $force = false): array {
                return $this->results;
            }
        };
    }

    /**
     * A partly successful run marks the course as ingested AND pending.
     *
     * This is the case the split exists for: one failing document must not
     * hide the documents that did arrive, or a consumer such as the tutor
     * falls back to general knowledge for the whole course.
     */
    public function test_reconcile_partial_sets_both_flags(): void {
        $cat = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $cat->id]);
        set_config('enabledcategories', (string) $cat->id, 'local_elediaai_sources');

        $manager = $this->manager_with([
            ['cmid' => 11, 'success' => true, 'status' => 'success', 'message' => ''],
            ['cmid' => 12, 'success' => false, 'status' => 'error', 'message' => 'boom'],
        ]);

        $this->assertSame('partial', course_state::reconcile((int) $course->id, $manager));
        $this->assertTrue(course_state::is_ingested((int) $course->id));
        $this->assertTrue(course_state::has_pending((int) $course->id));
    }

    /**
     * A fully successful run leaves nothing pending.
     */
    public function test_reconcile_complete_clears_pending(): void {
        $cat = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $cat->id]);
        set_config('enabledcategories', (string) $cat->id, 'local_elediaai_sources');

        $manager = $this->manager_with([
            ['cmid' => 11, 'success' => true, 'status' => 'success', 'message' => ''],
        ]);

        $this->assertSame('reindexed', course_state::reconcile((int) $course->id, $manager));
        $this->assertTrue(course_state::is_ingested((int) $course->id));
        $this->assertFalse(course_state::has_pending((int) $course->id));
    }

    /**
     * A document that has spent its retry budget stops keeping the course
     * pending — otherwise the reconcile would chase it every quarter hour.
     */
    public function test_exhausted_document_does_not_keep_the_course_pending(): void {
        $cat = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $cat->id]);
        set_config('enabledcategories', (string) $cat->id, 'local_elediaai_sources');

        for ($i = 0; $i < cm_state::MAX_ATTEMPTS; $i++) {
            cm_state::record_error((int) $course->id, 12, 'sid', 'boom', 'ingestionapi');
        }

        $manager = $this->manager_with([
            ['cmid' => 11, 'success' => true, 'status' => 'success', 'message' => ''],
            ['cmid' => 12, 'success' => false, 'status' => 'error', 'message' => 'boom'],
        ]);

        $this->assertSame('reindexed', course_state::reconcile((int) $course->id, $manager));
        $this->assertTrue(course_state::is_ingested((int) $course->id));
        $this->assertFalse(course_state::has_pending((int) $course->id));
    }

    /**
     * An ingested course with outstanding documents is queued again, although
     * marking and state agree.
     */
    public function test_queue_divergent_includes_pending_courses(): void {
        $cat = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $cat->id]);
        set_config('enabledcategories', (string) $cat->id, 'local_elediaai_sources');

        course_state::set_ingested((int) $course->id, true, false);
        $this->assertSame(0, course_state::queue_divergent_reconciles());

        course_state::set_ingested((int) $course->id, true, true);
        $this->assertSame(1, course_state::queue_divergent_reconciles());
    }

    /**
     * A selected module that never got an attempt is caught up (#32).
     *
     * The course counts as finished (`pending = 0`), yet one of its modules
     * has no state at all -- the event never arrived. Before, neither the
     * scheduled reconcile nor a targeted one touched the course again.
     */
    public function test_a_module_without_state_is_caught_up(): void {
        $cat = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $cat->id]);
        set_config('enabledcategories', (string) $cat->id, 'local_elediaai_sources');
        course_state::set_ingested((int) $course->id, true, false);
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        $this->assertSame([(int) $course->id], course_state::courses_with_unattempted_modules());
        $this->assertSame(1, course_state::queue_divergent_reconciles());
        $this->assertSame('reindexed', course_state::reconcile((int) $course->id, $this->manager_with([])));

        // Tried, nothing to send: recorded, and the course is left alone.
        cm_state::record_empty((int) $course->id, (int) $page->cmid, 'src', 'nothing', sink\sink_manager::active_id());
        $this->assertSame([], course_state::courses_with_unattempted_modules());
        $this->assertSame(0, course_state::queue_divergent_reconciles());
        $this->assertSame('noop', course_state::reconcile((int) $course->id, $this->manager_with([])));
    }

    /**
     * In opt-in mode only selected modules count, and hidden ones never do.
     */
    public function test_only_selected_visible_modules_count_in_optin_mode(): void {
        $cat = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $cat->id]);
        set_config('enabledcategories', (string) $cat->id, 'local_elediaai_sources');
        set_config('activitydefault', activity_gate::MODE_OPTIN, 'local_elediaai_sources');
        course_state::set_ingested((int) $course->id, true, false);
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $hidden = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'visible' => 0]);

        $this->assertSame([], course_state::courses_with_unattempted_modules());

        activity_gate::set_included((int) $course->id, (int) $hidden->cmid, true);
        $this->assertSame([], course_state::courses_with_unattempted_modules(), 'Hidden modules are not owed.');

        activity_gate::set_included((int) $course->id, (int) $page->cmid, true);
        $this->assertSame([(int) $course->id], course_state::courses_with_unattempted_modules((int) $course->id));
    }

    /**
     * The same course is offered to the bulk indexing action.
     */
    public function test_queue_pending_includes_partly_ingested_courses(): void {
        $cat = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $cat->id]);
        set_config('enabledcategories', (string) $cat->id, 'local_elediaai_sources');

        course_state::set_ingested((int) $course->id, true, true);

        $this->assertSame(1, course_state::queue_pending_ingestions());
        $this->assertSame(0, course_state::pending_ingestion_count(), 'It holds content, so it is not "waiting".');
        $this->assertSame(1, course_state::partial_ingestion_count());
    }

    /**
     * A released course, reconciled while empty.
     *
     * @return int The course id.
     */
    private function empty_reconciled_course(): int {
        $cat = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $cat->id]);
        $categories = array_filter(explode(',', (string) get_config('local_elediaai_sources', 'enabledcategories')));
        $categories[] = (string) $cat->id;
        set_config('enabledcategories', implode(',', $categories), 'local_elediaai_sources');
        $this->assertSame('reindexed', course_state::reconcile((int) $course->id, $this->manager_with([])));
        $this->assertFalse(course_state::is_ingested((int) $course->id));
        return (int) $course->id;
    }

    /**
     * AI-80: a module indexed after the course was reconciled empty raises
     * the course flag, and the course is not queued again for it.
     */
    public function test_sync_from_index_raises_flag_of_course_reconciled_while_empty(): void {
        $courseid = $this->empty_reconciled_course();
        cm_state::record_success($courseid, 11, 'sid11', 'hash', 'ingestionapi');

        $this->assertTrue(course_state::sync_from_index($courseid));
        $this->assertTrue(course_state::is_ingested($courseid));
        $this->assertFalse(course_state::has_pending($courseid));
        $this->assertSame(0, course_state::queue_divergent_reconciles());

        // Idempotent: nothing left to raise.
        $this->assertFalse(course_state::sync_from_index($courseid));
    }

    /**
     * AI-80: without indexed documents the flag stays down.
     */
    public function test_sync_from_index_needs_indexed_documents(): void {
        $courseid = $this->empty_reconciled_course();
        cm_state::record_error($courseid, 11, 'sid11', 'boom', 'ingestionapi');

        $this->assertFalse(course_state::sync_from_index($courseid));
        $this->assertFalse(course_state::is_ingested($courseid));
    }

    /**
     * AI-80: documents written to another destination do not count.
     */
    public function test_sync_from_index_ignores_other_destinations(): void {
        $courseid = $this->empty_reconciled_course();
        cm_state::record_success($courseid, 11, 'sid11', 'hash', 'literag');

        $this->assertFalse(course_state::sync_from_index($courseid));
        $this->assertFalse(course_state::is_ingested($courseid));
    }

    /**
     * AI-80: a course never reconciled for this target is left to the
     * reconcile, so the whole course gets indexed rather than one module.
     */
    public function test_sync_from_index_leaves_unreconciled_course_to_reconcile(): void {
        $cat = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $cat->id]);
        set_config('enabledcategories', (string) $cat->id, 'local_elediaai_sources');
        cm_state::record_success((int) $course->id, 11, 'sid11', 'hash', 'ingestionapi');

        $this->assertFalse(course_state::sync_from_index((int) $course->id));
        $this->assertFalse(course_state::is_ingested((int) $course->id));
        $this->assertSame(1, course_state::queue_divergent_reconciles());
    }

    /**
     * AI-80: a course that is no longer released is not raised.
     */
    public function test_sync_from_index_ignores_unreleased_course(): void {
        $courseid = $this->empty_reconciled_course();
        set_config('enabledcategories', '', 'local_elediaai_sources');
        cm_state::record_success($courseid, 11, 'sid11', 'hash', 'ingestionapi');

        $this->assertFalse(course_state::sync_from_index($courseid));
    }

    /**
     * AI-80: courses already stuck are repaired without re-sending anything.
     */
    public function test_repair_ingested_flags_heals_stuck_courses(): void {
        $stuck = $this->empty_reconciled_course();
        $empty = $this->empty_reconciled_course();
        cm_state::record_success($stuck, 11, 'sid11', 'hash', 'ingestionapi');

        $this->assertSame(1, course_state::repair_ingested_flags());
        $this->assertTrue(course_state::is_ingested($stuck));
        $this->assertFalse(course_state::is_ingested($empty));
        $this->assertSame(0, course_state::repair_ingested_flags());
    }

    /**
     * AI-80: a reconcile whose modules are all unchanged ('skipped') still
     * records the course as ingested when their documents are indexed.
     */
    public function test_reconcile_counts_unchanged_indexed_modules(): void {
        $cat = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $cat->id]);
        set_config('enabledcategories', (string) $cat->id, 'local_elediaai_sources');
        // Outstanding work keeps the course eligible for a reconcile run.
        course_state::set_ingested((int) $course->id, false, true);
        cm_state::record_success((int) $course->id, 11, 'sid11', 'hash', 'ingestionapi');

        $manager = $this->manager_with([
            ['cmid' => 11, 'success' => false, 'status' => 'skipped', 'message' => 'unchanged'],
        ]);

        $this->assertSame('reindexed', course_state::reconcile((int) $course->id, $manager));
        $this->assertTrue(course_state::is_ingested((int) $course->id));
        $this->assertFalse(course_state::has_pending((int) $course->id));
    }

    /**
     * forget() removes the state row (e.g. on course deletion).
     */
    public function test_forget(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        course_state::set_ingested((int) $course->id, true);
        $this->assertTrue($DB->record_exists('local_elediaai_sources_course', ['courseid' => $course->id]));

        course_state::forget((int) $course->id);
        $this->assertFalse($DB->record_exists('local_elediaai_sources_course', ['courseid' => $course->id]));
    }
}

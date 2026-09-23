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
 * Unit tests for the per-activity ingestion gate.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_sources\activity_gate
 */
final class activity_gate_test extends \advanced_testcase {
    /**
     * Untouched activities follow the site mode; opt-out is the default.
     */
    public function test_untouched_follows_mode(): void {
        $this->resetAfterTest();

        $this->assertTrue(activity_gate::should_ingest_cm(12345));

        set_config('activitydefault', activity_gate::MODE_OPTIN, 'local_elediaai_sources');
        $this->assertFalse(activity_gate::should_ingest_cm(12345));

        // Untouched is not the same as excluded: only an explicit exclusion
        // may ever trigger removal from the index.
        $this->assertFalse(activity_gate::is_explicitly_excluded(12345));
    }

    /**
     * An unknown mode value falls back to opt-out rather than to opt-in.
     */
    public function test_unknown_mode_falls_back_to_optout(): void {
        $this->resetAfterTest();
        set_config('activitydefault', 'garbage', 'local_elediaai_sources');
        $this->assertSame(activity_gate::MODE_OPTOUT, activity_gate::mode());
    }

    /**
     * An explicit decision wins over the mode — in both directions — and
     * survives a later mode switch.
     */
    public function test_explicit_decision_wins_and_survives_mode_switch(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        activity_gate::set_included(7, 100, false);
        activity_gate::set_included(7, 200, true);

        $this->assertFalse(activity_gate::should_ingest_cm(100));
        $this->assertTrue(activity_gate::is_explicitly_excluded(100));
        $this->assertTrue(activity_gate::should_ingest_cm(200));

        set_config('activitydefault', activity_gate::MODE_OPTIN, 'local_elediaai_sources');
        $this->assertFalse(activity_gate::should_ingest_cm(100));
        $this->assertTrue(activity_gate::should_ingest_cm(200));
    }

    /**
     * Setting a decision twice updates the one row instead of duplicating it.
     */
    public function test_set_included_is_idempotent(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        activity_gate::set_included(7, 100, false);
        activity_gate::set_included(7, 100, true);
        activity_gate::set_included(7, 100, true);

        $this->assertSame(1, $DB->count_records('local_elediaai_sources_cm', ['cmid' => 100]));
        $this->assertTrue(activity_gate::should_ingest_cm(100));
    }

    /**
     * Clearing a decision returns the activity to the mode default.
     */
    public function test_clear_returns_to_mode(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        set_config('activitydefault', activity_gate::MODE_OPTIN, 'local_elediaai_sources');
        activity_gate::set_included(7, 100, true);
        $this->assertTrue(activity_gate::should_ingest_cm(100));

        activity_gate::clear(100);
        $this->assertFalse(activity_gate::should_ingest_cm(100));
        $this->assertFalse(activity_gate::is_explicitly_excluded(100));
    }

    /**
     * decisions() returns the course's explicit decisions keyed by cmid.
     */
    public function test_decisions_bulk_read(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        activity_gate::set_included(7, 100, false);
        activity_gate::set_included(7, 200, true);
        activity_gate::set_included(8, 300, false);

        $this->assertSame([100 => false, 200 => true], activity_gate::decisions(7));
        $this->assertSame([], activity_gate::decisions(99));
    }

    /**
     * Switching opt-in → opt-out queues the convergence task; the other
     * direction does not, because it must never remove content.
     */
    public function test_mode_change_converges_only_the_safe_direction(): void {
        global $DB;
        $this->resetAfterTest();

        $classname = '\\local_elediaai_sources\\task\\converge_undecided_task';

        // Opt-out → opt-in: nothing to do, nothing queued.
        set_config('activitydefault_shadow', activity_gate::MODE_OPTOUT, 'local_elediaai_sources');
        set_config('activitydefault', activity_gate::MODE_OPTIN, 'local_elediaai_sources');
        activity_gate::note_mode_change();

        $this->assertSame(0, $DB->count_records('task_adhoc', ['classname' => $classname]));
        $this->assertSame(
            activity_gate::MODE_OPTIN,
            get_config('local_elediaai_sources', 'activitydefault_shadow'),
            'The shadow must track the new mode.'
        );

        // Opt-in → opt-out: undecided activities should enter the index.
        set_config('activitydefault', activity_gate::MODE_OPTOUT, 'local_elediaai_sources');
        activity_gate::note_mode_change();

        $this->assertSame(1, $DB->count_records('task_adhoc', ['classname' => $classname]));
        $this->assertSame(
            activity_gate::MODE_OPTOUT,
            get_config('local_elediaai_sources', 'activitydefault_shadow')
        );
    }

    /**
     * Saving the setting without an actual change queues nothing.
     */
    public function test_unchanged_mode_queues_nothing(): void {
        global $DB;
        $this->resetAfterTest();

        set_config('activitydefault_shadow', activity_gate::MODE_OPTOUT, 'local_elediaai_sources');
        set_config('activitydefault', activity_gate::MODE_OPTOUT, 'local_elediaai_sources');
        activity_gate::note_mode_change();

        $this->assertSame(0, $DB->count_records('task_adhoc', [
            'classname' => '\\local_elediaai_sources\\task\\converge_undecided_task',
        ]));
    }

    /**
     * The convergence task queues only undecided activities of released
     * courses — decided ones keep their decision.
     */
    public function test_convergence_task_queues_only_undecided(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $decided = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Decided</p>',
        ]);
        $undecided = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Undecided</p>',
        ]);
        set_config('enabledcategories', (string) $course->category, 'local_elediaai_sources');
        activity_gate::set_included((int) $course->id, (int) $decided->cmid, false);

        $DB->delete_records('task_adhoc', [
            'classname' => '\\local_elediaai_sources\\task\\ingest_module_task',
        ]);

        ob_start();
        (new \local_elediaai_sources\task\converge_undecided_task())->execute();
        ob_end_clean();

        $tasks = $DB->get_records('task_adhoc', [
            'classname' => '\\local_elediaai_sources\\task\\ingest_module_task',
        ]);
        $cmids = array_map(static fn($t) => (int) json_decode($t->customdata)->cmid, $tasks);

        $this->assertContains((int) $undecided->cmid, $cmids);
        $this->assertNotContains((int) $decided->cmid, $cmids, 'A decided activity keeps its decision.');
    }

    /**
     * forget_cm and forget_course remove exactly their rows.
     */
    public function test_forget_scopes(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        activity_gate::set_included(7, 100, false);
        activity_gate::set_included(7, 200, true);
        activity_gate::set_included(8, 300, false);

        activity_gate::forget_cm(100);
        $this->assertFalse($DB->record_exists('local_elediaai_sources_cm', ['cmid' => 100]));
        $this->assertTrue($DB->record_exists('local_elediaai_sources_cm', ['cmid' => 200]));

        activity_gate::forget_course(7);
        $this->assertFalse($DB->record_exists('local_elediaai_sources_cm', ['courseid' => 7]));
        $this->assertTrue($DB->record_exists('local_elediaai_sources_cm', ['cmid' => 300]));
    }
}

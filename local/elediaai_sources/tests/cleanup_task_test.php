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

use local_elediaai_sources\task\cleanup_task;

/**
 * Unit tests for the weekly convergence sweep.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_sources\task\cleanup_task
 */
final class cleanup_task_test extends \advanced_testcase {
    /**
     * Configure the ingestion API destination so deletes can be mocked.
     */
    private function configure_sink(): void {
        set_config('sink', 'ingestionapi', 'local_elediaai_sources');
        set_config('sink_ingestionapi_baseurl', 'http://localhost:8001', 'local_elediaai_sources');
        set_config('sink_ingestionapi_apikey', 'test-key', 'local_elediaai_sources');
    }

    /**
     * Run the task with output swallowed.
     */
    private function run_task(): void {
        ob_start();
        (new cleanup_task())->execute();
        ob_end_clean();
    }

    /**
     * A state row whose module is gone is cleaned from the index via the
     * STORED source id, then dropped.
     */
    public function test_orphaned_state_row_is_cleaned_via_stored_sourceid(): void {
        $this->resetAfterTest();
        $this->configure_sink();

        cm_state::record_success(7, 999999, 'frozen-tenant:course7:cmid999999', 'h', 'ingestionapi');

        \curl::mock_response('{"status": "ok"}');
        $this->run_task();

        $this->assertNull(cm_state::get(999999));
    }

    /**
     * A stale row pointing at a non-active destination is dropped WITHOUT a
     * delete — the old destination is never touched automatically.
     */
    public function test_stale_row_for_other_sink_dropped_without_http(): void {
        $this->resetAfterTest();
        $this->configure_sink();

        cm_state::record_success(7, 999998, 'sid', 'h', 'someothersink');

        // No mocked response on purpose: an HTTP call would fail the test.
        $this->run_task();

        $this->assertNull(cm_state::get(999998));
    }

    /**
     * A module hidden from learners is removed from the index by the sweep —
     * the blind spot left by visibility toggles firing no event.
     */
    public function test_hidden_module_state_is_removed(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->configure_sink();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Hidden</p>',
            'visible' => 0,
        ]);
        cm_state::record_success((int) $course->id, (int) $page->cmid, 'sid-hidden', 'h', 'ingestionapi');

        \curl::mock_response('{"status": "ok"}');
        $this->run_task();

        $this->assertNull(cm_state::get((int) $page->cmid));
    }

    /**
     * A visible module's state row survives the sweep.
     */
    public function test_visible_module_state_survives(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->configure_sink();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Visible</p>',
        ]);
        cm_state::record_success((int) $course->id, (int) $page->cmid, 'sid-visible', 'h', 'ingestionapi');

        $this->run_task();

        $this->assertNotNull(cm_state::get((int) $page->cmid));
    }

    /**
     * Orphaned decision rows are dropped; rows of existing modules stay.
     */
    public function test_orphaned_decision_rows_are_dropped(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->configure_sink();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Kept</p>',
        ]);
        activity_gate::set_included((int) $course->id, (int) $page->cmid, false);
        activity_gate::set_included(7, 888888, false);

        $this->run_task();

        $this->assertFalse($DB->record_exists('local_elediaai_sources_cm', ['cmid' => 888888]));
        $this->assertTrue($DB->record_exists('local_elediaai_sources_cm', ['cmid' => $page->cmid]));
    }
}

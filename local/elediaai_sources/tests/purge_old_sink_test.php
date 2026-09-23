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
use local_elediaai_sources\task\purge_old_sink_task;

/**
 * Unit tests for clearing the destination a site switched away from.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_sources\sink\sink_manager
 * @covers     \local_elediaai_sources\task\purge_old_sink_task
 */
final class purge_old_sink_test extends \advanced_testcase {
    /**
     * The shadow remembers the predecessor across a switch.
     */
    public function test_switch_records_the_previous_destination(): void {
        $this->resetAfterTest();

        set_config('sink', 'ingestionapi', 'local_elediaai_sources');
        set_config('sink_shadow', 'ingestionapi', 'local_elediaai_sources');
        $this->assertSame('', sink_manager::previous_id(), 'Nothing to clear before a switch.');

        set_config('sink', 'literag', 'local_elediaai_sources');
        sink_manager::note_switch();

        $this->assertSame('ingestionapi', sink_manager::previous_id());
        $this->assertSame(
            'literag',
            get_config('local_elediaai_sources', 'sink_shadow'),
            'The shadow tracks the new destination.'
        );
    }

    /**
     * Saving the setting without changing it records nothing.
     */
    public function test_unchanged_destination_records_nothing(): void {
        $this->resetAfterTest();

        set_config('sink', 'literag', 'local_elediaai_sources');
        set_config('sink_shadow', 'literag', 'local_elediaai_sources');
        sink_manager::note_switch();

        $this->assertSame('', sink_manager::previous_id());
    }

    /**
     * Switching back retargets the pending clean-up at the destination just
     * left, not at the one now in use again.
     *
     * A → B → A leaves documents in B, because the courses were re-ingested
     * there while B was active. A's own leftovers are not orphaned: they are
     * overwritten by the re-ingest, since source ids are deterministic.
     */
    public function test_switching_back_retargets_the_pending_purge(): void {
        $this->resetAfterTest();

        set_config('sink', 'ingestionapi', 'local_elediaai_sources');
        set_config('sink_shadow', 'ingestionapi', 'local_elediaai_sources');
        set_config('sink', 'literag', 'local_elediaai_sources');
        sink_manager::note_switch();
        $this->assertSame('ingestionapi', sink_manager::previous_id());

        set_config('sink', 'ingestionapi', 'local_elediaai_sources');
        sink_manager::note_switch();

        $this->assertSame('literag', sink_manager::previous_id(), 'B is now the destination left behind.');
    }

    /**
     * The active destination is never offered for clearing, even if a stale
     * config value names it.
     */
    public function test_active_destination_is_never_pending(): void {
        $this->resetAfterTest();

        set_config('sink', 'literag', 'local_elediaai_sources');
        set_config('previoussink', 'literag', 'local_elediaai_sources');

        $this->assertSame('', sink_manager::previous_id());
    }

    /**
     * instance() resolves any known destination, not just the active one.
     */
    public function test_instance_resolves_inactive_destinations(): void {
        $this->resetAfterTest();
        set_config('sink', 'literag', 'local_elediaai_sources');

        $this->assertNotNull(sink_manager::instance('ingestionapi'));
        $this->assertSame('ingestionapi', sink_manager::instance('ingestionapi')::id());
        $this->assertNull(sink_manager::instance('nosuchsink'));
    }

    /**
     * The task purges against the destination it was queued for, and leaves
     * the state of the destination now in use untouched.
     */
    public function test_purge_targets_the_recorded_destination_only(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Content</p>',
        ]);

        // The module already lives in the NEW destination.
        cm_state::record_success((int) $course->id, (int) $page->cmid, 'sid-new', 'h', 'literag');

        set_config('sink', 'literag', 'local_elediaai_sources');
        set_config('sink_ingestionapi_baseurl', 'http://localhost:8001', 'local_elediaai_sources');
        set_config('sink_ingestionapi_apikey', 'test-key', 'local_elediaai_sources');

        $task = new purge_old_sink_task();
        $task->set_custom_data(['courseid' => (int) $course->id, 'sinkid' => 'ingestionapi']);

        \curl::mock_response('{"status": "ok"}');
        ob_start();
        $task->execute();
        ob_end_clean();

        $row = cm_state::get((int) $page->cmid);
        $this->assertNotNull($row, 'Clearing the old destination must not erase what the new one holds.');
        $this->assertSame('literag', $row->sink);
    }

    /**
     * A task naming an unknown destination gives up quietly rather than
     * failing forever in the queue.
     */
    public function test_unknown_destination_is_skipped(): void {
        $this->resetAfterTest();

        $task = new purge_old_sink_task();
        $task->set_custom_data(['courseid' => 1, 'sinkid' => 'nosuchsink']);

        ob_start();
        $task->execute();
        $output = ob_get_clean();

        $this->assertStringContainsString('unknown destination', $output);
    }

    /**
     * An unconfigured destination fails loudly, so the queue retries instead
     * of reporting a purge that never reached anything.
     */
    public function test_unconfigured_destination_fails(): void {
        $this->resetAfterTest();

        set_config('sink_ingestionapi_baseurl', '', 'local_elediaai_sources');
        set_config('sink_ingestionapi_apikey', '', 'local_elediaai_sources');

        $task = new purge_old_sink_task();
        $task->set_custom_data(['courseid' => 1, 'sinkid' => 'ingestionapi']);

        $this->expectException(\moodle_exception::class);
        $task->execute();
    }
}

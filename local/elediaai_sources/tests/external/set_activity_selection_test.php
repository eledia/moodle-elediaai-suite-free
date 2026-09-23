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

namespace local_elediaai_sources\external;

use local_elediaai_sources\activity_gate;

/**
 * Unit tests for the activity-selection external function.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_sources\external\set_activity_selection
 */
final class set_activity_selection_test extends \advanced_testcase {
    /**
     * Create a course, a page and an enrolled editing teacher.
     *
     * @return array{0: \stdClass, 1: \stdClass, 2: \stdClass} Course, page, teacher.
     */
    private function setup_course(): array {
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Content</p>',
        ]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        return [$course, $page, $teacher];
    }

    /**
     * A teacher's exclusion is stored and the module's ingest task queued,
     * so the index converges without waiting for a form save.
     */
    public function test_exclusion_is_stored_and_task_queued(): void {
        global $DB;
        $this->resetAfterTest();

        [$course, $page, $teacher] = $this->setup_course();
        $this->setUser($teacher);

        $DB->delete_records('task_adhoc', [
            'classname' => '\\local_elediaai_sources\\task\\ingest_module_task',
        ]);

        $result = set_activity_selection::execute((int) $page->cmid, 'excluded');

        $this->assertFalse($result['effective']);
        $this->assertTrue(activity_gate::is_explicitly_excluded((int) $page->cmid));
        $this->assertGreaterThan(0, $DB->count_records('task_adhoc', [
            'classname' => '\\local_elediaai_sources\\task\\ingest_module_task',
        ]), 'The toggle must queue the same ingest task a form save gets.');
    }

    /**
     * The 'default' state drops the explicit row entirely.
     */
    public function test_default_state_clears_the_decision(): void {
        global $DB;
        $this->resetAfterTest();

        [$course, $page, $teacher] = $this->setup_course();
        $this->setUser($teacher);

        set_activity_selection::execute((int) $page->cmid, 'excluded');
        $this->assertTrue($DB->record_exists('local_elediaai_sources_cm', ['cmid' => $page->cmid]));

        $result = set_activity_selection::execute((int) $page->cmid, 'default');

        $this->assertFalse($DB->record_exists('local_elediaai_sources_cm', ['cmid' => $page->cmid]));
        $this->assertTrue($result['effective'], 'Opt-out mode: untouched means ingested.');
    }

    /**
     * A user without the capability is refused.
     */
    public function test_requires_capability(): void {
        $this->resetAfterTest();

        [$course, $page] = $this->setup_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        set_activity_selection::execute((int) $page->cmid, 'excluded');
    }

    /**
     * While the test-phase lock is on, the backend refuses like the UI hides.
     */
    public function test_respects_marking_lock(): void {
        $this->resetAfterTest();

        [$course, $page, $teacher] = $this->setup_course();
        set_config('lockcoursemarking', 1, 'local_elediaai_sources');
        $this->setUser($teacher);

        $this->expectException(\moodle_exception::class);
        set_activity_selection::execute((int) $page->cmid, 'excluded');
    }

    /**
     * An unknown state value is rejected.
     */
    public function test_rejects_unknown_state(): void {
        $this->resetAfterTest();

        [$course, $page, $teacher] = $this->setup_course();
        $this->setUser($teacher);

        $this->expectException(\invalid_parameter_exception::class);
        set_activity_selection::execute((int) $page->cmid, 'maybe');
    }
}

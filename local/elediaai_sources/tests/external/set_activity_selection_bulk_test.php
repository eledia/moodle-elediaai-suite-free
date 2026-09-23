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
 * Unit tests for the bulk activity-selection external function.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_sources\external\set_activity_selection_bulk
 */
final class set_activity_selection_bulk_test extends \advanced_testcase {
    /**
     * Create a course with two pages and an enrolled editing teacher.
     *
     * @return array{0: \stdClass, 1: \stdClass, 2: \stdClass, 3: \stdClass} Course, page A, page B, teacher.
     */
    private function setup_course(): array {
        $course = $this->getDataGenerator()->create_course();
        $a = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>A</p>',
        ]);
        $b = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>B</p>',
        ]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        return [$course, $a, $b, $teacher];
    }

    /**
     * One call stores the decision for every listed activity and queues their
     * ingest tasks.
     */
    public function test_bulk_exclusion_stores_all_and_queues_tasks(): void {
        global $DB;
        $this->resetAfterTest();

        [$course, $a, $b, $teacher] = $this->setup_course();
        $this->setUser($teacher);

        $DB->delete_records('task_adhoc', [
            'classname' => '\\local_elediaai_sources\\task\\ingest_module_task',
        ]);

        $result = set_activity_selection_bulk::execute(
            (int) $course->id,
            [(int) $a->cmid, (int) $b->cmid],
            'excluded'
        );

        $this->assertSame(2, $result['updated']);
        $this->assertTrue(activity_gate::is_explicitly_excluded((int) $a->cmid));
        $this->assertTrue(activity_gate::is_explicitly_excluded((int) $b->cmid));
        $this->assertSame(2, $DB->count_records('task_adhoc', [
            'classname' => '\\local_elediaai_sources\\task\\ingest_module_task',
        ]));
    }

    /**
     * The 'default' state clears the decisions again.
     */
    public function test_bulk_default_clears_decisions(): void {
        global $DB;
        $this->resetAfterTest();

        [$course, $a, $b, $teacher] = $this->setup_course();
        $this->setUser($teacher);

        set_activity_selection_bulk::execute((int) $course->id, [(int) $a->cmid, (int) $b->cmid], 'excluded');
        set_activity_selection_bulk::execute((int) $course->id, [(int) $a->cmid, (int) $b->cmid], 'default');

        $this->assertFalse($DB->record_exists('local_elediaai_sources_cm', ['cmid' => $a->cmid]));
        $this->assertFalse($DB->record_exists('local_elediaai_sources_cm', ['cmid' => $b->cmid]));
    }

    /**
     * A cmid from another course aborts the whole call — before anything is
     * written, so a section never ends up half-flipped.
     */
    public function test_foreign_cmid_aborts_without_writing(): void {
        global $DB;
        $this->resetAfterTest();

        [$course, $a, , $teacher] = $this->setup_course();
        $other = $this->getDataGenerator()->create_course();
        $foreign = $this->getDataGenerator()->create_module('page', [
            'course' => $other->id,
            'content' => '<p>Foreign</p>',
        ]);
        $this->setUser($teacher);

        try {
            set_activity_selection_bulk::execute(
                (int) $course->id,
                [(int) $a->cmid, (int) $foreign->cmid],
                'excluded'
            );
            $this->fail('A foreign cmid must be rejected.');
        } catch (\invalid_parameter_exception $e) {
            $this->assertStringContainsString('does not belong', $e->getMessage());
        }

        $this->assertFalse(
            $DB->record_exists('local_elediaai_sources_cm', ['cmid' => $a->cmid]),
            'Nothing may be written when one cmid is rejected.'
        );
    }

    /**
     * A user without the capability is refused.
     */
    public function test_requires_capability(): void {
        $this->resetAfterTest();

        [$course, $a] = $this->setup_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        set_activity_selection_bulk::execute((int) $course->id, [(int) $a->cmid], 'excluded');
    }

    /**
     * While the test-phase lock is on, the backend refuses.
     */
    public function test_respects_marking_lock(): void {
        $this->resetAfterTest();

        [$course, $a, , $teacher] = $this->setup_course();
        set_config('lockcoursemarking', 1, 'local_elediaai_sources');
        $this->setUser($teacher);

        $this->expectException(\moodle_exception::class);
        set_activity_selection_bulk::execute((int) $course->id, [(int) $a->cmid], 'excluded');
    }

    /**
     * An unknown state value is rejected.
     */
    public function test_rejects_unknown_state(): void {
        $this->resetAfterTest();

        [$course, $a, , $teacher] = $this->setup_course();
        $this->setUser($teacher);

        $this->expectException(\invalid_parameter_exception::class);
        set_activity_selection_bulk::execute((int) $course->id, [(int) $a->cmid], 'maybe');
    }
}

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

namespace webservice_elediamcp;

use advanced_testcase;
use assign;
use context_module;
use stdClass;
use webservice_elediamcp\local\ai\tool_exception;
use webservice_elediamcp\local\ai\tools\moodle_course_health;
use webservice_elediamcp\local\ai\tools\moodle_grade_submission;
use webservice_elediamcp\local\ai\tools\moodle_grading_queue;
use webservice_elediamcp\local\ai\tools\moodle_read_submission;

/**
 * Marking allocation in the grading-loop tools (read and grade only allocated students).
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \webservice_elediamcp\local\ai\tools\grading_guard
 * @covers      \webservice_elediamcp\local\ai\tools\moodle_read_submission
 * @covers      \webservice_elediamcp\local\ai\tools\moodle_grade_submission
 * @covers      \webservice_elediamcp\local\ai\tools\moodle_grading_queue
 * @covers      \webservice_elediamcp\local\ai\tools\moodle_course_health
 */
final class visibility_marking_allocation_test extends advanced_testcase {
    /**
     * Course with a marker (non-editing teacher), a lead (editing teacher) and two students;
     * student "allocated" is allocated to the marker, student "other" is not.
     *
     * @param bool $allocation Enable marking workflow and allocation.
     * @return stdClass
     */
    private function fixture(bool $allocation = true): stdClass {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $gen = $this->getDataGenerator();
        $f = new stdClass();
        $f->course = $gen->create_course();
        $f->marker = $gen->create_and_enrol($f->course, 'teacher');
        $f->lead = $gen->create_and_enrol($f->course, 'editingteacher');
        $f->allocated = $gen->create_and_enrol($f->course, 'student', ['firstname' => 'Ada', 'lastname' => 'Allocated']);
        $f->other = $gen->create_and_enrol($f->course, 'student', ['firstname' => 'Otto', 'lastname' => 'Otherone']);
        $instance = $gen->create_module('assign', [
            'course' => $f->course->id,
            'name' => 'Essay',
            'grade' => 100,
            'markingworkflow' => $allocation ? 1 : 0,
            'markingallocation' => $allocation ? 1 : 0,
        ]);
        $f->cm = get_coursemodule_from_instance('assign', $instance->id, $f->course->id, false, MUST_EXIST);
        $assign = new assign(context_module::instance((int) $f->cm->id), $f->cm, $f->course);
        if ($allocation) {
            if (method_exists($assign, 'update_allocated_markers')) {
                // Moodle 5.x: assign_allocated_marker.
                $assign->update_allocated_markers((int) $f->allocated->id, [(int) $f->marker->id]);
            } else {
                // Moodle 4.5: assign_user_flags.allocatedmarker.
                $flags = $assign->get_user_flags((int) $f->allocated->id, true);
                $flags->allocatedmarker = (int) $f->marker->id;
                $assign->update_user_flags($flags);
            }
        }
        return $f;
    }

    /**
     * Read arguments for one student.
     *
     * @param stdClass $f Fixture.
     * @param stdClass $student Student.
     * @return array
     */
    private function args(stdClass $f, stdClass $student): array {
        return ['cmid' => (int) $f->cm->id, 'user_id' => (int) $student->id];
    }

    /**
     * The marker reads and grades an allocated student.
     */
    public function test_marker_reaches_allocated_student(): void {
        $this->resetAfterTest();
        $f = $this->fixture();
        $this->setUser($f->marker);

        $read = moodle_read_submission::execute($this->args($f, $f->allocated), $f->marker);
        $this->assertArrayHasKey('submission', $read);
        $preview = moodle_grade_submission::execute($this->args($f, $f->allocated) + ['grade' => 70], $f->marker);
        $this->assertTrue($preview['requires_confirmation']);
    }

    /**
     * The marker may not read a student allocated to nobody / someone else.
     */
    public function test_marker_cannot_read_unallocated_student(): void {
        $this->resetAfterTest();
        $f = $this->fixture();
        $this->setUser($f->marker);

        try {
            moodle_read_submission::execute($this->args($f, $f->other), $f->marker);
            $this->fail('Expected tool_exception for a student not allocated to the marker.');
        } catch (tool_exception $e) {
            $this->assertStringContainsString('not a participant', $e->getMessage());
            $this->assertStringNotContainsString('Otherone', $e->getMessage());
        }
    }

    /**
     * The marker may not grade a student not allocated to them; nothing is stored.
     */
    public function test_marker_cannot_grade_unallocated_student(): void {
        global $DB;
        $this->resetAfterTest();
        $f = $this->fixture();
        $this->setUser($f->marker);

        try {
            moodle_grade_submission::execute($this->args($f, $f->other) + ['grade' => 50, 'confirm' => true], $f->marker);
            $this->fail('Expected tool_exception for a student not allocated to the marker.');
        } catch (tool_exception $e) {
            $this->assertStringContainsString('not a participant', $e->getMessage());
        }
        $this->assertFalse($DB->record_exists('assign_grades', ['userid' => $f->other->id]));
    }

    /**
     * Both students submit, so the counters show the difference between allocated and all.
     *
     * @param stdClass $f Fixture.
     * @return void
     */
    private function submit_both(stdClass $f): void {
        global $DB;
        foreach ([$f->allocated, $f->other] as $student) {
            $DB->insert_record('assign_submission', (object) [
                'assignment' => $f->cm->instance,
                'userid' => $student->id,
                'status' => 'submitted',
                'attemptnumber' => 0,
                'latest' => 1,
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
        }
    }

    /**
     * The grading queue counts only the allocated students for the marker, everything for a lead.
     */
    public function test_grading_queue_counts_only_allocated_students(): void {
        $this->resetAfterTest();
        $f = $this->fixture();
        $this->submit_both($f);
        $args = ['course_id' => (int) $f->course->id];

        $this->setUser($f->marker);
        $entry = moodle_grading_queue::execute($args, $f->marker)['assignments'][0];
        $this->assertSame(1, $entry['needs_grading_count']);
        $this->assertSame(1, $entry['submitted_count']);

        $this->setUser($f->lead);
        $entry = moodle_grading_queue::execute($args, $f->lead)['assignments'][0];
        $this->assertSame(2, $entry['needs_grading_count']);
        $this->assertSame(2, $entry['submitted_count']);
    }

    /**
     * The course report counts only the allocated students for the marker, everything for a lead.
     */
    public function test_course_health_counts_only_allocated_students(): void {
        $this->resetAfterTest();
        $f = $this->fixture();
        $this->submit_both($f);
        $args = ['course_id' => (int) $f->course->id];

        $this->setUser($f->marker);
        $entry = moodle_course_health::execute($args, $f->marker)['assignments'][0];
        $this->assertSame(1, $entry['submitted']);
        $this->assertSame(0, $entry['missing']);

        $this->setUser($f->lead);
        $entry = moodle_course_health::execute($args, $f->lead)['assignments'][0];
        $this->assertSame(2, $entry['submitted']);
        $this->assertSame(0, $entry['missing']);
    }

    /**
     * A person with mod/assign:manageallocations reaches every student; so does an admin.
     */
    public function test_manageallocations_and_admin_reach_all(): void {
        $this->resetAfterTest();
        $f = $this->fixture();

        $this->setUser($f->lead);
        $this->assertArrayHasKey('submission', moodle_read_submission::execute($this->args($f, $f->other), $f->lead));
        $this->assertArrayHasKey('submission', moodle_read_submission::execute($this->args($f, $f->allocated), $f->lead));

        $this->setAdminUser();
        $admin = get_admin();
        $this->assertArrayHasKey('submission', moodle_read_submission::execute($this->args($f, $f->other), $admin));
    }

    /**
     * Without marking allocation the marker still reaches every student.
     */
    public function test_without_allocation_unchanged(): void {
        $this->resetAfterTest();
        $f = $this->fixture(false);
        $this->setUser($f->marker);

        $this->assertArrayHasKey('submission', moodle_read_submission::execute($this->args($f, $f->other), $f->marker));
        $preview = moodle_grade_submission::execute($this->args($f, $f->other) + ['grade' => 60], $f->marker);
        $this->assertTrue($preview['requires_confirmation']);
    }

    /**
     * Marking workflow without allocation: the marker reaches every student.
     */
    public function test_workflow_without_allocation_unchanged(): void {
        global $DB;
        $this->resetAfterTest();
        $f = $this->fixture();
        $DB->set_field('assign', 'markingallocation', 0, ['id' => (int) $f->cm->instance]);
        $this->setUser($f->marker);

        $this->assertArrayHasKey('submission', moodle_read_submission::execute($this->args($f, $f->other), $f->marker));
    }

    /**
     * Allocation and separate groups together: allocated, but in a foreign group, is rejected.
     */
    public function test_allocated_student_in_foreign_group_rejected(): void {
        global $DB;
        $this->resetAfterTest();
        $f = $this->fixture();
        $gen = $this->getDataGenerator();
        $DB->set_field('course', 'groupmode', SEPARATEGROUPS, ['id' => $f->course->id]);
        $DB->set_field('course', 'groupmodeforce', 1, ['id' => $f->course->id]);
        rebuild_course_cache((int) $f->course->id, true);
        $mine = $gen->create_group(['courseid' => $f->course->id]);
        $foreign = $gen->create_group(['courseid' => $f->course->id]);
        $gen->create_group_member(['groupid' => $mine->id, 'userid' => $f->marker->id]);
        $gen->create_group_member(['groupid' => $foreign->id, 'userid' => $f->allocated->id]);
        $this->setUser($f->marker);

        $this->expectException(tool_exception::class);
        moodle_read_submission::execute($this->args($f, $f->allocated), $f->marker);
    }

    /**
     * Fail closed: the allocation list is built for the global user, a different acting user is refused.
     */
    public function test_allocation_fails_closed_for_other_global_user(): void {
        $this->resetAfterTest();
        $f = $this->fixture();
        $this->setUser($f->lead);

        $this->expectException(tool_exception::class);
        moodle_read_submission::execute($this->args($f, $f->allocated), $f->marker);
    }
}

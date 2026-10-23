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
use context_course;
use stdClass;
use webservice_elediamcp\local\ai\tool_exception;
use webservice_elediamcp\local\ai\tools\moodle_course_health;
use webservice_elediamcp\local\ai\tools\moodle_grading_queue;
use webservice_elediamcp\local\ai\tools\moodle_message_course_students;
use webservice_elediamcp\local\ai\tools\moodle_read_submission;

/**
 * Group visibility of the teacher tools (separate groups on course and activity level).
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \webservice_elediamcp\local\ai\tools\moodle_course_health
 * @covers      \webservice_elediamcp\local\ai\tools\moodle_grading_queue
 * @covers      \webservice_elediamcp\local\ai\tools\moodle_message_course_students
 * @covers      \webservice_elediamcp\local\ai\tools\grading_guard
 */
final class visibility_teacher_tools_test extends advanced_testcase {
    /**
     * Build a course with groups A (a1, a2), B (b1) and C (c1), one assignment with
     * submissions of a1 and b1, a tutor in group A without accessallgroups and a lead
     * teacher with accessallgroups.
     *
     * @param int $coursegroupmode Course group mode.
     * @param int $assigngroupmode Group mode of the assignment (used when the course mode is not forced).
     * @param bool $force Force the course group mode.
     * @return stdClass Fixture with course, cm, tutor, lead, students and groups.
     */
    private function fixture(int $coursegroupmode, int $assigngroupmode = NOGROUPS, bool $force = true): stdClass {
        global $DB;

        $gen = $this->getDataGenerator();
        $f = new stdClass();
        $f->course = $gen->create_course([
            'groupmode' => $coursegroupmode,
            'groupmodeforce' => $force ? 1 : 0,
        ]);
        $f->tutor = $gen->create_and_enrol($f->course, 'editingteacher', ['firstname' => 'Tutor', 'lastname' => 'Alpha']);
        $f->lead = $gen->create_and_enrol($f->course, 'editingteacher', ['firstname' => 'Lead', 'lastname' => 'Omega']);
        $f->a1 = $gen->create_and_enrol($f->course, 'student', ['firstname' => 'Anna', 'lastname' => 'Agroup']);
        $f->a2 = $gen->create_and_enrol($f->course, 'student', ['firstname' => 'Arno', 'lastname' => 'Agroup']);
        $f->b1 = $gen->create_and_enrol($f->course, 'student', ['firstname' => 'Berta', 'lastname' => 'Bgroupsecret']);
        $f->c1 = $gen->create_and_enrol($f->course, 'student', ['firstname' => 'Carl', 'lastname' => 'Cgroupsecret']);

        $f->groupa = $gen->create_group(['courseid' => $f->course->id, 'name' => 'A']);
        $f->groupb = $gen->create_group(['courseid' => $f->course->id, 'name' => 'B']);
        $f->groupc = $gen->create_group(['courseid' => $f->course->id, 'name' => 'C']);
        $memberships = [
            [$f->groupa, $f->a1], [$f->groupa, $f->a2], [$f->groupa, $f->tutor], [$f->groupb, $f->b1], [$f->groupc, $f->c1],
        ];
        foreach ($memberships as [$group, $member]) {
            $gen->create_group_member(['groupid' => $group->id, 'userid' => $member->id]);
        }

        // The tutor may not see all groups (an extra role prohibits it), the lead keeps the
        // default of editing teachers.
        $context = context_course::instance((int) $f->course->id);
        $noallgroups = create_role('No all groups ' . random_string(6), 'noallgroups' . random_string(6), '');
        assign_capability('moodle/site:accessallgroups', CAP_PROHIBIT, $noallgroups, $context->id, true);
        role_assign($noallgroups, $f->tutor->id, $context->id);

        $assign = $gen->create_module('assign', [
            'course' => $f->course->id,
            'name' => 'Essay',
            'grade' => 100,
            'groupmode' => $assigngroupmode,
        ]);
        $f->cm = get_coursemodule_from_instance('assign', $assign->id, $f->course->id, false, MUST_EXIST);
        foreach ([$f->a1, $f->b1] as $submitter) {
            $DB->insert_record('assign_submission', (object) [
                'assignment' => $assign->id,
                'userid' => $submitter->id,
                'status' => 'submitted',
                'attemptnumber' => 0,
                'latest' => 1,
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
        }
        accesslib_clear_all_caches_for_unit_testing();
        return $f;
    }

    /**
     * Encode a tool result for name checks.
     *
     * @param array $result Tool result.
     * @return string
     */
    private function dump(array $result): string {
        return json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    /**
     * The tutor role in the fixture really lacks accessallgroups and the lead has it.
     */
    public function test_fixture_roles(): void {
        $this->resetAfterTest();
        $f = $this->fixture(SEPARATEGROUPS);
        $context = context_course::instance((int) $f->course->id);
        $this->assertFalse(has_capability('moodle/site:accessallgroups', $context, $f->tutor->id));
        $this->assertTrue(has_capability('moodle/site:accessallgroups', $context, $f->lead->id));
        $this->assertTrue(has_capability('moodle/course:manageactivities', $context, $f->tutor->id));
        $this->assertTrue(has_capability('mod/assign:grade', \context_module::instance((int) $f->cm->id), $f->tutor->id));
    }

    /**
     * Separate groups: the tutor's health report covers only the own group.
     */
    public function test_course_health_separate_groups_tutor(): void {
        $this->resetAfterTest();
        $f = $this->fixture(SEPARATEGROUPS);
        $this->setUser($f->tutor);

        $result = moodle_course_health::execute(['course_id' => (int) $f->course->id], $f->tutor);
        $this->assertSame(2, $result['student_count']);
        $this->assertSame(2, $result['inactive_count']);
        $ids = array_column($result['inactive'], 'id');
        sort($ids);
        $expected = [(int) $f->a1->id, (int) $f->a2->id];
        sort($expected);
        $this->assertSame($expected, $ids);
        $this->assertSame(1, $result['assignments'][0]['submitted']);
        $this->assertSame(1, $result['assignments'][0]['missing']);
        $dump = $this->dump($result);
        $this->assertStringNotContainsString('Bgroupsecret', $dump);
        $this->assertStringNotContainsString('Cgroupsecret', $dump);
        $this->assertStringContainsString('Separate groups', $result['summary']);
    }

    /**
     * Separate groups: a teacher with accessallgroups still sees everybody.
     */
    public function test_course_health_separate_groups_accessallgroups(): void {
        $this->resetAfterTest();
        $f = $this->fixture(SEPARATEGROUPS);
        $this->setUser($f->lead);

        $result = moodle_course_health::execute(['course_id' => (int) $f->course->id], $f->lead);
        $this->assertSame(4, $result['student_count']);
        $this->assertSame(2, $result['assignments'][0]['submitted']);
        $this->assertSame(2, $result['assignments'][0]['missing']);
        $this->assertStringContainsString('Bgroupsecret', $this->dump($result));
        $this->assertStringNotContainsString('Separate groups', $result['summary']);
    }

    /**
     * Without group mode nothing changes for the tutor.
     */
    public function test_course_health_no_groups_unchanged(): void {
        $this->resetAfterTest();
        $f = $this->fixture(NOGROUPS);
        $this->setUser($f->tutor);

        $result = moodle_course_health::execute(['course_id' => (int) $f->course->id], $f->tutor);
        $this->assertSame(4, $result['student_count']);
        $this->assertSame(2, $result['assignments'][0]['submitted']);
        $this->assertStringContainsString('Cgroupsecret', $this->dump($result));
        $this->assertStringNotContainsString('Separate groups', $result['summary']);
    }

    /**
     * Visible groups do not restrict anything.
     */
    public function test_course_health_visible_groups_unchanged(): void {
        $this->resetAfterTest();
        $f = $this->fixture(VISIBLEGROUPS);
        $this->setUser($f->tutor);

        $result = moodle_course_health::execute(['course_id' => (int) $f->course->id], $f->tutor);
        $this->assertSame(4, $result['student_count']);
        $this->assertSame(2, $result['assignments'][0]['submitted']);
    }

    /**
     * A tutor in several groups sees the members of all own groups.
     */
    public function test_course_health_multiple_groups(): void {
        $this->resetAfterTest();
        $f = $this->fixture(SEPARATEGROUPS);
        $this->getDataGenerator()->create_group_member(['groupid' => $f->groupb->id, 'userid' => $f->tutor->id]);
        $this->setUser($f->tutor);

        $result = moodle_course_health::execute(['course_id' => (int) $f->course->id], $f->tutor);
        $this->assertSame(3, $result['student_count']);
        $this->assertSame(2, $result['assignments'][0]['submitted']);
        $dump = $this->dump($result);
        $this->assertStringContainsString('Bgroupsecret', $dump);
        $this->assertStringNotContainsString('Cgroupsecret', $dump);
    }

    /**
     * A tutor without any group sees nobody in a course with separate groups.
     */
    public function test_course_health_tutor_without_group(): void {
        $this->resetAfterTest();
        $f = $this->fixture(SEPARATEGROUPS);
        groups_remove_member($f->groupa->id, $f->tutor->id);
        $this->setUser($f->tutor);

        $result = moodle_course_health::execute(['course_id' => (int) $f->course->id], $f->tutor);
        $this->assertSame(0, $result['student_count']);
        $this->assertSame([], $result['inactive']);
        $this->assertSame(0, $result['assignments'][0]['submitted']);
        $this->assertSame(0, $result['assignments'][0]['missing']);
        $this->assertStringNotContainsString('group', strtolower(json_encode($result['inactive'])));
    }

    /**
     * Separate groups set only on the assignment narrow its counters, not the course list.
     */
    public function test_course_health_activity_separate_groups(): void {
        $this->resetAfterTest();
        $f = $this->fixture(NOGROUPS, SEPARATEGROUPS, false);
        $this->setUser($f->tutor);

        $result = moodle_course_health::execute(['course_id' => (int) $f->course->id], $f->tutor);
        $this->assertSame(4, $result['student_count']);
        $this->assertSame(1, $result['assignments'][0]['submitted']);
        $this->assertSame(1, $result['assignments'][0]['missing']);
        $this->assertStringContainsString('Separate groups', $result['summary']);
    }

    /**
     * Course total grade statistics only use the visible students.
     */
    public function test_course_health_grade_summary_separate_groups(): void {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        $this->resetAfterTest();
        $f = $this->fixture(SEPARATEGROUPS);
        $courseitem = \grade_item::fetch_course_item((int) $f->course->id);
        $courseitem->update_final_grade((int) $f->a1->id, 40, 'test');
        $courseitem->update_final_grade((int) $f->b1->id, 90, 'test');

        $this->setUser($f->tutor);
        $result = moodle_course_health::execute(['course_id' => (int) $f->course->id], $f->tutor);
        $this->assertSame(1, $result['grade_summary']['graded_students']);
        $this->assertEquals(40.0, $result['grade_summary']['max']);

        $this->setUser($f->lead);
        $result = moodle_course_health::execute(['course_id' => (int) $f->course->id], $f->lead);
        $this->assertSame(2, $result['grade_summary']['graded_students']);
        $this->assertEquals(90.0, $result['grade_summary']['max']);
    }

    /**
     * Separate groups: the grading queue counts only the tutor's group.
     */
    public function test_grading_queue_separate_groups(): void {
        $this->resetAfterTest();
        $f = $this->fixture(SEPARATEGROUPS);

        $this->setUser($f->tutor);
        $result = moodle_grading_queue::execute(['course_id' => (int) $f->course->id], $f->tutor);
        $this->assertSame(1, $result['total_needing_grading']);
        $this->assertSame(1, $result['assignments'][0]['needs_grading_count']);
        $this->assertSame(1, $result['assignments'][0]['submitted_count']);
        $this->assertStringContainsString('Separate groups', $result['summary']);

        $this->setUser($f->lead);
        $result = moodle_grading_queue::execute(['course_id' => (int) $f->course->id], $f->lead);
        $this->assertSame(2, $result['total_needing_grading']);
        $this->assertStringNotContainsString('Separate groups', $result['summary']);
    }

    /**
     * Grading queue: several own groups add up, no group means nothing to grade.
     */
    public function test_grading_queue_multiple_and_no_groups(): void {
        $this->resetAfterTest();
        $f = $this->fixture(SEPARATEGROUPS);
        $this->getDataGenerator()->create_group_member(['groupid' => $f->groupb->id, 'userid' => $f->tutor->id]);
        $this->setUser($f->tutor);
        $result = moodle_grading_queue::execute(['course_id' => (int) $f->course->id], $f->tutor);
        $this->assertSame(2, $result['total_needing_grading']);

        groups_remove_member($f->groupa->id, $f->tutor->id);
        groups_remove_member($f->groupb->id, $f->tutor->id);
        $result = moodle_grading_queue::execute(['course_id' => (int) $f->course->id, 'include_empty' => true], $f->tutor);
        $this->assertSame(0, $result['total_needing_grading']);
        $this->assertSame(0, $result['assignments'][0]['submitted_count']);
    }

    /**
     * Grading queue without group mode and with separate groups only on the activity.
     */
    public function test_grading_queue_no_groups_and_activity_level(): void {
        $this->resetAfterTest();
        $f = $this->fixture(NOGROUPS);
        $this->setUser($f->tutor);
        $result = moodle_grading_queue::execute(['course_id' => (int) $f->course->id], $f->tutor);
        $this->assertSame(2, $result['total_needing_grading']);
        $this->assertStringNotContainsString('Separate groups', $result['summary']);

        $g = $this->fixture(NOGROUPS, SEPARATEGROUPS, false);
        $this->setUser($g->tutor);
        $result = moodle_grading_queue::execute(['course_id' => (int) $g->course->id], $g->tutor);
        $this->assertSame(1, $result['total_needing_grading']);
    }

    /**
     * Non-editing teachers hold moodle/course:bulkmessaging and may message their group, like in Moodle.
     */
    public function test_message_allowed_for_non_editing_teacher(): void {
        $this->resetAfterTest();
        $f = $this->fixture(SEPARATEGROUPS);
        $gen = $this->getDataGenerator();
        $teacher = $gen->create_and_enrol($f->course, 'teacher', ['firstname' => 'Nonedit', 'lastname' => 'Teacher']);
        $gen->create_group_member(['groupid' => $f->groupa->id, 'userid' => $teacher->id]);
        $context = context_course::instance((int) $f->course->id);
        $this->assertFalse(has_capability('moodle/course:manageactivities', $context, $teacher->id));
        $this->assertFalse(has_capability('moodle/site:accessallgroups', $context, $teacher->id));

        $this->setUser($teacher);
        $preview = moodle_message_course_students::execute(
            ['course_id' => (int) $f->course->id, 'message' => 'Hello', 'target' => 'all'],
            $teacher
        );
        $this->assertSame(2, $preview['recipient_count']);
        $this->assertStringNotContainsString('Bgroupsecret', $this->dump($preview));
    }

    /**
     * Without moodle/course:bulkmessaging the tool is refused, even with moodle/course:manageactivities.
     */
    public function test_message_requires_bulkmessaging(): void {
        global $DB;
        $this->resetAfterTest();
        $f = $this->fixture(NOGROUPS);
        $context = context_course::instance((int) $f->course->id);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('moodle/course:bulkmessaging', CAP_PROHIBIT, $roleid, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($f->lead);
        $this->expectException(\required_capability_exception::class);
        moodle_message_course_students::execute(
            ['course_id' => (int) $f->course->id, 'message' => 'Hello', 'target' => 'all'],
            $f->lead
        );
    }

    /**
     * Separate groups: the recipient list holds only the tutor's group.
     */
    public function test_message_recipients_separate_groups(): void {
        $this->resetAfterTest();
        $f = $this->fixture(SEPARATEGROUPS);
        $args = ['course_id' => (int) $f->course->id, 'message' => 'Hello', 'target' => 'all'];

        $this->setUser($f->tutor);
        $preview = moodle_message_course_students::execute($args, $f->tutor);
        $this->assertSame(2, $preview['recipient_count']);
        $dump = $this->dump($preview);
        $this->assertStringContainsString('Agroup', $dump);
        $this->assertStringNotContainsString('Bgroupsecret', $dump);
        $this->assertStringNotContainsString('Cgroupsecret', $dump);

        // Confirmed sending reaches only the own group.
        $sink = $this->redirectMessages();
        $sent = moodle_message_course_students::execute($args + ['confirm' => true], $f->tutor);
        $this->assertSame(2, $sent['recipient_count']);
        $this->assertSame(2, $sent['sent_count'], 'Skipped: ' . json_encode($sent['skipped']));
        $sink->close();
        $tutorid = (int) $f->tutor->id;
        $this->assertNotEmpty(\core_message\api::get_conversation_between_users([$tutorid, (int) $f->a1->id]));
        $this->assertEmpty(\core_message\api::get_conversation_between_users([$tutorid, (int) $f->b1->id]));
        $this->assertEmpty(\core_message\api::get_conversation_between_users([$tutorid, (int) $f->c1->id]));

        $this->setUser($f->lead);
        $preview = moodle_message_course_students::execute($args, $f->lead);
        $this->assertSame(4, $preview['recipient_count']);
    }

    /**
     * Recipients without group mode stay unchanged; a tutor without group reaches nobody.
     */
    public function test_message_recipients_no_groups_and_without_group(): void {
        $this->resetAfterTest();
        $f = $this->fixture(NOGROUPS);
        $args = ['course_id' => (int) $f->course->id, 'message' => 'Hello', 'target' => 'all'];
        $this->setUser($f->tutor);
        $this->assertSame(4, moodle_message_course_students::execute($args, $f->tutor)['recipient_count']);

        $g = $this->fixture(SEPARATEGROUPS);
        groups_remove_member($g->groupa->id, $g->tutor->id);
        $this->setUser($g->tutor);
        $args['course_id'] = (int) $g->course->id;
        $result = moodle_message_course_students::execute($args, $g->tutor);
        $this->assertSame(0, $result['recipient_count']);
        $this->assertSame([], $result['recipients_preview']);
    }

    /**
     * not_submitted honours separate groups on activity level.
     */
    public function test_message_not_submitted_activity_separate_groups(): void {
        $this->resetAfterTest();
        $f = $this->fixture(NOGROUPS, SEPARATEGROUPS, false);
        $args = [
            'course_id' => (int) $f->course->id,
            'message' => 'Please submit',
            'target' => 'not_submitted',
            'assign_cmid' => (int) $f->cm->id,
        ];
        $this->setUser($f->tutor);
        $preview = moodle_message_course_students::execute($args, $f->tutor);
        $this->assertSame(1, $preview['recipient_count']);
        $this->assertSame([fullname($f->a2)], $preview['recipients_preview']);

        $this->setUser($f->lead);
        $preview = moodle_message_course_students::execute($args, $f->lead);
        // Lead sees a2 and c1 (b1 and a1 submitted).
        $this->assertSame(2, $preview['recipient_count']);
    }

    /**
     * Add a submitted latest attempt for a user.
     *
     * @param stdClass $f Fixture.
     * @param stdClass $user Submitting user.
     * @return void
     */
    private function submit(stdClass $f, stdClass $user): void {
        global $DB;
        $DB->insert_record('assign_submission', (object) [
            'assignment' => (int) $f->cm->instance,
            'userid' => $user->id,
            'status' => 'submitted',
            'attemptnumber' => 0,
            'latest' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Adds group D without participation; the tutor and student d1 (submitted) and d2 are members.
     *
     * @param stdClass $f Fixture.
     * @return void
     */
    private function add_nonparticipation_group(stdClass $f): void {
        $gen = $this->getDataGenerator();
        $f->groupd = $gen->create_group(['courseid' => $f->course->id, 'name' => 'D', 'participation' => 0]);
        $f->d1 = $gen->create_and_enrol($f->course, 'student', ['firstname' => 'Dora', 'lastname' => 'Dgroupsecret']);
        $f->d2 = $gen->create_and_enrol($f->course, 'student', ['firstname' => 'Dirk', 'lastname' => 'Dgroupsecret']);
        foreach ([$f->tutor, $f->d1, $f->d2] as $member) {
            $gen->create_group_member(['groupid' => $f->groupd->id, 'userid' => $member->id]);
        }
        $this->submit($f, $f->d1);
    }

    /**
     * Activity level: a group without participation does not count for the tutor's counters.
     */
    public function test_nonparticipation_group_counters(): void {
        $this->resetAfterTest();
        $f = $this->fixture(SEPARATEGROUPS);
        $this->add_nonparticipation_group($f);
        $this->setUser($f->tutor);

        $queue = moodle_grading_queue::execute(['course_id' => (int) $f->course->id], $f->tutor);
        $this->assertSame(1, $queue['total_needing_grading']);

        $health = moodle_course_health::execute(['course_id' => (int) $f->course->id], $f->tutor);
        $this->assertSame(1, $health['assignments'][0]['submitted']);
        $this->assertSame(1, $health['assignments'][0]['missing']);
    }

    /**
     * Activity level: members of a group without participation are no not_submitted recipients.
     */
    public function test_nonparticipation_group_recipients(): void {
        $this->resetAfterTest();
        $f = $this->fixture(SEPARATEGROUPS);
        $this->add_nonparticipation_group($f);
        $this->setUser($f->tutor);

        $preview = moodle_message_course_students::execute([
            'course_id' => (int) $f->course->id,
            'message' => 'Please submit',
            'target' => 'not_submitted',
            'assign_cmid' => (int) $f->cm->id,
        ], $f->tutor);
        $this->assertSame([fullname($f->a2)], $preview['recipients_preview']);
        $this->assertStringNotContainsString('Dgroupsecret', $this->dump($preview));
    }

    /**
     * Grading loop: a student only in a group without participation is out of reach.
     */
    public function test_nonparticipation_group_grading_guard(): void {
        $this->resetAfterTest();
        $f = $this->fixture(SEPARATEGROUPS);
        $this->add_nonparticipation_group($f);
        $this->setUser($f->tutor);

        $read = moodle_read_submission::execute(['cmid' => (int) $f->cm->id, 'user_id' => (int) $f->a1->id], $f->tutor);
        $this->assertSame('submitted', $read['submission']['status']);

        $this->expectException(tool_exception::class);
        moodle_read_submission::execute(['cmid' => (int) $f->cm->id, 'user_id' => (int) $f->d1->id], $f->tutor);
    }

    /**
     * Activity grouping: only the tutor's groups inside the grouping of the assignment count.
     */
    public function test_activity_grouping(): void {
        global $DB;
        $this->resetAfterTest();
        $f = $this->fixture(SEPARATEGROUPS);
        $gen = $this->getDataGenerator();
        // Tutor is in A and B; the assignment uses a grouping that contains only B.
        $gen->create_group_member(['groupid' => $f->groupb->id, 'userid' => $f->tutor->id]);
        $grouping = $gen->create_grouping(['courseid' => $f->course->id]);
        $gen->create_grouping_group(['groupingid' => $grouping->id, 'groupid' => $f->groupb->id]);
        $DB->set_field('course_modules', 'groupingid', (int) $grouping->id, ['id' => (int) $f->cm->id]);
        rebuild_course_cache((int) $f->course->id, true);
        $this->setUser($f->tutor);

        $queue = moodle_grading_queue::execute(['course_id' => (int) $f->course->id], $f->tutor);
        $this->assertSame(1, $queue['total_needing_grading']);
        $health = moodle_course_health::execute(['course_id' => (int) $f->course->id], $f->tutor);
        // Course level: A and B (3 students); assignment level: only B (b1 submitted).
        $this->assertSame(3, $health['student_count']);
        $this->assertSame(1, $health['assignments'][0]['submitted']);
        $this->assertSame(0, $health['assignments'][0]['missing']);
        $preview = moodle_message_course_students::execute([
            'course_id' => (int) $f->course->id,
            'message' => 'Please submit',
            'target' => 'not_submitted',
            'assign_cmid' => (int) $f->cm->id,
        ], $f->tutor);
        $this->assertSame(0, $preview['recipient_count']);
        $this->expectException(tool_exception::class);
        moodle_read_submission::execute(['cmid' => (int) $f->cm->id, 'user_id' => (int) $f->a1->id], $f->tutor);
    }

    /**
     * Course with separate groups not forced, assignment without groups: the assignment counters
     * cover everybody (as the assignment's own summary), the course lists stay limited.
     */
    public function test_course_health_course_level_not_forced(): void {
        $this->resetAfterTest();
        $f = $this->fixture(SEPARATEGROUPS, NOGROUPS, false);
        $this->setUser($f->tutor);

        $result = moodle_course_health::execute(['course_id' => (int) $f->course->id], $f->tutor);
        $this->assertSame(2, $result['student_count']);
        $this->assertStringNotContainsString('Bgroupsecret', $this->dump($result));
        $this->assertSame(2, $result['assignments'][0]['submitted']);
        $this->assertSame(2, $result['assignments'][0]['missing']);
        $queue = moodle_grading_queue::execute(['course_id' => (int) $f->course->id], $f->tutor);
        $this->assertSame(2, $queue['total_needing_grading']);
    }

    /**
     * Suspended students do not count, with and without group limit (as in core).
     */
    public function test_suspended_student_not_counted(): void {
        $this->resetAfterTest();
        $f = $this->fixture(SEPARATEGROUPS);
        $gen = $this->getDataGenerator();
        $suspended = $gen->create_user(['firstname' => 'Sven', 'lastname' => 'Suspendedsecret']);
        $gen->enrol_user($suspended->id, $f->course->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $gen->create_group_member(['groupid' => $f->groupa->id, 'userid' => $suspended->id]);
        $this->submit($f, $suspended);

        $this->setUser($f->tutor);
        $queue = moodle_grading_queue::execute(['course_id' => (int) $f->course->id], $f->tutor);
        $this->assertSame(1, $queue['total_needing_grading']);
        $health = moodle_course_health::execute(['course_id' => (int) $f->course->id], $f->tutor);
        $this->assertSame(1, $health['assignments'][0]['submitted']);

        $this->setUser($f->lead);
        $queue = moodle_grading_queue::execute(['course_id' => (int) $f->course->id], $f->lead);
        $this->assertSame(2, $queue['total_needing_grading']);
        $health = moodle_course_health::execute(['course_id' => (int) $f->course->id], $f->lead);
        $this->assertSame(2, $health['assignments'][0]['submitted']);
    }

    /**
     * Cases for the "needs grading" rule, with the result of core and of the previous tool code.
     *
     * @return array<string, array{0: string, 1: int}>
     */
    public static function needs_grading_provider(): array {
        return [
            // Grade with the same timestamp as the submission: core counts it (old code: 0).
            'same timestamp' => ['sametime', 1],
            // No grade (-1) on a points assignment, newer than the submission: core does not count it (old code: 1).
            'no grade on points' => ['minusonepoints', 0],
            // No grade (-1) on a scale assignment: core counts it.
            'no grade on scale' => ['minusonescale', 1],
            // Graded first attempt, then a new attempt without a grade: core counts it.
            'reopened attempt' => ['reopened', 1],
        ];
    }

    /**
     * "Needs grading" follows core assign::count_submissions_need_grading*().
     *
     * @dataProvider needs_grading_provider
     * @param string $case Case key.
     * @param int $expected Expected count of a1's submission.
     */
    public function test_grading_queue_needs_grading_condition(string $case, int $expected): void {
        global $DB;
        $this->resetAfterTest();
        $f = $this->fixture(NOGROUPS);
        // Only a1 keeps a submission.
        $DB->delete_records('assign_submission', ['userid' => $f->b1->id]);
        $time = (int) $DB->get_field('assign_submission', 'timemodified', ['userid' => $f->a1->id]);
        $grade = (object) [
            'assignment' => (int) $f->cm->instance, 'userid' => $f->a1->id, 'grader' => $f->lead->id,
            'grade' => 50, 'attemptnumber' => 0, 'timecreated' => $time, 'timemodified' => $time,
        ];
        if ($case === 'minusonepoints' || $case === 'minusonescale') {
            $grade->grade = -1;
            $grade->timemodified = $time + 10;
        }
        if ($case === 'minusonescale') {
            $scale = $this->getDataGenerator()->create_scale();
            $DB->set_field('assign', 'grade', -(int) $scale->id, ['id' => (int) $f->cm->instance]);
        }
        if ($case === 'reopened') {
            $grade->timemodified = $time + 10;
            $DB->set_field('assign_submission', 'latest', 0, ['userid' => $f->a1->id]);
            $DB->insert_record('assign_submission', (object) [
                'assignment' => (int) $f->cm->instance, 'userid' => $f->a1->id, 'status' => 'submitted',
                'attemptnumber' => 1, 'latest' => 1, 'timecreated' => $time + 20, 'timemodified' => $time + 20,
            ]);
        }
        $DB->insert_record('assign_grades', $grade);

        $this->setUser($f->lead);
        $result = moodle_grading_queue::execute(['course_id' => (int) $f->course->id, 'include_empty' => true], $f->lead);
        $this->assertSame($expected, $result['total_needing_grading']);
        $this->assertSame(1, $result['assignments'][0]['submitted_count']);
    }

    /**
     * Build a team assignment with team T1 (m1, m2) and team T2 (m3, m4).
     *
     * @param bool $requireall Require all team members to submit.
     * @return stdClass
     */
    private function team_fixture(bool $requireall): stdClass {
        $gen = $this->getDataGenerator();
        $t = new stdClass();
        $t->course = $gen->create_course();
        $t->teacher = $gen->create_and_enrol($t->course, 'editingteacher');
        $t->team1 = $gen->create_group(['courseid' => $t->course->id]);
        $t->team2 = $gen->create_group(['courseid' => $t->course->id]);
        foreach ([1 => $t->team1, 2 => $t->team1, 3 => $t->team2, 4 => $t->team2] as $i => $team) {
            $t->{'m' . $i} = $gen->create_and_enrol($t->course, 'student', ['firstname' => 'Member' . $i]);
            $gen->create_group_member(['groupid' => $team->id, 'userid' => $t->{'m' . $i}->id]);
        }
        $assign = $gen->create_module('assign', [
            'course' => $t->course->id, 'teamsubmission' => 1, 'grade' => 100,
            'requireallteammemberssubmit' => $requireall ? 1 : 0,
        ]);
        $t->cm = get_coursemodule_from_instance('assign', $assign->id, $t->course->id, false, MUST_EXIST);
        return $t;
    }

    /**
     * Store a team submission the way mod_assign does: the submitting member's row and the team row.
     *
     * @param stdClass $t Team fixture.
     * @param stdClass $member Submitting member.
     * @param stdClass $team Team group.
     * @param string $teamstatus Status of the team record.
     * @return void
     */
    private function team_submit(stdClass $t, stdClass $member, stdClass $team, string $teamstatus): void {
        global $DB;
        $now = time();
        foreach ([[(int) $member->id, 0, 'submitted'], [0, (int) $team->id, $teamstatus]] as [$userid, $groupid, $status]) {
            $DB->insert_record('assign_submission', (object) [
                'assignment' => (int) $t->cm->instance, 'userid' => $userid, 'groupid' => $groupid, 'status' => $status,
                'attemptnumber' => 0, 'latest' => 1, 'timecreated' => $now, 'timemodified' => $now,
            ]);
        }
    }

    /**
     * Team submission: one member submitted for the team; the other team has not submitted.
     */
    public function test_team_submission_counts(): void {
        $this->resetAfterTest();
        $t = $this->team_fixture(false);
        $this->team_submit($t, $t->m1, $t->team1, 'submitted');
        $this->setUser($t->teacher);

        $queue = moodle_grading_queue::execute(['course_id' => (int) $t->course->id], $t->teacher);
        $this->assertSame(1, $queue['total_needing_grading']);
        $this->assertSame(1, $queue['assignments'][0]['submitted_count']);

        // Both members of team 1 count as submitted, team 2 is missing.
        $health = moodle_course_health::execute(['course_id' => (int) $t->course->id], $t->teacher);
        $this->assertSame(2, $health['assignments'][0]['submitted']);
        $this->assertSame(2, $health['assignments'][0]['missing']);

        $preview = moodle_message_course_students::execute([
            'course_id' => (int) $t->course->id, 'message' => 'Please submit',
            'target' => 'not_submitted', 'assign_cmid' => (int) $t->cm->id,
        ], $t->teacher);
        $names = $preview['recipients_preview'];
        sort($names);
        $this->assertSame([fullname($t->m3), fullname($t->m4)], $names);
    }

    /**
     * Team submission with "require all members to submit": one member alone leaves the team in draft.
     */
    public function test_team_submission_require_all(): void {
        $this->resetAfterTest();
        $t = $this->team_fixture(true);
        $this->team_submit($t, $t->m1, $t->team1, 'draft');
        $this->setUser($t->teacher);

        $queue = moodle_grading_queue::execute(['course_id' => (int) $t->course->id, 'include_empty' => true], $t->teacher);
        $this->assertSame(0, $queue['assignments'][0]['submitted_count']);
        $health = moodle_course_health::execute(['course_id' => (int) $t->course->id], $t->teacher);
        $this->assertSame(0, $health['assignments'][0]['submitted']);
        $this->assertSame(4, $health['assignments'][0]['missing']);
        $preview = moodle_message_course_students::execute([
            'course_id' => (int) $t->course->id, 'message' => 'Please submit',
            'target' => 'not_submitted', 'assign_cmid' => (int) $t->cm->id,
        ], $t->teacher);
        $this->assertSame(4, $preview['recipient_count']);
    }

    /**
     * Team submissions count per team, not per member record (all members submitted).
     */
    public function test_grading_queue_team_submission(): void {
        $this->resetAfterTest();
        $t = $this->team_fixture(true);
        $this->team_submit($t, $t->m1, $t->team1, 'submitted');
        global $DB;
        $DB->insert_record('assign_submission', (object) [
            'assignment' => (int) $t->cm->instance, 'userid' => $t->m2->id, 'groupid' => 0, 'status' => 'submitted',
            'attemptnumber' => 0, 'latest' => 1, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $this->setUser($t->teacher);
        $result = moodle_grading_queue::execute(['course_id' => (int) $t->course->id], $t->teacher);
        $this->assertSame(1, $result['total_needing_grading']);
        $this->assertSame(1, $result['assignments'][0]['submitted_count']);
    }

    /**
     * Availability: students who cannot see the assignment are not counted as missing.
     */
    public function test_course_health_scope_respects_availability(): void {
        global $DB, $CFG;
        $this->resetAfterTest();
        $CFG->enableavailability = 1;
        $f = $this->fixture(NOGROUPS);
        // Restrict the assignment to group A.
        $availability = json_encode(\core_availability\tree::get_root_json(
            [\availability_group\condition::get_json((int) $f->groupa->id)]
        ));
        $DB->set_field('course_modules', 'availability', $availability, ['id' => (int) $f->cm->id]);
        rebuild_course_cache((int) $f->course->id, true);
        $this->setUser($f->lead);

        $health = moodle_course_health::execute(['course_id' => (int) $f->course->id], $f->lead);
        // Scope a1, a2 (group A); a1 submitted; b1's submission is outside the scope.
        $this->assertSame(1, $health['assignments'][0]['submitted']);
        $this->assertSame(1, $health['assignments'][0]['missing']);
    }
}

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
use webservice_elediamcp\local\ai\tool_exception;
use webservice_elediamcp\local\ai\tools\moodle_course_contents;

/**
 * Course visibility in moodle_course_contents.
 *
 * The tool shows a course only to people who can open it in Moodle
 * (can_access_course()): an active enrolment is not enough for a hidden course.
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \webservice_elediamcp\local\ai\tools\moodle_course_contents
 */
final class visibility_course_contents_test extends advanced_testcase {
    /**
     * Create a course with one page and a section summary.
     *
     * @param int $visible Course visibility flag.
     * @return array{0: \stdClass, 1: \stdClass}
     */
    private function make_course(int $visible): array {
        global $DB;
        $course = $this->getDataGenerator()->create_course(['visible' => $visible, 'numsections' => 1]);
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Prepared page',
            'section' => 1,
        ]);
        $DB->set_field('course_sections', 'summary', 'Secret section summary', [
            'course' => $course->id,
            'section' => 1,
        ]);
        rebuild_course_cache((int) $course->id, true);
        return [$course, $page];
    }

    /**
     * Collect all cmids of a tool response.
     *
     * @param array $output Tool output.
     * @return int[]
     */
    private function cmids(array $output): array {
        $cmids = [];
        foreach ($output['sections'] as $section) {
            foreach ($section['modules'] as $module) {
                $cmids[] = (int) $module['cmid'];
            }
        }
        return $cmids;
    }

    /**
     * Assert that the tool rejects the course for this user with a neutral message.
     *
     * @param int $courseid Course id.
     * @param \stdClass $user User.
     * @param array $extra Extra arguments.
     */
    private function assert_rejected(int $courseid, \stdClass $user, array $extra = []): void {
        try {
            moodle_course_contents::execute(['course_id' => $courseid] + $extra, $user);
            $this->fail('Expected tool_exception for a course the user cannot open.');
        } catch (tool_exception $e) {
            $this->assertSame("Course {$courseid} is not available to you.", $e->getMessage());
        }
    }

    /**
     * Data provider for the include_descriptions flag.
     *
     * @return array
     */
    public static function descriptions_provider(): array {
        return [
            'without descriptions' => [false],
            'with descriptions' => [true],
        ];
    }

    /**
     * An enrolled student cannot read a hidden course, with or without descriptions.
     *
     * @dataProvider descriptions_provider
     * @param bool $includedesc Whether include_descriptions is set.
     */
    public function test_hidden_course_rejected_for_enrolled_student(bool $includedesc): void {
        $this->resetAfterTest();
        [$course] = $this->make_course(0);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->assert_rejected((int) $course->id, $student, ['include_descriptions' => $includedesc]);
    }

    /**
     * The rejection for a hidden course reads the same as for a missing course.
     */
    public function test_hidden_and_missing_course_share_the_message(): void {
        $this->resetAfterTest();
        [$course] = $this->make_course(0);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->assert_rejected((int) $course->id, $student);
        $missing = (int) $course->id + 1000;
        $this->assert_rejected($missing, $student);
    }

    /**
     * A person with moodle/course:viewhiddencourses still gets the hidden course.
     */
    public function test_hidden_course_allowed_with_viewhiddencourses(): void {
        $this->resetAfterTest();
        [$course, $page] = $this->make_course(0);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $roleid = (int) $this->getDataGenerator()->create_role();
        assign_capability('moodle/course:viewhiddencourses', CAP_ALLOW, $roleid, context_course::instance($course->id)->id);
        role_assign($roleid, $student->id, context_course::instance($course->id)->id);
        $this->setUser($student);

        $output = moodle_course_contents::execute([
            'course_id' => (int) $course->id,
            'include_descriptions' => true,
        ], $student);

        $this->assertContains((int) $page->cmid, $this->cmids($output));
    }

    /**
     * A teacher (editingteacher has viewhiddencourses) still gets the hidden course.
     */
    public function test_hidden_course_allowed_for_teacher(): void {
        $this->resetAfterTest();
        [$course, $page] = $this->make_course(0);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $output = moodle_course_contents::execute(['course_id' => (int) $course->id], $teacher);

        $this->assertContains((int) $page->cmid, $this->cmids($output));
    }

    /**
     * A visible course is unchanged for an enrolled student, including descriptions.
     */
    public function test_visible_course_unchanged_for_student(): void {
        $this->resetAfterTest();
        [$course, $page] = $this->make_course(1);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $output = moodle_course_contents::execute([
            'course_id' => (int) $course->id,
            'include_descriptions' => true,
        ], $student);

        $this->assertContains((int) $page->cmid, $this->cmids($output));
        $summaries = array_column($output['sections'], 'summary_excerpt');
        $this->assertContains('Secret section summary', $summaries);
    }

    /**
     * A suspended enrolment does not open a visible course (active enrolment only, as before).
     */
    public function test_visible_course_rejected_for_suspended_enrolment(): void {
        $this->resetAfterTest();
        [$course] = $this->make_course(1);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $this->setUser($student);

        $this->assert_rejected((int) $course->id, $student);
    }

    /**
     * The site administrator gets a hidden course without an enrolment.
     */
    public function test_hidden_course_allowed_for_admin(): void {
        $this->resetAfterTest();
        [$course, $page] = $this->make_course(0);
        $this->setAdminUser();
        $admin = get_admin();

        $output = moodle_course_contents::execute(['course_id' => (int) $course->id], $admin);

        $this->assertContains((int) $page->cmid, $this->cmids($output));
    }

    /**
     * moodle/course:viewhiddenactivities alone no longer opens a course; it only
     * affects include_hidden for people who can open the course.
     */
    public function test_viewhiddenactivities_alone_does_not_open_course(): void {
        $this->resetAfterTest();
        [$course] = $this->make_course(1);
        $user = $this->getDataGenerator()->create_user();
        $roleid = (int) $this->getDataGenerator()->create_role();
        $coursecontext = context_course::instance($course->id);
        assign_capability('moodle/course:viewhiddenactivities', CAP_ALLOW, $roleid, $coursecontext->id);
        role_assign($roleid, $user->id, $coursecontext->id);
        $this->setUser($user);

        $this->assert_rejected((int) $course->id, $user);
    }

    /**
     * A rejected request is not answered from a cached response of an earlier,
     * permitted request (the access check runs before the cache).
     */
    public function test_hiding_course_takes_effect_despite_cache(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $page] = $this->make_course(1);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $output = moodle_course_contents::execute(['course_id' => (int) $course->id], $student);
        $this->assertContains((int) $page->cmid, $this->cmids($output));

        $DB->set_field('course', 'visible', 0, ['id' => $course->id]);
        $this->assert_rejected((int) $course->id, $student);
    }

    /**
     * Enable the guest enrolment instance of a course without a password.
     *
     * @param int $courseid Course id.
     */
    private function enable_guest_access(int $courseid): void {
        global $DB;
        $instance = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'guest'], '*', MUST_EXIST);
        $DB->update_record('enrol', (object) [
            'id' => $instance->id,
            'status' => ENROL_INSTANCE_ENABLED,
            'password' => '',
        ]);
    }

    /**
     * Give a user a role with the given capabilities in the course category (no enrolment).
     *
     * @param \stdClass $course Course.
     * @param \stdClass $user User.
     * @param string[] $capabilities Capabilities to allow.
     */
    private function assign_category_role(\stdClass $course, \stdClass $user, array $capabilities): void {
        $roleid = (int) $this->getDataGenerator()->create_role();
        $catcontext = \context_coursecat::instance((int) $course->category);
        foreach ($capabilities as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, $catcontext->id);
        }
        role_assign($roleid, $user->id, $catcontext->id);
    }

    /**
     * Guest access without a password does not open a course for the tutor.
     */
    public function test_guest_access_does_not_open_course(): void {
        $this->resetAfterTest();
        [$course] = $this->make_course(1);
        $this->enable_guest_access((int) $course->id);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->assert_rejected((int) $course->id, $user, ['include_descriptions' => true]);
    }

    /**
     * In a course with guest access an enrolled student is served as before.
     */
    public function test_guest_course_allowed_with_enrolment(): void {
        $this->resetAfterTest();
        [$course, $page] = $this->make_course(1);
        $this->enable_guest_access((int) $course->id);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $output = moodle_course_contents::execute(['course_id' => (int) $course->id], $student);

        $this->assertContains((int) $page->cmid, $this->cmids($output));
    }

    /**
     * moodle/course:view without enrolment opens a visible course (as before).
     */
    public function test_course_view_opens_visible_course(): void {
        $this->resetAfterTest();
        [$course, $page] = $this->make_course(1);
        $user = $this->getDataGenerator()->create_user();
        $this->assign_category_role($course, $user, ['moodle/course:view']);
        $this->setUser($user);

        $output = moodle_course_contents::execute(['course_id' => (int) $course->id], $user);

        $this->assertContains((int) $page->cmid, $this->cmids($output));
    }

    /**
     * A manager-like role with moodle/course:view but without
     * moodle/course:viewhiddencourses cannot open a hidden course.
     */
    public function test_course_view_without_viewhiddencourses_rejected_on_hidden_course(): void {
        $this->resetAfterTest();
        [$course] = $this->make_course(0);
        $user = $this->getDataGenerator()->create_user();
        $this->assign_category_role($course, $user, ['moodle/course:view']);
        $this->setUser($user);

        $this->assert_rejected((int) $course->id, $user);
    }

    /**
     * moodle/course:view together with moodle/course:viewhiddencourses opens a hidden course.
     */
    public function test_course_view_with_viewhiddencourses_opens_hidden_course(): void {
        $this->resetAfterTest();
        [$course, $page] = $this->make_course(0);
        $user = $this->getDataGenerator()->create_user();
        $this->assign_category_role($course, $user, ['moodle/course:view', 'moodle/course:viewhiddencourses']);
        $this->setUser($user);

        $output = moodle_course_contents::execute(['course_id' => (int) $course->id], $user);

        $this->assertContains((int) $page->cmid, $this->cmids($output));
    }

    /**
     * A teacher still gets hidden activities with include_hidden.
     */
    public function test_teacher_include_hidden_lists_hidden_activity(): void {
        $this->resetAfterTest();
        [$course] = $this->make_course(1);
        $hidden = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Hidden page',
            'visible' => 0,
        ]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $this->setUser($teacher);

        $output = moodle_course_contents::execute([
            'course_id' => (int) $course->id,
            'include_hidden' => true,
        ], $teacher);

        $this->assertContains((int) $hidden->cmid, $this->cmids($output));
    }
}

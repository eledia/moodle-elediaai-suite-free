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
use webservice_elediamcp\local\ai\tool_exception;
use webservice_elediamcp\local\ai\tools\moodle_calendar_upcoming;
use webservice_elediamcp\local\ai\tools\moodle_course_health;
use webservice_elediamcp\local\ai\tools\moodle_forum_discussions;
use webservice_elediamcp\local\ai\tools\moodle_get_resource;
use webservice_elediamcp\local\ai\tools\moodle_message_course_students;
use webservice_elediamcp\local\ai\tools\moodle_my_submission_files;
use webservice_elediamcp\local\ai\tools\moodle_verify_user_context;

/**
 * Enrolment status and cache order: suspended enrolments do not grant access.
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \webservice_elediamcp\local\ai\tools\moodle_forum_discussions
 * @covers      \webservice_elediamcp\local\ai\tools\moodle_my_submission_files
 * @covers      \webservice_elediamcp\local\ai\tools\moodle_calendar_upcoming
 * @covers      \webservice_elediamcp\local\ai\tools\moodle_course_health
 * @covers      \webservice_elediamcp\local\ai\tools\moodle_message_course_students
 * @covers      \webservice_elediamcp\local\ai\tools\moodle_get_resource
 * @covers      \webservice_elediamcp\local\ai\tools\moodle_verify_user_context
 */
final class visibility_enrolment_status_test extends advanced_testcase {
    /**
     * Course with a forum discussion and an assignment, one active and one suspended student.
     *
     * @return array{0: \stdClass, 1: \stdClass, 2: \stdClass, 3: \stdClass, 4: \stdClass, 5: \stdClass}
     *         Course, active student, suspended student, forum cm, discussion, assign cm.
     */
    private function fixture(): array {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $active = $gen->create_user();
        $suspended = $gen->create_user();
        $gen->enrol_user($active->id, $course->id, 'student');
        $gen->enrol_user($suspended->id, $course->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);

        $forum = $gen->create_module('forum', ['course' => $course->id, 'name' => 'Talk']);
        $forumcm = get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST);
        $discussion = $gen->get_plugin_generator('mod_forum')->create_discussion([
            'course' => $course->id,
            'forum' => $forum->id,
            'userid' => $active->id,
            'name' => 'Visible topic',
            'message' => 'Hello',
        ]);
        $assign = $gen->create_module('assign', ['course' => $course->id, 'name' => 'Essay']);
        $assigncm = get_coursemodule_from_instance('assign', $assign->id, $course->id, false, MUST_EXIST);
        return [$course, $active, $suspended, $forumcm, $discussion, $assigncm];
    }

    /**
     * Forum by cmid: active enrolment works, suspended enrolment is rejected.
     */
    public function test_forum_cmid_suspended_rejected(): void {
        $this->resetAfterTest();
        [, $active, $suspended, $forumcm] = $this->fixture();

        $this->setUser($active);
        $result = moodle_forum_discussions::execute(['cmid' => (int) $forumcm->id], $active);
        $this->assertContains('Visible topic', array_column($result['discussions'], 'subject'));

        $this->setUser($suspended);
        $this->expectException(tool_exception::class);
        moodle_forum_discussions::execute(['cmid' => (int) $forumcm->id], $suspended);
    }

    /**
     * Forum posts by discussion id: suspended enrolment is rejected, active works.
     */
    public function test_forum_discussion_suspended_rejected(): void {
        $this->resetAfterTest();
        [, $active, $suspended, , $discussion] = $this->fixture();

        $this->setUser($active);
        $result = moodle_forum_discussions::execute(['discussion_id' => (int) $discussion->id], $active);
        $this->assertNotEmpty($result['posts']);

        $this->setUser($suspended);
        $this->expectException(tool_exception::class);
        moodle_forum_discussions::execute(['discussion_id' => (int) $discussion->id], $suspended);
    }

    /**
     * Own submission files: suspended enrolment is rejected, active works.
     */
    public function test_my_submission_files_suspended_rejected(): void {
        $this->resetAfterTest();
        [, $active, $suspended, , , $assigncm] = $this->fixture();

        $this->setUser($active);
        $result = moodle_my_submission_files::execute(['cmid' => (int) $assigncm->id], $active);
        $this->assertFalse($result['has_submission']);

        $this->setUser($suspended);
        $this->expectException(tool_exception::class);
        moodle_my_submission_files::execute(['cmid' => (int) $assigncm->id], $suspended);
    }

    /**
     * Calendar: a lost enrolment takes effect at once, the cached answer is not reused.
     */
    public function test_calendar_cache_after_unenrolment(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $active] = $this->fixture();
        $this->setAdminUser();
        $this->getDataGenerator()->create_event([
            'eventtype' => 'course', 'courseid' => $course->id,
            'name' => 'Course exam', 'timestart' => time() + HOURSECS,
        ]);

        $this->setUser($active);
        $first = moodle_calendar_upcoming::execute(['days_ahead' => 7], $active);
        $this->assertContains('Course exam', array_column($first['events'], 'name'));
        $filtered = moodle_calendar_upcoming::execute(['days_ahead' => 7, 'course_id' => (int) $course->id], $active);
        $this->assertContains('Course exam', array_column($filtered['events'], 'name'));

        // Suspend the enrolment within the cache lifetime.
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        enrol_get_plugin('manual')->update_user_enrol($instance, (int) $active->id, ENROL_USER_SUSPENDED);

        $second = moodle_calendar_upcoming::execute(['days_ahead' => 7], $active);
        $this->assertNotContains('Course exam', array_column($second['events'], 'name'));

        $this->expectException(tool_exception::class);
        moodle_calendar_upcoming::execute(['days_ahead' => 7, 'course_id' => (int) $course->id], $active);
    }

    /**
     * Run both teacher tools for a user and report whether each was allowed.
     *
     * @param \stdClass $course Course.
     * @param \stdClass $user Acting user.
     * @return array{0: bool, 1: bool} Health allowed, message preview allowed.
     */
    private function teacher_tools_allowed(\stdClass $course, \stdClass $user): array {
        $this->setUser($user);
        $results = [];
        $calls = [
            fn() => moodle_course_health::execute(['course_id' => (int) $course->id], $user),
            fn() => moodle_message_course_students::execute(
                ['course_id' => (int) $course->id, 'message' => 'Hello', 'target' => 'all'],
                $user
            ),
        ];
        foreach ($calls as $call) {
            try {
                $call();
                $results[] = true;
            } catch (tool_exception $e) {
                $this->assertStringContainsString('cannot access course', $e->getMessage());
                $results[] = false;
            }
        }
        return $results;
    }

    /**
     * Teacher tools need course access: active teacher yes, suspended teacher no,
     * category teacher without enrolment no, category manager (course:view) yes, admin yes.
     */
    public function test_teacher_tools_require_course_access(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $category = $gen->create_category();
        $course = $gen->create_course(['category' => $category->id]);
        $gen->create_and_enrol($course, 'student');
        $catcontext = \context_coursecat::instance((int) $category->id);

        $active = $gen->create_and_enrol($course, 'editingteacher');
        $suspended = $gen->create_user();
        $gen->enrol_user($suspended->id, $course->id, 'editingteacher', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $catteacher = $gen->create_user();
        $gen->role_assign('editingteacher', $catteacher->id, $catcontext->id);
        $catmanager = $gen->create_user();
        $gen->role_assign('manager', $catmanager->id, $catcontext->id);

        $this->assertSame([true, true], $this->teacher_tools_allowed($course, $active));
        $this->assertSame([false, false], $this->teacher_tools_allowed($course, $suspended));
        $this->assertSame([false, false], $this->teacher_tools_allowed($course, $catteacher));
        $this->assertSame([true, true], $this->teacher_tools_allowed($course, $catmanager));
        $this->assertSame([true, true], $this->teacher_tools_allowed($course, get_admin()));
    }

    /**
     * Resource: active enrolment reads the page, suspended enrolment is rejected.
     */
    public function test_get_resource_suspended_rejected(): void {
        $this->resetAfterTest();
        [$course, $active, $suspended] = $this->fixture();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'name' => 'Reading']);

        $this->setUser($active);
        $output = moodle_get_resource::execute(['cmid' => (int) $page->cmid], $active);
        $this->assertSame('Reading', $output['name']);

        $this->setUser($suspended);
        $this->expectException(tool_exception::class);
        moodle_get_resource::execute(['cmid' => (int) $page->cmid], $suspended);
    }

    /**
     * Verified scope: an active enrolment is verified, a suspended one is not.
     */
    public function test_verify_user_context_suspended_not_verified(): void {
        $this->resetAfterTest();
        [$course, $active, $suspended] = $this->fixture();

        $this->setUser($active);
        $output = moodle_verify_user_context::execute(['course_ids' => [(int) $course->id]], $active);
        $this->assertSame([(int) $course->id], $output['verified_course_ids']);

        $this->setUser($suspended);
        $output = moodle_verify_user_context::execute(['course_ids' => [(int) $course->id]], $suspended);
        $this->assertSame([], $output['verified_course_ids']);
    }

    /**
     * Calendar: leaving a group takes effect within the cache lifetime.
     */
    public function test_calendar_cache_after_group_change(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        [$course, $active] = $this->fixture();
        $group = $gen->create_group(['courseid' => $course->id]);
        $gen->create_group_member(['groupid' => $group->id, 'userid' => $active->id]);
        $this->setAdminUser();
        $gen->create_event([
            'eventtype' => 'group', 'courseid' => $course->id, 'groupid' => $group->id,
            'name' => 'Group meeting', 'timestart' => time() + HOURSECS,
        ]);

        $this->setUser($active);
        $first = moodle_calendar_upcoming::execute(['days_ahead' => 7], $active);
        $this->assertContains('Group meeting', array_column($first['events'], 'name'));

        groups_remove_member($group->id, $active->id);
        $second = moodle_calendar_upcoming::execute(['days_ahead' => 7], $active);
        $this->assertNotContains('Group meeting', array_column($second['events'], 'name'));
    }

    /**
     * Calendar: hiding an activity takes effect within the cache lifetime.
     */
    public function test_calendar_cache_after_activity_hidden(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        [$course, $active] = $this->fixture();
        $assign = $gen->create_module('assign', ['course' => $course->id, 'name' => 'Due soon',
            'duedate' => time() + 2 * HOURSECS]);

        $this->setUser($active);
        $first = moodle_calendar_upcoming::execute(['days_ahead' => 7], $active);
        $this->assertContains((int) $assign->id, array_column($first['events'], 'instance'));

        set_coursemodule_visible((int) $assign->cmid, 0);
        $second = moodle_calendar_upcoming::execute(['days_ahead' => 7], $active);
        $this->assertNotContains((int) $assign->id, array_column($second['events'], 'instance'));
    }

    /**
     * Active-only access keeps admins and people with moodle/course:view without enrolment.
     */
    public function test_onlyactive_keeps_admin_and_course_view(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $category = $gen->create_category();
        $course = $gen->create_course(['category' => $category->id]);
        $page = $gen->create_module('page', ['course' => $course->id, 'name' => 'Reading']);
        $manager = $gen->create_user();
        $gen->role_assign('manager', $manager->id, \context_coursecat::instance((int) $category->id)->id);

        foreach ([get_admin(), $manager] as $person) {
            $this->setUser($person);
            $this->assertSame('Reading', moodle_get_resource::execute(['cmid' => (int) $page->cmid], $person)['name']);
            $output = moodle_verify_user_context::execute(['course_ids' => [(int) $course->id]], $person);
            $this->assertSame([(int) $course->id], $output['verified_course_ids']);
        }
    }
}

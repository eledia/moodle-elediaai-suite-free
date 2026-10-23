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
use context_module;
use webservice_elediamcp\local\ai\tool_exception;
use webservice_elediamcp\local\ai\tools\moodle_get_announcements;

/**
 * Announcement visibility in moodle_get_announcements.
 *
 * The tool lists only announcements Moodle shows the person
 * (forum_user_can_see_discussion(): timed display window, separate groups,
 * mod/forum:viewdiscussion).
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \webservice_elediamcp\local\ai\tools\moodle_get_announcements
 */
final class visibility_announcements_test extends advanced_testcase {
    /** @var \stdClass Course. */
    private \stdClass $course;

    /** @var \stdClass News forum. */
    private \stdClass $forum;

    /** @var \stdClass Editing teacher who posts. */
    private \stdClass $teacher;

    /**
     * Create a course with a news forum and a teacher.
     *
     * @param int $groupmode Group mode of the news forum.
     */
    private function make_course(int $groupmode = NOGROUPS): void {
        $this->course = $this->getDataGenerator()->create_course();
        $this->forum = $this->getDataGenerator()->create_module('forum', [
            'course' => $this->course->id,
            'type' => 'news',
            'groupmode' => $groupmode,
        ]);
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
    }

    /**
     * Post an announcement.
     *
     * @param string $name Subject.
     * @param array $extra Extra discussion fields (timestart, timeend, groupid).
     * @param int $age Seconds since the last change (orders the feed).
     */
    private function post(string $name, array $extra = [], int $age = 0): void {
        global $DB;
        /** @var \mod_forum_generator $fg */
        $fg = $this->getDataGenerator()->get_plugin_generator('mod_forum');
        $discussion = $fg->create_discussion((object) ([
            'course' => $this->course->id,
            'forum' => $this->forum->id,
            'userid' => $this->teacher->id,
            'name' => $name,
        ] + $extra));
        $DB->set_field('forum_discussions', 'timemodified', time() - 60 - $age, ['id' => $discussion->id]);
    }

    /**
     * Subjects of all returned announcements.
     *
     * @param \stdClass $user Calling user.
     * @param array $args Tool arguments.
     * @return string[]
     */
    private function subjects(\stdClass $user, array $args = []): array {
        $this->setUser($user);
        $output = moodle_get_announcements::execute($args, $user);
        return array_column($output['announcements'], 'subject');
    }

    /**
     * Create the current, future and expired timed announcements.
     */
    private function post_timed(): void {
        $this->post('Current news');
        $this->post('Future news', ['timestart' => time() + 2 * DAYSECS]);
        $this->post('Expired news', ['timestart' => time() - 3 * DAYSECS, 'timeend' => time() - DAYSECS]);
    }

    /**
     * With timed posts enabled, a student sees neither a future nor an expired announcement.
     */
    public function test_timed_announcements_hidden_from_student(): void {
        $this->resetAfterTest();
        set_config('forum_enabletimedposts', 1);
        $this->make_course();
        $this->post_timed();
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');

        $subjects = $this->subjects($student);

        $this->assertContains('Current news', $subjects);
        $this->assertNotContains('Future news', $subjects);
        $this->assertNotContains('Expired news', $subjects);
    }

    /**
     * A teacher with mod/forum:viewhiddentimedposts sees all three.
     */
    public function test_timed_announcements_visible_to_teacher(): void {
        $this->resetAfterTest();
        set_config('forum_enabletimedposts', 1);
        $this->make_course();
        $this->post_timed();

        $subjects = $this->subjects($this->teacher);

        $this->assertContains('Current news', $subjects);
        $this->assertContains('Future news', $subjects);
        $this->assertContains('Expired news', $subjects);
    }

    /**
     * Like Moodle's discussion list, the display window applies even when
     * forum_enabletimedposts is off (old posts may still carry a window).
     */
    public function test_timed_window_applies_when_timed_posts_disabled(): void {
        $this->resetAfterTest();
        set_config('forum_enabletimedposts', 0);
        $this->make_course();
        $this->post_timed();
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');

        $subjects = $this->subjects($student);

        $this->assertContains('Current news', $subjects);
        $this->assertNotContains('Future news', $subjects);
        $this->assertNotContains('Expired news', $subjects);
        $this->assertContains('Future news', $this->subjects($this->teacher));
    }

    /**
     * Hidden announcements leave no gap: the page fills with visible ones and
     * has_more counts only visible announcements.
     */
    public function test_hidden_announcements_do_not_eat_the_page(): void {
        $this->resetAfterTest();
        set_config('forum_enabletimedposts', 1);
        $this->make_course();
        $this->post('Future newest', ['timestart' => time() + DAYSECS], 0);
        $this->post('Current A', [], 10);
        $this->post('Future middle', ['timestart' => time() + DAYSECS], 20);
        $this->post('Current B', [], 30);
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($student);

        $one = moodle_get_announcements::execute(['limit' => 1], $student);
        $this->assertSame(['Current A'], array_column($one['announcements'], 'subject'));
        $this->assertTrue($one['has_more']);

        $two = moodle_get_announcements::execute(['limit' => 2], $student);
        $this->assertSame(['Current A', 'Current B'], array_column($two['announcements'], 'subject'));
        $this->assertFalse($two['has_more']);
    }

    /**
     * Separate groups: a student sees announcements for the own group and for
     * all participants, but not those of another group.
     */
    public function test_separate_groups_for_student(): void {
        $this->resetAfterTest();
        $this->make_course(SEPARATEGROUPS);
        $groupa = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $this->post('For group A', ['groupid' => $groupa->id]);
        $this->post('For group B', ['groupid' => $groupb->id]);
        $this->post('For everybody', ['groupid' => -1]);
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->getDataGenerator()->create_group_member(['groupid' => $groupa->id, 'userid' => $student->id]);

        $subjects = $this->subjects($student);

        $this->assertContains('For group A', $subjects);
        $this->assertContains('For everybody', $subjects);
        $this->assertNotContains('For group B', $subjects);
    }

    /**
     * Separate groups: a teacher with moodle/site:accessallgroups sees all groups.
     */
    public function test_separate_groups_for_teacher(): void {
        $this->resetAfterTest();
        $this->make_course(SEPARATEGROUPS);
        $groupa = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $this->post('For group A', ['groupid' => $groupa->id]);
        $this->post('For group B', ['groupid' => $groupb->id]);
        $this->post('For everybody', ['groupid' => -1]);

        $subjects = $this->subjects($this->teacher);

        $this->assertContains('For group A', $subjects);
        $this->assertContains('For group B', $subjects);
        $this->assertContains('For everybody', $subjects);
    }

    /**
     * Visible groups: a student sees announcements of every group, as in Moodle.
     */
    public function test_visible_groups_show_all_groups(): void {
        $this->resetAfterTest();
        $this->make_course(VISIBLEGROUPS);
        $groupa = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $this->post('For group B', ['groupid' => $groupb->id]);
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->getDataGenerator()->create_group_member(['groupid' => $groupa->id, 'userid' => $student->id]);

        $this->assertContains('For group B', $this->subjects($student));
    }

    /**
     * Without mod/forum:viewdiscussion the forum shows nothing, and neither does the tool.
     */
    public function test_no_viewdiscussion_no_announcements(): void {
        global $DB;
        $this->resetAfterTest();
        $this->make_course();
        $this->post('Current news');
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $studentrole = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $modcontext = context_module::instance($this->forum->cmid);
        assign_capability('mod/forum:viewdiscussion', CAP_PROHIBIT, $studentrole, $modcontext->id);

        $this->assertSame([], $this->subjects($student));
    }

    /**
     * Losing the enrolment takes effect at once, not only after the response cache expires.
     */
    public function test_unenrolment_takes_effect_despite_cache(): void {
        $this->resetAfterTest();
        $this->make_course();
        $this->post('Current news');
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');

        $this->assertContains('Current news', $this->subjects($student));

        $plugin = enrol_get_plugin('manual');
        $instance = $this->get_manual_instance((int) $this->course->id);
        $plugin->unenrol_user($instance, (int) $student->id);

        $this->assertSame([], $this->subjects($student));
    }

    /**
     * Separate groups with a grouping: only the user's groups within the
     * forum's grouping count, as in Moodle's discussion list.
     */
    public function test_separate_groups_respect_forum_grouping(): void {
        global $DB;
        $this->resetAfterTest();
        $this->make_course(SEPARATEGROUPS);
        $groupa = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $grouping = $this->getDataGenerator()->create_grouping(['courseid' => $this->course->id]);
        $this->getDataGenerator()->create_grouping_group(['groupingid' => $grouping->id, 'groupid' => $groupa->id]);
        $DB->set_field('course_modules', 'groupingid', $grouping->id, ['id' => $this->forum->cmid]);
        rebuild_course_cache((int) $this->course->id, true);
        $this->post('For group A', ['groupid' => $groupa->id]);
        $this->post('For group B', ['groupid' => $groupb->id]);
        $this->post('For everybody', ['groupid' => -1]);

        $inb = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->getDataGenerator()->create_group_member(['groupid' => $groupb->id, 'userid' => $inb->id]);
        $subjects = $this->subjects($inb);
        $this->assertNotContains('For group B', $subjects, 'Group B lies outside the forum grouping.');
        $this->assertNotContains('For group A', $subjects);
        $this->assertContains('For everybody', $subjects);

        $ina = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->getDataGenerator()->create_group_member(['groupid' => $groupa->id, 'userid' => $ina->id]);
        $this->assertContains('For group A', $this->subjects($ina));
    }

    /**
     * A display window that ends takes effect at once (no response cache).
     */
    public function test_ending_window_takes_effect_at_once(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('forum_enabletimedposts', 1);
        $this->make_course();
        $this->post('Current news');
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');

        $this->assertContains('Current news', $this->subjects($student));

        $DB->set_field('forum_discussions', 'timeend', time() - 1, ['forum' => $this->forum->id]);
        $this->assertNotContains('Current news', $this->subjects($student));
    }

    /**
     * A hidden course the student is enrolled in is rejected with a neutral message.
     */
    public function test_hidden_course_rejected_with_neutral_message(): void {
        global $DB;
        $this->resetAfterTest();
        $this->make_course();
        $this->post('Current news');
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $DB->set_field('course', 'visible', 0, ['id' => $this->course->id]);
        $this->setUser($student);

        try {
            moodle_get_announcements::execute(['course_id' => (int) $this->course->id], $student);
            $this->fail('Expected tool_exception for a hidden course.');
        } catch (tool_exception $e) {
            $this->assertSame("Course {$this->course->id} is not available to you.", $e->getMessage());
        }
    }

    /**
     * The rules are applied for the user passed to the tool, even if the
     * session user ($USER) has more rights; the final core check can only remove rows.
     */
    public function test_rules_apply_to_passed_user(): void {
        $this->resetAfterTest();
        set_config('forum_enabletimedposts', 1);
        $this->make_course();
        $this->post_timed();
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setAdminUser();

        $output = moodle_get_announcements::execute([], $student);
        $subjects = array_column($output['announcements'], 'subject');

        $this->assertContains('Current news', $subjects);
        $this->assertNotContains('Future news', $subjects);
    }

    /**
     * Hidden rows are filtered in SQL: many future and foreign-group
     * announcements do not drive up the number of database reads.
     */
    public function test_hidden_rows_cost_no_extra_reads(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('forum_enabletimedposts', 1);
        $this->make_course(SEPARATEGROUPS);
        $groupa = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        for ($i = 0; $i < 40; $i++) {
            $this->post("Future {$i}", ['timestart' => time() + DAYSECS], $i);
            $this->post("Group B {$i}", ['groupid' => $groupb->id], $i);
        }
        for ($i = 0; $i < 6; $i++) {
            $this->post("Visible {$i}", ['groupid' => $groupa->id], 100 + $i);
        }
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->getDataGenerator()->create_group_member(['groupid' => $groupa->id, 'userid' => $student->id]);
        $this->setUser($student);
        // Warm the course and access caches, then measure a second call.
        moodle_get_announcements::execute(['limit' => 5], $student);

        $before = $DB->perf_get_reads();
        $output = moodle_get_announcements::execute(['limit' => 5], $student);
        $reads = $DB->perf_get_reads() - $before;

        $this->assertCount(5, $output['announcements']);
        $this->assertTrue($output['has_more']);
        $this->assertStringStartsWith('Visible', $output['announcements'][0]['subject']);
        $this->assertLessThan(40, $reads, "Too many database reads: {$reads}");
    }

    /**
     * Manual enrolment instance of a course.
     *
     * @param int $courseid Course id.
     * @return \stdClass
     */
    private function get_manual_instance(int $courseid): \stdClass {
        global $DB;
        return $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'manual'], '*', MUST_EXIST);
    }
}

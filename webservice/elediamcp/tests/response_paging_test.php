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
use stdClass;
use webservice_elediamcp\local\ai\tool_exception;
use webservice_elediamcp\local\ai\response_budget;
use webservice_elediamcp\local\ai\tools\moodle_get_announcements;
use webservice_elediamcp\local\ai\tools\moodle_my_assignments;
use webservice_elediamcp\local\ai\tools\moodle_my_courses;
use webservice_elediamcp\local\ai\tools\moodle_quiz_info;

/**
 * The paged list tools keep their answers within the character budget.
 *
 * Every test uses entries long enough that a full default page would exceed
 * the default limit of 12000 characters.
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \webservice_elediamcp\local\ai\tools\moodle_my_assignments
 * @covers      \webservice_elediamcp\local\ai\tools\moodle_quiz_info
 * @covers      \webservice_elediamcp\local\ai\tools\moodle_my_courses
 * @covers      \webservice_elediamcp\local\ai\tools\moodle_get_announcements
 * @covers      \webservice_elediamcp\local\ai\response_budget
 */
final class response_paging_test extends advanced_testcase {
    /**
     * Load the module libraries.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        require_once($CFG->libdir . '/gradelib.php');
    }

    /**
     * A long name with a running number in front.
     *
     * @param string $prefix Prefix.
     * @param int $number Running number.
     * @return string Name of about 240 characters.
     */
    private static function long_name(string $prefix, int $number): string {
        return sprintf('%s %02d ', $prefix, $number) . trim(str_repeat('sample text ', 19));
    }

    /**
     * Course with a long name and an enrolled student.
     *
     * @return array{0: stdClass, 1: stdClass} Course and student.
     */
    private function course_with_student(): array {
        $course = $this->getDataGenerator()->create_course(['fullname' => self::long_name('Course', 0)]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);
        return [$course, $student];
    }

    /**
     * Call a tool page by page with next_offset and check every page.
     *
     * @param callable $call function(int $offset): array, one tool call.
     * @param string $listkey Key of the list in the answer.
     * @param string $idkey Key of the entry id.
     * @param int $maxchars Limit in force.
     * @return array{ids: array, pages: int[]} Ids in order and entries per page.
     */
    private function walk(callable $call, string $listkey, string $idkey, int $maxchars): array {
        $ids = [];
        $pages = [];
        $offset = 0;
        for ($guard = 0; $guard < 100; $guard++) {
            $answer = $call($offset);
            $count = count($answer[$listkey]);
            $this->assertGreaterThan(0, $count, 'A page with more entries to come is never empty.');
            if ($count > 1) {
                // Both protocol forms: with and without the {"result": ...} envelope.
                $this->assertLessThanOrEqual(response_budget::usable_chars($maxchars), response_budget::measure($answer));
                $this->assertLessThanOrEqual(
                    response_budget::usable_chars($maxchars),
                    response_budget::measure($answer, false)
                );
            }
            $this->assertSame($offset, $answer['offset']);
            $this->assertArrayHasKey('next_offset', $answer);
            foreach ($answer[$listkey] as $entry) {
                $ids[] = $entry[$idkey];
            }
            $pages[] = $count;
            if (!$answer['has_more']) {
                $this->assertNull($answer['next_offset']);
                $this->assertStringNotContainsString(response_budget::MORE_SENTENCE, $answer['summary']);
                break;
            }
            $this->assertSame($offset + $count, $answer['next_offset']);
            $this->assertStringContainsString(response_budget::MORE_SENTENCE, $answer['summary']);
            $offset = $answer['next_offset'];
        }
        return ['ids' => $ids, 'pages' => $pages];
    }

    /**
     * moodle_my_assignments: pages fit the limit and paging returns every assignment once.
     */
    public function test_my_assignments_pages(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->course_with_student();
        $expected = [];
        $now = time();
        for ($i = 1; $i <= 30; $i++) {
            $module = $this->getDataGenerator()->create_module('assign', [
                'course' => $course->id,
                'name' => self::long_name('Assignment', $i),
                'duedate' => $now + $i * DAYSECS,
            ]);
            $expected[] = (int) $module->cmid;
        }

        // Without the budget a full default page is far longer than 12000 characters.
        set_config('response_max_chars', 100000, 'webservice_elediamcp');
        $unbounded = moodle_my_assignments::execute([], $student);
        $this->assertCount(30, $unbounded['assignments']);
        $this->assertGreaterThan(12000, response_budget::measure($unbounded));
        $this->assertFalse($unbounded['has_more']);
        $this->assertNull($unbounded['next_offset']);

        unset_config('response_max_chars', 'webservice_elediamcp');
        $first = moodle_my_assignments::execute([], $student);
        $this->assertSame(30, $first['total']);
        $this->assertSame(30, $first['limit']);
        $this->assertTrue($first['has_more']);
        $this->assertLessThan(30, count($first['assignments']));
        $this->assertLessThanOrEqual(11400, response_budget::measure($first));

        $walk = $this->walk(
            fn(int $offset) => moodle_my_assignments::execute(['offset' => $offset], $student),
            'assignments',
            'cmid',
            12000
        );
        $this->assertSame($expected, $walk['ids']);

        set_config('response_max_chars', 2000, 'webservice_elediamcp');
        $small = moodle_my_assignments::execute([], $student);
        $this->assertLessThan(count($first['assignments']), count($small['assignments']));
        $this->assertSame($expected, $this->walk(
            fn(int $offset) => moodle_my_assignments::execute(['offset' => $offset], $student),
            'assignments',
            'cmid',
            2000
        )['ids']);
    }

    /**
     * moodle_my_assignments: a limit below the budget still caps the page.
     */
    public function test_my_assignments_limit_stays_upper_bound(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->course_with_student();
        for ($i = 1; $i <= 5; $i++) {
            $this->getDataGenerator()->create_module('assign', ['course' => $course->id, 'name' => "Short {$i}"]);
        }
        $answer = moodle_my_assignments::execute(['limit' => 2, 'offset' => 2], $student);
        $this->assertCount(2, $answer['assignments']);
        $this->assertSame(5, $answer['total']);
        $this->assertTrue($answer['has_more']);
        $this->assertSame(4, $answer['next_offset']);

        $last = moodle_my_assignments::execute(['limit' => 2, 'offset' => 4], $student);
        $this->assertCount(1, $last['assignments']);
        $this->assertFalse($last['has_more']);
        $this->assertNull($last['next_offset']);
    }

    /**
     * moodle_quiz_info: pages fit the limit and paging returns every quiz once.
     */
    public function test_quiz_info_pages(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->course_with_student();
        $expected = [];
        $now = time();
        for ($i = 1; $i <= 30; $i++) {
            $quiz = $this->getDataGenerator()->create_module('quiz', [
                'course' => $course->id,
                'name' => self::long_name('Quiz', $i),
                'timeclose' => $now + $i * DAYSECS,
            ]);
            $expected[] = (int) $quiz->cmid;
        }

        set_config('response_max_chars', 100000, 'webservice_elediamcp');
        $unbounded = moodle_quiz_info::execute([], $student);
        $this->assertCount(30, $unbounded['quizzes']);
        $this->assertGreaterThan(12000, response_budget::measure($unbounded));

        unset_config('response_max_chars', 'webservice_elediamcp');
        $first = moodle_quiz_info::execute([], $student);
        $this->assertSame(30, $first['total']);
        $this->assertTrue($first['has_more']);
        $this->assertLessThan(30, count($first['quizzes']));
        $this->assertLessThanOrEqual(11400, response_budget::measure($first));
        $this->assertSame($expected, $this->walk(
            fn(int $offset) => moodle_quiz_info::execute(['offset' => $offset], $student),
            'quizzes',
            'cmid',
            12000
        )['ids']);

        set_config('response_max_chars', 2000, 'webservice_elediamcp');
        $small = moodle_quiz_info::execute([], $student);
        $this->assertLessThan(count($first['quizzes']), count($small['quizzes']));
        $this->assertSame($expected, $this->walk(
            fn(int $offset) => moodle_quiz_info::execute(['offset' => $offset], $student),
            'quizzes',
            'cmid',
            2000
        )['ids']);
    }

    /**
     * moodle_my_courses: pages fit the limit and paging returns every course once.
     */
    public function test_my_courses_pages(): void {
        $this->resetAfterTest();
        $student = $this->getDataGenerator()->create_user();
        $this->setUser($student);
        $expected = [];
        for ($i = 1; $i <= 22; $i++) {
            $course = $this->getDataGenerator()->create_course([
                'fullname' => self::long_name('Course', $i),
                'summary' => str_repeat('A course summary sentence. ', 12),
            ]);
            $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
            $expected[] = (int) $course->id;
        }
        $args = ['classification' => 'all'];

        set_config('response_max_chars', 100000, 'webservice_elediamcp');
        $unbounded = moodle_my_courses::execute($args, $student);
        $this->assertCount(20, $unbounded['courses']);
        $this->assertGreaterThan(12000, response_budget::measure($unbounded));

        unset_config('response_max_chars', 'webservice_elediamcp');
        $first = moodle_my_courses::execute($args, $student);
        $this->assertSame(22, $first['total']);
        $this->assertTrue($first['has_more']);
        $this->assertLessThan(20, count($first['courses']));
        $this->assertLessThanOrEqual(11400, response_budget::measure($first));
        $this->assertSame($expected, $this->walk(
            fn(int $offset) => moodle_my_courses::execute($args + ['offset' => $offset], $student),
            'courses',
            'id',
            12000
        )['ids']);

        // A changed limit is not hidden by the response cache.
        set_config('response_max_chars', 2000, 'webservice_elediamcp');
        $small = moodle_my_courses::execute($args, $student);
        $this->assertLessThan(count($first['courses']), count($small['courses']));
        $this->assertSame($expected, $this->walk(
            fn(int $offset) => moodle_my_courses::execute($args + ['offset' => $offset], $student),
            'courses',
            'id',
            2000
        )['ids']);
    }

    /**
     * Course with a news forum and long announcements, newest first in the returned list.
     *
     * @param int $count Number of announcements.
     * @return array{0: stdClass, 1: int[]} Student and discussion ids, newest first.
     */
    private function announcements(int $count): array {
        global $DB;
        $course = $this->getDataGenerator()->create_course(['fullname' => self::long_name('Course', 0)]);
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id, 'type' => 'news']);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        /** @var \mod_forum_generator $fg */
        $fg = $this->getDataGenerator()->get_plugin_generator('mod_forum');
        $ids = [];
        for ($i = 1; $i <= $count; $i++) {
            $discussion = $fg->create_discussion((object) [
                'course' => $course->id,
                'forum' => $forum->id,
                'userid' => $teacher->id,
                'name' => self::long_name('Announcement', $i),
                'message' => '<p>' . str_repeat("Announcement {$i} body text. ", 80) . '</p>',
            ]);
            $DB->set_field('forum_discussions', 'timemodified', time() - 60 - $i * 10, ['id' => $discussion->id]);
            $ids[] = (int) $discussion->id;
        }
        $this->setUser($student);
        return [$student, $ids];
    }

    /**
     * moodle_get_announcements: pages fit the limit and paging returns every announcement once.
     */
    public function test_announcements_pages(): void {
        $this->resetAfterTest();
        [$student, $expected] = $this->announcements(12);
        $args = ['limit' => 30, 'excerpt_chars' => 1200];

        set_config('response_max_chars', 100000, 'webservice_elediamcp');
        $unbounded = moodle_get_announcements::execute($args, $student);
        $this->assertCount(12, $unbounded['announcements']);
        $this->assertGreaterThan(12000, response_budget::measure($unbounded));

        unset_config('response_max_chars', 'webservice_elediamcp');
        $first = moodle_get_announcements::execute($args, $student);
        $this->assertTrue($first['has_more']);
        $this->assertLessThan(12, count($first['announcements']));
        $this->assertStringStartsWith('Showing ', $first['summary']);
        $this->assertStringContainsString('not the complete list', $first['summary']);
        $this->assertStringStartsWith('Found 12 ', $unbounded['summary']);
        $this->assertSame(count($first['announcements']), $first['total']);
        $this->assertLessThanOrEqual(11400, response_budget::measure($first));
        $this->assertSame($expected, $this->walk(
            fn(int $offset) => moodle_get_announcements::execute($args + ['offset' => $offset], $student),
            'announcements',
            'discussion_id',
            12000
        )['ids']);
    }

    /**
     * moodle_get_announcements: a single announcement longer than the limit is still returned.
     */
    public function test_announcements_single_entry_over_limit(): void {
        $this->resetAfterTest();
        [$student, $expected] = $this->announcements(3);
        set_config('response_max_chars', 2000, 'webservice_elediamcp');
        $args = ['excerpt_chars' => 1200];

        $first = moodle_get_announcements::execute($args, $student);
        $this->assertCount(1, $first['announcements']);
        $this->assertGreaterThan(1900, response_budget::measure($first));
        $this->assertTrue($first['has_more']);
        $this->assertSame(1, $first['next_offset']);

        $walk = $this->walk(
            fn(int $offset) => moodle_get_announcements::execute($args + ['offset' => $offset], $student),
            'announcements',
            'discussion_id',
            2000
        );
        $this->assertSame($expected, $walk['ids']);
        $this->assertSame([1, 1, 1], $walk['pages']);
    }

    /**
     * moodle_get_announcements: the new offset skips visible announcements, newest first.
     */
    public function test_announcements_offset(): void {
        $this->resetAfterTest();
        [$student, $ids] = $this->announcements(5);
        $args = ['include_body' => false, 'limit' => 2];

        $page = moodle_get_announcements::execute($args + ['offset' => 2], $student);
        $this->assertSame([$ids[2], $ids[3]], array_column($page['announcements'], 'discussion_id'));
        $this->assertSame(2, $page['offset']);
        $this->assertTrue($page['has_more']);
        $this->assertSame(4, $page['next_offset']);

        $last = moodle_get_announcements::execute($args + ['offset' => 4], $student);
        $this->assertSame([$ids[4]], array_column($last['announcements'], 'discussion_id'));
        $this->assertFalse($last['has_more']);
        $this->assertNull($last['next_offset']);

        $beyond = moodle_get_announcements::execute($args + ['offset' => 9], $student);
        $this->assertSame([], $beyond['announcements']);
        $this->assertFalse($beyond['has_more']);
        $this->assertNull($beyond['next_offset']);
    }

    /**
     * The four tools describe the paging and accept an offset.
     */
    public function test_descriptions_and_schemas(): void {
        $tools = [moodle_my_assignments::class, moodle_quiz_info::class, moodle_my_courses::class,
            moodle_get_announcements::class];
        foreach ($tools as $tool) {
            $this->assertStringContainsString(response_budget::DESCRIPTION_SENTENCE, $tool::description(), $tool);
            $this->assertSame(0, $tool::input_schema()['properties']['offset']['minimum'], $tool);
            $output = $tool::output_schema();
            $this->assertSame(['integer', 'null'], $output['properties']['next_offset']['type'], $tool);
            $this->assertContains('next_offset', $output['required'], $tool);
        }
    }

    /**
     * Equal dates: quizzes and assignments are ordered by id, pages have no gap and no duplicate.
     */
    public function test_equal_dates_page_stably(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->course_with_student();
        $due = time() + 3 * DAYSECS;
        $assignids = [];
        $quizids = [];
        for ($i = 1; $i <= 12; $i++) {
            $assignids[] = (int) $this->getDataGenerator()->create_module('assign', [
                'course' => $course->id,
                'name' => self::long_name('Same day', $i),
                'duedate' => $due,
            ])->id;
            $quizids[] = (int) $this->getDataGenerator()->create_module('quiz', [
                'course' => $course->id,
                'name' => self::long_name('No dates', $i),
            ])->id;
        }
        sort($assignids);
        sort($quizids);
        set_config('response_max_chars', 2500, 'webservice_elediamcp');

        $assign = $this->walk(
            fn(int $offset) => moodle_my_assignments::execute(['offset' => $offset], $student),
            'assignments',
            'assign_id',
            2500
        );
        $this->assertGreaterThan(1, count($assign['pages']));
        $this->assertSame($assignids, $assign['ids']);

        $quiz = $this->walk(
            fn(int $offset) => moodle_quiz_info::execute(['offset' => $offset], $student),
            'quizzes',
            'quiz_id',
            2500
        );
        $this->assertGreaterThan(1, count($quiz['pages']));
        $this->assertSame($quizids, $quiz['ids']);
    }

    /**
     * An offset behind the end gives an empty page without next_offset; total stays.
     */
    public function test_offset_behind_end(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->course_with_student();
        $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $cases = [
            'assignments' => moodle_my_assignments::execute(['offset' => 5], $student),
            'quizzes' => moodle_quiz_info::execute(['offset' => 5], $student),
            'courses' => moodle_my_courses::execute(['classification' => 'all', 'offset' => 5], $student),
        ];
        foreach ($cases as $listkey => $answer) {
            $this->assertSame([], $answer[$listkey], $listkey);
            $this->assertSame(1, $answer['total'], $listkey);
            $this->assertSame(5, $answer['offset'], $listkey);
            $this->assertFalse($answer['has_more'], $listkey);
            $this->assertNull($answer['next_offset'], $listkey);
        }
    }

    /**
     * moodle_get_announcements: offsets above 1000 are rejected, and total is described as the page count.
     */
    public function test_announcements_offset_limit_and_description(): void {
        $this->resetAfterTest();
        [$student] = $this->announcements(1);
        $this->assertSame(1000, moodle_get_announcements::input_schema()['properties']['offset']['maximum']);
        $this->assertSame([], moodle_get_announcements::execute(['offset' => 1000], $student)['announcements']);
        $this->assertStringContainsString('not the number of all announcements', moodle_get_announcements::description());

        $this->expectException(tool_exception::class);
        moodle_get_announcements::execute(['offset' => 1001], $student);
    }
}

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
use grade_category;
use grade_grade;
use grade_item;
use stdClass;
use webservice_elediamcp\local\ai\tools\moodle_my_grades;

/**
 * Visibility tests for moodle_my_grades: the tool shows only what the core user report shows.
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \webservice_elediamcp\local\ai\tools\moodle_my_grades
 */
final class visibility_my_grades_test extends advanced_testcase {
    /** @var string Summary sentence for withheld grades. */
    private const NOTE = 'Some grades exist that are not shown to you yet: where grade_withheld is true, '
        . 'null values mean "not released", not "no grade".';

    /** @var string Summary sentence for a missing pass verdict. */
    private const PASS_NOTE = 'has_passed is null where the pass status cannot be determined or is not shown.';

    /**
     * Load the gradebook libraries.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->libdir . '/gradelib.php');
    }

    /**
     * Create a course with a student and manual grade items (max 100 each).
     *
     * @param array<int, float> $values Grade per item for the student.
     * @param array<string, mixed> $courseopts Course options.
     * @return array{course: stdClass, student: stdClass, items: array<int, grade_item>}
     */
    private function setup_course(array $values, array $courseopts = []): array {
        $course = $this->getDataGenerator()->create_course($courseopts);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $items = [];
        foreach ($values as $i => $value) {
            $record = $this->getDataGenerator()->create_grade_item([
                'courseid' => $course->id,
                'itemname' => 'Item ' . $i,
                'grademax' => 100,
            ]);
            $item = grade_item::fetch(['id' => $record->id]);
            $item->update_final_grade((int) $student->id, $value, 'test');
            $items[$i] = $item;
        }
        grade_regrade_final_grades((int) $course->id);
        return ['course' => $course, 'student' => $student, 'items' => $items];
    }

    /**
     * Call the tool for one course.
     *
     * @param stdClass $user Calling user.
     * @param int $courseid Course id.
     * @param bool $items Whether to include items.
     * @return array<string, mixed>
     */
    private function call(stdClass $user, int $courseid, bool $items = false): array {
        // Bring the gradebook up to date first, as the user report does before it renders.
        grade_force_full_regrading($courseid);
        grade_regrade_final_grades($courseid);
        $this->setUser($user);
        return moodle_my_grades::execute(['course_id' => $courseid, 'include_items' => $items], $user);
    }

    /**
     * Index items of a course entry by name.
     *
     * @param array<string, mixed> $output Tool output.
     * @return array<string, array<string, mixed>>
     */
    private function items_by_name(array $output): array {
        $out = [];
        foreach ($output['courses'][0]['items'] as $item) {
            $out[$item['name']] = $item;
        }
        return $out;
    }

    /**
     * Hide a single grade of a user (item stays visible).
     *
     * @param grade_item $item Grade item.
     * @param int $userid User id.
     * @param int $hidden 1 or a timestamp.
     */
    private function hide_grade(grade_item $item, int $userid, int $hidden): void {
        $grade = grade_grade::fetch(['itemid' => $item->id, 'userid' => $userid]);
        $grade->set_hidden($hidden);
    }

    /**
     * A graded visible item is listed with its value (was a TypeError under strict_types).
     */
    public function test_items_with_visible_grade(): void {
        $this->resetAfterTest();
        $data = $this->setup_course([1 => 72.0]);

        $output = $this->call($data['student'], (int) $data['course']->id, true);

        $items = $this->items_by_name($output);
        $this->assertArrayHasKey('Item 1', $items);
        $this->assertEqualsWithDelta(72.0, $items['Item 1']['grade'], 0.001);
        $this->assertSame('72.00', $items['Item 1']['grade_str']);
        $this->assertNotNull($items['Item 1']['graded_iso']);
        $this->assertFalse($items['Item 1']['grade_withheld']);
        $this->assertEqualsWithDelta(72.0, $output['courses'][0]['final_grade'], 0.001);
        $this->assertFalse($output['courses'][0]['grade_withheld']);
        $this->assertTrue($output['courses'][0]['grades_available']);
        $this->assertStringNotContainsString(self::NOTE, $output['summary']);
    }

    /**
     * showgrades = 0: learners get "access denied", users with grade:viewall still see grades.
     */
    public function test_showgrades_off(): void {
        $this->resetAfterTest();
        $data = $this->setup_course([1 => 60.0], ['showgrades' => 0]);
        $courseid = (int) $data['course']->id;

        $output = $this->call($data['student'], $courseid, true);
        $entry = $output['courses'][0];
        $this->assertSame('access denied', $entry['final_grade_str']);
        $this->assertNull($entry['final_grade']);
        $this->assertArrayNotHasKey('items', $entry);
        $this->assertFalse($entry['grades_available']);
        $this->assertFalse($entry['grade_withheld']);

        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $courseid, 'editingteacher');
        $data['items'][1]->update_final_grade((int) $teacher->id, 40.0, 'test');
        grade_regrade_final_grades($courseid);
        $output = $this->call($teacher, $courseid, true);
        $this->assertEqualsWithDelta(40.0, $output['courses'][0]['final_grade'], 0.001);
        $this->assertTrue($output['courses'][0]['grades_available']);
        $this->assertCount(1, $output['courses'][0]['items']);
    }

    /**
     * Control: showgrades = 1 keeps the learner's grade visible.
     */
    public function test_showgrades_on_shows_grade(): void {
        $this->resetAfterTest();
        $data = $this->setup_course([1 => 60.0], ['showgrades' => 1]);

        $output = $this->call($data['student'], (int) $data['course']->id);

        $this->assertEqualsWithDelta(60.0, $output['courses'][0]['final_grade'], 0.001);
    }

    /**
     * A permanently hidden item is left out; its value never appears.
     */
    public function test_item_hidden_permanently(): void {
        $this->resetAfterTest();
        $data = $this->setup_course([1 => 88.0, 2 => 50.0]);
        $data['items'][1]->set_hidden(1);

        $output = $this->call($data['student'], (int) $data['course']->id, true);

        $items = $this->items_by_name($output);
        $this->assertArrayNotHasKey('Item 1', $items);
        $this->assertEqualsWithDelta(50.0, $items['Item 2']['grade'], 0.001);
        $this->assertStringContainsString(self::NOTE, $output['summary']);
        $this->assertNull($output['courses'][0]['final_grade']);
        $this->assertTrue($output['courses'][0]['grade_withheld']);
        $this->assertFalse($items['Item 2']['grade_withheld']);
    }

    /**
     * An item hidden until a future date is listed without value; after the date it is shown.
     */
    public function test_item_hidden_until(): void {
        $this->resetAfterTest();
        $data = $this->setup_course([1 => 88.0, 2 => 50.0]);
        $data['items'][1]->set_hidden(time() + DAYSECS);

        $output = $this->call($data['student'], (int) $data['course']->id, true);
        $items = $this->items_by_name($output);
        $this->assertArrayHasKey('Item 1', $items);
        $this->assertNull($items['Item 1']['grade']);
        $this->assertSame('not shown yet', $items['Item 1']['grade_str']);
        $this->assertNull($items['Item 1']['graded_iso']);
        $this->assertTrue($items['Item 1']['grade_withheld']);
        $this->assertStringContainsString(self::NOTE, $output['summary']);

        // Control: the date has passed, the grade is visible.
        $data['items'][1]->set_hidden(time() - 60);
        $output = $this->call($data['student'], (int) $data['course']->id, true);
        $items = $this->items_by_name($output);
        $this->assertEqualsWithDelta(88.0, $items['Item 1']['grade'], 0.001);
        $this->assertFalse($items['Item 1']['grade_withheld']);
        $this->assertStringNotContainsString(self::NOTE, $output['summary']);
    }

    /**
     * A single hidden grade on a visible item is withheld (no rawgrade fallback).
     */
    public function test_single_grade_hidden_on_visible_item(): void {
        $this->resetAfterTest();
        $data = $this->setup_course([1 => 55.0, 2 => 50.0]);
        $courseid = (int) $data['course']->id;
        $this->hide_grade($data['items'][1], (int) $data['student']->id, 1);

        $output = $this->call($data['student'], $courseid, true);
        $items = $this->items_by_name($output);
        $this->assertArrayNotHasKey('Item 1', $items);
        $this->assertArrayHasKey('Item 2', $items);
        $this->assertStringContainsString(self::NOTE, $output['summary']);
        $this->assertNull($output['courses'][0]['final_grade']);

        // With "show all hidden items" the row is listed, still without value.
        grade_set_setting($courseid, 'report_user_showhiddenitems', 2);
        $output = $this->call($data['student'], $courseid, true);
        $items = $this->items_by_name($output);
        $this->assertNull($items['Item 1']['grade']);
        $this->assertSame('not shown yet', $items['Item 1']['grade_str']);
    }

    /**
     * Feedback on a hidden course total is withheld; visible feedback stays.
     */
    public function test_feedback_hidden(): void {
        $this->resetAfterTest();
        $data = $this->setup_course([1 => 70.0]);
        $courseid = (int) $data['course']->id;
        $studentid = (int) $data['student']->id;
        $courseitem = grade_item::fetch_course_item($courseid);
        grade_regrade_final_grades($courseid);
        $courseitem->update_final_grade($studentid, false, 'test', 'Course feedback text', FORMAT_PLAIN);

        // Control: visible feedback.
        $output = $this->call($data['student'], $courseid);
        $this->assertSame('Course feedback text', $output['courses'][0]['feedback_excerpt']);
        $this->assertFalse($output['courses'][0]['grade_withheld']);

        $this->hide_grade($courseitem, $studentid, 1);
        $output = $this->call($data['student'], $courseid);
        $entry = $output['courses'][0];
        $this->assertSame('', $entry['feedback_excerpt']);
        $this->assertTrue($entry['grade_withheld']);
        $this->assertNull($entry['final_grade']);
        $this->assertSame('not shown yet', $entry['final_grade_str']);
        $this->assertStringContainsString(self::NOTE, $output['summary']);
    }

    /**
     * The course total follows "Hide totals if they contain hidden items" like the user report.
     */
    public function test_course_total_with_hidden_elements(): void {
        $this->resetAfterTest();
        $data = $this->setup_course([1 => 88.0, 2 => 55.0, 3 => 50.0]);
        $courseid = (int) $data['course']->id;
        $studentid = (int) $data['student']->id;

        // Control: nothing hidden, the real total (natural aggregation: 193 of 300).
        $output = $this->call($data['student'], $courseid);
        $this->assertEqualsWithDelta(193.0, $output['courses'][0]['final_grade'], 0.001);
        $this->assertFalse($output['courses'][0]['grade_withheld']);

        $data['items'][1]->set_hidden(1);
        $this->hide_grade($data['items'][2], $studentid, 1);

        // Default: hide the total.
        $output = $this->call($data['student'], $courseid);
        $entry = $output['courses'][0];
        $this->assertNull($entry['final_grade']);
        $this->assertNull($entry['has_passed']);
        $this->assertTrue($entry['grade_withheld']);
        $this->assertSame('not shown yet', $entry['final_grade_str']);
        $this->assertStringContainsString(self::NOTE, $output['summary']);

        // Show the total without the hidden grades.
        grade_set_setting($courseid, 'report_user_showtotalsifcontainhidden', GRADE_REPORT_SHOW_TOTAL_IF_CONTAINS_HIDDEN);
        $output = $this->call($data['student'], $courseid);
        $entry = $output['courses'][0];
        $this->assertEqualsWithDelta(50.0, $entry['final_grade'], 0.001);
        $this->assertEqualsWithDelta(100.0, $entry['final_grade_max'], 0.001);
        $this->assertNull($entry['has_passed']);
        $this->assertFalse($entry['grade_withheld'], 'A value is shown, recalculated without hidden grades.');

        // The site chose to show the real total.
        grade_set_setting($courseid, 'report_user_showtotalsifcontainhidden', GRADE_REPORT_SHOW_REAL_TOTAL_IF_CONTAINS_HIDDEN);
        $output = $this->call($data['student'], $courseid);
        $this->assertEqualsWithDelta(193.0, $output['courses'][0]['final_grade'], 0.001);
        $this->assertFalse($output['courses'][0]['grade_withheld']);
    }

    /**
     * Users with moodle/grade:viewhidden see hidden items and the real total.
     */
    public function test_teacher_with_viewhidden_sees_everything(): void {
        $this->resetAfterTest();
        $data = $this->setup_course([1 => 88.0, 2 => 55.0, 3 => 50.0]);
        $courseid = (int) $data['course']->id;
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $courseid, 'editingteacher');
        foreach ([1 => 88.0, 2 => 55.0, 3 => 50.0] as $i => $value) {
            $data['items'][$i]->update_final_grade((int) $teacher->id, $value, 'test');
        }
        grade_regrade_final_grades($courseid);
        $data['items'][1]->set_hidden(1);
        $this->hide_grade($data['items'][2], (int) $teacher->id, 1);

        $output = $this->call($teacher, $courseid, true);

        $entry = $output['courses'][0];
        $this->assertEqualsWithDelta(193.0, $entry['final_grade'], 0.001);
        $items = $this->items_by_name($output);
        $this->assertEqualsWithDelta(88.0, $items['Item 1']['grade'], 0.001);
        $this->assertEqualsWithDelta(55.0, $items['Item 2']['grade'], 0.001);
        $this->assertStringNotContainsString(self::NOTE, $output['summary']);
    }

    /**
     * A change of visibility takes effect immediately (no stale cached response).
     */
    public function test_hiding_takes_effect_without_cache_delay(): void {
        $this->resetAfterTest();
        $data = $this->setup_course([1 => 88.0]);
        $courseid = (int) $data['course']->id;

        $output = $this->call($data['student'], $courseid, true);
        $this->assertEqualsWithDelta(88.0, $output['courses'][0]['items'][0]['grade'], 0.001);

        $data['items'][1]->set_hidden(1);
        $output = $this->call($data['student'], $courseid, true);
        $this->assertSame([], $output['courses'][0]['items']);
        $this->assertNull($output['courses'][0]['final_grade']);
    }

    /**
     * Enrol a teacher and give them the same grades as the student.
     *
     * @param int $courseid Course id.
     * @param array<int, grade_item> $items Items with value per key.
     * @param array<int, float> $values Grade per item.
     * @return stdClass Teacher.
     */
    private function add_teacher(int $courseid, array $items, array $values): stdClass {
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $courseid, 'editingteacher');
        foreach ($values as $i => $value) {
            $items[$i]->update_final_grade((int) $teacher->id, $value, 'test');
        }
        return $teacher;
    }

    /**
     * Items below a hidden category are left out, even when the hiding was not cascaded.
     */
    public function test_items_below_hidden_category(): void {
        $this->resetAfterTest();
        $data = $this->setup_course([1 => 50.0]);
        $courseid = (int) $data['course']->id;
        $catrecord = $this->getDataGenerator()->create_grade_category(['courseid' => $courseid, 'fullname' => 'Exam']);
        $record = $this->getDataGenerator()->create_grade_item([
            'courseid' => $courseid, 'itemname' => 'Resit', 'grademax' => 100, 'categoryid' => $catrecord->id,
        ]);
        $resit = grade_item::fetch(['id' => $record->id]);
        $resit->update_final_grade((int) $data['student']->id, 45.0, 'test');
        $category = grade_category::fetch(['id' => $catrecord->id]);
        $category->set_hidden(1);
        $this->assertFalse(grade_item::fetch(['id' => $record->id])->is_hidden(), 'Precondition: not cascaded.');

        $output = $this->call($data['student'], $courseid, true);
        $items = $this->items_by_name($output);
        $this->assertArrayNotHasKey('Resit', $items);
        $this->assertArrayHasKey('Item 1', $items);
        foreach ($output['courses'][0]['items'] as $item) {
            $this->assertNotSame('category', $item['item_type'], 'The hidden category total is left out.');
        }
        $this->assertStringContainsString(self::NOTE, $output['summary']);

        // Control: a teacher with viewhidden sees the item.
        $teacher = $this->add_teacher($courseid, [1 => $resit], [1 => 45.0]);
        $output = $this->call($teacher, $courseid, true);
        $this->assertEqualsWithDelta(45.0, $this->items_by_name($output)['Resit']['grade'], 0.001);
    }

    /**
     * has_passed is given for a visible real total and withheld when the total hides parts.
     */
    public function test_pass_grade_verdict(): void {
        $this->resetAfterTest();
        $data = $this->setup_course([1 => 88.0, 2 => 55.0, 3 => 50.0]);
        $courseid = (int) $data['course']->id;
        $courseitem = grade_item::fetch_course_item($courseid);
        $courseitem->gradepass = 150;
        $courseitem->update();

        $output = $this->call($data['student'], $courseid);
        $this->assertEqualsWithDelta(150.0, $output['courses'][0]['pass_grade'], 0.001);
        $this->assertTrue($output['courses'][0]['has_passed']);
        $this->assertStringContainsString('Pass grade reached', $output['summary']);
        $this->assertStringNotContainsString(self::PASS_NOTE, $output['summary']);

        $data['items'][1]->set_hidden(1);
        foreach (
            [
                GRADE_REPORT_HIDE_TOTAL_IF_CONTAINS_HIDDEN => null,
                GRADE_REPORT_SHOW_TOTAL_IF_CONTAINS_HIDDEN => null,
                GRADE_REPORT_SHOW_REAL_TOTAL_IF_CONTAINS_HIDDEN => true,
            ] as $setting => $expected
        ) {
            grade_set_setting($courseid, 'report_user_showtotalsifcontainhidden', $setting);
            $output = $this->call($data['student'], $courseid);
            $this->assertSame($expected, $output['courses'][0]['has_passed'], "Setting {$setting}");
            if ($expected === null) {
                $this->assertStringContainsString(self::PASS_NOTE, $output['summary'], "Setting {$setting}");
            } else {
                $this->assertStringNotContainsString(self::PASS_NOTE, $output['summary'], "Setting {$setting}");
            }
        }
    }

    /**
     * Items of activities the user cannot see are left out, also hidden-until rows.
     */
    public function test_items_of_activities_the_user_cannot_see(): void {
        $this->resetAfterTest();
        set_config('enableavailability', 1);
        $data = $this->setup_course([1 => 50.0]);
        $courseid = (int) $data['course']->id;
        $studentid = (int) $data['student']->id;
        $restriction = static fn(bool $show): string => json_encode([
            'op' => '&',
            'c' => [['type' => 'date', 'd' => '>=', 't' => time() + DAYSECS]],
            'showc' => [$show],
        ]);
        $hiddenassign = $this->getDataGenerator()->create_module('assign', [
            'course' => $courseid, 'name' => 'Hidden task', 'grade' => 100, 'availability' => $restriction(false),
        ]);
        $greyassign = $this->getDataGenerator()->create_module('assign', [
            'course' => $courseid, 'name' => 'Greyed task', 'grade' => 100, 'availability' => $restriction(true),
        ]);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $courseid, 'editingteacher');
        $assignitems = [];
        foreach (['Hidden task' => $hiddenassign, 'Greyed task' => $greyassign] as $name => $assign) {
            $item = grade_item::fetch([
                'courseid' => $courseid, 'itemtype' => 'mod', 'itemmodule' => 'assign', 'iteminstance' => $assign->id,
            ]);
            $item->update_raw_grade($studentid, 80.0, 'test');
            $item->update_raw_grade((int) $teacher->id, 80.0, 'test');
            $assignitems[$name] = $item;
        }

        $items = $this->items_by_name($this->call($data['student'], $courseid, true));
        $this->assertArrayNotHasKey('Hidden task', $items);
        $this->assertArrayHasKey('Greyed task', $items, 'Restricted with information shown: listed like the report.');

        // A hidden-until row of an activity the user cannot see is left out too.
        $assignitems['Hidden task']->set_hidden(time() + DAYSECS);
        $items = $this->items_by_name($this->call($data['student'], $courseid, true));
        $this->assertArrayNotHasKey('Hidden task', $items);

        // Control: the teacher may ignore restrictions and sees the item.
        $items = $this->items_by_name($this->call($teacher, $courseid, true));
        $this->assertArrayHasKey('Hidden task', $items);
    }

    /**
     * Category totals follow the hidden-total rule.
     */
    public function test_category_total_with_hidden_item(): void {
        $this->resetAfterTest();
        $data = $this->setup_course([]);
        $courseid = (int) $data['course']->id;
        $studentid = (int) $data['student']->id;
        $catrecord = $this->getDataGenerator()->create_grade_category(['courseid' => $courseid, 'fullname' => 'Unit']);
        $values = ['Unit A' => 40.0, 'Unit B' => 30.0];
        $items = [];
        foreach ($values as $name => $value) {
            $record = $this->getDataGenerator()->create_grade_item([
                'courseid' => $courseid, 'itemname' => $name, 'grademax' => 100, 'categoryid' => $catrecord->id,
            ]);
            $items[$name] = grade_item::fetch(['id' => $record->id]);
            $items[$name]->update_final_grade($studentid, $value, 'test');
        }
        $categorytotal = static function (array $output): array {
            foreach ($output['courses'][0]['items'] as $item) {
                if ($item['item_type'] === 'category') {
                    return $item;
                }
            }
            return [];
        };

        // Control: nothing hidden.
        $this->assertEqualsWithDelta(70.0, $categorytotal($this->call($data['student'], $courseid, true))['grade'], 0.001);

        $items['Unit A']->set_hidden(1);
        $total = $categorytotal($this->call($data['student'], $courseid, true));
        $this->assertNull($total['grade']);
        $this->assertSame('not shown yet', $total['grade_str']);
        $this->assertTrue($total['grade_withheld']);

        grade_set_setting($courseid, 'report_user_showtotalsifcontainhidden', GRADE_REPORT_SHOW_TOTAL_IF_CONTAINS_HIDDEN);
        $total = $categorytotal($this->call($data['student'], $courseid, true));
        $this->assertEqualsWithDelta(30.0, $total['grade'], 0.001);
        $this->assertEqualsWithDelta(100.0, $total['grade_max'], 0.001);
        $this->assertFalse($total['grade_withheld']);
    }

    /**
     * Setting "show no hidden items" (0) and the site defaults without a course setting.
     */
    public function test_show_no_hidden_items_and_site_defaults(): void {
        $this->resetAfterTest();
        $data = $this->setup_course([1 => 88.0, 2 => 50.0]);
        $courseid = (int) $data['course']->id;
        $data['items'][1]->set_hidden(time() + DAYSECS);

        // Site default (no course setting): show the real total. Core caches settings per course,
        // so the site value is set before the first read.
        set_config('grade_report_user_showtotalsifcontainhidden', GRADE_REPORT_SHOW_REAL_TOTAL_IF_CONTAINS_HIDDEN);
        $output = $this->call($data['student'], $courseid);
        $this->assertEqualsWithDelta(138.0, $output['courses'][0]['final_grade'], 0.001);

        grade_set_setting($courseid, 'report_user_showhiddenitems', 0);
        $items = $this->items_by_name($this->call($data['student'], $courseid, true));
        $this->assertArrayNotHasKey('Item 1', $items);
        $this->assertArrayHasKey('Item 2', $items);

        // A course setting wins over the site default.
        grade_set_setting($courseid, 'report_user_showtotalsifcontainhidden', GRADE_REPORT_HIDE_TOTAL_IF_CONTAINS_HIDDEN);
        $output = $this->call($data['student'], $courseid);
        $this->assertNull($output['courses'][0]['final_grade']);
    }

    /**
     * Scale items are shown with the scale label; hidden scale grades are withheld.
     */
    public function test_scale_items(): void {
        $this->resetAfterTest();
        $data = $this->setup_course([]);
        $courseid = (int) $data['course']->id;
        $scale = $this->getDataGenerator()->create_scale(['scale' => 'Low,Mid,High', 'courseid' => $courseid]);
        $record = $this->getDataGenerator()->create_grade_item([
            'courseid' => $courseid, 'itemname' => 'Scaled', 'scaleid' => $scale->id,
        ]);
        $item = grade_item::fetch(['id' => $record->id]);
        $item->update_final_grade((int) $data['student']->id, 2.0, 'test');

        $items = $this->items_by_name($this->call($data['student'], $courseid, true));
        $this->assertSame('Mid', $items['Scaled']['grade_str']);

        $item->set_hidden(time() + DAYSECS);
        $items = $this->items_by_name($this->call($data['student'], $courseid, true));
        $this->assertNull($items['Scaled']['grade']);
        $this->assertSame('not shown yet', $items['Scaled']['grade_str']);
    }

    /**
     * Without gradereport/user:view the course is denied, like the user report.
     */
    public function test_user_report_capability_required(): void {
        global $DB;
        $this->resetAfterTest();
        $data = $this->setup_course([1 => 60.0]);
        $courseid = (int) $data['course']->id;
        $studentrole = (int) $DB->get_field('role', 'id', ['shortname' => 'student']);
        assign_capability('gradereport/user:view', CAP_PROHIBIT, $studentrole, \context_course::instance($courseid)->id, true);

        $output = $this->call($data['student'], $courseid, true);

        $this->assertSame('access denied', $output['courses'][0]['final_grade_str']);
        $this->assertArrayNotHasKey('items', $output['courses'][0]);
    }

    /**
     * Courses without access are reported as such, never as "not yet graded".
     */
    public function test_denied_course_in_summary(): void {
        $this->resetAfterTest();
        $data = $this->setup_course([1 => 60.0]);
        $other = $this->getDataGenerator()->create_course(['showgrades' => 0]);
        $this->getDataGenerator()->enrol_user($data['student']->id, $other->id, 'student');
        $this->setUser($data['student']);

        $output = moodle_my_grades::execute([], $data['student']);

        $this->assertCount(2, $output['courses']);
        $this->assertStringContainsString('0 not yet graded', $output['summary']);
        $this->assertStringContainsString('not available to you in 1 further course', $output['summary']);

        $output = moodle_my_grades::execute(['course_id' => (int) $other->id], $data['student']);
        $this->assertSame('Grades of the requested course are not available to you.', $output['summary']);
    }

    /**
     * The hidden-category rule also applies to categories further up the tree.
     */
    public function test_items_below_nested_hidden_category(): void {
        $this->resetAfterTest();
        $data = $this->setup_course([1 => 50.0]);
        $courseid = (int) $data['course']->id;
        $outer = $this->getDataGenerator()->create_grade_category(['courseid' => $courseid, 'fullname' => 'Outer']);
        $inner = $this->getDataGenerator()->create_grade_category([
            'courseid' => $courseid, 'fullname' => 'Inner', 'parent' => $outer->id,
        ]);
        $record = $this->getDataGenerator()->create_grade_item([
            'courseid' => $courseid, 'itemname' => 'Deep', 'grademax' => 100, 'categoryid' => $inner->id,
        ]);
        grade_item::fetch(['id' => $record->id])->update_final_grade((int) $data['student']->id, 45.0, 'test');

        // Control: nothing hidden.
        $this->assertArrayHasKey('Deep', $this->items_by_name($this->call($data['student'], $courseid, true)));

        grade_category::fetch(['id' => $outer->id])->set_hidden(1);
        $this->assertFalse(grade_category::fetch(['id' => $inner->id])->is_hidden(), 'Precondition: not cascaded.');
        $items = $this->items_by_name($this->call($data['student'], $courseid, true));
        $this->assertArrayNotHasKey('Deep', $items);
        $this->assertArrayHasKey('Item 1', $items);
    }

    /**
     * A hidden item without a grade is "not graded yet", not withheld; hidden feedback alone is withheld.
     */
    public function test_withheld_flag_needs_an_existing_grade(): void {
        $this->resetAfterTest();
        $data = $this->setup_course([1 => 50.0]);
        $courseid = (int) $data['course']->id;
        $studentid = (int) $data['student']->id;
        $empty = grade_item::fetch(['id' => $this->getDataGenerator()->create_grade_item([
            'courseid' => $courseid, 'itemname' => 'Empty', 'grademax' => 100,
        ])->id]);
        $feedbackonly = grade_item::fetch(['id' => $this->getDataGenerator()->create_grade_item([
            'courseid' => $courseid, 'itemname' => 'Feedback only', 'grademax' => 100,
        ])->id]);
        $feedbackonly->update_final_grade($studentid, null, 'test', 'Well done', FORMAT_PLAIN);
        $empty->set_hidden(time() + DAYSECS);
        $feedbackonly->set_hidden(time() + DAYSECS);

        $items = $this->items_by_name($this->call($data['student'], $courseid, true));

        $this->assertNull($items['Empty']['grade']);
        $this->assertSame('-', $items['Empty']['grade_str']);
        $this->assertFalse($items['Empty']['grade_withheld']);
        $this->assertNull($items['Feedback only']['grade']);
        $this->assertTrue($items['Feedback only']['grade_withheld']);
    }

    /**
     * The output schema declares the new flags as booleans.
     */
    public function test_output_schema_declares_flags(): void {
        $course = moodle_my_grades::output_schema()['properties']['courses']['items']['properties'];
        $this->assertSame('boolean', $course['grade_withheld']['type']);
        $this->assertSame('boolean', $course['grades_available']['type']);
        $this->assertSame('boolean', $course['items']['items']['properties']['grade_withheld']['type']);
        $this->assertNotEmpty($course['grade_withheld']['description']);
    }

    /**
     * A course filter outside the user's courses gets the neutral message.
     */
    public function test_unavailable_course_message(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $stranger = $this->getDataGenerator()->create_user();
        $this->setUser($stranger);

        $this->expectException(\webservice_elediamcp\local\ai\tool_exception::class);
        $this->expectExceptionMessage("Course {$course->id} is not available to you.");
        moodle_my_grades::execute(['course_id' => (int) $course->id], $stranger);
    }

    /**
     * The tool description explains the flags and null values (the agent only forwards the description).
     */
    public function test_description_explains_flags(): void {
        $description = moodle_my_grades::description();
        $this->assertStringContainsString('grade_withheld', $description);
        $this->assertStringContainsString('grades_available', $description);
        $this->assertStringContainsString('has_passed null', $description);
    }
}

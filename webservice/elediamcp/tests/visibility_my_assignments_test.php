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
use grade_grade;
use grade_item;
use stdClass;
use webservice_elediamcp\local\ai\tools\moodle_due_work;
use webservice_elediamcp\local\ai\tools\moodle_my_assignments;

/**
 * Visibility of grades and effective dates in moodle_my_assignments.
 *
 * The tool must show a grade only when Moodle shows it to the learner
 * (marking workflow released, not hidden in the gradebook) and must use
 * the dates of the learner's user or group override.
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \webservice_elediamcp\local\ai\tools\moodle_my_assignments
 * @covers      \webservice_elediamcp\local\ai\tools\moodle_due_work
 */
final class visibility_my_assignments_test extends advanced_testcase {
    /**
     * Load the assignment and gradebook libraries.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        require_once($CFG->libdir . '/gradelib.php');
    }

    /**
     * Create an assignment and its assign object.
     *
     * @param stdClass $course Course.
     * @param array $params Extra module parameters.
     * @return array{0: stdClass, 1: assign} Module record and assign object.
     */
    private function create_assign(stdClass $course, array $params = []): array {
        $module = $this->getDataGenerator()->create_module('assign', array_merge([
            'course' => $course->id,
            'name' => 'Essay',
            'grade' => 100,
            'assignsubmission_onlinetext_enabled' => 1,
        ], $params));
        [$course, $cm] = get_course_and_cm_from_cmid((int) $module->cmid, 'assign');
        $assign = new assign(context_module::instance((int) $cm->id), $cm, $course);
        return [$module, $assign];
    }

    /**
     * Grade a learner as a teacher, optionally setting the workflow state.
     *
     * @param assign $assign Assignment.
     * @param stdClass $teacher Grading teacher.
     * @param int $userid Learner id.
     * @param float $grade Grade value.
     * @param string|null $workflowstate Marking workflow state or null.
     */
    private function grade(assign $assign, stdClass $teacher, int $userid, float $grade, ?string $workflowstate = null): void {
        $this->setUser($teacher);
        $data = new stdClass();
        $data->grade = $grade;
        $data->attemptnumber = -1;
        if ($workflowstate !== null) {
            $data->workflowstate = $workflowstate;
        }
        $assign->save_grade($userid, $data);
    }

    /**
     * Find the entry for one assignment in the tool output.
     *
     * @param array $output Tool output.
     * @param int $cmid Course module id.
     * @return array|null
     */
    private function entry(array $output, int $cmid): ?array {
        foreach ($output['assignments'] as $entry) {
            if ($entry['cmid'] === $cmid) {
                return $entry;
            }
        }
        return null;
    }

    /**
     * Assert that an entry carries no grade, as for an ungraded assignment.
     *
     * @param array|null $entry Tool entry.
     */
    private function assert_no_grade(?array $entry): void {
        $this->assertNotNull($entry);
        $this->assertNull($entry['grade']);
        $this->assertSame('-', $entry['grade_str']);
        $this->assertNull($entry['graded_iso']);
        $this->assertNotSame('graded', $entry['status']);
    }

    /**
     * Set up a course with a teacher and a learner.
     *
     * @return array{0: stdClass, 1: stdClass, 2: stdClass} Course, teacher, learner.
     */
    private function setup_course(): array {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        return [$course, $teacher, $student];
    }

    /**
     * A grade in a marking workflow that is not released stays hidden.
     */
    public function test_workflow_grade_not_released_is_withheld(): void {
        $this->resetAfterTest(true);
        [$course, $teacher, $student] = $this->setup_course();
        [$module, $assign] = $this->create_assign($course, ['markingworkflow' => 1]);
        $this->grade($assign, $teacher, (int) $student->id, 77, ASSIGN_MARKING_WORKFLOW_STATE_READYFORRELEASE);

        $this->setUser($student);
        $output = moodle_my_assignments::execute([], $student);

        $entry = $this->entry($output, (int) $module->cmid);
        $this->assert_no_grade($entry);
        $this->assertTrue($entry['grade_withheld']);
        $this->assertSame('notsubmitted', $entry['status']);
        $this->assertStringContainsString('not released to you yet', $output['summary']);

        $graded = moodle_my_assignments::execute(['status' => 'graded'], $student);
        $this->assertSame(0, $graded['total']);
    }

    /**
     * A released workflow grade is shown (control case).
     */
    public function test_workflow_grade_released_is_shown(): void {
        $this->resetAfterTest(true);
        [$course, $teacher, $student] = $this->setup_course();
        [$module, $assign] = $this->create_assign($course, ['markingworkflow' => 1]);
        $this->grade($assign, $teacher, (int) $student->id, 77, ASSIGN_MARKING_WORKFLOW_STATE_RELEASED);

        $this->setUser($student);
        $output = moodle_my_assignments::execute([], $student);

        $entry = $this->entry($output, (int) $module->cmid);
        $this->assertSame('graded', $entry['status']);
        $this->assertSame(77.0, $entry['grade']);
        $this->assertSame('77 / 100', $entry['grade_str']);
        $this->assertNotNull($entry['graded_iso']);
        $this->assertStringNotContainsString('not released to you yet', $output['summary']);
    }

    /**
     * Without a workflow a visible gradebook grade is shown (control case).
     */
    public function test_grade_without_workflow_is_shown(): void {
        $this->resetAfterTest(true);
        [$course, $teacher, $student] = $this->setup_course();
        [$module, $assign] = $this->create_assign($course);
        $this->grade($assign, $teacher, (int) $student->id, 55);

        $this->setUser($student);
        $output = moodle_my_assignments::execute([], $student);

        $entry = $this->entry($output, (int) $module->cmid);
        $this->assertSame('graded', $entry['status']);
        $this->assertSame(55.0, $entry['grade']);
        $this->assertFalse($entry['grade_withheld']);
        $this->assertSame('55 / 100', $entry['grade_str']);
        $this->assertNotNull($entry['graded_iso']);
    }

    /**
     * Gradebook hiding cases.
     *
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function hidden_grade_provider(): array {
        return [
            'grade hidden' => ['grade', false],
            'grade hidden until the future' => ['future', false],
            'grade hidden until the past' => ['past', true],
            'grade item hidden' => ['item', false],
        ];
    }

    /**
     * A grade hidden in the gradebook is withheld; an expired hidden-until shows it.
     *
     * @dataProvider hidden_grade_provider
     * @param string $mode How the grade is hidden.
     * @param bool $visible Whether the learner sees the grade in Moodle.
     */
    public function test_gradebook_hidden_grade(string $mode, bool $visible): void {
        $this->resetAfterTest(true);
        [$course, $teacher, $student] = $this->setup_course();
        [$module, $assign] = $this->create_assign($course);
        $this->grade($assign, $teacher, (int) $student->id, 88);

        $item = grade_item::fetch([
            'itemtype' => 'mod', 'itemmodule' => 'assign',
            'iteminstance' => (int) $module->id, 'courseid' => (int) $course->id,
        ]);
        if ($mode === 'item') {
            $item->set_hidden(1);
        } else {
            $gradegrade = grade_grade::fetch(['itemid' => $item->id, 'userid' => $student->id]);
            $hidden = ['grade' => 1, 'future' => time() + DAYSECS, 'past' => time() - DAYSECS][$mode];
            $gradegrade->set_hidden($hidden);
        }

        $this->setUser($student);
        $output = moodle_my_assignments::execute([], $student);
        $entry = $this->entry($output, (int) $module->cmid);

        if ($visible) {
            $this->assertSame('graded', $entry['status']);
            $this->assertSame(88.0, $entry['grade']);
            $this->assertFalse($entry['grade_withheld']);
            $this->assertStringNotContainsString('not released to you yet', $output['summary']);
        } else {
            $this->assert_no_grade($entry);
            $this->assertTrue($entry['grade_withheld']);
            $this->assertStringContainsString('not released to you yet', $output['summary']);
        }
    }

    /**
     * A user who may grade the assignment sees a hidden grade, as on the assignment page.
     */
    public function test_hidden_grade_visible_with_grade_capability(): void {
        global $DB;
        $this->resetAfterTest(true);
        [$course, $teacher, $student] = $this->setup_course();
        [$module, $assign] = $this->create_assign($course);
        $this->grade($assign, $teacher, (int) $student->id, 88);
        $item = grade_item::fetch([
            'itemtype' => 'mod', 'itemmodule' => 'assign',
            'iteminstance' => (int) $module->id, 'courseid' => (int) $course->id,
        ]);
        $item->set_hidden(1);

        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'student']);
        assign_capability('mod/assign:grade', CAP_ALLOW, $roleid, context_module::instance((int) $module->cmid)->id);

        $this->setUser($student);
        $output = moodle_my_assignments::execute([], $student);
        $entry = $this->entry($output, (int) $module->cmid);
        $this->assertSame('graded', $entry['status']);
        $this->assertSame(88.0, $entry['grade']);
    }

    /**
     * No response cache: hiding a grade takes effect on the next call.
     */
    public function test_hiding_takes_effect_immediately(): void {
        $this->resetAfterTest(true);
        [$course, $teacher, $student] = $this->setup_course();
        [$module, $assign] = $this->create_assign($course);
        $this->grade($assign, $teacher, (int) $student->id, 66);

        $this->setUser($student);
        $first = moodle_my_assignments::execute([], $student);
        $this->assertSame(66.0, $this->entry($first, (int) $module->cmid)['grade']);

        $item = grade_item::fetch([
            'itemtype' => 'mod', 'itemmodule' => 'assign',
            'iteminstance' => (int) $module->id, 'courseid' => (int) $course->id,
        ]);
        $gradegrade = grade_grade::fetch(['itemid' => $item->id, 'userid' => $student->id]);
        $gradegrade->set_hidden(1);

        $second = moodle_my_assignments::execute([], $student);
        $this->assert_no_grade($this->entry($second, (int) $module->cmid));
    }

    /**
     * Override kinds.
     *
     * @return array<string, array{0: string}>
     */
    public static function override_provider(): array {
        return [
            'user override' => ['user'],
            'group override' => ['group'],
        ];
    }

    /**
     * User and group overrides move due, cut-off and start dates and the overdue flag.
     *
     * @dataProvider override_provider
     * @param string $kind Override kind.
     */
    public function test_override_dates_and_overdue(string $kind): void {
        global $DB;
        $this->resetAfterTest(true);
        [$course, $teacher, $student] = $this->setup_course();
        $other = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $now = time();
        [$module] = $this->create_assign($course, [
            'duedate' => $now - DAYSECS,
            'cutoffdate' => $now - HOURSECS,
            'allowsubmissionsfromdate' => $now - (3 * DAYSECS),
        ]);

        $override = [
            'assignid' => (int) $module->id,
            'duedate' => $now + (2 * DAYSECS),
            'cutoffdate' => $now + (3 * DAYSECS),
            'allowsubmissionsfromdate' => $now - (2 * DAYSECS),
        ];
        if ($kind === 'user') {
            $override['userid'] = (int) $student->id;
        } else {
            $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
            $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $student->id]);
            $override['groupid'] = (int) $group->id;
            $override['sortorder'] = 1;
        }
        $DB->insert_record('assign_overrides', (object) $override);

        $this->setUser($student);
        $entry = $this->entry(moodle_my_assignments::execute([], $student), (int) $module->cmid);
        $this->assertSame(gmdate('c', $now + (2 * DAYSECS)), $entry['duedate_iso']);
        $this->assertSame(gmdate('c', $now + (3 * DAYSECS)), $entry['cutoffdate_iso']);
        $this->assertSame(gmdate('c', $now - (2 * DAYSECS)), $entry['allowsubmissionsfromdate_iso']);
        $this->assertFalse($entry['overdue']);
        $overdue = moodle_my_assignments::execute(['status' => 'overdue'], $student);
        $this->assertSame(0, $overdue['total']);

        // Control: a learner without the override keeps the default dates.
        $this->setUser($other);
        $entry = $this->entry(moodle_my_assignments::execute([], $other), (int) $module->cmid);
        $this->assertSame(gmdate('c', $now - DAYSECS), $entry['duedate_iso']);
        $this->assertSame(gmdate('c', $now - HOURSECS), $entry['cutoffdate_iso']);
        $this->assertTrue($entry['overdue']);
    }

    /**
     * An override can also make an assignment overdue for one learner only.
     */
    public function test_override_earlier_due_date_makes_overdue(): void {
        global $DB;
        $this->resetAfterTest(true);
        [$course, , $student] = $this->setup_course();
        $now = time();
        [$module] = $this->create_assign($course, ['duedate' => $now + (5 * DAYSECS)]);
        $DB->insert_record('assign_overrides', (object) [
            'assignid' => (int) $module->id,
            'userid' => (int) $student->id,
            'duedate' => $now - HOURSECS,
        ]);

        $this->setUser($student);
        $entry = $this->entry(moodle_my_assignments::execute([], $student), (int) $module->cmid);
        $this->assertSame(gmdate('c', $now - HOURSECS), $entry['duedate_iso']);
        $this->assertTrue($entry['overdue']);
    }

    /**
     * Due work keeps an overdue assignment whose grade is not released, and
     * drops it once the grade is released.
     */
    public function test_due_work_ignores_unreleased_grade(): void {
        $this->resetAfterTest(true);
        [$course, $teacher, $student] = $this->setup_course();
        [$module, $assign] = $this->create_assign($course, [
            'markingworkflow' => 1,
            'duedate' => time() - DAYSECS,
            'name' => 'Lab report',
        ]);
        $this->grade($assign, $teacher, (int) $student->id, 40, ASSIGN_MARKING_WORKFLOW_STATE_INMARKING);

        $this->setUser($student);
        $args = ['course_id' => (int) $course->id, 'days_ahead' => 3, 'include_calendar' => false];
        $output = moodle_due_work::execute($args, $student);
        $titles = array_column($output['items'], 'title');
        $this->assertContains('Lab report', $titles);
        $this->assertSame(1, $output['overdue_count']);

        // Control: once released, the graded assignment is no longer due work.
        $this->grade($assign, $teacher, (int) $student->id, 40, ASSIGN_MARKING_WORKFLOW_STATE_RELEASED);
        $this->setUser($student);
        $output = moodle_due_work::execute($args, $student);
        $this->assertNotContains('Lab report', array_column($output['items'], 'title'));
        $this->assertSame(0, $output['overdue_count']);
    }

    /**
     * Extension cases: [due offset, extension offset, overdue].
     *
     * @return array<string, array{0: int, 1: int, 2: bool}>
     */
    public static function extension_provider(): array {
        return [
            'extension later than the passed due date' => [-DAYSECS, 2 * DAYSECS, false],
            'extension earlier than the passed due date' => [-HOURSECS, -2 * DAYSECS, true],
            'extension earlier than a future due date' => [2 * DAYSECS, -DAYSECS, false],
        ];
    }

    /**
     * A personal extension counts like on the assignment page: the later of
     * due date and extension decides about overdue.
     *
     * @dataProvider extension_provider
     * @param int $dueoffset Due date relative to now.
     * @param int $extoffset Extension relative to now.
     * @param bool $overdue Expected overdue flag.
     */
    public function test_extension_due_date(int $dueoffset, int $extoffset, bool $overdue): void {
        global $DB;
        $this->resetAfterTest(true);
        [$course, , $student] = $this->setup_course();
        $now = time();
        [$module] = $this->create_assign($course, ['duedate' => $now + $dueoffset, 'name' => 'Extended task']);
        // Insert directly: the UI refuses an extension before the due date,
        // but older data or the web service API may still contain one.
        $DB->insert_record('assign_user_flags', (object) [
            'userid' => (int) $student->id,
            'assignment' => (int) $module->id,
            'extensionduedate' => $now + $extoffset,
            'locked' => 0,
            'mailed' => 0,
            'workflowstate' => '',
            'allocatedmarker' => 0,
        ]);

        $this->setUser($student);
        $entry = $this->entry(moodle_my_assignments::execute([], $student), (int) $module->cmid);
        $this->assertSame(gmdate('c', $now + $dueoffset), $entry['duedate_iso']);
        $this->assertSame(gmdate('c', $now + $extoffset), $entry['extensionduedate_iso']);
        $this->assertSame($overdue, $entry['overdue']);

        $output = moodle_due_work::execute(['course_id' => (int) $course->id, 'include_calendar' => false], $student);
        $this->assertSame($overdue ? 1 : 0, $output['overdue_count']);
    }

    /**
     * An extension granted through the assignment API removes the overdue state.
     */
    public function test_granted_extension_is_not_overdue(): void {
        $this->resetAfterTest(true);
        [$course, $teacher, $student] = $this->setup_course();
        $now = time();
        [$module, $assign] = $this->create_assign($course, ['duedate' => $now - DAYSECS]);
        $this->setUser($teacher);
        $this->assertTrue($assign->save_user_extension((int) $student->id, $now + DAYSECS));

        $this->setUser($student);
        $entry = $this->entry(moodle_my_assignments::execute([], $student), (int) $module->cmid);
        $this->assertFalse($entry['overdue']);
        $this->assertSame(gmdate('c', $now + DAYSECS), $entry['extensionduedate_iso']);
        $this->assertSame(0, moodle_my_assignments::execute(['status' => 'overdue'], $student)['total']);
    }

    /**
     * Without an extension the field stays null (control case).
     */
    public function test_no_extension_field_is_null(): void {
        $this->resetAfterTest(true);
        [$course, , $student] = $this->setup_course();
        [$module] = $this->create_assign($course, ['duedate' => time() + DAYSECS]);
        $this->setUser($student);
        $entry = $this->entry(moodle_my_assignments::execute([], $student), (int) $module->cmid);
        $this->assertNull($entry['extensionduedate_iso']);
    }

    /**
     * In team mode the group submission decides the status.
     */
    public function test_team_submission_status(): void {
        global $DB;
        $this->resetAfterTest(true);
        [$course, $teacher, $student] = $this->setup_course();
        $mate = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $student->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $mate->id]);
        [$module, $assign] = $this->create_assign($course, ['teamsubmission' => 1, 'duedate' => time() - DAYSECS]);

        $this->setUser($mate);
        $submission = $assign->get_group_submission((int) $mate->id, 0, true);
        $DB->set_field('assign_submission', 'status', ASSIGN_SUBMISSION_STATUS_SUBMITTED, ['id' => $submission->id]);

        $this->setUser($student);
        $entry = $this->entry(moodle_my_assignments::execute([], $student), (int) $module->cmid);
        $this->assertSame('submitted', $entry['status']);
        $this->assertFalse($entry['overdue']);
        $this->assertNull($entry['grade']);

        // Grade for the team member, visible in the gradebook.
        $this->grade($assign, $teacher, (int) $student->id, 61);
        $this->setUser($student);
        $entry = $this->entry(moodle_my_assignments::execute([], $student), (int) $module->cmid);
        $this->assertSame('graded', $entry['status']);
        $this->assertSame(61.0, $entry['grade']);
    }

    /**
     * A scale grade is reported with the scale item, not the index alone.
     */
    public function test_scale_grade(): void {
        $this->resetAfterTest(true);
        [$course, $teacher, $student] = $this->setup_course();
        $scale = $this->getDataGenerator()->create_scale(['scale' => 'Weak,Good,Excellent', 'courseid' => $course->id]);
        [$module, $assign] = $this->create_assign($course, ['grade' => -((int) $scale->id)]);
        $this->grade($assign, $teacher, (int) $student->id, 2);

        $this->setUser($student);
        $entry = $this->entry(moodle_my_assignments::execute([], $student), (int) $module->cmid);
        $this->assertSame('graded', $entry['status']);
        $this->assertSame(2.0, $entry['grade']);
        $this->assertSame('Good', $entry['grade_str']);
        $this->assertNull($entry['grade_max']);
    }

    /**
     * The list is ordered by the effective due date of the user.
     */
    public function test_sorted_by_effective_due_date(): void {
        global $DB;
        $this->resetAfterTest(true);
        [$course, , $student] = $this->setup_course();
        $now = time();
        [$late] = $this->create_assign($course, ['name' => 'Late by default', 'duedate' => $now + (5 * DAYSECS)]);
        [$early] = $this->create_assign($course, ['name' => 'Early by default', 'duedate' => $now + (2 * DAYSECS)]);
        [$middle] = $this->create_assign($course, ['name' => 'Middle', 'duedate' => $now + (3 * DAYSECS)]);
        $DB->insert_record('assign_overrides', (object) [
            'assignid' => (int) $late->id, 'userid' => (int) $student->id, 'duedate' => $now + DAYSECS,
        ]);
        $DB->insert_record('assign_user_flags', (object) [
            'userid' => (int) $student->id, 'assignment' => (int) $early->id,
            'extensionduedate' => $now + (4 * DAYSECS), 'locked' => 0, 'mailed' => 0,
            'workflowstate' => '', 'allocatedmarker' => 0,
        ]);

        $this->setUser($student);
        $output = moodle_my_assignments::execute([], $student);
        $this->assertSame(
            [(int) $late->cmid, (int) $middle->cmid, (int) $early->cmid],
            array_column($output['assignments'], 'cmid')
        );
    }

    /**
     * The summary counts withheld grades among the listed assignments only,
     * with singular and plural wording.
     */
    public function test_withheld_summary_counts_listed_assignments(): void {
        $this->resetAfterTest(true);
        [$course, $teacher, $student] = $this->setup_course();
        [, $first] = $this->create_assign($course, ['markingworkflow' => 1, 'name' => 'First']);
        [, $second] = $this->create_assign($course, ['markingworkflow' => 1, 'name' => 'Second']);
        $this->create_assign($course, ['name' => 'Third']);
        $this->grade($first, $teacher, (int) $student->id, 50, ASSIGN_MARKING_WORKFLOW_STATE_INMARKING);
        $this->grade($second, $teacher, (int) $student->id, 60, ASSIGN_MARKING_WORKFLOW_STATE_READYFORRELEASE);

        $this->setUser($student);
        $output = moodle_my_assignments::execute([], $student);
        $this->assertSame(3, $output['total']);
        $this->assertStringContainsString('Of these 3, 2 assignments have a grade that is not released', $output['summary']);

        // Filter that excludes them: no hint.
        $submitted = moodle_my_assignments::execute(['status' => 'submitted'], $student);
        $this->assertStringNotContainsString('not released', $submitted['summary']);
    }

    /**
     * In the workflow state "Not marked" Moodle reports no grade, so there is no hint.
     */
    public function test_not_marked_state_gives_no_hint(): void {
        $this->resetAfterTest(true);
        [$course, $teacher, $student] = $this->setup_course();
        [$module, $assign] = $this->create_assign($course, ['markingworkflow' => 1]);
        $this->grade($assign, $teacher, (int) $student->id, 45, ASSIGN_MARKING_WORKFLOW_STATE_NOTMARKED);

        $this->setUser($student);
        $output = moodle_my_assignments::execute([], $student);
        $entry = $this->entry($output, (int) $module->cmid);
        $this->assert_no_grade($entry);
        $this->assertFalse($entry['grade_withheld']);
        $this->assertStringNotContainsString('not released', $output['summary']);
    }

    /**
     * A grade overridden in the gradebook is shown during the workflow, as
     * on the assignment page; feedback stays out of the tool anyway.
     */
    public function test_gradebook_override_shown_during_workflow(): void {
        $this->resetAfterTest(true);
        [$course, $teacher, $student] = $this->setup_course();
        [$module, $assign] = $this->create_assign($course, ['markingworkflow' => 1]);
        $this->grade($assign, $teacher, (int) $student->id, 30, ASSIGN_MARKING_WORKFLOW_STATE_INMARKING);
        $item = grade_item::fetch([
            'itemtype' => 'mod', 'itemmodule' => 'assign',
            'iteminstance' => (int) $module->id, 'courseid' => (int) $course->id,
        ]);
        $item->update_final_grade((int) $student->id, 70, 'gradebook');

        $this->setUser($student);
        $entry = $this->entry(moodle_my_assignments::execute([], $student), (int) $module->cmid);
        $this->assertSame('graded', $entry['status']);
        $this->assertSame(70.0, $entry['grade']);
    }

    /**
     * Blind marking keeps grades out of the gradebook, so the learner sees none.
     */
    public function test_blind_marking_grade_is_withheld(): void {
        $this->resetAfterTest(true);
        [$course, $teacher, $student] = $this->setup_course();
        [$module, $assign] = $this->create_assign($course, ['blindmarking' => 1]);
        $this->grade($assign, $teacher, (int) $student->id, 90);

        $this->setUser($student);
        $output = moodle_my_assignments::execute([], $student);
        $this->assert_no_grade($this->entry($output, (int) $module->cmid));
        $this->assertStringContainsString('not released to you yet', $output['summary']);
    }

    /**
     * Relative course dates move the due date by the user's start offset.
     */
    public function test_relative_course_dates(): void {
        global $CFG;
        $this->resetAfterTest(true);
        $CFG->enablecourserelativedates = 1;
        $now = time();
        $course = $this->getDataGenerator()->create_course([
            'relativedatesmode' => 1, 'startdate' => $now - (20 * DAYSECS),
        ]);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student', 'manual', $now - (5 * DAYSECS));
        [$module] = $this->create_assign($course, ['duedate' => $now - (4 * DAYSECS)]);

        $this->setUser($student);
        $entry = $this->entry(moodle_my_assignments::execute([], $student), (int) $module->cmid);
        $this->assertSame(gmdate('c', $now + (11 * DAYSECS)), $entry['duedate_iso']);
        $this->assertFalse($entry['overdue']);
    }

    /**
     * Database reads stay nearly constant with the number of assignments.
     */
    public function test_reads_do_not_grow_per_assignment(): void {
        global $DB;
        $this->resetAfterTest(true);
        $reads = [];
        foreach ([1, 9] as $count) {
            [$course, $teacher, $student] = $this->setup_course();
            for ($i = 0; $i < $count; $i++) {
                [, $assign] = $this->create_assign($course, [
                    'name' => 'Task ' . $i,
                    'duedate' => time() + (($i + 1) * DAYSECS),
                    'markingworkflow' => $i % 2,
                ]);
                $state = $i % 2 ? ASSIGN_MARKING_WORKFLOW_STATE_RELEASED : null;
                $this->grade($assign, $teacher, (int) $student->id, 10 + $i, $state);
            }
            $this->setUser($student);
            // Warm up the course caches (modinfo, groups), then measure.
            moodle_my_assignments::execute([], $student);
            $before = $DB->perf_get_reads();
            $output = moodle_my_assignments::execute([], $student);
            $reads[$count] = $DB->perf_get_reads() - $before;
            $this->assertSame($count, $output['total']);
        }
        // Measured on 5.2.3: 13 reads for 1 and for 9 assignments.
        $this->assertLessThanOrEqual($reads[1] + 8, $reads[9], 'At most one extra read per assignment.');
    }

    /**
     * Every entry carries grade_withheld as declared in the output schema;
     * an ungraded assignment reports false.
     */
    public function test_grade_withheld_field_in_schema_and_entries(): void {
        $this->resetAfterTest(true);
        [$course, , $student] = $this->setup_course();
        [$module] = $this->create_assign($course);

        $schema = moodle_my_assignments::output_schema()['properties']['assignments']['items'];
        $this->assertSame('boolean', $schema['properties']['grade_withheld']['type']);
        $this->assertContains('grade_withheld', $schema['required']);

        $this->setUser($student);
        $entry = $this->entry(moodle_my_assignments::execute([], $student), (int) $module->cmid);
        foreach ($schema['required'] as $key) {
            $this->assertArrayHasKey($key, $entry);
        }
        $this->assertFalse($entry['grade_withheld']);
        $this->assertNull($entry['grade']);
    }

    /**
     * A foreign and a missing course get the same neutral message.
     */
    public function test_course_filter_message_is_neutral(): void {
        global $DB;
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $missing = (int) $DB->get_field_sql('SELECT MAX(id) FROM {course}') + 100;
        $this->setUser($student);

        foreach ([(int) $course->id, $missing] as $courseid) {
            try {
                moodle_my_assignments::execute(['course_id' => $courseid], $student);
                $this->fail('Expected tool_exception for course ' . $courseid);
            } catch (\webservice_elediamcp\local\ai\tool_exception $e) {
                $this->assertSame("Course {$courseid} is not available to you.", $e->getMessage());
            }
        }
    }

    /**
     * The tool description explains grade_withheld and null grades, because
     * clients may pass only the description to the language model.
     */
    public function test_description_explains_withheld_grades(): void {
        $description = moodle_my_assignments::description();
        $this->assertStringContainsString('grade_withheld', $description);
        $this->assertStringContainsString('null', $description);
    }
}

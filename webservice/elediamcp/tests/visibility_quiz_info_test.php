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
use grade_item;
use mod_quiz\quiz_settings;
use stdClass;
use webservice_elediamcp\local\ai\tool_exception;
use webservice_elediamcp\local\ai\tools\moodle_quiz_info;

/**
 * Visibility tests for moodle_quiz_info: review options, gradebook visibility
 * and user/group overrides.
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \webservice_elediamcp\local\ai\tools\moodle_quiz_info
 */
final class visibility_quiz_info_test extends advanced_testcase {
    /** @var string Summary sentence for results that are not released. */
    private const HIDDEN_NOTE = 'not shown to you yet';

    /**
     * Load the quiz and gradebook libraries.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        require_once($CFG->libdir . '/gradelib.php');
    }

    /**
     * Create a course with a quiz holding one true/false question.
     *
     * @param array $settings Extra quiz settings.
     * @return array{0: stdClass, 1: stdClass} Course and quiz.
     */
    private function create_quiz(array $settings = []): array {
        $course = $this->getDataGenerator()->create_course();
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz = $quizgenerator->create_instance(['course' => $course->id, 'grade' => 100] + $settings);

        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('truefalse', null, ['category' => $category->id]);
        quiz_add_quiz_question($question->id, $quiz);
        quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();
        return [$course, $quiz];
    }

    /**
     * Let a user finish one correct attempt at the given time.
     *
     * @param stdClass $quiz Quiz record.
     * @param stdClass $user The user.
     * @param int $timefinish When the attempt was submitted.
     */
    private function finish_attempt(stdClass $quiz, stdClass $user, int $timefinish): void {
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $this->setUser($user);
        $attempt = $quizgenerator->create_attempt($quiz->id, $user->id);
        $quizgenerator->submit_responses($attempt->id, [1 => 'True'], false, true, $timefinish);
    }

    /**
     * Review settings with marks only after the quiz has closed.
     *
     * @return array<string, int>
     */
    private static function marks_after_close(): array {
        return ['marksduring' => 0, 'marksimmediately' => 0, 'marksopen' => 0, 'marksclosed' => 1];
    }

    /**
     * Call the tool for one quiz.
     *
     * @param stdClass $quiz Quiz record.
     * @param stdClass $user The user.
     * @return array<string, mixed>
     */
    private function run_tool(stdClass $quiz, stdClass $user): array {
        $this->setUser($user);
        return moodle_quiz_info::execute(['cmid' => (int) $quiz->cmid], $user);
    }

    /**
     * Control: with default review options the grade and the attempt marks stay visible.
     */
    public function test_marks_visible_by_default(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $quiz] = $this->create_quiz();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->finish_attempt($quiz, $student, time() - 600);

        $out = $this->run_tool($quiz, $student);

        $this->assertEqualsWithDelta(100.0, $out['quizzes'][0]['my_grade'], 0.01);
        $this->assertFalse($out['quizzes'][0]['grade_withheld']);
        $this->assertEqualsWithDelta(100.0, $out['attempts'][0]['grade'], 0.01);
        $this->assertFalse($out['attempts'][0]['grade_withheld']);
        // The history carries the attempt id for the quiz tools, besides the running number (#195).
        $this->assertSame(
            (int) $DB->get_field('quiz_attempts', 'id', ['quiz' => $quiz->id, 'userid' => $student->id]),
            $out['attempts'][0]['attempt_id']
        );
        $this->assertSame(1, $out['attempts'][0]['attempt']);
        $this->assertStringNotContainsString(self::HIDDEN_NOTE, $out['summary']);
    }

    /**
     * Marks only after close: hidden while the quiz is open, visible once it has closed.
     */
    public function test_marks_after_close(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $quiz] = $this->create_quiz(self::marks_after_close() + ['timeclose' => time() + HOURSECS]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->finish_attempt($quiz, $student, time() - 600);

        $out = $this->run_tool($quiz, $student);
        $this->assertNull($out['quizzes'][0]['my_grade']);
        $this->assertCount(1, $out['attempts']);
        $this->assertSame('finished', $out['attempts'][0]['state']);
        $this->assertNull($out['attempts'][0]['grade']);
        $this->assertTrue($out['quizzes'][0]['grade_withheld']);
        $this->assertTrue($out['attempts'][0]['grade_withheld']);
        $this->assertStringContainsString(self::HIDDEN_NOTE, $out['summary']);

        // After the quiz has closed the results are released. Moodle keeps the
        // gradebook item hidden until the close date, so update it like a quiz edit does.
        $DB->set_field('quiz', 'timeclose', time() - 60, ['id' => $quiz->id]);
        quiz_grade_item_update($DB->get_record('quiz', ['id' => $quiz->id]));
        $out = $this->run_tool($quiz, $student);
        $this->assertEqualsWithDelta(100.0, $out['quizzes'][0]['my_grade'], 0.01);
        $this->assertEqualsWithDelta(100.0, $out['attempts'][0]['grade'], 0.01);
        $this->assertFalse($out['quizzes'][0]['grade_withheld']);
        $this->assertFalse($out['attempts'][0]['grade_withheld']);
        $this->assertStringNotContainsString(self::HIDDEN_NOTE, $out['summary']);
    }

    /**
     * A user override that keeps the quiz open also keeps the marks hidden for that user.
     */
    public function test_marks_after_close_respects_user_override(): void {
        $this->resetAfterTest();
        [$course, $quiz] = $this->create_quiz(self::marks_after_close() + ['timeclose' => time() - 60]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $other = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_override([
            'quiz' => $quiz->id, 'userid' => $student->id, 'timeclose' => time() + HOURSECS,
        ]);
        $this->finish_attempt($quiz, $student, time() - 600);
        $this->finish_attempt($quiz, $other, time() - 600);

        $out = $this->run_tool($quiz, $student);
        $this->assertNull($out['quizzes'][0]['my_grade']);
        $this->assertNull($out['attempts'][0]['grade']);

        // Without an override the quiz is closed and the marks are released.
        $out = $this->run_tool($quiz, $other);
        $this->assertEqualsWithDelta(100.0, $out['quizzes'][0]['my_grade'], 0.01);
        $this->assertEqualsWithDelta(100.0, $out['attempts'][0]['grade'], 0.01);
    }

    /**
     * Marks never shown: no grade and no attempt marks, even after close.
     */
    public function test_marks_never(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $quiz] = $this->create_quiz([
            'marksduring' => 0, 'marksimmediately' => 0, 'marksopen' => 0, 'marksclosed' => 0,
            'timeclose' => time() + HOURSECS,
        ]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->finish_attempt($quiz, $student, time() - 600);
        $DB->set_field('quiz', 'timeclose', time() - 60, ['id' => $quiz->id]);
        quiz_grade_item_update($DB->get_record('quiz', ['id' => $quiz->id]));

        $out = $this->run_tool($quiz, $student);
        $this->assertNull($out['quizzes'][0]['my_grade']);
        $this->assertNull($out['attempts'][0]['grade']);
        $this->assertSame(1, $out['quizzes'][0]['my_attempt_count']);
        $this->assertStringContainsString(self::HIDDEN_NOTE, $out['summary']);

        // The list mode withholds the grade as well.
        $list = moodle_quiz_info::execute(['course_id' => (int) $course->id], $student);
        $this->assertNull($list['quizzes'][0]['my_grade']);
        $this->assertStringContainsString(self::HIDDEN_NOTE, $list['summary']);
    }

    /**
     * Marks hidden immediately after the attempt: hidden in the first two minutes, then shown.
     *
     * The gradebook item stays visible here (marks are shown later while open), so this
     * also covers "overall grade withheld by the review options, gradebook visible".
     */
    public function test_marks_immediately_after(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $quiz] = $this->create_quiz(['marksimmediately' => 0]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->finish_attempt($quiz, $student, time());
        $gradeitem = grade_item::fetch([
            'itemtype' => 'mod', 'itemmodule' => 'quiz', 'iteminstance' => $quiz->id,
            'itemnumber' => 0, 'courseid' => $course->id,
        ]);
        $this->assertFalse((bool) $gradeitem->get_grade($student->id, false)->is_hidden());

        $out = $this->run_tool($quiz, $student);
        $this->assertNull($out['quizzes'][0]['my_grade']);
        $this->assertNull($out['attempts'][0]['grade']);
        $this->assertStringContainsString(self::HIDDEN_NOTE, $out['summary']);

        // Later while the quiz is open the marks are shown.
        $DB->set_field('quiz_attempts', 'timefinish', time() - 600, ['quiz' => $quiz->id, 'userid' => $student->id]);
        $out = $this->run_tool($quiz, $student);
        $this->assertEqualsWithDelta(100.0, $out['quizzes'][0]['my_grade'], 0.01);
        $this->assertEqualsWithDelta(100.0, $out['attempts'][0]['grade'], 0.01);
        $this->assertStringNotContainsString(self::HIDDEN_NOTE, $out['summary']);
    }

    /**
     * Two finished attempts in different review states: only the later-released one has marks,
     * and the overall grade needs visible marks for every attempt (overallstats on view.php).
     */
    public function test_mixed_finished_attempts(): void {
        $this->resetAfterTest();
        [$course, $quiz] = $this->create_quiz(['marksimmediately' => 0]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->finish_attempt($quiz, $student, time() - 600);
        $this->finish_attempt($quiz, $student, time());

        $out = $this->run_tool($quiz, $student);
        $this->assertCount(2, $out['attempts']);
        $this->assertEqualsWithDelta(100.0, $out['attempts'][0]['grade'], 0.01);
        $this->assertNull($out['attempts'][1]['grade']);
        $this->assertNull($out['quizzes'][0]['my_grade']);
        $this->assertStringContainsString(self::HIDDEN_NOTE, $out['summary']);
    }

    /**
     * A running attempt with marks hidden during the attempt withholds the overall grade,
     * while the finished attempt keeps its marks.
     */
    public function test_running_attempt_with_marks_hidden_during(): void {
        $this->resetAfterTest();
        [$course, $quiz] = $this->create_quiz(['marksduring' => 0]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->finish_attempt($quiz, $student, time() - 600);
        $this->setUser($student);
        $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_attempt($quiz->id, $student->id);

        $out = $this->run_tool($quiz, $student);
        $this->assertCount(2, $out['attempts']);
        $this->assertEqualsWithDelta(100.0, $out['attempts'][0]['grade'], 0.01);
        $this->assertSame('inprogress', $out['attempts'][1]['state']);
        $this->assertNull($out['attempts'][1]['grade']);
        $this->assertFalse($out['attempts'][1]['grade_withheld']);
        $this->assertFalse($out['attempts'][0]['grade_withheld']);
        $this->assertNull($out['quizzes'][0]['my_grade']);
        $this->assertTrue($out['quizzes'][0]['grade_withheld']);
        $this->assertStringContainsString(self::HIDDEN_NOTE, $out['summary']);
    }

    /**
     * Control with marks shown during the attempt: the overall grade stays visible, and a
     * running attempt never reports interim marks (the quiz page shows marks for finished
     * attempts only).
     */
    public function test_running_attempt_with_marks_shown_during(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $quiz] = $this->create_quiz();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->finish_attempt($quiz, $student, time() - 600);
        $this->setUser($student);
        $running = $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_attempt($quiz->id, $student->id);
        // Interim marks as stored by behaviours with immediate feedback.
        $DB->set_field('quiz_attempts', 'sumgrades', 1.0, ['id' => $running->id]);

        $out = $this->run_tool($quiz, $student);
        $this->assertEqualsWithDelta(100.0, $out['quizzes'][0]['my_grade'], 0.01);
        $this->assertEqualsWithDelta(100.0, $out['attempts'][0]['grade'], 0.01);
        $this->assertNull($out['attempts'][1]['grade']);
        $this->assertFalse($out['attempts'][1]['grade_withheld']);
        $this->assertStringNotContainsString(self::HIDDEN_NOTE, $out['summary']);
    }

    /**
     * Users who can see quiz reports and hidden grades still see their marks.
     *
     * Teachers normally only make preview attempts (which the tool skips), so the
     * capabilities are given to the student role here.
     */
    public function test_marks_never_visible_with_report_capabilities(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $quiz] = $this->create_quiz([
            'marksduring' => 0, 'marksimmediately' => 0, 'marksopen' => 0, 'marksclosed' => 0,
        ]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'student']);
        $contextid = context_course::instance($course->id)->id;
        assign_capability('mod/quiz:viewreports', CAP_ALLOW, $roleid, $contextid);
        assign_capability('moodle/grade:viewhidden', CAP_ALLOW, $roleid, $contextid);
        $this->finish_attempt($quiz, $student, time() - 600);

        $out = $this->run_tool($quiz, $student);
        $this->assertEqualsWithDelta(100.0, $out['quizzes'][0]['my_grade'], 0.01);
        $this->assertEqualsWithDelta(100.0, $out['attempts'][0]['grade'], 0.01);
        $this->assertStringNotContainsString(self::HIDDEN_NOTE, $out['summary']);
    }

    /**
     * A quiz grade hidden in the gradebook is withheld (item hidden and grade hidden until).
     */
    public function test_grade_hidden_in_gradebook(): void {
        $this->resetAfterTest();
        [$course, $quiz] = $this->create_quiz();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->finish_attempt($quiz, $student, time() - 600);
        $gradeitem = grade_item::fetch([
            'itemtype' => 'mod', 'itemmodule' => 'quiz', 'iteminstance' => $quiz->id,
            'itemnumber' => 0, 'courseid' => $course->id,
        ]);

        // The single grade is hidden until tomorrow.
        $grade = $gradeitem->get_grade($student->id, false);
        $grade->set_hidden(time() + DAYSECS);
        $out = $this->run_tool($quiz, $student);
        $this->assertNull($out['quizzes'][0]['my_grade']);
        $this->assertStringContainsString(self::HIDDEN_NOTE, $out['summary']);

        // Visible again: the grade is shown.
        $grade = $gradeitem->get_grade($student->id, false);
        $grade->set_hidden(0);
        $out = $this->run_tool($quiz, $student);
        $this->assertEqualsWithDelta(100.0, $out['quizzes'][0]['my_grade'], 0.01);

        // The whole grade item is hidden.
        $gradeitem->set_hidden(1);
        $out = $this->run_tool($quiz, $student);
        $this->assertNull($out['quizzes'][0]['my_grade']);
        $this->assertStringContainsString(self::HIDDEN_NOTE, $out['summary']);
        $this->assertTrue($out['quizzes'][0]['grade_withheld']);
        // The review options still show the attempt marks; only the overall grade is withheld.
        $this->assertEqualsWithDelta(100.0, $out['attempts'][0]['grade'], 0.01);
        $this->assertFalse($out['attempts'][0]['grade_withheld']);
    }

    /**
     * moodle/grade:viewhidden keeps precedence over a hidden gradebook grade.
     */
    public function test_grade_hidden_in_gradebook_with_viewhidden(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $quiz] = $this->create_quiz();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->finish_attempt($quiz, $student, time() - 600);
        $gradeitem = grade_item::fetch([
            'itemtype' => 'mod', 'itemmodule' => 'quiz', 'iteminstance' => $quiz->id,
            'itemnumber' => 0, 'courseid' => $course->id,
        ]);
        $gradeitem->set_hidden(1);

        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'student']);
        assign_capability('moodle/grade:viewhidden', CAP_ALLOW, $roleid, context_course::instance($course->id)->id);

        $out = $this->run_tool($quiz, $student);
        $this->assertEqualsWithDelta(100.0, $out['quizzes'][0]['my_grade'], 0.01);
    }

    /**
     * A user override changes open/close dates, time limit and attempts for that user only.
     */
    public function test_user_override(): void {
        $this->resetAfterTest();
        $now = time();
        [$course, $quiz] = $this->create_quiz([
            'timeopen' => $now + DAYSECS, 'timeclose' => $now + 2 * DAYSECS,
            'timelimit' => 600, 'attempts' => 1,
        ]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $other = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_override([
            'quiz' => $quiz->id, 'userid' => $student->id,
            'timeopen' => $now - HOURSECS, 'timeclose' => $now + HOURSECS,
            'timelimit' => 1200, 'attempts' => 3,
        ]);

        $entry = $this->run_tool($quiz, $student)['quizzes'][0];
        $this->assertTrue($entry['is_open_now']);
        $this->assertSame(gmdate('c', $now - HOURSECS), $entry['timeopen_iso']);
        $this->assertSame(gmdate('c', $now + HOURSECS), $entry['timeclose_iso']);
        $this->assertSame(1200, $entry['timelimit_seconds']);
        $this->assertSame(3, $entry['attempts_allowed']);
        // No attempt and no grade: nothing is withheld.
        $this->assertNull($entry['my_grade']);
        $this->assertFalse($entry['grade_withheld']);

        // Control: the quiz defaults apply to everyone else.
        $entry = $this->run_tool($quiz, $other)['quizzes'][0];
        $this->assertFalse($entry['is_open_now']);
        $this->assertSame(gmdate('c', $now + 2 * DAYSECS), $entry['timeclose_iso']);
        $this->assertSame(600, $entry['timelimit_seconds']);
        $this->assertSame(1, $entry['attempts_allowed']);
    }

    /**
     * A group override applies to the members of that group.
     */
    public function test_group_override(): void {
        $this->resetAfterTest();
        $now = time();
        [$course, $quiz] = $this->create_quiz([
            'timeopen' => $now - DAYSECS, 'timeclose' => $now - HOURSECS, 'attempts' => 1,
        ]);
        $member = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $outsider = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $member->id]);
        $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_override([
            'quiz' => $quiz->id, 'groupid' => $group->id,
            'timeclose' => $now + DAYSECS, 'attempts' => 2,
        ]);

        $entry = $this->run_tool($quiz, $member)['quizzes'][0];
        $this->assertTrue($entry['is_open_now']);
        $this->assertSame(gmdate('c', $now + DAYSECS), $entry['timeclose_iso']);
        $this->assertSame(2, $entry['attempts_allowed']);

        $entry = $this->run_tool($quiz, $outsider)['quizzes'][0];
        $this->assertFalse($entry['is_open_now']);
        $this->assertSame(gmdate('c', $now - HOURSECS), $entry['timeclose_iso']);
        $this->assertSame(1, $entry['attempts_allowed']);
    }

    /**
     * A suspended enrolment gives no access; the same user with an active enrolment has access.
     */
    public function test_suspended_enrolment_rejected(): void {
        $this->resetAfterTest();
        [$course, $quiz] = $this->create_quiz();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $this->setUser($student);

        // Without an active enrolment there is no course to list, so nothing is returned.
        $out = moodle_quiz_info::execute(['cmid' => (int) $quiz->cmid], $student);
        $this->assertSame(0, $out['total']);
        $this->assertSame([], $out['quizzes']);
        try {
            moodle_quiz_info::execute(['course_id' => (int) $course->id], $student);
            $this->fail('Expected tool_exception for a suspended enrolment.');
        } catch (tool_exception $e) {
            $this->assertSame("Course {$course->id} is not available to you.", $e->getMessage());
        }
        $this->assertSame(0, moodle_quiz_info::execute([], $student)['total']);

        // Control: once the enrolment is active again, the quiz is listed.
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student', 'manual', 0, 0, ENROL_USER_ACTIVE);
        $out = moodle_quiz_info::execute(['cmid' => (int) $quiz->cmid], $student);
        $this->assertSame((int) $quiz->cmid, $out['quizzes'][0]['cmid']);
    }

    /**
     * The output schema declares grade_withheld as a required boolean on quizzes and attempts.
     */
    public function test_schema_declares_grade_withheld(): void {
        $schema = moodle_quiz_info::output_schema();
        foreach (['quizzes', 'attempts'] as $list) {
            $items = $schema['properties'][$list]['items'];
            $this->assertSame('boolean', $items['properties']['grade_withheld']['type']);
            $this->assertContains('grade_withheld', $items['required']);
        }
    }

    /**
     * A course the user cannot use gets the neutral message, whether unknown or foreign.
     */
    public function test_unavailable_course_message(): void {
        $this->resetAfterTest();
        [$course] = $this->create_quiz();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $other = $this->getDataGenerator()->create_course();
        $this->setUser($student);

        foreach ([(int) $other->id, (int) $other->id + 1000] as $courseid) {
            try {
                moodle_quiz_info::execute(['course_id' => $courseid], $student);
                $this->fail('Expected tool_exception for course ' . $courseid);
            } catch (tool_exception $e) {
                $this->assertSame("Course {$courseid} is not available to you.", $e->getMessage());
            }
        }
    }

    /**
     * The tool description explains null values and grade_withheld, because the agent
     * passes only the description (not the output schema) to the language model.
     */
    public function test_description_explains_withheld_grades(): void {
        $description = moodle_quiz_info::description();
        $this->assertStringContainsString('grade_withheld', $description);
        $this->assertStringContainsString('null', $description);
    }
}

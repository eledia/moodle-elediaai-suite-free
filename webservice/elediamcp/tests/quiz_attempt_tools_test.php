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
use mod_quiz\quiz_attempt;
use mod_quiz\quiz_settings;
use stdClass;
use webservice_elediamcp\local\ai\registry;
use webservice_elediamcp\local\ai\response_budget;
use webservice_elediamcp\local\ai\tool_exception;
use webservice_elediamcp\local\ai\tools\moodle_quiz_finish;
use webservice_elediamcp\local\ai\tools\moodle_quiz_get_page;
use webservice_elediamcp\local\ai\tools\moodle_quiz_get_review;
use webservice_elediamcp\local\ai\tools\moodle_quiz_save_answers;
use webservice_elediamcp\local\ai\tools\moodle_quiz_start_attempt;
use webservice_elediamcp\local\ai\tools\quiz_attempt_helper;
use webservice_elediamcp\local\tool_provider;

/**
 * Tests for the moodle_quiz_* tools that take a normal Moodle quiz through MCP.
 *
 * @package     webservice_elediamcp
 * @category    test
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \webservice_elediamcp\local\ai\tools\moodle_quiz_start_attempt
 * @covers      \webservice_elediamcp\local\ai\tools\moodle_quiz_get_page
 * @covers      \webservice_elediamcp\local\ai\tools\moodle_quiz_save_answers
 * @covers      \webservice_elediamcp\local\ai\tools\moodle_quiz_finish
 * @covers      \webservice_elediamcp\local\ai\tools\moodle_quiz_get_review
 * @covers      \webservice_elediamcp\local\ai\tools\quiz_attempt_helper
 */
final class quiz_attempt_tools_test extends advanced_testcase {
    /** @var stdClass Course. */
    private stdClass $course;

    /** @var stdClass Student taking the quiz. */
    private stdClass $student;

    /**
     * Shared setup.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $this->course = $this->getDataGenerator()->create_course();
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($this->student);
    }

    /**
     * Create a quiz with questions laid out on pages.
     *
     * @param array $pages One list per page of [qtype, which, overrides] entries for the question generator.
     * @param array $settings Quiz settings overriding the generator defaults.
     * @return stdClass Quiz record with cmid.
     */
    private function create_quiz(array $pages, array $settings = []): stdClass {
        global $DB;

        $quiz = $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_instance(
            ['course' => $this->course->id] + $settings
        );
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category([
            'contextid' => context_module::instance($quiz->cmid)->id,
        ]);
        foreach (array_values($pages) as $index => $questions) {
            foreach ($questions as $entry) {
                [$qtype, $which] = $entry;
                $question = $questiongenerator->create_question(
                    $qtype,
                    $which,
                    ['category' => $category->id] + ($entry[2] ?? [])
                );
                quiz_add_quiz_question($question->id, $quiz, $index + 1);
            }
        }
        quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();

        $record = $DB->get_record('quiz', ['id' => $quiz->id], '*', MUST_EXIST);
        $record->cmid = $quiz->cmid;
        return $record;
    }

    /**
     * Start an attempt through the tool and return its id.
     *
     * @param stdClass $quiz Quiz.
     * @return int
     */
    private function start(stdClass $quiz): int {
        $output = moodle_quiz_start_attempt::execute(['cmid' => (int) $quiz->cmid, 'confirm' => true], $this->student);
        $this->assertTrue($output['inline_supported'], (string) ($output['unsupported_reason'] ?? ''));
        return (int) $output['attempt_id'];
    }

    /**
     * The question on a page by slot.
     *
     * @param array $page Output of moodle_quiz_get_page.
     * @param int $slot Slot.
     * @return array
     */
    private function question_in(array $page, int $slot): array {
        foreach ($page['questions'] as $question) {
            if ($question['slot'] === $slot) {
                return $question;
            }
        }
        $this->fail("Slot {$slot} not on the page.");
    }

    /**
     * The option value whose label is the given text.
     *
     * @param array $input Input description.
     * @param string $label Label.
     * @return string
     */
    private function option_value(array $input, string $label): string {
        foreach ($input['options'] as $option) {
            if ($option['label'] === $label) {
                return $option['value'];
            }
        }
        $this->fail("No option labelled {$label}.");
    }

    /**
     * Assert that a call fails with a tool_exception carrying the error code.
     *
     * @param string $errorcode Expected errorcode in the exception context.
     * @param callable $call Call under test.
     * @return void
     */
    private function assert_tool_error(string $errorcode, callable $call): void {
        try {
            $call();
            $this->fail("Expected tool_exception {$errorcode}.");
        } catch (tool_exception $ex) {
            $this->assertSame($errorcode, $ex->get_context()['errorcode'] ?? null, $ex->getMessage());
        }
    }

    /**
     * The five tools are registered and part of the free edition.
     */
    public function test_tools_are_registered_in_free_edition(): void {
        $names = ['moodle_quiz_start_attempt', 'moodle_quiz_get_page', 'moodle_quiz_save_answers',
            'moodle_quiz_finish', 'moodle_quiz_get_review'];
        foreach ($names as $name) {
            $this->assertContains($name, registry::names());
            $this->assertContains($name, tool_provider::free_ai_tool_names());
        }
        $this->assertTrue(moodle_quiz_finish::annotations()['destructiveHint']);
        $this->assertTrue(moodle_quiz_get_review::annotations()['readOnlyHint']);
    }

    /**
     * A new attempt starts on page 0 with the page count of the quiz.
     */
    public function test_start_attempt_starts_new_attempt(): void {
        global $DB;
        $quiz = $this->create_quiz([[['truefalse', 'true']], [['truefalse', 'true']]]);

        $output = moodle_quiz_start_attempt::execute(['cmid' => (int) $quiz->cmid], $this->student);

        $this->assertIsInt($output['attempt_id']);
        $this->assertSame('inprogress', $output['state']);
        $this->assertFalse($output['resumed']);
        $this->assertSame(2, $output['page_count']);
        $this->assertSame(0, $output['current_page']);
        $this->assertNull($output['time_limit_seconds']);
        $this->assertNull($output['time_left_seconds']);
        $this->assertTrue($output['inline_supported']);
        $this->assertSame(1, $DB->count_records('quiz_attempts', ['quiz' => $quiz->id, 'userid' => $this->student->id]));
    }

    /**
     * Calling start again continues the open attempt instead of opening a second one.
     */
    public function test_start_attempt_continues_open_attempt(): void {
        global $DB;
        $quiz = $this->create_quiz([[['truefalse', 'true']]]);

        $first = moodle_quiz_start_attempt::execute(['cmid' => (int) $quiz->cmid], $this->student);
        $second = moodle_quiz_start_attempt::execute(['cmid' => (int) $quiz->cmid], $this->student);

        $this->assertSame($first['attempt_id'], $second['attempt_id']);
        $this->assertTrue($second['resumed']);
        $this->assertSame(1, $DB->count_records('quiz_attempts', ['quiz' => $quiz->id, 'userid' => $this->student->id]));
    }

    /**
     * The time limit and the time left are reported.
     */
    public function test_start_attempt_reports_time_limit(): void {
        $quiz = $this->create_quiz([[['truefalse', 'true']]], ['timelimit' => 600]);

        $output = moodle_quiz_start_attempt::execute(['cmid' => (int) $quiz->cmid, 'confirm' => true], $this->student);

        $this->assertTrue($output['inline_supported']);
        $this->assertSame(600, $output['time_limit_seconds']);
        $this->assertGreaterThan(0, $output['time_left_seconds']);
        $this->assertLessThanOrEqual(600, $output['time_left_seconds']);
    }

    /**
     * A new attempt that starts a clock or uses up an attempt needs the user's agreement first.
     */
    public function test_start_attempt_asks_before_time_limit_or_limited_attempts(): void {
        global $DB;
        $quiz = $this->create_quiz([[['truefalse', 'true']]], ['timelimit' => 600, 'attempts' => 2]);
        $args = ['cmid' => (int) $quiz->cmid];

        $preview = moodle_quiz_start_attempt::execute($args, $this->student);
        $this->assertTrue($preview['requires_confirmation']);
        $this->assertNull($preview['attempt_id']);
        $this->assertSame('notstarted', $preview['state']);
        $this->assertSame(600, $preview['time_limit_seconds']);
        $this->assertSame(2, $preview['attempts_allowed']);
        $this->assertSame(0, $preview['attempts_used']);
        $this->assertStringContainsString('confirm=true', $preview['summary']);
        $this->assertSame(0, $DB->count_records('quiz_attempts', ['quiz' => $quiz->id]), 'Nothing was started.');

        $started = moodle_quiz_start_attempt::execute($args + ['confirm' => true], $this->student);
        $this->assertArrayNotHasKey('requires_confirmation', $started);
        $this->assertNotNull($started['attempt_id']);
        $this->assertSame(1, $DB->count_records('quiz_attempts', ['quiz' => $quiz->id]));

        // Continuing the open attempt needs no confirmation.
        $continued = moodle_quiz_start_attempt::execute($args, $this->student);
        $this->assertTrue($continued['resumed']);
        $this->assertSame($started['attempt_id'], $continued['attempt_id']);
    }

    /**
     * Without a time limit and with unlimited attempts the attempt starts at once.
     */
    public function test_start_attempt_without_consequences_starts_at_once(): void {
        $quiz = $this->create_quiz([[['truefalse', 'true']]]);
        $output = moodle_quiz_start_attempt::execute(['cmid' => (int) $quiz->cmid], $this->student);
        $this->assertNotNull($output['attempt_id']);
        $this->assertArrayNotHasKey('requires_confirmation', $output);
    }

    /**
     * Without mod/quiz:attempt no attempt can be started.
     */
    public function test_start_attempt_requires_attempt_capability(): void {
        global $DB;
        $quiz = $this->create_quiz([[['truefalse', 'true']]]);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        assign_capability('mod/quiz:attempt', CAP_PROHIBIT, $roleid, context_module::instance($quiz->cmid)->id);

        $this->expectException(\required_capability_exception::class);
        moodle_quiz_start_attempt::execute(['cmid' => (int) $quiz->cmid], $this->student);
    }

    /**
     * A hidden quiz cannot be started by a student.
     */
    public function test_start_attempt_rejects_hidden_quiz(): void {
        $quiz = $this->create_quiz([[['truefalse', 'true']]], ['visible' => 0]);

        $this->assert_tool_error('quiz_not_found', function () use ($quiz): void {
            moodle_quiz_start_attempt::execute(['cmid' => (int) $quiz->cmid], $this->student);
        });
    }

    /**
     * Access rules that need the browser start no attempt and return the fallback URL.
     *
     * @dataProvider browser_only_settings_provider
     * @param array $settings Quiz settings.
     */
    public function test_start_attempt_falls_back_to_browser(array $settings): void {
        global $DB;
        $quiz = $this->create_quiz([[['truefalse', 'true']]], $settings);

        $output = moodle_quiz_start_attempt::execute(['cmid' => (int) $quiz->cmid], $this->student);

        $this->assertFalse($output['inline_supported']);
        $this->assertNull($output['attempt_id']);
        $this->assertSame('notstarted', $output['state']);
        $this->assertStringContainsString('/mod/quiz/view.php?id=' . $quiz->cmid, $output['fallback_url']);
        $this->assertSame(0, $DB->count_records('quiz_attempts', ['quiz' => $quiz->id]));
    }

    /**
     * Quiz settings that require the browser.
     *
     * @return array
     */
    public static function browser_only_settings_provider(): array {
        return [
            'password' => [['quizpassword' => 'secret']],
            'delay between attempts' => [['delay1' => 3600]],
            'sequential navigation' => [['navmethod' => 'sequential']],
            'certainty-based marking' => [['preferredbehaviour' => 'deferredcbm']],
        ];
    }

    /**
     * A question type the app cannot show sends the learner to the browser.
     */
    public function test_start_attempt_unsupported_qtype_falls_back_to_browser(): void {
        $quiz = $this->create_quiz([[['truefalse', 'true'], ['multianswer', 'twosubq']]]);

        $output = moodle_quiz_start_attempt::execute(['cmid' => (int) $quiz->cmid], $this->student);

        $this->assertFalse($output['inline_supported']);
        $this->assertStringContainsString('multianswer', $output['unsupported_reason']);
    }

    /**
     * When no attempts are left, starting is refused with the quiz's own message.
     */
    public function test_start_attempt_rejects_when_no_attempts_left(): void {
        $quiz = $this->create_quiz([[['truefalse', 'true']]], ['attempts' => 1]);
        $attemptid = $this->start($quiz);
        moodle_quiz_finish::execute(['attempt_id' => $attemptid, 'confirm' => true], $this->student);

        $this->assert_tool_error('quiz_access_denied', function () use ($quiz): void {
            moodle_quiz_start_attempt::execute(['cmid' => (int) $quiz->cmid], $this->student);
        });
    }

    /**
     * The tools act only for the signed-in user.
     */
    public function test_tools_reject_acting_for_another_user(): void {
        $quiz = $this->create_quiz([[['truefalse', 'true']]]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'student'));

        $this->assert_tool_error('not_current_user', function () use ($quiz): void {
            moodle_quiz_start_attempt::execute(['cmid' => (int) $quiz->cmid], $this->student);
        });
    }

    /**
     * Each supported question type gets its structured input and no solution data.
     */
    public function test_get_page_describes_inputs(): void {
        $quiz = $this->create_quiz([[
            ['multichoice', 'one_of_four'],
            ['multichoice', 'two_of_four'],
            ['truefalse', 'true'],
            ['shortanswer', 'frogtoad'],
            ['numerical', 'pi'],
            ['match', 'foursubq'],
            ['essay', 'plain'],
        ]]);
        $attemptid = $this->start($quiz);

        $page = moodle_quiz_get_page::execute(['attempt_id' => $attemptid, 'page' => 0], $this->student);

        $this->assertSame(0, $page['page']);
        $this->assertSame(1, $page['page_count']);
        $kinds = array_map(static fn(array $q): string => $q['input']['kind'], $page['questions']);
        $this->assertSame(['single', 'multi', 'single', 'text', 'number', 'match', 'text'], $kinds);

        $single = $this->question_in($page, 1);
        $this->assertSame('1', $single['number']);
        $this->assertNotSame('', $single['text']);
        $this->assertStringNotContainsString('<', $single['text']);
        $this->assertCount(4, $single['input']['options']);
        foreach ($single['input']['options'] as $option) {
            $this->assertSame(['value', 'label'], array_keys($option));
        }
        $this->assertArrayNotHasKey('value', $single['input']);

        $truefalse = $this->question_in($page, 3);
        $this->assertSame(['1', '0'], array_column($truefalse['input']['options'], 'value'));

        $match = $this->question_in($page, 6);
        $this->assertCount(3, $match['input']['stems']);
        $this->assertNotEmpty($match['input']['options']);
    }

    /**
     * A page outside the attempt is rejected.
     */
    public function test_get_page_rejects_invalid_page(): void {
        $quiz = $this->create_quiz([[['truefalse', 'true']]]);
        $attemptid = $this->start($quiz);

        $this->assert_tool_error('invalid_page', function () use ($attemptid): void {
            moodle_quiz_get_page::execute(['attempt_id' => $attemptid, 'page' => 5], $this->student);
        });
    }

    /**
     * Another learner's attempt is invisible: the same answer as for a missing attempt.
     */
    public function test_tools_reject_other_users_attempt(): void {
        $quiz = $this->create_quiz([[['truefalse', 'true']]]);
        $attemptid = $this->start($quiz);
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($other);

        $calls = [
            fn() => moodle_quiz_get_page::execute(['attempt_id' => $attemptid, 'page' => 0], $other),
            fn() => moodle_quiz_save_answers::execute(
                ['attempt_id' => $attemptid, 'answers' => [['slot' => 1, 'value' => '1']]],
                $other
            ),
            fn() => moodle_quiz_finish::execute(['attempt_id' => $attemptid, 'confirm' => true], $other),
            fn() => moodle_quiz_get_review::execute(['attempt_id' => $attemptid], $other),
        ];
        foreach ($calls as $call) {
            $this->assert_tool_error('attempt_not_found', $call);
        }
        $this->setUser($this->student);
        $this->assertSame('inprogress', moodle_quiz_finish::execute(['attempt_id' => $attemptid], $this->student)['state']);
    }

    /**
     * A suspended enrolment blocks all five tools, like require_login() does in the browser.
     */
    public function test_tools_require_active_enrolment(): void {
        global $DB;
        $quiz = $this->create_quiz([[['truefalse', 'true']]]);
        $finished = $this->start_finished($quiz);
        $open = $this->start($quiz);

        $instance = $DB->get_record('enrol', ['courseid' => $this->course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        enrol_get_plugin('manual')->update_user_enrol($instance, $this->student->id, ENROL_USER_SUSPENDED);

        $this->assert_tool_error('quiz_not_found', function () use ($quiz): void {
            moodle_quiz_start_attempt::execute(['cmid' => (int) $quiz->cmid], $this->student);
        });
        $calls = [
            fn() => moodle_quiz_get_page::execute(['attempt_id' => $open, 'page' => 0], $this->student),
            fn() => moodle_quiz_save_answers::execute(
                ['attempt_id' => $open, 'answers' => [['slot' => 1, 'value' => '0']]],
                $this->student
            ),
            fn() => moodle_quiz_finish::execute(['attempt_id' => $open, 'confirm' => true], $this->student),
            fn() => moodle_quiz_get_review::execute(['attempt_id' => $finished], $this->student),
        ];
        foreach ($calls as $call) {
            $this->assert_tool_error('attempt_not_found', $call);
        }
    }

    /**
     * Start and finish a second attempt for the student (needs a quiz that allows several).
     *
     * @param stdClass $quiz Quiz.
     * @return int Attempt id of the finished attempt.
     */
    private function start_finished(stdClass $quiz): int {
        $attemptid = $this->start($quiz);
        moodle_quiz_finish::execute(['attempt_id' => $attemptid, 'confirm' => true], $this->student);
        return $attemptid;
    }

    /**
     * A finished attempt can no longer be read as a page, changed or submitted again.
     */
    public function test_tools_reject_finished_attempt(): void {
        $quiz = $this->create_quiz([[['truefalse', 'true']]]);
        $attemptid = $this->start($quiz);
        moodle_quiz_finish::execute(['attempt_id' => $attemptid, 'confirm' => true], $this->student);

        $calls = [
            fn() => moodle_quiz_get_page::execute(['attempt_id' => $attemptid, 'page' => 0], $this->student),
            fn() => moodle_quiz_save_answers::execute(
                ['attempt_id' => $attemptid, 'answers' => [['slot' => 1, 'value' => '0']]],
                $this->student
            ),
            fn() => moodle_quiz_finish::execute(['attempt_id' => $attemptid, 'confirm' => true], $this->student),
        ];
        foreach ($calls as $call) {
            $this->assert_tool_error('attempt_finished', $call);
        }
    }

    /**
     * Saved answers come back on the page, and saving them again adds no step.
     */
    public function test_save_answers_round_trip_and_idempotent(): void {
        global $DB;
        $quiz = $this->create_quiz([[
            ['multichoice', 'one_of_four'],
            ['multichoice', 'two_of_four'],
            ['shortanswer', 'frogtoad'],
            ['numerical', 'pi'],
            ['match', 'foursubq'],
        ]]);
        $attemptid = $this->start($quiz);
        $page = moodle_quiz_get_page::execute(['attempt_id' => $attemptid, 'page' => 0], $this->student);
        $single = $this->question_in($page, 1)['input'];
        $multi = $this->question_in($page, 2)['input'];
        $match = $this->question_in($page, 5)['input'];
        $stemkey = $match['stems'][0]['key'];
        $choice = $match['options'][0]['value'];

        $answers = [
            ['slot' => 1, 'value' => $this->option_value($single, 'One')],
            ['slot' => 2, 'values' => [$multi['options'][0]['value'], $multi['options'][2]['value']]],
            ['slot' => 3, 'value' => 'frog'],
            ['slot' => 4, 'value' => '3.14'],
            ['slot' => 5, 'values' => [$stemkey => $choice]],
        ];
        $saved = moodle_quiz_save_answers::execute(['attempt_id' => $attemptid, 'answers' => $answers], $this->student);
        $this->assertSame(5, $saved['saved']);

        $steps = $DB->count_records('question_attempt_steps');
        moodle_quiz_save_answers::execute(['attempt_id' => $attemptid, 'answers' => $answers], $this->student);
        $this->assertSame($steps, $DB->count_records('question_attempt_steps'));

        $page = moodle_quiz_get_page::execute(['attempt_id' => $attemptid, 'page' => 0], $this->student);
        $this->assertSame($this->option_value($single, 'One'), $this->question_in($page, 1)['input']['value']);
        $this->assertEqualsCanonicalizing(
            [$multi['options'][0]['value'], $multi['options'][2]['value']],
            $this->question_in($page, 2)['input']['values']
        );
        $this->assertSame('frog', $this->question_in($page, 3)['input']['value']);
        $this->assertSame('3.14', $this->question_in($page, 4)['input']['value']);
        $this->assertSame([$stemkey => $choice], (array) $this->question_in($page, 5)['input']['values']);
    }

    /**
     * An answer that does not fit the question is refused and nothing is saved.
     */
    public function test_save_answers_rejects_invalid_value(): void {
        global $DB;
        $quiz = $this->create_quiz([[['multichoice', 'one_of_four'], ['truefalse', 'true']]]);
        $attemptid = $this->start($quiz);
        $steps = $DB->count_records('question_attempt_steps');

        $this->assert_tool_error('invalid_answer', function () use ($attemptid): void {
            moodle_quiz_save_answers::execute(['attempt_id' => $attemptid, 'answers' => [
                ['slot' => 2, 'value' => '1'],
                ['slot' => 1, 'value' => 'not-an-option'],
            ]], $this->student);
        });
        $this->assertSame($steps, $DB->count_records('question_attempt_steps'));
    }

    /**
     * A slot that is not in the attempt is refused.
     */
    public function test_save_answers_rejects_unknown_slot(): void {
        $quiz = $this->create_quiz([[['truefalse', 'true']]]);
        $attemptid = $this->start($quiz);

        $this->assert_tool_error('invalid_answer', function () use ($attemptid): void {
            moodle_quiz_save_answers::execute(
                ['attempt_id' => $attemptid, 'answers' => [['slot' => 9, 'value' => '1']]],
                $this->student
            );
        });
    }

    /**
     * Finishing without confirm is only a preview with the unanswered count.
     */
    public function test_finish_without_confirm_is_preview(): void {
        global $DB;
        $quiz = $this->create_quiz([[['truefalse', 'true'], ['truefalse', 'true']]]);
        $attemptid = $this->start($quiz);
        moodle_quiz_save_answers::execute(
            ['attempt_id' => $attemptid, 'answers' => [['slot' => 1, 'value' => '1']]],
            $this->student
        );

        $preview = moodle_quiz_finish::execute(['attempt_id' => $attemptid], $this->student);

        $this->assertFalse($preview['finished']);
        $this->assertTrue($preview['requires_confirmation']);
        $this->assertSame(1, $preview['unanswered']);
        $this->assertSame('inprogress', $DB->get_field('quiz_attempts', 'state', ['id' => $attemptid]));
    }

    /**
     * Confirmed finishing lets Moodle grade the attempt and records the quiz grade.
     */
    public function test_finish_with_confirm_grades_in_moodle(): void {
        global $DB;
        $quiz = $this->create_quiz([[['truefalse', 'true'], ['truefalse', 'true']]]);
        $attemptid = $this->start($quiz);
        moodle_quiz_save_answers::execute(['attempt_id' => $attemptid, 'answers' => [
            ['slot' => 1, 'value' => '1'],
            ['slot' => 2, 'value' => '0'],
        ]], $this->student);

        $result = moodle_quiz_finish::execute(['attempt_id' => $attemptid, 'confirm' => true], $this->student);

        $this->assertTrue($result['finished']);
        $this->assertSame('finished', $result['state']);
        $this->assertEqualsWithDelta(50.0, $result['grade'], 0.01);
        $this->assertEqualsWithDelta(100.0, $result['max_grade'], 0.01);
        $this->assertTrue($result['review_available']);
        $this->assertSame('finished', $DB->get_field('quiz_attempts', 'state', ['id' => $attemptid]));
        $this->assertEqualsWithDelta(
            50.0,
            (float) $DB->get_field('quiz_grades', 'grade', ['quiz' => $quiz->id, 'userid' => $this->student->id]),
            0.01
        );
    }

    /**
     * The review shows state, marks, feedback and the right answer when the quiz allows it.
     */
    public function test_get_review_with_all_options(): void {
        $quiz = $this->create_quiz([[['truefalse', 'true'], ['truefalse', 'true']]]);
        $attemptid = $this->start($quiz);
        moodle_quiz_save_answers::execute(['attempt_id' => $attemptid, 'answers' => [
            ['slot' => 1, 'value' => '1'],
            ['slot' => 2, 'value' => '0'],
        ]], $this->student);
        moodle_quiz_finish::execute(['attempt_id' => $attemptid, 'confirm' => true], $this->student);

        $review = moodle_quiz_get_review::execute(['attempt_id' => $attemptid], $this->student);

        $this->assertEqualsWithDelta(50.0, $review['grade'], 0.01);
        $this->assertCount(2, $review['questions']);
        [$right, $wrong] = $review['questions'];
        $this->assertSame('correct', $right['state']);
        $this->assertEqualsWithDelta(1.0, $right['mark'], 0.001);
        $this->assertEqualsWithDelta(1.0, $right['max_mark'], 0.001);
        $this->assertSame('incorrect', $wrong['state']);
        $this->assertEqualsWithDelta(0.0, $wrong['mark'], 0.001);
        $this->assertArrayHasKey('right_answer', $wrong);
        $this->assertStringNotContainsString('<', $wrong['feedback']);
    }

    /**
     * Review options that hide marks, correctness and the right answer are respected.
     */
    public function test_get_review_respects_review_options(): void {
        $quiz = $this->create_quiz([[['truefalse', 'true']]], [
            'correctnessimmediately' => 0,
            'marksimmediately' => 0,
            'maxmarksimmediately' => 0,
            'rightanswerimmediately' => 0,
            'specificfeedbackimmediately' => 0,
            'generalfeedbackimmediately' => 0,
            'overallfeedbackimmediately' => 0,
        ]);
        $attemptid = $this->start($quiz);
        moodle_quiz_save_answers::execute(
            ['attempt_id' => $attemptid, 'answers' => [['slot' => 1, 'value' => '1']]],
            $this->student
        );
        $finish = moodle_quiz_finish::execute(['attempt_id' => $attemptid, 'confirm' => true], $this->student);
        $this->assertNull($finish['grade']);

        $review = moodle_quiz_get_review::execute(['attempt_id' => $attemptid], $this->student);

        $this->assertNull($review['grade']);
        $question = $review['questions'][0];
        $this->assertNull($question['state']);
        $this->assertNull($question['mark']);
        $this->assertNull($question['max_mark']);
        $this->assertSame('', $question['feedback']);
        $this->assertArrayNotHasKey('right_answer', $question);
    }

    /**
     * A quiz that allows no review at all refuses the review.
     */
    public function test_get_review_rejects_when_review_not_allowed(): void {
        $quiz = $this->create_quiz([[['truefalse', 'true']]], ['attemptimmediately' => 0]);
        $attemptid = $this->start($quiz);
        $finish = moodle_quiz_finish::execute(['attempt_id' => $attemptid, 'confirm' => true], $this->student);
        $this->assertFalse($finish['review_available']);

        $this->assert_tool_error('review_not_allowed', function () use ($attemptid): void {
            moodle_quiz_get_review::execute(['attempt_id' => $attemptid], $this->student);
        });
    }

    /**
     * An open attempt has no review yet.
     */
    public function test_get_review_rejects_unfinished_attempt(): void {
        $quiz = $this->create_quiz([[['truefalse', 'true']]]);
        $attemptid = $this->start($quiz);

        $this->assert_tool_error('attempt_not_finished', function () use ($attemptid): void {
            moodle_quiz_get_review::execute(['attempt_id' => $attemptid], $this->student);
        });
    }

    /**
     * Later than right after the attempt, reviewing needs mod/quiz:reviewmyattempts.
     */
    public function test_get_review_requires_reviewmyattempts_later(): void {
        global $DB;
        $quiz = $this->create_quiz([[['truefalse', 'true']]]);
        $attemptid = $this->start($quiz);
        moodle_quiz_finish::execute(['attempt_id' => $attemptid, 'confirm' => true], $this->student);
        // Move the submission out of the "immediately after" window.
        $DB->set_field('quiz_attempts', 'timefinish', time() - HOURSECS, ['id' => $attemptid]);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        assign_capability('mod/quiz:reviewmyattempts', CAP_PROHIBIT, $roleid, context_module::instance($quiz->cmid)->id);

        $this->expectException(\required_capability_exception::class);
        moodle_quiz_get_review::execute(['attempt_id' => $attemptid], $this->student);
    }
    /**
     * The newest saved answer text of a question slot of an attempt.
     *
     * @param int $attemptid Attempt id.
     * @param int $slot Slot.
     * @return string|null
     */
    private function saved_answer(int $attemptid, int $slot): ?string {
        global $DB;
        $quba = \question_engine::load_questions_usage_by_activity(
            (int) $DB->get_field('quiz_attempts', 'uniqueid', ['id' => $attemptid], MUST_EXIST)
        );
        $answer = $quba->get_question_attempt($slot)->get_last_qt_data()['answer'] ?? null;
        return $answer === null ? null : (string) $answer;
    }

    /**
     * Units for the numerical question: ungraded (default unit shown) or graded (penalty).
     *
     * @param bool $graded Whether the unit counts for the grade.
     * @return array Overrides for the question generator.
     */
    private function unit_overrides(bool $graded): array {
        return [
            'unitrole' => $graded ? '1' : '0',
            'multichoicedisplay' => '0',
            'unitgradingtypes' => '1',
            'unitpenalty' => 0.5,
            'unit' => ['m'],
            'multiplier' => ['1.0'],
        ];
    }

    /**
     * A unit that is not graded is added to the number, as before.
     */
    public function test_numerical_ungraded_unit_is_added(): void {
        $quiz = $this->create_quiz([[['numerical', 'pi', $this->unit_overrides(false)]]]);
        $attemptid = $this->start($quiz);

        $page = moodle_quiz_get_page::execute(['attempt_id' => $attemptid, 'page' => 0], $this->student);
        $input = $this->question_in($page, 1)['input'];
        $this->assertSame('m', $input['unit']);
        $this->assertArrayNotHasKey('unit_required', $input);

        moodle_quiz_save_answers::execute(
            ['attempt_id' => $attemptid, 'answers' => [['slot' => 1, 'value' => '42']]],
            $this->student
        );
        $this->assertSame('42 m', $this->saved_answer($attemptid, 1));
    }

    /**
     * A graded unit is never added for the learner: the answer is saved as typed and the question says so.
     */
    public function test_numerical_graded_unit_is_not_added(): void {
        $quiz = $this->create_quiz([[['numerical', 'pi', $this->unit_overrides(true)]]]);
        $attemptid = $this->start($quiz);

        $page = moodle_quiz_get_page::execute(['attempt_id' => $attemptid, 'page' => 0], $this->student);
        $input = $this->question_in($page, 1)['input'];
        $this->assertTrue($input['unit_required']);
        $this->assertSame('m', $input['default_unit']);
        $this->assertArrayNotHasKey('unit', $input);

        moodle_quiz_save_answers::execute(
            ['attempt_id' => $attemptid, 'answers' => [['slot' => 1, 'value' => '42']]],
            $this->student
        );
        $this->assertSame('42', $this->saved_answer($attemptid, 1), 'The unit is not added.');

        moodle_quiz_save_answers::execute(
            ['attempt_id' => $attemptid, 'answers' => [['slot' => 1, 'value' => '42 m']]],
            $this->student
        );
        $this->assertSame('42 m', $this->saved_answer($attemptid, 1), 'A unit typed by the learner is kept.');
    }
    /**
     * Quiz with many multiple-choice questions on one page, and a low response limit.
     *
     * @param int $count Number of questions.
     * @return stdClass Quiz.
     */
    private function create_big_quiz(int $count): stdClass {
        set_config('response_max_chars', 2000, 'webservice_elediamcp');
        $questions = array_fill(0, $count, ['multichoice', 'two_of_four']);
        return $this->create_quiz([$questions]);
    }

    /**
     * A page with many questions comes in parts that fit the response limit, in order and without gaps.
     */
    public function test_get_page_is_paged_to_the_response_limit(): void {
        $quiz = $this->create_big_quiz(12);
        $attemptid = $this->start($quiz);
        $budget = response_budget::usable_chars(2000);

        $slots = [];
        $offset = 0;
        $parts = 0;
        do {
            $page = moodle_quiz_get_page::execute(
                ['attempt_id' => $attemptid, 'page' => 0, 'offset' => $offset],
                $this->student
            );
            $parts++;
            $this->assertSame(12, $page['total']);
            $this->assertSame($offset, $page['offset']);
            $this->assertLessThanOrEqual($budget, response_budget::measure($page), 'Part ' . $parts);
            foreach ($page['questions'] as $question) {
                $slots[] = $question['slot'];
            }
            if ($page['has_more']) {
                $this->assertSame($offset + count($page['questions']), $page['next_offset']);
                $this->assertStringContainsString('next_offset', $page['summary']);
            } else {
                $this->assertNull($page['next_offset']);
            }
            $offset = (int) ($page['next_offset'] ?? 0);
        } while ($page['has_more'] && $parts < 20);

        $this->assertGreaterThan(1, $parts, 'The page does not fit one answer.');
        $this->assertSame(range(1, 12), $slots, 'Every question once, in order.');
    }

    /**
     * A page that fits is returned whole; an offset beyond the page is refused.
     */
    public function test_get_page_small_page_and_invalid_offset(): void {
        $quiz = $this->create_quiz([[['truefalse', 'true'], ['truefalse', 'true']]]);
        $attemptid = $this->start($quiz);

        $page = moodle_quiz_get_page::execute(['attempt_id' => $attemptid, 'page' => 0], $this->student);
        $this->assertSame(2, $page['total']);
        $this->assertCount(2, $page['questions']);
        $this->assertFalse($page['has_more']);
        $this->assertNull($page['next_offset']);

        $this->assert_tool_error('invalid_argument', function () use ($attemptid): void {
            moodle_quiz_get_page::execute(['attempt_id' => $attemptid, 'page' => 0, 'offset' => 2], $this->student);
        });
    }

    /**
     * A long review comes in parts that fit the limit; the overall feedback and the grade stay usable.
     */
    public function test_get_review_is_paged_to_the_response_limit(): void {
        $quiz = $this->create_big_quiz(12);
        $attemptid = $this->start($quiz);
        moodle_quiz_finish::execute(['attempt_id' => $attemptid, 'confirm' => true], $this->student);
        $budget = response_budget::usable_chars(2000);

        $slots = [];
        $offset = 0;
        $parts = 0;
        do {
            $review = moodle_quiz_get_review::execute(
                ['attempt_id' => $attemptid, 'offset' => $offset],
                $this->student
            );
            $parts++;
            $this->assertSame(12, $review['total']);
            $this->assertSame($offset, $review['offset']);
            $this->assertArrayHasKey('grade', $review);
            $this->assertLessThanOrEqual($budget, response_budget::measure($review), 'Part ' . $parts);
            $this->assertSame($offset === 0, array_key_exists('overall_feedback', $review));
            foreach ($review['questions'] as $question) {
                $slots[] = $question['slot'];
            }
            $offset = (int) ($review['next_offset'] ?? 0);
        } while ($review['has_more'] && $parts < 30);

        $this->assertGreaterThan(1, $parts, 'The review does not fit one answer.');
        $this->assertSame(range(1, 12), $slots, 'Every question once, in order.');

        $this->assert_tool_error('invalid_argument', function () use ($attemptid): void {
            moodle_quiz_get_review::execute(['attempt_id' => $attemptid, 'offset' => 12], $this->student);
        });
    }
    /**
     * A quiz with a Check step (hints and feedback while answering) says that this step is browser only.
     */
    public function test_start_attempt_flags_the_check_step(): void {
        $interactive = $this->create_quiz([[['truefalse', 'true']]], ['preferredbehaviour' => 'interactive']);
        $output = moodle_quiz_start_attempt::execute(['cmid' => (int) $interactive->cmid], $this->student);
        $this->assertTrue($output['inline_supported']);
        $this->assertTrue($output['check_in_browser']);
        $this->assertStringContainsString('Check step', $output['summary']);

        $deferred = $this->create_quiz([[['truefalse', 'true']]]);
        $output = moodle_quiz_start_attempt::execute(['cmid' => (int) $deferred->cmid], $this->student);
        $this->assertFalse($output['check_in_browser']);
        $this->assertStringNotContainsString('Check step', $output['summary']);
    }

    /**
     * A question already checked in the browser is reported as finished and refuses new answers.
     */
    public function test_checked_question_is_finished_and_cannot_be_saved(): void {
        $quiz = $this->create_quiz(
            [[['truefalse', 'true'], ['truefalse', 'true']]],
            ['preferredbehaviour' => 'immediatefeedback']
        );
        $attemptid = $this->start($quiz);

        // The learner presses "Check" on the first question in the browser.
        $attemptobj = quiz_attempt::create($attemptid);
        $qa = $attemptobj->get_question_attempt(1);
        $prefix = $qa->get_field_prefix();
        $attemptobj->process_submitted_actions(time(), false, [
            'slots' => '1',
            $prefix . 'answer' => '1',
            $prefix . '-submit' => '1',
            $prefix . ':sequencecheck' => (string) $qa->get_sequence_check_count(),
        ]);

        $page = moodle_quiz_get_page::execute(['attempt_id' => $attemptid, 'page' => 0], $this->student);
        $this->assertTrue($this->question_in($page, 1)['finished']);
        $this->assertFalse($this->question_in($page, 2)['finished']);

        $this->assert_tool_error('question_finished', function () use ($attemptid): void {
            moodle_quiz_save_answers::execute(
                ['attempt_id' => $attemptid, 'answers' => [['slot' => 1, 'value' => '0']]],
                $this->student
            );
        });
        // The open question can still be answered.
        $saved = moodle_quiz_save_answers::execute(
            ['attempt_id' => $attemptid, 'answers' => [['slot' => 2, 'value' => '1']]],
            $this->student
        );
        $this->assertSame(1, $saved['saved']);
    }
    /**
     * Give an answer the way the browser does and return the saved text of the question.
     *
     * @param int $attemptid Attempt id.
     * @param int $slot Slot.
     * @param string $answer Value of the true/false answer field ('1' or '0').
     * @return void
     */
    private function browser_answer(int $attemptid, int $slot, string $answer): void {
        $attemptobj = quiz_attempt::create($attemptid);
        $qa = $attemptobj->get_question_attempt($slot);
        $prefix = $qa->get_field_prefix();
        $attemptobj->process_submitted_actions(time(), false, [
            'slots' => (string) $slot,
            $prefix . 'answer' => $answer,
            $prefix . ':sequencecheck' => (string) $qa->get_sequence_check_count(),
        ]);
    }

    /**
     * A save with the sequence of a question that changed elsewhere is refused and saves nothing.
     */
    public function test_save_answers_refuses_a_stale_sequence(): void {
        $quiz = $this->create_quiz([[['truefalse', 'true'], ['truefalse', 'true']]]);
        $attemptid = $this->start($quiz);
        $page = moodle_quiz_get_page::execute(['attempt_id' => $attemptid, 'page' => 0], $this->student);
        $first = $this->question_in($page, 1);
        $second = $this->question_in($page, 2);
        $this->assertIsInt($first['sequence']);

        // The learner answers the first question in the browser in the meantime.
        $this->browser_answer($attemptid, 1, '1');

        $this->assert_tool_error('out_of_sequence', function () use ($attemptid, $first, $second): void {
            moodle_quiz_save_answers::execute(['attempt_id' => $attemptid, 'answers' => [
                ['slot' => 2, 'value' => '0', 'sequence' => $second['sequence']],
                ['slot' => 1, 'value' => '0', 'sequence' => $first['sequence']],
            ]], $this->student);
        });
        $this->assertSame('1', $this->saved_answer($attemptid, 1), 'The answer from the browser is kept.');
        $this->assertNull($this->saved_answer($attemptid, 2), 'The refused call saved nothing.');
    }

    /**
     * Current sequences are accepted, the answer reports the new sequences, and a save without sequence still works.
     */
    public function test_save_answers_accepts_current_sequences_and_reports_new_ones(): void {
        $quiz = $this->create_quiz([[['truefalse', 'true']]]);
        $attemptid = $this->start($quiz);
        $page = moodle_quiz_get_page::execute(['attempt_id' => $attemptid, 'page' => 0], $this->student);
        $sequence = $this->question_in($page, 1)['sequence'];

        $saved = moodle_quiz_save_answers::execute(['attempt_id' => $attemptid, 'answers' => [
            ['slot' => 1, 'value' => '1', 'sequence' => $sequence],
        ]], $this->student);
        $this->assertSame(1, $saved['saved']);
        $next = $saved['sequences']->{'1'};
        $this->assertGreaterThan($sequence, $next, 'A saved answer is a new step of the question.');

        // The new sequence from the answer works for the next save without loading the page again.
        $again = moodle_quiz_save_answers::execute(['attempt_id' => $attemptid, 'answers' => [
            ['slot' => 1, 'value' => '0', 'sequence' => $next],
        ]], $this->student);
        $this->assertSame('0', $this->saved_answer($attemptid, 1));
        $this->assertGreaterThan($next, $again['sequences']->{'1'});

        // The old sequence is stale now.
        $this->assert_tool_error('out_of_sequence', function () use ($attemptid, $sequence): void {
            moodle_quiz_save_answers::execute(['attempt_id' => $attemptid, 'answers' => [
                ['slot' => 1, 'value' => '1', 'sequence' => $sequence],
            ]], $this->student);
        });

        // Without sequence the answer overwrites what is saved (the behaviour before sequences).
        moodle_quiz_save_answers::execute(['attempt_id' => $attemptid, 'answers' => [
            ['slot' => 1, 'value' => '1'],
        ]], $this->student);
        $this->assertSame('1', $this->saved_answer($attemptid, 1));

        $this->assert_tool_error('invalid_answer', function () use ($attemptid): void {
            moodle_quiz_save_answers::execute(['attempt_id' => $attemptid, 'answers' => [
                ['slot' => 1, 'value' => '1', 'sequence' => 'abc'],
            ]], $this->student);
        });
    }
    /**
     * Move the start of an attempt into the past, to let its time run out.
     *
     * @param int $attemptid Attempt id.
     * @param int $seconds How long ago the attempt started.
     * @return void
     */
    private function backdate_attempt(int $attemptid, int $seconds): void {
        global $DB;
        $DB->set_field('quiz_attempts', 'timestart', time() - $seconds, ['id' => $attemptid]);
    }

    /**
     * Someone outside the course, or inside a hidden course or behind an access restriction, sees no quiz.
     */
    public function test_tools_refuse_users_without_access_to_the_quiz(): void {
        global $DB;
        $quiz = $this->create_quiz([[['truefalse', 'true']]]);
        $attemptid = $this->start($quiz);
        $args = ['cmid' => (int) $quiz->cmid, 'confirm' => true];

        // Not enrolled.
        $outsider = $this->getDataGenerator()->create_user();
        $this->setUser($outsider);
        $this->assert_tool_error('quiz_not_found', function () use ($args, $outsider): void {
            moodle_quiz_start_attempt::execute($args, $outsider);
        });

        // Hidden course.
        $this->setUser($this->student);
        course_change_visibility((int) $this->course->id, false);
        $this->assert_tool_error('quiz_not_found', function () use ($args): void {
            moodle_quiz_start_attempt::execute($args, $this->student);
        });
        $this->assert_tool_error('attempt_not_found', function () use ($attemptid): void {
            moodle_quiz_get_page::execute(['attempt_id' => $attemptid, 'page' => 0], $this->student);
        });
        course_change_visibility((int) $this->course->id, true);

        // Access restriction on the activity (not available yet).
        $restriction = ['op' => '&', 'showc' => [true], 'c' => [['type' => 'date', 'd' => '>=', 't' => time() + DAYSECS]]];
        $DB->set_field('course_modules', 'availability', json_encode($restriction), ['id' => $quiz->cmid]);
        rebuild_course_cache((int) $this->course->id, true);
        $this->assert_tool_error('quiz_not_found', function () use ($args): void {
            moodle_quiz_start_attempt::execute($args, $this->student);
        });
        $this->assert_tool_error('attempt_not_found', function () use ($attemptid): void {
            moodle_quiz_get_page::execute(['attempt_id' => $attemptid, 'page' => 0], $this->student);
        });
    }

    /**
     * With "submit automatically" an attempt whose time ran out is closed by the next call.
     */
    public function test_time_limit_over_closes_the_attempt(): void {
        $quiz = $this->create_quiz([[['truefalse', 'true']]], ['timelimit' => 600, 'overduehandling' => 'autosubmit']);
        $attemptid = $this->start($quiz);
        $this->backdate_attempt($attemptid, 1200);

        $calls = [
            fn() => moodle_quiz_get_page::execute(['attempt_id' => $attemptid, 'page' => 0], $this->student),
            fn() => moodle_quiz_save_answers::execute(
                ['attempt_id' => $attemptid, 'answers' => [['slot' => 1, 'value' => '1']]],
                $this->student
            ),
            fn() => moodle_quiz_finish::execute(['attempt_id' => $attemptid, 'confirm' => true], $this->student),
        ];
        foreach ($calls as $call) {
            $this->assert_tool_error('attempt_finished', $call);
        }
        $review = moodle_quiz_get_review::execute(['attempt_id' => $attemptid], $this->student);
        $this->assertSame('finished', $review['state']);
    }

    /**
     * With a grace period an overdue attempt can only be submitted; after the grace period it is closed.
     */
    public function test_grace_period_allows_only_submitting(): void {
        $settings = ['timelimit' => 600, 'overduehandling' => 'graceperiod', 'graceperiod' => 3600];
        $quiz = $this->create_quiz([[['truefalse', 'true']]], $settings);
        $attemptid = $this->start($quiz);
        $this->backdate_attempt($attemptid, 700);

        $this->assert_tool_error('attempt_overdue', function () use ($attemptid): void {
            moodle_quiz_get_page::execute(['attempt_id' => $attemptid, 'page' => 0], $this->student);
        });
        $this->assert_tool_error('attempt_overdue', function () use ($attemptid): void {
            moodle_quiz_save_answers::execute(
                ['attempt_id' => $attemptid, 'answers' => [['slot' => 1, 'value' => '1']]],
                $this->student
            );
        });
        $preview = moodle_quiz_finish::execute(['attempt_id' => $attemptid], $this->student);
        $this->assertTrue($preview['requires_confirmation']);
        $this->assertSame('overdue', $preview['state']);
        $done = moodle_quiz_finish::execute(['attempt_id' => $attemptid, 'confirm' => true], $this->student);
        $this->assertTrue($done['finished']);

        // A second attempt first goes overdue; if it is still open after the grace period it is closed
        // without grading (Moodle abandons an attempt only from the overdue state).
        $second = $this->start($quiz);
        $this->backdate_attempt($second, 700);
        $this->assert_tool_error('attempt_overdue', function () use ($second): void {
            moodle_quiz_get_page::execute(['attempt_id' => $second, 'page' => 0], $this->student);
        });
        $this->backdate_attempt($second, 600 + 3600 + 100);
        $this->assert_tool_error('attempt_finished', function () use ($second): void {
            moodle_quiz_finish::execute(['attempt_id' => $second, 'confirm' => true], $this->student);
        });
    }

    /**
     * A user override of the time limit and of the number of attempts applies.
     */
    public function test_user_override_applies_to_time_limit_and_attempts(): void {
        global $DB;
        $quiz = $this->create_quiz([[['truefalse', 'true']]], ['timelimit' => 600, 'attempts' => 1]);
        $DB->insert_record('quiz_overrides', (object) [
            'quiz' => $quiz->id, 'userid' => $this->student->id, 'groupid' => null,
            'timeopen' => null, 'timeclose' => null, 'timelimit' => 60, 'attempts' => 2, 'password' => null,
        ]);

        $preview = moodle_quiz_start_attempt::execute(['cmid' => (int) $quiz->cmid], $this->student);
        $this->assertSame(60, $preview['time_limit_seconds']);
        $this->assertSame(2, $preview['attempts_allowed']);

        $first = $this->start($quiz);
        moodle_quiz_finish::execute(['attempt_id' => $first, 'confirm' => true], $this->student);
        $second = $this->start($quiz);
        $this->assertNotSame($first, $second, 'The override allows a second attempt.');
    }

    /**
     * A teacher who may preview the quiz can start an attempt without mod/quiz:attempt.
     */
    public function test_preview_user_can_start_an_attempt(): void {
        $quiz = $this->create_quiz([[['truefalse', 'true']]]);
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->setUser($teacher);

        $output = moodle_quiz_start_attempt::execute(['cmid' => (int) $quiz->cmid], $teacher);

        $this->assertNotNull($output['attempt_id']);
        $this->assertTrue($output['inline_supported']);
    }

    /**
     * True/false takes real booleans, an essay keeps its text, and a match question keeps the stems
     * that were not sent again.
     */
    public function test_answer_round_trips_for_truefalse_essay_and_match(): void {
        $quiz = $this->create_quiz([[['truefalse', 'true'], ['essay', 'plain'], ['match', 'foursubq']]]);
        $attemptid = $this->start($quiz);
        $page = moodle_quiz_get_page::execute(['attempt_id' => $attemptid, 'page' => 0], $this->student);
        $match = $this->question_in($page, 3)['input'];
        $this->assertSame('text', $this->question_in($page, 2)['input']['kind']);

        moodle_quiz_save_answers::execute(['attempt_id' => $attemptid, 'answers' => [
            ['slot' => 1, 'value' => true],
            ['slot' => 2, 'value' => 'Mein Aufsatz'],
            ['slot' => 3, 'values' => [$match['stems'][0]['key'] => $match['options'][0]['value']]],
        ]], $this->student);
        $this->assertSame('1', $this->saved_answer($attemptid, 1));
        moodle_quiz_save_answers::execute(['attempt_id' => $attemptid, 'answers' => [
            ['slot' => 1, 'value' => false],
            ['slot' => 3, 'values' => [$match['stems'][1]['key'] => $match['options'][1]['value']]],
        ]], $this->student);
        $this->assertSame('0', $this->saved_answer($attemptid, 1));

        $page = moodle_quiz_get_page::execute(['attempt_id' => $attemptid, 'page' => 0], $this->student);
        $this->assertSame('Mein Aufsatz', $this->question_in($page, 2)['input']['value']);
        $values = (array) $this->question_in($page, 3)['input']['values'];
        $this->assertArrayHasKey($match['stems'][0]['key'], $values, 'The first stem stays after the second save.');
        $this->assertArrayHasKey($match['stems'][1]['key'], $values);
    }

    /**
     * The review follows the options of the phase: right after the attempt, later while the quiz is open,
     * and after it closed.
     */
    public function test_review_follows_the_options_of_each_phase(): void {
        global $DB;
        $quiz = $this->create_quiz([[['truefalse', 'true']]], [
            'correctnessimmediately' => 1,
            'correctnessopen' => 0,
            'correctnessclosed' => 1,
        ]);
        $attemptid = $this->start($quiz);
        moodle_quiz_save_answers::execute(
            ['attempt_id' => $attemptid, 'answers' => [['slot' => 1, 'value' => '1']]],
            $this->student
        );
        moodle_quiz_finish::execute(['attempt_id' => $attemptid, 'confirm' => true], $this->student);

        $immediately = moodle_quiz_get_review::execute(['attempt_id' => $attemptid], $this->student);
        $this->assertNotNull($immediately['questions'][0]['state'], 'Right after the attempt.');

        // Later, while the quiz is still open: correctness is hidden.
        $DB->set_field('quiz_attempts', 'timefinish', time() - HOURSECS, ['id' => $attemptid]);
        $later = moodle_quiz_get_review::execute(['attempt_id' => $attemptid], $this->student);
        $this->assertNull($later['questions'][0]['state'], 'Later while open.');

        // After the quiz closed: correctness is shown again.
        $DB->set_field('quiz', 'timeclose', time() - MINSECS, ['id' => $quiz->id]);
        $closed = moodle_quiz_get_review::execute(['attempt_id' => $attemptid], $this->student);
        $this->assertNotNull($closed['questions'][0]['state'], 'After the quiz closed.');
    }

    /**
     * The page has to be a whole, existing page number.
     */
    public function test_get_page_validates_the_page_number(): void {
        $quiz = $this->create_quiz([[['truefalse', 'true']]]);
        $attemptid = $this->start($quiz);

        $this->assert_tool_error('invalid_page', function () use ($attemptid): void {
            moodle_quiz_get_page::execute(['attempt_id' => $attemptid, 'page' => -1], $this->student);
        });
        $this->assert_tool_error('invalid_argument', function () use ($attemptid): void {
            moodle_quiz_get_page::execute(['attempt_id' => $attemptid, 'page' => 'abc'], $this->student);
        });
        $page = moodle_quiz_get_page::execute(['attempt_id' => $attemptid, 'page' => '0'], $this->student);
        $this->assertSame(0, $page['page'], 'A numeric string is accepted.');
    }

    /**
     * Answers that a teacher still grades make the grade provisional, and a teacher's comment comes with the review.
     */
    public function test_manual_grading_is_provisional_and_the_teacher_comment_is_shown(): void {
        global $DB;
        $quiz = $this->create_quiz([[['truefalse', 'true'], ['essay', 'plain']]]);
        $attemptid = $this->start($quiz);
        moodle_quiz_save_answers::execute(['attempt_id' => $attemptid, 'answers' => [
            ['slot' => 1, 'value' => '1'],
            ['slot' => 2, 'value' => 'Mein Aufsatz'],
        ]], $this->student);

        $finish = moodle_quiz_finish::execute(['attempt_id' => $attemptid, 'confirm' => true], $this->student);
        $this->assertTrue($finish['requires_manual_grading']);
        $this->assertStringContainsString('teacher', $finish['summary']);
        $review = moodle_quiz_get_review::execute(['attempt_id' => $attemptid], $this->student);
        $this->assertTrue($review['requires_manual_grading']);
        $this->assertArrayNotHasKey('teacher_comment', $review['questions'][1]);

        // The teacher grades the essay with a comment.
        $uniqueid = (int) $DB->get_field('quiz_attempts', 'uniqueid', ['id' => $attemptid], MUST_EXIST);
        $quba = \question_engine::load_questions_usage_by_activity($uniqueid);
        $quba->get_question_attempt(2)->manual_grade('Argumentation gut, Quellen fehlen', 1.0, FORMAT_HTML);
        \question_engine::save_questions_usage_by_activity($quba);

        $review = moodle_quiz_get_review::execute(['attempt_id' => $attemptid], $this->student);
        $this->assertSame('Argumentation gut, Quellen fehlen', $review['questions'][1]['teacher_comment']);
        $this->assertArrayNotHasKey('teacher_comment', $review['questions'][0]);

        // A quiz without manual grading is not provisional.
        $plain = $this->create_quiz([[['truefalse', 'true']]]);
        $other = $this->start($plain);
        $done = moodle_quiz_finish::execute(['attempt_id' => $other, 'confirm' => true], $this->student);
        $this->assertFalse($done['requires_manual_grading']);
    }

    /**
     * Word limits and "response required" are shown with the question and reported when a saved answer breaks them.
     */
    public function test_essay_limits_are_reported(): void {
        $limits = ['maxwordenabled' => 1, 'maxwordlimit' => 3, 'responserequired' => 1];
        $quiz = $this->create_quiz([[['essay', 'plain', $limits]]]);
        $attemptid = $this->start($quiz);
        $page = moodle_quiz_get_page::execute(['attempt_id' => $attemptid, 'page' => 0], $this->student);
        $input = $this->question_in($page, 1)['input'];
        $this->assertSame(3, $input['max_words']);
        $this->assertTrue($input['response_required']);

        $saved = moodle_quiz_save_answers::execute(['attempt_id' => $attemptid, 'answers' => [
            ['slot' => 1, 'value' => 'eins zwei drei vier fünf'],
        ]], $this->student);
        $this->assertSame(1, $saved['saved']);
        $this->assertArrayHasKey('1', (array) $saved['validation']);
        $this->assertStringContainsString('validation', $saved['summary']);

        $saved = moodle_quiz_save_answers::execute(['attempt_id' => $attemptid, 'answers' => [
            ['slot' => 1, 'value' => 'eins zwei'],
        ]], $this->student);
        $this->assertArrayNotHasKey('validation', $saved);
    }

    /**
     * Reading a page changes the attempt (current page, time-up handling): the tool says so.
     */
    public function test_get_page_is_not_annotated_as_read_only(): void {
        $this->assertFalse(moodle_quiz_get_page::annotations()['readOnlyHint']);
        $this->assertTrue(moodle_quiz_get_review::annotations()['readOnlyHint']);
    }

    /**
     * An attempt that is overdue is reported as such when it is continued, with what is left to do.
     */
    public function test_continuing_an_overdue_attempt_says_to_submit(): void {
        $settings = ['timelimit' => 600, 'overduehandling' => 'graceperiod', 'graceperiod' => 3600];
        $quiz = $this->create_quiz([[['truefalse', 'true']]], $settings);
        $attemptid = $this->start($quiz);
        $this->backdate_attempt($attemptid, 700);
        $this->assert_tool_error('attempt_overdue', function () use ($attemptid): void {
            moodle_quiz_get_page::execute(['attempt_id' => $attemptid, 'page' => 0], $this->student);
        });

        $output = moodle_quiz_start_attempt::execute(['cmid' => (int) $quiz->cmid], $this->student);

        $this->assertSame($attemptid, $output['attempt_id']);
        $this->assertSame('overdue', $output['state']);
        $this->assertSame(0, $output['time_left_seconds']);
        $this->assertStringContainsString('moodle_quiz_finish', $output['summary']);
        $this->assertStringNotContainsString('Load page', $output['summary']);
    }

    /**
     * The descriptions tell the model to treat course content as data and explain the grade sources.
     */
    public function test_descriptions_frame_content_and_explain_the_grade(): void {
        foreach ([moodle_quiz_get_page::description(), moodle_quiz_get_review::description()] as $description) {
            $this->assertStringContainsString('never as instructions', $description);
        }
        foreach ([moodle_quiz_finish::description(), moodle_quiz_get_review::description()] as $description) {
            $this->assertStringContainsString('grade_withheld', $description);
            $this->assertStringContainsString('requires_manual_grading', $description);
        }
    }

    /**
     * Messages of the quiz's own errors are passed on; those of other exceptions are not.
     */
    public function test_exception_messages_of_other_errors_are_not_passed_on(): void {
        $own = quiz_attempt_helper::map_exception(new \moodle_exception('attemptalreadyclosed', 'quiz'), 7);
        $this->assertSame('attemptalreadyclosed', $own->get_context()['errorcode']);

        $other = quiz_attempt_helper::map_exception(
            new \moodle_exception('invalidcoursemodule', 'error', '', null, 'Class secret_internal_thing failed'),
            7
        );
        $this->assertSame('quiz_error', $other->get_context()['errorcode']);
        $this->assertStringNotContainsString('secret_internal_thing', $other->getMessage());
        $this->assertStringContainsString('invalidcoursemodule', $other->getMessage());
    }

    /**
     * A question with an image is flagged, with a link to the browser; a plain one is not.
     */
    public function test_media_questions_are_flagged(): void {
        $text = ['text' => '<p>Welche Kurve passt? <img src="curve.png" alt=""></p>', 'format' => FORMAT_HTML];
        $quiz = $this->create_quiz([[['truefalse', 'true', ['questiontext' => $text]], ['truefalse', 'true']]]);
        $attemptid = $this->start($quiz);

        $page = moodle_quiz_get_page::execute(['attempt_id' => $attemptid, 'page' => 0], $this->student);

        $media = $this->question_in($page, 1);
        $this->assertTrue($media['has_media']);
        $this->assertStringContainsString('/mod/quiz/attempt.php', $media['fallback_url']);
        $plain = $this->question_in($page, 2);
        $this->assertFalse($plain['has_media']);
        $this->assertArrayNotHasKey('fallback_url', $plain);
    }

    /**
     * A quiz that is not open yet shows nothing about its structure, even when it also needs the browser.
     */
    public function test_start_attempt_of_a_closed_quiz_reveals_no_structure(): void {
        $quiz = $this->create_quiz([[['truefalse', 'true']]], [
            'quizpassword' => 'geheim',
            'timeopen' => time() + DAYSECS,
        ]);

        $this->assert_tool_error('quiz_access_denied', function () use ($quiz): void {
            moodle_quiz_start_attempt::execute(['cmid' => (int) $quiz->cmid], $this->student);
        });

        // A quiz that is only open for another network is not available, as in the browser.
        $outside = $this->create_quiz([[['truefalse', 'true']]], ['subnet' => '10.0.0.0/8']);
        $this->assert_tool_error('quiz_access_denied', function () use ($outside): void {
            moodle_quiz_start_attempt::execute(['cmid' => (int) $outside->cmid], $this->student);
        });

        // Once open, the password sends the learner to the browser as before.
        global $DB;
        $DB->set_field('quiz', 'timeopen', time() - HOURSECS, ['id' => $quiz->id]);
        $output = moodle_quiz_start_attempt::execute(['cmid' => (int) $quiz->cmid], $this->student);
        $this->assertFalse($output['inline_supported']);
        $this->assertNull($output['attempt_id']);
    }
}

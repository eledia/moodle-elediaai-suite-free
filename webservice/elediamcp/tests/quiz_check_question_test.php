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
use mod_quiz\quiz_settings;
use stdClass;
use webservice_elediamcp\local\ai\registry;
use webservice_elediamcp\local\ai\tool_exception;
use webservice_elediamcp\local\ai\tools\moodle_quiz_check_question;
use webservice_elediamcp\local\ai\tools\moodle_quiz_finish;
use webservice_elediamcp\local\ai\tools\moodle_quiz_get_page;
use webservice_elediamcp\local\ai\tools\moodle_quiz_save_answers;
use webservice_elediamcp\local\ai\tools\moodle_quiz_start_attempt;
use webservice_elediamcp\local\tool_provider;

/**
 * Tests for moodle_quiz_check_question, the Check button of immediate feedback and interactive quizzes.
 *
 * @package     webservice_elediamcp
 * @category    test
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \webservice_elediamcp\local\ai\tools\moodle_quiz_check_question
 * @covers      \webservice_elediamcp\local\ai\tools\quiz_attempt_helper
 */
final class quiz_check_question_test extends advanced_testcase {
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
     * Create a one-page quiz.
     *
     * @param array $questions [qtype, which] entries for the question generator.
     * @param array $settings Quiz settings overriding the generator defaults.
     * @return stdClass Quiz record with cmid and the question ids by slot.
     */
    private function create_quiz(array $questions, array $settings = []): stdClass {
        global $DB;

        $quiz = $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_instance(
            ['course' => $this->course->id] + $settings + ['preferredbehaviour' => 'immediatefeedback']
        );
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category([
            'contextid' => context_module::instance($quiz->cmid)->id,
        ]);
        $ids = [];
        foreach ($questions as $index => [$qtype, $which]) {
            $question = $questiongenerator->create_question($qtype, $which, ['category' => $category->id]);
            quiz_add_quiz_question($question->id, $quiz, 1);
            $ids[$index + 1] = (int) $question->id;
        }
        quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();

        $record = $DB->get_record('quiz', ['id' => $quiz->id], '*', MUST_EXIST);
        $record->cmid = $quiz->cmid;
        $record->questionids = $ids;
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
     * The input description of a slot on page 0.
     *
     * @param int $attemptid Attempt.
     * @param int $slot Slot.
     * @return array
     */
    private function input_of(int $attemptid, int $slot): array {
        $page = moodle_quiz_get_page::execute(['attempt_id' => $attemptid, 'page' => 0], $this->student);
        foreach ($page['questions'] as $question) {
            if ($question['slot'] === $slot) {
                return $question['input'];
            }
        }
        $this->fail("Slot {$slot} not on the page.");
    }

    /**
     * The option values whose labels are given.
     *
     * @param array $input Input description.
     * @param string[] $labels Labels.
     * @return string[]
     */
    private function values_for(array $input, array $labels): array {
        $values = [];
        foreach ($input['options'] as $option) {
            if (in_array($option['label'], $labels, true)) {
                $values[] = $option['value'];
            }
        }
        $this->assertCount(count($labels), $values);
        return $values;
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
     * The tool is registered, free and a write tool that is not destructive.
     */
    public function test_tool_is_registered(): void {
        $this->assertContains('moodle_quiz_check_question', registry::names());
        $this->assertContains('moodle_quiz_check_question', tool_provider::free_ai_tool_names());
        $annotations = moodle_quiz_check_question::annotations();
        $this->assertFalse($annotations['readOnlyHint']);
        $this->assertFalse($annotations['destructiveHint']);
    }

    /**
     * A right answer is saved, graded as correct and the question is final afterwards.
     */
    public function test_right_answer_is_correct_with_feedback(): void {
        $quiz = $this->create_quiz([['truefalse', 'true']]);
        $attemptid = $this->start($quiz);

        $result = moodle_quiz_check_question::execute(['attempt_id' => $attemptid, 'slot' => 1, 'value' => '1'], $this->student);

        $this->assertSame(1, $result['slot']);
        $this->assertSame('correct', $result['state']);
        $this->assertTrue($result['finished']);
        $this->assertStringContainsString('This is the right answer.', $result['feedback']);
        $this->assertStringContainsString('You should have selected true.', $result['feedback']);
        $this->assertSame('True', $result['right_answer']);
        $this->assertStringNotContainsString(
            'The correct answer is',
            $result['feedback'],
            'The right answer is a field of its own.'
        );

        $this->assertSame('1', $this->input_of($attemptid, 1)['value'], 'The answer is saved.');
        $this->assert_tool_error('question_finished', function () use ($attemptid): void {
            moodle_quiz_check_question::execute(['attempt_id' => $attemptid, 'slot' => 1, 'value' => '0'], $this->student);
        });
        $this->assert_tool_error('question_finished', function () use ($attemptid): void {
            moodle_quiz_save_answers::execute(
                ['attempt_id' => $attemptid, 'answers' => [['slot' => 1, 'value' => '0']]],
                $this->student
            );
        });
    }

    /**
     * The result names the answer that was checked, as the user sees it (#195).
     */
    public function test_result_names_the_checked_answer(): void {
        $quiz = $this->create_quiz([['truefalse', 'true'], ['multichoice', 'one_of_four'], ['multichoice', 'two_of_four']]);
        $attemptid = $this->start($quiz);

        // The model sent "0" for "True" once: the person must be able to see what was checked.
        $args = ['attempt_id' => $attemptid, 'slot' => 1];
        $result = moodle_quiz_check_question::execute($args + ['value' => '0'], $this->student);
        $this->assertSame('False', $result['answer_checked']);
        $this->assertStringContainsString('(answer: False)', $result['summary']);
        $this->assertSame('incorrect', $result['state']);

        $single = $this->input_of($attemptid, 2);
        $result = moodle_quiz_check_question::execute(
            ['attempt_id' => $attemptid, 'slot' => 2, 'value' => $single['options'][1]['value']],
            $this->student
        );
        $this->assertSame($single['options'][1]['label'], $result['answer_checked']);

        $multi = $this->input_of($attemptid, 3);
        $labels = [$multi['options'][0]['label'], $multi['options'][2]['label']];
        $result = moodle_quiz_check_question::execute(
            ['attempt_id' => $attemptid, 'slot' => 3, 'values' => $this->values_for($multi, $labels)],
            $this->student
        );
        $this->assertSame($labels, $result['answer_checked']);
        $this->assertStringContainsString(implode('; ', $labels), $result['summary']);
    }

    /**
     * A value may be the label of the option, without case and extra white space (#195).
     */
    public function test_value_may_be_the_label_of_the_option(): void {
        $quiz = $this->create_quiz([['truefalse', 'true'], ['multichoice', 'one_of_four'], ['multichoice', 'two_of_four']]);
        $attemptid = $this->start($quiz);

        $args = ['attempt_id' => $attemptid, 'slot' => 1];
        $result = moodle_quiz_check_question::execute($args + ['value' => ' true '], $this->student);
        $this->assertSame('True', $result['answer_checked']);
        $this->assertSame('correct', $result['state']);
        $this->assertSame('1', $this->input_of($attemptid, 1)['value']);

        $single = $this->input_of($attemptid, 2);
        $label = strtoupper($single['options'][2]['label']);
        $args = ['attempt_id' => $attemptid, 'slot' => 2];
        $result = moodle_quiz_check_question::execute($args + ['value' => $label], $this->student);
        $this->assertSame($single['options'][2]['label'], $result['answer_checked']);

        $multi = $this->input_of($attemptid, 3);
        $labels = [$multi['options'][1]['label'], $multi['options'][3]['label']];
        $args = ['attempt_id' => $attemptid, 'slot' => 3];
        $result = moodle_quiz_check_question::execute($args + ['values' => $labels], $this->student);
        $this->assertSame($labels, $result['answer_checked']);
    }

    /**
     * An answer that fits no option can be corrected: it carries the valid options and retryable true,
     * and nothing is saved or checked (#195).
     */
    public function test_unmatched_answer_names_the_options_and_is_retryable(): void {
        $quiz = $this->create_quiz([['truefalse', 'true'], ['multichoice', 'one_of_four']]);
        $attemptid = $this->start($quiz);

        foreach ([[1, 'maybe'], [2, 'no such option']] as [$slot, $value]) {
            try {
                $args = ['attempt_id' => $attemptid, 'slot' => $slot, 'value' => $value];
                moodle_quiz_check_question::execute($args, $this->student);
                $this->fail('An answer that fits no option must be refused.');
            } catch (tool_exception $ex) {
                $context = $ex->get_context();
                $this->assertSame('invalid_answer', $context['errorcode']);
                $this->assertTrue($context['retryable']);
                $this->assertSame($this->input_of($attemptid, $slot)['options'], $context['options']);
            }
        }
        $this->assertArrayNotHasKey('value', $this->input_of($attemptid, 1), 'Nothing was saved.');
    }

    /**
     * Two options with the same label are ambiguous: the label alone is refused (#195).
     */
    public function test_ambiguous_label_is_refused(): void {
        $this->assertNull($this->match_label('Same', [['value' => '1', 'label' => 'Same'], ['value' => '2', 'label' => 'same']]));
    }

    /**
     * An input that is the value of one option and the label of another is ambiguous, and the match
     * ignores surrounding white space (#195).
     */
    public function test_label_that_is_the_value_of_another_option_is_ambiguous(): void {
        $options = [['value' => '4', 'label' => '3'], ['value' => '5', 'label' => '4']];
        $this->assertNull($this->match_label('4', $options), 'Value 4 or label "4"? Nothing is guessed.');
        $this->assertSame('5', $this->match_label(' 5 ', $options));
        $this->assertSame('4', $this->match_label('3', $options));
    }

    /**
     * Run the label matching of the helper.
     *
     * @param string $input Input.
     * @param array $options Options.
     * @return string|null
     */
    private function match_label(string $input, array $options): ?string {
        $method = new \ReflectionMethod(\webservice_elediamcp\local\ai\tools\quiz_attempt_helper::class, 'value_for_label');
        $method->setAccessible(true);
        return $method->invoke(null, $input, $options, []);
    }

    /**
     * A wrong answer is graded as incorrect.
     */
    public function test_wrong_answer_is_incorrect(): void {
        $quiz = $this->create_quiz([['truefalse', 'true']]);
        $attemptid = $this->start($quiz);

        $result = moodle_quiz_check_question::execute(['attempt_id' => $attemptid, 'slot' => 1, 'value' => false], $this->student);

        $this->assertSame('incorrect', $result['state']);
        $this->assertStringContainsString('This is the wrong answer.', $result['feedback']);
        $this->assertSame('True', $result['right_answer']);
    }

    /**
     * Half of the right choices of a multiple-answer question is partially correct.
     */
    public function test_half_right_answer_is_partial(): void {
        $quiz = $this->create_quiz([['multichoice', 'two_of_four']]);
        $attemptid = $this->start($quiz);
        $values = $this->values_for($this->input_of($attemptid, 1), ['One']);

        $result = moodle_quiz_check_question::execute(
            ['attempt_id' => $attemptid, 'slot' => 1, 'values' => $values],
            $this->student
        );

        $this->assertSame('partial', $result['state']);
        $this->assertTrue($result['finished']);
        $this->assertStringContainsString('One', $result['right_answer']);
        $this->assertStringContainsString('Three', $result['right_answer']);
    }

    /**
     * An incomplete answer is refused and nothing is saved, as the browser shows a validation message.
     */
    public function test_incomplete_answer_is_refused(): void {
        $quiz = $this->create_quiz([['multichoice', 'two_of_four']]);
        $attemptid = $this->start($quiz);

        $this->assert_tool_error('invalid_answer', function () use ($attemptid): void {
            moodle_quiz_check_question::execute(['attempt_id' => $attemptid, 'slot' => 1, 'values' => []], $this->student);
        });

        $this->assertSame([], $this->input_of($attemptid, 1)['values']);
    }

    /**
     * Without the review options "during the attempt" the check reveals neither correctness nor feedback.
     */
    public function test_hidden_review_options_reveal_nothing(): void {
        $quiz = $this->create_quiz([['truefalse', 'true']], [
            'correctnessduring' => 0,
            'marksduring' => 0,
            'specificfeedbackduring' => 0,
            'generalfeedbackduring' => 0,
            'rightanswerduring' => 0,
        ]);
        $attemptid = $this->start($quiz);

        $result = moodle_quiz_check_question::execute(['attempt_id' => $attemptid, 'slot' => 1, 'value' => '1'], $this->student);

        $this->assertNull($result['state']);
        $this->assertNull($result['feedback']);
        $this->assertArrayNotHasKey('right_answer', $result);
        $this->assertTrue($result['finished']);
    }

    /**
     * Interactive with hints: a wrong check leaves another try, the next check starts it.
     */
    public function test_interactive_allows_another_try(): void {
        global $DB;
        $quiz = $this->create_quiz([['truefalse', 'true']], ['preferredbehaviour' => 'interactive']);
        $DB->insert_record('question_hints', (object) [
            'questionid' => $quiz->questionids[1],
            'hint' => 'Think again.',
            'hintformat' => FORMAT_HTML,
        ]);
        $attemptid = $this->start($quiz);

        $first = moodle_quiz_check_question::execute(['attempt_id' => $attemptid, 'slot' => 1, 'value' => '0'], $this->student);
        $this->assertFalse($first['finished']);
        $this->assertSame(1, $first['tries_left']);
        $this->assertNull($first['state'], 'Moodle shows no correctness while another try is open.');
        $this->assertArrayNotHasKey('right_answer', $first);
        $this->assertStringContainsString('Think again.', $first['feedback']);

        $second = moodle_quiz_check_question::execute(['attempt_id' => $attemptid, 'slot' => 1, 'value' => '1'], $this->student);
        $this->assertTrue($second['finished']);
        $this->assertSame('correct', $second['state']);
        $this->assertArrayNotHasKey('tries_left', $second);
    }

    /**
     * A quiz without a Check step refuses the check; saving still works.
     */
    public function test_deferred_feedback_is_not_checkable(): void {
        $quiz = $this->create_quiz([['truefalse', 'true']], ['preferredbehaviour' => 'deferredfeedback']);
        $attemptid = $this->start($quiz);

        $this->assert_tool_error('not_checkable', function () use ($attemptid): void {
            moodle_quiz_check_question::execute(['attempt_id' => $attemptid, 'slot' => 1, 'value' => '1'], $this->student);
        });
        $this->assertArrayNotHasKey('value', $this->input_of($attemptid, 1), 'The refused check saved nothing.');
    }

    /**
     * An essay is graded by hand even in an immediate feedback quiz, so it has no Check step.
     */
    public function test_essay_is_not_checkable(): void {
        $quiz = $this->create_quiz([['essay', 'plain']]);
        $attemptid = $this->start($quiz);

        $this->assert_tool_error('not_checkable', function () use ($attemptid): void {
            moodle_quiz_check_question::execute(['attempt_id' => $attemptid, 'slot' => 1, 'value' => 'Text'], $this->student);
        });
    }

    /**
     * Someone else's attempt looks like a missing one and stays untouched.
     */
    public function test_foreign_attempt_is_not_found(): void {
        $quiz = $this->create_quiz([['truefalse', 'true']]);
        $attemptid = $this->start($quiz);
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($other);

        $this->assert_tool_error('attempt_not_found', function () use ($attemptid, $other): void {
            moodle_quiz_check_question::execute(['attempt_id' => $attemptid, 'slot' => 1, 'value' => '0'], $other);
        });

        $this->setUser($this->student);
        $this->assertArrayNotHasKey('value', $this->input_of($attemptid, 1));
    }

    /**
     * A finished attempt can no longer be checked.
     */
    public function test_finished_attempt_is_refused(): void {
        $quiz = $this->create_quiz([['truefalse', 'true']]);
        $attemptid = $this->start($quiz);
        moodle_quiz_finish::execute(['attempt_id' => $attemptid, 'confirm' => true], $this->student);

        $this->assert_tool_error('attempt_finished', function () use ($attemptid): void {
            moodle_quiz_check_question::execute(['attempt_id' => $attemptid, 'slot' => 1, 'value' => '1'], $this->student);
        });
    }

    /**
     * An answer to a question that changed elsewhere is refused with out_of_sequence.
     */
    public function test_stale_sequence_is_refused(): void {
        $quiz = $this->create_quiz([['truefalse', 'true']]);
        $attemptid = $this->start($quiz);
        $saved = moodle_quiz_save_answers::execute(
            ['attempt_id' => $attemptid, 'answers' => [['slot' => 1, 'value' => '0']]],
            $this->student
        );
        $sequence = (int) $saved['sequences']->{'1'};

        $this->assert_tool_error('out_of_sequence', function () use ($attemptid, $sequence): void {
            moodle_quiz_check_question::execute(
                ['attempt_id' => $attemptid, 'slot' => 1, 'value' => '1', 'sequence' => $sequence - 1],
                $this->student
            );
        });
        $result = moodle_quiz_check_question::execute(
            ['attempt_id' => $attemptid, 'slot' => 1, 'value' => '1', 'sequence' => $sequence],
            $this->student
        );
        $this->assertSame('correct', $result['state']);
    }

    /**
     * Missing answer or slot outside the attempt are invalid.
     */
    public function test_invalid_arguments(): void {
        $quiz = $this->create_quiz([['truefalse', 'true']]);
        $attemptid = $this->start($quiz);

        $this->assert_tool_error('invalid_answer', function () use ($attemptid): void {
            moodle_quiz_check_question::execute(['attempt_id' => $attemptid, 'slot' => 1], $this->student);
        });
        $this->assert_tool_error('invalid_answer', function () use ($attemptid): void {
            moodle_quiz_check_question::execute(['attempt_id' => $attemptid, 'slot' => 9, 'value' => '1'], $this->student);
        });
        $this->assert_tool_error('invalid_argument', function (): void {
            moodle_quiz_check_question::execute(['attempt_id' => 0, 'slot' => 1, 'value' => '1'], $this->student);
        });
    }
}

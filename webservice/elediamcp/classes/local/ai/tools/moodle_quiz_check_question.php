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

declare(strict_types=1);

namespace webservice_elediamcp\local\ai\tools;

use moodle_exception;
use question_display_options;
use stdClass;
use webservice_elediamcp\local\ai\ai_tool;
use webservice_elediamcp\local\ai\tool_exception;

/**
 * Learner tool: check one answer during the attempt, like the "Check" button of the browser.
 *
 * Only for questions whose behaviour has that button and makes the question final (immediate
 * feedback, interactive with multiple tries). Moodle grades the answer; feedback and the right
 * answer are returned only as far as the quiz review options "during the attempt" show them.
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moodle_quiz_check_question implements ai_tool {
    /**
     * Return the MCP tool name.
     *
     * @return string
     */
    public static function name(): string {
        return 'moodle_quiz_check_question';
    }

    /**
     * Return the human-readable tool title.
     *
     * @return string
     */
    public static function title(): string {
        return 'Check one answer in my quiz attempt';
    }

    /**
     * Return the tool description shown to MCP clients.
     *
     * @return string
     */
    public static function description(): string {
        return 'Saves the authenticated user\'s answer to one question of their open quiz attempt and checks it, '
            . 'like the Check button in Moodle. Only for attempts where moodle_quiz_start_attempt returned '
            . 'checkable true (immediate feedback or interactive with multiple tries); otherwise the call is '
            . 'refused with not_checkable. Send {slot, value} or {slot, values} as for moodle_quiz_save_answers; '
            . 'value may be the label of the option the user chose. '
            . 'Moodle grades the answer: state is correct, partial, incorrect or needsgrading, and null when the '
            . 'quiz does not show correctness during the attempt; feedback and right_answer follow the same '
            . 'options. answer_checked is the answer Moodle took, as the user sees it: tell the user which answer '
            . 'was checked. After the check the answer can no longer be changed, unless finished is false: then '
            . 'the question allows another try (tries_left); show the feedback, and the next check of this slot '
            . 'starts the next try. Use this '
            . 'every time the user states an answer to a question of their open attempt, and never judge '
            . 'the answer yourself: Moodle grades it. Check only the user\'s own answer, never one you '
            . 'guessed or made up.';
    }

    /**
     * Return the JSON schema for accepted arguments.
     *
     * @return array<string, mixed>
     */
    public static function input_schema(): array {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['attempt_id', 'slot'],
            'properties' => [
                'attempt_id' => ['type' => 'integer', 'minimum' => 1],
                'slot' => ['type' => 'integer', 'minimum' => 1],
                'value' => [
                    'type' => ['string', 'number', 'boolean'],
                    'description' => 'single: option value; text: the answer; number: the number.',
                ],
                'values' => [
                    'type' => ['array', 'object'],
                    'description' => 'multi: list of option values; match: stem key => option value.',
                ],
                'sequence' => [
                    'type' => 'integer',
                    'minimum' => 0,
                    'description' => 'Leave it out unless you have the sequence of exactly this question from '
                        . 'moodle_quiz_get_page or the previous save. It only protects answers given elsewhere '
                        . 'from being overwritten; without it the call takes the current state.',
                ],
            ],
        ];
    }

    /**
     * Return the JSON schema for tool output.
     *
     * @return array<string, mixed>
     */
    public static function output_schema(): array {
        return [
            'type' => 'object',
            'required' => ['slot', 'answer_checked', 'state', 'feedback', 'finished', 'summary'],
            'properties' => [
                'slot' => ['type' => 'integer'],
                'answer_checked' => [
                    'type' => ['string', 'array', 'null'],
                    'items' => ['type' => 'string'],
                    'description' => 'The answer that was checked, as the user sees it: the label of the chosen '
                        . 'option (a list for several choices or a matching question) or the text. Tell the user.',
                ],
                'state' => [
                    'type' => ['string', 'null'],
                    'enum' => ['correct', 'partial', 'incorrect', 'needsgrading', null],
                    'description' => 'Null when the quiz does not show correctness during the attempt.',
                ],
                'feedback' => [
                    'type' => ['string', 'null'],
                    'description' => 'Feedback and hints as plain text; null when hidden or empty.',
                ],
                'right_answer' => [
                    'type' => 'string',
                    'description' => 'Only when the quiz shows the right answer during the attempt.',
                ],
                'finished' => [
                    'type' => 'boolean',
                    'description' => 'False when the question allows another try; the answer can be checked again.',
                ],
                'tries_left' => ['type' => 'integer', 'description' => 'Only when finished is false.'],
                'sequence' => ['type' => 'integer', 'description' => 'The new sequence of the question.'],
                'summary' => ['type' => 'string'],
            ],
        ];
    }

    /**
     * Return MCP annotations for this tool.
     *
     * @return array<string, mixed>
     */
    public static function annotations(): array {
        return [
            'title' => self::title(),
            'readOnlyHint' => false,
            'destructiveHint' => false,
            'idempotentHint' => false,
            'openWorldHint' => false,
        ];
    }

    /**
     * Execute the tool for the authenticated user.
     *
     * @param array<string, mixed> $arguments Tool arguments.
     * @param stdClass $user Authenticated Moodle user.
     * @return array<string, mixed>
     */
    public static function execute(array $arguments, stdClass $user): array {
        global $PAGE;

        $attemptid = (int) ($arguments['attempt_id'] ?? 0);
        $slot = isset($arguments['slot']) && is_numeric($arguments['slot']) ? (int) $arguments['slot'] : 0;
        if ($attemptid <= 0 || $slot <= 0) {
            throw new tool_exception('attempt_id and slot are required.', ['errorcode' => 'invalid_argument']);
        }
        if (!array_key_exists('value', $arguments) && !array_key_exists('values', $arguments)) {
            throw new tool_exception('Send the answer as value or values.', ['slot' => $slot, 'errorcode' => 'invalid_answer']);
        }

        $attemptobj = quiz_attempt_helper::open_attempt($attemptid, $user);
        if (!in_array($slot, array_map('intval', $attemptobj->get_slots()), true) || !$attemptobj->is_real_question($slot)) {
            throw new tool_exception(
                "Slot {$slot} is not a question of this attempt.",
                ['slot' => $slot, 'errorcode' => 'invalid_answer']
            );
        }
        $qa = $attemptobj->get_question_attempt($slot);
        if (!quiz_attempt_helper::is_supported($qa)) {
            throw new tool_exception(
                "The question in slot {$slot} cannot be answered in the app.",
                [
                    'slot' => $slot,
                    'fallback_url' => $attemptobj->attempt_url($slot, $attemptobj->get_question_page($slot))->out(false),
                    'errorcode' => 'unsupported_question',
                ]
            );
        }
        if (!quiz_attempt_helper::is_checkable($qa)) {
            throw new tool_exception(
                "The question in slot {$slot} has no Check step; its answer is graded when the attempt is finished. "
                    . 'Save it with moodle_quiz_save_answers.',
                ['slot' => $slot, 'behaviour' => $qa->get_behaviour_name(), 'errorcode' => 'not_checkable']
            );
        }
        if ($qa->get_state()->is_finished()) {
            throw new tool_exception(
                "The question in slot {$slot} was already checked; its answer can no longer be changed.",
                ['slot' => $slot, 'errorcode' => 'question_finished']
            );
        }
        if (isset($arguments['sequence'])) {
            if (!is_numeric($arguments['sequence']) || (int) $arguments['sequence'] < 0) {
                throw new tool_exception(
                    "sequence in slot {$slot} must be a whole number from moodle_quiz_get_page.",
                    ['slot' => $slot, 'errorcode' => 'invalid_answer']
                );
            }
            if ((int) $arguments['sequence'] !== (int) $qa->get_sequence_check_count()) {
                throw new tool_exception(
                    "The question in slot {$slot} was changed elsewhere (for example in the browser) since you "
                        . 'loaded it. Nothing was saved. Repeat the call without sequence; the page does not need '
                        . 'to be loaded again.',
                    ['slot' => $slot, 'attempt_id' => $attemptid, 'errorcode' => 'out_of_sequence']
                );
            }
        }
        if (!$attemptobj->check_page_access((int) $attemptobj->get_question_page($slot))) {
            throw new tool_exception(
                "Slot {$slot} is not on a page you can answer now.",
                ['slot' => $slot, 'errorcode' => 'invalid_answer']
            );
        }

        $response = quiz_attempt_helper::response_for($qa, $arguments);
        $question = $qa->get_question();
        if (!$question->is_complete_response($response)) {
            // Moodle would keep the answer as "invalid" and check nothing; say why instead.
            $why = quiz_attempt_helper::plain((string) $question->get_validation_error($response));
            throw new tool_exception(
                "The answer for slot {$slot} is not complete and cannot be checked yet"
                    . ($why !== '' ? ': ' . $why : '.') . ' Nothing was saved.',
                ['slot' => $slot, 'errorcode' => 'invalid_answer']
            );
        }

        try {
            if (self::is_try_again_state($qa)) {
                // Interactive with tries left: the browser shows "Try again" first, then a new Check.
                $attemptobj->process_submitted_actions(time(), false, [
                    'slots' => (string) $slot,
                    $qa->get_behaviour_field_name('tryagain') => '1',
                    $qa->get_field_prefix() . ':sequencecheck' => (string) $qa->get_sequence_check_count(),
                ]);
                $qa = $attemptobj->get_question_attempt($slot);
            }
            $prefix = $qa->get_field_prefix();
            $postdata = ['slots' => (string) $slot];
            foreach ($response as $name => $value) {
                $postdata[$prefix . $name] = $value;
            }
            $postdata[$qa->get_behaviour_field_name('submit')] = '1';
            $postdata[$prefix . ':sequencecheck'] = (string) $qa->get_sequence_check_count();
            // Same processing as the Check button of the attempt page.
            $attemptobj->process_submitted_actions(time(), false, $postdata);
            $attemptobj->fire_attempt_updated_event();
        } catch (moodle_exception $ex) {
            throw quiz_attempt_helper::map_exception($ex, $attemptid);
        }

        $qa = $attemptobj->get_question_attempt($slot);
        $tryagain = self::is_try_again_state($qa);
        $finished = $qa->get_state()->is_finished();

        // What the attempt page shows under the question now: the review options "during the attempt",
        // adjusted by the behaviour (for example no correctness while another try is open).
        $options = clone $attemptobj->get_display_options(false);
        $qa->get_behaviour()->adjust_display_options($options);

        $state = $finished ? quiz_attempt_helper::state_for($qa->get_state(), (bool) $options->correctness) : null;

        // The renderers need a page context and URL; the attempt page is the one this mirrors.
        $PAGE->set_context($attemptobj->get_context());
        if (!$PAGE->has_set_url()) {
            $PAGE->set_url($attemptobj->attempt_url($slot, $attemptobj->get_question_page($slot)));
        }
        // The right answer is a field of its own, not part of the feedback text.
        $feedbackoptions = clone $options;
        $feedbackoptions->rightanswer = question_display_options::HIDDEN;
        $feedback = '';
        if ($options->feedback || $options->generalfeedback || $options->numpartscorrect) {
            $feedback = quiz_attempt_helper::plain(
                (string) $qa->get_question()->get_renderer($PAGE)->feedback($qa, $feedbackoptions)
            );
        }

        $answer = quiz_attempt_helper::answer_shown($attemptobj, $slot);
        $result = [
            'slot' => $slot,
            'answer_checked' => $answer,
            'state' => $state,
            'feedback' => $feedback !== '' ? $feedback : null,
            'finished' => $finished,
            'sequence' => (int) $qa->get_sequence_check_count(),
        ];
        if ($finished && $options->rightanswer) {
            $right = $qa->get_right_answer_summary();
            if ($right !== null && $right !== '') {
                $result['right_answer'] = quiz_attempt_helper::plain((string) $right);
            }
        }
        $shown = is_array($answer) ? implode('; ', $answer) : (string) $answer;
        $of = $shown !== '' ? sprintf(' (answer: %s)', $shown) : '';
        if ($tryagain) {
            $result['tries_left'] = (int) $qa->get_last_behaviour_var('_triesleft');
            $result['summary'] = sprintf(
                'Checked slot %d%s: not right yet, %d more try(s).',
                $slot,
                $of,
                $result['tries_left']
            );
        } else {
            $result['summary'] = sprintf(
                'Checked slot %d%s: %s. The answer can no longer be changed.',
                $slot,
                $of,
                $state ?? 'correctness is not shown during the attempt'
            );
        }
        return $result;
    }

    /**
     * Whether an interactive question waits for "Try again" after a wrong check.
     *
     * @param \question_attempt $qa Question attempt.
     * @return bool
     */
    private static function is_try_again_state(\question_attempt $qa): bool {
        $behaviour = $qa->get_behaviour();
        return $behaviour instanceof \qbehaviour_interactive && $behaviour->is_try_again_state();
    }
}

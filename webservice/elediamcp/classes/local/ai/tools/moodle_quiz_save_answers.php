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
use stdClass;
use webservice_elediamcp\local\ai\ai_tool;
use webservice_elediamcp\local\ai\tool_exception;

/**
 * Learner tool: save answers in an open quiz attempt (no grading yet).
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moodle_quiz_save_answers implements ai_tool {
    /** @var int Upper bound for answers in one call. */
    private const MAX_ANSWERS = 200;

    /**
     * Return the MCP tool name.
     *
     * @return string
     */
    public static function name(): string {
        return 'moodle_quiz_save_answers';
    }

    /**
     * Return the human-readable tool title.
     *
     * @return string
     */
    public static function title(): string {
        return 'Save answers in my quiz attempt';
    }

    /**
     * Return the tool description shown to MCP clients.
     *
     * @return string
     */
    public static function description(): string {
        return 'Saves the authenticated user\'s answers in their open quiz attempt, like moving to the next '
            . 'page in Moodle. Send {slot, value} for single, text and number inputs and {slot, values} for '
            . 'multi (list of option values) and match (stem key => option value). Saving the same answers '
            . 'again changes nothing. Send the sequence of the question (from moodle_quiz_get_page, or from the '
            . 'previous save) with every answer: if the question was changed elsewhere in the meantime, for '
            . 'example in the browser, the call is refused with out_of_sequence and nothing is saved; load the '
            . 'page again then. Without sequence the answer overwrites whatever is saved now. Only save answers '
            . 'the user gave; Moodle grades them on moodle_quiz_finish.';
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
            'required' => ['attempt_id', 'answers'],
            'properties' => [
                'attempt_id' => ['type' => 'integer', 'minimum' => 1],
                'answers' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => self::MAX_ANSWERS,
                    'items' => [
                        'type' => 'object',
                        'required' => ['slot'],
                        'properties' => [
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
                                'description' => 'The sequence of the question from moodle_quiz_get_page or from the '
                                    . 'previous save. Protects answers given elsewhere from being overwritten.',
                            ],
                        ],
                    ],
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
            'required' => ['saved', 'summary'],
            'properties' => [
                'saved' => ['type' => 'integer', 'description' => 'Number of questions processed.'],
                'slots' => ['type' => 'array', 'items' => ['type' => 'integer']],
                'validation' => [
                    'type' => 'object',
                    'description' => 'Slot => why the saved answer is not complete (for example a word limit); '
                        . 'empty when every answer is complete. Tell the user and save a corrected answer.',
                ],
                'sequences' => [
                    'type' => 'object',
                    'description' => 'Slot => the new sequence of the question, to use with the next save.',
                ],
                'state' => ['type' => 'string'],
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
            'idempotentHint' => true,
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
        $attemptid = (int) ($arguments['attempt_id'] ?? 0);
        $answers = $arguments['answers'] ?? null;
        if ($attemptid <= 0 || !is_array($answers) || $answers === []) {
            throw new tool_exception(
                'attempt_id and a non-empty answers list are required.',
                ['errorcode' => 'invalid_argument']
            );
        }
        if (count($answers) > self::MAX_ANSWERS) {
            throw new tool_exception(
                'Too many answers in one call (max ' . self::MAX_ANSWERS . ').',
                ['errorcode' => 'invalid_argument']
            );
        }

        $attemptobj = quiz_attempt_helper::open_attempt($attemptid, $user);
        $attemptslots = array_map('intval', $attemptobj->get_slots());

        // Validate everything first, so a bad answer saves nothing.
        $postdata = [];
        $slots = [];
        foreach ($answers as $answer) {
            if (!is_array($answer) || !isset($answer['slot']) || !is_numeric($answer['slot'])) {
                throw new tool_exception('Every answer needs a slot.', ['errorcode' => 'invalid_answer']);
            }
            $slot = (int) $answer['slot'];
            if (!in_array($slot, $attemptslots, true) || !$attemptobj->is_real_question($slot)) {
                throw new tool_exception(
                    "Slot {$slot} is not a question of this attempt.",
                    ['slot' => $slot, 'errorcode' => 'invalid_answer']
                );
            }
            if (isset($slots[$slot])) {
                throw new tool_exception("Slot {$slot} appears twice.", ['slot' => $slot, 'errorcode' => 'invalid_answer']);
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
            if (isset($answer['sequence'])) {
                if (!is_numeric($answer['sequence']) || (int) $answer['sequence'] < 0) {
                    throw new tool_exception(
                        "sequence in slot {$slot} must be a whole number from moodle_quiz_get_page.",
                        ['slot' => $slot, 'errorcode' => 'invalid_answer']
                    );
                }
                if ((int) $answer['sequence'] !== (int) $qa->get_sequence_check_count()) {
                    throw new tool_exception(
                        "The question in slot {$slot} was changed elsewhere (for example in the browser) since you "
                            . 'loaded it. Load the page again and retry; nothing was saved.',
                        ['slot' => $slot, 'attempt_id' => $attemptid, 'errorcode' => 'out_of_sequence']
                    );
                }
            }
            if ($qa->get_state()->is_finished()) {
                // Checked in the browser (interactive or immediate feedback): Moodle would drop the answer
                // silently, so say it instead of reporting "Saved".
                throw new tool_exception(
                    "The question in slot {$slot} was already checked in the browser; its answer can no longer be changed.",
                    ['slot' => $slot, 'errorcode' => 'question_finished']
                );
            }
            if (!$attemptobj->check_page_access((int) $attemptobj->get_question_page($slot))) {
                throw new tool_exception(
                    "Slot {$slot} is not on a page you can answer now.",
                    ['slot' => $slot, 'errorcode' => 'invalid_answer']
                );
            }

            $prefix = $qa->get_field_prefix();
            foreach (quiz_attempt_helper::response_for($qa, $answer) as $name => $value) {
                $postdata[$prefix . $name] = $value;
            }
            $postdata[$prefix . ':sequencecheck'] = (string) $qa->get_sequence_check_count();
            $slots[$slot] = $slot;
        }
        $postdata = ['slots' => implode(',', $slots)] + $postdata;

        try {
            // Same processing as submitting the attempt form without finishing it.
            $attemptobj->process_submitted_actions(time(), false, $postdata);
            $attemptobj->fire_attempt_updated_event();
        } catch (moodle_exception $ex) {
            throw quiz_attempt_helper::map_exception($ex, $attemptid);
        }

        // What the browser shows under a field: an answer outside the limits is saved but not complete.
        $validation = new \stdClass();
        foreach ($slots as $slot) {
            $qa = $attemptobj->get_question_attempt($slot);
            $question = $qa->get_question();
            $message = method_exists($question, 'get_validation_error')
                ? quiz_attempt_helper::plain((string) $question->get_validation_error($qa->get_last_qt_data()))
                : '';
            if ($message !== '') {
                $validation->{(string) $slot} = $message;
            }
        }
        $sequences = new \stdClass();
        foreach ($slots as $slot) {
            $sequences->{(string) $slot} = (int) $attemptobj->get_question_attempt($slot)->get_sequence_check_count();
        }

        $result = [
            'saved' => count($slots),
            'slots' => array_values($slots),
            'sequences' => $sequences,
            'state' => (string) $attemptobj->get_state(),
            'summary' => sprintf(
                'Saved %d answer(s).%s Finish with moodle_quiz_finish when the user is done.',
                count($slots),
                (array) $validation === [] ? '' : ' Some answers are not complete, see validation.'
            ),
        ];
        if ((array) $validation !== []) {
            $result['validation'] = $validation;
        }
        return $result;
    }
}

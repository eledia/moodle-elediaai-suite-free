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

use mod_quiz\quiz_attempt;
use moodle_exception;
use question_state;
use stdClass;
use webservice_elediamcp\local\ai\ai_tool;
use webservice_elediamcp\local\ai\tool_exception;

/**
 * Learner tool: submit an open quiz attempt for grading (two-step confirmation).
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moodle_quiz_finish implements ai_tool {
    /**
     * Return the MCP tool name.
     *
     * @return string
     */
    public static function name(): string {
        return 'moodle_quiz_finish';
    }

    /**
     * Return the human-readable tool title.
     *
     * @return string
     */
    public static function title(): string {
        return 'Submit my quiz attempt';
    }

    /**
     * Return the tool description shown to MCP clients.
     *
     * @return string
     */
    public static function description(): string {
        return 'Submits the authenticated user\'s open quiz attempt for grading. Without confirm it only '
            . 'returns a preview with the number of unanswered questions; tell the user and call again with '
            . 'confirm=true only after they agreed. Submitting cannot be undone: the answers can no longer be '
            . 'changed and Moodle grades the attempt. Returns the grade when the quiz shows it; it follows the '
            . 'review options of the quiz, and the gradebook can hide it separately (moodle_quiz_info reports '
            . 'that as grade_withheld). requires_manual_grading true means a teacher still grades some answers, '
            . 'so the grade is provisional. With review_available true the user can see their answers and the '
            . 'right answers with moodle_quiz_get_review. The preview holds quiz_name, answered and unanswered '
            . 'questions.';
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
            'required' => ['attempt_id'],
            'properties' => [
                'attempt_id' => ['type' => 'integer', 'minimum' => 1],
                'confirm' => [
                    'type' => 'boolean',
                    'default' => false,
                    'description' => 'Must be true to actually submit the attempt.',
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
            'required' => ['finished', 'requires_confirmation', 'summary'],
            'properties' => [
                'finished' => ['type' => 'boolean'],
                'requires_confirmation' => ['type' => 'boolean'],
                'preview' => [
                    'type' => 'object',
                    'description' => 'Only with requires_confirmation=true: what the confirmation shows the user. '
                        . 'The facts are here and not at the top level.',
                    'properties' => [
                        'quiz_name' => ['type' => 'string'],
                        'answered' => ['type' => 'integer'],
                        'unanswered' => ['type' => 'integer',
                            'description' => 'Questions without an answer. Saved, not yet checked answers are not in it.'],
                        'saved_unchecked' => ['type' => 'integer',
                            'description' => 'Answers saved but not checked yet (questions with several tries). '
                                . 'They count as answered and are graded on submit.'],
                        'card_summary' => [
                            'type' => 'string',
                            'description' => 'Plain text for the confirmation card, in the language of the user. '
                                . 'Show this and not the English summary.',
                        ],
                    ],
                ],
                'state' => ['type' => 'string', 'enum' => ['inprogress', 'overdue', 'finished', 'abandoned'],
                    'description' => 'Only after the attempt was submitted.'],
                'unanswered' => ['type' => 'integer', 'description' => 'Only after the attempt was submitted.'],
                'requires_manual_grading' => [
                    'type' => 'boolean',
                    'description' => 'True when a teacher still grades some answers: the grade is provisional.',
                ],
                'grade' => ['type' => ['number', 'null'],
                    'description' => 'Null while hidden by the review options or waiting for manual grading.'],
                'max_grade' => ['type' => 'number'],
                'review_available' => ['type' => 'boolean'],
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
            'destructiveHint' => true,
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
        $attemptid = (int) ($arguments['attempt_id'] ?? 0);
        if ($attemptid <= 0) {
            throw new tool_exception('attempt_id is required.', ['errorcode' => 'invalid_argument']);
        }

        // An overdue attempt may still be submitted while the grace period lasts.
        $graceperiod = self::uses_grace_period($attemptid, $user);
        $attemptobj = quiz_attempt_helper::open_attempt($attemptid, $user, $graceperiod);
        $unanswered = $attemptobj->get_number_of_unanswered_questions();
        $maxgrade = round((float) $attemptobj->get_quiz()->grade, 2);

        if (empty($arguments['confirm'])) {
            $total = 0;
            foreach ($attemptobj->get_slots() as $slot) {
                if ($attemptobj->is_real_question((int) $slot)) {
                    $total++;
                }
            }
            $saved = $unanswered > 0 ? self::count_saved_unchecked($attemptobj) : 0;
            // Moodle counts an answer that is saved but not checked yet (questions with several tries) as
            // unanswered, although submitting grades it (#195). Those are answered for the person.
            $empty = max(0, $unanswered - $saved);
            $a = (object) [
                'quiz' => format_string((string) $attemptobj->get_quiz()->name),
                'answered' => max(0, $total - $empty),
                'unanswered' => $empty,
                'saved_unchecked' => $saved,
                'total' => $total,
            ];
            // What the confirmation shows is in `preview` only (#195): the card used to print state and the
            // unanswered count from the top level, next to its text.
            return [
                'finished' => false,
                'requires_confirmation' => true,
                'preview' => [
                    'quiz_name' => $a->quiz,
                    'answered' => $a->answered,
                    'unanswered' => $empty,
                    'saved_unchecked' => $saved,
                    'card_summary' => quiz_attempt_helper::person_string('cardsummary_quiz_finish', 'webservice_elediamcp', $a),
                ],
                'summary' => ($empty > 0
                        ? sprintf('%d question(s) are still unanswered. ', $empty)
                        : 'All questions are answered. ')
                    . ($saved > 0
                        ? sprintf('%d answer(s) are saved but not checked yet; submitting grades them. ', $saved)
                        : '')
                    . 'After submitting, the answers cannot be changed. Call again with confirm=true to submit.',
            ];
        }

        try {
            $state = $attemptobj->process_attempt(time(), true, false, (int) $attemptobj->get_currentpage());
        } catch (moodle_exception $ex) {
            throw quiz_attempt_helper::map_exception($ex, $attemptid);
        }

        $attemptobj = quiz_attempt::create($attemptid);
        $grade = quiz_attempt_helper::visible_grade($attemptobj, $attemptobj->get_display_options(true));
        $reviewavailable = quiz_attempt_helper::review_available($attemptobj);
        $manual = (bool) $attemptobj->requires_manual_grading();

        if ($state === quiz_attempt::ABANDONED) {
            $summary = 'The grace period had run out, so the attempt was closed without grading.';
        } else if ($grade !== null && $manual) {
            $summary = sprintf(
                'Submitted. Provisional grade %s of %s: a teacher still grades some answers, so it can change.',
                $grade,
                $maxgrade
            );
        } else if ($grade !== null) {
            $summary = sprintf('Submitted. Grade %s of %s.', $grade, $maxgrade);
        } else if ($attemptobj->requires_manual_grading()) {
            $summary = 'Submitted. Some answers are graded by a teacher; the grade follows later.';
        } else {
            $summary = 'Submitted. The quiz does not show the grade yet.';
        }

        return [
            'finished' => true,
            'requires_confirmation' => false,
            'state' => (string) $attemptobj->get_state(),
            'unanswered' => $unanswered,
            'grade' => $grade,
            'max_grade' => $maxgrade,
            'requires_manual_grading' => $manual,
            'review_available' => $reviewavailable,
            'summary' => $summary,
        ];
    }

    /**
     * Number of questions whose answer is saved but not checked yet.
     *
     * Moodle puts such a question in the state "todo", the same as an empty one, and
     * get_number_of_unanswered_questions() counts both.
     *
     * @param quiz_attempt $attemptobj Attempt.
     * @return int
     */
    private static function count_saved_unchecked(quiz_attempt $attemptobj): int {
        $count = 0;
        foreach ($attemptobj->get_slots() as $slot) {
            if (!$attemptobj->is_real_question((int) $slot)) {
                continue;
            }
            if ($attemptobj->get_question_state((int) $slot) !== question_state::$todo) {
                continue;
            }
            $qa = $attemptobj->get_question_attempt((int) $slot);
            $data = $qa->get_last_qt_data();
            // Nothing saved: not asked, and some question types are not ready to judge an empty response.
            if (!$data) {
                continue;
            }
            if ($qa->get_question(false)->is_complete_response($data)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Whether the attempt's quiz lets overdue attempts be submitted during a grace period.
     *
     * @param int $attemptid Attempt id.
     * @param stdClass $user Authenticated user.
     * @return bool
     */
    private static function uses_grace_period(int $attemptid, stdClass $user): bool {
        $attemptobj = quiz_attempt_helper::own_attempt($attemptid, $user);
        return (string) $attemptobj->get_quiz()->overduehandling === 'graceperiod';
    }
}

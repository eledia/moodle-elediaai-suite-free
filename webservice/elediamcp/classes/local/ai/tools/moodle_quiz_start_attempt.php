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
use mod_quiz\quiz_settings;
use moodle_exception;
use stdClass;
use webservice_elediamcp\local\ai\ai_tool;
use webservice_elediamcp\local\ai\tool_exception;

/**
 * Learner tool: start a Moodle quiz attempt, or continue the open one.
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moodle_quiz_start_attempt implements ai_tool {
    /**
     * Return the MCP tool name.
     *
     * @return string
     */
    public static function name(): string {
        return 'moodle_quiz_start_attempt';
    }

    /**
     * Return the human-readable tool title.
     *
     * @return string
     */
    public static function title(): string {
        return 'Start or continue my quiz attempt';
    }

    /**
     * Return the tool description shown to MCP clients.
     *
     * @return string
     */
    public static function description(): string {
        return 'Starts an attempt at a normal Moodle quiz (by cmid) for the authenticated user, or continues '
            . 'the attempt that is still open; it never opens a second one. Returns the attempt id, page count, '
            . 'current page and the time left. A new attempt with a time limit or a limited number of attempts '
            . 'starts the clock or uses up an attempt, so the first call only returns a preview '
            . '(requires_confirmation true; preview holds quiz_name, attempt_number, attempts_allowed and '
            . 'time_limit_minutes, null when there is no limit): tell the user and call '
            . 'again with confirm=true only after they agreed. Continuing an open attempt needs no confirmation. '
            . 'checkable is true when the quiz has a Check button with feedback during the attempt (immediate '
            . 'feedback, interactive): check one answer with moodle_quiz_check_question when the user asks for it. '
            . 'check_in_browser is true when the quiz has a Check step this server cannot offer (adaptive mode): '
            . 'that step exists only in the browser; answers saved here are graded when the attempt is finished. '
            . 'When inline_supported is false (password, Safe Exam Browser, '
            . 'network or delay rules, sequential navigation or question types that cannot be shown here), '
            . 'send the user to fallback_url instead. Then use moodle_quiz_get_page, moodle_quiz_save_answers '
            . 'and moodle_quiz_finish; an attempt whose time is up can only be submitted with moodle_quiz_finish. '
            . 'Moodle grades the attempt; never answer questions for the user.';
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
            'required' => ['cmid'],
            'properties' => [
                'cmid' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'description' => 'Course module id of the quiz.',
                ],
                'confirm' => [
                    'type' => 'boolean',
                    'description' => 'Set true after the user agreed to start a new attempt that has a time limit '
                        . 'or counts against a limited number of attempts.',
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
            'required' => ['attempt_id', 'inline_supported', 'fallback_url', 'summary'],
            'properties' => [
                'attempt_id' => ['type' => ['integer', 'null'],
                    'description' => 'Null when no attempt was started because the browser is needed.'],
                'state' => ['type' => 'string', 'enum' => ['notstarted', 'inprogress', 'overdue']],
                'resumed' => ['type' => 'boolean', 'description' => 'True when an open attempt was continued.'],
                'page_count' => ['type' => 'integer'],
                'current_page' => ['type' => 'integer', 'description' => '0-based.'],
                'time_limit_seconds' => ['type' => ['integer', 'null']],
                'time_left_seconds' => ['type' => ['integer', 'null']],
                'requires_confirmation' => ['type' => 'boolean',
                    'description' => 'True when nothing was started: confirm with the user, then call again with confirm=true.'],
                'preview' => [
                    'type' => 'object',
                    'description' => 'Only with requires_confirmation=true: what the confirmation shows the user. '
                        . 'The facts are here and not at the top level.',
                    'properties' => [
                        'quiz_name' => ['type' => 'string'],
                        'attempt_number' => ['type' => 'integer', 'description' => 'The attempt this would be.'],
                        'attempts_allowed' => ['type' => ['integer', 'null'], 'description' => 'Null when unlimited.'],
                        'time_limit_minutes' => ['type' => ['integer', 'null'], 'description' => 'Null when there is none.'],
                        'card_summary' => [
                            'type' => 'string',
                            'description' => 'Plain text for the confirmation card, in the language of the user. '
                                . 'Show this and not the English summary.',
                        ],
                    ],
                ],
                'attempts_allowed' => ['type' => ['integer', 'null'], 'description' => 'Null when unlimited.'],
                'attempts_used' => ['type' => 'integer'],
                'inline_supported' => ['type' => 'boolean'],
                'checkable' => ['type' => 'boolean',
                    'description' => 'True when single answers can be checked with moodle_quiz_check_question.'],
                'check_in_browser' => ['type' => 'boolean',
                    'description' => 'True when hints and feedback during the attempt (Check button) exist only in the browser.'],
                'unsupported_reason' => ['type' => ['string', 'null']],
                'fallback_url' => ['type' => 'string'],
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
        $cmid = (int) ($arguments['cmid'] ?? 0);
        if ($cmid <= 0) {
            throw new tool_exception('cmid is required.', ['errorcode' => 'invalid_argument']);
        }

        $quizobj = quiz_attempt_helper::quiz_for_cmid($cmid, $user);
        if (!$quizobj->is_preview_user()) {
            require_capability('mod/quiz:attempt', $quizobj->get_context(), $user->id);
        }
        if (!$quizobj->has_questions()) {
            throw new tool_exception('This quiz has no questions yet.', ['cmid' => $cmid, 'errorcode' => 'no_questions']);
        }

        $accessmanager = $quizobj->get_access_manager(time());
        $unfinished = quiz_get_user_attempt_unfinished($quizobj->get_quizid(), (int) $user->id);

        // A quiz that is not open for this user shows nothing about its structure: the browser says only
        // that it is not available. The browser fallback below (password, SEB, ...) comes after this.
        if (!$quizobj->is_preview_user()) {
            $messages = $accessmanager->prevent_access();
            if ($messages) {
                throw quiz_attempt_helper::access_denied($messages, $quizobj);
            }
        }

        $reason = quiz_attempt_helper::browser_only_reason($quizobj, $accessmanager);
        if ($reason === null) {
            $qtypes = quiz_attempt_helper::unsupported_qtypes_in_structure($quizobj);
            if ($qtypes) {
                $reason = 'This quiz contains question types the app cannot show (' . implode(', ', $qtypes) . ').';
            }
        }
        if ($reason !== null) {
            return self::browser_payload($quizobj, $unfinished ?: null, $reason);
        }

        try {
            [$currentattemptid, $attemptnumber, $lastattempt, $messages] =
                quiz_validate_new_attempt($quizobj, $accessmanager, false, -1, false);
        } catch (moodle_exception $ex) {
            if ($ex->errorcode === 'attemptalreadyclosed') {
                throw new tool_exception(
                    'Your open attempt was just closed because its time ran out. Call this tool again to '
                        . 'start a new attempt if one is allowed.',
                    ['cmid' => $cmid, 'errorcode' => 'attempt_finished']
                );
            }
            throw quiz_attempt_helper::map_exception($ex, 0);
        }
        if (!$quizobj->is_preview_user() && $messages) {
            throw quiz_attempt_helper::access_denied($messages, $quizobj);
        }

        $resumed = (bool) $currentattemptid;
        if (!$resumed && empty($arguments['confirm']) && self::needs_confirmation($quizobj)) {
            return self::confirmation_payload($quizobj, (int) $attemptnumber - 1);
        }
        if ($resumed) {
            $attemptobj = quiz_attempt::create((int) $currentattemptid);
        } else {
            if ($accessmanager->is_preflight_check_required(null)) {
                // Only rules without user input reach this point (the time-limit notice).
                if ($accessmanager->validate_preflight_check([], [], null)) {
                    return self::browser_payload($quizobj, null, 'This quiz asks for information before it starts.');
                }
                $accessmanager->notify_preflight_check_passed(null);
            }
            try {
                $attempt = quiz_prepare_and_start_new_attempt($quizobj, $attemptnumber, $lastattempt);
            } catch (moodle_exception $ex) {
                throw quiz_attempt_helper::map_exception($ex, 0);
            }
            $attemptobj = quiz_attempt::create((int) $attempt->id);
        }

        $now = time();
        $endtime = $attemptobj->get_access_manager($now)->get_end_time($attemptobj->get_attempt());
        $unsupported = quiz_attempt_helper::unsupported_slots($attemptobj);
        $inline = $unsupported === [];
        $timelimit = (int) ($quizobj->get_quiz()->timelimit ?? 0);

        $overdue = $resumed && (string) $attemptobj->get_state() === quiz_attempt::OVERDUE;
        $verb = $resumed ? 'Continued' : 'Started';
        $checkable = quiz_attempt_helper::is_checkable_quiz($quizobj->get_quiz());
        $checkinbrowser = !$checkable && quiz_attempt_helper::has_check_step($quizobj->get_quiz());
        if ($checkable) {
            $checknote = ' This quiz has a Check step with feedback.';
        } else if ($checkinbrowser) {
            $checknote = ' This quiz also has a Check step with hints and feedback; it exists only in the browser, answers saved '
                . 'here are graded when the attempt is finished.';
        } else {
            $checknote = '';
        }
        $attemptid = (int) $attemptobj->get_attemptid();
        if ($overdue) {
            // The tool refuses pages and answers for an overdue attempt: say what is left to do.
            $graceend = $endtime !== false ? (int) $endtime + (int) ($quizobj->get_quiz()->graceperiod ?? 0) : 0;
            $summary = sprintf(
                'Continued attempt %d, but its time is up. Answers can no longer be saved; the attempt can only be '
                    . 'submitted%s.',
                $attemptid,
                $graceend > 0 ? ' (the grace period lasts until ' . userdate($graceend) . ')' : ''
            );
        } else if ($inline) {
            $summary = sprintf(
                '%s attempt %d with %d page(s).',
                $verb,
                $attemptid,
                $attemptobj->get_num_pages()
            ) . $checknote;
        } else {
            $summary = sprintf('%s attempt %d, but some questions need the browser: open fallback_url.', $verb, $attemptid);
        }

        return [
            'attempt_id' => $attemptid,
            'state' => (string) $attemptobj->get_state(),
            'resumed' => $resumed,
            'page_count' => (int) $attemptobj->get_num_pages(),
            'current_page' => (int) $attemptobj->get_currentpage(),
            'time_limit_seconds' => $timelimit > 0 ? $timelimit : null,
            'time_left_seconds' => $overdue ? 0 : ($endtime !== false ? max(0, (int) $endtime - $now) : null),
            'inline_supported' => $inline,
            'checkable' => $checkable,
            'check_in_browser' => $checkinbrowser,
            'unsupported_reason' => $inline ? null
                : 'Questions in slots ' . implode(', ', $unsupported) . ' cannot be shown in the app.',
            'fallback_url' => $inline
                ? quiz_attempt_helper::view_url($cmid)
                : $attemptobj->attempt_url(null, $attemptobj->get_currentpage())->out(false),
            'summary' => $summary,
        ];
    }

    /**
     * Whether a new attempt has consequences the user has to agree to first.
     *
     * A time limit starts a clock the moment the attempt exists; a limited number of attempts
     * means this one is used up. In the browser Moodle asks first (start page, time limit notice).
     *
     * @param quiz_settings $quizobj Quiz.
     * @return bool
     */
    private static function needs_confirmation(quiz_settings $quizobj): bool {
        $quiz = $quizobj->get_quiz();
        return (int) ($quiz->timelimit ?? 0) > 0 || (int) ($quiz->attempts ?? 0) > 0;
    }

    /**
     * Preview returned instead of starting a new attempt that needs the user's agreement.
     *
     * @param quiz_settings $quizobj Quiz.
     * @param int $used Attempts the user has already made.
     * @return array<string, mixed>
     */
    private static function confirmation_payload(quiz_settings $quizobj, int $used): array {
        $quiz = $quizobj->get_quiz();
        $timelimit = (int) ($quiz->timelimit ?? 0);
        $allowed = (int) ($quiz->attempts ?? 0);
        $parts = [];
        if ($timelimit > 0) {
            $parts[] = sprintf('it has a time limit of %d minute(s) that starts at once', (int) ceil($timelimit / 60));
        }
        if ($allowed > 0) {
            $parts[] = sprintf('it uses attempt %d of %d', $used + 1, $allowed);
        }

        $minutes = $timelimit > 0 ? (int) ceil($timelimit / 60) : null;
        $a = (object) [
            'quiz' => format_string((string) $quiz->name),
            'number' => $used + 1,
            'allowed' => $allowed,
            'minutes' => $minutes,
        ];
        $key = $allowed > 0 ? 'cardsummary_quiz_start_limited' : 'cardsummary_quiz_start';
        $card = quiz_attempt_helper::person_string($key, 'webservice_elediamcp', $a);
        if ($minutes !== null) {
            $key = $minutes === 1 ? 'cardsummary_quiz_timelimit_one' : 'cardsummary_quiz_timelimit';
            $card .= ' ' . quiz_attempt_helper::person_string($key, 'webservice_elediamcp', $a);
        }

        // What the confirmation shows is in `preview` only (#195): the card used to print state and counts
        // from the top level, next to its text.
        return [
            'attempt_id' => null,
            'resumed' => false,
            'requires_confirmation' => true,
            'preview' => [
                'quiz_name' => $a->quiz,
                'attempt_number' => $used + 1,
                'attempts_allowed' => $allowed > 0 ? $allowed : null,
                'time_limit_minutes' => $minutes,
                'card_summary' => $card,
            ],
            'inline_supported' => true,
            'fallback_url' => quiz_attempt_helper::view_url((int) $quizobj->get_cmid()),
            'summary' => 'Nothing was started. Starting a new attempt cannot be undone: '
                . implode(' and ', $parts) . '. Ask the user, then call again with confirm=true.',
        ];
    }

    /**
     * Payload when the quiz has to be taken in the browser; no attempt is started.
     *
     * @param quiz_settings $quizobj Quiz.
     * @param stdClass|null $unfinished The user's open attempt, if any.
     * @param string $reason Why the browser is needed.
     * @return array<string, mixed>
     */
    private static function browser_payload(quiz_settings $quizobj, ?stdClass $unfinished, string $reason): array {
        global $DB;

        $pages = (int) $DB->count_records_sql(
            'SELECT COUNT(DISTINCT page) FROM {quiz_slots} WHERE quizid = :quizid',
            ['quizid' => $quizobj->get_quizid()]
        );
        $timelimit = (int) ($quizobj->get_quiz()->timelimit ?? 0);

        return [
            'attempt_id' => $unfinished ? (int) $unfinished->id : null,
            'state' => $unfinished ? (string) $unfinished->state : 'notstarted',
            'resumed' => false,
            'page_count' => $pages,
            'current_page' => $unfinished ? (int) $unfinished->currentpage : 0,
            'time_limit_seconds' => $timelimit > 0 ? $timelimit : null,
            'time_left_seconds' => null,
            'inline_supported' => false,
            'unsupported_reason' => $reason,
            'fallback_url' => quiz_attempt_helper::view_url((int) $quizobj->get_cmid()),
            'summary' => $reason . ' Open fallback_url to take the quiz in the browser.',
        ];
    }
}

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

use context_module;
use mod_quiz\access_manager;
use mod_quiz\quiz_attempt;
use mod_quiz\quiz_settings;
use moodle_exception;
use moodle_url;
use question_attempt;
use question_display_options;
use stdClass;
use webservice_elediamcp\local\ai\tool_exception;

/**
 * Shared helpers for the moodle_quiz_* attempt tools.
 *
 * Everything goes through the mod_quiz attempt API and the question engine:
 * questions are described from their question definitions, answers are
 * written as question-engine post data and graded by Moodle. Nothing is read
 * back from rendered attempt HTML.
 *
 * The tools run as the authenticated user only. The quiz API reads the
 * current user from $USER in several places, so every entry point checks
 * that the user handed to the tool is the current one.
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class quiz_attempt_helper {
    /** @var string[] Access rules the app can honour inline; any other active rule needs the browser. */
    private const INLINE_RULES = [
        'quizaccess_openclosedate',
        'quizaccess_numattempts',
        'quizaccess_timelimit',
    ];

    /** @var string[] Error codes of mod_quiz whose message can be shown to the caller. */
    private const PASS_ON_ERRORCODES = [
        'attemptalreadyclosed', 'attempterror', 'notyourattempt', 'noattemptsallowed', 'timelimitexceeded',
        'attemptnotinprogress', 'cannotreview',
    ];

    /** @var string[] Behaviours with a "Check" step (hints and feedback during the attempt), browser only. */
    private const CHECK_BEHAVIOURS = ['immediatefeedback', 'interactive', 'adaptive', 'adaptivenopenalty'];

    /** @var string[] Question behaviours whose responses can be saved without extra controls. */
    private const INLINE_BEHAVIOURS = [
        'deferredfeedback',
        'immediatefeedback',
        'interactive',
        'adaptive',
        'adaptivenopenalty',
        'manualgraded',
        'informationitem',
    ];

    /** @var string[] Question types the app renders inline. */
    private const INLINE_QTYPES = [
        'multichoice',
        'truefalse',
        'shortanswer',
        'numerical',
        'essay',
        'aitext',
        'match',
        'description',
    ];

    /**
     * Load the quiz behind a course module id for the authenticated user.
     *
     * @param int $cmid Course module id.
     * @param stdClass $user Authenticated user.
     * @return quiz_settings
     * @throws tool_exception When the quiz does not exist or is not visible.
     */
    public static function quiz_for_cmid(int $cmid, stdClass $user): quiz_settings {
        self::require_current_user($user);
        self::require_quiz_lib();

        $notfound = new tool_exception(
            "No quiz with cmid {$cmid} is visible to you.",
            ['cmid' => $cmid, 'errorcode' => 'quiz_not_found']
        );
        $cm = get_coursemodule_from_id('quiz', $cmid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            throw $notfound;
        }
        self::require_cm_visible((int) $cm->course, $cmid, $user, $notfound);

        return quiz_settings::create((int) $cm->instance, (int) $user->id);
    }

    /**
     * Load one of the user's own attempts and check it may still be worked on.
     *
     * @param int $attemptid Attempt id.
     * @param stdClass $user Authenticated user.
     * @param bool $allowoverdue Whether an overdue attempt (grace period) is acceptable.
     * @return quiz_attempt
     * @throws tool_exception When the attempt is not the user's, is closed or is blocked.
     */
    public static function open_attempt(int $attemptid, stdClass $user, bool $allowoverdue = false): quiz_attempt {
        $attemptobj = self::own_attempt($attemptid, $user);

        if (!$attemptobj->is_preview_user()) {
            require_capability('mod/quiz:attempt', $attemptobj->get_context(), $user->id);
        }

        // Closes the attempt when its time ran out, exactly as the attempt page does.
        $attemptobj->handle_if_time_expired(time(), true);
        if ($attemptobj->is_finished()) {
            throw new tool_exception(
                'This attempt is already finished. Use moodle_quiz_get_review to see the result.',
                ['attempt_id' => $attemptid, 'state' => $attemptobj->get_state(), 'errorcode' => 'attempt_finished']
            );
        }
        if (!$allowoverdue && $attemptobj->get_state() === quiz_attempt::OVERDUE) {
            throw new tool_exception(
                'The time for this attempt has run out. Submit it with moodle_quiz_finish.',
                ['attempt_id' => $attemptid, 'state' => quiz_attempt::OVERDUE, 'errorcode' => 'attempt_overdue']
            );
        }

        $accessmanager = $attemptobj->get_access_manager(time());
        $messages = $accessmanager->prevent_access();
        if (!$attemptobj->is_preview_user() && $messages) {
            throw self::access_denied($messages, $attemptobj->get_quizobj());
        }
        $blocker = self::browser_only_reason($attemptobj->get_quizobj(), $accessmanager);
        if ($blocker !== null) {
            throw new tool_exception(
                $blocker . ' Continue this attempt in the browser.',
                [
                    'attempt_id' => $attemptid,
                    'fallback_url' => $attemptobj->attempt_url(null, $attemptobj->get_currentpage())->out(false),
                    'errorcode' => 'inline_not_supported',
                ]
            );
        }

        return $attemptobj;
    }

    /**
     * Load one of the user's own attempts, in any state.
     *
     * @param int $attemptid Attempt id.
     * @param stdClass $user Authenticated user.
     * @return quiz_attempt
     * @throws tool_exception When the attempt does not exist or belongs to someone else.
     */
    public static function own_attempt(int $attemptid, stdClass $user): quiz_attempt {
        global $DB;

        self::require_current_user($user);
        self::require_quiz_lib();

        // One message for "missing" and "someone else's", so ids cannot be probed.
        $notfound = new tool_exception(
            "No quiz attempt {$attemptid} of yours exists.",
            ['attempt_id' => $attemptid, 'errorcode' => 'attempt_not_found']
        );
        $row = $DB->get_record('quiz_attempts', ['id' => $attemptid], 'id, userid, quiz', IGNORE_MISSING);
        if (!$row || (int) $row->userid !== (int) $user->id) {
            throw $notfound;
        }
        $attemptobj = quiz_attempt::create($attemptid);
        self::require_cm_visible((int) $attemptobj->get_courseid(), (int) $attemptobj->get_cmid(), $user, $notfound);

        return $attemptobj;
    }

    /**
     * Why this quiz cannot be taken inside the app, or null when it can.
     *
     * Checks the quiz settings and access rules only; question types are
     * checked per attempt with {@see self::unsupported_slots()}.
     *
     * @param quiz_settings $quizobj Quiz.
     * @param access_manager $accessmanager Access manager for now.
     * @return string|null Human-readable reason.
     */
    public static function browser_only_reason(quiz_settings $quizobj, access_manager $accessmanager): ?string {
        $quiz = $quizobj->get_quiz();
        if ((string) $quiz->navmethod === QUIZ_NAVMETHOD_SEQ) {
            return 'This quiz uses sequential navigation.';
        }
        if (!in_array((string) $quiz->preferredbehaviour, self::INLINE_BEHAVIOURS, true)) {
            return 'This quiz uses the question behaviour "' . $quiz->preferredbehaviour . '".';
        }
        foreach ($accessmanager->get_active_rule_names() as $rule) {
            if (!in_array($rule, self::INLINE_RULES, true)) {
                return 'This quiz has the access rule "' . $rule . '" (for example a password, Safe Exam Browser, '
                    . 'network address or delay between attempts).';
            }
        }
        if ($accessmanager->attempt_must_be_in_popup()) {
            return 'This quiz must be taken in a secure window.';
        }
        return null;
    }

    /**
     * Question types of the quiz definition the app cannot render (random slots are checked per attempt).
     *
     * @param quiz_settings $quizobj Quiz.
     * @return string[] Unsupported question type names.
     */
    public static function unsupported_qtypes_in_structure(quiz_settings $quizobj): array {
        $quizobj->preload_questions();
        $types = array_diff($quizobj->get_all_question_types_used(false), ['random']);
        return array_values(array_diff($types, self::INLINE_QTYPES));
    }

    /**
     * Slots of an attempt whose question the app cannot render inline.
     *
     * @param quiz_attempt $attemptobj Attempt.
     * @return int[]
     */
    public static function unsupported_slots(quiz_attempt $attemptobj): array {
        $slots = [];
        foreach ($attemptobj->get_slots() as $slot) {
            if (!self::is_supported($attemptobj->get_question_attempt($slot))) {
                $slots[] = (int) $slot;
            }
        }
        return $slots;
    }

    /**
     * Whether a question attempt can be shown and answered inline.
     *
     * @param question_attempt $qa Question attempt.
     * @return bool
     */
    public static function is_supported(question_attempt $qa): bool {
        $question = $qa->get_question(false);
        $qtype = $question->get_type_name();
        if (
            !in_array($qtype, self::INLINE_QTYPES, true)
                || !in_array($qa->get_behaviour_name(), self::INLINE_BEHAVIOURS, true)
        ) {
            return false;
        }
        if ($qtype === 'essay' || $qtype === 'aitext') {
            // Plain text only: no file picker and no required attachments.
            $format = (string) ($question->responseformat ?? 'editor');
            if (!in_array($format, ['plain', 'monospaced', 'editor'], true)) {
                return false;
            }
            if (!empty($question->attachmentsrequired)) {
                return false;
            }
        }
        if ($qtype === 'numerical' && $question->has_separate_unit_field()) {
            return false;
        }
        return true;
    }

    /**
     * Describe the input of a question attempt for the app, with the current response.
     *
     * @param quiz_attempt $attemptobj Attempt.
     * @param int $slot Slot.
     * @return array<string, mixed>
     */
    public static function describe_input(quiz_attempt $attemptobj, int $slot): array {
        $qa = $attemptobj->get_question_attempt($slot);
        if (!self::is_supported($qa)) {
            return [
                'kind' => 'unsupported',
                'fallback_url' => $attemptobj->attempt_url($slot, $attemptobj->get_question_page($slot))->out(false),
            ];
        }

        $question = $qa->get_question();
        $response = $qa->get_last_qt_data();

        switch ($question->get_type_name()) {
            case 'truefalse':
                $input = [
                    'kind' => 'single',
                    'options' => [
                        ['value' => '1', 'label' => get_string('true', 'qtype_truefalse')],
                        ['value' => '0', 'label' => get_string('false', 'qtype_truefalse')],
                    ],
                ];
                if (array_key_exists('answer', $response) && $response['answer'] !== '') {
                    $input['value'] = ((int) $response['answer']) ? '1' : '0';
                }
                return $input;

            case 'multichoice':
                $order = $question->get_order($qa);
                $options = [];
                foreach ($order as $answerid) {
                    $answer = $question->answers[$answerid];
                    $options[] = [
                        'value' => (string) $answerid,
                        'label' => self::plain($question->format_text(
                            $answer->answer,
                            $answer->answerformat,
                            $qa,
                            'question',
                            'answer',
                            $answerid
                        )),
                    ];
                }
                if ($question instanceof \qtype_multichoice_single_question) {
                    $input = ['kind' => 'single', 'options' => $options];
                    $index = $response['answer'] ?? null;
                    if ($index !== null && $index !== '' && isset($order[(int) $index]) && (int) $index >= 0) {
                        $input['value'] = (string) $order[(int) $index];
                    }
                    return $input;
                }
                $values = [];
                foreach ($order as $index => $answerid) {
                    if (!empty($response['choice' . $index])) {
                        $values[] = (string) $answerid;
                    }
                }
                return ['kind' => 'multi', 'options' => $options, 'values' => $values];

            case 'shortanswer':
            case 'essay':
            case 'aitext':
                $input = ['kind' => 'text'];
                if ($question->get_type_name() === 'essay') {
                    // Limits the browser shows under the field; an answer outside them is not complete.
                    if ((int) ($question->minwordlimit ?? 0) > 0) {
                        $input['min_words'] = (int) $question->minwordlimit;
                    }
                    if ((int) ($question->maxwordlimit ?? 0) > 0) {
                        $input['max_words'] = (int) $question->maxwordlimit;
                    }
                    if (!empty($question->responserequired)) {
                        $input['response_required'] = true;
                    }
                }
                if (isset($response['answer']) && (string) $response['answer'] !== '') {
                    $input['value'] = $question->get_type_name() === 'shortanswer'
                        ? (string) $response['answer']
                        : self::plain((string) $response['answer']);
                }
                return $input;

            case 'numerical':
                $input = ['kind' => 'number'];
                $unit = self::numerical_unit($question);
                if ($unit !== '') {
                    $input['unit'] = $unit;
                }
                if (self::unit_is_graded($question)) {
                    // The unit counts for the grade, so the learner has to type it: ask them, never add it.
                    $input['unit_required'] = true;
                    $default = trim((string) $question->ap->get_default_unit());
                    if ($default !== '') {
                        $input['default_unit'] = $default;
                    }
                }
                if (isset($response['answer']) && (string) $response['answer'] !== '') {
                    $value = (string) $response['answer'];
                    if ($unit !== '' && str_ends_with($value, ' ' . $unit)) {
                        $value = substr($value, 0, -strlen(' ' . $unit));
                    }
                    $input['value'] = $value;
                }
                return $input;

            case 'match':
                $stems = [];
                $values = [];
                foreach ($question->get_stem_order() as $key => $stemid) {
                    $stems[] = [
                        'key' => (string) $stemid,
                        'label' => self::plain($question->format_text(
                            $question->stems[$stemid],
                            $question->stemformat[$stemid],
                            $qa,
                            'qtype_match',
                            'subquestion',
                            $stemid
                        )),
                    ];
                    $choicekey = (int) ($response['sub' . $key] ?? 0);
                    $choiceorder = $question->get_choice_order();
                    if ($choicekey > 0 && isset($choiceorder[$choicekey])) {
                        $values[(string) $stemid] = (string) $choiceorder[$choicekey];
                    }
                }
                $options = [];
                foreach ($question->get_choice_order() as $choiceid) {
                    $options[] = [
                        'value' => (string) $choiceid,
                        'label' => self::plain((string) $question->choices[$choiceid]),
                    ];
                }
                // An empty map must stay a JSON object.
                return ['kind' => 'match', 'stems' => $stems, 'options' => $options, 'values' => (object) $values];
        }

        return [
            'kind' => 'unsupported',
            'fallback_url' => $attemptobj->attempt_url($slot, $attemptobj->get_question_page($slot))->out(false),
        ];
    }

    /**
     * Turn one app answer into question-type response variables.
     *
     * @param question_attempt $qa Question attempt.
     * @param array<string, mixed> $answer One entry of the answers argument.
     * @return array<string, string> Response variables without the field prefix.
     * @throws tool_exception On a value that does not fit the question.
     */
    public static function response_for(question_attempt $qa, array $answer): array {
        $question = $qa->get_question();
        $slot = (int) $qa->get_slot();
        $value = $answer['value'] ?? null;
        $values = $answer['values'] ?? null;
        $invalid = static function (string $why) use ($slot): tool_exception {
            return new tool_exception(
                "Answer for slot {$slot} is invalid: {$why}",
                ['slot' => $slot, 'errorcode' => 'invalid_answer']
            );
        };

        switch ($question->get_type_name()) {
            case 'truefalse':
                if (!is_scalar($value)) {
                    throw $invalid('use value "1" (true) or "0" (false).');
                }
                $value = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
                if ($value !== '1' && $value !== '0') {
                    throw $invalid('use value "1" (true) or "0" (false).');
                }
                return ['answer' => $value];

            case 'multichoice':
                $order = array_map('strval', $question->get_order($qa));
                if ($question instanceof \qtype_multichoice_single_question) {
                    $index = is_scalar($value) ? array_search((string) $value, $order, true) : false;
                    if ($index === false) {
                        throw $invalid('value must be one of the option values.');
                    }
                    return ['answer' => (string) $index];
                }
                if (!is_array($values)) {
                    $values = is_array($value) ? $value : null;
                }
                if (!is_array($values)) {
                    throw $invalid('values must be a list of option values.');
                }
                $chosen = array_map('strval', array_values($values));
                if (array_diff($chosen, $order)) {
                    throw $invalid('values may only contain option values.');
                }
                $response = [];
                foreach ($order as $index => $answerid) {
                    // Unticked boxes are sent as 0 so a re-save can clear them.
                    $response['choice' . $index] = in_array($answerid, $chosen, true) ? '1' : '0';
                }
                return $response;

            case 'shortanswer':
                if (!is_scalar($value)) {
                    throw $invalid('value must be text.');
                }
                return ['answer' => trim((string) $value)];

            case 'essay':
            case 'aitext':
                if (!is_scalar($value)) {
                    throw $invalid('value must be text.');
                }
                return ['answer' => (string) $value, 'answerformat' => (string) FORMAT_PLAIN];

            case 'numerical':
                if (!is_scalar($value)) {
                    throw $invalid('value must be a number.');
                }
                $text = trim((string) $value);
                $unit = self::numerical_unit($question);
                if ($text !== '' && $unit !== '' && is_numeric(str_replace(',', '.', $text))) {
                    // The app shows the unit next to the field, so the learner enters the number only.
                    $text .= ' ' . $unit;
                }
                return ['answer' => $text];

            case 'match':
                if (!is_array($values)) {
                    $values = is_array($value) ? $value : null;
                }
                if (!is_array($values)) {
                    throw $invalid('values must map stem keys to option values.');
                }
                $stemorder = array_map('strval', $question->get_stem_order());
                $choicekeys = array_flip(array_map('strval', $question->get_choice_order()));
                // Start from the saved response so a partial map keeps the other stems.
                $response = [];
                foreach ($stemorder as $key => $notused) {
                    $response['sub' . $key] = (string) (int) $qa->get_last_qt_var('sub' . $key, 0);
                }
                foreach ($values as $stemid => $choiceid) {
                    $key = array_search((string) $stemid, $stemorder, true);
                    if ($key === false) {
                        throw $invalid("unknown stem key {$stemid}.");
                    }
                    if (!is_scalar($choiceid) || !isset($choicekeys[(string) $choiceid])) {
                        throw $invalid("unknown option value for stem {$stemid}.");
                    }
                    $response['sub' . $key] = (string) $choicekeys[(string) $choiceid];
                }
                return $response;
        }

        throw $invalid('this question type cannot be answered in the app.');
    }

    /**
     * Whether the question, its options or its stems contain images, audio, video or other embedded media.
     *
     * The tools describe questions as plain text, where such media is lost.
     *
     * @param \question_attempt $qa Question attempt.
     * @return bool
     */
    public static function question_has_media(\question_attempt $qa): bool {
        $question = $qa->get_question();
        $html = [(string) $question->format_questiontext($qa)];
        foreach ((array) ($question->answers ?? []) as $answer) {
            if (isset($answer->answer)) {
                $html[] = (string) $answer->answer;
            }
        }
        foreach ((array) ($question->stems ?? []) as $stem) {
            $html[] = (string) $stem;
        }
        return (bool) preg_match('/<(img|video|audio|iframe|object|embed|svg)\b/i', implode(' ', $html));
    }

    /**
     * Whether the quiz has a "Check" step (hints and feedback while answering) that only the browser offers.
     *
     * @param \stdClass $quiz Quiz record.
     * @return bool
     */
    public static function has_check_step(\stdClass $quiz): bool {
        return in_array((string) ($quiz->preferredbehaviour ?? ''), self::CHECK_BEHAVIOURS, true);
    }

    /**
     * The unit the app shows next to a numerical answer and adds to it, or ''.
     *
     * Empty when the unit is graded: then the learner has to type it themselves.
     *
     * @param \question_definition $question Numerical question.
     * @return string
     */
    private static function numerical_unit(\question_definition $question): string {
        if (!isset($question->ap) || (int) $question->unitdisplay !== \qtype_numerical::UNITINPUT) {
            return '';
        }
        if (self::unit_is_graded($question)) {
            // Adding the unit for the learner would hand them the unit part of the grade.
            return '';
        }
        return trim((string) $question->ap->get_default_unit());
    }

    /**
     * Whether the unit typed with the answer counts for the grade (penalty for a missing or wrong unit).
     *
     * @param \question_definition $question Numerical question.
     * @return bool
     */
    private static function unit_is_graded(\question_definition $question): bool {
        return isset($question->ap)
            && (int) $question->unitdisplay === \qtype_numerical::UNITINPUT
            && (int) ($question->unitgradingtype ?? 0) !== 0;
    }

    /**
     * Quiz grade of an attempt, when the review options let the user see it.
     *
     * @param quiz_attempt $attemptobj Finished attempt.
     * @param question_display_options $options Review options.
     * @return float|null
     */
    public static function visible_grade(quiz_attempt $attemptobj, question_display_options $options): ?float {
        $attempt = $attemptobj->get_attempt();
        if (
            $attempt->state !== quiz_attempt::FINISHED
                || $options->marks < question_display_options::MARK_AND_MAX
                || $attempt->sumgrades === null
        ) {
            return null;
        }
        $grade = quiz_rescale_grade($attempt->sumgrades, $attemptobj->get_quiz(), false);
        return $grade === null ? null : round((float) $grade, 2);
    }

    /**
     * Whether the user may open the review of a finished attempt now.
     *
     * @param quiz_attempt $attemptobj Finished attempt.
     * @return bool
     */
    public static function review_available(quiz_attempt $attemptobj): bool {
        if (!$attemptobj->is_finished()) {
            return false;
        }
        try {
            $attemptobj->check_review_capability();
        } catch (\required_capability_exception $ex) {
            return false;
        }
        return (bool) $attemptobj->get_display_options(true)->attempt;
    }

    /**
     * Turn access-rule messages into one tool exception.
     *
     * @param string[] $messages Messages from the access manager.
     * @param quiz_settings $quizobj Quiz.
     * @return tool_exception
     */
    public static function access_denied(array $messages, quiz_settings $quizobj): tool_exception {
        $text = implode(' ', array_map(static fn($m): string => self::plain((string) $m), $messages));
        return new tool_exception(
            'You cannot attempt this quiz now: ' . $text,
            [
                'messages' => array_values(array_map(static fn($m): string => self::plain((string) $m), $messages)),
                'fallback_url' => $quizobj->view_url()->out(false),
                'errorcode' => 'quiz_access_denied',
            ]
        );
    }

    /**
     * Wrap an unexpected quiz exception in an LLM-actionable tool exception.
     *
     * @param moodle_exception $ex Exception from mod_quiz or the question engine.
     * @param int $attemptid Attempt id.
     * @return tool_exception
     */
    public static function map_exception(moodle_exception $ex, int $attemptid): tool_exception {
        if (
            $ex instanceof \question_out_of_sequence_exception
                || $ex->errorcode === 'submissionoutofsequencefriendlymessage'
        ) {
            return new tool_exception(
                'The attempt was changed elsewhere (for example in the browser). Load the page again and retry.',
                ['attempt_id' => $attemptid, 'errorcode' => 'out_of_sequence']
            );
        }
        // Only messages of the quiz's own errors are passed on; those of other exceptions can carry
        // class names or internals.
        if (in_array((string) $ex->errorcode, self::PASS_ON_ERRORCODES, true)) {
            return new tool_exception(
                'The quiz could not process the request: ' . $ex->getMessage(),
                ['attempt_id' => $attemptid, 'errorcode' => (string) $ex->errorcode]
            );
        }
        return new tool_exception(
            'The quiz could not process the request (error ' . $ex->errorcode . ').',
            ['attempt_id' => $attemptid, 'errorcode' => 'quiz_error']
        );
    }

    /**
     * Rendered HTML to plain text with simple line breaks.
     *
     * @param string $html HTML.
     * @return string
     */
    public static function plain(string $html): string {
        $text = html_to_text($html, 0, false);
        $text = preg_replace("/[ \t]+\n/", "\n", $text);
        $text = preg_replace("/\n{3,}/", "\n\n", (string) $text);
        return trim((string) $text);
    }

    /**
     * Quiz view page, where the browser can take over.
     *
     * @param int $cmid Course module id.
     * @return string
     */
    public static function view_url(int $cmid): string {
        return (new moodle_url('/mod/quiz/view.php', ['id' => $cmid]))->out(false);
    }

    /**
     * Load mod_quiz's procedural library.
     *
     * @return void
     */
    private static function require_quiz_lib(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
    }

    /**
     * The quiz API acts on $USER, so the tools refuse to act for anybody else.
     *
     * @param stdClass $user Authenticated user handed to the tool.
     * @return void
     * @throws tool_exception When $user is not the current user.
     */
    private static function require_current_user(stdClass $user): void {
        global $USER;
        if ((int) ($USER->id ?? 0) !== (int) $user->id || isguestuser($user)) {
            throw new tool_exception(
                'Quiz attempts can only be made by the signed-in user.',
                ['errorcode' => 'not_current_user']
            );
        }
    }

    /**
     * Check the user can see the course module (active enrolment, visibility, availability).
     *
     * @param int $courseid Course id.
     * @param int $cmid Course module id.
     * @param stdClass $user User.
     * @param tool_exception $notfound Exception to throw otherwise.
     * @return void
     */
    private static function require_cm_visible(int $courseid, int $cmid, stdClass $user, tool_exception $notfound): void {
        $course = get_course($courseid);
        if (!is_siteadmin($user) && !can_access_course($course, $user, '', true)) {
            throw $notfound;
        }
        $cm = get_fast_modinfo($course, (int) $user->id)->cms[$cmid] ?? null;
        if (!$cm || !$cm->uservisible) {
            throw $notfound;
        }
        // Make sure the module context exists before the quiz API asks for it.
        context_module::instance($cmid);
    }
}

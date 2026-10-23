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
use context_system;
use grade_item;
use mod_quiz\quiz_attempt;
use moodle_url;
use question_display_options;
use stdClass;
use webservice_elediamcp\local\ai\ai_tool;
use webservice_elediamcp\local\ai\response_budget;
use webservice_elediamcp\local\ai\tool_exception;

/**
 * AI-native quiz overview with the user's own attempt history.
 *
 * Lists quizzes visible to the authenticated user across their enrolled
 * courses with timing, attempt limits and the user's own best grade; for a
 * single quiz (cmid) it additionally returns the user's attempt history.
 * Only ever exposes the authenticated user's own attempts and grades.
 * Designed for "how did my quiz go?", "which quizzes are still open?" and
 * revision-planning queries.
 *
 * @package     webservice_elediamcp
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moodle_quiz_info implements ai_tool {
    /** @var int Default page size. */
    private const DEFAULT_LIMIT = 30;

    /** @var int Maximum page size. */
    private const MAX_LIMIT = 100;

    /** @var array<int, string> Quiz grade-method constants to labels. */
    private const GRADE_METHODS = [
        1 => 'highest',
        2 => 'average',
        3 => 'first',
        4 => 'last',
    ];

    /**
     * Tool name.
     *
     * @return string
     */
    public static function name(): string {
        return 'moodle_quiz_info';
    }

    /**
     * Tool title.
     *
     * @return string
     */
    public static function title(): string {
        return 'Quizzes and my attempt history';
    }

    /**
     * Tool description.
     *
     * @return string
     */
    public static function description(): string {
        return 'Returns quizzes visible to the authenticated user across their enrolled courses, '
            . 'each with open/close dates, time limit, allowed attempts, grading method and the '
            . 'user\'s own attempt count and best grade. Pass cmid for a single quiz to also get '
            . 'the user\'s attempt history (state, started/finished, duration, grade per attempt). '
            . 'Never exposes other users\' attempts. Use to answer "which quizzes are open?", '
            . '"how did my last attempt go?" and to plan revision. my_grade and attempt grades are null '
            . 'while Moodle does not show them to the user: grade_withheld is true when a value exists but '
            . 'is not released yet, and false when there is no value yet (also for an attempt in progress). '
            . 'Never report a withheld value as "no grade" or as 0. ' . response_budget::DESCRIPTION_SENTENCE;
    }

    /**
     * Input schema.
     *
     * @return array<string, mixed>
     */
    public static function input_schema(): array {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'course_id' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'description' => 'Restrict to a single course id.',
                ],
                'cmid' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'description' => 'Course module id of one quiz: returns its detail incl. my attempts.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => self::MAX_LIMIT,
                    'default' => self::DEFAULT_LIMIT,
                ],
                'offset' => [
                    'type' => 'integer',
                    'minimum' => 0,
                    'default' => 0,
                ],
            ],
        ];
    }

    /**
     * Output schema.
     *
     * @return array<string, mixed>
     */
    public static function output_schema(): array {
        return [
            'type' => 'object',
            'required' => ['quizzes', 'total', 'has_more', 'next_offset', 'summary'],
            'properties' => [
                'quizzes' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['cmid', 'quiz_id', 'name', 'course_id', 'url', 'grade_withheld'],
                        'properties' => [
                            'cmid' => ['type' => 'integer'],
                            'quiz_id' => ['type' => 'integer'],
                            'name' => ['type' => 'string'],
                            'course_id' => ['type' => 'integer'],
                            'course_name' => ['type' => 'string'],
                            'timeopen_iso' => ['type' => ['string', 'null']],
                            'timeclose_iso' => ['type' => ['string', 'null']],
                            'is_open_now' => ['type' => 'boolean'],
                            'timelimit_seconds' => ['type' => ['integer', 'null']],
                            'attempts_allowed' => ['type' => 'integer',
                                'description' => '0 = unlimited'],
                            'grade_method' => ['type' => 'string',
                                'enum' => array_values(self::GRADE_METHODS)],
                            'my_attempt_count' => ['type' => 'integer'],
                            'my_grade' => ['type' => ['number', 'null'],
                                'description' => 'null when there is no grade or it is not released to the user yet'],
                            'grade_withheld' => ['type' => 'boolean',
                                'description' => 'true: a grade exists but Moodle does not show it to the user yet '
                                    . '(quiz review options or hidden in the gradebook); my_grade is then null. '
                                    . 'false with my_grade null: no grade yet.'],
                            'grade_max' => ['type' => ['number', 'null']],
                            'url' => ['type' => 'string'],
                        ],
                    ],
                ],
                'attempts' => [
                    'type' => 'array',
                    'description' => 'The user\'s own attempts; only when cmid was given.',
                    'items' => [
                        'type' => 'object',
                        'required' => ['attempt', 'state', 'grade_withheld'],
                        'properties' => [
                            'attempt' => ['type' => 'integer'],
                            'state' => ['type' => 'string'],
                            'started_iso' => ['type' => ['string', 'null']],
                            'finished_iso' => ['type' => ['string', 'null']],
                            'duration_seconds' => ['type' => ['integer', 'null']],
                            'grade' => ['type' => ['number', 'null'],
                                'description' => 'null when not graded or the marks are not released yet'],
                            'grade_withheld' => ['type' => 'boolean',
                                'description' => 'true: the attempt has marks but the quiz review options do not '
                                    . 'show them to the user yet; grade is then null. false with grade null: not '
                                    . 'graded yet or the attempt is still in progress.'],
                            'grade_max' => ['type' => ['number', 'null']],
                        ],
                    ],
                ],
                'total' => ['type' => 'integer'],
                'limit' => ['type' => 'integer'],
                'offset' => ['type' => 'integer'],
                'has_more' => ['type' => 'boolean'],
                'next_offset' => [
                    'type' => ['integer', 'null'],
                    'description' => 'offset for the next call while has_more is true, else null',
                ],
                'summary' => ['type' => 'string'],
            ],
        ];
    }

    /**
     * Tool annotations.
     *
     * @return array<string, mixed>
     */
    public static function annotations(): array {
        return [
            'title' => self::title(),
            'readOnlyHint' => true,
            'destructiveHint' => false,
            'idempotentHint' => true,
            'openWorldHint' => false,
        ];
    }

    /**
     * Execute the tool.
     *
     * @param array<string, mixed> $arguments Validated arguments.
     * @param stdClass $user Authenticated user record.
     * @return array<string, mixed>
     */
    public static function execute(array $arguments, stdClass $user): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        require_once($CFG->libdir . '/gradelib.php');

        $userid = (int) $user->id;
        $courseidfilter = isset($arguments['course_id']) ? (int) $arguments['course_id'] : 0;
        $cmidfilter = isset($arguments['cmid']) ? (int) $arguments['cmid'] : 0;
        $limit = isset($arguments['limit'])
            ? max(1, min(self::MAX_LIMIT, (int) $arguments['limit']))
            : self::DEFAULT_LIMIT;
        $offset = isset($arguments['offset']) ? max(0, (int) $arguments['offset']) : 0;

        $usercourses = enrol_get_users_courses($userid, true, ['id', 'fullname']);
        $coursemap = [];
        foreach ($usercourses as $c) {
            $coursemap[(int) $c->id] = (string) $c->fullname;
        }
        if ($courseidfilter > 0) {
            if (!isset($coursemap[$courseidfilter])) {
                throw new tool_exception(
                    "Course {$courseidfilter} is not available to you.",
                    ['course_id' => $courseidfilter]
                );
            }
            $coursemap = [$courseidfilter => $coursemap[$courseidfilter]];
        }
        if (empty($coursemap)) {
            return self::empty_payload($limit);
        }

        // The full quiz record is needed: the review options (review* fields) decide
        // whether marks may be shown, and the overrides replace the timing fields.
        [$insql, $params] = $DB->get_in_or_equal(array_keys($coursemap), SQL_PARAMS_NAMED, 'cid');
        $sql = "SELECT q.*, cm.id AS cmid
                  FROM {quiz} q
                  JOIN {course_modules} cm ON cm.instance = q.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                 WHERE q.course $insql
              ORDER BY COALESCE(NULLIF(q.timeclose, 0), q.timeopen, q.id) ASC, q.id ASC";
        $rows = $DB->get_records_sql($sql, $params);

        if ($cmidfilter > 0) {
            $rows = array_filter($rows, static fn($r) => (int) $r->cmid === $cmidfilter);
            if (empty($rows)) {
                throw new tool_exception(
                    "No quiz with cmid {$cmidfilter} is visible to you.",
                    ['cmid' => $cmidfilter]
                );
            }
        }

        // The user's own non-preview attempts for all candidate quizzes, in one query.
        $attemptsbyquiz = [];
        if (!empty($rows)) {
            [$qinsql, $qparams] = $DB->get_in_or_equal(array_keys($rows), SQL_PARAMS_NAMED, 'qid');
            $qparams['userid'] = $userid;
            $attemptrecords = $DB->get_records_select(
                'quiz_attempts',
                "quiz $qinsql AND userid = :userid AND preview = 0",
                $qparams,
                'quiz ASC, attempt ASC'
            );
            foreach ($attemptrecords as $attemptrecord) {
                $attemptsbyquiz[(int) $attemptrecord->quiz][(int) $attemptrecord->id] = $attemptrecord;
            }
        }

        $now = time();
        $stringopts = ['context' => context_system::instance(), 'filter' => false, 'escape' => true];
        $entries = [];
        $attempts = [];
        $withheld = false;

        foreach ($rows as $r) {
            $cmid = (int) $r->cmid;
            $course = get_course((int) $r->course);
            // Only active enrolments count, as in require_login() when entering the course.
            if (!is_siteadmin($user) && !can_access_course($course, $user, '', true)) {
                if ($cmidfilter > 0) {
                    throw new tool_exception(
                        "No quiz with cmid {$cmidfilter} is visible to you.",
                        ['cmid' => $cmidfilter]
                    );
                }
                continue;
            }
            $modinfo = get_fast_modinfo((int) $r->course, $userid);
            $cm = $modinfo->cms[$cmid] ?? null;
            if (!$cm || !$cm->uservisible) {
                if ($cmidfilter > 0) {
                    throw new tool_exception(
                        "No quiz with cmid {$cmidfilter} is visible to you.",
                        ['cmid' => $cmidfilter]
                    );
                }
                continue;
            }

            // Apply the user and group overrides (dates, time limit, attempts) as
            // mod/quiz/view.php does, so the entry shows this person's values.
            $quiz = quiz_update_effective_access(clone $r, $userid);
            $context = context_module::instance($cmid);

            $timeopen = (int) ($quiz->timeopen ?? 0);
            $timeclose = (int) ($quiz->timeclose ?? 0);
            $grademax = (float) ($quiz->grade ?? 0);

            // Only the calling user's own attempts and final grade, ever.
            $attemptrows = $attemptsbyquiz[(int) $quiz->id] ?? [];

            $finalgrade = self::visible_final_grade($quiz, $attemptrows, $context, $userid);
            if ($finalgrade['withheld']) {
                $withheld = true;
            }

            $entries[] = [
                'cmid' => $cmid,
                'quiz_id' => (int) $quiz->id,
                'name' => format_string((string) $quiz->name, true, $stringopts),
                'course_id' => (int) $quiz->course,
                'course_name' => format_string($coursemap[(int) $quiz->course] ?? '', true, $stringopts),
                'timeopen_iso' => $timeopen > 0 ? gmdate('c', $timeopen) : null,
                'timeclose_iso' => $timeclose > 0 ? gmdate('c', $timeclose) : null,
                'is_open_now' => ($timeopen === 0 || $timeopen <= $now)
                    && ($timeclose === 0 || $timeclose > $now),
                'timelimit_seconds' => !empty($quiz->timelimit) ? (int) $quiz->timelimit : null,
                'attempts_allowed' => (int) ($quiz->attempts ?? 0),
                'grade_method' => self::GRADE_METHODS[(int) ($quiz->grademethod ?? 1)] ?? 'highest',
                'my_attempt_count' => count($attemptrows),
                'my_grade' => $finalgrade['grade'],
                'grade_withheld' => $finalgrade['withheld'],
                'grade_max' => $grademax > 0 ? $grademax : null,
                'url' => (new moodle_url('/mod/quiz/view.php', ['id' => $cmid]))->out(false),
            ];

            if ($cmidfilter > 0) {
                $attempts = self::attempt_history($quiz, $attemptrows, $context, $withheld);
            }
        }

        $total = count($entries);
        $summary = self::build_summary($total, $cmidfilter, $attempts, $withheld);

        // The page is as large as the limit allows and the answer still fits
        // into the character budget (response_budget); the attempt history
        // counts towards the answer.
        $payload = response_budget::fit_page(
            array_slice($entries, $offset, $limit),
            static function (array $page) use ($total, $limit, $offset, $summary, $cmidfilter, $attempts): array {
                $paging = response_budget::paging($offset, count($page), $total);
                $answer = [
                    'quizzes' => $page,
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_more' => $paging['has_more'],
                    'next_offset' => $paging['next_offset'],
                    'summary' => response_budget::summary($summary, $paging['has_more']),
                ];
                if ($cmidfilter > 0) {
                    $answer['attempts'] = $attempts;
                }
                return $answer;
            }
        );
        return $payload;
    }

    /**
     * Whether the quiz review options let this user see the marks of one attempt now.
     *
     * Uses quiz_get_review_options(), which also grants full marks to users who can
     * see quiz reports and hidden grades (mod/quiz:viewreports, moodle/grade:viewhidden).
     *
     * @param stdClass $quiz Quiz record with overrides applied.
     * @param stdClass $attempt quiz_attempts row.
     * @param context_module $context Quiz context.
     * @return bool
     */
    private static function marks_visible(stdClass $quiz, stdClass $attempt, context_module $context): bool {
        $options = quiz_get_review_options($quiz, $attempt, $context);
        return $options->marks >= question_display_options::MARK_AND_MAX;
    }

    /**
     * The user's overall quiz grade, only when Moodle shows it on the quiz page.
     *
     * Follows mod/quiz/view.php: the value comes from the gradebook when the quiz has
     * a grade item (it may be overridden there), otherwise from quiz_grades. It is
     * shown only when there is a finished attempt and the marks are visible for every
     * attempt (overallstats), and only when the grade is not hidden in the gradebook,
     * unless the user has moodle/grade:viewhidden.
     *
     * Differences to the quiz page, both on the safe side or covered by Moodle:
     * - Users with mod/quiz:viewreports and moodle/grade:viewhidden get the grade even when
     *   the review options hide marks, because quiz_get_review_options() shows them their
     *   marks in the attempt table and the attempt review as well.
     * - The quiz page first closes attempts whose time has run out
     *   (handle_if_time_expired()). This read-only tool does not write, so such an attempt
     *   stays "in progress" until Moodle (page view or cron) processes it, and the grade is
     *   withheld until then.
     *
     * @param stdClass $quiz Quiz record with overrides applied.
     * @param array $attemptrows The user's own non-preview attempts.
     * @param context_module $context Quiz context.
     * @param int $userid The user.
     * @return array{grade: ?float, withheld: bool}
     */
    private static function visible_final_grade(
        stdClass $quiz,
        array $attemptrows,
        context_module $context,
        int $userid
    ): array {
        $value = null;
        $hidden = false;
        $gradeitem = grade_item::fetch([
            'itemtype' => 'mod',
            'itemmodule' => 'quiz',
            'iteminstance' => (int) $quiz->id,
            'itemnumber' => 0,
            'courseid' => (int) $quiz->course,
        ]);
        if ($gradeitem) {
            // Without a database row this is an empty grade_grade (finalgrade null);
            // is_hidden() still includes the hidden state of the grade item.
            $grade = $gradeitem->get_grade($userid, false);
            if ($grade->finalgrade !== null) {
                $value = (float) $grade->finalgrade;
            }
            $hidden = (bool) $grade->is_hidden();
        } else {
            $best = quiz_get_best_grade($quiz, $userid);
            if ($best !== null) {
                $value = (float) $best;
            }
        }
        if ($value === null) {
            return ['grade' => null, 'withheld' => false];
        }

        $hasfinished = false;
        $allmarks = !empty($attemptrows);
        foreach ($attemptrows as $attempt) {
            // Same states as quiz_get_user_attempts(..., 'finished') in view.php; 'submitted'
            // exists from Moodle 5.0 on and is spelled out because the constant is missing in 4.5.
            if (in_array($attempt->state, [quiz_attempt::FINISHED, quiz_attempt::ABANDONED, 'submitted'], true)) {
                $hasfinished = true;
            }
            if (!self::marks_visible($quiz, $attempt, $context)) {
                $allmarks = false;
            }
        }
        $visible = $hasfinished && $allmarks;
        if ($visible && $hidden) {
            $coursecontext = $context->get_course_context();
            $visible = has_capability('moodle/grade:viewhidden', $coursecontext, $userid);
        }
        return $visible
            ? ['grade' => round($value, 2), 'withheld' => false]
            : ['grade' => null, 'withheld' => true];
    }

    /**
     * The calling user's own attempt history for one quiz.
     *
     * The grade of an attempt is only included when the attempt is finished and the quiz
     * review options show the marks for that attempt at this moment, like the attempt
     * table on mod/quiz/view.php (quiz_attempt::is_finished() and marks >= MARK_AND_MAX).
     *
     * @param stdClass $quiz Quiz record with overrides applied.
     * @param array $rows The user's own non-preview attempts, ordered by attempt.
     * @param context_module $context Quiz context.
     * @param bool $withheld Set to true when a grade exists but is not shown.
     * @return array<int, array<string, mixed>>
     */
    private static function attempt_history(stdClass $quiz, array $rows, context_module $context, bool &$withheld): array {
        $sumgrades = (float) ($quiz->sumgrades ?? 0);
        $grademax = (float) ($quiz->grade ?? 0);

        $attempts = [];
        foreach ($rows as $row) {
            $started = (int) ($row->timestart ?? 0);
            $finished = (int) ($row->timefinish ?? 0);
            $grade = null;
            $gradewithheld = false;
            $isfinished = in_array($row->state, [quiz_attempt::FINISHED, quiz_attempt::ABANDONED], true);
            if ($isfinished && $row->sumgrades !== null && $sumgrades > 0 && $grademax > 0) {
                if (self::marks_visible($quiz, $row, $context)) {
                    $grade = round((float) $row->sumgrades / $sumgrades * $grademax, 2);
                } else {
                    $gradewithheld = true;
                    $withheld = true;
                }
            }
            $attempts[] = [
                'attempt' => (int) $row->attempt,
                'state' => (string) $row->state,
                'started_iso' => $started > 0 ? gmdate('c', $started) : null,
                'finished_iso' => $finished > 0 ? gmdate('c', $finished) : null,
                'duration_seconds' => ($started > 0 && $finished > 0) ? ($finished - $started) : null,
                'grade' => $grade,
                'grade_withheld' => $gradewithheld,
                'grade_max' => $grademax > 0 ? $grademax : null,
            ];
        }
        return $attempts;
    }

    /**
     * Empty payload helper.
     *
     * @param int $limit Page size.
     * @return array<string, mixed>
     */
    private static function empty_payload(int $limit): array {
        return [
            'quizzes' => [],
            'total' => 0,
            'limit' => $limit,
            'offset' => 0,
            'has_more' => false,
            'next_offset' => null,
            'summary' => 'No quizzes found.',
        ];
    }

    /**
     * Build a one-sentence summary for the LLM.
     *
     * @param int $total Matching quizzes.
     * @param int $cmidfilter Single-quiz filter (0 = none).
     * @param array $attempts Attempt history (detail mode).
     * @param bool $withheld Whether a grade or attempt mark exists but is not released yet.
     * @return string
     */
    private static function build_summary(int $total, int $cmidfilter, array $attempts, bool $withheld = false): string {
        if ($total === 0) {
            return 'No quizzes found.';
        }
        if ($cmidfilter > 0) {
            $n = count($attempts);
            $summary = $n === 0
                ? 'Quiz found; you have not attempted it yet.'
                : sprintf('Quiz found with %d attempt(s) of yours.', $n);
        } else {
            $summary = sprintf('Found %d quiz(zes) across your courses.', $total);
        }
        if ($withheld) {
            $summary .= ' Some quiz results are not shown to you yet (null values mean "not released", not "no grade").';
        }
        return $summary;
    }
}

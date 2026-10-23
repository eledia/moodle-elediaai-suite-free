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

use assign;
use grade_grade;
use grade_item;
use context_system;
use moodle_url;
use stdClass;
use webservice_elediamcp\local\ai\ai_tool;
use webservice_elediamcp\local\ai\response_budget;
use webservice_elediamcp\local\ai\tool_exception;

/**
 * AI-native assignment overview.
 *
 * Lists all assignments visible to the authenticated user across the
 * courses they are enrolled in, with derived status
 * (notsubmitted / submitted / graded), due / cut-off dates and an
 * overdue flag. Designed for "what is due soon?" and "what have I not
 * submitted yet?" agent queries.
 *
 * @package     webservice_elediamcp
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moodle_my_assignments implements ai_tool {
    /** @var int Default page size. */
    private const DEFAULT_LIMIT = 30;

    /** @var int Maximum page size. */
    private const MAX_LIMIT = 100;

    /** @var string[] Allowed status filter values. */
    private const STATUS_VALUES = ['all', 'notsubmitted', 'submitted', 'graded', 'overdue', 'upcoming'];

    /**
     * Tool name.
     *
     * @return string
     */
    public static function name(): string {
        return 'moodle_my_assignments';
    }

    /**
     * Tool title.
     *
     * @return string
     */
    public static function title(): string {
        return 'My assignments and submission status';
    }

    /**
     * Tool description.
     *
     * @return string
     */
    public static function description(): string {
        return 'Returns assignments visible to the authenticated user across their enrolled '
            . 'courses, each with name, due date, cut-off date, derived status (notsubmitted, '
            . 'submitted, graded), grade (when graded) and an overdue flag. Filter with '
            . 'status (notsubmitted, submitted, graded, overdue, upcoming) or course_id. Use to '
            . 'answer "what is due soon?" and "what have I not submitted yet?". grade is null while '
            . 'the grade is not shown to the user; grade_withheld is true when a grade exists but must '
            . 'not be shown yet and false when there is no grade yet, so never report a withheld grade '
            . 'as "no grade" or as 0. ' . response_budget::DESCRIPTION_SENTENCE;
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
                'status' => [
                    'type' => 'string',
                    'enum' => self::STATUS_VALUES,
                    'default' => 'all',
                    'description' => 'Filter by derived status (default all).',
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
            'required' => ['assignments', 'total', 'has_more', 'next_offset', 'summary'],
            'properties' => [
                'assignments' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['cmid', 'assign_id', 'name', 'course_id', 'status', 'grade_withheld', 'url'],
                        'properties' => [
                            'cmid' => ['type' => 'integer'],
                            'assign_id' => ['type' => 'integer'],
                            'name' => ['type' => 'string'],
                            'course_id' => ['type' => 'integer'],
                            'course_name' => ['type' => 'string'],
                            'duedate_iso' => ['type' => ['string', 'null']],
                            'extensionduedate_iso' => ['type' => ['string', 'null']],
                            'cutoffdate_iso' => ['type' => ['string', 'null']],
                            'allowsubmissionsfromdate_iso' => ['type' => ['string', 'null']],
                            'status' => ['type' => 'string', 'enum' => ['notsubmitted', 'submitted', 'graded']],
                            'overdue' => ['type' => 'boolean'],
                            'submitted_iso' => ['type' => ['string', 'null']],
                            'grade' => ['type' => ['number', 'null']],
                            'grade_max' => ['type' => ['number', 'null']],
                            'grade_str' => ['type' => 'string'],
                            'grade_withheld' => [
                                'type' => 'boolean',
                                'description' => 'true when a grade exists that Moodle does not show to the person yet; '
                                    . 'grade is then null',
                            ],
                            'graded_iso' => ['type' => ['string', 'null']],
                            'url' => ['type' => 'string'],
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
                'status' => ['type' => 'string'],
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
        global $DB, $CFG;

        $userid = (int) $user->id;
        $courseidfilter = isset($arguments['course_id']) ? (int) $arguments['course_id'] : 0;
        $statusfilter = isset($arguments['status']) ? (string) $arguments['status'] : 'all';
        if (!in_array($statusfilter, self::STATUS_VALUES, true)) {
            throw new tool_exception(
                "Unknown status '{$statusfilter}'. Allowed: " . implode(', ', self::STATUS_VALUES) . '.',
                ['allowed' => self::STATUS_VALUES]
            );
        }
        $limit = isset($arguments['limit'])
            ? max(1, min(self::MAX_LIMIT, (int) $arguments['limit']))
            : self::DEFAULT_LIMIT;
        $offset = isset($arguments['offset']) ? max(0, (int) $arguments['offset']) : 0;

        // No response cache here: grade visibility in Moodle can change at any
        // second (hidden-until dates, marking workflow release), so every call
        // reads the current state after the access checks below.
        $usercourses = enrol_get_users_courses($userid, true, ['id', 'fullname']);
        $coursemap = [];
        foreach ($usercourses as $c) {
            $coursemap[(int) $c->id] = (string) $c->fullname;
        }
        if ($courseidfilter > 0) {
            if (!isset($coursemap[$courseidfilter]) && !is_siteadmin($user)) {
                throw new tool_exception(
                    "Course {$courseidfilter} is not available to you.",
                    ['course_id' => $courseidfilter]
                );
            }
            $coursemap = [$courseidfilter => $coursemap[$courseidfilter] ?? ('Course ' . $courseidfilter)];
        }
        if (empty($coursemap)) {
            return self::empty_payload($statusfilter);
        }

        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        require_once($CFG->libdir . '/gradelib.php');

        [$insql, $params] = $DB->get_in_or_equal(array_keys($coursemap), SQL_PARAMS_NAMED, 'cid');
        $sql = "SELECT a.id AS assignid, a.name, a.course AS courseid,
                       a.duedate, a.cutoffdate, a.allowsubmissionsfromdate,
                       a.grade AS grademax, a.markingworkflow, a.teamsubmission,
                       cm.id AS cmid, cm.visible AS cmvisible
                  FROM {assign} a
                  JOIN {course_modules} cm ON cm.instance = a.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
                 WHERE a.course $insql
              ORDER BY COALESCE(NULLIF(a.duedate, 0), a.allowsubmissionsfromdate, a.id) ASC, a.id ASC";
        $rows = $DB->get_records_sql($sql, $params);

        // Keep only assignments the user can see, then load everything else
        // for all of them at once, so the cost does not grow per assignment.
        $visible = [];
        foreach ($rows as $r) {
            $modinfo = get_fast_modinfo((int) $r->courseid, $userid);
            $cm = $modinfo->cms[(int) $r->cmid] ?? null;
            if ($cm && $cm->uservisible) {
                $visible[(int) $r->assignid] = [$r, $cm, $modinfo->get_course()];
            }
        }
        if (empty($visible)) {
            return self::empty_payload($statusfilter);
        }
        $visiblecourses = array_values(array_unique(array_map(static fn(array $v): int => (int) $v[0]->courseid, $visible)));
        $data = self::load_user_data(array_keys($visible), $visiblecourses, $userid);

        $now = time();
        $stringopts = ['context' => context_system::instance(), 'filter' => false, 'escape' => true];
        $entries = [];
        $sortkeys = [];
        $withheldcount = 0;
        $startoffsets = [];

        foreach ($visible as $assignid => [$r, $cm, $course]) {
            $cmid = (int) $r->cmid;
            $courseid = (int) $r->courseid;
            $assign = null;
            if (!empty($r->teamsubmission) || isset($data['overridden'][$assignid])) {
                // Only these cases need the assignment API: group submissions,
                // and user or group overrides (assign::override_exists() rules).
                $assign = new assign($cm->context, $cm, $course);
            }

            // Submission (latest attempt; the group submission in team mode).
            if ($assign && !empty($r->teamsubmission)) {
                $submission = $assign->get_group_submission($userid, 0, false);
            } else {
                $submission = $data['submissions'][$assignid] ?? null;
            }
            $submittedat = null;
            $hassubmission = false;
            if ($submission && (string) $submission->status === ASSIGN_SUBMISSION_STATUS_SUBMITTED) {
                $hassubmission = true;
                $submittedat = (int) ($submission->timemodified ?? $submission->timecreated ?? 0);
            }

            // Effective dates as on the assignment page: overrides through
            // assign::update_effective_access() and get_instance($userid);
            // otherwise the relative course dates rule of
            // assign::calculate_properties() (due date + start offset).
            if ($assign && isset($data['overridden'][$assignid])) {
                $assign->update_effective_access($userid);
                $instance = $assign->get_instance($userid);
                $duedate = (int) ($instance->duedate ?? 0);
                $cutoffdate = (int) ($instance->cutoffdate ?? 0);
                $allowfrom = (int) ($instance->allowsubmissionsfromdate ?? 0);
            } else {
                $duedate = (int) ($r->duedate ?? 0);
                if ($duedate > 0) {
                    if (!isset($startoffsets[$courseid])) {
                        $startoffsets[$courseid] = self::start_offset($course, $userid);
                    }
                    $duedate += $startoffsets[$courseid];
                }
                $cutoffdate = (int) ($r->cutoffdate ?? 0);
                $allowfrom = (int) ($r->allowsubmissionsfromdate ?? 0);
            }

            // Personal extension (assign_user_flags.extensionduedate). Moodle
            // shows it next to the due date and counts the remaining time
            // against the later of both (mod_assign renderer, get_time_remaining()).
            $flags = $data['flags'][$assignid] ?? null;
            $extension = $duedate > 0 ? (int) ($flags->extensionduedate ?? 0) : 0;
            $effectivedue = $extension > 0 ? max($duedate, $extension) : $duedate;

            // Grade as Moodle shows it to this user.
            [$gradeval, $gradestr, $gradedat, $withheld] = self::visible_grade(
                $r,
                $cm,
                $userid,
                $data['grades'][$assignid] ?? null,
                $flags,
                $data['assigngrades'][$assignid] ?? null
            );

            $status = 'notsubmitted';
            if ($gradeval !== null) {
                $status = 'graded';
            } else if ($hassubmission) {
                $status = 'submitted';
            }
            $overdue = $effectivedue > 0 && $effectivedue < $now && $status === 'notsubmitted';

            if (!self::matches_status_filter($status, $overdue, $effectivedue, $now, $statusfilter)) {
                continue;
            }
            if ($withheld) {
                $withheldcount++;
            }

            $grademax = (float) ($r->grademax ?? 0);
            $sortkeys[] = $effectivedue > 0 ? $effectivedue : ($allowfrom > 0 ? $allowfrom : $assignid);
            $entries[] = [
                'cmid' => $cmid,
                'assign_id' => $assignid,
                'name' => format_string((string) $r->name, true, $stringopts),
                'course_id' => $courseid,
                'course_name' => isset($coursemap[$courseid])
                    ? format_string($coursemap[$courseid], true, $stringopts) : '',
                'duedate_iso' => $duedate > 0 ? gmdate('c', $duedate) : null,
                'extensionduedate_iso' => $extension > 0 ? gmdate('c', $extension) : null,
                'cutoffdate_iso' => $cutoffdate > 0 ? gmdate('c', $cutoffdate) : null,
                'allowsubmissionsfromdate_iso' => $allowfrom > 0 ? gmdate('c', $allowfrom) : null,
                'status' => $status,
                'overdue' => $overdue,
                'submitted_iso' => $submittedat ? gmdate('c', $submittedat) : null,
                'grade' => $gradeval,
                'grade_max' => $grademax > 0 ? $grademax : null,
                'grade_str' => $gradestr ?? self::format_grade($gradeval, $grademax),
                'grade_withheld' => $withheld,
                'graded_iso' => $gradedat ? gmdate('c', $gradedat) : null,
                'url' => (new moodle_url('/mod/assign/view.php', ['id' => $cmid]))->out(false),
            ];
        }

        // Overrides, extensions and relative dates can move a due date, so
        // order by the effective dates (same key as the SQL order).
        if ($entries) {
            // Equal dates are ordered by assignment id, so pages stay stable between calls.
            $ids = array_column($entries, 'assign_id');
            array_multisort($sortkeys, SORT_ASC, SORT_NUMERIC, $ids, SORT_ASC, SORT_NUMERIC, $entries);
        }

        $total = count($entries);

        $summary = self::build_summary($total, $statusfilter, $courseidfilter, $coursemap);
        if ($withheldcount > 0) {
            $summary .= $withheldcount === 1
                ? sprintf(
                    ' Of these %d, 1 assignment has a grade that is not released to you yet; it is listed without a grade.',
                    $total
                )
                : sprintf(
                    ' Of these %d, %d assignments have a grade that is not released to you yet; '
                        . 'they are listed without a grade.',
                    $total,
                    $withheldcount
                );
        }

        // The page is as large as the limit allows and the answer still fits
        // into the character budget (response_budget).
        return response_budget::fit_page(
            array_slice($entries, $offset, $limit),
            static function (array $page) use ($total, $limit, $offset, $statusfilter, $summary): array {
                $paging = response_budget::paging($offset, count($page), $total);
                return [
                    'assignments' => $page,
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_more' => $paging['has_more'],
                    'next_offset' => $paging['next_offset'],
                    'status' => $statusfilter,
                    'summary' => response_budget::summary($summary, $paging['has_more']),
                ];
            }
        );
    }

    /**
     * Load the user's submissions, flags, grades and overrides for many
     * assignments with a fixed number of queries.
     *
     * @param int[] $assignids Visible assignment ids.
     * @param int[] $courseids Course ids of these assignments.
     * @param int $userid User id.
     * @return array{submissions: array, flags: array, assigngrades: array, grades: array, overridden: array}
     */
    private static function load_user_data(array $assignids, array $courseids, int $userid): array {
        global $DB;

        [$ain, $aparams] = $DB->get_in_or_equal($assignids, SQL_PARAMS_NAMED, 'aid');
        $aparams['uid'] = $userid;

        // Own submissions: the latest attempt, else the most recent one.
        $submissions = [];
        $rs = $DB->get_recordset_sql(
            "SELECT * FROM {assign_submission}
              WHERE userid = :uid AND assignment $ain
           ORDER BY assignment, latest DESC, timemodified DESC, id DESC",
            $aparams
        );
        foreach ($rs as $rec) {
            $submissions[(int) $rec->assignment] ??= $rec;
        }
        $rs->close();

        // Marking workflow state and extension (assign::get_user_flags()).
        $flags = [];
        foreach ($DB->get_records_select('assign_user_flags', "userid = :uid AND assignment $ain", $aparams) as $rec) {
            $flags[(int) $rec->assignment] = $rec;
        }

        // Assignment grade of the latest attempt (assign::get_user_grade()).
        $assigngrades = [];
        $rs = $DB->get_recordset_select(
            'assign_grades',
            "userid = :uid AND assignment $ain",
            $aparams,
            'assignment, attemptnumber DESC, id DESC',
            'id, assignment, attemptnumber, grade'
        );
        foreach ($rs as $rec) {
            $assigngrades[(int) $rec->assignment] ??= $rec;
        }
        $rs->close();

        // Overrides that apply to the user: own or one of the user's groups.
        $groupids = [];
        foreach ($courseids as $courseid) {
            $groupids = array_merge($groupids, groups_get_user_groups($courseid, $userid)[0] ?? []);
        }
        $where = "assignid $ain AND userid = :uid";
        $oparams = $aparams;
        if ($groupids) {
            [$gin, $gparams] = $DB->get_in_or_equal(array_unique($groupids), SQL_PARAMS_NAMED, 'gid');
            $where = "assignid $ain AND (userid = :uid OR groupid $gin)";
            $oparams += $gparams;
        }
        $overriddenids = $DB->get_fieldset_select('assign_overrides', 'DISTINCT assignid', $where, $oparams);
        $overridden = array_flip(array_map('intval', $overriddenids));

        // Gradebook: refresh final grades where needed (as grade_get_grades()
        // does), then read the grade items and the user's grades in one go.
        [$cin, $cparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
        $stale = $DB->get_fieldset_select(
            'grade_items',
            'courseid',
            "itemtype = 'course' AND needsupdate = 1 AND courseid $cin",
            $cparams
        );
        foreach ($stale as $courseid) {
            grade_regrade_final_grades((int) $courseid);
        }
        $items = [];
        $itemrecords = $DB->get_records_select(
            'grade_items',
            "itemtype = 'mod' AND itemmodule = 'assign' AND itemnumber = 0 AND courseid $cin AND iteminstance $ain",
            $cparams + $aparams
        );
        foreach ($itemrecords as $rec) {
            $items[(int) $rec->id] = new grade_item($rec, false);
        }
        $grades = [];
        if ($items) {
            [$iin, $iparams] = $DB->get_in_or_equal(array_keys($items), SQL_PARAMS_NAMED, 'iid');
            $iparams['uid'] = $userid;
            foreach ($DB->get_records_select('grade_grades', "userid = :uid AND itemid $iin", $iparams) as $rec) {
                $gradegrade = new grade_grade($rec, false);
                $gradegrade->grade_item = $items[(int) $rec->itemid];
                $grades[(int) $gradegrade->grade_item->iteminstance] = $gradegrade;
            }
        }

        return [
            'submissions' => $submissions,
            'flags' => $flags,
            'assigngrades' => $assigngrades,
            'grades' => $grades,
            'overridden' => $overridden,
        ];
    }

    /**
     * Start offset of relative course dates for the user (0 if not used).
     *
     * Same rule as assign::calculate_properties().
     *
     * @param stdClass $course Course record.
     * @param int $userid User id.
     * @return int
     */
    private static function start_offset(stdClass $course, int $userid): int {
        if (empty($course->relativedatesmode)) {
            return 0;
        }
        $dates = course_get_course_dates_for_user_id($course, $userid);
        return !empty($dates['start']) ? (int) $dates['startoffset'] : 0;
    }

    /**
     * Resolve the grade of one assignment as Moodle would show it to the user.
     *
     * Mirrors assign::get_assign_feedback_status_renderable() (mod/assign/locallib.php,
     * the $hasgrade and $gradevisible conditions):
     * - the grade comes from the gradebook, not from assign_grades; with the
     *   marking workflow the gradebook only receives it on release
     *   (assign::gradebook_item_update());
     * - a grade hidden in the gradebook (hidden, hidden until, hidden item)
     *   is shown only to users with mod/assign:grade.
     * A grade counts as withheld when one exists but is not shown: hidden in
     * the gradebook, or entered in assign_grades but not yet in the gradebook
     * (workflow not released, blind marking), except in the workflow state
     * "Not marked", in which Moodle reports no grade at all.
     *
     * @param stdClass $r Assignment row.
     * @param \cm_info $cm Course module.
     * @param int $userid User id.
     * @param grade_grade|null $gradegrade Gradebook grade of the user.
     * @param stdClass|null $flags User flags.
     * @param stdClass|null $assigngrade Latest assign_grades row of the user.
     * @return array{0: ?float, 1: ?string, 2: ?int, 3: bool} Grade, display string for scales,
     *     date graded, whether a grade exists but is withheld.
     */
    private static function visible_grade(
        stdClass $r,
        \cm_info $cm,
        int $userid,
        ?grade_grade $gradegrade,
        ?stdClass $flags,
        ?stdClass $assigngrade
    ): array {
        $item = $gradegrade ? $gradegrade->grade_item : null;
        $hasgradebookgrade = $gradegrade !== null && empty($item->needsupdate) && is_numeric($gradegrade->finalgrade);
        if ($hasgradebookgrade) {
            if ($gradegrade->is_hidden() && !has_capability('mod/assign:grade', $cm->context, $userid)) {
                return [null, null, null, true];
            }
            $value = (float) $gradegrade->finalgrade;
            $str = (int) $item->gradetype === GRADE_TYPE_SCALE ? grade_format_gradevalue($value, $item, true) : null;
            $dategraded = $gradegrade->get_dategraded();
            return [$value, $str, $dategraded ? (int) $dategraded : null, false];
        }

        $hasassigngrade = $assigngrade && $assigngrade->grade !== null && (float) $assigngrade->grade >= 0;
        if (!$hasassigngrade) {
            return [null, null, null, false];
        }
        $state = (string) ($flags->workflowstate ?? '');
        if (!empty($r->markingworkflow) && ($state === '' || $state === ASSIGN_MARKING_WORKFLOW_STATE_NOTMARKED)) {
            return [null, null, null, false];
        }
        return [null, null, null, true];
    }

    /**
     * Check whether an assignment row matches the requested status filter.
     *
     * @param string $status Derived status.
     * @param bool $overdue Overdue flag.
     * @param int $duedate Due timestamp.
     * @param int $now Current time.
     * @param string $filter Requested filter.
     * @return bool
     */
    private static function matches_status_filter(string $status, bool $overdue, int $duedate, int $now, string $filter): bool {
        switch ($filter) {
            case 'all':
                return true;
            case 'notsubmitted':
                return $status === 'notsubmitted' && !$overdue;
            case 'submitted':
                return $status === 'submitted';
            case 'graded':
                return $status === 'graded';
            case 'overdue':
                return $overdue;
            case 'upcoming':
                return $status === 'notsubmitted' && $duedate > 0 && $duedate >= $now && $duedate <= ($now + (7 * DAYSECS));
            default:
                return true;
        }
    }

    /**
     * Format a grade for human / LLM consumption.
     *
     * @param float|null $grade Numeric grade or null.
     * @param float $max Max grade (0 = unscaled / scale).
     * @return string
     */
    private static function format_grade(?float $grade, float $max): string {
        if ($grade === null) {
            return '-';
        }
        if ($max > 0) {
            return sprintf('%s / %s', self::format_number($grade), self::format_number($max));
        }
        return self::format_number($grade);
    }

    /**
     * Pretty number formatter that drops trailing zeros.
     *
     * @param float $value Number to format.
     * @return string
     */
    private static function format_number(float $value): string {
        $formatted = number_format($value, 2, '.', '');
        return rtrim(rtrim($formatted, '0'), '.');
    }

    /**
     * Empty payload helper for users with no eligible courses.
     *
     * @param string $statusfilter Active status filter.
     * @return array<string, mixed>
     */
    private static function empty_payload(string $statusfilter): array {
        return [
            'assignments' => [],
            'total' => 0,
            'limit' => self::DEFAULT_LIMIT,
            'offset' => 0,
            'has_more' => false,
            'next_offset' => null,
            'status' => $statusfilter,
            'summary' => 'No assignments found.',
        ];
    }

    /**
     * Build a one-sentence summary for the LLM.
     *
     * @param int $total Matching assignments.
     * @param string $status Active status filter.
     * @param int $courseidfilter Course filter (0 = none).
     * @param array<int, string> $coursemap Map of course id => fullname.
     * @return string
     */
    private static function build_summary(int $total, string $status, int $courseidfilter, array $coursemap): string {
        if ($total === 0) {
            return $status === 'all' ? 'No assignments found.' : sprintf('No %s assignments.', $status);
        }
        $courselabel = '';
        if ($courseidfilter > 0) {
            $courselabel = isset($coursemap[$courseidfilter])
                ? sprintf(' in "%s"', $coursemap[$courseidfilter])
                : sprintf(' in course %d', $courseidfilter);
        }
        $word = $total === 1 ? 'assignment' : 'assignments';
        $statuslabel = $status === 'all' ? '' : ($status . ' ');
        return sprintf('Found %d %s%s%s.', $total, $statuslabel, $word, $courselabel);
    }
}

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
use moodle_url;
use stdClass;
use webservice_elediamcp\local\ai\ai_tool;

/**
 * AI-native teacher grading queue.
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moodle_grading_queue implements ai_tool {
    /** @var int Default page size. */
    private const DEFAULT_LIMIT = 20;

    /** @var int Maximum page size. */
    private const MAX_LIMIT = 50;

    /**
     * Tool name.
     *
     * @return string
     */
    public static function name(): string {
        return 'moodle_grading_queue';
    }

    /**
     * Tool title.
     *
     * @return string
     */
    public static function title(): string {
        return 'Assignments waiting for grading';
    }

    /**
     * Tool description.
     *
     * @return string
     */
    public static function description(): string {
        return 'Returns assignments the authenticated teacher can grade, with counts of submitted '
            . 'attempts that appear to need grading. Use this to answer "what do I need to correct?" '
            . 'or to build a teacher daily briefing.';
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
                'include_empty' => [
                    'type' => 'boolean',
                    'default' => false,
                    'description' => 'Include gradeable assignments even when no submissions need grading.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => self::MAX_LIMIT,
                    'default' => self::DEFAULT_LIMIT,
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
            'required' => ['assignments', 'total_needing_grading', 'summary'],
            'properties' => [
                'assignments' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['cmid', 'assign_id', 'name', 'course_id', 'needs_grading_count', 'url'],
                        'properties' => [
                            'cmid' => ['type' => 'integer'],
                            'assign_id' => ['type' => 'integer'],
                            'name' => ['type' => 'string'],
                            'course_id' => ['type' => 'integer'],
                            'course_name' => ['type' => 'string'],
                            'needs_grading_count' => ['type' => 'integer'],
                            'submitted_count' => ['type' => 'integer'],
                            'oldest_submission_iso' => ['type' => ['string', 'null']],
                            'url' => ['type' => 'string'],
                        ],
                    ],
                ],
                'total_needing_grading' => ['type' => 'integer'],
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
     * @param array<string, mixed> $arguments Tool arguments.
     * @param stdClass $user Authenticated user.
     * @return array<string, mixed>
     */
    public static function execute(array $arguments, stdClass $user): array {
        global $DB;

        $courseidfilter = isset($arguments['course_id']) ? (int) $arguments['course_id'] : 0;
        $includeempty = !empty($arguments['include_empty']);
        $limit = isset($arguments['limit'])
            ? max(1, min(self::MAX_LIMIT, (int) $arguments['limit']))
            : self::DEFAULT_LIMIT;

        $courses = enrol_get_users_courses((int) $user->id, true, ['id', 'fullname']);
        if ($courseidfilter > 0) {
            $courses = isset($courses[$courseidfilter]) || is_siteadmin($user)
                ? [$courseidfilter => $courses[$courseidfilter] ?? get_course($courseidfilter)]
                : [];
        }
        if (empty($courses)) {
            return [
                'assignments' => [],
                'total_needing_grading' => 0,
                'summary' => 'No gradeable courses found for this user.',
            ];
        }

        [$insql, $params] = $DB->get_in_or_equal(array_keys($courses), SQL_PARAMS_NAMED, 'cid');
        $sql = "SELECT a.id AS assignid, a.name, a.course AS courseid, a.grade, a.teamsubmission,
                       a.markingworkflow, a.markingallocation, cm.id AS cmid
                  FROM {assign} a
                  JOIN {course_modules} cm ON cm.instance = a.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
                 WHERE a.course $insql
              ORDER BY a.course ASC, a.name ASC";
        $rows = $DB->get_records_sql($sql, $params);

        $entries = [];
        $total = 0;
        $grouprestricted = false;
        foreach ($rows as $row) {
            $context = context_module::instance((int) $row->cmid);
            if (!has_capability('mod/assign:grade', $context, $user->id)) {
                continue;
            }
            $modinfo = get_fast_modinfo((int) $row->courseid, (int) $user->id);
            $cm = $modinfo->cms[(int) $row->cmid] ?? null;
            if (!$cm || (!$cm->uservisible && !has_capability('moodle/course:viewhiddenactivities', $context, $user->id))) {
                continue;
            }

            $allowedgroupids = self::allowed_groupids($cm, $context, $user);
            $grouprestricted = $grouprestricted || $allowedgroupids !== null;
            // With marking allocation a grader counts only the students allocated to them, like the grading table.
            $allocated = !empty($row->markingworkflow) && !empty($row->markingallocation)
                ? grading_guard::allocated_student_ids((int) $row->cmid, $user)
                : null;
            $stats = self::submission_stats($row, $context, $allowedgroupids, $allocated);
            if (!$includeempty && $stats['needs_grading_count'] === 0) {
                continue;
            }
            $total += $stats['needs_grading_count'];
            $entries[] = [
                'cmid' => (int) $row->cmid,
                'assign_id' => (int) $row->assignid,
                'name' => format_string((string) $row->name, true, ['context' => $context]),
                'course_id' => (int) $row->courseid,
                'course_name' => format_string((string) ($courses[(int) $row->courseid]->fullname ?? ''), true),
                'needs_grading_count' => $stats['needs_grading_count'],
                'submitted_count' => $stats['submitted_count'],
                'oldest_submission_iso' => $stats['oldest_submission'] ? date(DATE_ATOM, $stats['oldest_submission']) : null,
                'url' => (new moodle_url('/mod/assign/view.php', ['id' => (int) $row->cmid, 'action' => 'grading']))->out(false),
            ];
        }

        usort($entries, static fn(array $a, array $b): int => $b['needs_grading_count'] <=> $a['needs_grading_count']);
        $entries = array_slice($entries, 0, $limit);

        return [
            'assignments' => $entries,
            'total_needing_grading' => $total,
            'summary' => ($total === 0
                ? 'No submitted assignments appear to need grading.'
                : "{$total} submitted assignment(s) appear to need grading.")
                . ($grouprestricted ? ' Separate groups: only members of your own groups are counted.' : ''),
        ];
    }

    /**
     * Groups the grader is limited to in this assignment, or null when no group limit applies.
     *
     * With separate groups on activity level and without moodle/site:accessallgroups the
     * grader sees only own groups that are marked for participation
     * (groups_get_activity_allowed_groups()). A grader without such a group sees nobody.
     *
     * @param \cm_info $cm Course module.
     * @param context_module $context Module context.
     * @param stdClass $user Acting user.
     * @return int[]|null Group ids (possibly empty) or null for no restriction.
     */
    private static function allowed_groupids(\cm_info $cm, context_module $context, stdClass $user): ?array {
        if (
            (int) groups_get_activity_groupmode($cm) !== SEPARATEGROUPS
                || has_capability('moodle/site:accessallgroups', $context, $user->id)
        ) {
            return null;
        }
        // Only groups marked for participation, as groups_get_activity_allowed_groups() does.
        return array_map('intval', array_keys(
            groups_get_all_groups((int) $cm->course, (int) $user->id, (int) $cm->groupingid, 'g.*', false, true)
        ));
    }

    /**
     * Count submissions for one assignment the way the grading summary of mod_assign does.
     *
     * Same rules as assign::count_submissions_need_grading_with_groups() (Moodle 5.1+; 4.5 has
     * count_submissions_need_grading() for one selected group with the same conditions): only
     * active enrolments, optionally limited to groups, and "needs grading" means no grade yet,
     * a grade older than or as old as the submission, or "no grade" on a scale. Team submissions
     * are counted per team: "needs grading" like core 5.1+ via the groups of the submitting
     * members (the default team without a group is not counted there, as in core), "submitted"
     * from the submitted team records like assign::count_submissions_with_status_and_groups().
     *
     * @param stdClass $assign Row with assignid, grade and teamsubmission.
     * @param context_module $context Module context.
     * @param int[]|null $groupids Limit to members of these groups; null for all.
     * @param int[]|null $allocated Limit to these allocated students (marking allocation); null for all.
     * @return array{needs_grading_count: int, submitted_count: int, oldest_submission: int|null}
     */
    private static function submission_stats(
        stdClass $assign,
        context_module $context,
        ?array $groupids,
        ?array $allocated = null
    ): array {
        global $DB;

        if ($groupids === []) {
            return ['needs_grading_count' => 0, 'submitted_count' => 0, 'oldest_submission' => null];
        }
        [$esql, $params] = get_enrolled_sql($context, '', $groupids ?? 0, true);
        $params['assignment'] = (int) $assign->assignid;
        $params['submitted'] = 'submitted';
        $scalegrade = (int) $assign->grade < 0 ? ' OR g.grade = -1' : '';
        $sql = "SELECT s.id, s.userid, s.timemodified,
                       CASE WHEN (s.timemodified >= g.timemodified OR g.timemodified IS NULL
                                  OR g.grade IS NULL$scalegrade) THEN 1 ELSE 0 END AS needsgrading
                  FROM {assign_submission} s
             LEFT JOIN {assign_grades} g
                    ON g.assignment = s.assignment
                   AND g.userid = s.userid
                   AND g.attemptnumber = s.attemptnumber
                  JOIN ($esql) e ON e.id = s.userid
                 WHERE s.assignment = :assignment
                   AND s.latest = 1
                   AND s.status = :submitted
                   AND s.timemodified IS NOT NULL";
        $rows = $DB->get_records_sql($sql, $params);
        if ($allocated !== null) {
            $rows = array_filter($rows, static fn($row): bool => in_array((int) $row->userid, $allocated, true));
        }

        $submitted = [];
        $needs = [];
        $oldest = null;
        foreach ($rows as $row) {
            $submitted[] = (int) $row->userid;
            if ((int) $row->needsgrading === 1) {
                $needs[] = (int) $row->userid;
                $time = (int) $row->timemodified;
                $oldest = $oldest === null ? $time : min($oldest, $time);
            }
        }

        if (!empty($assign->teamsubmission)) {
            return [
                'needs_grading_count' => self::count_teams((int) $assign->assignid, $needs),
                'submitted_count' => self::count_submitted_teams((int) $assign->assignid, $groupids),
                'oldest_submission' => $oldest,
            ];
        }
        return [
            'needs_grading_count' => count($needs),
            'submitted_count' => count($submitted),
            'oldest_submission' => $oldest,
        ];
    }

    /**
     * Count the submission teams (groups with a team submission) the given members belong to.
     *
     * @param int $assignmentid Assignment id.
     * @param int[] $userids Team members.
     * @return int
     */
    private static function count_teams(int $assignmentid, array $userids): int {
        global $DB;

        if ($userids === []) {
            return 0;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'member');
        $params['assignment'] = $assignmentid;
        return (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT gm.groupid)
               FROM {groups_members} gm
               JOIN {assign_submission} ts
                 ON ts.groupid = gm.groupid
                AND ts.assignment = :assignment
                AND ts.userid = 0
                AND ts.latest = 1
              WHERE gm.userid $insql",
            $params
        );
    }

    /**
     * Count submitted team records, optionally limited to the given groups (core rule).
     *
     * @param int $assignmentid Assignment id.
     * @param int[]|null $groupids Limit to these teams; null for all (including the default team).
     * @return int
     */
    private static function count_submitted_teams(int $assignmentid, ?array $groupids): int {
        global $DB;

        $select = 'assignment = :assignment AND userid = 0 AND latest = 1 AND status = :submitted';
        $params = ['assignment' => $assignmentid, 'submitted' => 'submitted'];
        if ($groupids !== null) {
            if ($groupids === []) {
                return 0;
            }
            [$insql, $inparams] = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED, 'team');
            $select .= " AND groupid $insql";
            $params += $inparams;
        }
        return $DB->count_records_select('assign_submission', $select, $params);
    }
}

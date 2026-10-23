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

use context_course;
use context_module;
use grade_item;
use stdClass;
use webservice_elediamcp\local\ai\ai_tool;
use webservice_elediamcp\local\ai\tool_exception;

/**
 * Teacher tool: read-only course health report.
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moodle_course_health implements ai_tool {
    /** @var int Maximum inactive students listed by name. */
    private const INACTIVE_LIST_CAP = 20;

    /**
     * Return the MCP tool name.
     *
     * @return string
     */
    public static function name(): string {
        return 'moodle_course_health';
    }

    /**
     * Return the human-readable tool title.
     *
     * @return string
     */
    public static function title(): string {
        return 'Course health report';
    }

    /**
     * Return the tool description shown to MCP clients.
     *
     * @return string
     */
    public static function description(): string {
        return 'Read-only teacher report for one course: student count, students inactive for N '
            . 'days (never accessed or no recent access), per-assignment submission and grading '
            . 'coverage, and the course total grade summary. Requires '
            . 'moodle/course:viewparticipants and moodle/grade:viewall in the course. Good starting '
            . 'point before moodle_grading_queue (what needs grading) or '
            . 'moodle_message_course_students (nudge inactive or missing students).';
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
            'required' => ['course_id'],
            'properties' => [
                'course_id' => ['type' => 'integer', 'minimum' => 1],
                'inactive_days' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 365,
                    'default' => 14,
                    'description' => 'A student counts as inactive without course access in this many days.',
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
            'required' => ['student_count', 'inactive', 'assignments', 'summary'],
            'properties' => [
                'student_count' => ['type' => 'integer'],
                'inactive_count' => ['type' => 'integer'],
                'inactive' => [
                    'type' => 'array',
                    'description' => 'Capped list of inactive students.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'fullname' => ['type' => 'string'],
                            'last_access' => ['type' => 'integer', 'description' => '0 = never.'],
                        ],
                    ],
                ],
                'assignments' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'cmid' => ['type' => 'integer'],
                            'name' => ['type' => 'string'],
                            'duedate' => ['type' => 'integer'],
                            'submitted' => ['type' => 'integer'],
                            'graded' => ['type' => 'integer'],
                            'missing' => ['type' => 'integer'],
                        ],
                    ],
                ],
                'grade_summary' => [
                    'type' => ['object', 'null'],
                    'properties' => [
                        'graded_students' => ['type' => 'integer'],
                        'average' => ['type' => ['number', 'null']],
                        'min' => ['type' => ['number', 'null']],
                        'max' => ['type' => ['number', 'null']],
                        'grade_max' => ['type' => 'number'],
                    ],
                ],
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
            'readOnlyHint' => true,
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
        global $CFG, $DB;

        $courseid = (int) ($arguments['course_id'] ?? 0);
        if ($courseid <= 0) {
            throw new tool_exception('course_id is required.');
        }
        $inactivedays = isset($arguments['inactive_days'])
            ? max(1, min(365, (int) $arguments['inactive_days']))
            : 14;

        $course = get_course($courseid);
        $context = context_course::instance($courseid);
        // Course access as in require_login(): only an active enrolment (or course:view) counts.
        if (!is_siteadmin($user) && !can_access_course($course, $user, '', true)) {
            throw new tool_exception('You cannot access course ' . $courseid . '.', ['course_id' => $courseid]);
        }
        require_capability('moodle/course:viewparticipants', $context, $user->id);
        require_capability('moodle/grade:viewall', $context, $user->id);

        // Students = active enrolled users who can submit assignments. With separate groups on course
        // level and without accessallgroups only members of the own groups count, as on the
        // participants page (user/classes/table/participants_search.php).
        $allowedgroupids = self::course_allowed_groupids($course, $context, $user);
        $students = [];
        if ($allowedgroupids === null || $allowedgroupids !== []) {
            $students = get_enrolled_users($context, 'mod/assign:submit', $allowedgroupids ?? 0, 'u.id, u.firstname,
                u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename', null, 0, 0, true);
        }
        $studentids = array_map(static fn(stdClass $student): int => (int) $student->id, $students);
        $studentcount = count($studentids);
        $grouprestricted = $allowedgroupids !== null;

        // Inactivity via per-course last access.
        $cutoff = time() - $inactivedays * DAYSECS;
        $lastaccess = [];
        if ($studentids !== []) {
            [$insql, $params] = $DB->get_in_or_equal($studentids, SQL_PARAMS_NAMED);
            $params['courseid'] = $courseid;
            $lastaccess = $DB->get_records_select_menu(
                'user_lastaccess',
                "courseid = :courseid AND userid $insql",
                $params,
                '',
                'userid, timeaccess'
            );
        }
        $inactive = [];
        foreach ($students as $student) {
            $access = (int) ($lastaccess[$student->id] ?? 0);
            if ($access < $cutoff) {
                $inactive[] = [
                    'id' => (int) $student->id,
                    'fullname' => fullname($student),
                    'last_access' => $access,
                ];
            }
        }
        usort($inactive, static fn(array $a, array $b): int => $a['last_access'] <=> $b['last_access']);
        $inactivecount = count($inactive);

        // Per-assignment submission and grading coverage (latest attempts).
        $assignments = [];
        $modinfo = get_fast_modinfo($course);
        foreach ($modinfo->get_instances_of('assign') as $cm) {
            if (!$cm->uservisible) {
                continue;
            }
            // Counters per assignment follow the assignment itself, like its grading summary:
            // active students who may submit, limited by separate groups on activity level and by
            // the availability of the assignment (assign::count_participants() -> list_participants()).
            $modcontext = context_module::instance((int) $cm->id);
            $groupids = self::activity_allowed_groupids($cm, $course, $modcontext, $user);
            if ($groupids !== null) {
                $grouprestricted = true;
            }
            $scope = [];
            if ($groupids === null || $groupids !== []) {
                $scope = get_enrolled_users($modcontext, 'mod/assign:submit', $groupids ?? 0, 'u.id', null, 0, 0, true);
                $scope = (new \core_availability\info_module($cm))->filter_user_list($scope);
            }
            $scopeids = array_map('intval', array_keys($scope));
            $instance = $DB->get_record(
                'assign',
                ['id' => (int) $cm->instance],
                'id, course, duedate, teamsubmission, teamsubmissiongroupingid, markingworkflow, markingallocation'
            );
            // With marking allocation a grader counts only the students allocated to them, like the grading summary.
            if (!empty($instance->markingworkflow) && !empty($instance->markingallocation)) {
                $allocated = grading_guard::allocated_student_ids((int) $cm->id, $user);
                if ($allocated !== null) {
                    $scopeids = array_values(array_intersect($scopeids, $allocated));
                }
            }
            $submitted = 0;
            $graded = 0;
            if ($scopeids !== []) {
                if (!empty($instance->teamsubmission)) {
                    // Team submissions: a member counts as submitted when the team has submitted.
                    $submitted = count(self::team_submitted_userids($instance, $scopeids));
                } else {
                    [$insql, $params] = $DB->get_in_or_equal($scopeids, SQL_PARAMS_NAMED, 'scope');
                    $params['assignment'] = (int) $cm->instance;
                    $submitted = (int) $DB->get_field_sql(
                        "SELECT COUNT(DISTINCT userid)
                           FROM {assign_submission}
                          WHERE assignment = :assignment AND latest = 1 AND status = 'submitted' AND userid $insql",
                        $params
                    );
                }
                [$insql, $params] = $DB->get_in_or_equal($scopeids, SQL_PARAMS_NAMED, 'scope');
                $params['assignment'] = (int) $cm->instance;
                $graded = (int) $DB->get_field_sql(
                    "SELECT COUNT(DISTINCT userid)
                       FROM {assign_grades}
                      WHERE assignment = :assignment AND grade IS NOT NULL AND grade >= 0 AND userid $insql",
                    $params
                );
            }
            $assignments[] = [
                'cmid' => (int) $cm->id,
                'name' => $cm->get_formatted_name(),
                'duedate' => (int) ($instance->duedate ?? 0),
                'submitted' => $submitted,
                'graded' => $graded,
                'missing' => max(0, count($scopeids) - $submitted),
            ];
        }

        // Course total grade summary.
        require_once($CFG->libdir . '/gradelib.php');
        $gradesummary = null;
        $courseitem = grade_item::fetch_course_item($courseid);
        if ($courseitem && $studentids !== []) {
            [$insql, $params] = $DB->get_in_or_equal($studentids, SQL_PARAMS_NAMED);
            $params['itemid'] = (int) $courseitem->id;
            $stats = $DB->get_record_sql(
                "SELECT COUNT(finalgrade) AS graded, AVG(finalgrade) AS avggrade,
                        MIN(finalgrade) AS mingrade, MAX(finalgrade) AS maxgrade
                   FROM {grade_grades}
                  WHERE itemid = :itemid AND finalgrade IS NOT NULL AND userid $insql",
                $params
            );
            $gradesummary = [
                'graded_students' => (int) $stats->graded,
                'average' => $stats->avggrade !== null ? round((float) $stats->avggrade, 1) : null,
                'min' => $stats->mingrade !== null ? (float) $stats->mingrade : null,
                'max' => $stats->maxgrade !== null ? (float) $stats->maxgrade : null,
                'grade_max' => (float) $courseitem->grademax,
            ];
        }

        return [
            'student_count' => $studentcount,
            'inactive_count' => $inactivecount,
            'inactive' => array_slice($inactive, 0, self::INACTIVE_LIST_CAP),
            'assignments' => $assignments,
            'grade_summary' => $gradesummary,
            'summary' => format_string($course->fullname, true, ['context' => $context]) . ': '
                . $studentcount . ' student(s), ' . $inactivecount . ' inactive for over '
                . $inactivedays . ' days, ' . count($assignments) . ' assignment(s).'
                . ($inactivecount > self::INACTIVE_LIST_CAP
                    ? ' Inactive list capped at ' . self::INACTIVE_LIST_CAP . ' entries.'
                    : '')
                . ($grouprestricted
                    ? ' Separate groups: only members of your own groups are included.'
                    : ''),
        ];
    }

    /**
     * Groups the user is limited to on course level, or null when there is no limit.
     *
     * Mirrors the participants page: with separate groups as course group mode and without
     * moodle/site:accessallgroups only the members of the user's own groups are visible.
     *
     * @param stdClass $course Course record.
     * @param context_course $context Course context.
     * @param stdClass $user Acting user.
     * @return int[]|null Group ids (possibly empty) or null for no restriction.
     */
    private static function course_allowed_groupids(stdClass $course, context_course $context, stdClass $user): ?array {
        if (
            (int) groups_get_course_groupmode($course) !== SEPARATEGROUPS
                || has_capability('moodle/site:accessallgroups', $context, $user->id)
        ) {
            return null;
        }
        return array_map('intval', array_keys(groups_get_all_groups((int) $course->id, (int) $user->id)));
    }

    /**
     * Groups the user is limited to in one activity, or null when the activity adds no group limit.
     *
     * Same rule as groups_get_activity_allowed_groups(): with separate groups and without
     * moodle/site:accessallgroups only own groups marked for participation, within the grouping.
     *
     * @param \cm_info $cm Course module.
     * @param stdClass $course Course record.
     * @param context_module $modcontext Module context.
     * @param stdClass $user Acting user.
     * @return int[]|null Group ids (possibly empty) or null for no restriction.
     */
    private static function activity_allowed_groupids(
        \cm_info $cm,
        stdClass $course,
        context_module $modcontext,
        stdClass $user
    ): ?array {
        if (
            (int) groups_get_activity_groupmode($cm, $course) !== SEPARATEGROUPS
                || has_capability('moodle/site:accessallgroups', $modcontext, $user->id)
        ) {
            return null;
        }
        return array_map('intval', array_keys(
            groups_get_all_groups((int) $course->id, (int) $user->id, (int) $cm->groupingid, 'g.*', false, true)
        ));
    }

    /**
     * Members of the given users whose team has submitted (team submissions).
     *
     * Same team rule as assign::get_submission_group(): the team is the only group with
     * participation of the user (within the team grouping); with none or several groups the
     * user belongs to the default team (groupid 0). The team status is the status of the
     * team record (userid 0), as mod_assign stores it.
     *
     * @param stdClass $assign Assign record with id, course and teamsubmissiongroupingid.
     * @param int[] $userids Users to check.
     * @return int[] Users whose team has submitted.
     */
    private static function team_submitted_userids(stdClass $assign, array $userids): array {
        global $DB;

        if ($userids === []) {
            return [];
        }
        $teams = $DB->get_fieldset_select(
            'assign_submission',
            'groupid',
            'assignment = :assignment AND userid = 0 AND latest = 1 AND status = :submitted',
            ['assignment' => (int) $assign->id, 'submitted' => 'submitted']
        );
        if ($teams === []) {
            return [];
        }
        $submittedteams = array_flip(array_map('intval', $teams));

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'member');
        $params['courseid'] = (int) $assign->course;
        $groupingjoin = '';
        if (!empty($assign->teamsubmissiongroupingid)) {
            $groupingjoin = 'JOIN {groupings_groups} gg ON gg.groupid = g.id AND gg.groupingid = :groupingid';
            $params['groupingid'] = (int) $assign->teamsubmissiongroupingid;
        }
        $memberships = $DB->get_recordset_sql(
            "SELECT gm.id, gm.userid, gm.groupid
               FROM {groups_members} gm
               JOIN {groups} g ON g.id = gm.groupid AND g.courseid = :courseid AND g.participation = 1
                    $groupingjoin
              WHERE gm.userid $insql",
            $params
        );
        $groupsbyuser = [];
        foreach ($memberships as $membership) {
            $groupsbyuser[(int) $membership->userid][] = (int) $membership->groupid;
        }
        $memberships->close();

        $result = [];
        foreach ($userids as $userid) {
            $groups = $groupsbyuser[$userid] ?? [];
            $team = count($groups) === 1 ? $groups[0] : 0;
            if (isset($submittedteams[$team])) {
                $result[] = (int) $userid;
            }
        }
        return $result;
    }
}

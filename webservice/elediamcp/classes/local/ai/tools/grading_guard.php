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
use context_module;
use stdClass;
use webservice_elediamcp\local\ai\tool_exception;

/**
 * Shared participant boundaries for the grading-loop MCP tools.
 *
 * Mirrors what the Moodle grading UI enforces: no access to blind-marking
 * assignments (identities must stay hidden), only real assignment
 * participants, and the separate-groups boundary for graders without
 * moodle/site:accessallgroups.
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class grading_guard {
    /**
     * Throw unless the acting user may read/grade the given student's submission.
     *
     * @param assign $assign Assignment instance.
     * @param stdClass $cm Course module record (from get_coursemodule_from_id).
     * @param stdClass $course Course record.
     * @param context_module $context Module context.
     * @param stdClass $user Acting (grading) user.
     * @param int $studentid Target student id.
     * @param int $cmid Course module id (for error context).
     * @return void
     */
    public static function require_gradable_participant(
        assign $assign,
        stdClass $cm,
        stdClass $course,
        context_module $context,
        stdClass $user,
        int $studentid,
        int $cmid
    ): void {
        if ($assign->is_blind_marking()) {
            throw new tool_exception(
                'This assignment uses blind marking; student identities must stay hidden. '
                . 'Use the Moodle grading interface instead.',
                ['cmid' => $cmid]
            );
        }

        $reachable = (bool) $assign->get_participant($studentid);

        if ($reachable) {
            $groupmode = groups_get_activity_groupmode($cm, $course);
            if (
                (int) $groupmode === SEPARATEGROUPS
                    && !has_capability('moodle/site:accessallgroups', $context, $user->id)
            ) {
                $groupingid = (int) ($cm->groupingid ?? 0);
                $studentgroups = groups_get_all_groups((int) $course->id, $studentid, $groupingid);
                // Only groups marked for participation, as groups_get_activity_allowed_groups() does.
                $gradergroups = groups_get_all_groups((int) $course->id, (int) $user->id, $groupingid, 'g.*', false, true);
                $reachable = (bool) array_intersect_key($studentgroups, $gradergroups);
            }
        }

        if ($reachable) {
            $reachable = self::is_allocated_to_grader($assign, $context, $user, $studentid);
        }

        if (!$reachable) {
            throw new tool_exception(
                'user_id ' . $studentid . ' is not a participant of this assignment you are allowed to grade.',
                ['cmid' => $cmid, 'user_id' => $studentid]
            );
        }
    }

    /**
     * With marking allocation, a grader without mod/assign:manageallocations reaches only allocated students.
     *
     * Same rule as the grading app: its panel and mod_assign_get_participant only load students from
     * the grading table, which is built from list_participants(); that list keeps only the students
     * allocated to the current grader (mod/assign/locallib.php list_participants()). The allocation
     * is stored differently in 4.5 and 5.x, so the core list is used instead of the tables.
     * The list also applies the availability of the assignment (and in 4.5 excludes suspended
     * accounts), so with allocation this check is stricter than get_participant(), exactly like
     * the grading table.
     *
     * @param assign $assign Assignment instance.
     * @param context_module $context Module context.
     * @param stdClass $user Acting (grading) user.
     * @param int $studentid Target student id.
     * @return bool
     */
    private static function is_allocated_to_grader(
        assign $assign,
        context_module $context,
        stdClass $user,
        int $studentid
    ): bool {
        $allocated = self::allocated_ids($assign, $context, $user);
        return $allocated === null || in_array($studentid, $allocated, true);
    }

    /**
     * Students a grader reaches through the marking allocation of an assignment, for counters.
     *
     * Same rule as the grading table: with marking allocation, a grader without
     * mod/assign:manageallocations sees only the students allocated to them. Returns null when
     * nothing is restricted (no marking allocation, or the user manages allocations).
     *
     * @param int $cmid Course module id of the assignment.
     * @param stdClass $user Acting (grading) user.
     * @return int[]|null Allocated student ids, or null for no restriction.
     */
    public static function allocated_student_ids(int $cmid, stdClass $user): ?array {
        $cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
        $context = context_module::instance($cmid);
        $assign = new assign($context, $cm, get_course((int) $cm->course));
        return self::allocated_ids($assign, $context, $user);
    }

    /**
     * Allocated student ids of the grader, or null when the allocation does not restrict them.
     *
     * @param assign $assign Assignment instance.
     * @param context_module $context Module context.
     * @param stdClass $user Acting (grading) user.
     * @return int[]|null
     */
    private static function allocated_ids(assign $assign, context_module $context, stdClass $user): ?array {
        global $USER;

        $instance = $assign->get_instance();
        if (
            empty($instance->markingworkflow)
                || empty($instance->markingallocation)
                || has_capability('mod/assign:manageallocations', $context, $user->id)
        ) {
            return null;
        }
        // The core list_participants() filters for the global $USER; the tools run as that user. Fail closed otherwise.
        if ((int) $USER->id !== (int) $user->id) {
            return [];
        }
        return array_map('intval', array_keys($assign->list_participants(0, true)));
    }
}

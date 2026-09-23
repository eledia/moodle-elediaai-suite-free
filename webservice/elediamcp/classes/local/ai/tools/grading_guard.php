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
                $gradergroups = groups_get_all_groups((int) $course->id, (int) $user->id, $groupingid);
                $reachable = (bool) array_intersect_key($studentgroups, $gradergroups);
            }
        }

        if (!$reachable) {
            throw new tool_exception(
                'user_id ' . $studentid . ' is not a participant of this assignment you are allowed to grade.',
                ['cmid' => $cmid, 'user_id' => $studentid]
            );
        }
    }
}

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

namespace block_elediaai_tutor\local;

/**
 * Coarse user audience resolution for the dashboard hero mode.
 *
 * The dashboard (/my/) has no course context, so the audience-specific prompt
 * starters need a site-wide answer to "is this user primarily a manager, a
 * teacher or a student?". The heuristic is archetype-based: any role
 * assignment whose role has a manager/coursecreator archetype (or site-admin
 * status, or course-creation rights at system level) makes a manager; any
 * teacher-archetype assignment makes a teacher; everyone else is a student.
 *
 * The result is cached per request and in a MUC application cache, so role
 * changes surface after at most the cache TTL (or a purge) — acceptable for a
 * purely cosmetic starter selection that never widens permissions: the
 * suggested prompts run through MCP tools that enforce the user's real
 * capabilities server-side.
 *
 * @package     block_elediaai_tutor
 * @author      Johannes Moskaliuk
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_audience {
    /** @var string Users who manage courses site- or category-wide. */
    public const MANAGER = 'manager';

    /** @var string Users who teach in at least one course. */
    public const TEACHER = 'teacher';

    /** @var string Everyone else (the default audience). */
    public const STUDENT = 'student';

    /**
     * Resolve the audience for a user, cached in MUC (static-accelerated, so
     * repeated calls within a request are memory lookups).
     *
     * @param int $userid The user id.
     * @return string One of the MANAGER/TEACHER/STUDENT constants.
     */
    public static function resolve(int $userid): string {
        $cache = \cache::make('block_elediaai_tutor', 'audience');
        $cached = $cache->get($userid);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $audience = self::detect($userid);
        $cache->set($userid, $audience);
        return $audience;
    }

    /**
     * Uncached detection.
     *
     * @param int $userid The user id.
     * @return string One of the MANAGER/TEACHER/STUDENT constants.
     */
    private static function detect(int $userid): string {
        if (
            is_siteadmin($userid)
                || has_capability('moodle/course:create', \core\context\system::instance(), $userid)
        ) {
            return self::MANAGER;
        }
        if (self::has_archetype_assignment($userid, ['manager', 'coursecreator'])) {
            return self::MANAGER;
        }
        if (self::has_archetype_assignment($userid, ['editingteacher', 'teacher'])) {
            return self::TEACHER;
        }
        return self::STUDENT;
    }

    /**
     * Whether the user holds any role assignment with one of the archetypes.
     *
     * A single indexed EXISTS-style lookup on role_assignments; context level
     * is irrelevant — an assignment anywhere (system, category, course) counts.
     *
     * @param int $userid The user id.
     * @param string[] $archetypes Role archetype names.
     * @return bool
     */
    private static function has_archetype_assignment(int $userid, array $archetypes): bool {
        global $DB;

        [$insql, $inparams] = $DB->get_in_or_equal($archetypes, SQL_PARAMS_NAMED);
        $sql = "SELECT 'x'
                  FROM {role_assignments} ra
                  JOIN {role} r ON r.id = ra.roleid
                 WHERE ra.userid = :userid AND r.archetype $insql";

        return $DB->record_exists_sql($sql, ['userid' => $userid] + $inparams);
    }
}

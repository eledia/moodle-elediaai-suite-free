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

use local_elediaai_core\local\course_creator;
use webservice_elediamcp\local\ai\tool_exception;

/**
 * Picks the category for a new course, for every tool that creates one.
 *
 * moodle_create_course and local_elediaai_coursegen's moodle_generate_course
 * both used to fall back to the site default category, where a course
 * creator of one category may do nothing (#33). The choice and the errors
 * the model reads are made here, once.
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_category_guard {
    /**
     * The category the course goes into, or a tool error the model can act on.
     *
     * A missing permission is not a service outage: the answer names the
     * categories the person may use and says not to retry the same call.
     *
     * @param int|null $requested Category id from the arguments.
     * @param int $userid The acting user.
     * @return int
     * @throws tool_exception When no category can be chosen.
     */
    public static function resolve(?int $requested, int $userid): int {
        $allowed = course_creator::creatable_categories($userid);
        $choices = [];
        foreach ($allowed as $id => $name) {
            $choices[] = ['id' => $id, 'name' => $name];
        }

        if ($allowed === []) {
            throw new tool_exception(
                'The user may not create courses in any category (moodle/course:create is missing). '
                . 'Tell them to ask an administrator for the course creator role. Do not retry.',
                ['errorcode' => 'nopermissions']
            );
        }
        if ($requested !== null && !isset($allowed[$requested])) {
            throw new tool_exception(
                'The user may not create courses in category ' . $requested . '. Categories they may use: '
                . self::names($allowed) . '. Ask which one, then call again with its category_id.',
                ['errorcode' => 'nopermissions', 'allowed_categories' => $choices]
            );
        }

        $categoryid = course_creator::resolve_category($requested, $userid);
        if ($categoryid === null) {
            throw new tool_exception(
                'Which course category should the course go into? The user may create courses in: '
                . self::names($allowed) . '. Ask them, then call again with that category_id.',
                ['errorcode' => 'categoryrequired', 'allowed_categories' => $choices]
            );
        }
        return $categoryid;
    }

    /**
     * Categories as "Name (id 12)" for a message the model reads.
     *
     * @param array<int, string> $categories Id => name.
     * @return string
     */
    private static function names(array $categories): string {
        $out = [];
        foreach ($categories as $id => $name) {
            $out[] = '"' . $name . '" (id ' . $id . ')';
        }
        return implode(', ', $out);
    }
}

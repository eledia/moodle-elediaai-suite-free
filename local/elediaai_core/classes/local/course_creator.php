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

namespace local_elediaai_core\local;

use core\context\course as context_course;
use core\context\coursecat as context_coursecat;
use core_course_category;
use stdClass;

/**
 * What every suite path that creates a course has to do around create_course().
 *
 * Moodle's own form does two things that create_course() does not: it picks a
 * category the person may create in, and afterwards it enrols a course creator
 * in the new course (course/edit.php, $CFG->creatornewroleid). The MCP tools
 * and the course author called create_course() alone. A course creator in one
 * category therefore either failed on the default category or ended up with a
 * course they could not open (#33, #34). Both steps live here so every path
 * behaves like the form.
 *
 * @package   local_elediaai_core
 * @copyright 2026 eLeDia GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_creator {
    /**
     * The categories a person may create courses in, by id, with their path as name.
     *
     * @param int $userid The person.
     * @return array<int, string> Category id => "Parent / Child".
     */
    public static function creatable_categories(int $userid): array {
        global $DB;

        $categories = [];
        foreach ($DB->get_fieldset_select('course_categories', 'id', '1 = 1 ORDER BY sortorder') as $id) {
            $id = (int) $id;
            if (!has_capability('moodle/course:create', context_coursecat::instance($id), $userid)) {
                continue;
            }
            $category = core_course_category::get($id, IGNORE_MISSING, true);
            if ($category !== null) {
                $categories[$id] = $category->get_nested_name(false);
            }
        }
        return $categories;
    }

    /**
     * The category a new course goes into.
     *
     * A requested category is checked and used. Without one: the only category
     * the person may create in; else the site default, if they may create
     * there; else nothing -- the caller has to ask which one.
     *
     * @param int|null $requested Category id asked for, or null.
     * @param int $userid The person creating the course.
     * @return int|null The category id, or null when the person has to choose.
     * @throws \required_capability_exception When the requested category is not allowed.
     * @throws \moodle_exception When the person may create courses nowhere.
     */
    public static function resolve_category(?int $requested, int $userid): ?int {
        if ($requested !== null && $requested > 0) {
            require_capability('moodle/course:create', context_coursecat::instance($requested), $userid);
            return $requested;
        }

        $allowed = self::creatable_categories($userid);
        if ($allowed === []) {
            throw new \moodle_exception('nopermissions', 'error', '', 'moodle/course:create');
        }
        if (count($allowed) === 1) {
            return (int) array_key_first($allowed);
        }
        $default = (int) core_course_category::get_default()->id;
        return isset($allowed[$default]) ? $default : null;
    }

    /**
     * Enrol the person who created a course, as course/edit.php does.
     *
     * Only when they could not work in the course otherwise; a site admin
     * follows $CFG->enroladminnewcourse like in the form.
     *
     * @param stdClass $course The new course.
     * @param int $userid Who created it.
     * @return bool Whether an enrolment was made.
     */
    public static function enrol_creator(stdClass $course, int $userid): bool {
        global $CFG;

        if (empty($CFG->creatornewroleid) || $userid <= 0) {
            return false;
        }
        $context = context_course::instance((int) $course->id);
        $enrol = is_siteadmin($userid)
            ? !empty($CFG->enroladminnewcourse)
            : !is_viewing($context, $userid, 'moodle/role:assign');
        if (!$enrol || is_enrolled($context, $userid, 'moodle/role:assign')) {
            return false;
        }

        require_once($CFG->libdir . '/enrollib.php');
        return (bool) enrol_try_internal_enrol((int) $course->id, $userid, (int) $CFG->creatornewroleid);
    }
}

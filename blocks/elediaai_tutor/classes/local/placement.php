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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace block_elediaai_tutor\local;

use core\context;
use moodle_page;

/**
 * Resolve the course a Tutor block instance belongs to.
 *
 * A block does not only live directly in a course. Moodle allows block
 * instances in every context below the course as well, most notably in an
 * activity (module context). Checking the parent context for CONTEXT_COURSE
 * therefore misses those placements: the course stays unknown, and any page
 * that later initialises the settings navigation fails, because
 * moodle_page::set_cm() insists that the page course matches the module.
 *
 * All context resolution lives here so course, activity and any future nesting
 * behave the same way everywhere in the plugin.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class placement {
    /**
     * Course id of the context a block instance is placed in.
     *
     * Walks up to the enclosing course, so an instance inside an activity
     * reports that activity's course instead of nothing.
     *
     * @param context $blockcontext Context of the block instance.
     * @return int Course id, or 0 outside any course (e.g. Dashboard, front page block).
     */
    public static function courseid(context $blockcontext): int {
        $coursecontext = $blockcontext->get_course_context(false);
        if ($coursecontext === false) {
            return 0;
        }
        // The site front page is a course and is treated as one (operator
        // decision 20.09.2026); only a block outside any course context --
        // the dashboard -- answers 0 and is then scoped by enrolments.
        return (int) $coursecontext->instanceid;
    }

    /**
     * Course module the block instance sits in, if any.
     *
     * @param context $blockcontext Context of the block instance.
     * @return \cm_info|null Null when the block is not inside an activity.
     */
    public static function cm(context $blockcontext): ?\cm_info {
        $parent = $blockcontext->get_parent_context();
        if (!$parent || $parent->contextlevel !== CONTEXT_MODULE) {
            return null;
        }
        $coursecontext = $blockcontext->get_course_context(false);
        if ($coursecontext === false) {
            return null;
        }
        [, $cm] = get_course_and_cm_from_cmid((int) $parent->instanceid);
        return $cm;
    }

    /**
     * Point a page at the course and activity a block instance belongs to.
     *
     * Must run before anything triggers the settings navigation — that is,
     * before any output. Sets the activity as well, otherwise Moodle builds the
     * navigation for a module while the page still carries the site course and
     * aborts with a coding exception.
     *
     * @param moodle_page $page The page to configure.
     * @param context $blockcontext Context of the block instance.
     * @return int The resolved course id, or 0 outside any course.
     */
    public static function prepare_page(moodle_page $page, context $blockcontext): int {
        $courseid = self::courseid($blockcontext);
        if ($courseid === 0) {
            $page->set_pagelayout('standard');
            return 0;
        }

        $cm = self::cm($blockcontext);
        if ($cm !== null) {
            // Note: set_cm() sets the course itself and keeps both in sync.
            $page->set_cm($cm);
        } else {
            $page->set_course(get_course($courseid));
        }
        $page->set_pagelayout('incourse');
        return $courseid;
    }

    /**
     * Human-readable name of the place a block instance sits in.
     *
     * @param context $blockcontext Context of the block instance.
     * @return string Course full name, the activity name, or the plain context name.
     */
    public static function name(context $blockcontext): string {
        $parent = $blockcontext->get_parent_context();
        if (!$parent) {
            return '';
        }
        $courseid = self::courseid($blockcontext);
        if ($courseid > 0 && $parent->contextlevel == CONTEXT_COURSE) {
            return format_string(get_course($courseid)->fullname);
        }
        return $parent->get_context_name(false);
    }
}

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

namespace local_elediaai_sources;

/**
 * Who may read the plugin's help page, and in which context.
 *
 * The help page used to require moodle/site:config, so the teachers who
 * actually operate the activity selection could not read the help for it
 * (G-05). Opened from a course, it now asks for the capability that page
 * asks for; opened without a course, it stays an administrator page.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class help_access {
    /** @var string Capability of the teacher-facing activity selection. */
    public const COURSE_CAPABILITY = 'local/elediaai_sources:selectactivities';

    /**
     * The context the help page runs in, after checking the current user may read it there.
     *
     * The caller has done require_login() (with the course, if one is given).
     *
     * @param \stdClass|null $course The course the page was opened from, or null.
     * @return \core\context The course context, or the system context.
     * @throws \required_capability_exception When the user may not read the help there.
     */
    public static function require_context(?\stdClass $course): \core\context {
        if ($course !== null && (int) $course->id !== SITEID) {
            $context = \core\context\course::instance((int) $course->id);
            require_capability(self::COURSE_CAPABILITY, $context);
            return $context;
        }

        $context = \core\context\system::instance();
        require_capability('moodle/site:config', $context);
        return $context;
    }
}

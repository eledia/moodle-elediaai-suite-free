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

/**
 * Who may see the AI Suite launcher and its overview page.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core\local;

use core\context\system;

/**
 * The one place that answers "does this user get the launcher?".
 *
 * The pill in the top bar, the entry in the primary navigation and the
 * overview page all ask here, so the three cannot disagree.
 *
 * The capability is a course-level one because teachers hold their role in a
 * course, not on the site. The question is therefore "on the site, or in any
 * course": a user who holds it nowhere gets no launcher.
 */
final class launcher_access {
    /** @var string The capability behind the launcher. */
    public const CAPABILITY = 'local/elediaai_core:uselauncher';

    /** @var array<int, bool> Answers for the current request, by user id. */
    private static array $answers = [];

    /**
     * Static-only.
     */
    private function __construct() {
    }

    /**
     * Whether the user may see the launcher and the overview.
     *
     * @param int|null $userid The user; null for the current one.
     * @return bool
     */
    public static function can_use(?int $userid = null): bool {
        global $USER;

        $userid ??= (int) $USER->id;
        if ($userid <= 0) {
            return false;
        }
        if (isset(self::$answers[$userid])) {
            return self::$answers[$userid];
        }

        $allowed = has_capability(self::CAPABILITY, system::instance(), $userid)
            || !empty(get_user_capability_course(self::CAPABILITY, $userid, false, '', '', 1));

        self::$answers[$userid] = $allowed;
        return $allowed;
    }

    /**
     * Where a "back to the suite" link should lead for the current user.
     *
     * Pages that learners can open (help, feedback) link back to the overview.
     * Learners do not get the overview any more, so for them the link leads to
     * the dashboard instead of an error page. Anything that is not the
     * overview address is returned unchanged.
     *
     * @param string $url The link target the page asked for.
     * @param string $label The link label the page asked for.
     * @return array{0: string, 1: string} URL and label to use.
     */
    public static function home_link(string $url, string $label): array {
        $overview = (new \moodle_url('/local/elediaai_core/index.php'))->out(false);
        if ($url !== $overview || self::can_use()) {
            return [$url, $label];
        }
        return [(new \moodle_url('/my/'))->out(false), get_string('myhome')];
    }

    /**
     * Forget the answers kept for this request.
     *
     * Called by tests that change roles between two questions.
     *
     * @return void
     */
    public static function reset_cache(): void {
        self::$answers = [];
    }
}

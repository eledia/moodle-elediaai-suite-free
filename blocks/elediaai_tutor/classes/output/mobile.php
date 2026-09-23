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

namespace block_elediaai_tutor\output;

/**
 * Moodle App content callbacks (see db/mobile.php).
 *
 * Each handler returns an Ionic template that hosts the standalone chat page
 * (view.php) in a core-iframe. The page itself enforces login, enrolment,
 * capability, the consent gate and all limits — these callbacks only build the
 * URL, so nothing here can leak data the page would not serve.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile {
    /**
     * Course options entry: course-scoped chat.
     *
     * @param array $args App arguments (courseid, appversion
     *                    etc. as provided by the delegate).
     * @return array Remote add-on content response.
     */
    public static function mobile_course_view(array $args): array {
        $courseid = (int) ($args['courseid'] ?? 0);

        // Per-course opt-in: the teacher decides by adding the tutor block to
        // the course. Courses without it get a notice instead of the chat.
        if ($courseid <= 0 || !\block_elediaai_tutor\local\widget::course_has_tutor($courseid)) {
            $notice = '<ion-card><ion-card-content>'
                . s(get_string('notenabledincourse', 'block_elediaai_tutor'))
                . '</ion-card-content></ion-card>';
            return [
                'templates' => [['id' => 'main', 'html' => $notice]],
                'javascript' => '',
                'otherdata' => [],
            ];
        }

        return self::iframe_response($courseid);
    }

    /**
     * Main menu entry: global chat.
     *
     * @param array $args App arguments.
     * @return array Remote add-on content response.
     */
    public static function mobile_global_view(array $args): array {
        return self::iframe_response(0);
    }

    /**
     * Build the core-iframe template response for the standalone page.
     *
     * @param int $courseid Course id, or 0 for global chat.
     * @return array{templates: array, javascript: string, otherdata: array}
     */
    private static function iframe_response(int $courseid): array {
        $params = ['embedded' => 1];
        if ($courseid > 0) {
            $params['courseid'] = $courseid;
        }
        $url = new \moodle_url('/blocks/elediaai_tutor/view.php', $params);

        // Core-iframe auto-logins same-site URLs inside the app.
        $html = '<core-iframe src="' . s($url->out(false)) . '"></core-iframe>';

        return [
            'templates' => [
                ['id' => 'main', 'html' => $html],
            ],
            'javascript' => '',
            'otherdata' => [],
        ];
    }
}

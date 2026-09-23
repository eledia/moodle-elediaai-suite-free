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
 * Moodle App integration for the eLeDia.ai Tutor block.
 *
 * Registers remote add-on handlers that render the standalone chat page
 * (view.php) inside the app via core-iframe — the app's iframe component
 * performs same-site auto-login itself, so the chat opens in-app with a valid
 * session instead of bouncing to the device browser.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Handlers follow the site toggles: disabling course or global chat removes
// the corresponding app entry point (the app refreshes its remote add-ons on
// login / pull-to-refresh; purge caches after changing the toggles).
$handlers = [];

if (\block_elediaai_tutor\local\security::course_chat_enabled()) {
    // A "Tutor" entry in the app's course options menu: opens the
    // course-scoped chat inside the app. Per-course opt-in (the tutor block
    // being present) is enforced by the content callback and view.php.
    $handlers['coursetutor'] = [
        'delegate' => 'CoreCourseOptionsDelegate',
        'method' => 'mobile_course_view',
        'displaydata' => [
            'title' => 'pluginname',
            'class' => 'block_elediaai-chat-course',
        ],
    ];
}

if (\block_elediaai_tutor\local\security::global_chat_enabled()) {
    // A main-menu entry for the global chat.
    $handlers['globaltutor'] = [
        'delegate' => 'CoreMainMenuDelegate',
        'method' => 'mobile_global_view',
        'displaydata' => [
            'title' => 'pluginname',
            'icon' => 'fas-comments',
            'class' => 'block_elediaai-chat-menu',
        ],
    ];
}

$addons = empty($handlers) ? [] : [
    'block_elediaai_tutor' => [
        'handlers' => $handlers,
        'lang' => [
            ['pluginname', 'block_elediaai_tutor'],
        ],
    ],
];

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

/**
 * Hook callbacks for the eLeDia.ai Tutor block.
 *
 * @package     block_elediaai_tutor
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        // Request remote erasure before Moodle invalidates the user's MCP token.
        'hook' => \core_user\hook\before_user_deleted::class,
        'callback' => \block_elediaai_tutor\observer::class . '::before_user_deleted',
        'priority' => 500,
    ],
    [
        // Offer the full-page tutor home as a selectable Moodle start page.
        'hook' => \core_user\hook\extend_default_homepage::class,
        'callback' => \block_elediaai_tutor\hook_callbacks::class . '::add_tutor_home_option',
        'priority' => 500,
    ],
    [
        // Render the learner-facing floating tutor outside Moodle block regions.
        'hook' => \core\hook\output\before_standard_top_of_body_html_generation::class,
        'callback' => \block_elediaai_tutor\hook_callbacks::class . '::inject_sitewide_tutor',
        'priority' => 490,
    ],
    [
        'hook' => \core\hook\output\before_standard_top_of_body_html_generation::class,
        'callback' => \block_elediaai_tutor\hook_callbacks::class . '::inject_admin_launcher',
        'priority' => 480,
    ],
    [
        // The "Configure <this tutor block>" action opens the Plugin Shell (the suitable settings UI)
        // instead of Moodle's generic block edit form. No-JS fallback: the control falls
        // back to a bui_editid page load, which this intercepts before any output.
        'hook' => \core\hook\output\before_http_headers::class,
        'callback' => \block_elediaai_tutor\hook_callbacks::class . '::redirect_block_config',
        'priority' => 500,
    ],
    [
        // With JS on, the "Configure" control opens a modal (core_block/edit) rather than
        // navigating; this loads the client-side helper that redirects it to the shell.
        'hook' => \core\hook\output\before_standard_top_of_body_html_generation::class,
        'callback' => \block_elediaai_tutor\hook_callbacks::class . '::configure_block_in_shell',
        'priority' => 470,
    ],
];

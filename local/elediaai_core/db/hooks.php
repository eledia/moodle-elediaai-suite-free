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
 * Hook callbacks for local_elediaai_core.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => \core\hook\output\before_standard_top_of_body_html_generation::class,
        'callback' => \local_elediaai_core\hook_callbacks::class . '::inject_launcher',
        // Run after the main LernHive launcher (priority 600) so the AI
        // launcher button sits to the right of the main one.
        'priority' => 500,
    ],
    [
        // Without this the suite is unreachable on any theme that hides the
        // launcher pill -- theme_elediaai does exactly that, by design.
        'hook' => \core\hook\navigation\primary_extend::class,
        'callback' => \local_elediaai_core\hook_callbacks::class . '::extend_primary_navigation',
    ],
    [
        'hook' => \core\hook\output\before_standard_head_html_generation::class,
        'callback' => \local_elediaai_core\hook_callbacks::class . '::tint_ai_chooser_icons',
    ],
];

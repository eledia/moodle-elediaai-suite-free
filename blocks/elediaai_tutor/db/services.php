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
 * External (AJAX) function definitions for the eLeDia.ai Tutor block.
 *
 * These functions are only ever called from the plugin's own AMD module over
 * Moodle's authenticated core/ajax channel (ajax => true, loginrequired honoured
 * by the calling page). They are deliberately not added to any external service,
 * so they cannot be invoked through the public Web Services API.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    // The tutor's own send: identical in shape to the shared endpoint, and one
    // parameter wider. The extra one names the surface the turn came from, so
    // the instance a learner has in front of them decides persona, mode, budget
    // and answer style — a course context alone cannot resolve any of them.
    'block_elediaai_tutor_send_message' => [
        'classname' => 'block_elediaai_tutor\external\send_message',
        'description' => 'Send one tutor chat turn from a named surface and return the rendered answer.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'block/elediaai_tutor:use',
        'loginrequired' => true,
    ],
    'block_elediaai_tutor_give_consent' => [
        'classname' => 'block_elediaai_tutor\external\give_consent',
        'description' => 'Record the current user\'s acknowledgement of the privacy guidelines.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'block/elediaai_tutor:use',
        'loginrequired' => true,
    ],
    'block_elediaai_tutor_set_ltm' => [
        'classname' => 'block_elediaai_tutor\external\set_ltm',
        'description' => 'Set the current user\'s long-term memory opt-in preference.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'block/elediaai_tutor:use',
        'loginrequired' => true,
    ],
    'block_elediaai_tutor_delete_my_data' => [
        'classname' => 'block_elediaai_tutor\external\delete_my_data',
        'description' => 'Delete all of the current user\'s own tutor data.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'block/elediaai_tutor:deleteownhistory',
        'loginrequired' => true,
    ],
    'block_elediaai_tutor_generate_copilot_analysis' => [
        'classname' => 'block_elediaai_tutor\external\generate_copilot_analysis',
        'description' => 'Generate a teacher-facing AI analysis of the course\'s logged tutor questions.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'block/elediaai_tutor:viewreports',
        'loginrequired' => true,
    ],
];

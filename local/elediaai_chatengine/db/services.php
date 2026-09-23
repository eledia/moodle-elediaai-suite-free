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
 * External functions of the AI chat engine.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_elediaai_chatengine_send_message' => [
        'classname' => 'local_elediaai_chatengine\external\send_message',
        'description' => 'Send one chat turn from a placement and return the rendered answer.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_elediaai_chatengine_load_history' => [
        'classname' => 'local_elediaai_chatengine\external\load_history',
        'description' => 'Load the turns of a conversation.',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_elediaai_chatengine_list_conversations' => [
        'classname' => 'local_elediaai_chatengine\\external\\list_conversations',
        'description' => 'List a person\'s conversations in one placement.',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_elediaai_chatengine_clear_conversation' => [
        'classname' => 'local_elediaai_chatengine\external\clear_conversation',
        'description' => 'Delete the turns of a conversation at its owner\'s request.',
        'type' => 'write',
        'ajax' => true,
    ],
];

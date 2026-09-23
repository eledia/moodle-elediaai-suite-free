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
 * Streaming endpoint for one chat turn.
 *
 * Moodle's external API is request and response, so an answer can only be shown
 * while it is being produced through a separate endpoint that holds the
 * connection open. Every gate is the same as on the buffered path, because both
 * go through {@see \local_elediaai_chatengine\chat_service} — the rules cannot
 * drift apart because there is only one set of them.
 *
 * What this file owns is its own front door: the parameters, the session key,
 * and which placement is being addressed. The transport — headers, session
 * lock, flushing, and the frame names of the backend contract — belongs to
 * {@see \local_elediaai_chatengine\local\stream_runner}, so a placement that
 * keeps its own streaming endpoint inherits the protocol instead of writing a
 * second copy of it.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_elediaai_chatengine\local\hints;
use local_elediaai_chatengine\local\stream_runner;
use local_elediaai_chatengine\placement\registry;

require_once(__DIR__ . '/../../config.php');

$component = required_param('component', PARAM_COMPONENT);
$message = required_param('message', PARAM_RAW);
$instanceid = optional_param('instanceid', 0, PARAM_INT);
$threadid = optional_param('threadid', 0, PARAM_INT);
$newthread = optional_param('newthread', 0, PARAM_BOOL);

// The hints come from the engine's own vocabulary rather than from a list kept
// here, so this path and the buffered endpoint cannot drift into accepting
// different things - which is how the streamed turn worked while the buffered
// one was refused for an unexpected key.
$clienthints = [];
foreach (hints::names() as $hintname) {
    $clienthints[$hintname] = optional_param($hintname, '', hints::PARAM_TYPE);
}

require_login();
require_sesskey();

$placement = registry::require_placement($component);
$context = $placement->context($instanceid);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/elediaai_chatengine/stream.php'));

stream_runner::run(
    $component,
    $instanceid,
    (int) $USER->id,
    $message,
    $context,
    $threadid > 0 ? $threadid : null,
    $clienthints,
    [],
    $newthread
);

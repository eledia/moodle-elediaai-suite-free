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
 * Streaming endpoint for one tutor turn.
 *
 * The streamed twin of {@see \block_elediaai_tutor\external\send_message}, and
 * it exists for the same reason: the turn has to say which surface it came
 * from, so the instance a learner has in front of them decides the persona, the
 * mode, the budget and the answer style. A placement that moved only the
 * buffered path would answer differently depending on whether streaming is
 * switched on — the exact failure this whole change came from.
 *
 * Everything below the front door belongs to the engine:
 * {@see \local_elediaai_chatengine\local\stream_runner} owns the headers, the
 * session lock, the flushing and the frame names of the backend contract, so
 * there is only ever one implementation of the protocol.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_elediaai_tutor\chatengine\placement;
use local_elediaai_chatengine\local\hints;
use local_elediaai_chatengine\local\stream_runner;

require_once(__DIR__ . '/../../config.php');

$contextid = required_param('contextid', PARAM_INT);
$message = required_param('message', PARAM_RAW);
$courseid = max(0, optional_param('courseid', 0, PARAM_INT));
$threadid = optional_param('threadid', 0, PARAM_INT);
$newthread = optional_param('newthread', 0, PARAM_BOOL);

// Read from the engine's vocabulary, so this path accepts exactly what the
// buffered endpoint does.
$clienthints = [];
foreach (hints::names() as $hintname) {
    $clienthints[$hintname] = optional_param($hintname, '', hints::PARAM_TYPE);
}

require_login();
require_sesskey();

$scope = new placement();
// The conversation's context, not the surface's: the turn is recorded against
// the course scope, and that is what the page is set to.
$context = $scope->context($courseid);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/blocks/elediaai_tutor/stream.php'));

// Proven before it is believed, and given back afterwards.
placement::use_surface($contextid, $courseid);
try {
    stream_runner::run(
        placement::component(),
        $courseid,
        (int) $USER->id,
        $message,
        $context,
        $threadid > 0 ? $threadid : null,
        $clienthints,
        [],
        $newthread
    );
} finally {
    placement::forget_surface();
}

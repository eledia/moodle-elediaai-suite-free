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
 * Per-course activity selection for ingestion.
 *
 * A teacher-facing page, so it uses the standard in-course layout rather than
 * the plugin's admin shell — teachers stay in their course surroundings.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_elediaai_sources\course_gate;
use local_elediaai_sources\output\activities_page;

$courseid = required_param('id', PARAM_INT);
$course = get_course($courseid);

require_login($course);
$context = \core\context\course::instance($course->id);
require_capability('local/elediaai_sources:selectactivities', $context);

// While the test-phase lock is on, the selection is centrally frozen and the
// page (like the form field and the navigation entry) is unavailable.
if (course_gate::marking_locked()) {
    throw new moodle_exception(
        'nopermissions',
        'error',
        '',
        get_string('lockcoursemarking', 'local_elediaai_sources')
    );
}

$PAGE->set_url(new moodle_url('/local/elediaai_sources/activities.php', ['id' => $course->id]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('activities_title', 'local_elediaai_sources'));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('activities_title', 'local_elediaai_sources'));

echo $OUTPUT->render_from_template(
    'local_elediaai_sources/activities_page',
    (new activities_page($course))->export_for_template($OUTPUT)
);

echo $OUTPUT->footer();

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
 * Dry run for one activity: what would be sent to the index, and why.
 *
 * Reachable two ways, deliberately. A teacher reaches it from the course and
 * needs the course context; an administrator diagnosing the pipeline holds
 * :reindex at system level and may not be enrolled anywhere. The test-phase
 * lock hides it from teachers along with the selection page, but not from
 * administrators — the lock governs who decides, not who may look, and a test
 * phase is exactly when looking matters.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_elediaai_sources\course_gate;
use local_elediaai_sources\output\preview_page;

$cmid = required_param('cmid', PARAM_INT);

// Log in before the lookup: the id is typed in by hand on the reindex page,
// and a guest probing ids should meet the login form, not "no such module".
require_login();

[$course, $cm] = get_course_and_cm_from_cmid($cmid);
$modulecontext = \core\context\module::instance($cm->id);

$isadmin = has_capability('local/elediaai_sources:reindex', \core\context\system::instance());

if (!$isadmin) {
    require_login($course, false, $cm);
    require_capability('local/elediaai_sources:selectactivities', $modulecontext);

    if (course_gate::marking_locked()) {
        throw new moodle_exception(
            'nopermissions',
            'error',
            '',
            get_string('lockcoursemarking', 'local_elediaai_sources')
        );
    }
}

// Set the module, not merely its context. With only the context set, the page
// keeps the site as its course while claiming a module context, and building the
// navigation for that combination dereferences the module the page never got.
// The teacher path was spared: require_login($course, false, $cm) sets both.
$PAGE->set_cm($cm, $course);
$PAGE->set_url(new moodle_url('/local/elediaai_sources/preview.php', ['cmid' => $cm->id]));
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('preview_title', 'local_elediaai_sources'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->add_body_class('path-local-elediaai_sources');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('preview_title', 'local_elediaai_sources'));
echo html_writer::tag('p', get_string('preview_intro', 'local_elediaai_sources'), ['class' => 'text-muted']);

echo $OUTPUT->render_from_template(
    'local_elediaai_sources/preview_page',
    (new preview_page($cm))->export_for_template($OUTPUT)
);

echo $OUTPUT->footer();

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
 * Teacher-Copilot: die KI-Deutung der Kurs-Einblicke.
 *
 * Diese Seite war der Frage-Report des Tutors. Die Zahlen und Listen stehen
 * seit dem 19.09.2026 in den Kurs-Einblicken von local_elediaai_core -- der
 * Turn-Speicher deckt alle Placements ab, nicht nur diesen Block, und der alte
 * Frage-Log wurde seit dem 27.08.2026 nicht mehr gefuellt.
 *
 * Geblieben ist der Copilot, und zwar hier: er fuehrt einen echten Chat-Turn
 * ueber die Engine, mit der Einwilligung und der Ratenbegrenzung des Tutors.
 * Der Kern kennt seine Plugins nicht und darf diesen Turn nicht ausloesen --
 * deshalb zeigen zwei Flaechen zwei Dinge, statt eine Abhaengigkeit in die
 * falsche Richtung zu legen.
 *
 * @package     block_elediaai_tutor
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);

$course = get_course($courseid);
require_login($course, false);
$context = \core\context\course::instance($course->id);

$allowed = has_capability('local/elediaai_core:viewcourseinsights', $context)
    || has_capability('block/elediaai_tutor:viewreports', $context);
if (!$allowed) {
    throw new required_capability_exception($context, 'block/elediaai_tutor:viewreports', 'nopermissions', '');
}

$url = new moodle_url('/blocks/elediaai_tutor/report.php', ['courseid' => $course->id]);
$insightsurl = new moodle_url('/local/elediaai_core/course_insights.php', ['courseid' => $course->id]);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('copilot_page_title', 'block_elediaai_tutor'));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('copilot_page_title', 'block_elediaai_tutor'));

echo html_writer::div(
    html_writer::tag('p', get_string('copilot_page_intro', 'block_elediaai_tutor'))
        . html_writer::link(
            $insightsurl,
            get_string('copilot_open_insights', 'block_elediaai_tutor'),
            ['class' => 'btn btn-secondary']
        ),
    'eat-admin-intro'
);

echo $OUTPUT->render_from_template('block_elediaai_tutor/copilot_section', ['courseid' => $course->id]);
$PAGE->requires->js_call_amd('block_elediaai_tutor/copilot_report', 'init', [$course->id]);

echo $OUTPUT->footer();

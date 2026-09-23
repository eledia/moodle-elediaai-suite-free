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
 * Kurs-Einblicke: was die Lernenden beschaeftigt hat.
 *
 * Nachfolger von blocks/elediaai_tutor/report.php. Der Bericht gehoerte dem
 * Block, als der Block die Engine war; seit dem 27.08.2026 ist er ein Placement
 * unter mehreren, und die Datengrundlage deckt alle ab.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_elediaai_core\local\audit_page;
use local_elediaai_core\local\insights;
use local_elediaai_core\local\insights_page;
use local_elediaai_core\output\plugin_page;
use local_elediaai_core\output\plugin_shell;

$courseid = required_param('courseid', PARAM_INT);
$days = optional_param('days', insights::DEFAULT_DAYS, PARAM_INT);
if (!in_array($days, [7, 30, 180], true)) {
    $days = insights::DEFAULT_DAYS;
}

$course = get_course($courseid);
require_login($course);
$context = \core\context\course::instance($courseid);

// Die eigene Capability, und zusaetzlich die des Tutors: bestehende
// Rollenzuweisungen sollen nach dem Umzug der Seite nicht ins Leere laufen.
$allowed = has_capability('local/elediaai_core:viewcourseinsights', $context)
    || has_capability('block/elediaai_tutor:viewreports', $context);
if (!$allowed) {
    throw new required_capability_exception(
        $context,
        'local/elediaai_core:viewcourseinsights',
        'nopermissions',
        ''
    );
}

$url = new moodle_url('/local/elediaai_core/course_insights.php', [
    'courseid' => $courseid,
    'days' => $days,
]);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('surface_insights_title', 'local_elediaai_core'));
$PAGE->set_heading('');
$PAGE->requires->css('/local/elediaai_core/styles.css');

$shell = [
    'name' => get_string('shell_name', 'local_elediaai_core'),
    'tagline' => get_string('surface_insights_title', 'local_elediaai_core'),
    'subtitle' => format_string($course->fullname),
    'homeurl' => (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false),
    'homelabel' => get_string('insights_backtocourse', 'local_elediaai_core'),
] + plugin_shell::action_slots(
    'local_elediaai_core',
    false,
    $url,
    get_string('shell_help_label', 'local_elediaai_core')
);

echo $OUTPUT->header();
plugin_page::open($shell, plugin_page::MODIFIER_DEFAULT);
plugin_shell::content_open();
echo audit_page::css();

$summary = insights::summary($courseid, $days);

echo html_writer::start_div('lh-ai-audit-stack');

// Kopf mit Zeitraumwahl.
// Keine eigene Ueberschrift: die Huelle setzt schon eine h1 mit demselben
// Namen und dem Kurs darunter. Vorher standen zwei h1 auf der Seite, mit
// verschiedenem Wortlaut.
$head = html_writer::tag('p', get_string('insights_lead', 'local_elediaai_core'), [
    'class' => 'eai-audit-panel__body',
]);
$periods = '';
foreach ([7 => 'insights_period_7', 30 => 'insights_period_30', 180 => 'insights_period_180'] as $value => $stringid) {
    $periodurl = new moodle_url('/local/elediaai_core/course_insights.php', [
        'courseid' => $courseid,
        'days' => $value,
    ]);
    $classes = 'lh-ai-audit__quickfilter' . ($days === $value ? ' is-active' : '');
    $periods .= html_writer::link($periodurl, get_string($stringid, 'local_elediaai_core'), [
        'class' => $classes,
    ]);
}
$head .= html_writer::div($periods, 'lh-ai-audit__quickfilters');

// Wegzeile: zurueck in den Kurs, und -- wer mehrere betreut -- zur Kursauswahl.
// Ohne sie ist diese Seite eine Sackgasse, die man nur ueber das Kursmenue
// wieder verlaesst.
$wege = html_writer::link(
    new moodle_url('/course/view.php', ['id' => $courseid]),
    get_string('insights_backtocourse', 'local_elediaai_core'),
    ['class' => 'eai-waypoint']
);
if (\local_elediaai_core\local\audit_config::can_view(\core\context\system::instance())) {
    $wege .= html_writer::link(
        new moodle_url('/local/elediaai_core/audit_didactic.php', ['days' => $days]),
        get_string('insights_othercourse', 'local_elediaai_core'),
        ['class' => 'eai-waypoint']
    );
}
$head .= html_writer::div($wege, 'eai-waypoints');

echo html_writer::div($head, 'eai-insights-head');

echo insights_page::metrics($summary, $days);

if ($summary->total === 0) {
    echo html_writer::div(
        html_writer::tag('p', get_string('insights_nothing_yet', 'local_elediaai_core'), [
            'class' => 'eai-audit-panel__empty',
        ]),
        'eai-audit-panel'
    );
} else {
    echo insights_page::gaps(insights::gaps($courseid, $days, 8), $context);
    echo insights_page::unclear(insights::unclear($courseid, $days, 6));
    echo insights_page::trend(insights::per_day($courseid, 14));
    echo insights_page::verbatim(
        insights::recent($courseid, $days, 20),
        insights::retention_days()
    );
}

echo html_writer::end_div();

plugin_shell::content_close();
plugin_page::close();
echo $OUTPUT->footer();

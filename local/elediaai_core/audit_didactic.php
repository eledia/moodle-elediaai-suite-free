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
 * Kursauswahl fuer die Kurs-Einblicke.
 *
 * Diese Seite zeigte frueher eine site-weite Zaehlung von Zusammenfassungen und
 * Erklaerungen je Kontext -- eine schwaechere Fassung derselben Idee, die die
 * Kurs-Einblicke jetzt richtig beantworten. Sie ist deshalb das geworden, was
 * der Weg dorthin brauchte und nie hatte: welcher meiner Kurse hat ueberhaupt
 * etwas zu zeigen.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_elediaai_core\local\audit_config;
use local_elediaai_core\local\audit_page;
use local_elediaai_core\local\insights;
use local_elediaai_core\output\plugin_page;
use local_elediaai_core\output\plugin_shell;
use local_elediaai_core\output\section_nav;

require_login();

$context = \core\context\system::instance();
if (!audit_config::can_view($context)) {
    throw new required_capability_exception($context, 'moodle/ai:viewaiusagereport', 'nopermissions', '');
}

$days = optional_param('days', insights::DEFAULT_DAYS, PARAM_INT);
if (!in_array($days, [7, 30, 180], true)) {
    $days = insights::DEFAULT_DAYS;
}

$url = new moodle_url('/local/elediaai_core/audit_didactic.php', ['days' => $days]);
$indexurl = new moodle_url('/local/elediaai_core/index.php');

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('surface_insights_title', 'local_elediaai_core'));
$PAGE->set_heading('');
$PAGE->requires->css('/local/elediaai_core/styles.css');

$shell = [
    'name' => get_string('shell_name', 'local_elediaai_core'),
    'tagline' => get_string('surface_insights_title', 'local_elediaai_core'),
    'subtitle' => get_string('chooser_page_intro', 'local_elediaai_core'),
    'homeurl' => $indexurl->out(false),
    'homelabel' => get_string('feature_backtosuite', 'local_elediaai_core'),
    'sectionnav' => section_nav::render_for_feature('audit', section_nav::FEATURE_AUDIT_DIDACTIC),
] + audit_page::actions($context);

echo $OUTPUT->header();
plugin_page::open($shell, plugin_page::MODIFIER_DEFAULT);
plugin_shell::content_open();
echo audit_page::css();

echo html_writer::start_div('lh-ai-audit-stack');

// Zeitraumwahl, dieselbe Form wie auf den Einblicken selbst.
$periods = '';
foreach ([7 => 'insights_period_7', 30 => 'insights_period_30', 180 => 'insights_period_180'] as $value => $stringid) {
    $periodurl = new moodle_url('/local/elediaai_core/audit_didactic.php', ['days' => $value]);
    $periods .= html_writer::link($periodurl, get_string($stringid, 'local_elediaai_core'), [
        'class' => 'lh-ai-audit__quickfilter' . ($days === $value ? ' is-active' : ''),
    ]);
}

$panel = audit_page::section_heading('chooser_eyebrow', 'chooser_title', 'chooser_intro');
$panel .= html_writer::div($periods, 'lh-ai-audit__quickfilters');

$courses = insights::courses_with_activity($days, 50);
if ($courses === []) {
    $panel .= html_writer::div(
        get_string('chooser_empty', 'local_elediaai_core'),
        'eai-audit-panel__empty'
    );
} else {
    $rows = '';
    foreach ($courses as $row) {
        try {
            $course = get_course($row->courseid);
        } catch (\Throwable $e) {
            continue;
        }
        $coursecontext = \core\context\course::instance($row->courseid, IGNORE_MISSING);
        if (!$coursecontext) {
            continue;
        }
        // Wer das Audit site-weit sehen darf, sieht jeden Kurs; sonst nur die
        // eigenen. Die Auswahl darf nicht mehr zeigen als die Zielseite.
        $allowed = audit_config::can_view($context)
            || has_capability('local/elediaai_core:viewcourseinsights', $coursecontext);
        if (!$allowed) {
            continue;
        }

        $target = new moodle_url('/local/elediaai_core/course_insights.php', [
            'courseid' => $row->courseid,
            'days' => $days,
        ]);

        $covered = max(0, 100 - $row->uncoveredpct);
        $bar = html_writer::div('', 'eai-splitbar__covered', ['style' => 'width: ' . $covered . '%;']);
        $bar .= html_writer::div('', 'eai-splitbar__open', ['style' => 'width: ' . $row->uncoveredpct . '%;']);
        $track = html_writer::div($bar, 'eai-splitbar', [
            'role' => 'img',
            'aria-label' => get_string('insights_metric_grounded_label', 'local_elediaai_core', [
                'covered' => $covered,
                'open' => $row->uncoveredpct,
            ]),
        ]);

        $rows .= html_writer::link(
            $target,
            html_writer::div(
                html_writer::div(format_string($course->fullname), 'eai-chooser__name')
                    . html_writer::div(
                        get_string('chooser_meta', 'local_elediaai_core', [
                            'questions' => $row->questions,
                            'askers' => $row->askers,
                        ]),
                        'eai-chooser__meta'
                    ),
                'eai-chooser__text'
            )
                . html_writer::div($track, 'eai-chooser__bar')
                . html_writer::div(
                    get_string('chooser_uncovered', 'local_elediaai_core', $row->uncoveredpct),
                    'eai-chooser__share'
                ),
            ['class' => 'eai-chooser__row']
        );
    }

    $panel .= $rows !== ''
        ? html_writer::div($rows, 'eai-chooser')
        : html_writer::div(get_string('chooser_empty', 'local_elediaai_core'), 'eai-audit-panel__empty');
}

echo html_writer::div($panel, 'eai-audit-panel');
echo html_writer::end_div();

plugin_shell::content_close();
plugin_page::close();
echo $OUTPUT->footer();

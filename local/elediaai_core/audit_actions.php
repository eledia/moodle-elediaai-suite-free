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
 * Handlungsprotokoll der KI -- was sie getan hat, in wessen Auftrag.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use core_reportbuilder\system_report_factory;
use local_elediaai_core\local\actions;
use local_elediaai_core\local\audit_page;
use local_elediaai_core\output\plugin_page;
use local_elediaai_core\output\plugin_shell;
use local_elediaai_core\output\section_nav;
use local_elediaai_core\reportbuilder\local\systemreports\aiactions;

require_login();

$courseid = optional_param('courseid', 0, PARAM_INT);
$quick = optional_param('actionquick', '', PARAM_ALPHANUMEXT);
if (!in_array($quick, ['', 'write', 'failed'], true)) {
    $quick = '';
}

// Auf Kursebene vergebbar: eine Lehrkraft darf sehen, was die KI in ihrem Kurs
// getan hat, ohne die ganze Website zu sehen.
if ($courseid > 0) {
    $course = get_course($courseid);
    $context = \core\context\course::instance($courseid);
    require_login($course);
} else {
    $context = \core\context\system::instance();
}
require_capability('local/elediaai_core:viewaiactions', $context);

$url = new moodle_url('/local/elediaai_core/audit_actions.php');
if ($courseid > 0) {
    $url->param('courseid', $courseid);
}
if ($quick !== '') {
    $url->param('actionquick', $quick);
}

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('surface_actions_title', 'local_elediaai_core'));
$PAGE->set_heading('');
$PAGE->requires->css('/local/elediaai_core/styles.css');
$PAGE->requires->js_call_amd('local_elediaai_core/audit_table', 'init');

$shell = [
    'name' => get_string('shell_name', 'local_elediaai_core'),
    'tagline' => get_string('surface_actions_title', 'local_elediaai_core'),
    'subtitle' => get_string('action_page_intro', 'local_elediaai_core'),
    'homeurl' => (new moodle_url('/local/elediaai_core/index.php'))->out(false),
    'homelabel' => get_string('feature_backtosuite', 'local_elediaai_core'),
    'sectionnav' => section_nav::render_for_feature('audit', section_nav::FEATURE_AUDIT_ACTIONS),
] + plugin_shell::action_slots(
    'local_elediaai_core',
    false,
    $url,
    get_string('shell_help_label', 'local_elediaai_core')
);

echo $OUTPUT->header();
plugin_page::open($shell, plugin_page::MODIFIER_WIDE);
plugin_shell::content_open();
echo audit_page::css();

$summary = actions::summary($courseid);

echo html_writer::start_div('lh-ai-audit-stack');

$panel = audit_page::section_heading(
    'action_eyebrow',
    'action_panel_title',
    'action_panel_intro'
);
$metrics = audit_page::metric(
    get_string('action_metric_total', 'local_elediaai_core'),
    number_format($summary->total, 0, ',', ' ')
);
// Der Schreibanteil ist die Zahl, wegen der es diese Seite gibt -- als
// Aufteilung gezeichnet, nicht nur geschrieben.
$metrics .= audit_page::metric(
    get_string('action_metric_writes', 'local_elediaai_core'),
    number_format($summary->writes, 0, ',', ' '),
    null,
    audit_page::split_bar(
        100 - (int) round($summary->writepct),
        get_string('action_metric_writes_hint', 'local_elediaai_core', format_float($summary->writepct, 1))
    ),
    'warn'
);
$metrics .= audit_page::metric(
    get_string('action_metric_failed', 'local_elediaai_core'),
    number_format($summary->failed, 0, ',', ' '),
    null,
    null,
    $summary->failed > 0 ? 'warn' : 'quiet'
);
$metrics .= audit_page::metric(
    get_string('action_metric_people', 'local_elediaai_core'),
    number_format($summary->people, 0, ',', ' ')
);
$panel .= html_writer::div($metrics, 'eai-metrics');
echo html_writer::div($panel, 'eai-audit-panel');

echo html_writer::start_div('lh-ai-audit eai-datatable');
echo audit_page::section_heading(
    'action_table_eyebrow',
    'action_table_title',
    'action_table_intro'
);
echo audit_page::quickfilter_bar($url, 'actionquick', [
    '' => get_string('audit_quick_all', 'local_elediaai_core'),
    'write' => get_string('action_quick_write', 'local_elediaai_core'),
    'failed' => get_string('audit_quick_failed', 'local_elediaai_core'),
], $quick);
echo system_report_factory::create(aiactions::class, $context, '', '', 0, ['courseid' => $courseid])->output();
echo html_writer::end_div();

$days = actions::retention_days();
echo html_writer::tag(
    'p',
    $days > 0
        ? get_string('action_retention_note', 'local_elediaai_core', $days)
        : get_string('action_retention_note_forever', 'local_elediaai_core'),
    ['class' => 'eai-audit-panel__note']
);

echo html_writer::end_div();

plugin_shell::content_close();
plugin_page::close();
echo $OUTPUT->footer();

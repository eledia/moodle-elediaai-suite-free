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
 * Technical AI audit.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use core_reportbuilder\system_report_factory;
use local_elediaai_core\local\audit_config;
use local_elediaai_core\local\audit_page;
use local_elediaai_core\output\plugin_page;
use local_elediaai_core\output\plugin_shell;
use local_elediaai_core\output\section_nav;
use local_elediaai_core\reportbuilder\local\systemreports\audit as audit_report;
use local_elediaai_core\reportbuilder\local\systemreports\turns as turns_report;

require_login();

$context = \core\context\system::instance();
if (!audit_config::feature_available()) {
    audit_config::render_unavailable_page($PAGE, $OUTPUT, new moodle_url('/local/elediaai_core/audit_technical.php'));
}
if (!audit_config::can_view($context)) {
    throw new required_capability_exception($context, 'moodle/ai:viewaiusagereport', 'nopermissions', '');
}

$url = new moodle_url('/local/elediaai_core/audit_technical.php');
$indexurl = new moodle_url('/local/elediaai_core/index.php');
$quickfilter = optional_param('quick', '', PARAM_ALPHANUMEXT);
$allowedquickfilters = ['', 'participants', 'generate_text', 'summarise_text', 'explain_text', 'failed'];
if (!in_array($quickfilter, $allowedquickfilters, true)) {
    $quickfilter = '';
}
if ($quickfilter !== '') {
    $url->param('quick', $quickfilter);
}
$suitequick = optional_param('suitequick', '', PARAM_ALPHANUMEXT);
$allowedsuitefilters = ['', 'chat', 'generate_text', 'ungrounded', 'failed'];
if (!in_array($suitequick, $allowedsuitefilters, true)) {
    $suitequick = '';
}
if ($suitequick !== '') {
    $url->param('suitequick', $suitequick);
}

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('surface_requests_title', 'local_elediaai_core'));
$PAGE->set_heading('');

$PAGE->requires->css('/local/elediaai_core/styles.css');
$PAGE->requires->js_call_amd('local_elediaai_core/audit_preview', 'init');
$PAGE->requires->js_call_amd('local_elediaai_core/audit_table', 'init');

$shell = [
    'name' => get_string('shell_name', 'local_elediaai_core'),
    'tagline' => get_string('surface_requests_title', 'local_elediaai_core'),
    'subtitle' => get_string('audit_requests_page_intro', 'local_elediaai_core'),
    'homeurl' => $indexurl->out(false),
    'homelabel' => get_string('feature_backtosuite', 'local_elediaai_core'),
    'sectionnav' => section_nav::render_for_feature('audit', section_nav::FEATURE_AUDIT_TECHNICAL),
] + audit_page::actions($context);

echo $OUTPUT->header();
plugin_page::open($shell, plugin_page::MODIFIER_WIDE);
plugin_shell::content_open();
echo audit_page::css();

// Kein Zahlenblock mehr: derselbe stand auf der Uebersicht und schob hier
// die beiden Tabellen unter den Falz. Diese Seite hat eine Aufgabe, und das
// sind die Anfragen selbst.
echo html_writer::start_div('lh-ai-audit-stack');

// Zwei Berichte, nicht einer: die Suite-Turns aus dem eigenen Speicher und
// Moodles Register fuer alles, was nicht zur Suite gehoert. Eine Vereinigung
// haette dieselbe Aktion doppelt gezaehlt, solange local_aitransparency_rec
// keine registerid traegt (Bestandsaufnahme, 05.09.2026).
$basisurl = new moodle_url('/local/elediaai_core/audit_technical.php');

echo html_writer::start_div('lh-ai-audit eai-datatable');
echo audit_page::section_heading(
    'audit_suite_report_eyebrow',
    'audit_suite_report_title',
    'audit_suite_report_intro'
);
echo audit_page::quickfilter_bar($basisurl, 'suitequick', [
    '' => get_string('audit_quick_all', 'local_elediaai_core'),
    'chat' => get_string('audit_quick_chat', 'local_elediaai_core'),
    'generate_text' => get_string('audit_action_generate_text', 'local_elediaai_core'),
    'ungrounded' => get_string('audit_quick_ungrounded', 'local_elediaai_core'),
    'failed' => get_string('audit_quick_failed', 'local_elediaai_core'),
], $suitequick);
echo system_report_factory::create(turns_report::class, $context)->output();
echo html_writer::end_div();

echo html_writer::start_div('lh-ai-audit eai-datatable');
echo audit_page::section_heading(
    'audit_core_report_eyebrow',
    'audit_core_report_title',
    'audit_core_report_intro'
);
echo audit_page::quickfilter_bar($basisurl, 'quick', [
    '' => get_string('audit_quick_all', 'local_elediaai_core'),
    'participants' => get_string('audit_quick_participants', 'local_elediaai_core'),
    'generate_text' => get_string('audit_action_generate_text', 'local_elediaai_core'),
    'summarise_text' => get_string('audit_action_summarise_text', 'local_elediaai_core'),
    'explain_text' => get_string('audit_action_explain_text', 'local_elediaai_core'),
    'failed' => get_string('audit_quick_failed', 'local_elediaai_core'),
], $quickfilter);
echo system_report_factory::create(audit_report::class, $context)->output();
echo html_writer::end_div();
echo html_writer::end_div();

plugin_shell::content_close();
plugin_page::close();
echo $OUTPUT->footer();

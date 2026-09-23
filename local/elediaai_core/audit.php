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
 * AI audit overview.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_elediaai_core\local\audit_config;
use local_elediaai_core\local\audit_page;
use local_elediaai_core\output\lucide_icon;
use local_elediaai_core\output\plugin_page;
use local_elediaai_core\output\plugin_shell;
use local_elediaai_core\output\section_nav;

require_login();

$context = \core\context\system::instance();
if (!audit_config::feature_available()) {
    audit_config::render_unavailable_page($PAGE, $OUTPUT, new moodle_url('/local/elediaai_core/audit.php'));
}
if (!audit_config::can_view($context)) {
    throw new required_capability_exception($context, 'moodle/ai:viewaiusagereport', 'nopermissions', '');
}

$url = new moodle_url('/local/elediaai_core/audit.php');
$indexurl = new moodle_url('/local/elediaai_core/index.php');

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('audit_heading', 'local_elediaai_core'));
$PAGE->set_heading('');

$PAGE->requires->css('/local/elediaai_core/styles.css');

$shell = [
    'name' => get_string('shell_name', 'local_elediaai_core'),
    'tagline' => get_string('audit_heading', 'local_elediaai_core'),
    'subtitle' => get_string('audit_overview_intro', 'local_elediaai_core'),
    'homeurl' => $indexurl->out(false),
    'homelabel' => get_string('feature_backtosuite', 'local_elediaai_core'),
    'sectionnav' => section_nav::render_for_feature('audit', section_nav::FEATURE_AUDIT),
] + audit_page::actions($context);

echo $OUTPUT->header();
plugin_page::open($shell, plugin_page::MODIFIER_DEFAULT);
plugin_shell::content_open();
echo audit_page::css();

// Erst die vier Wege, dann die Zahlen. Vorher stand der Zustandsblock oben
// und man musste an ihm vorbeiscrollen, um zu erfahren, wohin es ueberhaupt
// geht -- obwohl genau das die Aufgabe dieser Seite ist.
//
// Jede Karte tragt oben den Geltungsbereich und darunter den Namen der
// Flaeche. Der Name ist derselbe wie im Reiter und im Seitentitel: ein Ziel,
// ein Wortlaut. Vorher hiess dieselbe Flaeche "Kurs-Einblicke" im Reiter,
// "Was die Lernenden gefragt haben" auf der Karte und "Welcher Kurs?" im Titel.
$einstiege = [
    [
        'url' => new moodle_url('/local/elediaai_core/audit_didactic.php'),
        'eyebrow' => 'entry_scope_course',
        'title' => 'surface_insights_title',
        'text' => 'entry_insights_text',
        'icon' => 'chart-simple',
    ],
];
if (has_capability('local/elediaai_core:viewaiactions', $context)) {
    $einstiege[] = [
        'url' => new moodle_url('/local/elediaai_core/audit_actions.php'),
        'eyebrow' => 'entry_scope_site',
        'title' => 'surface_actions_title',
        'text' => 'entry_actions_text',
        'icon' => 'shield-alt',
    ];
}
$einstiege[] = [
    'url' => new moodle_url('/local/elediaai_core/audit_technical.php'),
    'eyebrow' => 'entry_scope_site',
    'title' => 'surface_requests_title',
    'text' => 'entry_requests_text',
    'icon' => 'list-check',
];
if (has_capability('moodle/site:config', $context)) {
    $einstiege[] = [
        'url' => new moodle_url('/local/elediaai_core/audit_settings.php'),
        'eyebrow' => 'entry_scope_admin',
        'title' => 'surface_settings_title',
        'text' => 'entry_settings_text',
        'icon' => 'sliders',
    ];
}

echo html_writer::start_div('lh-ai-audit-stack');

$karten = '';
foreach ($einstiege as $einstieg) {
    $inhalt = html_writer::div(lucide_icon::render($einstieg['icon']), 'eai-entry__icon');
    $inhalt .= html_writer::div(
        get_string($einstieg['eyebrow'], 'local_elediaai_core'),
        'eai-entry__eyebrow'
    );
    $inhalt .= html_writer::tag(
        'span',
        get_string($einstieg['title'], 'local_elediaai_core'),
        ['class' => 'eai-entry__title']
    );
    $inhalt .= html_writer::div(
        get_string($einstieg['text'], 'local_elediaai_core'),
        'eai-entry__text'
    );
    $karten .= html_writer::link($einstieg['url'], $inhalt, ['class' => 'eai-entry']);
}
echo html_writer::div($karten, 'eai-entries');

// Darunter der Zustand: einmal aus Moodles Register (alles, immer), einmal aus
// dem Turn-Protokoll der Suite (30 Tage). Beide Flaechen sagen das jetzt
// selbst -- nebeneinander gestellte Zahlen aus zwei Grundmengen ohne diese
// Angabe waren irrefuehrend.
echo audit_page::technical_panel(audit_page::overview_data());
echo audit_page::component_panel(audit_page::component_usage(30), 30);

echo html_writer::end_div();

plugin_shell::content_close();
plugin_page::close();
echo $OUTPUT->footer();

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
 * The design system of the suite, shown against this installation.
 *
 * Two gates, and they answer different questions. The setting decides whether
 * the site offers the page at all; without it the page is gone, not forbidden,
 * because a site that does not develop plugins should not carry a locked door
 * it never opens. The capability decides who walks through it.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_elediaai_core\output\developer_page;
use local_elediaai_core\output\plugin_page;
use local_elediaai_core\output\plugin_shell;

require_login();

$context = \core\context\system::instance();
$url = new moodle_url('/local/elediaai_core/developer.php');

// Erst die Berechtigung, dann die Einstellung. Wer keine hat, erfaehrt gar
// nichts ueber die Seite; wer sie hat, erfaehrt auch, wie man sie einschaltet
// -- eine stumme Fehlermeldung an dieselbe Person waere nur ein Raetsel.
require_capability('moodle/site:config', $context);
if (!developer_page::is_enabled()) {
    throw new moodle_exception('developer_disabled', 'local_elediaai_core');
}

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('developer_heading', 'local_elediaai_core'));
$PAGE->set_heading('');
$PAGE->requires->css('/local/elediaai_core/styles.css');
$PAGE->requires->js_call_amd('local_elediaai_core/developer', 'init');

$shell = [
    'name' => get_string('shell_name', 'local_elediaai_core'),
    'tagline' => get_string('developer_heading', 'local_elediaai_core'),
    'subtitle' => get_string('developer_subtitle', 'local_elediaai_core'),
] + plugin_shell::action_slots(
    'local_elediaai_core',
    false,
    null,
    get_string('shell_help_label', 'local_elediaai_core')
);

echo $OUTPUT->header();
plugin_page::open($shell, plugin_page::MODIFIER_WIDE);
plugin_shell::content_open();

// One section of the page: heading from $key, optional sentence below it from
// $introkey (pass '' for none), and the already rendered HTML in $body.
$abschnitt = function (string $key, string $introkey, string $body): string {
    $kopf = html_writer::tag('h2', get_string($key, 'local_elediaai_core'), ['class' => 'lh-dev__heading']);
    if ($introkey !== '') {
        $kopf .= html_writer::tag('p', get_string($introkey, 'local_elediaai_core'), ['class' => 'lh-dev__intro']);
    }
    return html_writer::div($kopf . $body, 'lh-dev__section');
};

// --- Umgebung. -----------------------------------------------------------
$zeilen = '';
foreach (developer_page::environment() as $name => $wert) {
    $zeilen .= html_writer::tag('dt', s($name));
    $zeilen .= html_writer::tag('dd', s($wert));
}
echo $abschnitt('developer_environment', '', html_writer::tag('dl', $zeilen, ['class' => 'lh-dev__facts']));

// --- Token, aufgeloest. --------------------------------------------------
// Die Werte traegt der Browser nach: der Quelltext sagt, was dasteht, aber
// nicht, was sich durchgesetzt hat. Genau diese Frage ist der Zweck der Seite.
$tabellen = '';
foreach (developer_page::token_groups() as $gruppe) {
    $kopf = html_writer::tag(
        'tr',
        html_writer::tag('th', get_string('developer_col_token', 'local_elediaai_core'))
        . html_writer::tag('th', get_string('developer_col_value', 'local_elediaai_core'))
        . html_writer::tag('th', get_string('developer_col_origin', 'local_elediaai_core'))
    );
    $koerper = '';
    foreach ($gruppe['tokens'] as $token) {
        $koerper .= html_writer::tag(
            'tr',
            html_writer::tag('td', html_writer::tag('code', s($token)))
            . html_writer::tag(
                'td',
                html_writer::span('', 'lh-dev__swatch', ['data-eai-swatch' => $token, 'hidden' => 'hidden'])
                . html_writer::span('', '', ['data-eai-token' => $token])
            )
            . html_writer::tag('td', '', ['data-eai-origin' => $token]),
            ['class' => 'lh-dev__row']
        );
    }
    $tabellen .= html_writer::tag('h3', s($gruppe['title']), ['class' => 'lh-dev__subheading']);
    $tabellen .= html_writer::tag(
        'table',
        html_writer::tag('thead', $kopf) . html_writer::tag('tbody', $koerper),
        ['class' => 'lh-dev__table']
    );
}
echo $abschnitt('developer_tokens', 'developer_tokens_intro', $tabellen);

// --- Skalen. -------------------------------------------------------------
$proben = '';
foreach (developer_page::type_scale() as $stufe) {
    $name = substr($stufe, strlen('--font-size-'));
    $proben .= html_writer::div(
        html_writer::div(
            html_writer::tag('code', s($stufe))
            . html_writer::tag('span', '', ['data-eai-token' => $stufe, 'class' => 'lh-dev__measure']),
            'lh-dev__samplemeta'
        )
        . html_writer::div(
            s($name) . ' — Anton jagt zwölf Boxkämpfer quer über den Sylter Deich',
            'lh-dev__sampletext',
            ['style' => 'font-size: var(' . s($stufe) . ');']
        ),
        'lh-dev__sample'
    );
}
echo $abschnitt('developer_scales', 'developer_scales_intro', $proben);

// --- Bausteine. ----------------------------------------------------------
$bausteine = html_writer::div(
    html_writer::tag('button', 'Primär', ['class' => 'btn btn-primary', 'type' => 'button'])
    . html_writer::tag('button', 'Sekundär', ['class' => 'btn btn-secondary', 'type' => 'button'])
    . html_writer::tag('span', 'Bereit', ['class' => 'lh-plugin-tag lh-plugin-tag--active'])
    . html_writer::tag('span', 'Hinweis', ['class' => 'lh-plugin-tag lh-plugin-tag--info'])
    . html_writer::tag('span', 'Neutral', ['class' => 'lh-plugin-tag lh-plugin-tag--neutral']),
    'lh-dev__blocks'
);
$bausteine .= html_writer::div(
    html_writer::tag('h4', 'Eine Karte', ['class' => 'lh-plugin-card__title'])
    . html_writer::tag('p', 'So sieht eine Karte der Suite aus, wenn sie nur die Token liest.'),
    'lh-plugin-card'
);
echo $abschnitt('developer_blocks', 'developer_blocks_intro', $bausteine);

// --- Vertrag. ------------------------------------------------------------
$vertrag = developer_page::contract();
echo $abschnitt('developer_contract', '', $vertrag === ''
    ? $OUTPUT->notification(
        get_string('developer_contract_missing', 'local_elediaai_core'),
        \core\output\notification::NOTIFY_WARNING
    )
    : html_writer::div(format_text($vertrag, FORMAT_MARKDOWN, ['context' => $context]), 'lh-dev__contract'));

plugin_shell::content_close();
plugin_page::close();
echo $OUTPUT->footer();

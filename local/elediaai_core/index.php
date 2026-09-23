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
 * LernHive AI dashboard — lists every registered AI feature.
 *
 * Wrapped in the LernHive Plugin Shell per ux-system.md §3 so the
 * suite has Zone-A title/help, section nav (Overview / Audit) and
 * consistent shell width with the rest of LernHive.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_elediaai_core\feature\registry;
use local_elediaai_core\output\feature_grid;
use local_elediaai_core\output\plugin_page;
use local_elediaai_core\output\plugin_shell;
use local_elediaai_core\output\section_nav;

require_login();

$context = \core\context\system::instance();

// Suche und Zielgruppe reisen ueber GET, damit ein gefilterter Blick sich
// verschicken und mit einem Lesezeichen versehen laesst -- und damit die Seite
// ohne JavaScript vollstaendig bedienbar bleibt.
$query = trim(optional_param('q', '', PARAM_TEXT));
$audience = optional_param('audience', '', PARAM_ALPHA);

$urlparams = [];
if ($query !== '') {
    $urlparams['q'] = $query;
}
if ($audience !== '') {
    $urlparams['audience'] = $audience;
}
$url = new moodle_url('/local/elediaai_core/index.php', $urlparams);

$PAGE->set_url($url);
$PAGE->set_context($context);
// The dashboard is a full-width suite entry point, matching the tutor home
// layout so the wide shell can use the available viewport.
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('dashboard_heading', 'local_elediaai_core'));
$PAGE->set_heading('');

$PAGE->requires->css('/local/elediaai_core/styles.css');

$features = registry::visible();

$actions = plugin_shell::action_slots(
    'local_elediaai_core',
    false, // Suite-level has no in-shell settings page; per-feature settings live in each sub-plugin.
    null,
    get_string('shell_help_label', 'local_elediaai_core')
);

// Zone-A action icon linking to the AI Tutor home, rendered next to Help.
// Guarded so it only appears when the eLeDia.ai Tutor block is installed.
if (file_exists($CFG->dirroot . '/blocks/elediaai_tutor/home.php')) {
    $actions['headeractionicons'] = [
        [
            'url' => (new moodle_url('/blocks/elediaai_tutor/home.php'))->out(false),
            'label' => get_string('shell_aitutor_label', 'local_elediaai_core'),
            'faicon' => 'fa-comments',
            'modifierclass' => 'lh-plugin-header__action--aitutor',
        ],
    ];
}

$shell = [
    'name' => get_string('shell_name', 'local_elediaai_core'),
    'tagline' => get_string('nav_overview', 'local_elediaai_core'),
    'subtitle' => get_string('dashboard_subtitle', 'local_elediaai_core'),
    'sectionnav' => section_nav::render(section_nav::SECTION_OVERVIEW),
] + $actions;

echo $OUTPUT->header();
// Wide shell (88rem) so the suite dashboard's three-column feature grid
// (lh-plugin-grid--cols-3, task10) has the horizontal room to render as
// three columns instead of collapsing to two at the default 72rem width.
plugin_page::open($shell, plugin_page::MODIFIER_WIDE);
plugin_shell::content_open();
echo html_writer::tag('style', feature_grid::styles());

if (empty($features)) {
    echo $OUTPUT->notification(
        get_string('dashboard_empty', 'local_elediaai_core'),
        \core\output\notification::NOTIFY_INFO
    );
} else {
    // Die Bedienelemente stehen auch dann da, wenn gerade nichts durchkommt --
    // sonst waere die Suche, die zu null Treffern gefuehrt hat, verschwunden
    // und niemand koennte sie zuruecknehmen.
    echo feature_grid::render_controls($url, $query, $audience);

    $gezeigt = registry::narrow($features, $query, $audience);

    // Der Hinweis steht immer im Dokument und ist nur versteckt, solange etwas
    // durchkommt. Das Modul soll ihn ein- und ausblenden koennen, ohne HTML zu
    // bauen -- ein zweiter Ort, an dem derselbe Satz formuliert wird, geht beim
    // ersten Umformulieren auseinander.
    echo html_writer::start_tag('div', [
        'data-region' => 'feature-empty',
        'hidden' => empty($gezeigt) ? null : 'hidden',
    ]);
    echo $OUTPUT->notification(
        get_string('dashboard_nomatch', 'local_elediaai_core'),
        \core\output\notification::NOTIFY_INFO
    );
    echo html_writer::link(
        new moodle_url('/local/elediaai_core/index.php'),
        get_string('dashboard_showall', 'local_elediaai_core'),
        ['class' => 'btn btn-secondary', 'data-region' => 'feature-showall']
    );
    echo html_writer::end_tag('div');

    // Jede Kachel wird ausgeliefert; welche sichtbar ist, entscheidet der
    // Server fuer den ersten Aufschlag und danach der Browser.
    echo feature_grid::render($features, ['matching' => array_keys($gezeigt)]);

    // Was sich ohne Seitenwechsel aendert, muss angesagt werden: eine
    // Trefferzahl, die lautlos von einundzwanzig auf drei faellt, ist fuer
    // jemanden am Screenreader nicht passiert.
    echo html_writer::tag('p', '', [
        'class' => 'sr-only',
        'data-region' => 'feature-count',
        'role' => 'status',
        'aria-live' => 'polite',
    ]);

    $PAGE->requires->js_call_amd('local_elediaai_core/feature_filter', 'init');
}

plugin_shell::content_close();
plugin_page::close();
echo $OUTPUT->footer();

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
 * How the suite is doing, as reported by its own plugins.
 *
 * The page asks nothing itself. Every line comes from the plugin it is
 * about -- whether LiteRAG answers, whether a destination is set up,
 * whether MCP exposes anything. Before this existed, one page in
 * `block_elediaai_tutor` asked all of it on everybody's behalf, which meant
 * reaching into three other plugins' innards to do it.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_elediaai_core\health\check;
use local_elediaai_core\health\registry as health_registry;
use local_elediaai_core\output\plugin_page;
use local_elediaai_core\output\plugin_shell;
use local_elediaai_core\output\section_nav;

require_login();

$context = \core\context\system::instance();
$url = new moodle_url('/local/elediaai_core/health.php');

require_capability('moodle/site:config', $context);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('health_heading', 'local_elediaai_core'));
$PAGE->set_heading('');
$PAGE->requires->css('/local/elediaai_core/styles.css');

$shell = [
    'name' => get_string('shell_name', 'local_elediaai_core'),
    'tagline' => get_string('health_heading', 'local_elediaai_core'),
    'subtitle' => get_string('health_intro', 'local_elediaai_core'),
    'homeurl' => (new moodle_url('/local/elediaai_core/index.php'))->out(false),
    'sectionnav' => section_nav::render(section_nav::SECTION_HEALTH),
] + plugin_shell::action_slots('local_elediaai_core', false, null);

echo $OUTPUT->header();
plugin_page::open($shell, plugin_page::MODIFIER_READING);
plugin_shell::content_open();

$checks = health_registry::all();

if (empty($checks)) {
    // Kein Plugin meldet sich. Das ist kein Fehler -- der Bericht ist
    // freiwillig --, aber es gehoert gesagt, damit eine leere Seite nicht
    // wie „alles in Ordnung" gelesen wird.
    echo $OUTPUT->notification(
        get_string('health_none', 'local_elediaai_core'),
        \core\output\notification::NOTIFY_INFO
    );
} else {
    $offen = count(array_filter($checks, static fn(check $c): bool => $c->needs_attention()));
    echo $OUTPUT->notification(
        $offen > 0
            ? get_string('health_summary_attention', 'local_elediaai_core', $offen)
            : get_string('health_summary_quiet', 'local_elediaai_core', count($checks)),
        $offen > 0 ? \core\output\notification::NOTIFY_WARNING : \core\output\notification::NOTIFY_SUCCESS
    );

    echo html_writer::start_tag('div', ['class' => 'lh-health']);
    foreach ($checks as $c) {
        echo html_writer::start_tag('article', [
            'class' => 'lh-health__row lh-health__row--' . $c->status,
            'data-health-component' => s($c->component),
        ]);
        echo html_writer::tag(
            'div',
            s(get_string('health_status_' . $c->status, 'local_elediaai_core')),
            ['class' => 'lh-health__status']
        );
        echo html_writer::start_tag('div', ['class' => 'lh-health__body']);
        echo html_writer::tag('h2', s($c->label), ['class' => 'lh-health__label']);
        echo html_writer::tag('p', s($c->component), ['class' => 'lh-health__component']);
        if (trim($c->detail) !== '') {
            echo html_writer::tag('p', s($c->detail), ['class' => 'lh-health__detail']);
        }
        if ($c->actionurl !== null) {
            echo html_writer::link(
                $c->actionurl,
                s($c->actionlabel ?? get_string('settings')),
                ['class' => 'btn btn-secondary btn-sm']
            );
        }
        echo html_writer::end_tag('div');
        echo html_writer::end_tag('article');
    }
    echo html_writer::end_tag('div');
}

plugin_shell::content_close();
plugin_page::close();
echo $OUTPUT->footer();

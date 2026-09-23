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
 * Site report over the provenance records.
 *
 * The capability local/aitransparency:viewreport was defined when the plugin was
 * scaffolded and described "the site-level report of provenance records and
 * unsigned artefacts". Until now there was no such report -- the plugin had four
 * writers and no reader at all, so nothing it recorded was ever visible.
 *
 * @package    local_aitransparency
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_aitransparency\marker;
use local_aitransparency\provenance;

require_login();

$context = \core\context\system::instance();
require_capability('local/aitransparency:viewreport', $context);

$page = max(0, optional_param('page', 0, PARAM_INT));
$perpage = 50;

$url = new moodle_url('/local/aitransparency/report.php', ['page' => $page]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('report_title', 'local_aitransparency'));
$PAGE->set_heading(get_string('report_title', 'local_aitransparency'));
$PAGE->requires->css('/local/aitransparency/styles.css');
$PAGE->requires->js_call_amd('local_elediaai_core/audit_table', 'init');

$total = provenance::count_records();
$unmarked = provenance::count_unmarked();

echo $OUTPUT->header();
echo html_writer::tag('p', get_string('report_intro', 'local_aitransparency'), [
    'class' => 'aitransparency-verify__intro',
]);

// Die Zahl, um die es geht: ein Datensatz, der nicht markiert ist, bedeutet
// eine Ausgabe, die ohne ihre Kennzeichnung hinausging.
echo html_writer::div(
    html_writer::tag('strong', $total) . ' '
        . get_string('report_total', 'local_aitransparency') . ' · '
        . html_writer::tag('strong', $unmarked) . ' '
        . get_string('report_unmarked', 'local_aitransparency'),
    'aitransparency-verify__note'
);

if ($total === 0) {
    echo $OUTPUT->notification(
        get_string('report_empty', 'local_aitransparency'),
        \core\output\notification::NOTIFY_INFO
    );
    echo $OUTPUT->footer();
    die;
}

echo $OUTPUT->paging_bar($total, $page, $perpage, $url);

$table = new html_table();
$table->head = [
    get_string('report_col_time', 'local_aitransparency'),
    get_string('report_col_component', 'local_aitransparency'),
    get_string('report_col_asset', 'local_aitransparency'),
    get_string('report_col_provider', 'local_aitransparency'),
    get_string('report_col_markstate', 'local_aitransparency'),
    get_string('report_col_verify', 'local_aitransparency'),
];
$table->attributes['class'] = 'table generaltable';

foreach (provenance::recent($perpage, $page * $perpage) as $record) {
    $component = marker::component_label($record->component);
    $marked = $record->markstate === 'marked'
        || $record->markstate === 'embedded'
        || $record->markstate === 'sidecar';

    $table->data[] = [
        userdate($record->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
        s($component),
        get_string('assettype_' . $record->assettype, 'local_aitransparency'),
        s(trim(marker::provider_label($record->provider)
        . ($record->model !== '' ? ' / ' . $record->model : ''))),
        html_writer::span(
            get_string('markstate_' . $record->markstate, 'local_aitransparency'),
            'eai-badge ' . ($marked ? 'eai-badge--ok' : 'eai-badge--warn')
        ),
        html_writer::link(
            marker::verify_url($record->uuid),
            get_string('report_open', 'local_aitransparency')
        ),
    ];
}

// In denselben Behaelter wie die Audit-Berichte: sechs Spalten passen auf
// einen Bildschirm, aber nicht auf ein Telefon, und dort wird die Tabelle
// zur Karte je Zeile statt seitlich geschoben zu werden. Die Regeln dafuer
// stehen in local_elediaai_core, das dieses Plugin ohnehin voraussetzt.
echo html_writer::div(html_writer::table($table), 'eai-datatable');
echo $OUTPUT->paging_bar($total, $page, $perpage, $url);
echo $OUTPUT->footer();

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
 * Resolves a provenance UUID: did an AI make this, and which one.
 *
 * Login required, no capability. Art. 50 Abs. 2 argues for anybody holding the
 * content being able to check it, but a page open to the world would also
 * confirm that a given record EXISTS to anybody who guesses a UUID. Requiring a
 * session is the middle: everybody on the site can check, nobody outside can
 * enumerate. The page never names the person who triggered the generation --
 * the question is whether an AI was involved, not who operated it.
 *
 * @package    local_aitransparency
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_aitransparency\marker;
use local_aitransparency\provenance;

require_login();

$uuid = trim(optional_param('uuid', '', PARAM_ALPHANUMEXT));
$url = new moodle_url('/local/aitransparency/verify.php');
if ($uuid !== '') {
    $url->param('uuid', $uuid);
}

$PAGE->set_url($url);
$PAGE->set_context(\core\context\system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('verify_title', 'local_aitransparency'));
$PAGE->set_heading(get_string('verify_title', 'local_aitransparency'));
$PAGE->requires->css('/local/aitransparency/styles.css');

$record = $uuid === '' ? null : provenance::get($uuid);

echo $OUTPUT->header();

if ($record === null) {
    echo $OUTPUT->notification(
        get_string($uuid === '' ? 'verify_nouuid' : 'verify_notfound', 'local_aitransparency'),
        \core\output\notification::NOTIFY_WARNING
    );
    echo $OUTPUT->footer();
    die;
}

$rows = [
    'verify_component' => get_string('verify_component_value', 'local_aitransparency', [
        'component' => s(marker::component_label($record->component)),
    ]),
    'verify_assettype' => get_string('assettype_' . $record->assettype, 'local_aitransparency'),
    'verify_provider' => s(trim(marker::provider_label($record->provider)
        . ($record->model !== '' ? ' / ' . $record->model : ''))),
    'verify_time' => userdate($record->timecreated),
    'verify_markstate' => get_string('markstate_' . $record->markstate, 'local_aitransparency'),
    'verify_contenthash' => html_writer::tag('code', s($record->contenthash), [
        'class' => 'aitransparency-verify__hash',
    ]),
];

$body = html_writer::tag('h2', get_string('verify_found', 'local_aitransparency'), [
    'class' => 'aitransparency-verify__heading',
]);
$body .= html_writer::tag('p', get_string('verify_found_intro', 'local_aitransparency'), [
    'class' => 'aitransparency-verify__intro',
]);

$list = '';
foreach ($rows as $stringid => $value) {
    $list .= html_writer::tag('dt', get_string($stringid, 'local_aitransparency'));
    $list .= html_writer::tag('dd', $value);
}
$body .= html_writer::tag('dl', $list, ['class' => 'aitransparency-verify__list']);

$body .= html_writer::div(
    get_string('verify_noperson', 'local_aitransparency'),
    'aitransparency-verify__note'
);
$body .= html_writer::div(
    html_writer::tag('code', s($record->uuid)),
    'aitransparency-verify__uuid'
);

echo html_writer::div($body, 'aitransparency-verify');
echo $OUTPUT->footer();

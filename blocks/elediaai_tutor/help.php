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
 * Plugin-owned help page.
 *
 * @package     block_elediaai_tutor
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/output/shell.php');

use block_elediaai_tutor\output\shell;

// Aus einem Kurs heraus liest die Lehrkraft die Hilfe mit dem Recht, mit dem
// sie den Tutor dort einrichtet (G-05); ohne Kurs bleibt es eine Seite fuer die
// Administration.
$courseid = optional_param('id', 0, PARAM_INT);
$course = ($courseid && $courseid != SITEID) ? get_course($courseid) : null;
require_login($course);
if ($course) {
    $context = \core\context\course::instance($course->id);
    require_capability('block/elediaai_tutor:manage', $context);
} else {
    $context = \core\context\system::instance();
    require_capability('moodle/site:config', $context);
}
$url = new moodle_url('/blocks/elediaai_tutor/help.php', $course ? ['id' => $course->id] : []);
// Die Huelle fuehrt in die Administration; im Kurs bleibt die Lehrkraft in
// ihrer gewohnten Umgebung.
$useshell = $course === null && shell::is_available();

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout($course ? 'incourse' : 'report');
$PAGE->blocks->show_only_fake_blocks(true);
$PAGE->set_title(get_string('shell_help_label', 'block_elediaai_tutor'));
if ($course) {
    $PAGE->set_heading(format_string($course->fullname));
} else {
    $PAGE->set_heading($useshell ? '' : get_string('shell_help_label', 'block_elediaai_tutor'));
}
shell::require_css();

$docfile = str_starts_with(current_language(), 'de')
    ? __DIR__ . '/docs/02-user-doc.de.md'
    : __DIR__ . '/docs/02-user-doc.md';
if (!is_readable($docfile)) {
    $docfile = __DIR__ . '/docs/02-user-doc.md';
}

// Das Dokument ist fuer das Team geschrieben und wird hier Lesenden gezeigt.
// help_document nimmt den Meta-Block und die Verweise auf Nachbardokumente
// heraus, die im Repository liegen und nicht auf der Website (task25).
$markdown = \local_elediaai_core\output\help_document::read($docfile);
$html = $markdown !== ''
    ? format_text($markdown, FORMAT_MARKDOWN, ['context' => $context])
    : html_writer::tag('p', get_string('error'));

echo $OUTPUT->header();

$header = $useshell ? shell::context(shell::ACTIVE_CONFIGURATION) : null;
if ($header) {
    $header['tagline'] = get_string('help', 'core');
    $header['sectionnav'] = shell::sectionnav('help');
    \block_elediaai_tutor\output\plugin_page::open($header, \block_elediaai_tutor\output\plugin_page::MODIFIER_READING);
    \block_elediaai_tutor\output\plugin_shell::content_open();
} else {
    echo $OUTPUT->heading(get_string('shell_help_label', 'block_elediaai_tutor'));
}

echo html_writer::start_div('path-block-elediaai_tutor');
echo html_writer::tag('article', $html, ['class' => 'lh-plugin-card eat-help-doc']);
echo html_writer::end_div();

if ($header) {
    \block_elediaai_tutor\output\plugin_shell::content_close();
    \block_elediaai_tutor\output\plugin_page::close();
}

echo $OUTPUT->footer();

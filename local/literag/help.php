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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin-owned help page.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/output/shell.php');

use local_literag\output\shell;

$context = \core\context\system::instance();
$url = new moodle_url('/local/literag/help.php');

require_login();
require_capability('local/literag:manage', $context);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->blocks->show_only_fake_blocks(true);
$PAGE->set_title(get_string('shell_help_label', 'local_literag'));
$PAGE->set_heading('');
$PAGE->activityheader->disable();
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

// Der Rahmen kommt aus dem Kern. Die Alt-Klasse laeuft mit, weil 117 Regeln
// dieses Stylesheets Nachfahren von .lh-plugin-shell sind und im erzeugten
// Block liegen, der aus dem Quell-Repo gespiegelt wird.
\local_elediaai_core\output\plugin_page::open(
    shell::header_data(shell::ACTIVE_HELP, get_string('help', 'core')),
    \local_elediaai_core\output\plugin_page::MODIFIER_DEFAULT,
    'lh-plugin-shell lr-help-shell'
);
\local_elediaai_core\output\plugin_shell::content_open('lh-plugin-content-area');

echo html_writer::tag('article', $html, ['class' => 'lr-help-doc']);

\local_elediaai_core\output\plugin_shell::content_close();
\local_elediaai_core\output\plugin_page::close();

echo $OUTPUT->footer();

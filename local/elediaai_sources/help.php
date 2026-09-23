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
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/output/shell.php');

use local_elediaai_core\output\plugin_page;
use local_elediaai_core\output\plugin_shell;
use local_elediaai_sources\output\shell;

require_login();
$context = \core\context\system::instance();
require_capability('moodle/site:config', $context);

$url = new moodle_url('/local/elediaai_sources/help.php');
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->blocks->show_only_fake_blocks(true);
$PAGE->set_title(get_string('shell_help_label', 'local_elediaai_sources'));
$PAGE->set_heading(shell::is_available() ? '' : get_string('shell_help_label', 'local_elediaai_sources'));
$PAGE->activityheader->disable();
shell::require_css();

// The help page renders the user manual. There is one manual, in German, as
// before — the previous language branch looked for a translated file that never
// existed and always fell through to the same document.
$docfile = __DIR__ . '/docs/user_manual.md';

// Das Dokument ist fuer das Team geschrieben und wird hier Lesenden gezeigt.
// help_document nimmt den Meta-Block und die Verweise auf Nachbardokumente
// heraus, die im Repository liegen und nicht auf der Website (task25).
$markdown = \local_elediaai_core\output\help_document::read($docfile);
$html = $markdown !== ''
    ? format_text($markdown, FORMAT_MARKDOWN, ['context' => $context])
    : html_writer::tag('p', get_string('error'));

echo $OUTPUT->header();

// Die gemeinsame Huelle des Kerns statt der Vorlage aus dem Tutor-Block.
if (shell::is_available()) {
    plugin_page::open(shell::header_data(get_string('help', 'core'), 'help'), plugin_page::MODIFIER_READING);
    plugin_shell::content_open();
} else {
    echo $OUTPUT->heading(get_string('shell_help_label', 'local_elediaai_sources'));
}

echo html_writer::tag('article', $html, ['class' => 'rg-docs-content rg-shell-card']);

if (shell::is_available()) {
    plugin_shell::content_close();
    plugin_page::close();
}

echo $OUTPUT->footer();

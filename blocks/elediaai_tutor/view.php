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
 * Standalone chat page for the eLeDia.ai Tutor.
 *
 * Hosts the same widget the block renders, on its own page. Primary use case:
 * embedding the tutor in the Moodle App via a tool_mobile custom menu item
 * (the app opens site URLs with auto-login), where third-party blocks are not
 * rendered. Also usable in any browser as a focused, full-page chat.
 *
 * Parameters:
 * - courseid (optional): chat in that course's context (enrolment + capability
 *   enforced); omit or 0 for global chat at the system context.
 * - embedded (optional bool): use Moodle's chrome-less "embedded" page layout —
 *   pass 1 when opening inside the app or an iframe.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

use block_elediaai_tutor\local\security;
use block_elediaai_tutor\local\widget;
use block_elediaai_tutor\output\shell;

require(__DIR__ . '/../../config.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
$embedded = optional_param('embedded', 0, PARAM_BOOL);

if ($courseid > 0 && $courseid != SITEID) {
    $course = get_course($courseid);
    require_login($course, false);
    $context = \core\context\course::instance($course->id);
    $enabled = security::course_chat_enabled();
    $disabledstring = 'error_course_chat_disabled';
} else {
    require_login(null, false);
    $courseid = 0;
    $context = \core\context\system::instance();
    $enabled = security::global_chat_enabled();
    $disabledstring = 'error_global_chat_disabled';
}

if (isguestuser()) {
    throw new moodle_exception('noguest');
}
require_capability('block/elediaai_tutor:use', $context);

$useshell = !$embedded
    && $courseid === 0
    && shell::is_available()
    && has_capability('moodle/site:config', \core\context\system::instance());

// Per-instance preview: launched from a block's instance shell (blockid of a tutor
// block the viewer can manage). Wrap the chat in that instance shell so the
// Settings / Tutor / Preview / MCP menu persists for teachers (who lack site:config).
$blockid = optional_param('blockid', 0, PARAM_INT);
$useinstanceshell = false;
if (!$embedded && !$useshell && $blockid > 0 && shell::is_available()) {
    $blockrecord = $DB->get_record('block_instances', ['id' => $blockid, 'blockname' => 'elediaai_tutor']);
    if ($blockrecord && has_capability('block/elediaai_tutor:manage', \core\context\block::instance($blockid))) {
        $useinstanceshell = true;
    }
}
$anyshell = $useshell || $useinstanceshell;

$PAGE->set_url(new moodle_url(
    '/blocks/elediaai_tutor/view.php',
    ['courseid' => $courseid, 'embedded' => $embedded]
));
if ($useinstanceshell) {
    // Render in the block context (as edit_instance.php / instance_tutor.php do) so the
    // page does not pull in the course's secondary navigation and course-header banner:
    // the instance shell supplies its own header, and the course name would otherwise be
    // shown twice (course banner + shell tagline). require_login() above still scopes
    // enrolment, and the chat itself renders in this block's context (see below).
    $PAGE->set_context(\core\context\block::instance($blockid));
    if ($courseid > 0) {
        $PAGE->set_course($course);
        $PAGE->set_pagelayout('incourse');
    } else {
        $PAGE->set_pagelayout('report');
    }
} else {
    $PAGE->set_context($context);
    $PAGE->set_pagelayout($embedded ? 'embedded' : 'standard');
}
if (!$embedded) {
    $PAGE->blocks->show_only_fake_blocks(true);
}
$PAGE->set_title(get_string('default_persona', 'block_elediaai_tutor'));
if ($courseid > 0) {
    $PAGE->set_heading($anyshell ? '' : format_string($course->fullname));
} else {
    $PAGE->set_heading($anyshell ? '' : get_string('default_persona', 'block_elediaai_tutor'));
}
if ($anyshell) {
    shell::require_css();
}
$PAGE->add_body_class('elediaai-chat-pagebody');

echo $OUTPUT->header();
if ($useshell) {
    shell::open(shell::ACTIVE_PREVIEW);
} else if ($useinstanceshell) {
    shell::open_instance($blockid, shell::ACTIVE_INSTANCE_PREVIEW);
}

if (!$enabled) {
    echo $OUTPUT->notification(get_string($disabledstring, 'block_elediaai_tutor'), 'info');
    if ($anyshell) {
        shell::close();
    }
    echo $OUTPUT->footer();
    die;
}

// Course chat is opt-in per course: the teacher enables it by adding the
// tutor block. Without it, the page declines with a friendly notice.
if ($courseid > 0 && !widget::course_has_tutor($courseid)) {
    echo $OUTPUT->notification(get_string('notenabledincourse', 'block_elediaai_tutor'), 'info');
    if ($anyshell) {
        shell::close();
    }
    echo $OUTPUT->footer();
    die;
}

// Always the inline (embedded display mode) panel: on a dedicated page the
// launcher/overlay modes make no sense. Assembled before config_error() because
// that check is mode-aware — it needs the resolved course id and the instance
// ragmode to decide whether grounded mode (and the MCP connector) is required.
$rendercontext = $context;
$instancecfg = ['displaymode' => 'embedded'];
if ($useinstanceshell) {
    // Mirror the in-page block render (see block_elediaai_tutor::get_content) so the
    // preview reflects THIS instance's saved settings — branding, persona and any
    // exposed per-instance overrides — rather than the site defaults. The block
    // context resolves the instance's own logo/avatar uploads.
    $instancecfg = (array) (!empty($blockrecord->configdata)
        ? unserialize_object(base64_decode($blockrecord->configdata)) : new stdClass());
    $instancecfg['instanceid'] = (int) $blockid;
    $instancecfg['displaymode'] = 'embedded';
    $rendercontext = \core\context\block::instance($blockid);
}

$configerror = widget::config_error($courseid, $instancecfg);
if ($configerror !== null) {
    $canmanage = has_capability('block/elediaai_tutor:manage', $context);
    echo $OUTPUT->render_from_template('block_elediaai_tutor/unavailable', [
        'isadmin' => $canmanage,
        'message' => $canmanage ? $configerror : get_string('unavailable_user', 'block_elediaai_tutor'),
    ]);
    if ($anyshell) {
        shell::close();
    }
    echo $OUTPUT->footer();
    die;
}
echo html_writer::div(
    widget::render($rendercontext, $courseid, $instancecfg),
    'elediaai-chat-page'
);

if ($anyshell) {
    shell::close();
}
echo $OUTPUT->footer();

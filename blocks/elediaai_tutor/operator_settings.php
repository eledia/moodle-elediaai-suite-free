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
 * Plugin-shell settings page for the eLeDia.ai Tutor block.
 *
 * This page reuses Moodle's admin settings form and save handling, but renders
 * the plugin shell server-side so the layout is stable from the first paint.
 *
 * @package     block_elediaai_tutor
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/classes/output/shell.php');

use block_elediaai_tutor\output\shell;

$section = 'blocksettingelediaai_tutor';
$return = optional_param('return', '', PARAM_ALPHA);
$context = \core\context\system::instance();
$url = new moodle_url('/blocks/elediaai_tutor/operator_settings.php', ['section' => $section]);

// Moodle admin settings are populated in many plugins based on the current
// "section" request parameter. This shell page owns only one section, so expose
// it before admin_get_root() loads settings.php files.
$_GET['section'] = $_GET['section'] ?? $section;
$_REQUEST['section'] = $_REQUEST['section'] ?? $section;

require_login(0, false);
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagetype('admin-setting-' . $section);
$PAGE->set_pagelayout('report');
$PAGE->blocks->show_only_fake_blocks(true);
$PAGE->set_title(get_string('nav_settings', 'block_elediaai_tutor'));
$PAGE->set_heading(shell::is_available() ? '' : get_string('nav_settings', 'block_elediaai_tutor'));
$PAGE->navigation->clear_cache();
navigation_node::require_admin_tree();
shell::require_css();

$adminroot = admin_get_root();
$settingspage = $adminroot->locate($section, true);
if (empty($settingspage) || !($settingspage instanceof admin_settingpage)) {
    throw new \moodle_exception('sectionerror', 'admin', "$CFG->wwwroot/$CFG->admin/");
}
if (!$settingspage->check_access()) {
    throw new \moodle_exception('accessdenied', 'admin');
}

$errormsg = '';
if ($data = data_submitted()) {
    if (confirm_sesskey() && isset($data->action) && $data->action === 'save-settings') {
        $count = admin_write_settings($data);
        if (empty($adminroot->errors)) {
            if ($count) {
                redirect($PAGE->url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
            }
            switch ($return) {
                case 'site':
                    redirect("$CFG->wwwroot/");
                    break;
                case 'admin':
                    redirect("$CFG->wwwroot/$CFG->admin/");
                    break;
            }
            redirect($PAGE->url);
        }
        $errormsg = get_string('errorwithsettings', 'admin');
        $firsterror = reset($adminroot->errors);
        $PAGE->set_focuscontrol($firsterror->id);
        $settingspage = $adminroot->locate($section, true);
    }
}

$sectioncards = [
    [
        'key' => 'design',
        'icon' => 'palette',
        'title' => get_string('setting_section_design', 'block_elediaai_tutor'),
        'body' => get_string('setting_section_design_desc', 'block_elediaai_tutor'),
    ],
    [
        'key' => 'conversation',
        'icon' => 'comments',
        'title' => get_string('setting_section_conversation', 'block_elediaai_tutor'),
        'body' => get_string('setting_section_conversation_desc', 'block_elediaai_tutor'),
    ],
    [
        'key' => 'technical',
        'icon' => 'plug',
        'title' => get_string('setting_section_technical', 'block_elediaai_tutor'),
        'body' => get_string('setting_section_technical_desc', 'block_elediaai_tutor'),
    ],
];

$PAGE->requires->js_call_amd('block_elediaai_tutor/settings_shell', 'init', [[
    'inShell' => true,
    'sectionCards' => $sectioncards,
    'pluginTitle' => get_string('pluginname', 'block_elediaai_tutor'),
    'hubTitle' => get_string('settings_hub_title', 'block_elediaai_tutor'),
    'hubDesc' => get_string('settings_hub_desc', 'block_elediaai_tutor'),
    'topicsLabel' => get_string('settings_hub_topics_label', 'block_elediaai_tutor'),
    'backLabel' => get_string('settings_back_to_overview', 'block_elediaai_tutor'),
    'exposeLabel' => get_string('settings_expose_inline_label', 'block_elediaai_tutor'),
    'cancelLabel' => get_string('cancel'),
    'cancelUrl' => (new moodle_url('/local/elediaai_core/health.php'))->out(false),
]]);
$PAGE->requires->js_call_amd('core_form/changechecker', 'watchFormById', ['adminsettings']);

echo $OUTPUT->header();

shell::open(shell::ACTIVE_SETTINGS, true);

if ($errormsg !== '') {
    echo $OUTPUT->notification($errormsg);
}

$pageparams = $PAGE->url->params();
$settingscontext = [
    'actionurl' => $PAGE->url->out(false),
    'params' => array_map(static function ($param) use ($pageparams): array {
        return [
            'name' => $param,
            'value' => $pageparams[$param],
        ];
    }, array_keys($pageparams)),
    'sesskey' => sesskey(),
    'return' => $return,
    'title' => null,
    'settings' => $settingspage->output_html(),
    'showsave' => $settingspage->show_save(),
];

echo $OUTPUT->render_from_template('core_admin/settings', $settingscontext);

if ($settingspage->has_dependencies()) {
    echo $OUTPUT->render_from_template('core_admin/settings_showhide', [
        'dependencies' => json_encode($settingspage->get_dependencies_for_javascript()),
    ]);
}

shell::close();

echo $OUTPUT->footer();

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
 * Plugin-shell settings page for the AI audit.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use core\context\system;
use core\output\notification;
use local_elediaai_core\form\audit_settings as audit_settings_form;
use local_elediaai_core\local\actions;
use local_elediaai_core\local\audit_config;
use local_elediaai_core\local\insights;
use local_elediaai_core\local\quota_manager;
use local_elediaai_core\output\plugin_page;
use local_elediaai_core\output\plugin_shell;
use local_elediaai_core\output\section_nav;

$context = system::instance();
$url = new moodle_url('/local/elediaai_core/audit_settings.php');
$backurl = new moodle_url('/local/elediaai_core/audit.php');

require_login();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('surface_settings_title', 'local_elediaai_core'));
$PAGE->set_heading('');

$PAGE->requires->css('/local/elediaai_core/styles.css');

$form = new audit_settings_form($url);
$form->set_data((object) [
    'audit_access' => (string) (get_config('local_elediaai_core', 'audit_access') ?: audit_config::ACCESS_CORE_CAPABILITY),
    'audit_anonymize_users' => (int) get_config('local_elediaai_core', 'audit_anonymize_users'),
    'audit_show_prompt' => audit_config::show_prompt() ? 1 : 0,
    'audit_show_response' => audit_config::show_response() ? 1 : 0,
    'audit_show_error' => audit_config::show_error() ? 1 : 0,
    'audit_show_tokens' => audit_config::show_tokens() ? 1 : 0,
    'action_retentiondays' => actions::retention_days(),
    'turn_retentiondays' => insights::retention_days(),
    'usage_retentiondays' => quota_manager::retention_days(),
    'quota_student_hour' => (int) get_config('local_elediaai_core', 'quota_student_hour'),
    'quota_student_day' => (int) get_config('local_elediaai_core', 'quota_student_day'),
    'quota_student_month' => (int) get_config('local_elediaai_core', 'quota_student_month'),
    'quota_teacher_hour' => (int) get_config('local_elediaai_core', 'quota_teacher_hour'),
    'quota_teacher_day' => (int) get_config('local_elediaai_core', 'quota_teacher_day'),
    'quota_teacher_month' => (int) get_config('local_elediaai_core', 'quota_teacher_month'),
]);

if ($form->is_cancelled()) {
    redirect($backurl);
}

if ($data = $form->get_data()) {
    set_config('audit_access', (string) $data->audit_access, 'local_elediaai_core');
    set_config('audit_anonymize_users', (int) $data->audit_anonymize_users, 'local_elediaai_core');
    set_config('audit_show_prompt', (int) $data->audit_show_prompt, 'local_elediaai_core');
    set_config('audit_show_response', (int) $data->audit_show_response, 'local_elediaai_core');
    set_config('audit_show_error', (int) $data->audit_show_error, 'local_elediaai_core');
    set_config('audit_show_tokens', (int) $data->audit_show_tokens, 'local_elediaai_core');
    set_config('action_retentiondays', max(0, (int) $data->action_retentiondays), 'local_elediaai_core');
    set_config('turn_retentiondays', max(0, (int) $data->turn_retentiondays), 'local_elediaai_core');
    set_config('usage_retentiondays', max(0, (int) $data->usage_retentiondays), 'local_elediaai_core');
    set_config('quota_student_hour', max(0, (int) $data->quota_student_hour), 'local_elediaai_core');
    set_config('quota_student_day', max(0, (int) $data->quota_student_day), 'local_elediaai_core');
    set_config('quota_student_month', max(0, (int) $data->quota_student_month), 'local_elediaai_core');
    set_config('quota_teacher_hour', max(0, (int) $data->quota_teacher_hour), 'local_elediaai_core');
    set_config('quota_teacher_day', max(0, (int) $data->quota_teacher_day), 'local_elediaai_core');
    set_config('quota_teacher_month', max(0, (int) $data->quota_teacher_month), 'local_elediaai_core');

    redirect($url, get_string('changessaved'), null, notification::NOTIFY_SUCCESS);
}

$actions = plugin_shell::action_slots(
    'local_elediaai_core',
    true,
    $url,
    get_string('shell_help_label', 'local_elediaai_core'),
    get_string('feature_settings', 'local_elediaai_core'),
    settingsiscurrent: true
);

$shell = [
    'name' => get_string('shell_name', 'local_elediaai_core'),
    'tagline' => get_string('surface_settings_title', 'local_elediaai_core'),
    'subtitle' => get_string('audit_settings_page_intro', 'local_elediaai_core'),
    'homeurl' => $backurl->out(false),
    'homelabel' => get_string('nav_audit', 'local_elediaai_core'),
    'sectionnav' => section_nav::render_for_feature('audit', section_nav::FEATURE_SETTINGS),
] + $actions;

echo $OUTPUT->header();
plugin_page::open($shell, plugin_page::MODIFIER_READING);
plugin_shell::content_open();
echo $OUTPUT->heading(get_string('audit_settings_heading', 'local_elediaai_core'), 2);
$form->display();
plugin_shell::content_close();
plugin_page::close();
echo $OUTPUT->footer();

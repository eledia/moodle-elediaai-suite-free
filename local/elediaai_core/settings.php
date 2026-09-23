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
 * Admin settings for the LernHive AI Suite shell.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_elediaai_core\local\actions;
use local_elediaai_core\local\audit_config;
use local_elediaai_core\local\quota_manager;

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_elediaai_core_audit',
        get_string('audit_settings_heading', 'local_elediaai_core')
    );

    $settings->add(new admin_setting_heading(
        'local_elediaai_core/audit_settings_heading',
        get_string('audit_settings_heading', 'local_elediaai_core'),
        get_string('audit_settings_heading_desc', 'local_elediaai_core')
    ));

    $settings->add(new admin_setting_configselect(
        'local_elediaai_core/audit_access',
        get_string('audit_access', 'local_elediaai_core'),
        get_string('audit_access_desc', 'local_elediaai_core'),
        audit_config::ACCESS_CORE_CAPABILITY,
        [
            audit_config::ACCESS_CORE_CAPABILITY => get_string('audit_access_corecap', 'local_elediaai_core'),
            audit_config::ACCESS_TEACHER_OWN_COURSES => get_string('audit_access_teacherowncourses', 'local_elediaai_core'),
            audit_config::ACCESS_ADMINS_ONLY => get_string('audit_access_adminonly', 'local_elediaai_core'),
        ]
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_elediaai_core/audit_anonymize_users',
        get_string('audit_anonymize_users', 'local_elediaai_core'),
        get_string('audit_anonymize_users_desc', 'local_elediaai_core'),
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_elediaai_core/audit_show_prompt',
        get_string('audit_show_prompt', 'local_elediaai_core'),
        get_string('audit_show_prompt_desc', 'local_elediaai_core'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_elediaai_core/audit_show_response',
        get_string('audit_show_response', 'local_elediaai_core'),
        get_string('audit_show_response_desc', 'local_elediaai_core'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_elediaai_core/audit_show_error',
        get_string('audit_show_error', 'local_elediaai_core'),
        get_string('audit_show_error_desc', 'local_elediaai_core'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_elediaai_core/audit_show_tokens',
        get_string('audit_show_tokens', 'local_elediaai_core'),
        get_string('audit_show_tokens_desc', 'local_elediaai_core'),
        1
    ));

    // Drei Protokolle mit drei Aufgaben verdienen nicht dieselbe Frist. Die
    // dritte (der Herkunftsnachweis) gehoert local_aitransparency und steht
    // dort; hier stehen die beiden, die dieses Plugin fuehrt.
    $settings->add(new admin_setting_configtext(
        'local_elediaai_core/action_retentiondays',
        get_string('action_retentiondays', 'local_elediaai_core'),
        get_string('action_retentiondays_desc', 'local_elediaai_core'),
        actions::DEFAULT_RETENTION_DAYS,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_elediaai_core/turn_retentiondays',
        get_string('turn_retentiondays', 'local_elediaai_core'),
        get_string('turn_retentiondays_desc', 'local_elediaai_core'),
        90,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_elediaai_core/usage_retentiondays',
        get_string('usage_retentiondays', 'local_elediaai_core'),
        get_string('usage_retentiondays_desc', 'local_elediaai_core'),
        quota_manager::RETENTION_DAYS,
        PARAM_INT
    ));

    $settings->add(new admin_setting_heading(
        'local_elediaai_core/quota_settings_heading',
        get_string('quota_settings_heading', 'local_elediaai_core'),
        get_string('quota_settings_desc', 'local_elediaai_core')
    ));

    foreach (['student', 'teacher'] as $bucket) {
        foreach (['hour', 'day', 'month'] as $window) {
            $settings->add(new admin_setting_configtext(
                'local_elediaai_core/quota_' . $bucket . '_' . $window,
                get_string('quota_' . $bucket . '_' . $window, 'local_elediaai_core'),
                get_string('quota_limit_desc', 'local_elediaai_core'),
                0,
                PARAM_INT
            ));
        }
    }

    $settings->add(new admin_setting_configtext(
        'local_elediaai_core/quota_completion_buffer',
        get_string('quota_completion_buffer', 'local_elediaai_core'),
        get_string('quota_completion_buffer_desc', 'local_elediaai_core'),
        500,
        PARAM_INT
    ));

    foreach ([256, 512, 1024] as $edge) {
        $settings->add(new admin_setting_configtext(
            'local_elediaai_core/quota_image_cost_' . $edge,
            get_string('quota_image_cost_' . $edge, 'local_elediaai_core'),
            get_string('quota_image_cost_desc', 'local_elediaai_core'),
            constant('local_elediaai_core\\local\\quota_manager::DEFAULT_IMAGE_COST_' . $edge),
            PARAM_INT
        ));
    }

    $settings->add(new admin_setting_heading(
        'local_elediaai_core/developerdocs_heading',
        get_string('developerdocs_heading', 'local_elediaai_core'),
        get_string('developerdocs_heading_desc', 'local_elediaai_core')
    ));

    // Vorgabe 0: die Seite ist fuer Menschen geschrieben, die Plugins bauen.
    // Wer die Website nur betreibt, soll sie nicht erst wegklicken muessen.
    $settings->add(new admin_setting_configcheckbox(
        'local_elediaai_core/developerdocs',
        get_string('developerdocs', 'local_elediaai_core'),
        get_string('developerdocs_desc', 'local_elediaai_core'),
        0
    ));

    $ADMIN->add('localplugins', $settings);
}

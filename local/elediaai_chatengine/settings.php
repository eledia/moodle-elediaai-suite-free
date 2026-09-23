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
 * Administration settings for the AI chat engine.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_elediaai_chatengine_settings',
        get_string('pluginname', 'local_elediaai_chatengine')
    );
    $ADMIN->add('localplugins', $settings);

    // Which backend answers is not settable here; it follows the destination
    // configured in local_elediaai_sources. Only the connection to it is.
    $settings->add(new admin_setting_heading(
        'local_elediaai_chatengine/head_backend',
        get_string('settings_head_backend', 'local_elediaai_chatengine'),
        get_string('settings_head_backend_desc', 'local_elediaai_chatengine')
    ));

    // ... with exactly one exception, and it announces itself. The simulator
    // answers without a language model, so it belongs to no destination; it
    // is here rather than in local_elediaai_sources because nothing is
    // ingested for it.
    $settings->add(new admin_setting_configcheckbox(
        'local_elediaai_chatengine/' . \local_elediaai_chatengine\adapter\simulator_adapter::SETTING,
        get_string('setting_simulator', 'local_elediaai_chatengine'),
        get_string('setting_simulator_desc', 'local_elediaai_chatengine'),
        0
    ));

    $settings->add(new admin_setting_heading(
        'local_elediaai_chatengine/head_ingestionapi',
        get_string('settings_head_ingestionapi', 'local_elediaai_chatengine'),
        get_string('settings_head_ingestionapi_desc', 'local_elediaai_chatengine')
    ));

    $settings->add(new admin_setting_configtext(
        'local_elediaai_chatengine/backend_ingestionapi_url',
        get_string('setting_backend_ingestionapi_url', 'local_elediaai_chatengine'),
        get_string('setting_backend_ingestionapi_url_desc', 'local_elediaai_chatengine'),
        '',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configselect(
        'local_elediaai_chatengine/backend_ingestionapi_authmethod',
        get_string('setting_backend_ingestionapi_authmethod', 'local_elediaai_chatengine'),
        get_string('setting_backend_ingestionapi_authmethod_desc', 'local_elediaai_chatengine'),
        'none',
        [
            'none' => get_string('setting_authmethod_none', 'local_elediaai_chatengine'),
            'bearer' => get_string('setting_authmethod_bearer', 'local_elediaai_chatengine'),
            'header' => get_string('setting_authmethod_header', 'local_elediaai_chatengine'),
        ]
    ));

    $settings->add(new admin_setting_encryptedpassword(
        'local_elediaai_chatengine/backend_ingestionapi_authtoken',
        get_string('setting_backend_ingestionapi_authtoken', 'local_elediaai_chatengine'),
        get_string('setting_backend_ingestionapi_authtoken_desc', 'local_elediaai_chatengine')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_elediaai_chatengine/backend_ingestionapi_allowinsecure',
        get_string('setting_backend_ingestionapi_allowinsecure', 'local_elediaai_chatengine'),
        get_string('setting_backend_ingestionapi_allowinsecure_desc', 'local_elediaai_chatengine'),
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_elediaai_chatengine/backend_ingestionapi_allowprivate',
        get_string('setting_backend_ingestionapi_allowprivate', 'local_elediaai_chatengine'),
        get_string('setting_backend_ingestionapi_allowprivate_desc', 'local_elediaai_chatengine'),
        0
    ));

    // The six tool names of the documented backend contract. They belong to the
    // backend, not to any one placement, which is why they moved out of the
    // tutor block along with the transport.
    $tooldefaults = [
        'chat' => 'tutor_chat',
        'history' => '',
        'delete' => '',
        'deleteuser' => '',
        'memoryoptin' => '',
        'recluster' => '',
    ];
    foreach ($tooldefaults as $key => $default) {
        $settings->add(new admin_setting_configtext(
            'local_elediaai_chatengine/tool_' . $key,
            get_string('setting_tool_' . $key, 'local_elediaai_chatengine'),
            get_string('setting_tool_' . $key . '_desc', 'local_elediaai_chatengine'),
            $default,
            PARAM_ALPHANUMEXT
        ));
    }

    // LiteRAG needs no connection settings; the heading says so rather than
    // leaving an administrator looking for the ones that are missing.
    $settings->add(new admin_setting_heading(
        'local_elediaai_chatengine/head_literag',
        get_string('settings_head_literag', 'local_elediaai_chatengine'),
        get_string('settings_head_literag_desc', 'local_elediaai_chatengine')
    ));

    $settings->add(new admin_setting_heading(
        'local_elediaai_chatengine/head_design',
        get_string('settings_head_design', 'local_elediaai_chatengine'),
        get_string('settings_head_design_desc', 'local_elediaai_chatengine')
    ));

    if (during_initial_install() === false) {
        $settings->add(new admin_setting_configselect(
            'local_elediaai_chatengine/sitedesign',
            get_string('setting_sitedesign', 'local_elediaai_chatengine'),
            get_string('setting_sitedesign_desc', 'local_elediaai_chatengine'),
            \local_elediaai_chatengine\local\design::NONE,
            \local_elediaai_chatengine\local\design::menu()
        ));
    }

    $settings->add(new admin_setting_heading(
        'local_elediaai_chatengine/head_limits',
        get_string('settings_head_limits', 'local_elediaai_chatengine'),
        ''
    ));

    // Both backends call back into Moodle as the asking user, so the service
    // that carries those callbacks is an engine setting.
    global $DB;

    $serviceoptions = [0 => get_string('setting_authmethod_none', 'local_elediaai_chatengine')];
    if (during_initial_install() === false) {
        $mcpapi = '\\webservice_elediamcp\\api';
        if (class_exists($mcpapi)) {
            foreach (call_user_func([$mcpapi, 'get_services']) as $service) {
                $serviceoptions[(int) $service->id] = format_string($service->name);
            }
        } else {
            $services = $DB->get_records('external_services', ['enabled' => 1], 'name ASC', 'id, name');
            foreach ($services as $service) {
                $serviceoptions[(int) $service->id] = format_string($service->name);
            }
        }
    }

    $settings->add(new admin_setting_configselect(
        'local_elediaai_chatengine/mcpserviceid',
        get_string('setting_mcpserviceid', 'local_elediaai_chatengine'),
        get_string('setting_mcpserviceid_desc', 'local_elediaai_chatengine'),
        0,
        $serviceoptions
    ));

    $integers = [
        'tokenlifetime' => 3600,
        'requesttimeout' => 30,
        'maxmessagelength' => 4000,
        'historylimit' => 20,
        'maxscopecourses' => 20,
        'ratelimitperminute' => 20,
        'dailymessagelimit' => 0,
        'retentiondays' => 0,
    ];
    foreach ($integers as $name => $default) {
        $settings->add(new admin_setting_configtext(
            'local_elediaai_chatengine/' . $name,
            get_string('setting_' . $name, 'local_elediaai_chatengine'),
            get_string('setting_' . $name . '_desc', 'local_elediaai_chatengine'),
            $default,
            PARAM_INT
        ));
    }

    $settings->add(new admin_setting_configcheckbox(
        'local_elediaai_chatengine/streamingenabled',
        get_string('setting_streamingenabled', 'local_elediaai_chatengine'),
        get_string('setting_streamingenabled_desc', 'local_elediaai_chatengine'),
        1
    ));
}

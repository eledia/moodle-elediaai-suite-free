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
 * Admin settings for local_literag.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_literag\output\shell;

if ($hassiteconfig) {
    require_once(__DIR__ . '/classes/output/shell.php');

    $settings = new admin_settingpage('local_literag', get_string('pluginname', 'local_literag'));
    $ADMIN->add('localplugins', $settings);

    $currentsection = optional_param('section', '', PARAM_ALPHANUMEXT);
    if ($ADMIN->fulltree && $currentsection === 'local_literag') {
        global $OUTPUT, $PAGE;

        shell::require_css();
        $PAGE->add_body_class('path-local-literag');
        $PAGE->add_body_class('lh-plugin-shell-page');
        $PAGE->add_body_class('lr-admin-settings-shell-page');
        $PAGE->add_body_class('lr-admin-settings-pending');

        $sectioncards = [
            [
                'key' => 'connection',
                'title' => get_string('head_connection', 'local_literag'),
                'body' => get_string('settings_section_connection_desc', 'local_literag'),
            ],
            [
                'key' => 'llm',
                'title' => get_string('head_llm', 'local_literag'),
                'body' => get_string('settings_section_llm_desc', 'local_literag'),
            ],
            [
                'key' => 'retrieval',
                'title' => get_string('head_retrieval', 'local_literag'),
                'body' => get_string('settings_section_retrieval_desc', 'local_literag'),
            ],
            [
                'key' => 'livetools',
                'title' => get_string('head_livetools', 'local_literag'),
                'body' => get_string('settings_section_livetools_desc', 'local_literag'),
            ],
            [
                'key' => 'tools',
                'title' => get_string('head_tools', 'local_literag'),
                'body' => get_string('settings_section_tools_desc', 'local_literag'),
            ],
            [
                'key' => 'privacy',
                'title' => get_string('head_privacy', 'local_literag'),
                'body' => get_string('settings_section_privacy_desc', 'local_literag'),
            ],
        ];
        // The config carries a rendered header (~2 KB), well past the 1024-char
        // budget js_call_amd warns about — and with developer debugging on, that
        // notice surfaces as an error on every visit to this page. Invoke the
        // same module through an inline require() instead, so the payload travels
        // in an inline <script> rather than the AMD argument string. The init()
        // contract is unchanged. The JSON_HEX_* flags keep the markup in
        // headerHtml from breaking out of the script. Same approach as
        // blocks/elediaai_tutor/settings.php, which hit this first.
        $shellconfig = [
            'headerHtml' => shell::header_html(shell::ACTIVE_SETTINGS),
            'sectionCards' => $sectioncards,
            'pluginTitle' => get_string('pluginname', 'local_literag'),
            'hubDesc' => get_string('settings_hub_desc', 'local_literag'),
        ];
        $encodedconfig = json_encode(
            $shellconfig,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
        );
        $PAGE->requires->js_amd_inline(
            "require(['local_literag/settings_shell'], function(shell) {"
            . "shell.init({$encodedconfig});"
            . "});"
        );
    }

    // Connection (ingestion + transport).
    $settings->add(new admin_setting_heading(
        'local_literag/head_connection',
        get_string('head_connection', 'local_literag'),
        ''
    ));

    $upserturl = (new moodle_url('/local/literag/ingest.php/documents/upsert'))->out(false);
    $mcpurl = (new moodle_url('/local/literag/mcp.php'))->out(false);
    $settings->add(new admin_setting_description(
        'local_literag/endpointinfo',
        get_string('endpointinfo', 'local_literag'),
        get_string('endpointinfo_desc', 'local_literag', (object) ['upsert' => $upserturl, 'mcp' => $mcpurl])
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_literag/ingest_api_key',
        get_string('ingest_api_key', 'local_literag'),
        get_string('ingest_api_key_desc', 'local_literag'),
        ''
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_literag/transport_auth_token',
        get_string('transport_auth_token', 'local_literag'),
        get_string('transport_auth_token_desc', 'local_literag'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_literag/rate_limit_per_minute',
        get_string('rate_limit_per_minute', 'local_literag'),
        get_string('rate_limit_per_minute_desc', 'local_literag'),
        20,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_literag/rate_limit_per_day',
        get_string('rate_limit_per_day', 'local_literag'),
        get_string('rate_limit_per_day_desc', 'local_literag'),
        200,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_literag/emergency_disable',
        get_string('emergency_disable', 'local_literag'),
        get_string('emergency_disable_desc', 'local_literag'),
        0
    ));

    // LLM client.
    $settings->add(new admin_setting_heading(
        'local_literag/head_llm',
        get_string('head_llm', 'local_literag'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_literag/llm_base_url',
        get_string('llm_base_url', 'local_literag'),
        get_string('llm_base_url_desc', 'local_literag'),
        'https://api.openai.com/v1',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_literag/llm_api_key',
        get_string('llm_api_key', 'local_literag'),
        get_string('llm_api_key_desc', 'local_literag'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_literag/llm_model',
        get_string('llm_model', 'local_literag'),
        get_string('llm_model_desc', 'local_literag'),
        'gpt-4o-mini',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_literag/llm_temperature',
        get_string('llm_temperature', 'local_literag'),
        get_string('llm_temperature_desc', 'local_literag'),
        '0.2',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_literag/llm_max_tokens',
        get_string('llm_max_tokens', 'local_literag'),
        get_string('llm_max_tokens_desc', 'local_literag'),
        1024,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_literag/llm_timeout',
        get_string('llm_timeout', 'local_literag'),
        get_string('llm_timeout_desc', 'local_literag'),
        25,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_literag/llm_allow_private',
        get_string('llm_allow_private', 'local_literag'),
        get_string('llm_allow_private_desc', 'local_literag'),
        0
    ));

    // Retrieval & chunking.
    $settings->add(new admin_setting_heading(
        'local_literag/head_retrieval',
        get_string('head_retrieval', 'local_literag'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_literag/chunk_size',
        get_string('chunk_size', 'local_literag'),
        get_string('chunk_size_desc', 'local_literag'),
        1200,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_literag/chunk_overlap',
        get_string('chunk_overlap', 'local_literag'),
        get_string('chunk_overlap_desc', 'local_literag'),
        150,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_literag/retrieval_candidates',
        get_string('retrieval_candidates', 'local_literag'),
        get_string('retrieval_candidates_desc', 'local_literag'),
        20,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_literag/context_chunks',
        get_string('context_chunks', 'local_literag'),
        get_string('context_chunks_desc', 'local_literag'),
        5,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_literag/enable_rerank',
        get_string('enable_rerank', 'local_literag'),
        get_string('enable_rerank_desc', 'local_literag'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'local_literag/rerank_model',
        get_string('rerank_model', 'local_literag'),
        get_string('rerank_model_desc', 'local_literag'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_literag/pdftotext_path',
        get_string('pdftotext_path', 'local_literag'),
        get_string('pdftotext_path_desc', 'local_literag'),
        '',
        PARAM_PATH
    ));

    // Live Moodle tools (webservice_elediamcp).
    $settings->add(new admin_setting_heading(
        'local_literag/head_livetools',
        get_string('head_livetools', 'local_literag'),
        get_string('head_livetools_desc', 'local_literag')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_literag/enable_mcp_tools',
        get_string('enable_mcp_tools', 'local_literag'),
        get_string('enable_mcp_tools_desc', 'local_literag'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_literag/enable_write_tools',
        get_string('enable_write_tools', 'local_literag'),
        get_string('enable_write_tools_desc', 'local_literag'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'local_literag/max_tool_iterations',
        get_string('max_tool_iterations', 'local_literag'),
        get_string('max_tool_iterations_desc', 'local_literag'),
        4,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_literag/mcp_timeout',
        get_string('mcp_timeout', 'local_literag'),
        get_string('mcp_timeout_desc', 'local_literag'),
        10,
        PARAM_INT
    ));

    // Tool names (must match the tutor block).
    $settings->add(new admin_setting_heading(
        'local_literag/head_tools',
        get_string('head_tools', 'local_literag'),
        get_string('head_tools_desc', 'local_literag')
    ));

    $settings->add(new admin_setting_configtext(
        'local_literag/chattoolname',
        get_string('chattoolname', 'local_literag'),
        get_string('chattoolname_desc', 'local_literag'),
        'tutor_chat',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_literag/historytoolname',
        get_string('historytoolname', 'local_literag'),
        get_string('historytoolname_desc', 'local_literag'),
        'tutor_get_history',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_literag/deletetoolname',
        get_string('deletetoolname', 'local_literag'),
        get_string('deletetoolname_desc', 'local_literag'),
        'tutor_delete_conversation',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_literag/deleteusertoolname',
        get_string('deleteusertoolname', 'local_literag'),
        get_string('deleteusertoolname_desc', 'local_literag'),
        'tutor_delete_user_data',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_literag/memoryoptintoolname',
        get_string('memoryoptintoolname', 'local_literag'),
        get_string('memoryoptintoolname_desc', 'local_literag'),
        'tutor_set_memory_optin',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_literag/reclustertoolname',
        get_string('reclustertoolname', 'local_literag'),
        get_string('reclustertoolname_desc', 'local_literag'),
        'tutor_recluster_questions',
        PARAM_ALPHANUMEXT
    ));

    // Memory, logging & retention.
    $settings->add(new admin_setting_heading(
        'local_literag/head_privacy',
        get_string('head_privacy', 'local_literag'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_literag/enable_memory',
        get_string('enable_memory', 'local_literag'),
        get_string('enable_memory_desc', 'local_literag'),
        0
    ));

    $settings->add(new admin_setting_configselect(
        'local_literag/log_verbosity',
        get_string('log_verbosity', 'local_literag'),
        get_string('log_verbosity_desc', 'local_literag'),
        1,
        [
            0 => get_string('loglevel_errors', 'local_literag'),
            1 => get_string('loglevel_counts', 'local_literag'),
            2 => get_string('loglevel_full', 'local_literag'),
        ]
    ));

    $settings->add(new admin_setting_configtext(
        'local_literag/query_log_retention_days',
        get_string('query_log_retention_days', 'local_literag'),
        get_string('query_log_retention_days_desc', 'local_literag'),
        90,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_literag/conversation_retention_days',
        get_string('conversation_retention_days', 'local_literag'),
        get_string('conversation_retention_days_desc', 'local_literag'),
        365,
        PARAM_INT
    ));
}

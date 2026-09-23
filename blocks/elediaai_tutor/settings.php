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
 * Global admin settings for the eLeDia.ai Tutor block.
 *
 * The infrastructure/security settings (RAG server, MCP token, limits, privacy)
 * are declared by hand. Everything that defines a *tutor* — persona, every visual
 * `--eac-*` token, launcher, footer, images and a few behaviour toggles — is
 * generated from {@see \block_elediaai_tutor\local\registry}, each followed by an
 * "allow per-instance override" checkbox (expose_<key>).
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use block_elediaai_tutor\local\branding;
use block_elediaai_tutor\local\registry;
use block_elediaai_tutor\output\shell;

if ($hassiteconfig) {
    require_once(__DIR__ . '/classes/local/security.php');
    require_once(__DIR__ . '/classes/local/registry.php');
    require_once(__DIR__ . '/classes/local/branding.php');
    require_once(__DIR__ . '/classes/output/shell.php');

    $currentsection = optional_param('section', '', PARAM_ALPHANUMEXT);
    // Guard the $PAGE->url read: while the admin tree is built during install/upgrade
    // (admin_apply_default_settings) no URL is set yet, and reading it would emit a
    // "did not call $PAGE->set_url()" debugging notice — which moodle-plugin-ci treats
    // as a failure. has_set_url() is false there, so we simply skip the decoration.
    $decoratecoresettingspage = $PAGE->has_set_url()
        && $PAGE->url->get_path() === '/' . $CFG->admin . '/settings.php';
    if ($ADMIN->fulltree && $currentsection === 'blocksettingelediaai_tutor' && $decoratecoresettingspage) {
        global $OUTPUT, $PAGE;
        shell::require_css();

        if (shell::is_available()) {
            $PAGE->add_body_class('eat-admin-settings-pending');
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
            $headerhtml = $OUTPUT->render_from_template(
                'block_elediaai_tutor/plugin_shell_header',
                shell::context(shell::ACTIVE_SETTINGS, true)
            );
            // The shell config carries a rendered header template (~2 KB), which exceeds the
            // 1024-char budget js_call_amd warns about (and dev debugging escalates to a fatal).
            // Invoke the same module via an inline require() instead, so the payload travels in
            // an inline <script> rather than the AMD argument string. The init() contract is
            // unchanged. JSON_HEX_TAG keeps any markup in headerHtml from breaking the script.
            $shellconfig = [
                'headerHtml' => $headerhtml,
                'sectionCards' => $sectioncards,
                'pluginTitle' => get_string('pluginname', 'block_elediaai_tutor'),
                'hubTitle' => get_string('settings_hub_title', 'block_elediaai_tutor'),
                'hubDesc' => get_string('settings_hub_desc', 'block_elediaai_tutor'),
                'topicsLabel' => get_string('settings_hub_topics_label', 'block_elediaai_tutor'),
                'backLabel' => get_string('settings_back_to_overview', 'block_elediaai_tutor'),
                'exposeLabel' => get_string('settings_expose_inline_label', 'block_elediaai_tutor'),
                'cancelLabel' => get_string('cancel'),
                'cancelUrl' => (new moodle_url('/local/elediaai_core/health.php'))->out(false),
            ];
            $encodedconfig = json_encode(
                $shellconfig,
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
            );
            $PAGE->requires->js_amd_inline(
                "require(['block_elediaai_tutor/settings_shell'], function(shell) {"
                . "shell.init({$encodedconfig});"
                . "});"
            );
        }
    }

                // Shell-friendly configuration overview.
    $ADMIN->add('blocksettings', new admin_externalpage(
        'block_elediaai_tutor_configuration',
        get_string('configuration', 'block_elediaai_tutor'),
        new moodle_url('/local/elediaai_core/health.php'),
        'moodle/site:config'
    ));

    // The tutor library / import-export management page.
    $ADMIN->add('blocksettings', new admin_externalpage(
        'block_elediaai_tutor_managetutors',
        get_string('managetutors', 'block_elediaai_tutor'),
        new moodle_url('/blocks/elediaai_tutor/manage_tutors.php'),
        'moodle/site:config'
    ));

    // Offer the MCP services declared by webservice_elediamcp when available,
    // else fall back to all enabled external services.
    $serviceoptions = [0 => get_string('setting_mcpserviceid_none', 'block_elediaai_tutor')];
    if (during_initial_install() === false) {
        $mcpapi = '\\webservice_elediamcp\\api';
        if (class_exists($mcpapi)) {
            foreach (call_user_func([$mcpapi, 'get_services']) as $svc) {
                $serviceoptions[(int) $svc->id] = format_string($svc->name);
            }
        } else {
            global $DB;
            $services = $DB->get_records('external_services', ['enabled' => 1], 'name ASC', 'id, name');
            foreach ($services as $svc) {
                $serviceoptions[(int) $svc->id] = format_string($svc->name);
            }
        }
    }

    // Friendly label/description with a sensible fallback for the ~40 raw tokens.
    $reglabel = function (string $key, array $entry): string {
        if (get_string_manager()->string_exists('reg_' . $key, 'block_elediaai_tutor')) {
            return get_string('reg_' . $key, 'block_elediaai_tutor');
        }
        return (string) ($entry['token'] ?? $key);
    };
    $regdesc = function (string $key): string {
        if (get_string_manager()->string_exists('reg_' . $key . '_desc', 'block_elediaai_tutor')) {
            return get_string('reg_' . $key . '_desc', 'block_elediaai_tutor');
        }
        return get_string('reg_tokenhint', 'block_elediaai_tutor');
    };
    // Registry key => [storedfile config name, file area] for the image settings.
    $filemap = [
        'logo' => ['brandlogo', branding::LOGO_FILEAREA],
        'avatar' => ['brandavatar', branding::AVATAR_FILEAREA],
    ];
    $imageopts = ['maxfiles' => 1, 'accepted_types' => ['.png', '.jpg', '.jpeg', '.svg', '.webp', '.gif']];

    $infocards = static function (array $cards): string {
        $html = html_writer::start_div('eat-settings-infocards');
        foreach ($cards as $card) {
            $html .= html_writer::div(
                html_writer::span(
                    \block_elediaai_tutor\local\icon::render($card['icon']),
                    'eat-settings-infocard__icon'
                ) .
                html_writer::div(
                    html_writer::div($card['title'], 'eat-settings-infocard__title') .
                    html_writer::div($card['body'], 'eat-settings-infocard__body'),
                    'eat-settings-infocard__text'
                ),
                'eat-settings-infocard'
            );
        }
        return $html . html_writer::end_div();
    };

    $addregistrygroup = function (string $group) use ($settings, $reglabel, $regdesc, $filemap, $imageopts): void {
        $keys = array_values(array_filter(
            registry::group_keys($group),
            static fn(string $key): bool => registry::is_available($key)
        ));
        if (empty($keys)) {
            return;
        }
        $settings->add(new admin_setting_heading(
            'block_elediaai_tutor/reggroup_' . $group,
            get_string('reggroup_' . $group, 'block_elediaai_tutor'),
            ''
        ));

        foreach ($keys as $key) {
            $entry = registry::get($key);
            $label = $reglabel($key, $entry);
            $desc = $regdesc($key);
            $default = $entry['default'];

            if ($entry['type'] === 'file') {
                [$cfgname, $filearea] = $filemap[$key];
                $settings->add(new admin_setting_configstoredfile(
                    'block_elediaai_tutor/' . $cfgname,
                    $label,
                    $desc,
                    $filearea,
                    0,
                    $imageopts
                ));
            } else if ($entry['type'] === 'colour') {
                $settings->add(new admin_setting_configcolourpicker(
                    'block_elediaai_tutor/' . registry::sitekey($key),
                    $label,
                    $desc,
                    (string) $default
                ));
            } else if ($entry['type'] === 'checkbox') {
                $settings->add(new admin_setting_configcheckbox(
                    'block_elediaai_tutor/' . registry::sitekey($key),
                    $label,
                    $desc,
                    (int) $default
                ));
            } else if ($entry['type'] === 'select') {
                $options = [];
                foreach ($entry['options'] as $value => $optkey) {
                    $options[$value] = get_string($optkey, 'block_elediaai_tutor');
                }
                $settings->add(new admin_setting_configselect(
                    'block_elediaai_tutor/' . registry::sitekey($key),
                    $label,
                    $desc,
                    (string) $default,
                    $options
                ));
            } else if ($entry['type'] === 'coursescope') {
                $settings->add(new \core_admin\local\settings\autocomplete(
                    'block_elediaai_tutor/' . registry::sitekey($key),
                    $label,
                    $desc,
                    [],
                    \local_elediaai_chatengine\local\knowledge_scope::options(),
                    [
                        'multiple' => true,
                        'delimiter' => ',',
                        'placeholder' => get_string('search'),
                        'manageurl' => false,
                        'managetext' => '',
                    ]
                ));
            } else if ($entry['type'] === 'textarea') {
                $settings->add(new admin_setting_configtextarea(
                    'block_elediaai_tutor/' . registry::sitekey($key),
                    $label,
                    $desc,
                    (string) $default
                ));
            } else if (!empty($entry['choices'])) {
                // Cssvalue / font with friendly named options → a dropdown so no
                // one has to type raw CSS. The empty value is the built-in default.
                $cfgname = registry::sitekey($key);
                $current = get_config('block_elediaai_tutor', $cfgname);
                $options = registry::choice_select_options(
                    $key,
                    get_string('reg_opt_default', 'block_elediaai_tutor'),
                    $current === false ? null : (string) $current
                );
                $settings->add(new admin_setting_configselect(
                    'block_elediaai_tutor/' . $cfgname,
                    $label,
                    $desc,
                    (string) $default,
                    $options
                ));
            } else {
                // Free text (persona fields, labels, footer text).
                $settings->add(new admin_setting_configtext(
                    'block_elediaai_tutor/' . registry::sitekey($key),
                    $label,
                    $desc,
                    (string) $default,
                    PARAM_TEXT
                ));
            }

            // The "Allow per-instance override" companion checkbox.
            if (!empty($entry['instanceable'])) {
                $settings->add(new admin_setting_configcheckbox(
                    'block_elediaai_tutor/' . registry::EXPOSE_PREFIX . $key,
                    get_string('expose_label', 'block_elediaai_tutor', $label),
                    get_string('expose_desc', 'block_elediaai_tutor'),
                    !empty($entry['exposedefault']) ? 1 : 0
                ));
            }
        }
    };

    // Design.
    $settings->add(new admin_setting_heading(
        'block_elediaai_tutor/sectiondesign',
        get_string('setting_section_design', 'block_elediaai_tutor'),
        ''
    ));

    foreach (['accent', 'surfaces', 'text', 'bubbles', 'states', 'shape', 'effects', 'launcher', 'footer', 'files'] as $group) {
        $addregistrygroup($group);
    }

    // Admin custom CSS (trusted; targets the widget's .elediaai-chat-* classes).
    $settings->add(new admin_setting_configtextarea(
        'block_elediaai_tutor/customcss',
        get_string('setting_customcss', 'block_elediaai_tutor'),
        get_string('setting_customcss_desc', 'block_elediaai_tutor'),
        '',
        PARAM_RAW
    ));

    // Conversation & display.
    $settings->add(new admin_setting_heading(
        'block_elediaai_tutor/sectionconversation',
        get_string('setting_section_conversation', 'block_elediaai_tutor'),
        ''
    ));

    $settings->add(new admin_setting_heading(
        'block_elediaai_tutor/headerchatactivation',
        get_string('setting_header_chat_activation', 'block_elediaai_tutor'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'block_elediaai_tutor/enableglobalchat',
        get_string('setting_enableglobalchat', 'block_elediaai_tutor'),
        get_string('setting_enableglobalchat_desc', 'block_elediaai_tutor'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'block_elediaai_tutor/enablecoursechat',
        get_string('setting_enablecoursechat', 'block_elediaai_tutor'),
        get_string('setting_enablecoursechat_desc', 'block_elediaai_tutor'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'block_elediaai_tutor/enablesitewidechat',
        get_string('setting_enablesitewidechat', 'block_elediaai_tutor'),
        get_string('setting_enablesitewidechat_desc', 'block_elediaai_tutor'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'block_elediaai_tutor/allowllmonly',
        get_string('setting_allowllmonly', 'block_elediaai_tutor'),
        get_string('setting_allowllmonly_desc', 'block_elediaai_tutor'),
        1
    ));

    foreach (['persona', 'conversation', 'dashboard'] as $group) {
        $addregistrygroup($group);
    }

    // AI-Home: pointer to the full-page start experience and how to make it
    // the site's landing page (defaulthomepage → custom URL, Moodle 4.5+).
    $settings->add(new admin_setting_heading(
        'block_elediaai_tutor/headerhome',
        get_string('setting_header_home', 'block_elediaai_tutor'),
        get_string(
            'setting_header_home_desc',
            'block_elediaai_tutor',
            (new moodle_url('/blocks/elediaai_tutor/home.php'))->out(false)
        )
    ));

    $settings->add(new admin_setting_heading(
        'block_elediaai_tutor/headerprivacy',
        get_string('setting_header_privacy', 'block_elediaai_tutor'),
        ''
    ));

    $settings->add(new admin_setting_confightmleditor(
        'block_elediaai_tutor/privacyguidelinestext',
        get_string('setting_privacyguidelinestext', 'block_elediaai_tutor'),
        get_string('setting_privacyguidelinestext_desc', 'block_elediaai_tutor'),
        ''
    ));

    // Technical settings.
    $settings->add(new admin_setting_heading(
        'block_elediaai_tutor/sectiontechnical',
        get_string('setting_section_technical', 'block_elediaai_tutor'),
        ''
    ));

    $settings->add(new admin_setting_heading(
        'block_elediaai_tutor/headerrag',
        get_string('setting_header_rag', 'block_elediaai_tutor'),
        get_string('setting_header_rag_desc', 'block_elediaai_tutor')
    ));



    // Encrypted at rest with the site encryption key (moodledata), decrypted
    // server-side only in security::rag_auth_token(). See SUI-416.










    $settings->add(new admin_setting_heading(
        'block_elediaai_tutor/headertoken',
        get_string('setting_header_token', 'block_elediaai_tutor'),
        get_string('setting_header_token_desc', 'block_elediaai_tutor')
    ));



    $settings->add(new admin_setting_heading(
        'block_elediaai_tutor/headerbehaviour',
        get_string('setting_header_behaviour', 'block_elediaai_tutor'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'block_elediaai_tutor/analyticsretentiondays',
        get_string('setting_analyticsretention', 'block_elediaai_tutor'),
        get_string('setting_analyticsretention_desc', 'block_elediaai_tutor'),
        180,
        PARAM_INT
    ));


    $settings->add(new admin_setting_configselect(
        'block_elediaai_tutor/loggingverbosity',
        get_string('setting_loggingverbosity', 'block_elediaai_tutor'),
        get_string('setting_loggingverbosity_desc', 'block_elediaai_tutor'),
        1,
        [
            0 => get_string('loglevel_errors', 'block_elediaai_tutor'),
            1 => get_string('loglevel_normal', 'block_elediaai_tutor'),
            2 => get_string('loglevel_verbose', 'block_elediaai_tutor'),
        ]
    ));
}

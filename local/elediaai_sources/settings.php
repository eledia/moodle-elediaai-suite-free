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
 * Admin settings for the AI Sources plugin.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_elediaai_core\output\plugin_page;
use local_elediaai_sources\output\shell;
use core_admin\local\settings\autocomplete;

if ($hassiteconfig) {
    require_once(__DIR__ . '/classes/output/shell.php');

    $settings = new admin_settingpage('local_elediaai_sources_settings', get_string('pluginname', 'local_elediaai_sources'));
    $fallbackreindexhtml = '';

    $currentsection = optional_param('section', '', PARAM_ALPHANUMEXT);
    if ($ADMIN->fulltree && $currentsection === 'local_elediaai_sources_settings') {
        global $OUTPUT, $PAGE;

        shell::require_css();

        $pendingcount = \local_elediaai_sources\course_state::pending_ingestion_count();
        $partialcount = \local_elediaai_sources\course_state::partial_ingestion_count();
        // Three states, in order of what needs attention: courses holding
        // nothing yet, courses holding only part of their content, and done.
        $unfinished = $pendingcount > 0 || $partialcount > 0;
        $panelclass = $unfinished ? 'rg-action-panel--warning' : 'rg-action-panel--success';
        $panelicon = $unfinished ? 'upload' : 'check';
        if ($pendingcount > 0) {
            $paneltitle = get_string('pendingindexingtitle', 'local_elediaai_sources');
            $panelbody = $partialcount > 0
                ? get_string('pendingandpartialcount', 'local_elediaai_sources', (object) [
                    'pending' => $pendingcount,
                    'partial' => $partialcount,
                ])
                : get_string('pendingindexingcount', 'local_elediaai_sources', $pendingcount);
        } else if ($partialcount > 0) {
            $paneltitle = get_string('partialindexingtitle', 'local_elediaai_sources');
            $panelbody = get_string('partialindexingcount', 'local_elediaai_sources', $partialcount);
        } else {
            $paneltitle = get_string('indexingreadytitle', 'local_elediaai_sources');
            $panelbody = get_string('indexingreadybody', 'local_elediaai_sources');
        }
        $reindexurl = new moodle_url('/local/elediaai_sources/reindex.php');
        $actionhtml = html_writer::link(
            $reindexurl,
            shell::lucide_icon('rotate-cw') .
            html_writer::span(get_string('openreindex', 'local_elediaai_sources')),
            ['class' => 'btn btn-secondary rg-secondary-action rg-action-panel__action']
        );
        if ($unfinished) {
            $actionhtml = html_writer::tag(
                'form',
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'queuepending', 'value' => '1']) .
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
                html_writer::tag(
                    'button',
                    shell::lucide_icon('play') .
                    html_writer::span(get_string('indexreleasedcourses', 'local_elediaai_sources')),
                    ['type' => 'submit', 'class' => 'btn btn-primary rg-primary-action']
                ),
                [
                    'method' => 'post',
                    'action' => $reindexurl->out(false),
                    'class' => 'rg-action-panel__action',
                ]
            );
        }
        $reindexhtml = html_writer::tag(
            'section',
            html_writer::tag(
                'div',
                html_writer::span(
                    shell::lucide_icon($panelicon),
                    'rg-action-panel__icon'
                ) .
                html_writer::tag(
                    'div',
                    html_writer::tag('h3', $paneltitle, ['class' => 'rg-action-panel__title']) .
                    html_writer::tag('p', $panelbody, ['class' => 'rg-action-panel__body']),
                    ['class' => 'rg-action-panel__text']
                ),
                ['class' => 'rg-action-panel__main']
            ) .
            $actionhtml,
            ['class' => 'rg-action-panel ' . $panelclass . ' rg-settings-indexing-panel']
        );

        if (shell::is_available()) {
            $PAGE->add_body_class('path-local-elediaai_sources');
            $PAGE->add_body_class('lh-plugin-shell-page');
            $PAGE->add_body_class('rg-admin-settings-shell-page');
            $PAGE->add_body_class('rg-admin-settings-pending');

            // Die Kopfzeile der gemeinsamen Huelle, als Markup: das AMD-Modul
            // verschiebt sie in Moodles Einstellungsseite, ausgeben laesst sie
            // sich hier also nicht.
            $headerhtml = plugin_page::header_html(shell::header_data(null, shell::ACTIVE_SETTINGS));
            // The markup travels through the page, not through the JS argument
            // list: Moodle warns above 1024 characters there, and a shell header
            // passes that on its own. The module relocates these nodes, so no
            // markup is ever re-parsed from a string.
            $shellsource = html_writer::div(
                html_writer::div($headerhtml, '', ['id' => 'les-shell-header']) .
                html_writer::div($reindexhtml, '', ['id' => 'les-shell-reindex']),
                '',
                ['id' => 'les-shell-source', 'hidden' => 'hidden']
            );
            $settings->add(new admin_setting_description(
                'local_elediaai_sources/shell_source',
                '',
                $shellsource
            ));
            $PAGE->requires->js_call_amd('local_elediaai_sources/settings_shell', 'init', [[
                'pluginTitle' => get_string('pluginname', 'local_elediaai_sources'),
            ]]);
        } else if ($pendingcount > 0) {
            $fallbackreindexhtml = $reindexhtml;
        }
    }

    if ($fallbackreindexhtml !== '') {
        $settings->add(new admin_setting_description(
            'local_elediaai_sources/pending_indexing_notice',
            '',
            $fallbackreindexhtml
        ));
    }

    // Destination.
    $settings->add(new admin_setting_heading(
        'local_elediaai_sources/head_sink',
        get_string('head_sink', 'local_elediaai_sources'),
        get_string('head_sink_desc', 'local_elediaai_sources')
    ));

    $queuecallback = static function (): void {
        \local_elediaai_sources\course_state::queue_divergent_reconciles();
    };

    // Exactly one destination is active. Switching is supported; running two
    // in parallel is not, which is why this is a single choice.
    $sinksetting = new admin_setting_configselect(
        'local_elediaai_sources/sink',
        get_string('sink', 'local_elediaai_sources'),
        get_string('sink_desc', 'local_elediaai_sources'),
        \local_elediaai_sources\sink\sink_manager::DEFAULT_SINK,
        \local_elediaai_sources\sink\sink_manager::menu()
    );
    $sinksetting->set_updatedcallback(function () use ($queuecallback) {
        // Remember the predecessor before reconciling, so the old destination
        // can still be cleared afterwards.
        \local_elediaai_sources\sink\sink_manager::note_switch();
        $queuecallback();
    });
    $settings->add($sinksetting);

    // Destination: external ingestion API.
    $settings->add(new admin_setting_heading(
        'local_elediaai_sources/head_sink_ingestionapi',
        get_string('head_sink_ingestionapi', 'local_elediaai_sources'),
        get_string('head_sink_ingestionapi_desc', 'local_elediaai_sources')
    ));

    // Service base URL. The actions beneath it are fixed by the API
    // specification (/documents/upsert, /documents/delete, /health), so only
    // the base is configured — nothing is derived from one action URL.
    $baseurlsetting = new admin_setting_configtext(
        'local_elediaai_sources/sink_ingestionapi_baseurl',
        get_string('sink_ingestionapi_baseurl', 'local_elediaai_sources'),
        get_string('sink_ingestionapi_baseurl_desc', 'local_elediaai_sources'),
        'http://rag-service:8001',
        PARAM_URL
    );
    $baseurlsetting->set_updatedcallback($queuecallback);
    $settings->add($baseurlsetting);

    // API key (password field, server-side only).
    $apikeysetting = new admin_setting_configpasswordunmask(
        'local_elediaai_sources/sink_ingestionapi_apikey',
        get_string('sink_ingestionapi_apikey', 'local_elediaai_sources'),
        get_string('sink_ingestionapi_apikey_desc', 'local_elediaai_sources'),
        ''
    );
    $apikeysetting->set_updatedcallback($queuecallback);
    $settings->add($apikeysetting);

    // Destination: LiteRAG on this site. Deliberately without settings — the
    // route is derived from wwwroot and the key belongs to local_literag.
    $settings->add(new admin_setting_heading(
        'local_elediaai_sources/head_sink_literag',
        get_string('head_sink_literag', 'local_elediaai_sources'),
        get_string('head_sink_literag_desc', 'local_elediaai_sources')
    ));

    // Connection.
    $settings->add(new admin_setting_heading(
        'local_elediaai_sources/head_connection',
        get_string('head_connection', 'local_elediaai_sources'),
        get_string('head_connection_desc', 'local_elediaai_sources')
    ));

    $privatetargetsetting = new admin_setting_configcheckbox(
        'local_elediaai_sources/allow_private_target',
        get_string('allow_private_target', 'local_elediaai_sources'),
        get_string('allow_private_target_desc', 'local_elediaai_sources'),
        0
    );
    $privatetargetsetting->set_updatedcallback($queuecallback);
    $settings->add($privatetargetsetting);

    // Note: there is deliberately NO tenant setting. The tenant identity is
    // derived from $CFG->wwwroot (see \local_elediaai_sources\tenant), matching what
    // the RAG service verifies on the retrieval path.

    // Course selection.
    $settings->add(new admin_setting_heading(
        'local_elediaai_sources/head_courses',
        get_string('head_courses', 'local_elediaai_sources'),
        get_string('head_courses_desc', 'local_elediaai_sources')
    ));

    // Course marking — category allow-list (opt-in). A course is ingested when
    // its category (or an ancestor) is selected here, unless overridden on the
    // course itself via the AI Sources custom field.
    $categoryoptions = [];
    if (during_initial_install() === false) {
        $categoryoptions = \core_course_category::make_categories_list();
    }
    $allcatsetting = new admin_setting_configcheckbox(
        'local_elediaai_sources/allcategories',
        get_string('allcategories', 'local_elediaai_sources'),
        get_string('allcategories_desc', 'local_elediaai_sources'),
        0
    );
    $allcatsetting->set_updatedcallback($queuecallback);
    $settings->add($allcatsetting);

    $categorysetting = new autocomplete(
        'local_elediaai_sources/enabledcategories',
        get_string('enabledcategories', 'local_elediaai_sources'),
        get_string('enabledcategories_desc', 'local_elediaai_sources'),
        [],
        $categoryoptions,
        [
            'multiple' => true,
            'delimiter' => ',',
            'placeholder' => get_string('search'),
            'manageurl' => false,
            'managetext' => '',
        ]
    );
    // Re-evaluate every course's marking promptly when the list changes.
    $categorysetting->set_updatedcallback($queuecallback);
    $settings->add($categorysetting);

    // Central pilot-course list — select specific courses to ingest regardless
    // of category. Intended for test/pilot phases.
    $courseoptions = [];
    if (during_initial_install() === false) {
        global $DB;

        // The site front page is in the list: it is a course, it can hold
        // material, and since it has no category the pilot list is the only
        // way to name it.
        $courses = $DB->get_records('course', null, 'fullname ASC', 'id, fullname, shortname');
        foreach ($courses as $course) {
            $courseoptions[(string) $course->id] = format_string($course->fullname)
                . ' (' . s($course->shortname) . ')';
        }
    }
    $pilotsetting = new autocomplete(
        'local_elediaai_sources/pilotcourses',
        get_string('pilotcourses', 'local_elediaai_sources'),
        get_string('pilotcourses_desc', 'local_elediaai_sources'),
        [],
        $courseoptions,
        [
            'multiple' => true,
            'delimiter' => ',',
            'placeholder' => get_string('searchcourses', 'local_elediaai_sources'),
            'manageurl' => false,
            'managetext' => '',
        ]
    );
    $pilotsetting->set_updatedcallback($queuecallback);
    $settings->add($pilotsetting);

    // Default for activities the teacher has not decided on. Explicit
    // per-activity decisions always win; this only fills the gap, and
    // switching it never deletes anything — index deletions are driven solely
    // by explicit exclusions. The callback converges only the safe direction:
    // opt-in→opt-out queues ingestion of undecided activities.
    $activitydefaultsetting = new admin_setting_configselect(
        'local_elediaai_sources/activitydefault',
        get_string('activitydefault', 'local_elediaai_sources'),
        get_string('activitydefault_desc', 'local_elediaai_sources'),
        \local_elediaai_sources\activity_gate::MODE_OPTOUT,
        [
            \local_elediaai_sources\activity_gate::MODE_OPTOUT =>
                get_string('activitydefault_optout', 'local_elediaai_sources'),
            \local_elediaai_sources\activity_gate::MODE_OPTIN =>
                get_string('activitydefault_optin', 'local_elediaai_sources'),
        ]
    );
    $activitydefaultsetting->set_updatedcallback(function () {
        \local_elediaai_sources\activity_gate::note_mode_change();
    });
    $settings->add($activitydefaultsetting);

    // Lock teacher editing of the per-course AI Sources override. When on
    // (test-phase lock-down), only managers/admins can change a course's
    // marking; teachers still see it read-only.
    $locksetting = new admin_setting_configcheckbox(
        'local_elediaai_sources/lockcoursemarking',
        get_string('lockcoursemarking', 'local_elediaai_sources'),
        get_string('lockcoursemarking_desc', 'local_elediaai_sources'),
        0
    );
    $locksetting->set_updatedcallback(function () {
        \local_elediaai_sources\setup::sync_field_lock();
        // Toggling the lock changes which rules apply (overrides become inert
        // or effective again) — reconcile affected courses promptly.
        \local_elediaai_sources\course_state::queue_divergent_reconciles();
    });
    $settings->add($locksetting);

    // Extraction options.
    $settings->add(new admin_setting_configcheckbox(
        'local_elediaai_sources/scorm_harvest_slidetext',
        get_string('scorm_harvest_slidetext', 'local_elediaai_sources'),
        get_string('scorm_harvest_slidetext_desc', 'local_elediaai_sources'),
        1
    ));

    // Limits.
    $settings->add(new admin_setting_heading(
        'local_elediaai_sources/head_limits',
        get_string('head_limits', 'local_elediaai_sources'),
        get_string('head_limits_desc', 'local_elediaai_sources')
    ));

    // Max document size in MB.
    $maxsizesetting = new admin_setting_configtext(
        'local_elediaai_sources/max_document_size_mb',
        get_string('max_document_size_mb', 'local_elediaai_sources'),
        get_string('max_document_size_mb_desc', 'local_elediaai_sources'),
        '20',
        PARAM_INT
    );
    $maxsizesetting->set_updatedcallback($queuecallback);
    $settings->add($maxsizesetting);

    // Request timeout in seconds.
    $timeoutsetting = new admin_setting_configtext(
        'local_elediaai_sources/request_timeout_seconds',
        get_string('request_timeout_seconds', 'local_elediaai_sources'),
        get_string('request_timeout_seconds_desc', 'local_elediaai_sources'),
        '30',
        PARAM_INT
    );
    $timeoutsetting->set_updatedcallback($queuecallback);
    $settings->add($timeoutsetting);

    $ADMIN->add('localplugins', $settings);

    // Reindex external page.
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_elediaai_sources_reindex',
        get_string('reindex', 'local_elediaai_sources'),
        new moodle_url('/local/elediaai_sources/reindex.php')
    ));
}

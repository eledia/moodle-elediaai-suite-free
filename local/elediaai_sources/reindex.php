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
 * Admin page for manual course content reindexing.
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
require_capability('local/elediaai_sources:reindex', $context);

$courseid = optional_param('courseid', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$queuepending = optional_param('queuepending', 0, PARAM_BOOL);
$force = optional_param('force', 0, PARAM_BOOL);
$purgeoldsink = optional_param('purgeoldsink', 0, PARAM_BOOL);
$purgeconfirm = optional_param('purgeconfirm', 0, PARAM_BOOL);

$pageurl = new moodle_url('/local/elediaai_sources/reindex.php');
$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_title(get_string('reindex', 'local_elediaai_sources'));
$PAGE->set_heading(get_string('reindex', 'local_elediaai_sources'));
$PAGE->add_body_class('path-local-elediaai_sources');
$PAGE->activityheader->disable();
shell::require_css();

echo $OUTPUT->header();

// Die gemeinsame Huelle des Kerns statt der Vorlage aus dem Tutor-Block.
if (shell::is_available()) {
    plugin_page::open(shell::header_data(null, shell::ACTIVE_REINDEX), plugin_page::MODIFIER_WIDE);
    plugin_shell::content_open();
}
echo html_writer::start_div('rg-shell-card');

if ($purgeoldsink && confirm_sesskey()) {
    // Clearing the destination the site left behind. Deliberately an explicit
    // action: at switch time the old backend is often unreachable, and a
    // failing purge must not block the switch.
    $previousid = \local_elediaai_sources\sink\sink_manager::previous_id();
    $previoussink = $previousid !== ''
        ? \local_elediaai_sources\sink\sink_manager::instance($previousid)
        : null;

    if ($previoussink === null) {
        echo $OUTPUT->notification(get_string('purgeoldsinknone', 'local_elediaai_sources'), 'info');
        echo $OUTPUT->single_button($pageurl, get_string('back'), 'get');
    } else if (!$purgeconfirm) {
        // Probe before asking: an unreachable destination cannot be cleared,
        // and saying so now beats a pile of failing tasks later.
        $health = $previoussink->healthcheck();
        if (empty($health['success'])) {
            echo $OUTPUT->notification(
                get_string('purgeoldsinkunreachable', 'local_elediaai_sources', s((string) $health['error'])),
                'warning'
            );
        }

        $courses = $DB->count_records('local_elediaai_sources_course', ['sink' => $previousid]);
        echo $OUTPUT->confirm(
            get_string('purgeoldsinkconfirm', 'local_elediaai_sources', (object) [
                'sink' => $previoussink::name(),
                'courses' => $courses,
            ]),
            new moodle_url($pageurl, [
                'purgeoldsink' => 1,
                'purgeconfirm' => 1,
                'sesskey' => sesskey(),
            ]),
            $pageurl
        );
    } else {
        // Exactly the courses still recorded as living in the old destination.
        $courseids = $DB->get_fieldset_select(
            'local_elediaai_sources_course',
            'courseid',
            'sink = :sink',
            ['sink' => $previousid]
        );

        foreach ($courseids as $purgecourseid) {
            $task = new \local_elediaai_sources\task\purge_old_sink_task();
            $task->set_custom_data([
                'courseid' => (int) $purgecourseid,
                'sinkid' => $previousid,
            ]);
            \core\task\manager::queue_adhoc_task($task, true);
        }

        \local_elediaai_sources\sink\sink_manager::forget_previous();
        echo $OUTPUT->notification(
            get_string('purgeoldsinkqueued', 'local_elediaai_sources', count($courseids)),
            'success'
        );
        echo $OUTPUT->single_button($pageurl, get_string('back'), 'get');
    }
} else if ($queuepending && confirm_sesskey()) {
    $queued = \local_elediaai_sources\course_state::queue_pending_ingestions();
    echo $OUTPUT->notification(get_string('pendingindexingqueued', 'local_elediaai_sources', $queued), 'success');
    echo $OUTPUT->single_button($pageurl, get_string('back'), 'get');
} else if ($courseid && $confirm && confirm_sesskey()) {
    // Perform reindex.
    try {
        $course = get_course($courseid);
    } catch (\dml_missing_record_exception $e) {
        echo $OUTPUT->notification(get_string('coursenotfound', 'local_elediaai_sources'), 'error');
        echo html_writer::end_div();
        echo html_writer::end_div();
        echo $OUTPUT->footer();
        die();
    }

    echo $OUTPUT->heading(get_string('ingesting', 'local_elediaai_sources', $course->fullname), 3);

    $manager = new \local_elediaai_sources\ingestion_manager();
    $results = $manager->reindex_course($courseid, (bool) $force);

    // Record what the run achieved. A partial result is recorded as such: the
    // documents that arrived are usable, the ones that failed stay pending, so
    // the next reconcile picks them up instead of the course looking untouched.
    $marked = \local_elediaai_sources\course_gate::should_ingest($courseid);
    $hassuccess = false;
    $haspending = false;
    foreach ($results as $result) {
        $status = $result['status'] ?? '';
        if ($status === 'success') {
            $hassuccess = true;
        } else if ($status === 'error') {
            $cmid = (int) ($result['cmid'] ?? 0);
            if ($cmid === 0 || !\local_elediaai_sources\cm_state::is_exhausted($cmid)) {
                $haspending = true;
            }
        }
    }
    if ($marked) {
        $wasingested = \local_elediaai_sources\course_state::is_ingested($courseid);
        // Unchanged modules report 'skipped' although indexed, so the module
        // state is asked as well (AI-80).
        \local_elediaai_sources\course_state::set_ingested(
            $courseid,
            $hassuccess || $wasingested
                || \local_elediaai_sources\course_state::has_indexed_documents($courseid),
            $haspending
        );
    } else {
        \local_elediaai_sources\course_state::mark_purged($courseid);
    }

    // Display results table.
    $table = new html_table();
    $table->head = [
        get_string('modulename', 'local_elediaai_sources'),
        get_string('status', 'local_elediaai_sources'),
        get_string('details', 'local_elediaai_sources'),
    ];
    $table->attributes['class'] = 'generaltable table-striped table-hover table-sm';

    $successcount = 0;
    foreach ($results as $result) {
        $row = new html_table_row();

        $modname = $result['module_name'] ?? get_string('unknownmodule', 'local_elediaai_sources', $result['cmid']);
        $row->cells[] = $modname;

        if ($result['success']) {
            $row->cells[] = html_writer::tag(
                'span',
                get_string('statussuccess', 'local_elediaai_sources'),
                ['class' => 'badge badge-success bg-success']
            );
            $successcount++;
        } else if ($result['status'] === 'skipped') {
            $row->cells[] = html_writer::tag(
                'span',
                get_string('statusskipped', 'local_elediaai_sources'),
                ['class' => 'badge badge-warning bg-warning text-dark']
            );
        } else {
            $row->cells[] = html_writer::tag(
                'span',
                get_string('statuserror', 'local_elediaai_sources'),
                ['class' => 'badge badge-danger bg-danger']
            );
        }

        $row->cells[] = s((string) ($result['message'] ?? ''));
        $table->data[] = $row;
    }

    echo html_writer::start_tag('div', ['role' => 'status', 'aria-live' => 'polite']);
    echo html_writer::table($table);
    if ($haserror) {
        echo $OUTPUT->notification(get_string('reindexcomplete', 'local_elediaai_sources'), 'warning');
    } else {
        echo $OUTPUT->notification(get_string('reindexsuccess', 'local_elediaai_sources', $successcount), 'success');
    }
    echo html_writer::end_tag('div');

    // Back link.
    echo $OUTPUT->single_button($pageurl, get_string('back'), 'get');
} else if ($courseid) {
    // Show confirmation.
    try {
        $course = get_course($courseid);
    } catch (\dml_missing_record_exception $e) {
        echo $OUTPUT->notification(get_string('coursenotfound', 'local_elediaai_sources'), 'error');
        echo html_writer::end_div();
        echo html_writer::end_div();
        echo $OUTPUT->footer();
        die();
    }

    echo $OUTPUT->heading($course->fullname, 3);

    $confirmurl = new moodle_url('/local/elediaai_sources/reindex.php', [
        'courseid' => $courseid,
        'confirm' => 1,
        'sesskey' => sesskey(),
    ]);
    $forceurl = new moodle_url($confirmurl, ['force' => 1]);
    echo $OUTPUT->single_button($confirmurl, get_string('reindex_btn', 'local_elediaai_sources'), 'post');
    // The plain reindex skips content the state records as unchanged; the
    // forced variant re-sends everything — the recovery path when the
    // destination lost data the state still believes to be there.
    echo $OUTPUT->single_button($forceurl, get_string('reindex_force_btn', 'local_elediaai_sources'), 'post');
    echo html_writer::tag('p', get_string('reindex_force_desc', 'local_elediaai_sources'), ['class' => 'text-muted']);
    echo $OUTPUT->single_button($pageurl, get_string('cancel'), 'get');
} else {
    // Show course selection form.
    echo html_writer::tag(
        'div',
        html_writer::tag('h2', get_string('reindex', 'local_elediaai_sources'), ['class' => 'rg-page-title']) .
        html_writer::tag('p', get_string('reindexintro', 'local_elediaai_sources'), ['class' => 'rg-page-intro']),
        ['class' => 'rg-page-head']
    );

    $pendingcount = \local_elediaai_sources\course_state::pending_ingestion_count();
    $partialcount = \local_elediaai_sources\course_state::partial_ingestion_count();
    if ($pendingcount > 0 || $partialcount > 0) {
        if ($pendingcount > 0) {
            $paneltitle = get_string('pendingindexingtitle', 'local_elediaai_sources');
            $panelbody = $partialcount > 0
                ? get_string('pendingandpartialcount', 'local_elediaai_sources', (object) [
                    'pending' => $pendingcount,
                    'partial' => $partialcount,
                ])
                : get_string('pendingindexingcount', 'local_elediaai_sources', $pendingcount);
        } else {
            $paneltitle = get_string('partialindexingtitle', 'local_elediaai_sources');
            $panelbody = get_string('partialindexingcount', 'local_elediaai_sources', $partialcount);
        }
        $queueurl = new moodle_url('/local/elediaai_sources/reindex.php', [
            'queuepending' => 1,
            'sesskey' => sesskey(),
        ]);
        echo html_writer::tag(
            'section',
            html_writer::tag(
                'div',
                html_writer::span(
                    shell::lucide_icon('upload'),
                    'rg-action-panel__icon'
                ) .
                html_writer::tag(
                    'div',
                    html_writer::tag(
                        'h3',
                        $paneltitle,
                        ['class' => 'rg-action-panel__title']
                    ) .
                    html_writer::tag(
                        'p',
                        $panelbody,
                        ['class' => 'rg-action-panel__body']
                    ),
                    ['class' => 'rg-action-panel__text']
                ),
                ['class' => 'rg-action-panel__main']
            ) .
            html_writer::tag(
                'form',
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'queuepending', 'value' => '1']) .
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
                html_writer::tag(
                    'button',
                    shell::lucide_icon('play') .
                    html_writer::span(get_string('indexreleasedcourses', 'local_elediaai_sources')),
                    ['type' => 'submit', 'class' => 'btn btn-primary rg-primary-action']
                ),
                ['method' => 'post', 'action' => $queueurl->out_omit_querystring(), 'class' => 'rg-action-panel__action']
            ),
            ['class' => 'rg-action-panel rg-action-panel--warning']
        );
    }

    echo html_writer::tag(
        'section',
        html_writer::tag('h3', get_string('manualreindex', 'local_elediaai_sources'), ['class' => 'rg-section-title']) .
        html_writer::tag('p', get_string('manualreindex_desc', 'local_elediaai_sources'), ['class' => 'rg-section-desc']) .
        html_writer::start_tag('form', [
            'method' => 'get',
            'action' => $pageurl->out_omit_querystring(),
            'class' => 'rg-inline-form',
        ]) .
        html_writer::tag('label', get_string('courseid', 'local_elediaai_sources'), [
            'for' => 'id_courseid',
            'class' => 'rg-inline-form__label',
        ]) .
        html_writer::empty_tag('input', [
            'type' => 'number',
            'name' => 'courseid',
            'id' => 'id_courseid',
            'class' => 'form-control rg-inline-form__input',
            'required' => 'required',
            'min' => '1',
        ]) .
        html_writer::tag(
            'button',
            shell::lucide_icon('rotate-cw') .
            html_writer::span(get_string('reindexcourse', 'local_elediaai_sources')),
            ['type' => 'submit', 'class' => 'btn btn-secondary rg-secondary-action']
        ) .
        html_writer::end_tag('form'),
        ['class' => 'rg-panel']
    );

    // The error report below names cmids, and so do the source ids in the
    // destination. Both are dead ends without a way to look one up directly:
    // an administrator diagnosing a gap is rarely enrolled in the course.
    $previewaction = (new moodle_url('/local/elediaai_sources/preview.php'))->out_omit_querystring();
    echo html_writer::tag(
        'section',
        html_writer::tag('h3', get_string('preview_lookup', 'local_elediaai_sources'), ['class' => 'rg-section-title']) .
        html_writer::tag('p', get_string('preview_lookup_desc', 'local_elediaai_sources'), ['class' => 'rg-section-desc']) .
        html_writer::start_tag('form', [
            'method' => 'get',
            'action' => $previewaction,
            'class' => 'rg-inline-form',
        ]) .
        html_writer::tag('label', get_string('preview_cmid', 'local_elediaai_sources'), [
            'for' => 'id_previewcmid',
            'class' => 'rg-inline-form__label',
        ]) .
        html_writer::empty_tag('input', [
            'type' => 'number',
            'name' => 'cmid',
            'id' => 'id_previewcmid',
            'class' => 'form-control rg-inline-form__input',
            'required' => 'required',
            'min' => '1',
        ]) .
        html_writer::tag(
            'button',
            shell::lucide_icon('search') .
            html_writer::span(get_string('preview_open', 'local_elediaai_sources')),
            ['type' => 'submit', 'class' => 'btn btn-secondary rg-secondary-action']
        ) .
        html_writer::end_tag('form'),
        ['class' => 'rg-panel']
    );

    $previousid = \local_elediaai_sources\sink\sink_manager::previous_id();
    if ($previousid !== '') {
        $previoussink = \local_elediaai_sources\sink\sink_manager::instance($previousid);
        $purgeurl = new moodle_url($pageurl, ['purgeoldsink' => 1, 'sesskey' => sesskey()]);
        echo html_writer::tag(
            'section',
            html_writer::tag(
                'h3',
                get_string('purgeoldsink', 'local_elediaai_sources'),
                ['class' => 'rg-section-title']
            ) .
            html_writer::tag(
                'p',
                get_string('purgeoldsink_desc', 'local_elediaai_sources', $previoussink::name()),
                ['class' => 'rg-section-desc']
            ) .
            $OUTPUT->single_button($purgeurl, get_string('purgeoldsink_btn', 'local_elediaai_sources'), 'post'),
            ['class' => 'rg-panel']
        );
    }

    // Recent per-activity failures. Without this the only trace of a failed
    // ingest is a line in the cron log, which nobody reads until something is
    // already missing from the tutor's answers.
    $errorrows = $DB->get_records(
        'local_elediaai_sources_cmstate',
        ['laststatus' => \local_elediaai_sources\cm_state::STATUS_ERROR],
        'timemodified DESC',
        'id, courseid, cmid, lasterror, timemodified',
        0,
        50
    );
    if (!empty($errorrows)) {
        $errortable = new html_table();
        $errortable->head = [
            get_string('course'),
            get_string('modulename', 'local_elediaai_sources'),
            get_string('details', 'local_elediaai_sources'),
            get_string('date'),
        ];
        $errortable->attributes['class'] = 'generaltable table-striped table-sm';

        foreach ($errorrows as $errorrow) {
            $coursename = $DB->get_field('course', 'fullname', ['id' => $errorrow->courseid]);
            $errortable->data[] = [
                $coursename !== false ? format_string($coursename) : (string) $errorrow->courseid,
                get_string('unknownmodule', 'local_elediaai_sources', $errorrow->cmid),
                s((string) $errorrow->lasterror),
                userdate((int) $errorrow->timemodified),
            ];
        }

        echo html_writer::tag(
            'section',
            html_writer::tag(
                'h3',
                get_string('recenterrors', 'local_elediaai_sources'),
                ['class' => 'rg-section-title']
            ) .
            html_writer::tag(
                'p',
                get_string('recenterrors_desc', 'local_elediaai_sources'),
                ['class' => 'rg-section-desc']
            ) .
            html_writer::table($errortable),
            ['class' => 'rg-panel']
        );
    }
}

echo html_writer::end_div();
if (shell::is_available()) {
    plugin_shell::content_close();
    plugin_page::close();
}
echo $OUTPUT->footer();

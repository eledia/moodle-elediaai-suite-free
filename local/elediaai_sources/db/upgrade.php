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
 * Upgrade steps for the AI Sources plugin.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute upgrade steps.
 *
 * The component was installed fresh rather than upgraded from local_ragingest:
 * it brings its own tables and nothing carries over. The predecessor's steps
 * were written against a different component name and could never run here.
 * An existing site keeps local_ragingest as a separate, then functionless
 * plugin and uninstalls it through the plugin overview.
 *
 * @param int $oldversion The currently installed version.
 * @return bool
 */
function xmldb_local_elediaai_sources_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026082303) {
        // Explicit per-activity ingestion decisions (see classes/activity_gate.php).
        $table = new xmldb_table('local_elediaai_sources_cm');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('included', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('cmid', XMLDB_KEY_UNIQUE, ['cmid']);
        $table->add_index('courseid', XMLDB_INDEX_NOTUNIQUE, ['courseid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026082303, 'local', 'elediaai_sources');
    }

    if ($oldversion < 2026082304) {
        // Site-local index truth per course module (see classes/cm_state.php).
        $table = new xmldb_table('local_elediaai_sources_cmstate');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('sink', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL);
        $table->add_field('sourceid', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        $table->add_field('contenthash', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL);
        $table->add_field('laststatus', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL);
        $table->add_field('lasterror', XMLDB_TYPE_TEXT);
        $table->add_field('timeingested', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('cmid', XMLDB_KEY_UNIQUE, ['cmid']);
        $table->add_index('courseid', XMLDB_INDEX_NOTUNIQUE, ['courseid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026082304, 'local', 'elediaai_sources');
    }

    if ($oldversion < 2026082305) {
        // Seed the mode shadow the updated-callback compares against — the
        // previous value is already overwritten by the time it runs.
        $mode = (string) get_config('local_elediaai_sources', 'activitydefault');
        set_config(
            'activitydefault_shadow',
            $mode === 'optin' ? 'optin' : 'optout',
            'local_elediaai_sources'
        );

        upgrade_plugin_savepoint(true, 2026082305, 'local', 'elediaai_sources');
    }

    if ($oldversion < 2026082400) {
        // Seed the destination shadow the switch-callback compares against;
        // without it the first switch after this upgrade could not name its
        // predecessor.
        set_config(
            'sink_shadow',
            \local_elediaai_sources\sink\sink_manager::active_id(),
            'local_elediaai_sources'
        );

        upgrade_plugin_savepoint(true, 2026082400, 'local', 'elediaai_sources');
    }

    if ($oldversion < 2026082505) {
        // Split the two questions the "ingested" flag used to answer at once:
        // whether anything is in the index (what the tutor asks) and whether
        // anything is still outstanding (what the reconcile asks).
        $coursetable = new xmldb_table('local_elediaai_sources_course');
        $pending = new xmldb_field('pending', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'ingested');
        if (!$dbman->field_exists($coursetable, $pending)) {
            $dbman->add_field($coursetable, $pending);
        }

        // Per-module retry budget, so one permanently failing document cannot
        // make every reconcile run repeat the same error.
        $cmtable = new xmldb_table('local_elediaai_sources_cmstate');
        $attempts = new xmldb_field('attempts', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'laststatus');
        if (!$dbman->field_exists($cmtable, $attempts)) {
            $dbman->add_field($cmtable, $attempts);
        }

        // Existing rows that already record a failure start with one attempt
        // spent rather than a fresh budget — the failure did happen.
        $DB->set_field_select(
            'local_elediaai_sources_cmstate',
            'attempts',
            1,
            'laststatus = :status AND attempts = 0',
            ['status' => \local_elediaai_sources\cm_state::STATUS_ERROR]
        );

        upgrade_plugin_savepoint(true, 2026082505, 'local', 'elediaai_sources');
    }

    if ($oldversion < 2026101402) {
        // The feedback extractor is withdrawn (#14, operator decision): course
        // evaluations are not a curated knowledge source, and one person's
        // rating is noise. Its directory is gone, so it has no upgrade step of
        // its own left to run; it kept nothing but its version row, and that
        // row would leave it listed as "missing from disk".
        $DB->delete_records('config_plugins', ['plugin' => 'aisourcesextractor_feedback']);

        // What it sent must leave the index as well. Queued rather than sent
        // here: an upgrade must not wait for, or fail on, the destination.
        $cms = $DB->get_records_sql(
            "SELECT cm.id, cm.course
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module AND m.name = :modname
               JOIN {local_elediaai_sources_cmstate} st ON st.cmid = cm.id
              WHERE st.laststatus <> :empty",
            ['modname' => 'feedback', 'empty' => \local_elediaai_sources\cm_state::STATUS_EMPTY]
        );
        foreach ($cms as $cm) {
            $task = new \local_elediaai_sources\task\delete_module_task();
            $task->set_custom_data(['courseid' => (int) $cm->course, 'cmid' => (int) $cm->id]);
            \core\task\manager::queue_adhoc_task($task, true);
        }

        upgrade_plugin_savepoint(true, 2026101402, 'local', 'elediaai_sources');
    }

    if ($oldversion < 2026101500) {
        // Wikis and databases sent content not every learner may read (#100);
        // quizzes got valid HTML (#14). The reconcile only revisits courses
        // whose state diverges, so an indexed module would keep its old
        // document. Each one with index state is queued once; unchanged
        // content is skipped by its hash, changed content replaces the old
        // document, and content that is now empty removes it.
        $cms = $DB->get_records_sql(
            "SELECT cm.id, cm.course
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module
               JOIN {local_elediaai_sources_cmstate} st ON st.cmid = cm.id
              WHERE m.name IN ('wiki', 'data', 'quiz')
                AND st.laststatus <> :empty",
            ['empty' => \local_elediaai_sources\cm_state::STATUS_EMPTY]
        );
        foreach ($cms as $cm) {
            $task = new \local_elediaai_sources\task\ingest_module_task();
            $task->set_custom_data(['courseid' => (int) $cm->course, 'cmid' => (int) $cm->id]);
            \core\task\manager::queue_adhoc_task($task, true);
        }

        upgrade_plugin_savepoint(true, 2026101500, 'local', 'elediaai_sources');
    }

    if ($oldversion < 2026101501) {
        // Interactive Books now yield their chapter titles and no longer their
        // interface labels (#92). Indexed H5P activities are sent once more;
        // the reconcile alone would not revisit them (see 2026101500).
        $cms = $DB->get_records_sql(
            "SELECT cm.id, cm.course
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module AND m.name = :modname
               JOIN {local_elediaai_sources_cmstate} st ON st.cmid = cm.id
              WHERE st.laststatus <> :empty",
            ['modname' => 'h5pactivity', 'empty' => \local_elediaai_sources\cm_state::STATUS_EMPTY]
        );
        foreach ($cms as $cm) {
            $task = new \local_elediaai_sources\task\ingest_module_task();
            $task->set_custom_data(['courseid' => (int) $cm->course, 'cmid' => (int) $cm->id]);
            \core\task\manager::queue_adhoc_task($task, true);
        }

        upgrade_plugin_savepoint(true, 2026101501, 'local', 'elediaai_sources');
    }

    if ($oldversion < 2026101502) {
        // Assignments now send the files attached to their description and
        // become a document set: the description as one sub-document, each file
        // as another (#90). Indexed assignments are sent once more, so the old
        // single document is replaced (the multi-document path clears the
        // module's prefix first).
        $cms = $DB->get_records_sql(
            "SELECT cm.id, cm.course
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module AND m.name = :modname
               JOIN {local_elediaai_sources_cmstate} st ON st.cmid = cm.id
              WHERE st.laststatus <> :empty",
            ['modname' => 'assign', 'empty' => \local_elediaai_sources\cm_state::STATUS_EMPTY]
        );
        foreach ($cms as $cm) {
            $task = new \local_elediaai_sources\task\ingest_module_task();
            $task->set_custom_data(['courseid' => (int) $cm->course, 'cmid' => (int) $cm->id]);
            \core\task\manager::queue_adhoc_task($task, true);
        }

        upgrade_plugin_savepoint(true, 2026101502, 'local', 'elediaai_sources');
    }

    if ($oldversion < 2026101503) {
        // Assignments are no longer indexed (#103, operator decision
        // 15.10.2026): the tutor reads them live through the Moodle tools,
        // which act with the learner's rights. An assignment's description can
        // be hidden until submissions open, its rubric can be hidden before
        // grading -- rules the index would have to rebuild and the live path
        // gets from Moodle. The extractor's directory is gone; its version row
        // would leave it "missing from disk".
        $DB->delete_records('config_plugins', ['plugin' => 'aisourcesextractor_assign']);

        // What was sent leaves the index. Queued, as for the feedback
        // extractor: an upgrade must not wait for the destination.
        $cms = $DB->get_records_sql(
            "SELECT cm.id, cm.course
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module AND m.name = :modname
               JOIN {local_elediaai_sources_cmstate} st ON st.cmid = cm.id
              WHERE st.laststatus <> :empty",
            ['modname' => 'assign', 'empty' => \local_elediaai_sources\cm_state::STATUS_EMPTY]
        );
        foreach ($cms as $cm) {
            $task = new \local_elediaai_sources\task\delete_module_task();
            $task->set_custom_data(['courseid' => (int) $cm->course, 'cmid' => (int) $cm->id]);
            \core\task\manager::queue_adhoc_task($task, true);
        }

        upgrade_plugin_savepoint(true, 2026101503, 'local', 'elediaai_sources');
    }

    if ($oldversion < 2026112401) {
        // H5P keeps its questions but no longer marks the correct answer, and
        // drops hints, feedback and the marked words of blanks
        // (ingestionpipeline#23). Indexed H5P activities are sent once more, as
        // in 2026101501; so are pages, labels and books whose stored HTML
        // embeds an H5P placeholder, which h5p_embed_helper resolves through
        // the same extractor. Other modules with an embedded H5P pick the
        // change up with their next edit or a course reindex. Quizzes and
        // graded lessons are queued by their own extractors' upgrade steps.
        $params = ['empty' => \local_elediaai_sources\cm_state::STATUS_EMPTY];
        $placeholder = '%h5p-placeholder%';
        $cms = $DB->get_records_sql(
            "SELECT cm.id, cm.course
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module
               JOIN {local_elediaai_sources_cmstate} st ON st.cmid = cm.id
              WHERE st.laststatus <> :empty
                AND (m.name = :h5pactivity
                     OR (m.name = :page AND EXISTS (
                         SELECT 1 FROM {page} p
                          WHERE p.id = cm.instance AND " . $DB->sql_like('p.content', ':p1') . "))
                     OR (m.name = :label AND EXISTS (
                         SELECT 1 FROM {label} l
                          WHERE l.id = cm.instance AND " . $DB->sql_like('l.intro', ':p2') . "))
                     OR (m.name = :book AND EXISTS (
                         SELECT 1 FROM {book_chapters} bc
                          WHERE bc.bookid = cm.instance AND " . $DB->sql_like('bc.content', ':p3') . ")))",
            $params + [
                'h5pactivity' => 'h5pactivity',
                'page' => 'page',
                'label' => 'label',
                'book' => 'book',
                'p1' => $placeholder,
                'p2' => $placeholder,
                'p3' => $placeholder,
            ]
        );
        foreach ($cms as $cm) {
            $task = new \local_elediaai_sources\task\ingest_module_task();
            $task->set_custom_data(['courseid' => (int) $cm->course, 'cmid' => (int) $cm->id]);
            \core\task\manager::queue_adhoc_task($task, true);
        }

        upgrade_plugin_savepoint(true, 2026112401, 'local', 'elediaai_sources');
    }

    if ($oldversion < 2026112402) {
        // Per sent document of a module, as an asynchronous destination reports
        // it (ai_suite#232): the pipeline's 202 only means "job stored", so the
        // job id and the status read back later need a place of their own. One
        // row per (sub-)document, because a Folder sends several and a module
        // only counts as indexed once all of them are.
        //
        // No data migration: existing success rows stay success and get no
        // document rows, so the status check never touches them (no backfill).
        $table = new xmldb_table('local_elediaai_sources_docstate');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('sourceid', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        $table->add_field('ordinal', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('jobid', XMLDB_TYPE_CHAR, '64');
        $table->add_field('remotestatus', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL);
        $table->add_field('errortype', XMLDB_TYPE_CHAR, '100');
        $table->add_field('errormessage', XMLDB_TYPE_TEXT);
        $table->add_field('attemptcount', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('maxattempts', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('nextretryat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('statuscheckedat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timelastcheck', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('nextcheck', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('checkfailures', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('checkerror', XMLDB_TYPE_CHAR, '255');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('sourceid', XMLDB_INDEX_UNIQUE, ['sourceid']);
        $table->add_index('cmid', XMLDB_INDEX_NOTUNIQUE, ['cmid']);
        $table->add_index('courseid-nextcheck', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'nextcheck']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026112402, 'local', 'elediaai_sources');
    }

    if ($oldversion < 2026112500) {
        // The Ingestion API key is stored encrypted with the site key from now
        // on (ai_suite#252). Re-save an existing plaintext key as ciphertext;
        // empty and already encrypted values are left as they are.
        \local_elediaai_sources\setup::encrypt_stored_apikey();

        upgrade_plugin_savepoint(true, 2026112500, 'local', 'elediaai_sources');
    }

    if ($oldversion < 2026112501) {
        // Deletes the destination has not carried out yet (ai_suite#242, #243):
        // a 409 while a job is in flight, a 5xx or a network error used to be
        // logged once and forgotten, so documents of deleted activities,
        // deleted courses and removed folder files stayed in the index.
        // No data migration: deletes that failed before this step are not
        // known to the plugin any more.
        $table = new xmldb_table('local_elediaai_sources_delq');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('sourceid', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        $table->add_field('scope', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL);
        $table->add_field('sink', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL);
        $table->add_field('tenant', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('status', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL);
        $table->add_field('attempts', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('nextattempt', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lasthttpcode', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lasterror', XMLDB_TYPE_TEXT);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('sourceid-scope', XMLDB_INDEX_UNIQUE, ['sourceid', 'scope']);
        $table->add_index('status-nextattempt', XMLDB_INDEX_NOTUNIQUE, ['status', 'nextattempt']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026112501, 'local', 'elediaai_sources');
    }

    if ($oldversion < 2026112600) {
        // The course field "AI Sources" showed English options and an outdated
        // German description (ai_suite#262). Rewrite name, description and the
        // option labels in the site language; the course form shows them in
        // each user's language. Courses store the option position, which stays
        // as it is, so no course changes its marking. Options an administrator
        // edited are left alone. No-op when the field does not exist.
        \local_elediaai_sources\setup::update_course_field_labels();

        upgrade_plugin_savepoint(true, 2026112600, 'local', 'elediaai_sources');
    }

    return true;
}

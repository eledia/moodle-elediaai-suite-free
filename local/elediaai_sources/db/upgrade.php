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

    return true;
}

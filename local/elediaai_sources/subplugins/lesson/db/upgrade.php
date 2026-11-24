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
 * Upgrade steps for the lesson content extractor.
 *
 * @package    aisourcesextractor_lesson
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute upgrade steps.
 *
 * @param int $oldversion The currently installed version.
 * @return bool
 */
function xmldb_aisourcesextractor_lesson_upgrade(int $oldversion): bool {
    global $DB;

    if ($oldversion < 2026112400) {
        // Graded lessons no longer send the answers and responses of their
        // question pages (ingestionpipeline#23). Each graded lesson with index
        // state is queued once so the new content replaces the old document;
        // practice and ungraded lessons are unchanged and left alone. Same
        // condition as extractor::is_graded().
        $cms = $DB->get_records_sql(
            "SELECT cm.id, cm.course
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module AND m.name = :modname
               JOIN {lesson} l ON l.id = cm.instance
               JOIN {local_elediaai_sources_cmstate} st ON st.cmid = cm.id
              WHERE st.laststatus <> :empty
                AND l.practice = 0
                AND l.grade <> 0",
            ['modname' => 'lesson', 'empty' => \local_elediaai_sources\cm_state::STATUS_EMPTY]
        );
        foreach ($cms as $cm) {
            $task = new \local_elediaai_sources\task\ingest_module_task();
            $task->set_custom_data(['courseid' => (int) $cm->course, 'cmid' => (int) $cm->id]);
            \core\task\manager::queue_adhoc_task($task, true);
        }

        upgrade_plugin_savepoint(true, 2026112400, 'aisourcesextractor', 'lesson');
    }

    return true;
}

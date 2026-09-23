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

namespace local_elediaai_sources\task;

use local_elediaai_sources\activity_gate;
use local_elediaai_sources\course_gate;

/**
 * Queue ingestion for every undecided activity of released courses.
 *
 * Runs once after the site default flips from opt-in to opt-out: activities
 * nobody decided on were merely skipped until now and should enter the index
 * under the new default. The fan-out is deliberately dumb — it only queues the
 * regular per-module ingest task, which re-checks every gate, visibility and
 * the unchanged-skip at run time. Queued as ONE ad-hoc task so the settings
 * request that triggers it stays fast.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class converge_undecided_task extends \core\task\adhoc_task {
    /**
     * Get the task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_converge_undecided', 'local_elediaai_sources');
    }

    /**
     * Fan out per-module ingest tasks.
     */
    public function execute(): void {
        global $DB;

        $queued = 0;
        $courses = $DB->get_recordset('course', null, 'id', 'id');
        foreach ($courses as $course) {
            $courseid = (int) $course->id;
            if (!course_gate::should_ingest($courseid)) {
                continue;
            }

            $decisions = activity_gate::decisions($courseid);
            foreach (get_fast_modinfo($courseid)->get_cms() as $cm) {
                if ($cm->deletioninprogress || array_key_exists((int) $cm->id, $decisions)) {
                    continue;
                }
                $task = new ingest_module_task();
                $task->set_custom_data([
                    'courseid' => $courseid,
                    'cmid' => (int) $cm->id,
                ]);
                \core\task\manager::queue_adhoc_task($task, true);
                $queued++;
            }
        }
        $courses->close();

        mtrace("  [local_elediaai_sources] Queued {$queued} undecided module(s) after mode switch.");
    }
}

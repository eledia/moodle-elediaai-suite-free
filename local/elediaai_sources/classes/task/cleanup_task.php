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
use local_elediaai_sources\cm_state;
use local_elediaai_sources\ingestion_manager;
use local_elediaai_sources\sink\sink_manager;

/**
 * Weekly convergence sweep for the blind spots events cannot cover.
 *
 * Two of those exist by construction: hiding a module fires no Moodle event
 * (verified in 4.5 and 5.2 — cmactions::set_visibility triggers nothing), and
 * state or decision rows can be orphaned when an event was missed. This task
 * makes both converge:
 *
 * - state rows whose module is gone: the index is cleaned via the STORED
 *   source id (which froze the tenant of the ingest moment), then the row is
 *   dropped. Rows pointing at a destination other than the active one are
 *   dropped without a delete — the old destination is deliberately never
 *   touched automatically (same principle as a destination switch).
 * - state rows whose module is hidden from learners: removed from the index,
 *   row dropped. What learners cannot see, the tutor must not cite.
 * - decision rows whose module is gone: dropped (no HTTP involved).
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_task extends \core\task\scheduled_task {
    /**
     * Get the task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_cleanup', 'local_elediaai_sources');
    }

    /**
     * Execute the sweep.
     */
    public function execute(): void {
        global $DB;

        $sink = sink_manager::active();
        $activeid = $sink::id();
        $candelete = $sink->is_configured();

        $orphanstates = 0;
        $hiddenstates = 0;

        $rows = $DB->get_records('local_elediaai_sources_cmstate');
        foreach ($rows as $row) {
            $cmid = (int) $row->cmid;

            if (!$DB->record_exists('course_modules', ['id' => $cmid])) {
                // Module gone. Clean the active destination via the stored id;
                // stale rows for other destinations are dropped untouched.
                if ((string) $row->sink === $activeid && $candelete) {
                    $result = $sink->delete((string) $row->sourceid, 'prefix');
                    if (empty($result['success'])) {
                        continue;
                    }
                }
                cm_state::forget($cmid);
                $orphanstates++;
                continue;
            }

            try {
                $cm = get_fast_modinfo((int) $row->courseid)->get_cm($cmid);
            } catch (\Exception $e) {
                continue;
            }

            $hidden = !ingestion_manager::visible_to_learners($cm);
            if ($hidden && (string) $row->sink === $activeid && $candelete) {
                $result = $sink->delete((string) $row->sourceid, 'prefix');
                if (!empty($result['success'])) {
                    cm_state::forget($cmid);
                    $hiddenstates++;
                }
            }
        }

        // Decision rows of deleted modules: no HTTP involved, plain cleanup.
        $orphandecisions = $DB->count_records_select(
            'local_elediaai_sources_cm',
            'cmid NOT IN (SELECT id FROM {course_modules})'
        );
        if ($orphandecisions > 0) {
            $DB->delete_records_select(
                'local_elediaai_sources_cm',
                'cmid NOT IN (SELECT id FROM {course_modules})'
            );
        }

        mtrace("  [local_elediaai_sources] Cleanup: {$orphanstates} orphaned state row(s), "
            . "{$hiddenstates} hidden module(s) removed from the index, "
            . "{$orphandecisions} orphaned decision row(s).");
    }
}

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

use local_elediaai_sources\course_state;
use local_elediaai_sources\ingestion_manager;

/**
 * Ad-hoc task to ingest a single course module into the RAG index.
 *
 * Queued by the event observer when a course module is created or updated.
 * Runs asynchronously via Moodle cron.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ingest_module_task extends \core\task\adhoc_task {
    /**
     * Get the task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskingestion', 'local_elediaai_sources');
    }

    /**
     * Execute the ingestion task.
     *
     * Retrieves the course module and sends its content to the RAG API.
     * Errors are logged but never thrown to prevent cron disruption.
     */
    public function execute(): void {
        $data = $this->get_custom_data();

        if (empty($data->courseid) || empty($data->cmid)) {
            mtrace('  [local_elediaai_sources] Ingest task: missing courseid or cmid, skipping.');
            return;
        }

        mtrace("  [local_elediaai_sources] Ingesting cmid {$data->cmid} in course {$data->courseid}...");

        try {
            $manager = new ingestion_manager();
            $result = $manager->ingest_module((int) $data->courseid, (int) $data->cmid);

            // The module path writes module state only; carry the course flag
            // along, or a course reconciled while empty never counts as
            // ingested and the tutor ignores its content (AI-80).
            if (course_state::sync_from_index((int) $data->courseid)) {
                mtrace("  [local_elediaai_sources] Course {$data->courseid} now counts as ingested.");
            }

            if ($result['success']) {
                mtrace("  [local_elediaai_sources] Ingestion succeeded for cmid {$data->cmid}.");
            } else {
                mtrace("  [local_elediaai_sources] Ingestion result for cmid {$data->cmid}: "
                    . "{$result['status']} — {$result['message']}");
            }
        } catch (\Exception $e) {
            mtrace("  [local_elediaai_sources] Ingestion error for cmid {$data->cmid}: " . $e->getMessage());
        }
    }
}

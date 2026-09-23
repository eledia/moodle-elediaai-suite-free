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
use local_elediaai_sources\sink\sink_manager;

/**
 * Scheduled task: reconcile every course's ingestion state with its marking.
 *
 * Catches changes that don't fire a per-course event — most importantly edits
 * to the category allow-list, which can flip eligibility for many courses at
 * once — by queueing a per-course reconcile only where state and marking
 * actually differ.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reconcile_all_task extends \core\task\scheduled_task {
    /**
     * Task name shown in the scheduled-tasks UI.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_reconcile_all', 'local_elediaai_sources');
    }

    /**
     * Queue a reconcile for every course whose marking and state diverge.
     *
     * @return void
     */
    public function execute(): void {
        if (!sink_manager::active()->is_configured()) {
            mtrace('local_elediaai_sources: no ingestion destination configured — skipping reconcile.');
            return;
        }

        // Courses whose flag lags behind their indexed modules (AI-80).
        $repaired = course_state::repair_ingested_flags();
        if ($repaired > 0) {
            mtrace("local_elediaai_sources: repaired the ingested flag of {$repaired} course(s).");
        }

        $queued = course_state::queue_divergent_reconciles();
        mtrace("local_elediaai_sources: queued {$queued} course reconcile task(s).");
    }
}

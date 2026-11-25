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

use local_elediaai_sources\delete_queue;
use local_elediaai_sources\sink\sink_manager;

/**
 * Retries deletes the destination has not carried out yet, every five minutes.
 *
 * Works through {@see delete_queue}: due entries are sent again, a success
 * removes the entry, a transient failure schedules the next attempt with a
 * growing pause, anything else gives the entry up and leaves it for the
 * report (ai_suite#242, #243). With nothing due, a run is a single query.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_queue_task extends \core\task\scheduled_task {
    /**
     * Get the task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_delete_queue', 'local_elediaai_sources');
    }

    /**
     * Retry every delete whose next attempt is due.
     */
    public function execute(): void {
        $summary = delete_queue::process_due(sink_manager::active());

        if (!$summary['configured']) {
            mtrace('  [local_elediaai_sources] Delete queue: the destination is not configured, nothing attempted.');
            return;
        }
        if ($summary['due'] === 0) {
            mtrace('  [local_elediaai_sources] Delete queue: nothing due.');
            return;
        }
        mtrace("  [local_elediaai_sources] Delete queue: {$summary['due']} due, {$summary['deleted']} deleted, "
            . "{$summary['retry']} to retry, {$summary['failed']} given up, {$summary['dropped']} dropped.");
    }
}

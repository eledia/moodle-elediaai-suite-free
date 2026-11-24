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

use local_elediaai_sources\status_sync;

/**
 * Confirms accepted documents with the destination, every five minutes.
 *
 * Separate from the nightly reconcile on purpose (D-b): the course flags the
 * tutor reads on every load, the automatic re-send of retryable failures, the
 * status report and the 24-hour timeout must not depend on someone opening a
 * course page. Only documents in flight are asked about; with none, the run
 * is a single query.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class status_sync_task extends \core\task\scheduled_task {
    /**
     * Get the task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_status_sync', 'local_elediaai_sources');
    }

    /**
     * Check every course whose next status check is due.
     */
    public function execute(): void {
        $summary = (new status_sync())->run_due();

        if (!$summary['applicable']) {
            mtrace('  [local_elediaai_sources] Status check: the destination reports no processing status.');
            return;
        }
        if ($summary['nothinginflight']) {
            mtrace('  [local_elediaai_sources] Status check: nothing in flight.');
            return;
        }
        mtrace("  [local_elediaai_sources] Status check: {$summary['courses']} course(s), "
            . "{$summary['requests']} request(s), {$summary['checked']} document(s), "
            . "{$summary['changed']} activity state(s) changed, {$summary['unavailable']} course(s) unavailable.");
    }
}

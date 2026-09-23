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

use local_elediaai_sources\ingestion_manager;
use local_elediaai_sources\sink\sink_manager;

/**
 * Clear one course's documents from a destination the site left behind.
 *
 * The only operation that deliberately talks to something other than the
 * active destination. It carries the sink id in its own data rather than
 * reading the config at run time: by then the site may have switched again,
 * and this task must still address the destination it was queued for.
 *
 * Clearing stays an explicit admin action, not an automatic consequence of
 * switching — at switch time the old backend is often unreachable, and a
 * failing purge must never block the switch itself.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class purge_old_sink_task extends \core\task\adhoc_task {
    /**
     * Get the task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_purge_old_sink', 'local_elediaai_sources');
    }

    /**
     * Purge the course from the recorded destination.
     */
    public function execute(): void {
        $data = $this->get_custom_data();

        if (empty($data->courseid) || empty($data->sinkid)) {
            mtrace('  [local_elediaai_sources] Purge task: missing courseid or sinkid, skipping.');
            return;
        }

        $sink = sink_manager::instance((string) $data->sinkid);
        if ($sink === null) {
            mtrace("  [local_elediaai_sources] Purge task: unknown destination {$data->sinkid}, skipping.");
            return;
        }
        if (!$sink->is_configured()) {
            // Its settings may have been cleared meanwhile. Failing loudly is
            // better than reporting a purge that never reached anything.
            throw new \moodle_exception('apinotconfigured', 'local_elediaai_sources');
        }

        mtrace("  [local_elediaai_sources] Purging course {$data->courseid} from {$data->sinkid}...");

        $manager = new ingestion_manager($sink);
        $results = $manager->purge_course((int) $data->courseid);

        $failed = 0;
        foreach ($results as $result) {
            if (($result['status'] ?? '') === 'error') {
                $failed++;
            }
        }

        if ($failed > 0) {
            // Let the task API retry: a partly cleared destination is exactly
            // what a retry should finish.
            throw new \moodle_exception(
                'purgeoldsinkfailed',
                'local_elediaai_sources',
                '',
                (object) ['course' => $data->courseid, 'failed' => $failed]
            );
        }

        mtrace("  [local_elediaai_sources] Course {$data->courseid} cleared from {$data->sinkid}.");
    }
}

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

/**
 * Ad-hoc task: reconcile one course's ingestion state with its marking.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reconcile_course_task extends \core\task\adhoc_task {
    /**
     * Run the reconciliation for the queued course.
     *
     * @return void
     */
    public function execute(): void {
        $data = $this->get_custom_data();
        $courseid = (int) ($data->courseid ?? 0);
        if ($courseid <= 0) {
            return;
        }
        try {
            $action = course_state::reconcile($courseid);
            mtrace("local_elediaai_sources: reconciled course {$courseid} -> {$action}");
        } catch (\Throwable $e) {
            mtrace("local_elediaai_sources: reconcile error for course {$courseid}: " . $e->getMessage());
            throw $e;
        }
    }
}

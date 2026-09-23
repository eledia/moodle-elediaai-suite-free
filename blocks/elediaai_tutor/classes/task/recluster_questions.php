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

declare(strict_types=1);

namespace block_elediaai_tutor\task;

use block_elediaai_tutor\local\recluster_service;
use core\task\scheduled_task;

/**
 * Nightly task that converges question-analytics topic labels via the RAG server.
 *
 * No-op unless question analytics is enabled AND a recluster tool name is
 * configured.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recluster_questions extends scheduled_task {
    /**
     * Return the task name shown in the scheduled tasks UI.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_recluster_questions', 'block_elediaai_tutor');
    }

    /**
     * Run the reclustering.
     *
     * @return void
     */
    public function execute(): void {
        if (!recluster_service::is_configured()) {
            mtrace('block_elediaai_tutor: reclustering is not configured (analytics off or no tool name).');
            return;
        }

        $stats = recluster_service::run();
        mtrace(sprintf(
            'block_elediaai_tutor: reclustered %d course(s), %d batch(es), %d topic(s) updated, %d failure(s).',
            $stats['courses'],
            $stats['batches'],
            $stats['updated'],
            $stats['failed']
        ));
    }
}

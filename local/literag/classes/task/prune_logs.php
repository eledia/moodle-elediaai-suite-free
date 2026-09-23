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

namespace local_literag\task;

use local_literag\local\config;

/**
 * Prune old query-log rows and expired conversations per the configured retention.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class prune_logs extends \core\task\scheduled_task {
    /** @var int Number of expired conversations to delete per batch. */
    private const CONVERSATION_BATCH_SIZE = 500;

    /**
     * Return the human-readable task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_prune_logs', 'local_literag');
    }

    /**
     * Execute the task.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;

        $now = time();

        $querydays = config::query_log_retention_days();
        if ($querydays > 0) {
            $cutoff = $now - ($querydays * DAYSECS);
            $DB->delete_records_select('local_literag_query_log', 'timecreated < ?', [$cutoff]);
        }

        $convdays = config::conversation_retention_days();
        if ($convdays > 0) {
            $cutoff = $now - ($convdays * DAYSECS);
            do {
                $oldrecords = $DB->get_records_select(
                    'local_literag_conversations',
                    'timemodified < ?',
                    [$cutoff],
                    'id ASC',
                    'id',
                    0,
                    self::CONVERSATION_BATCH_SIZE
                );
                $oldids = array_keys($oldrecords);
                if (empty($oldids)) {
                    break;
                }
                [$insql, $params] = $DB->get_in_or_equal($oldids);
                $DB->delete_records_select('local_literag_messages', "conversationid $insql", $params);
                $DB->delete_records_select('local_literag_conversations', "id $insql", $params);
            } while (count($oldids) === self::CONVERSATION_BATCH_SIZE);
        }
    }
}

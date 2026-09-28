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

namespace local_elediaai_chatengine\task;

use local_elediaai_chatengine\local\connection;
use local_elediaai_chatengine\local\thread_store;
use local_elediaai_chatengine\local\usage;

/**
 * Deletes conversations that have passed the configured retention.
 *
 * Retention is off by default: a conversation a learner may want to come back
 * to should not disappear because nobody made a decision. Once a site sets a
 * period, this is the one place that applies it, for every placement at once —
 * which is the point of a shared store.
 *
 * The same run also drops daily message counters older than
 * {@see usage::RETENTION_DAYS}. That retention is fixed, not configurable, and
 * applies even when conversation retention is off: a counter is only needed
 * to enforce today's limit and to look back a short while, and a per-person,
 * per-day row must not grow into a history of when somebody worked.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class purge_threads extends \core\task\scheduled_task {
    #[\Override]
    public function get_name(): string {
        return get_string('task_purge_threads', 'local_elediaai_chatengine');
    }

    #[\Override]
    public function execute(): void {
        $pruned = usage::prune();
        if ($pruned > 0) {
            mtrace('local_elediaai_chatengine: deleted ' . $pruned . ' expired daily message counters.');
        }

        $this->delete_expired_threads();
    }

    /**
     * Delete conversations older than the configured retention, if one is set.
     *
     * @return void
     */
    private function delete_expired_threads(): void {
        global $DB;

        $days = connection::retention_days();
        if ($days <= 0) {
            return;
        }

        $cutoff = time() - ($days * DAYSECS);
        $ids = $DB->get_fieldset_select(
            thread_store::THREAD_TABLE,
            'id',
            'timemodified < :cutoff',
            ['cutoff' => $cutoff]
        );
        if ($ids === []) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'tid');
        $DB->delete_records_select(thread_store::MESSAGE_TABLE, "threadid $insql", $params);
        $DB->delete_records_select(thread_store::THREAD_TABLE, "id $insql", $params);

        mtrace('local_elediaai_chatengine: deleted ' . count($ids) . ' expired conversations.');
    }
}

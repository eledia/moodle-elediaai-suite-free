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

/**
 * Retention task for local_aitransparency.
 *
 * @package    local_aitransparency
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aitransparency\task;

/**
 * Anonymises the triggering user on old provenance records.
 *
 * Provenance records are compliance evidence and must survive deletion of the
 * generated content. Only the personal link (userid) is removed once the
 * configured retention window has passed: the record is kept, userid is set to
 * 0. The record itself carries no other personal data.
 */
final class anonymise_records extends \core\task\scheduled_task {
    /**
     * Task name shown in the scheduled tasks report.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_anonymise_records', 'local_aitransparency');
    }

    /**
     * Anonymise userid on records older than the retention window.
     */
    public function execute(): void {
        global $DB;

        $days = (int) get_config('local_aitransparency', 'retentiondays');
        if ($days <= 0) {
            // Retention disabled: keep the personal link indefinitely.
            return;
        }

        $cutoff = time() - ($days * DAYSECS);
        $count = $DB->count_records_select(
            'local_aitransparency_rec',
            'userid <> 0 AND timecreated < :cutoff',
            ['cutoff' => $cutoff]
        );
        if ($count === 0) {
            return;
        }

        $DB->set_field_select(
            'local_aitransparency_rec',
            'userid',
            0,
            'userid <> 0 AND timecreated < :cutoff',
            ['cutoff' => $cutoff]
        );

        mtrace("local_aitransparency: anonymised userid on {$count} provenance record(s) older than {$days} day(s).");
    }
}

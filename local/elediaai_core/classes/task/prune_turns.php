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
 * Retention task for the anonymous turn log.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_elediaai_core\task;

use local_elediaai_core\local\insights;

/**
 * Deletes turns older than the configured retention.
 *
 * The turn log holds free text, so it gets the shortest of the suite's three
 * retentions and a real delete -- not the anonymisation the other two use.
 * There is nothing to anonymise: the row never held a name.
 */
final class prune_turns extends \core\task\scheduled_task {
    /**
     * Task name shown in the scheduled tasks report.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_prune_turns', 'local_elediaai_core');
    }

    /**
     * Delete expired turns.
     *
     * @return void
     */
    public function execute(): void {
        $days = insights::retention_days();
        if ($days <= 0) {
            mtrace('local_elediaai_core: Aufbewahrung der Turns unbegrenzt, nichts zu tun.');
            return;
        }

        $removed = insights::prune();
        mtrace("local_elediaai_core: {$removed} Turn(s) aelter als {$days} Tag(e) entfernt.");
    }
}

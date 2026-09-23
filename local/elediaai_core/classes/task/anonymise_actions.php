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
 * Retention task for the AI action log.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_elediaai_core\task;

use local_elediaai_core\local\actions;

/**
 * Removes the personal link from old actions, and keeps the row.
 *
 * The action log is the oversight record: a proof that disappears on request
 * proves nothing. So this task anonymises where the other two delete -- the same
 * mechanic local_aitransparency uses for provenance records, and for the same
 * reason.
 */
final class anonymise_actions extends \core\task\scheduled_task {
    /**
     * Task name shown in the scheduled tasks report.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_anonymise_actions', 'local_elediaai_core');
    }

    /**
     * Anonymise expired actions.
     *
     * @return void
     */
    public function execute(): void {
        $days = actions::retention_days();
        if ($days <= 0) {
            mtrace('local_elediaai_core: Personenbezug der Handlungen unbegrenzt, nichts zu tun.');
            return;
        }

        $count = actions::anonymise();
        mtrace("local_elediaai_core: Personenbezug bei {$count} Handlung(en) aelter als {$days} Tag(e) entfernt.");
    }
}

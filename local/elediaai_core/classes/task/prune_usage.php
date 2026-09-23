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
 * Retention task for the credit ledger.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_elediaai_core\task;

use local_elediaai_core\local\quota_manager;

/**
 * Deletes credit-ledger rows past their window.
 *
 * `quota_manager::prune()` existed from the start and had no caller: the plugin
 * carried no tasks.php at all, so the ledger grew without limit. It is also the
 * source of the „welches Feature wird benutzt" view, which is why the retention
 * is configurable rather than fixed -- a shorter window silently shortens that
 * report.
 */
final class prune_usage extends \core\task\scheduled_task {
    /**
     * Task name shown in the scheduled tasks report.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_prune_usage', 'local_elediaai_core');
    }

    /**
     * Delete expired ledger rows.
     *
     * @return void
     */
    public function execute(): void {
        $days = quota_manager::retention_days();
        if ($days <= 0) {
            mtrace('local_elediaai_core: Aufbewahrung des Hauptbuchs unbegrenzt, nichts zu tun.');
            return;
        }

        $removed = quota_manager::prune($days);
        mtrace("local_elediaai_core: {$removed} Hauptbuchzeile(n) aelter als {$days} Tag(e) entfernt.");
    }
}

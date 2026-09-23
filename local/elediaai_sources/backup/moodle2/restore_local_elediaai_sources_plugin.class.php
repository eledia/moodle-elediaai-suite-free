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
 * Restore support for the per-activity ingestion decision.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Restores the decision onto the newly created activity.
 *
 * The restored row records no author: `usermodified` is left at 0 rather than
 * carrying a user id that may belong to a different person on this site. What
 * matters for behaviour is the decision itself.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_local_elediaai_sources_plugin extends restore_local_plugin {
    /**
     * Declare the path this plugin restores from.
     *
     * @return restore_path_element[]
     */
    protected function define_module_plugin_structure() {
        return [
            new restore_path_element(
                'aisourcesdecision',
                $this->get_pathfor('/aisourcesdecision')
            ),
        ];
    }

    /**
     * Write the decision for the restored activity.
     *
     * @param array|object $data The backed-up decision.
     * @return void
     */
    public function process_aisourcesdecision($data): void {
        global $DB;

        $data = (object) $data;
        $cmid = (int) $this->task->get_moduleid();
        $courseid = (int) $this->task->get_courseid();
        if ($cmid <= 0) {
            return;
        }

        $values = [
            'courseid' => $courseid,
            'included' => (int) $data->included === 1 ? 1 : 0,
            'usermodified' => 0,
            'timemodified' => time(),
        ];

        // Restoring into a course that already holds a decision for this
        // module (re-restore over an existing activity) must not duplicate it.
        $existing = $DB->get_record('local_elediaai_sources_cm', ['cmid' => $cmid]);
        if ($existing) {
            $DB->update_record(
                'local_elediaai_sources_cm',
                (object) (['id' => $existing->id, 'cmid' => $cmid] + $values)
            );
            return;
        }
        $DB->insert_record('local_elediaai_sources_cm', (object) (['cmid' => $cmid] + $values));
    }
}

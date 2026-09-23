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

namespace local_elediaai_sources\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_elediaai_sources\activity_gate;
use local_elediaai_sources\course_gate;
use local_elediaai_sources\task\ingest_module_task;

/**
 * Store a per-activity ingestion decision (AJAX backend of the course page).
 *
 * Besides writing the decision this queues the module's ingest task — the
 * same one every module-form save gets from the course_module_updated event —
 * so a toggle on the course page converges the index just as promptly:
 * the task reads the flag at cron time and upserts or prefix-deletes.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_activity_selection extends external_api {
    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'state' => new external_value(PARAM_ALPHA, 'One of: included, excluded, default'),
        ]);
    }

    /**
     * Store the decision and queue the module's ingest task.
     *
     * @param int $cmid The course module id.
     * @param string $state One of 'included', 'excluded', 'default'.
     * @return array Effective state.
     */
    public static function execute(int $cmid, string $state): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'state' => $state,
        ]);

        if (!in_array($params['state'], ['included', 'excluded', 'default'], true)) {
            throw new \invalid_parameter_exception('state must be included, excluded or default');
        }

        $cm = get_coursemodule_from_id('', $params['cmid'], 0, false, MUST_EXIST);
        $context = \core\context\module::instance($cm->id);
        self::validate_context($context);
        require_capability('local/elediaai_sources:selectactivities', $context);

        // While the test-phase lock is on, the selection is centrally frozen —
        // the UI is hidden, and the backend refuses too.
        if (course_gate::marking_locked()) {
            throw new \moodle_exception(
                'nopermissions',
                'error',
                '',
                get_string('lockcoursemarking', 'local_elediaai_sources')
            );
        }

        if ($params['state'] === 'default') {
            activity_gate::clear((int) $cm->id);
        } else {
            activity_gate::set_included((int) $cm->course, (int) $cm->id, $params['state'] === 'included');
        }

        $task = new ingest_module_task();
        $task->set_custom_data([
            'courseid' => (int) $cm->course,
            'cmid' => (int) $cm->id,
        ]);
        \core\task\manager::queue_adhoc_task($task, true);

        return [
            'cmid' => (int) $cm->id,
            'effective' => activity_gate::should_ingest_cm((int) $cm->id),
        ];
    }

    /**
     * Return definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'effective' => new external_value(PARAM_BOOL, 'Whether the activity will be ingested now'),
        ]);
    }
}

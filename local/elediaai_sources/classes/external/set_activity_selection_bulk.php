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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_elediaai_sources\activity_gate;
use local_elediaai_sources\course_gate;
use local_elediaai_sources\task\ingest_module_task;

/**
 * Store one ingestion decision for several activities of a course at once.
 *
 * Backs the per-section bulk buttons: one round-trip and one transaction
 * instead of a client-side loop, so a section either flips completely or not
 * at all. Every cmid must belong to the given course — a foreign cmid aborts
 * the whole call before anything is written.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_activity_selection_bulk extends external_api {
    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'cmids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Course module id')
            ),
            'state' => new external_value(PARAM_ALPHA, 'One of: included, excluded, default'),
        ]);
    }

    /**
     * Store the decisions and queue the modules' ingest tasks.
     *
     * @param int $courseid The course id.
     * @param int[] $cmids The course module ids, all within that course.
     * @param string $state One of 'included', 'excluded', 'default'.
     * @return array Number of updated activities.
     */
    public static function execute(int $courseid, array $cmids, string $state): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'cmids' => $cmids,
            'state' => $state,
        ]);

        if (!in_array($params['state'], ['included', 'excluded', 'default'], true)) {
            throw new \invalid_parameter_exception('state must be included, excluded or default');
        }

        $course = get_course($params['courseid']);
        $context = \core\context\course::instance($course->id);
        self::validate_context($context);
        require_capability('local/elediaai_sources:selectactivities', $context);

        if (course_gate::marking_locked()) {
            throw new \moodle_exception(
                'nopermissions',
                'error',
                '',
                get_string('lockcoursemarking', 'local_elediaai_sources')
            );
        }

        $modinfo = get_fast_modinfo($course);
        $cms = $modinfo->get_cms();

        // All-or-nothing: a cmid outside this course aborts before any write.
        foreach ($params['cmids'] as $cmid) {
            if (!isset($cms[$cmid])) {
                throw new \invalid_parameter_exception("cmid {$cmid} does not belong to course {$course->id}");
            }
        }

        $transaction = $DB->start_delegated_transaction();
        foreach ($params['cmids'] as $cmid) {
            if ($params['state'] === 'default') {
                activity_gate::clear((int) $cmid);
            } else {
                activity_gate::set_included((int) $course->id, (int) $cmid, $params['state'] === 'included');
            }
        }
        $transaction->allow_commit();

        // Queue outside the transaction: the tasks read the committed rows at
        // cron time and converge the index (upsert or prefix delete).
        foreach ($params['cmids'] as $cmid) {
            $task = new ingest_module_task();
            $task->set_custom_data([
                'courseid' => (int) $course->id,
                'cmid' => (int) $cmid,
            ]);
            \core\task\manager::queue_adhoc_task($task, true);
        }

        return ['updated' => count($params['cmids'])];
    }

    /**
     * Return definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'updated' => new external_value(PARAM_INT, 'Number of activities updated'),
        ]);
    }
}

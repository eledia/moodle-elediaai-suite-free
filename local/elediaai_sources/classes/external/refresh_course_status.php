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
use local_elediaai_sources\course_gate;
use local_elediaai_sources\output\activities_page;
use local_elediaai_sources\status_sync;

/**
 * Refresh the processing status of a course's documents (AJAX backend of the course page).
 *
 * Called once right after the activity page rendered, and by its "Refresh
 * status" button (D-c). The page itself never waits for the destination; this
 * function asks it, through the same service the five-minute task uses, and
 * returns the stored state afterwards -- also when it asked nothing because
 * the course is throttled, nothing is in flight, or the status API failed.
 * Badge, detail and head are built here from the same methods the page uses,
 * so the browser only swaps them in.
 *
 * Same rights as the page: the capability in the course context, the feature
 * switched on, the selection not centrally locked.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class refresh_course_status extends external_api {
    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
        ]);
    }

    /**
     * Check the course's documents in flight, throttled, and return the stored state.
     *
     * @param int $courseid The course id.
     * @return array The state after the check.
     */
    public static function execute(int $courseid): array {
        global $PAGE;

        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid]);

        $course = get_course($params['courseid']);
        $context = \core\context\course::instance((int) $course->id);
        self::validate_context($context);
        require_capability('local/elediaai_sources:selectactivities', $context);
        \local_elediaai_core\feature\registry::require_enabled('sources');
        if (course_gate::marking_locked()) {
            throw new \moodle_exception(
                'nopermissions',
                'error',
                '',
                get_string('lockcoursemarking', 'local_elediaai_sources')
            );
        }

        $result = \core\di::get(status_sync::class)->sync_course((int) $course->id, true);

        $page = (new activities_page($course))->export_for_template($PAGE->get_renderer('core'));
        $activities = [];
        foreach ($page['sections'] as $section) {
            foreach ($section['activities'] as $activity) {
                $activities[] = [
                    'cmid' => (int) $activity['cmid'],
                    'status' => (string) $activity['status'],
                    'statuslabel' => (string) $activity['statuslabel'],
                    'statusclass' => (string) $activity['statusclass'],
                    'statustitle' => (string) $activity['statustitle'],
                    'statusdetail' => (string) $activity['statusdetail'],
                    'statusreason' => (string) $activity['statusreason'],
                    'inflight' => (bool) $activity['inflight'],
                ];
            }
        }

        return [
            'refreshed' => (bool) $result['refreshed'],
            'throttled' => (bool) $result['throttled'],
            'unavailable' => (bool) $result['unavailable'],
            'nothinginflight' => (bool) $result['nothinginflight'],
            'nextallowed' => (int) $result['nextallowed'],
            'retryin' => max(0, (int) $result['nextallowed'] - time()),
            'lastchecked' => (int) $page['lastchecked'],
            'lastcheckedtext' => (string) $page['lastcheckedtext'],
            'coursestatus' => (string) $page['coursestatus'],
            'coursestatuslabel' => (string) $page['coursestatuslabel'],
            'countindexed' => (int) $page['countindexed'],
            'countprocessing' => (int) $page['countprocessing'],
            'counterror' => (int) $page['counterror'],
            'hasinflight' => (bool) $page['hasinflight'],
            'statusunavailable' => (bool) $page['statusunavailable'],
            'activities' => $activities,
        ];
    }

    /**
     * Return definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'refreshed' => new external_value(PARAM_BOOL, 'Whether the destination answered this call'),
            'throttled' => new external_value(PARAM_BOOL, 'Whether the call asked nothing because the course was checked just now'),
            'unavailable' => new external_value(PARAM_BOOL, 'Whether the status API failed this call'),
            'nothinginflight' => new external_value(PARAM_BOOL, 'Whether no document is being processed any more'),
            'nextallowed' => new external_value(PARAM_INT, 'Unix time from which the next check may ask the destination'),
            'retryin' => new external_value(PARAM_INT, 'Seconds until the next check may ask the destination'),
            'lastchecked' => new external_value(PARAM_INT, 'Unix time of the last successful status check, 0 when none'),
            'lastcheckedtext' => new external_value(PARAM_TEXT, 'The last successful check in words, or empty'),
            'coursestatus' => new external_value(PARAM_ALPHA, 'processing, partial, indexed, error or none'),
            'coursestatuslabel' => new external_value(PARAM_TEXT, 'The course status in words'),
            'countindexed' => new external_value(PARAM_INT, 'Activities confirmed in the index'),
            'countprocessing' => new external_value(PARAM_INT, 'Activities accepted, not yet confirmed'),
            'counterror' => new external_value(PARAM_INT, 'Activities that failed'),
            'hasinflight' => new external_value(PARAM_BOOL, 'Whether documents are still being processed'),
            'statusunavailable' => new external_value(PARAM_BOOL, 'Whether the status of documents in flight cannot be read'),
            'activities' => new external_multiple_structure(new external_single_structure([
                'cmid' => new external_value(PARAM_INT, 'Course module id'),
                'status' => new external_value(PARAM_ALPHA, 'Machine status of the activity'),
                'statuslabel' => new external_value(PARAM_TEXT, 'Badge text'),
                'statusclass' => new external_value(PARAM_TEXT, 'Badge classes'),
                'statustitle' => new external_value(PARAM_TEXT, 'Title attribute of the badge'),
                'statusdetail' => new external_value(PARAM_TEXT, 'Visible detail line'),
                'statusreason' => new external_value(PARAM_TEXT, 'Original message of the destination'),
                'inflight' => new external_value(PARAM_BOOL, 'Whether the activity is still being processed'),
            ])),
        ]);
    }
}

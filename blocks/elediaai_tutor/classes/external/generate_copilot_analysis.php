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

namespace block_elediaai_tutor\external;

use block_elediaai_tutor\local\copilot_service;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use moodle_exception;

/**
 * External function: generate the Teacher-Copilot analysis for a course.
 *
 * Available to holders of block/elediaai_tutor:viewreports on the question
 * analytics report page. Reads nothing beyond the already-logged questions
 * and writes nothing to Moodle.
 *
 * @package     block_elediaai_tutor
 * @author      Johannes Moskaliuk
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generate_copilot_analysis extends external_api {
    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course to analyse'),
        ]);
    }

    /**
     * Generate and return the analysis.
     *
     * @param int $courseid The course id.
     * @return array Response structure.
     * @throws moodle_exception
     */
    public static function execute(int $courseid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid]);

        $course = get_course($params['courseid']);
        require_login($course, false);
        $context = \core\context\course::instance($course->id);
        self::validate_context($context);
        // Die eigene Capability der Kurs-Einblicke, und zusaetzlich die alte
        // des Berichts: bestehende Rollenzuweisungen sollen nach dem Umzug
        // nicht ins Leere laufen.
        $allowed = has_capability('local/elediaai_core:viewcourseinsights', $context)
            || has_capability('block/elediaai_tutor:viewreports', $context);
        if (!$allowed) {
            throw new \core\exception\required_capability_exception(
                $context,
                'local/elediaai_core:viewcourseinsights',
                'nopermissions',
                ''
            );
        }

        // Kein Schalter mehr davor. Die Analyse hing an der Opt-in-Einstellung
        // "enableanalytics" des alten Frage-Logs; der Turn-Speicher laeuft
        // immer, und ein Schalter, der eine Auswertung abschaltet, deren Daten
        // ohnehin da sind, verwirrt nur.

        $result = copilot_service::analyse((int) $course->id, (int) $USER->id, $context);

        return [
            'analysishtml' => $result['analysishtml'],
            'iserror' => $result['iserror'],
            'generatedat' => time(),
        ];
    }

    /**
     * Return definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'analysishtml' => new external_value(PARAM_RAW, 'Sanitised HTML of the analysis'),
            'iserror' => new external_value(PARAM_BOOL, 'Whether the tool reported an error'),
            'generatedat' => new external_value(PARAM_INT, 'Unix timestamp of generation'),
        ]);
    }
}

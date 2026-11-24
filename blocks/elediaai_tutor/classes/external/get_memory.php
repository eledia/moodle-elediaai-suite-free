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

use block_elediaai_tutor\local\ltm;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * External function: what the tutor has remembered about the calling user.
 *
 * Always the calling user's own memory: the backend is asked with that user's
 * token and answers for its owner only. Read-only; nothing is stored in Moodle.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_memory extends external_api {
    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'Context id of the block'),
        ]);
    }

    /**
     * Load the current user's stored memory.
     *
     * @param int $contextid Block (or system) context id.
     * @return array The view of {@see ltm::fetch()}.
     */
    public static function execute(int $contextid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
        ]);

        $context = helper::resolve_context($params['contextid']);
        require_login();
        self::validate_context($context);
        require_capability('block/elediaai_tutor:use', $context);

        return ltm::fetch((int) $USER->id, $context);
    }

    /**
     * Return definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_ALPHA, 'ok, unavailable or error'),
            'categories' => new external_multiple_structure(
                new external_single_structure([
                    'category' => new external_value(PARAM_ALPHA, 'Category key'),
                    'label' => new external_value(PARAM_TEXT, 'Category label'),
                    'items' => new external_multiple_structure(
                        new external_single_structure([
                            'content' => new external_value(PARAM_TEXT, 'What was remembered'),
                            'updated' => new external_value(PARAM_INT, 'Last change, Unix time; 0 if unknown'),
                            'updatedlabel' => new external_value(PARAM_TEXT, 'Last change, formatted'),
                        ])
                    ),
                ])
            ),
            'sessions' => new external_multiple_structure(
                new external_single_structure([
                    'courseid' => new external_value(PARAM_INT, 'Course id; 0 without a course'),
                    'coursename' => new external_value(PARAM_TEXT, 'Course name, or a note that there is none'),
                    'lines' => new external_multiple_structure(
                        new external_single_structure([
                            'label' => new external_value(PARAM_TEXT, 'Translated line prefix, may be empty'),
                            'text' => new external_value(PARAM_TEXT, 'Line text as the agent wrote it'),
                        ])
                    ),
                    'updated' => new external_value(PARAM_INT, 'Last change, Unix time; 0 if unknown'),
                    'updatedlabel' => new external_value(PARAM_TEXT, 'Last change, formatted'),
                    'hidden' => new external_value(PARAM_BOOL, 'Behind "Show more" at first'),
                ])
            ),
            'hasmemories' => new external_value(PARAM_BOOL, 'Whether any memory is stored'),
            'hassessions' => new external_value(PARAM_BOOL, 'Whether any session note is stored'),
            'sessioncount' => new external_value(PARAM_INT, 'Number of session notes delivered'),
            'hasmore' => new external_value(PARAM_BOOL, 'Whether some session notes start hidden'),
            'sessionpage' => new external_value(PARAM_INT, 'Session notes revealed per "Show more"'),
        ]);
    }
}

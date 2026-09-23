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

use block_elediaai_tutor\local\deletion_service;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * External function: delete all of the current user's tutor data.
 *
 * Always acts on the calling user only. All local tutor data is always removed;
 * deletion is propagated to the external RAG/Tutor server when a delete tool
 * is configured, and otherwise honestly reported as unsupported.
 * Called over Moodle's authenticated AJAX channel (sesskey enforced by the
 * AJAX endpoint).
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_my_data extends external_api {
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
     * Delete all tutor data for the current user.
     *
     * @param int $contextid Block (or system) context id.
     * @return array Deletion counters and external deletion status.
     */
    public static function execute(int $contextid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
        ]);

        $context = helper::resolve_context($params['contextid']);
        require_login();
        self::validate_context($context);
        require_capability('block/elediaai_tutor:deleteownhistory', $context);

        return deletion_service::delete_all_for_user((int) $USER->id, $context);
    }

    /**
     * Return definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'localdeleted' => new external_value(PARAM_INT, 'Total local data items deleted'),
            'conversationsdeleted' => new external_value(PARAM_INT, 'Local conversations deleted'),
            'questionsdeleted' => new external_value(PARAM_INT, 'Question log entries deleted'),
            'consentsdeleted' => new external_value(PARAM_INT, 'Consent records deleted'),
            'usagedeleted' => new external_value(PARAM_INT, 'Usage counter records deleted'),
            'diagnosticsdeleted' => new external_value(PARAM_INT, 'Diagnostic records deleted'),
            'ltmpreferencedeleted' => new external_value(PARAM_INT, 'LTM preferences deleted'),
            'externalsupported' => new external_value(PARAM_BOOL, 'Whether external deletion is configured'),
            'externaldeleted' => new external_value(PARAM_INT, 'External deletions that succeeded'),
            'externalfailed' => new external_value(PARAM_INT, 'External deletions that failed'),
        ]);
    }
}

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
use core_external\external_single_structure;
use core_external\external_value;

/**
 * External function: set the current user's long-term memory opt-in.
 *
 * Always acts on the calling user only; there is no way to change another
 * user's preference through this endpoint. Called over Moodle's authenticated
 * AJAX channel (sesskey enforced by the AJAX endpoint).
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_ltm extends external_api {
    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'Context id of the block'),
            'enabled' => new external_value(PARAM_BOOL, 'Whether long-term memory is opted in'),
        ]);
    }

    /**
     * Store the opt-in choice for the current user.
     *
     * @param int $contextid Block (or system) context id.
     * @param bool $enabled The new opt-in state.
     * @return array{enabled: bool}
     */
    public static function execute(int $contextid, bool $enabled): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'enabled' => $enabled,
        ]);

        $context = helper::resolve_context($params['contextid']);
        require_login();
        self::validate_context($context);
        require_capability('block/elediaai_tutor:use', $context);

        ltm::set_enabled((int) $USER->id, (bool) $params['enabled']);

        \block_elediaai_tutor\event\ltm_preference_changed::create([
            'context' => $context,
            'userid' => (int) $USER->id,
            'relateduserid' => (int) $USER->id,
            'other' => ['enabled' => (int) (bool) $params['enabled']],
        ])->trigger();

        // Best-effort push to the RAG server (no-op unless the admin has
        // configured the memory opt-in tool). The preference change itself
        // never depends on the server being reachable: every chat call carries
        // the current consent independently.
        ltm::sync_to_rag((int) $USER->id, $context);

        return ['enabled' => ltm::is_enabled((int) $USER->id)];
    }

    /**
     * Return definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'enabled' => new external_value(PARAM_BOOL, 'The stored opt-in state'),
        ]);
    }
}

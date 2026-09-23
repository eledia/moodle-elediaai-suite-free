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

namespace local_elediaai_chatengine\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_elediaai_chatengine\local\thread_store;
use local_elediaai_chatengine\placement\registry;

/**
 * Drop the turns of a conversation at its owner's request.
 *
 * The backend's own conversation id is forgotten at the same time, so the next
 * turn starts a new transcript rather than resuming one the person just asked
 * to be rid of on a service that never heard about it.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class clear_conversation extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'component' => new external_value(PARAM_COMPONENT, 'Frankenstyle component of the placement'),
            'instanceid' => new external_value(PARAM_INT, 'Placement instance id, 0 for site-wide', VALUE_DEFAULT, 0),
            'threadid' => new external_value(PARAM_INT, 'Conversation to clear, 0 for the current one', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Clear the conversation.
     *
     * @param string $component Frankenstyle component of the placement.
     * @param int $instanceid Placement instance id.
     * @param int $threadid Conversation to clear, 0 for the current one.
     * @return array Whether anything was cleared.
     */
    public static function execute(string $component, int $instanceid, int $threadid = 0): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'component' => $component,
            'instanceid' => $instanceid,
            'threadid' => $threadid,
        ]);

        $placement = registry::require_placement((string) $params['component']);
        $context = $placement->context((int) $params['instanceid']);
        self::validate_context($context);
        $placement->require_access((int) $params['instanceid'], (int) $USER->id);
        // Hiding the button is a courtesy to the reader; this is the rule. A
        // surface that has taken the conversation as work handed in says no,
        // and the endpoint is where that has to hold -- it is reachable
        // without the panel.
        if (!$placement->may_clear((int) $params['instanceid'], (int) $USER->id)) {
            throw new \moodle_exception('error_clearnotallowed', 'local_elediaai_chatengine');
        }

        $thread = $params['threadid'] > 0
            ? thread_store::owned((int) $params['threadid'], (int) $USER->id)
            : thread_store::current((string) $params['component'], (int) $params['instanceid'], (int) $USER->id);

        if ($thread === null) {
            return ['cleared' => false];
        }

        thread_store::clear((int) $thread->id);

        return ['cleared' => true];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cleared' => new external_value(PARAM_BOOL, 'Whether a conversation was cleared'),
        ]);
    }
}

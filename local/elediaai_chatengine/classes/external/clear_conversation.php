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
use local_elediaai_chatengine\local\backend_erasure;
use local_elediaai_chatengine\local\thread_store;
use local_elediaai_chatengine\placement\registry;

/**
 * Delete or empty a conversation at its owner's request.
 *
 * Two requests share the endpoint (ai_suite#256):
 *
 *  - With a thread id the reader deletes that conversation, typically out of
 *    the history list. The thread goes with its turns; an emptied thread
 *    would come back as an empty row at the top of the list.
 *  - Without one the reader empties the conversation they are in, as the
 *    clear button of a surface with one conversation per activity does. The
 *    thread stays, so the next turn carries on in it.
 *
 * Either way the backend's transcript is deleted too (tutor_delete_conversation,
 * when switched on) and its conversation id forgotten, so the next turn starts
 * a new transcript instead of resuming the one the person asked to be rid of.
 * A backend that fails does not keep the conversation in Moodle alive.
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
            'threadid' => new external_value(PARAM_INT, 'Conversation to delete, 0 to empty the current one', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Delete the named conversation, or empty the current one.
     *
     * @param string $component Frankenstyle component of the placement.
     * @param int $instanceid Placement instance id.
     * @param int $threadid Conversation to delete, 0 to empty the current one.
     * @return array Whether anything was deleted or emptied.
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

        // The placement's rule above only holds for its own conversations. A
        // thread id from another surface, or another instance, would let one
        // placement's yes delete what another one protects -- a scenario
        // handed in, say -- so such a thread counts as not there.
        $here = $thread !== null && (string) $thread->placement === (string) $params['component'];
        $here = $here && (int) $thread->instanceid === (int) $params['instanceid'];
        if (!$here) {
            return ['cleared' => false];
        }

        // The record as it was: clear() forgets the conversation id the
        // backend needs to find its transcript.
        if ($params['threadid'] > 0) {
            thread_store::delete((int) $thread->id);
        } else {
            thread_store::clear((int) $thread->id);
        }
        // Moodle first: the backend is best effort and never throws.
        backend_erasure::erase_conversations([$thread]);

        return ['cleared' => true];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cleared' => new external_value(PARAM_BOOL, 'Whether a conversation was deleted or emptied'),
        ]);
    }
}

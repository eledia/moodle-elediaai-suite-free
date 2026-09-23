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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_elediaai_chatengine\local\connection;
use local_elediaai_chatengine\local\markdown_renderer;
use local_elediaai_chatengine\local\message;
use local_elediaai_chatengine\local\thread_store;
use local_elediaai_chatengine\placement\registry;

/**
 * Load a conversation so a reloaded page continues where it left off.
 *
 * Served from this site's own store, not from the backend. A learner
 * reopening their chat must not depend on an external service being reachable,
 * and a site that has switched destination must still be able to show what was
 * said before the switch.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class load_history extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'component' => new external_value(PARAM_COMPONENT, 'Frankenstyle component of the placement'),
            'instanceid' => new external_value(PARAM_INT, 'Placement instance id, 0 for site-wide', VALUE_DEFAULT, 0),
            'threadid' => new external_value(PARAM_INT, 'Conversation to load, 0 for the current one', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Load the turns.
     *
     * @param string $component Frankenstyle component of the placement.
     * @param int $instanceid Placement instance id.
     * @param int $threadid Conversation to load, 0 for the current one.
     * @return array The conversation.
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

        $thread = $params['threadid'] > 0
            ? thread_store::owned((int) $params['threadid'], (int) $USER->id)
            : thread_store::current((string) $params['component'], (int) $params['instanceid'], (int) $USER->id);

        if ($thread === null) {
            return ['threadid' => 0, 'messages' => []];
        }

        $messages = thread_store::messages((int) $thread->id, connection::history_limit());

        return [
            'threadid' => (int) $thread->id,
            'messages' => array_values(array_map(
                static fn(message $item): array => [
                    'role' => $item->role,
                    'html' => $item->role === message::ROLE_ASSISTANT
                        ? markdown_renderer::render($item->content, $context)
                        : format_text($item->content, FORMAT_PLAIN, ['context' => $context]),
                    'timecreated' => $item->timecreated,
                    // The citations and the origin travel with a restored turn
                    // for the same reason they travel with a fresh one: without
                    // them a grounded answer looks exactly like an ungrounded
                    // one, and the grounding claim is the whole point of
                    // showing sources.
                    'sources' => array_map(
                        static fn(array $source): array => [
                            'title' => (string) ($source['title'] ?? ''),
                            'url' => (string) ($source['url'] ?? ''),
                            'snippet' => (string) ($source['snippet'] ?? ''),
                        ],
                        array_values($item->sources)
                    ),
                    'origin' => $item->origin,
                ],
                $messages
            )),
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'threadid' => new external_value(PARAM_INT, 'The conversation, or 0 when there is none yet'),
            'messages' => new external_multiple_structure(
                new external_single_structure([
                    'role' => new external_value(PARAM_ALPHA, 'user or assistant'),
                    'html' => new external_value(PARAM_RAW, 'The turn, rendered and sanitised server-side'),
                    'timecreated' => new external_value(PARAM_INT, 'When the turn was recorded'),
                    'sources' => new external_multiple_structure(
                        new external_single_structure([
                            'title' => new external_value(PARAM_TEXT, 'Source title'),
                            'url' => new external_value(PARAM_URL, 'Source URL'),
                            'snippet' => new external_value(PARAM_TEXT, 'Short excerpt'),
                        ]),
                        'Cited course material, empty for a user turn or an ungrounded answer'
                    ),
                    'origin' => new external_value(
                        PARAM_ALPHA,
                        'grounded, mcp or general; empty for a user turn and for turns recorded before this was stored'
                    ),
                ]),
                'Turns, oldest first'
            ),
        ]);
    }
}

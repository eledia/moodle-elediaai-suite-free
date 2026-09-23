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
use local_elediaai_chatengine\local\thread_store;
use local_elediaai_chatengine\placement\registry;

/**
 * List a person's conversations in one placement.
 *
 * Several threads per owner are an engine feature, so listing them is too, even
 * though only the tutor block currently shows the list. Putting it in the
 * placement that happens to use it first is how the four implementations came
 * about in the first place.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class list_conversations extends external_api {
    /** @var int Most conversations a list ever needs to show. */
    private const MAX_ROWS = 50;

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'component' => new external_value(PARAM_COMPONENT, 'Frankenstyle component of the placement'),
            'instanceid' => new external_value(PARAM_INT, 'Placement instance id, 0 for site-wide', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * List the conversations.
     *
     * @param string $component Frankenstyle component of the placement.
     * @param int $instanceid Placement instance id.
     * @return array The conversations.
     */
    public static function execute(string $component, int $instanceid = 0): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'component' => $component,
            'instanceid' => $instanceid,
        ]);

        $placement = registry::require_placement((string) $params['component']);
        $context = $placement->context((int) $params['instanceid']);
        self::validate_context($context);
        $placement->require_access((int) $params['instanceid'], (int) $USER->id);

        $threads = thread_store::threads_for(
            (string) $params['component'],
            (int) $params['instanceid'],
            (int) $USER->id,
            null,
            self::MAX_ROWS
        );

        return [
            'conversations' => array_map(
                static fn(\stdClass $thread): array => [
                    'id' => (int) $thread->id,
                    'title' => (string) ($thread->title ?? ''),
                    'preview' => (string) ($thread->lastpreview ?? ''),
                    'timemodified' => (int) $thread->timemodified,
                ],
                $threads
            ),
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'conversations' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Conversation id'),
                    'title' => new external_value(PARAM_TEXT, 'Display title, empty when none was set'),
                    'preview' => new external_value(PARAM_TEXT, 'Preview of the last message'),
                    'timemodified' => new external_value(PARAM_INT, 'When the conversation was last used'),
                ]),
                'Conversations, most recently used first'
            ),
        ]);
    }
}

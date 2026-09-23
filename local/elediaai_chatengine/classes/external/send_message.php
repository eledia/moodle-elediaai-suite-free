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
use local_elediaai_chatengine\chat_service;
use local_elediaai_chatengine\local\hints;
use local_elediaai_chatengine\placement\registry;

/**
 * Send one chat turn, from whichever placement is asking.
 *
 * One endpoint for every surface. The client names its placement and instance;
 * everything that follows — who may chat, which backend answers, in which mode
 * — is decided server-side. A client that sent a different mode or a different
 * backend would simply be ignored, because it is never asked.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_message extends external_api {
    /**
     * Parameters.
     *
     * The hints are appended from the engine's own vocabulary rather than
     * listed here, so this endpoint and `stream.php` accept the same set. They
     * are all optional and default to the empty string: a placement that sends
     * none reaches the backend with exactly what it did before.
     *
     * The order matters. Moodle hands the validated parameters to
     * {@see self::execute()} positionally, so every name declared here has to
     * appear in that signature in this order.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(array_merge([
            'component' => new external_value(PARAM_COMPONENT, 'Frankenstyle component of the placement'),
            'instanceid' => new external_value(PARAM_INT, 'Placement instance id, 0 for site-wide', VALUE_DEFAULT, 0),
            'message' => new external_value(PARAM_RAW, 'The message as typed'),
            'threadid' => new external_value(PARAM_INT, 'Conversation to continue, 0 for the current one', VALUE_DEFAULT, 0),
            'newthread' => new external_value(
                PARAM_BOOL,
                'Start a fresh conversation instead of continuing the current one; ignored when threadid names one',
                VALUE_DEFAULT,
                false
            ),
        ], hints::parameters()));
    }

    /**
     * Run the turn.
     *
     * @param string $component Frankenstyle component of the placement.
     * @param int $instanceid Placement instance id.
     * @param string $message The message as typed.
     * @param int $threadid Conversation to continue, 0 for the current one.
     * @param bool $newthread Start a fresh conversation instead of continuing the current one.
     * @param string $answerstyle Pedagogical answer style asked for, or ''.
     * @param string $intent Routing hint asked for, or ''.
     * @param string $pendingdecision Answer to a confirmation card, or ''.
     * @return array The render-ready answer.
     */
    public static function execute(
        string $component,
        int $instanceid,
        string $message,
        int $threadid = 0,
        bool $newthread = false,
        string $answerstyle = '',
        string $intent = '',
        string $pendingdecision = ''
    ): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'component' => $component,
            'instanceid' => $instanceid,
            'message' => $message,
            'threadid' => $threadid,
            'newthread' => $newthread,
            'answerstyle' => $answerstyle,
            'intent' => $intent,
            'pendingdecision' => $pendingdecision,
        ]);

        $placement = registry::require_placement((string) $params['component']);
        $context = $placement->context((int) $params['instanceid']);
        self::validate_context($context);

        // The hints are the client's wish, not its decision: the engine checks
        // them against its vocabulary and the placement has the last word.
        $clienthints = [];
        foreach (hints::names() as $name) {
            $clienthints[$name] = (string) $params[$name];
        }

        $result = chat_service::send(
            (string) $params['component'],
            (int) $params['instanceid'],
            (int) $USER->id,
            (string) $params['message'],
            $params['threadid'] > 0 ? (int) $params['threadid'] : null,
            null,
            [],
            null,
            null,
            $clienthints,
            !empty($params['newthread']),
        );

        return [
            'threadid' => $result['threadid'],
            'answerhtml' => $result['answerhtml'],
            'sources' => array_map(
                static fn(array $source): array => [
                    'title' => (string) ($source['title'] ?? ''),
                    'url' => (string) ($source['url'] ?? ''),
                    'snippet' => (string) ($source['snippet'] ?? ''),
                ],
                array_values($result['sources'])
            ),
            'origin' => $result['origin'],
            'iserror' => $result['iserror'],
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'threadid' => new external_value(PARAM_INT, 'The conversation the turn was recorded in'),
            'answerhtml' => new external_value(PARAM_RAW, 'The answer, already rendered and sanitised server-side'),
            'sources' => new external_multiple_structure(
                new external_single_structure([
                    'title' => new external_value(PARAM_TEXT, 'Source title'),
                    'url' => new external_value(PARAM_URL, 'Source URL'),
                    'snippet' => new external_value(PARAM_TEXT, 'Short excerpt'),
                ]),
                'Cited course material, empty for an ungrounded answer'
            ),
            'origin' => new external_value(PARAM_ALPHA, 'grounded or general'),
            'iserror' => new external_value(PARAM_BOOL, 'Whether the backend reported a tool-level error'),
        ]);
    }
}

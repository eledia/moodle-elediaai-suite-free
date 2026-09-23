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

use block_elediaai_tutor\chatengine\placement;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_elediaai_chatengine\chat_service;
use local_elediaai_chatengine\local\hints;

/**
 * Send one tutor turn from a named surface.
 *
 * The tutor is configured per block instance — persona, branding, answer mode,
 * daily budget, whether the style may be changed — while a conversation belongs
 * to the **course**: `view.php?courseid=` answers for any course without a
 * block instance to key a thread on. The shared endpoint carries one instance
 * id and therefore cannot carry both.
 *
 * So the tutor keeps its own front door, which the engine's contract invites
 * (docs/contract.md §6). It is thin on purpose: prove which surface the turn
 * came from, name it, and hand the turn to the engine unchanged. The
 * conversation, the safety framing, the quota, the backend and the audit trail
 * stay where they belong.
 *
 * Nothing here reaches the backend that did not before. What changes is where
 * the values come from: the instance a learner actually has in front of them,
 * instead of the site defaults a course context could never resolve.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_message extends external_api {
    /**
     * Parameters.
     *
     * The hints come from the engine's vocabulary rather than a list kept here,
     * so this endpoint accepts exactly what the shared one does. The order
     * matters: Moodle hands the validated parameters to {@see self::execute()}
     * positionally.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(array_merge([
            'contextid' => new external_value(
                PARAM_INT,
                'Where the chat is rendered: the block instance, or the course or system context of a standalone page'
            ),
            'message' => new external_value(PARAM_RAW, 'The message as typed'),
            'courseid' => new external_value(
                PARAM_INT,
                'Course the conversation belongs to, 0 for the global chat',
                VALUE_DEFAULT,
                0
            ),
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
     * @param int $contextid The context the chat is rendered in.
     * @param string $message The message as typed.
     * @param int $courseid The course, or 0 for the global chat.
     * @param int $threadid Conversation to continue, 0 for the current one.
     * @param bool $newthread Start a fresh conversation instead of continuing the current one.
     * @param string $answerstyle Pedagogical answer style asked for, or ''.
     * @param string $intent Routing hint asked for, or ''.
     * @param string $pendingdecision Answer to a confirmation card, or ''.
     * @return array The render-ready answer.
     */
    public static function execute(
        int $contextid,
        string $message,
        int $courseid = 0,
        int $threadid = 0,
        bool $newthread = false,
        string $answerstyle = '',
        string $intent = '',
        string $pendingdecision = ''
    ): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'message' => $message,
            'courseid' => $courseid,
            'threadid' => $threadid,
            'newthread' => $newthread,
            'answerstyle' => $answerstyle,
            'intent' => $intent,
            'pendingdecision' => $pendingdecision,
        ]);

        $courseid = max(0, (int) $params['courseid']);
        $scope = new placement();
        // The conversation's context, not the surface's: that is what the turn
        // is recorded against and what course access is enforced on.
        self::validate_context($scope->context($courseid));

        $clienthints = [];
        foreach (hints::names() as $name) {
            $clienthints[$name] = (string) $params[$name];
        }

        // Proven before it is believed, and given back afterwards: a static
        // that outlived the turn would configure the next one.
        placement::use_surface((int) $params['contextid'], $courseid);
        try {
            $result = chat_service::send(
                placement::component(),
                $courseid,
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
        } finally {
            placement::forget_surface();
        }

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
     * Identical to the shared endpoint's, so the panel drives either without a
     * translation layer in the browser.
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
            'origin' => new external_value(PARAM_ALPHA, 'grounded, general or mcp'),
            'iserror' => new external_value(PARAM_BOOL, 'Whether the backend reported a tool-level error'),
        ]);
    }
}

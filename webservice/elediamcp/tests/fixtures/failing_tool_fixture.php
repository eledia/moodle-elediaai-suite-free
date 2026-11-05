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

/**
 * Fixture tool that fails the way the test asks (#182).
 *
 * @package     webservice_elediamcp
 * @author      Immanuel Pasanec <immanuel.pasanec@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace webservice_elediamcp\tests\fixtures;

use stdClass;
use webservice_elediamcp\local\ai\ai_tool;
use webservice_elediamcp\local\ai\tool_exception;

/**
 * Tool that raises a business error, a PHP error or succeeds, by its `mode` argument.
 *
 * @package     webservice_elediamcp
 * @author      Immanuel Pasanec <immanuel.pasanec@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class failing_tool_fixture implements ai_tool {
    /**
     * Tool name.
     *
     * @return string
     */
    public static function name(): string {
        return 'fixture_fail';
    }

    /**
     * Tool title.
     *
     * @return string
     */
    public static function title(): string {
        return 'Fixture fail';
    }

    /**
     * Tool description.
     *
     * @return string
     */
    public static function description(): string {
        return 'Fails on request. Test fixture for the error log.';
    }

    /**
     * Input schema.
     *
     * @return array
     */
    public static function input_schema(): array {
        return ['type' => 'object', 'properties' => ['mode' => ['type' => 'string']], 'required' => []];
    }

    /**
     * Output schema.
     *
     * @return array
     */
    public static function output_schema(): array {
        return ['type' => 'object', 'properties' => ['ok' => ['type' => 'boolean']], 'required' => []];
    }

    /**
     * MCP annotations.
     *
     * @return array
     */
    public static function annotations(): array {
        return [
            'title' => self::title(),
            'readOnlyHint' => true,
            'destructiveHint' => false,
            'idempotentHint' => true,
            'openWorldHint' => false,
        ];
    }

    /**
     * Execute.
     *
     * @param array $arguments Tool arguments.
     * @param stdClass $user Acting user.
     * @return array
     */
    public static function execute(array $arguments, stdClass $user): array {
        switch ($arguments['mode'] ?? '') {
            case 'business':
                throw new tool_exception('No quiz with cmid 7 is visible to you.');
            case 'finished':
                throw new tool_exception(
                    'The question in slot 3 was already checked; its answer can no longer be changed.',
                    ['slot' => 3, 'errorcode' => 'question_finished']
                );
            case 'badargument':
                throw new tool_exception('attempt_id is required.', ['errorcode' => 'invalid_argument']);
            case 'retryable':
                throw new tool_exception(
                    'Answer for slot 3 is invalid: use one of the options.',
                    [
                        'slot' => 3,
                        'errorcode' => 'invalid_answer',
                        'retryable' => true,
                        'options' => [['value' => '1', 'label' => 'True']],
                    ]
                );
            case 'long':
                throw new tool_exception(str_repeat('x', 500));
            case 'php':
                // Like a missing include: the message carries a path and a value that must stay out of the log.
                throw new \Error('Call to undefined function secret_function() in /var/www/private/file.php');
            default:
                return ['ok' => true];
        }
    }
}

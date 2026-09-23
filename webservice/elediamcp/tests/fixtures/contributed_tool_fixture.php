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
 * Fixture tools for the contributed-tool provider hook tests.
 *
 * @package     webservice_elediamcp
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace webservice_elediamcp\tests\fixtures;

use stdClass;
use webservice_elediamcp\local\ai\ai_tool;

/**
 * Minimal valid contributed tool.
 *
 * @package     webservice_elediamcp
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class contributed_tool_fixture implements ai_tool {
    /**
     * Tool name.
     *
     * @return string
     */
    public static function name(): string {
        return 'fixture_echo';
    }

    /**
     * Tool title.
     *
     * @return string
     */
    public static function title(): string {
        return 'Fixture echo';
    }

    /**
     * Tool description.
     *
     * @return string
     */
    public static function description(): string {
        return 'Echoes its input back. Test fixture for the provider hook.';
    }

    /**
     * Input schema.
     *
     * @return array
     */
    public static function input_schema(): array {
        return [
            'type' => 'object',
            'properties' => ['value' => ['type' => 'string']],
            'required' => [],
            'additionalProperties' => false,
        ];
    }

    /**
     * Output schema.
     *
     * @return array
     */
    public static function output_schema(): array {
        return [
            'type' => 'object',
            'properties' => ['value' => ['type' => 'string']],
            'required' => ['value'],
            'additionalProperties' => false,
        ];
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
        return ['value' => (string) ($arguments['value'] ?? '')];
    }
}

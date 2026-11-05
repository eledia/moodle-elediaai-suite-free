<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Fixture write tool with the two-step contract (#178).
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

/**
 * Tool that answers with a preview, and with `created` only when called with `confirm`.
 *
 * @package     webservice_elediamcp
 * @author      Immanuel Pasanec <immanuel.pasanec@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class confirm_tool_fixture implements ai_tool {
    /**
     * Tool name.
     *
     * @return string
     */
    public static function name(): string {
        return 'fixture_write';
    }

    /**
     * Tool title.
     *
     * @return string
     */
    public static function title(): string {
        return 'Fixture write';
    }

    /**
     * Tool description.
     *
     * @return string
     */
    public static function description(): string {
        return 'Writes on request. Test fixture for the confirmation gate.';
    }

    /**
     * Input schema.
     *
     * @return array
     */
    public static function input_schema(): array {
        return [
            'type' => 'object',
            'properties' => ['name' => ['type' => 'string'], 'confirm' => ['type' => 'boolean']],
            'required' => [],
        ];
    }

    /**
     * Output schema.
     *
     * @return array
     */
    public static function output_schema(): array {
        return ['type' => 'object', 'properties' => ['created' => ['type' => 'boolean']], 'required' => []];
    }

    /**
     * MCP annotations.
     *
     * @return array
     */
    public static function annotations(): array {
        return [
            'title' => self::title(),
            'readOnlyHint' => false,
            'destructiveHint' => false,
            'idempotentHint' => false,
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
        if (empty($arguments['confirm'])) {
            return ['created' => false, 'requires_confirmation' => true, 'summary' => 'Would write.'];
        }
        return ['created' => true, 'requires_confirmation' => false, 'summary' => 'Written.'];
    }
}

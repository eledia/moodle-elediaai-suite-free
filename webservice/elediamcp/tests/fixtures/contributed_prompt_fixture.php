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
 * Fixture prompt for the contributed-prompt registry hook tests.
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace webservice_elediamcp\tests\fixtures;

use stdClass;
use webservice_elediamcp\local\mcp\prompt;

/**
 * Minimal valid contributed prompt.
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class contributed_prompt_fixture implements prompt {
    /**
     * Return the MCP prompt name.
     *
     * @return string Prompt identifier.
     */
    public static function name(): string {
        return 'fixture_prompt';
    }

    /**
     * Return the human-readable prompt title.
     *
     * @return string Display title.
     */
    public static function title(): string {
        return 'Fixture prompt';
    }

    /**
     * Return the prompt description shown to MCP clients.
     *
     * @return string Prompt description.
     */
    public static function description(): string {
        return 'Fixture prompt for registry tests.';
    }

    /**
     * Return the argument definitions accepted by this prompt.
     *
     * @return array<int, array<string, mixed>> Argument definitions.
     */
    public static function arguments(): array {
        return [];
    }

    /**
     * Return the MCP tools this prompt orchestrates.
     *
     * @return string[] Tool names.
     */
    public static function requires_tools(): array {
        return [];
    }

    /**
     * Render the prompt messages for the authenticated user.
     *
     * @param array<string, mixed> $arguments Validated prompt arguments.
     * @param stdClass $user Authenticated Moodle user.
     * @return array<int, array<string, mixed>> MCP messages.
     */
    public static function render(array $arguments, stdClass $user): array {
        return [['role' => 'user', 'content' => ['type' => 'text', 'text' => 'fixture']]];
    }
}

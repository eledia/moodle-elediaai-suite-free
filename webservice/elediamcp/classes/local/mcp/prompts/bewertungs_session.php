<?php
// This file is part of Moodle - http://moodle.org/.
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

declare(strict_types=1);

namespace webservice_elediamcp\local\mcp\prompts;

use stdClass;

/**
 * Prompt for a guided grading session.
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class bewertungs_session extends catalog {
    /**
     * Return the MCP prompt name.
     *
     * @return string Prompt identifier.
     */
    public static function name(): string {
        return 'bewertungs_session';
    }
    /**
     * Return the human-readable prompt title.
     *
     * @return string Display title.
     */
    public static function title(): string {
        return self::text('prompt_bewertungs_session_title');
    }
    /**
     * Return the prompt description shown to MCP clients.
     *
     * @return string Prompt description.
     */
    public static function description(): string {
        return self::text('prompt_bewertungs_session_description');
    }
    /**
     * Return the argument definitions accepted by this prompt.
     *
     * @return array<int, array<string, mixed>> Argument definitions.
     */
    public static function arguments(): array {
        return [
            ['name' => 'kurs_id', 'description' => self::text('prompt_arg_kurs_id'), 'type' => 'integer', 'required' => true],
            ['name' => 'aktivitaet', 'description' => self::text('prompt_arg_aktivitaet'), 'required' => false],
        ];
    }
    /**
     * Return the MCP tools this prompt orchestrates.
     *
     * @return string[] Tool names.
     */
    public static function requires_tools(): array {
        return ['moodle_grading_queue', 'moodle_read_submission', 'moodle_grade_submission'];
    }
    /**
     * Render the prompt messages for the authenticated user.
     *
     * @param array<string, mixed> $arguments Validated prompt arguments.
     * @param stdClass $user Authenticated Moodle user.
     * @return array<int, array<string, mixed>> MCP messages.
     */
    public static function render(array $arguments, stdClass $user): array {
        return self::message(self::text('prompt_bewertungs_session_text', [
            'courseid' => (int) $arguments['kurs_id'], 'activity' => (string) ($arguments['aktivitaet'] ?? ''),
        ]));
    }
}

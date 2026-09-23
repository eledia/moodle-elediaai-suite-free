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

use webservice_elediamcp\local\mcp\prompt;

/**
 * Common helpers for the built-in prompt catalogue.
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class catalog implements prompt {
    /**
     * Get a localized prompt string.
     *
     * @param string $key Language string key.
     * @param mixed $data Optional language string data.
     * @return string
     */
    protected static function text(string $key, mixed $data = null): string {
        return get_string($key, 'webservice_elediamcp', $data);
    }

    /**
     * Wrap prompt text in an MCP user message.
     *
     * @param string $text Prompt text.
     * @return array<int, array<string, mixed>>
     */
    protected static function message(string $text): array {
        return [['role' => 'user', 'content' => ['type' => 'text', 'text' => $text]]];
    }
}

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

namespace webservice_elediamcp\local;

/**
 * Decides whether the scopes of a token allow an MCP request.
 *
 * OAuth tokens carry the scopes the person agreed to on the consent page. A token with no
 * scope (manual tokens, tokens issued before scopes were stored) is not limited. The check
 * can be switched off for a whole site with `$CFG->webservice_elediamcp_enforce_scopes = false;`
 * in config.php (a host setting on purpose, not an admin setting).
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class scope_gate {
    /** Scope for tools that only read. */
    public const TOOLS_READ = 'mcp:tools.read';
    /** Scope for tools that change data. */
    public const TOOLS_CALL = 'mcp:tools.call';
    /** Scope for resources. */
    public const RESOURCES_READ = 'mcp:resources.read';
    /** Scope for prompt templates. */
    public const PROMPTS_READ = 'mcp:prompts.read';

    /**
     * Whether scopes are enforced on this site.
     *
     * @return bool
     */
    public static function is_enforced(): bool {
        global $CFG;
        if (!isset($CFG->webservice_elediamcp_enforce_scopes)) {
            return true;
        }
        $value = $CFG->webservice_elediamcp_enforce_scopes;
        // A text from the environment ('false', 'off', 'no', '0') switches off as well as the PHP values.
        return is_string($value) ? filter_var($value, FILTER_VALIDATE_BOOLEAN) : !empty($value);
    }

    /**
     * The scopes a request needs, any one of which is enough.
     *
     * @param string $method MCP method (tools/list, tools/call, prompts/list, prompts/get, ...).
     * @param bool $writetool For tools/call: the tool changes data.
     * @return string[] Accepted scopes; empty when the method needs no scope.
     */
    public static function required_scopes(string $method, bool $writetool = false): array {
        switch ($method) {
            case 'tools/list':
                return [self::TOOLS_READ, self::TOOLS_CALL];
            case 'tools/call':
                return $writetool ? [self::TOOLS_CALL] : [self::TOOLS_READ, self::TOOLS_CALL];
            case 'resources/read':
                return [self::RESOURCES_READ];
            case 'prompts/list':
            case 'prompts/get':
                return [self::PROMPTS_READ];
            default:
                return [];
        }
    }

    /**
     * Listing methods that answer with an empty or reduced list instead of a refusal.
     *
     * A client asks for the prompt list right after connecting; a 403 there would look like a lost
     * authorisation. Reading a prompt (`prompts/get`) stays refused.
     *
     * @param string $method MCP method.
     * @return bool
     */
    public static function is_soft_method(string $method): bool {
        return $method === 'prompts/list';
    }

    /**
     * Whether a token with the given scopes may make the request.
     *
     * @param string|null $tokenscope Space-separated scopes of the token; null for no limit.
     * @param string[] $needed Accepted scopes from {@see required_scopes()}.
     * @return bool
     */
    public static function allows(?string $tokenscope, array $needed): bool {
        if ($needed === [] || $tokenscope === null || !self::is_enforced()) {
            return true;
        }
        $granted = preg_split('/\s+/', trim($tokenscope), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_intersect($needed, $granted) !== [];
    }
}

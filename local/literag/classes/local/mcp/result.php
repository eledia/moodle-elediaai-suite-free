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

namespace local_literag\local\mcp;

/**
 * Builders for MCP tool results and JSON-RPC 2.0 envelopes.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class result {
    /**
     * Build an MCP tool result with both a text content part and structured content.
     *
     * @param string $text The human-readable text part (also the Markdown answer).
     * @param array $structured The structuredContent payload.
     * @param bool $iserror Whether this is a handled, user-facing error.
     * @return array MCP tool result.
     */
    public static function tool(string $text, array $structured, bool $iserror = false): array {
        return [
            'content' => [['type' => 'text', 'text' => $text]],
            'structuredContent' => $structured,
            'isError' => $iserror,
        ];
    }

    /**
     * Wrap a tool result into a successful JSON-RPC response envelope.
     *
     * @param mixed $id The request id to echo.
     * @param array $toolresult The MCP tool result.
     * @return array
     */
    public static function success($id, array $toolresult): array {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $toolresult];
    }

    /**
     * Build a JSON-RPC error envelope.
     *
     * @param mixed $id The request id to echo (null when unknown).
     * @param int $code JSON-RPC error code.
     * @param string $message Diagnostic message (server-side only).
     * @return array
     */
    public static function error($id, int $code, string $message): array {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }
}

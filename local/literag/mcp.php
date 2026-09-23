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
 * MCP / Tutor endpoint for block_elediaai_tutor.
 *
 * Speaks MCP over Streamable HTTP: one JSON-RPC 2.0 tools/call per request,
 * answered as a single application/json envelope. The block points its
 * "RAG server URL" setting at this script. Machine-to-machine: no session, no
 * CSRF; per-user identity travels in the validated moodle_token argument.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_DEBUG_DISPLAY', true);
define('WS_SERVER', true);

// phpcs:ignore moodle.Files.RequireLogin.Missing
require(__DIR__ . '/../../config.php');

use local_literag\local\config;
use local_literag\local\mcp\dispatcher;
use local_literag\local\mcp\result;

/**
 * Emit a JSON-RPC envelope and stop.
 *
 * @param array $envelope JSON-RPC response.
 * @param int $status HTTP status (default 200).
 * @return void
 */
function local_literag_mcp_respond(array $envelope, int $status = 200): void {
    if ($status !== 200) {
        header("HTTP/1.1 $status");
    }
    header('Content-Type: application/json; charset=utf-8');
    header('MCP-Protocol-Version: 2025-06-18');
    echo json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    die;
}

// Administratively disabled.
if (config::is_disabled()) {
    local_literag_mcp_respond(result::error(null, -32000, 'service disabled'), 503);
}

// Optional transport authorization (defence in depth; bearer token).
$expected = config::transport_auth_token();
if ($expected !== '') {
    $auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if ($auth === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $key => $value) {
            if (strcasecmp($key, 'Authorization') === 0) {
                $auth = (string) $value;
                break;
            }
        }
    }
    $presented = preg_replace('/^\s*Bearer\s+/i', '', $auth);
    if (!hash_equals($expected, (string) $presented)) {
        local_literag_mcp_respond(result::error(null, -32001, 'unauthorized'), 401);
    }
}

// Decode the JSON-RPC request body.
$raw = file_get_contents('php://input');
$request = json_decode((string) $raw, true);
if (!is_array($request)) {
    local_literag_mcp_respond(result::error(null, -32700, 'parse error'));
}

$envelope = (new dispatcher())->dispatch($request);
local_literag_mcp_respond($envelope);

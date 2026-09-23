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
 * Ingestion endpoint for local_elediaai_sources.
 *
 * Implements the RAG ingestion API v1.2 receiving side. The configured upsert
 * URL ends in "/documents/upsert" (slash arguments / PATH_INFO); the ingester
 * derives "/documents/delete" by suffix replacement, which routes back here.
 * Authenticated by the X-API-Key header. Machine-to-machine: no session, no CSRF.
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
use local_literag\local\document_store;
use local_literag\local\ingest_exception;

/**
 * Emit a JSON response with an explicit HTTP status and stop.
 *
 * @param int $status HTTP status code.
 * @param array $data Response body.
 * @return void
 */
function local_literag_ingest_respond(int $status, array $data): void {
    $messages = [
        200 => 'OK', 400 => 'Bad Request', 401 => 'Unauthorized',
        403 => 'Forbidden', 404 => 'Not Found', 500 => 'Internal Server Error',
        503 => 'Service Unavailable',
    ];
    $reason = $messages[$status] ?? 'Status';
    header("HTTP/1.1 $status $reason");
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    die;
}

/**
 * Read a request header value across SAPIs.
 *
 * @param string $name Header name (e.g. "X-API-Key").
 * @return string
 */
function local_literag_header(string $name): string {
    $server = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    if (isset($_SERVER[$server])) {
        return (string) $_SERVER[$server];
    }
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return (string) $value;
            }
        }
    }
    return '';
}

// Administratively disabled.
if (config::is_disabled()) {
    local_literag_ingest_respond(503, ['status' => 'disabled']);
}

// Authenticate with the shared ingestion key (constant-time compare).
$configuredkey = config::ingest_api_key();
if ($configuredkey === '') {
    // Not configured yet — let the ingester retry once the admin sets the key.
    local_literag_ingest_respond(503, ['status' => 'not_configured']);
}
$providedkey = local_literag_header('X-API-Key');
if ($providedkey === '' || !hash_equals($configuredkey, $providedkey)) {
    local_literag_ingest_respond(401, ['status' => 'unauthorized']);
}

// Route: the trailing path segment (or ?action=) selects health/upsert/delete.
$pathinfo = (string) ($_SERVER['PATH_INFO'] ?? '');
$action = '';
if (preg_match('/(health|upsert|delete)\/*$/', $pathinfo, $m)) {
    $action = $m[1];
} else {
    $requestedaction = optional_param('action', '', PARAM_ALPHA);
    if (in_array($requestedaction, ['health', 'upsert', 'delete'], true)) {
        $action = $requestedaction;
    }
}
if ($action === '') {
    local_literag_ingest_respond(404, ['status' => 'unknown_endpoint']);
}
if ($action === 'health') {
    local_literag_ingest_respond(200, ['status' => 'ok']);
}

// Decode the JSON body.
$raw = file_get_contents('php://input');
$payload = json_decode((string) $raw, true);
if (!is_array($payload)) {
    local_literag_ingest_respond(400, ['status' => 'invalid_json']);
}

$store = new document_store();
try {
    if ($action === 'upsert') {
        $store->upsert($payload);
    } else {
        $scope = (string) ($payload['scope'] ?? 'exact');
        $store->delete((string) ($payload['source_id'] ?? ''), $scope === 'prefix' ? 'prefix' : 'exact');
    }
    local_literag_ingest_respond(200, ['status' => 'ok']);
} catch (ingest_exception $e) {
    local_literag_ingest_respond($e->httpstatus, ['status' => 'error', 'detail' => $e->getMessage()]);
} catch (\Throwable $e) {
    debugging('local_literag ingest error: ' . $e->getMessage(), DEBUG_DEVELOPER);
    local_literag_ingest_respond(500, ['status' => 'error']);
}

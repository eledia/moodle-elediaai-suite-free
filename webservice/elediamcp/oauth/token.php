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
 * OAuth 2.1 token endpoint for the MCP authorization server.
 *
 * Exchanges an authorization code + PKCE verifier for a Moodle MCP access token.
 * Public endpoint (no cookies, no login): the code and PKCE binding are the proof
 * of authorisation. Errors are returned as RFC 6749 §5.2 JSON error objects.
 *
 * @package     webservice_elediamcp
 * @author      Sven (eLeDia) <dev@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_DEBUG_DISPLAY', true);
define('NO_MOODLE_COOKIES', true);
define('WS_SERVER', true);

// phpcs:ignore moodle.Files.RequireLogin.Missing
require(__DIR__ . '/../../../config.php');

use webservice_elediamcp\local\oauth\oauth_exception;
use webservice_elediamcp\local\oauth\service;
use webservice_elediamcp\local\security;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

$origin = isset($_SERVER['HTTP_ORIGIN']) ? (string) $_SERVER['HTTP_ORIGIN'] : null;
$cors = security::resolve_cors_origin($origin);
if ($cors !== null) {
    header('Access-Control-Allow-Origin: ' . $cors);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'OPTIONS') {
    http_response_code(204);
    die;
}
if ($method !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['error' => 'invalid_request', 'error_description' => 'POST required']);
    die;
}

if (!service::is_enabled()) {
    http_response_code(404);
    echo json_encode(['error' => 'oauth_disabled']);
    die;
}

/**
 * Collect token request parameters from a form-encoded or JSON body.
 *
 * @return array<string, string>
 */
function webservice_elediamcp_oauth_token_params(): array {
    $fields = ['grant_type', 'code', 'redirect_uri', 'client_id', 'code_verifier'];
    $params = [];
    foreach ($fields as $field) {
        $value = optional_param($field, '', PARAM_RAW);
        if ($value !== '') {
            $params[$field] = $value;
        }
    }
    if (!empty($params)) {
        return $params;
    }
    // Fall back to a JSON body for clients that post application/json.
    $raw = file_get_contents('php://input');
    if ($raw !== false && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            foreach ($fields as $field) {
                if (isset($decoded[$field]) && is_scalar($decoded[$field])) {
                    $params[$field] = (string) $decoded[$field];
                }
            }
        }
    }
    return $params;
}

try {
    $response = service::exchange_code(webservice_elediamcp_oauth_token_params());
    http_response_code(200);
    echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (oauth_exception $ex) {
    http_response_code($ex->get_http_status());
    if ($ex->get_error_code() === 'invalid_client') {
        header('WWW-Authenticate: Basic realm="Moodle MCP"');
    }
    echo json_encode([
        'error' => $ex->get_error_code(),
        'error_description' => $ex->get_description(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (\Throwable $ex) {
    debugging('MCP OAuth token endpoint error: ' . $ex->getMessage(), DEBUG_DEVELOPER);
    http_response_code(500);
    echo json_encode(['error' => 'server_error']);
}
die;

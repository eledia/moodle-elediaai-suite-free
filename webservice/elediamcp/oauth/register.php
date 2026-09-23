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
 * OAuth 2.0 Dynamic Client Registration endpoint (RFC 7591) for the MCP server.
 *
 * Lets a compliant MCP client register itself as a public (PKCE-only) client and
 * receive a client_id, so it can start the Authorization Code + PKCE flow without
 * a human pre-provisioning it. Registration issues no client secret.
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
    header('Access-Control-Allow-Headers: Content-Type');
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

if (!service::is_enabled() || !security::oauth_allow_dynamic_registration()) {
    http_response_code(404);
    echo json_encode(['error' => 'registration_disabled']);
    die;
}

$raw = file_get_contents('php://input');
$request = ($raw !== false && $raw !== '') ? json_decode($raw, true) : null;
if (!is_array($request)) {
    http_response_code(400);
    echo json_encode([
        'error' => 'invalid_client_metadata',
        'error_description' => get_string('oauth_err_invalid_registration_body', 'webservice_elediamcp'),
    ]);
    die;
}

try {
    $response = service::register_client($request);
    http_response_code(201);
    echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (oauth_exception $ex) {
    http_response_code($ex->get_http_status());
    echo json_encode([
        'error' => $ex->get_error_code(),
        'error_description' => $ex->get_description(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (\Throwable $ex) {
    debugging('MCP OAuth registration endpoint error: ' . $ex->getMessage(), DEBUG_DEVELOPER);
    http_response_code(500);
    echo json_encode(['error' => 'server_error']);
}
die;

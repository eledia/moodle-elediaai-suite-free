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
 * OAuth 2.0 Authorization Server Metadata document for the MCP server.
 *
 * Implements RFC 8414 so that MCP clients can discover the authorization,
 * token and (optional) registration endpoints for the OAuth 2.1 Authorization
 * Code + PKCE flow. Served only while the flow is enabled by the administrator;
 * otherwise the document is 404 so clients fall back to bearer-token onboarding.
 *
 * @package     webservice_elediamcp
 * @author      Sven (eLeDia) <dev@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_DEBUG_DISPLAY', true);
define('NO_MOODLE_COOKIES', true);

// phpcs:ignore moodle.Files.RequireLogin.Missing
require(__DIR__ . '/../../../config.php');

header('Content-Type: application/json; charset=utf-8');

use webservice_elediamcp\local\oauth\service;

if (!service::is_enabled()) {
    header('Cache-Control: no-store');
    http_response_code(404);
    echo json_encode(['error' => 'oauth_disabled']);
    die;
}

header('Cache-Control: public, max-age=300');

echo json_encode(
    service::authorization_server_metadata(),
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
);

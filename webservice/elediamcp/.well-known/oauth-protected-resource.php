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
 * OAuth 2.0 Protected Resource Metadata document for the MCP server.
 *
 * Implements RFC 9728 so that MCP clients (Claude Desktop, Cursor, Windsurf,
 * etc.) can perform zero-configuration discovery of the resource and its
 * associated authorisation server(s).
 *
 * The list of authorization servers is populated with the in-Moodle OAuth 2.1
 * Authorization Code + PKCE issuer once an administrator enables it (see
 * /.well-known/oauth-authorization-server); until then it stays empty and
 * clients onboard with a manually issued bearer token.
 *
 * @package     webservice_elediamcp
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_DEBUG_DISPLAY', true);
define('NO_MOODLE_COOKIES', true);

// phpcs:ignore moodle.Files.RequireLogin.Missing
require(__DIR__ . '/../../../config.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');

use webservice_elediamcp\local\oauth\service;

$resource = $CFG->wwwroot . '/webservice/elediamcp/server.php';

$authorizationservers = [];
if (service::is_enabled()) {
    $authorizationservers[] = service::issuer();
}

$metadata = [
    'resource' => $resource,
    'authorization_servers' => $authorizationservers,
    'bearer_methods_supported' => ['header'],
    'resource_documentation' => $CFG->wwwroot . '/webservice/elediamcp/configuration.php',
    'scopes_supported' => service::supported_scopes(),
    'token_introspection_endpoint' => null,
];

echo json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

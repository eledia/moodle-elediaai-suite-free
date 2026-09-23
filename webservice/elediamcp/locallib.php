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

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/webservice/lib.php");
// For the \curl wrapper used below; setup.php only loads it conditionally.
require_once("$CFG->libdir/filelib.php");

/**
 * MCP test client for automated testing.
 *
 * Implements the Moodle webservice_test_client_interface to provide
 * standardized testing capabilities for the MCP web service protocol.
 *
 * @package     webservice_elediamcp
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2025 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class webservice_elediamcp_test_client implements webservice_test_client_interface {
    /**
     * Execute a test web service request.
     *
     * This method implements the standard Moodle web service testing interface,
     * making JSON-RPC 2.0 requests to the MCP endpoint for automated testing.
     *
     * @param string $serverurl The server URL (including token parameter).
     * @param string $function The function name to call.
     * @param array $params The parameters of the called function.
     * @return mixed The decoded response from the server.
     */
    public function simpletest($serverurl, $function, $params): mixed {
        $request = [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => [
                'name' => $function,
                'arguments' => $params,
            ],
            'id' => 1,
        ];

        // Moodle's cURL wrapper, not raw cURL: it honours the site proxy
        // settings and the blocked-hosts / allowed-ports protection. Raw
        // curl_init() would let an admin-supplied URL reach hosts the site
        // deliberately blocks (SSRF).
        $curl = new \curl();
        $curl->setHeader('Content-Type: application/json');
        $response = $curl->post($serverurl, json_encode($request), [
            // The endpoint is a local web service; without a timeout a broken
            // endpoint would hang the test page indefinitely.
            'CURLOPT_TIMEOUT' => 30,
            'CURLOPT_CONNECTTIMEOUT' => 10,
        ]);

        if ($curl->get_errno()) {
            return ['error' => $curl->error];
        }

        return json_decode($response, true);
    }
}

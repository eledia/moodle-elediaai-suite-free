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

namespace local_literag\local\http;

/**
 * Moodle cURL-backed HTTP transport for outbound LLM calls.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class curl_transport implements transport {
    /** @var bool Whether to bypass Moodle's cURL security blocklist. */
    private bool $allowprivate;

    /**
     * Constructor.
     *
     * @param bool $allowprivate True to allow private/loopback hosts (self-hosted LiteLLM).
     */
    public function __construct(bool $allowprivate = false) {
        $this->allowprivate = $allowprivate;
    }

    /**
     * POST a body over HTTP and return the response.
     *
     * @param string $url
     * @param string[] $headers
     * @param string $body
     * @param int $timeout
     * @return array{status: int, body: string, error: string}
     */
    public function post(string $url, array $headers, string $body, int $timeout): array {
        global $CFG;
        // The \curl class lives in filelib.php, which is not auto-loaded in the
        // WS_SERVER / CLI bootstrap used by the entry scripts.
        require_once($CFG->libdir . '/filelib.php');

        $options = $this->allowprivate ? ['ignoresecurity' => true] : [];
        $curl = new \curl($options);
        $curl->setHeader($headers);

        $response = $curl->post($url, $body, [
            'CURLOPT_TIMEOUT' => $timeout,
            'CURLOPT_CONNECTTIMEOUT' => min(10, $timeout),
        ]);

        $info = $curl->get_info();
        $errno = $curl->get_errno();

        return [
            'status' => (int) ($info['http_code'] ?? 0),
            'body' => (string) $response,
            'error' => $errno ? (string) $curl->error : '',
        ];
    }
}

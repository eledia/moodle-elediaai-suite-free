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

namespace local_elediaai_sources;

/**
 * HTTP transport used by the destinations in {@see \local_elediaai_sources\sink\sink}.
 *
 * Carries retries, timeout handling, the `X-API-Key` header and the Docker
 * loopback rewrite — nothing else. Which URL a request goes to is decided by
 * the calling sink, which knows its own endpoints; the client no longer
 * derives one action URL from another and no longer reads an endpoint from
 * the plugin configuration.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api_client {
    /** @var int Maximum number of attempts for a failing request. */
    private const MAX_RETRIES = 2;

    /** @var string The API key sent as X-API-Key. */
    private string $apikey;

    /** @var int Request timeout in seconds. */
    private int $timeout;

    /** @var bool Whether private/internal targets may bypass Moodle cURL restrictions. */
    private bool $allowprivatetarget;

    /**
     * Constructor.
     *
     * @param string $apikey The key the destination authenticates with.
     * @param int|null $timeout Request timeout in seconds; plugin setting when null.
     * @param bool|null $allowprivatetarget Private-host opt-in; plugin setting when null.
     */
    public function __construct(string $apikey = '', ?int $timeout = null, ?bool $allowprivatetarget = null) {
        $this->apikey = $apikey;
        $this->timeout = $timeout ?? (int) (get_config('local_elediaai_sources', 'request_timeout_seconds') ?: 30);
        $this->allowprivatetarget = $allowprivatetarget
            ?? !empty(get_config('local_elediaai_sources', 'allow_private_target'));
    }

    /**
     * POST a JSON payload, retrying network and 5xx failures.
     *
     * @param string $url The absolute request URL.
     * @param array $payload The JSON payload.
     * @return array Result with keys 'success', 'http_code', 'response', 'error'.
     */
    public function post(string $url, array $payload): array {
        $target = $this->resolve_target($url);
        $jsonpayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $lastresult = [
            'success' => false,
            'http_code' => 0,
            'response' => '',
            'error' => '',
        ];

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            $curl = $this->create_curl();
            $curl->setHeader(array_merge([
                'Content-Type: application/json',
                'X-API-Key: ' . $this->apikey,
            ], $target['headers']));

            $response = $curl->post($target['url'], $jsonpayload, [
                'CURLOPT_TIMEOUT' => $this->timeout,
                'CURLOPT_CONNECTTIMEOUT' => min(10, $this->timeout),
            ]);

            $lastresult = $this->result_from($curl, $response);

            // Success — return immediately.
            if ($lastresult['success']) {
                return $lastresult;
            }

            // Do not retry client errors (4xx).
            if ($lastresult['http_code'] >= 400 && $lastresult['http_code'] < 500) {
                return $lastresult;
            }

            // Keep inline backoff short; Moodle task retry handles longer outages.
            if ($attempt < self::MAX_RETRIES) {
                sleep(1);
            }
        }

        debugging(
            "RAG API request to {$target['url']} failed after " . self::MAX_RETRIES
                . " attempts. HTTP {$lastresult['http_code']}: {$lastresult['error']}",
            DEBUG_DEVELOPER
        );

        return $lastresult;
    }

    /**
     * GET a URL once with short timeouts, for health probes.
     *
     * A health probe is answered or it is not; retrying it only delays the
     * settings page that shows the result.
     *
     * @param string $url The absolute request URL.
     * @return array Result with keys 'success', 'http_code', 'response', 'error'.
     */
    public function get(string $url): array {
        $target = $this->resolve_target($url);

        $curl = $this->create_curl();
        $curl->setHeader(array_merge([
            'Accept: application/json',
            'X-API-Key: ' . $this->apikey,
        ], $target['headers']));

        $response = $curl->get($target['url'], [], [
            'CURLOPT_TIMEOUT' => min(5, max(1, $this->timeout)),
            'CURLOPT_CONNECTTIMEOUT' => min(3, max(1, $this->timeout)),
        ]);

        return $this->result_from($curl, $response);
    }

    /**
     * Build the common result row from a finished cURL call.
     *
     * @param \curl $curl The finished cURL wrapper.
     * @param string|bool $response The raw response body.
     * @return array Result with keys 'success', 'http_code', 'response', 'error'.
     */
    private function result_from(\curl $curl, $response): array {
        $info = $curl->get_info();
        $httpcode = (int) ($info['http_code'] ?? 0);
        $errno = $curl->get_errno();

        return [
            'success' => ($httpcode >= 200 && $httpcode < 300),
            'http_code' => $httpcode,
            'response' => is_string($response) ? $response : '',
            'error' => $errno ? $curl->error : '',
        ];
    }

    /**
     * Create the Moodle cURL wrapper.
     *
     * Some installations use an internal Docker/Kubernetes service name such
     * as `rag-service:8001`. Moodle blocks private hosts and non-standard ports
     * by default, so bypassing that protection is an explicit admin opt-in.
     *
     * @return \curl
     */
    private function create_curl(): \curl {
        global $CFG;

        require_once($CFG->libdir . '/filelib.php');

        return new \curl($this->allowprivatetarget ? ['ignoresecurity' => true] : []);
    }

    /**
     * Route local Docker callbacks through the host while preserving Moodle's public host.
     *
     * In local Docker setups Moodle's public wwwroot is often localhost:8080.
     * From inside the PHP container that address points at the container
     * itself, so calls to an in-Moodle destination must go through
     * host.docker.internal while keeping Moodle's configured Host header.
     *
     * The rewrite never bypasses Moodle's cURL blocklist by itself: reaching a
     * private host remains an explicit admin opt-in via allow_private_target
     * ({@see create_curl()}).
     *
     * @param string $url The URL the sink asked for.
     * @return array Keys 'url' (possibly rewritten) and 'headers' (extra headers).
     */
    private function resolve_target(string $url): array {
        global $CFG;

        $unchanged = ['url' => $url, 'headers' => []];
        if ($url === '') {
            return $unchanged;
        }

        $wwwroot = rtrim((string) $CFG->wwwroot, '/');
        if (!str_starts_with($url, $wwwroot . '/')) {
            return $unchanged;
        }

        $parts = parse_url($wwwroot);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (!in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return $unchanged;
        }

        $scheme = (string) ($parts['scheme'] ?? 'http');
        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
        $path = rtrim((string) ($parts['path'] ?? ''), '/');
        $suffix = substr($url, strlen($wwwroot));

        return [
            'url' => $scheme . '://host.docker.internal' . $port . $path . $suffix,
            'headers' => ['Host: ' . $host . $port],
        ];
    }
}

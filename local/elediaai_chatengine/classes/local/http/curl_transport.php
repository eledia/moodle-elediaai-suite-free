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

namespace local_elediaai_chatengine\local\http;

use curl;

/**
 * Default {@see transport} backed by Moodle's cURL wrapper.
 *
 * Using {@see curl} (rather than raw cURL) means the site's cURL security
 * helper applies — blocked hosts, internal ranges and disallowed ports are
 * rejected centrally, providing defence in depth against SSRF on top of the
 * scheme/host validation in {@see \local_elediaai_chatengine\local\connection}.
 *
 * @package    local_elediaai_chatengine
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class curl_transport implements transport {
    /** @var bool Whether to bypass the site cURL security helper (dev/internal only). */
    private bool $ignoresecurity;

    /**
     * Constructor.
     *
     * @param bool $ignoresecurity When true, the site cURL security helper (blocked
     *                             hosts/ports) is bypassed for the configured RAG host.
     *                             Off by default; only enabled by an explicit admin opt-in
     *                             for internal-network or local-development RAG servers.
     */
    public function __construct(bool $ignoresecurity = false) {
        $this->ignoresecurity = $ignoresecurity;
    }

    /**
     * {@inheritDoc}
     *
     * @param string $url Absolute request URL.
     * @param string[] $headers Request headers as "Name: value" strings.
     * @param string $body Raw request body.
     * @param int $timeout Timeout in seconds.
     * @return array{status: int, headers: array<string,string>, body: string, error: string}
     */
    public function post(string $url, array $headers, string $body, int $timeout): array {
        global $CFG;
        // The curl wrapper lives in filelib, which is not preloaded in CLI /
        // scheduled-task contexts.
        require_once($CFG->libdir . '/filelib.php');

        $curl = new curl($this->ignoresecurity ? ['ignoresecurity' => true] : []);
        $options = [
            'CURLOPT_TIMEOUT' => $timeout,
            'CURLOPT_CONNECTTIMEOUT' => min($timeout, 10),
            'CURLOPT_FOLLOWLOCATION' => 0,
            'CURLOPT_HTTPHEADER' => $headers,
        ];

        $response = $curl->post($url, $body, $options);
        $info = $curl->get_info();
        $errno = $curl->get_errno();
        $error = $curl->error ?? '';

        return [
            'status' => (int) ($info['http_code'] ?? 0),
            'headers' => $curl->getResponse() ?: [],
            'body' => is_string($response) ? $response : '',
            'error' => $errno ? (string) $error : '',
        ];
    }

    /**
     * POST and hand every received chunk to a callback as it arrives.
     *
     * Uses CURLOPT_WRITEFUNCTION so cURL passes each chunk straight through
     * instead of collecting the body first — that is what makes incremental
     * rendering possible at all. The chunks are additionally concatenated and
     * returned, so the caller can still parse the closing frame from the full
     * body.
     *
     * @param string $url Absolute request URL.
     * @param string[] $headers Request headers as "Name: value" strings.
     * @param string $body Raw request body.
     * @param int $timeout Timeout in seconds.
     * @param callable(string): void $onchunk Called with each raw chunk as it arrives.
     * @return array{status: int, headers: array<string,string>, body: string, error: string}
     */
    public function post_streaming(
        string $url,
        array $headers,
        string $body,
        int $timeout,
        callable $onchunk
    ): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $collected = '';
        $curl = new curl($this->ignoresecurity ? ['ignoresecurity' => true] : []);
        $options = [
            'CURLOPT_TIMEOUT' => $timeout,
            'CURLOPT_CONNECTTIMEOUT' => min($timeout, 10),
            'CURLOPT_FOLLOWLOCATION' => 0,
            'CURLOPT_HTTPHEADER' => $headers,
            // Without this cURL buffers the whole response before returning.
            'CURLOPT_WRITEFUNCTION' => function ($ch, string $chunk) use (&$collected, $onchunk): int {
                $collected .= $chunk;
                $onchunk($chunk);
                // Confirm every byte, or cURL aborts the transfer.
                return strlen($chunk);
            },
        ];

        $curl->post($url, $body, $options);
        $info = $curl->get_info();
        $errno = $curl->get_errno();
        $error = $curl->error ?? '';

        return [
            'status' => (int) ($info['http_code'] ?? 0),
            'headers' => $curl->getResponse() ?: [],
            'body' => $collected,
            'error' => $errno ? (string) $error : '',
        ];
    }
}

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

/**
 * Minimal HTTP POST transport abstraction.
 *
 * The RAG client depends on this interface rather than on cURL directly so the
 * external RAG/Tutor server can be faked in unit tests without any network I/O.
 *
 * @package    local_elediaai_chatengine
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface transport {
    /**
     * Issue an HTTP POST and return the raw response.
     *
     * @param string $url Absolute request URL.
     * @param string[] $headers Request headers as "Name: value" strings.
     * @param string $body Raw request body.
     * @param int $timeout Timeout in seconds.
     * @return array{status: int, headers: array<string,string>, body: string, error: string}
     */
    public function post(string $url, array $headers, string $body, int $timeout): array;

    /**
     * POST and hand every received chunk to a callback as it arrives.
     *
     * Unlike {@see self::post()} this must not buffer the whole body before
     * returning: the caller renders partial answers from the chunks. The full
     * body is still returned so the caller can parse the closing frame.
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
    ): array;
}

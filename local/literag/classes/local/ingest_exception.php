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

namespace local_literag\local;

/**
 * A client error during ingestion, carrying the HTTP status to return.
 *
 * Used to drive local_elediaai_sources's retry behaviour: 4xx are logged with no retry,
 * 5xx (any other Throwable) trigger client-side retries.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ingest_exception extends \Exception {
    /** @var int HTTP status code to return to the ingester. */
    public int $httpstatus;

    /**
     * Constructor.
     *
     * @param string $message Diagnostic message (server-side only).
     * @param int $httpstatus HTTP status to return (default 400).
     */
    public function __construct(string $message, int $httpstatus = 400) {
        parent::__construct($message);
        $this->httpstatus = $httpstatus;
    }
}

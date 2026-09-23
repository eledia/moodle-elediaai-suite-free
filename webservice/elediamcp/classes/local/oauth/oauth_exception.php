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

namespace webservice_elediamcp\local\oauth;

use RuntimeException;

/**
 * A recoverable OAuth 2.1 protocol error.
 *
 * Carries the RFC 6749 / RFC 7591 error code, an optional human-readable
 * description, and the HTTP status the endpoint should return. The message is
 * always safe to expose to the client: it never contains internal detail or
 * secrets. Whether the authorization endpoint may redirect the error back to
 * the client (only once the client and redirect URI are known-good) is tracked
 * separately by {@see self::$redirectable}.
 *
 * @package     webservice_elediamcp
 * @author      Sven (eLeDia) <dev@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class oauth_exception extends RuntimeException {
    /** @var string RFC error code (e.g. invalid_request, invalid_grant, invalid_client). */
    protected string $errorcode;

    /** @var string Safe, human-readable description. */
    protected string $description;

    /** @var int HTTP status code the endpoint should return. */
    protected int $httpstatus;

    /** @var bool Whether the error may be delivered by redirecting to the client. */
    protected bool $redirectable;

    /**
     * Constructor.
     *
     * @param string $errorcode RFC error code.
     * @param string $description Safe, human-readable description.
     * @param int $httpstatus HTTP status to return (defaults to 400).
     * @param bool $redirectable Whether the error may be redirected to the client.
     */
    public function __construct(
        string $errorcode,
        string $description = '',
        int $httpstatus = 400,
        bool $redirectable = true
    ) {
        $this->errorcode = $errorcode;
        $this->description = $description;
        $this->httpstatus = $httpstatus;
        $this->redirectable = $redirectable;
        parent::__construct($description !== '' ? $description : $errorcode);
    }

    /**
     * The RFC error code.
     *
     * @return string
     */
    public function get_error_code(): string {
        return $this->errorcode;
    }

    /**
     * The safe, human-readable description.
     *
     * @return string
     */
    public function get_description(): string {
        return $this->description;
    }

    /**
     * The HTTP status the endpoint should return.
     *
     * @return int
     */
    public function get_http_status(): int {
        return $this->httpstatus;
    }

    /**
     * Whether the error may be delivered by redirecting to the client.
     *
     * @return bool
     */
    public function is_redirectable(): bool {
        return $this->redirectable;
    }
}

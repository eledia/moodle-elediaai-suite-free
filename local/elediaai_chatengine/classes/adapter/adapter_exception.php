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

namespace local_elediaai_chatengine\adapter;

/**
 * A backend could not answer.
 *
 * Raised rather than swallowed: the engine never falls back to a different
 * backend when the configured one fails. A silent switch would hand the user
 * a different model past the quota ledger and past the audit trail, and they
 * would have no way of telling.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class adapter_exception extends \moodle_exception {
    /** @var int Transport-level HTTP status, or 0 when there was none. */
    protected int $httpstatus;

    /**
     * Constructor.
     *
     * @param string $errorcode Language string identifier in local_elediaai_chatengine.
     * @param string|null $debuginfo Non-sensitive detail for administrators; never a credential.
     * @param int $httpstatus Transport-level HTTP status, or 0.
     * @param mixed $a Language string parameter.
     */
    public function __construct(
        string $errorcode = 'error_backend_unavailable',
        ?string $debuginfo = null,
        int $httpstatus = 0,
        mixed $a = null
    ) {
        $this->httpstatus = $httpstatus;
        parent::__construct($errorcode, 'local_elediaai_chatengine', '', $a, $debuginfo);
    }

    /**
     * The transport status, when the failure had one.
     *
     * @return int HTTP status, or 0.
     */
    public function http_status(): int {
        return $this->httpstatus;
    }

    /**
     * Whether the backend rejected the credential.
     *
     * The one failure worth retrying: a cached user token may have been
     * revoked or expired, and a fresh one cures it. Every other failure —
     * timeout, 5xx, malformed answer — would only send the same turn twice.
     *
     * @return bool True on 401 or 403.
     */
    public function is_auth_failure(): bool {
        return $this->httpstatus === 401 || $this->httpstatus === 403;
    }
}

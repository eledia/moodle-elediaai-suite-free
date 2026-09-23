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

namespace local_literag\local\mcp;

/**
 * A protocol-level tool failure, surfaced to the client as a JSON-RPC error.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_exception extends \Exception {
    /** @var int JSON-RPC error code. */
    public int $rpccode;

    /**
     * Constructor.
     *
     * @param string $message Diagnostic message (server-side only).
     * @param int $rpccode JSON-RPC error code (default -32000).
     */
    public function __construct(string $message, int $rpccode = -32000) {
        parent::__construct($message);
        $this->rpccode = $rpccode;
    }
}

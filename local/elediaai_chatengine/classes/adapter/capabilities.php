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
 * What a backend can do, asked before it is offered.
 *
 * Reported rather than assumed: whether a later backend such as OERWEAVE
 * answers without retrieval is genuinely unknown today, and the settings form
 * must be able to hide the option instead of promising it.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class capabilities {
    /**
     * Constructor.
     *
     * @param bool $supportsungrounded Whether the backend can answer without retrieval.
     * @param bool $supportstools Whether the backend may call back into Moodle through the MCP connector.
     * @param bool $supportsstreaming Whether the backend emits answer fragments as they are produced.
     * @param bool $stateful Whether the backend keeps its own transcript and issues a conversation id.
     */
    public function __construct(
        /** @var bool Whether the backend can answer without retrieval. */
        public readonly bool $supportsungrounded = false,
        /** @var bool Whether the backend may call back into Moodle. */
        public readonly bool $supportstools = false,
        /** @var bool Whether the backend streams fragments. */
        public readonly bool $supportsstreaming = false,
        /** @var bool Whether the backend keeps its own transcript. */
        public readonly bool $stateful = false,
    ) {
    }
}

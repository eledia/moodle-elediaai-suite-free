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

namespace local_literag\local\mcp\tools;

use local_literag\local\memory_store;
use local_literag\local\mcp\result;
use local_literag\local\mcp\tool;
use local_literag\local\mcp\tool_exception;
use local_literag\local\token_validator;

/**
 * Optional tutor_set_memory_optin tool: record consent; erase on opt-out.
 *
 * Consent is authoritative per-request via tutor_chat's ltm_enabled; this tool's
 * job is the erase-on-revoke side effect.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tutor_set_memory_optin implements tool {
    /**
     * Record long-term memory consent, erasing stored memory on opt-out.
     *
     * @param array $arguments
     * @return array
     * @throws tool_exception
     */
    public function handle(array $arguments): array {
        $user = token_validator::resolve_user((string) ($arguments['moodle_token'] ?? ''));
        if ($user === null) {
            throw new tool_exception('invalid moodle_token', -32001);
        }
        $enabled = (bool) ($arguments['enabled'] ?? false);

        $deleted = 0;
        if (!$enabled) {
            // Opt-out = erasure of everything already stored.
            $deleted = (new memory_store())->delete_all_for_user((int) $user->id);
        }

        return result::tool('', [
            'accepted' => true,
            'enabled' => $enabled,
            'memories_deleted' => $deleted,
        ], false);
    }
}

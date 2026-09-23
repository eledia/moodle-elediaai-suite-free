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

use local_literag\local\conversation_repository;
use local_literag\local\mcp\result;
use local_literag\local\mcp\tool;
use local_literag\local\mcp\tool_exception;
use local_literag\local\memory_store;
use local_literag\local\token_validator;
use local_literag\local\user_eraser;

/**
 * Optional tutor_delete_user_data tool: erase ALL data held for the user.
 *
 * Complete by definition: every conversation/transcript, all long-term memory,
 * and the user's query-log rows — even conversations Moodle no longer points to.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tutor_delete_user_data implements tool {
    /**
     * Erase all data held for the authenticated user.
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
        $counts = user_eraser::erase((int) $user->id);

        return result::tool('', [
            'deleted' => true,
            'conversations_deleted' => $counts['conversations'],
            'memories_deleted' => $counts['memories'],
        ], false);
    }
}

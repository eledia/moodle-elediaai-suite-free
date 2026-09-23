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
use local_literag\local\token_validator;

/**
 * Optional tutor_get_history tool: return a conversation's messages.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tutor_get_history implements tool {
    /**
     * Return the messages of one of the authenticated user's conversations.
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
        $convkey = trim((string) ($arguments['conversation_id'] ?? ''));

        $repo = new conversation_repository();
        $conversation = $repo->find_owned($convkey, (int) $user->id);

        $messages = [];
        if ($conversation !== null) {
            $messages = $repo->recent_messages($conversation, 200);
        }

        return result::tool('', ['messages' => $messages], false);
    }
}

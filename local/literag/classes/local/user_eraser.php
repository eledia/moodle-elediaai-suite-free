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
 * Complete erasure of a user's tutor data.
 *
 * Single source of truth shared by the tutor_delete_user_data tool and the
 * privacy (GDPR) provider so both delete exactly the same rows.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_eraser {
    /**
     * Erase every conversation, message, memory and query-log row for a user.
     *
     * @param int $userid
     * @return array{conversations: int, memories: int} Deletion counts.
     */
    public static function erase(int $userid): array {
        global $DB;

        $conversations = (new conversation_repository())->delete_all_for_user($userid);
        $memories = (new memory_store())->delete_all_for_user($userid);
        $DB->delete_records('local_literag_query_log', ['userid' => $userid]);

        return ['conversations' => $conversations, 'memories' => $memories];
    }
}

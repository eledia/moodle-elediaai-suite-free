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
 * Persists tutor conversations and messages, keyed by a public conversation id.
 *
 * Ownership is always enforced: a conversation is only ever loaded, continued,
 * read or deleted for the user who owns it.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class conversation_repository {
    /** @var int How many prior messages to feed back as context. */
    private const HISTORY_LIMIT = 20;

    /**
     * Find a conversation by its public key, scoped to its owner.
     *
     * @param string $convkey Public conversation id.
     * @param int $userid Owner.
     * @return \stdClass|null The conversation row, or null when not found / not owned.
     */
    public function find_owned(string $convkey, int $userid): ?\stdClass {
        global $DB;
        $convkey = trim($convkey);
        if ($convkey === '') {
            return null;
        }
        $row = $DB->get_record('local_literag_conversations', ['convkey' => $convkey, 'userid' => $userid]);
        return $row ?: null;
    }

    /**
     * Create a new conversation and return its row.
     *
     * @param int $userid Owner.
     * @param int $courseid Course context (0 for global).
     * @param string $answerstyle Pedagogical style for this turn.
     * @return \stdClass The created conversation row (with ->convkey).
     */
    public function create(int $userid, int $courseid, string $answerstyle): \stdClass {
        global $DB;
        $now = time();
        $record = (object) [
            'userid' => $userid,
            'courseid' => $courseid,
            'tenant' => tenant::id(),
            'lastanswerstyle' => $answerstyle,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $record->convkey = 'conv-' . random_string(24);
                $record->id = $DB->insert_record('local_literag_conversations', $record);
                return $record;
            } catch (\dml_exception $e) {
                if ($attempt === 2) {
                    throw $e;
                }
            }
        }
        throw new \coding_exception('Could not generate unique conversation key');
    }

    /**
     * Touch a conversation's modified time (and last style).
     *
     * @param \stdClass $conversation
     * @param string $answerstyle
     * @return void
     */
    public function touch(\stdClass $conversation, string $answerstyle): void {
        global $DB;
        $DB->update_record('local_literag_conversations', (object) [
            'id' => $conversation->id,
            'lastanswerstyle' => $answerstyle,
            'timemodified' => time(),
        ]);
    }

    /**
     * Store (or clear) the conversation's pending write action awaiting confirmation.
     *
     * @param \stdClass $conversation
     * @param array|null $action The action to persist as JSON, or null to clear.
     * @return void
     */
    public function set_pending_action(\stdClass $conversation, ?array $action): void {
        global $DB;
        $json = $action === null ? null : json_encode($action, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $DB->set_field('local_literag_conversations', 'pendingaction', $json, ['id' => $conversation->id]);
        $conversation->pendingaction = $json;
    }

    /**
     * Get the conversation's pending write action, or null when none is set.
     *
     * @param \stdClass $conversation
     * @return array|null
     */
    public function get_pending_action(\stdClass $conversation): ?array {
        if (empty($conversation->pendingaction)) {
            return null;
        }
        $decoded = json_decode((string) $conversation->pendingaction, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Append a message to a conversation.
     *
     * @param \stdClass $conversation
     * @param string $role user|assistant.
     * @param string $content Message text.
     * @param string|null $topic Canonical topic label.
     * @param int $primarycmid Primary source cmid for analytics.
     * @param array|null $sources Source rows ({title,url,snippet}) for an assistant message.
     * @return void
     */
    public function add_message(
        \stdClass $conversation,
        string $role,
        string $content,
        ?string $topic = null,
        int $primarycmid = 0,
        ?array $sources = null
    ): void {
        global $DB;
        $DB->insert_record('local_literag_messages', (object) [
            'conversationid' => $conversation->id,
            'userid' => $conversation->userid,
            'role' => $role === 'user' ? 'user' : 'assistant',
            'content' => $content,
            'sourcesjson' => empty($sources) ? null : json_encode(array_values($sources)),
            'topic' => $topic,
            'primarycmid' => $primarycmid,
            'timecreated' => time(),
        ]);
    }

    /**
     * Load recent messages of a conversation as [{role, content, sources}, ...], oldest first.
     *
     * @param \stdClass $conversation
     * @param int|null $limit Maximum messages (defaults to the history window).
     * @return array
     */
    public function recent_messages(\stdClass $conversation, ?int $limit = null): array {
        global $DB;
        $limit = $limit ?? self::HISTORY_LIMIT;
        $rows = $DB->get_records(
            'local_literag_messages',
            ['conversationid' => $conversation->id],
            'timecreated DESC, id DESC',
            'id, role, content, sourcesjson',
            0,
            $limit
        );
        $rows = array_reverse($rows);
        $messages = [];
        foreach ($rows as $row) {
            $sources = [];
            if (!empty($row->sourcesjson)) {
                $decoded = json_decode($row->sourcesjson, true);
                if (is_array($decoded)) {
                    $sources = $decoded;
                }
            }
            $messages[] = ['role' => $row->role, 'content' => (string) $row->content, 'sources' => $sources];
        }
        return $messages;
    }

    /**
     * Delete a conversation and its messages (owner-scoped).
     *
     * @param string $convkey
     * @param int $userid
     * @return bool True when something was deleted.
     */
    public function delete_owned(string $convkey, int $userid): bool {
        global $DB;
        $conversation = $this->find_owned($convkey, $userid);
        if ($conversation === null) {
            return false;
        }
        $DB->delete_records('local_literag_messages', ['conversationid' => $conversation->id]);
        $DB->delete_records('local_literag_conversations', ['id' => $conversation->id]);
        return true;
    }

    /**
     * Delete ALL conversations and messages for a user.
     *
     * @param int $userid
     * @return int Number of conversations deleted.
     */
    public function delete_all_for_user(int $userid): int {
        global $DB;
        $count = $DB->count_records('local_literag_conversations', ['userid' => $userid]);
        $DB->delete_records('local_literag_messages', ['userid' => $userid]);
        $DB->delete_records('local_literag_conversations', ['userid' => $userid]);
        return $count;
    }
}

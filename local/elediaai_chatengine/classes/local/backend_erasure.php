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

namespace local_elediaai_chatengine\local;

use local_elediaai_chatengine\adapter\adapter;
use local_elediaai_chatengine\backend_resolver;

/**
 * Carries a privacy deletion over to the active backend.
 *
 * The backend keeps its own transcripts and long-term memory, and the
 * deletion in Moodle never reached them unless the tutor block happened to be
 * involved (ai_suite#250). This is the engine's own way there, for every
 * placement, in two widths (rag_server_spec A.3, A.4):
 *
 *  - {@see self::erase_users()} asks for everything the backend holds about a
 *    person (tutor_delete_user_data, key "deleteuser"). Only for a complete
 *    deletion of that person: it takes the long-term memory and transcripts
 *    from every course with it.
 *  - {@see self::erase_conversations()} deletes single conversations
 *    (tutor_delete_conversation, key "delete"). For everything narrower, such
 *    as an expired course: the rest of the person's data stays.
 *
 * Best-effort by design. A privacy deletion must never fail because a
 * service elsewhere is down, refuses the credential or has no such tool:
 * every failure is logged and counted, nothing is thrown. The caller deletes
 * the Moodle data regardless.
 *
 * Every call needs a callback token of the person. The adapters mint it for
 * the person, not for whoever runs the deletion, so a request processed by
 * tool_dataprivacy as a scheduled task works as long as the account is still
 * active. For a deleted, unconfirmed or guest account Moodle refuses the
 * token; that is a logged failure like any other. The tokens minted for the
 * calls are revoked straight afterwards.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class backend_erasure {
    /** @var string Logical key of the person-wide erasure tool, see connection::TOOLS. */
    public const TOOL = 'deleteuser';

    /** @var string Logical key of the per-conversation deletion tool. */
    public const CONVERSATION_TOOL = 'delete';

    /**
     * Erase everything the backend holds for each person, never throwing.
     *
     * @param int[] $userids The people to erase.
     * @param adapter|null $adapter The backend to ask; the active one when null.
     * @return array{requested: int, erased: int, failed: int} What happened.
     */
    public static function erase_users(array $userids, ?adapter $adapter = null): array {
        $userids = array_values(array_unique(array_filter(array_map('intval', $userids), static fn(int $id): bool => $id > 0)));
        $calls = array_map(static fn(int $userid): array => [$userid, []], $userids);

        return self::run(self::TOOL, $calls, $adapter);
    }

    /**
     * Delete single conversations at the backend, never throwing.
     *
     * Only conversations created against the backend that is asked now are
     * sent: a conversation id belongs to the service that issued it, and a
     * site that switched destination cannot delete there any more.
     *
     * @param \stdClass[] $threads Thread records with userid, backend and convkey.
     * @param adapter|null $adapter The backend to ask; the active one when null.
     * @return array{requested: int, erased: int, failed: int} What happened.
     */
    public static function erase_conversations(array $threads, ?adapter $adapter = null): array {
        $empty = ['requested' => 0, 'erased' => 0, 'failed' => 0];
        $threads = array_filter(
            $threads,
            static fn(\stdClass $thread): bool => (int) $thread->userid > 0 && trim((string) $thread->convkey) !== ''
        );
        if ($threads === []) {
            return $empty;
        }
        $adapter = self::resolve(self::CONVERSATION_TOOL, $adapter, count($threads));
        if (!$adapter instanceof adapter) {
            return $adapter ?? $empty;
        }

        $calls = [];
        $seen = [];
        foreach ($threads as $thread) {
            if ((string) $thread->backend !== $adapter::id()) {
                continue;
            }
            $key = (int) $thread->userid . '|' . $thread->convkey;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $calls[] = [(int) $thread->userid, ['conversation_id' => (string) $thread->convkey]];
        }

        return self::run(self::CONVERSATION_TOOL, $calls, $adapter);
    }

    /**
     * The backend to ask, or the result to return instead.
     *
     * @param string $tool The logical tool key, which is also its switch.
     * @param adapter|null $adapter An injected backend, if any.
     * @param int $pending How many calls would be made, counted as failed when resolving fails.
     * @return adapter|array|null The backend, a failure result, or null when nothing is to be done.
     */
    private static function resolve(string $tool, ?adapter $adapter, int $pending): adapter|array|null {
        try {
            // Switched off means "delete on this site only", as the setting says.
            if (!connection::tool_enabled($tool)) {
                return null;
            }
            // No backend configured: nothing outside this site to erase.
            return $adapter ?? backend_resolver::active();
        } catch (\Throwable $e) {
            self::log('backend could not be resolved: ' . $e->getMessage());
            return ['requested' => 0, 'erased' => 0, 'failed' => $pending];
        }
    }

    /**
     * Make the calls one by one, then revoke the tokens they minted.
     *
     * @param string $tool The logical tool key.
     * @param array $calls Pairs of [userid, arguments].
     * @param adapter|null $adapter The backend, or null to resolve it.
     * @return array{requested: int, erased: int, failed: int} What happened.
     */
    private static function run(string $tool, array $calls, ?adapter $adapter): array {
        $result = ['requested' => 0, 'erased' => 0, 'failed' => 0];
        if ($calls === []) {
            return $result;
        }
        $adapter = self::resolve($tool, $adapter, count($calls));
        if (!$adapter instanceof adapter) {
            return $adapter ?? $result;
        }

        $users = [];
        foreach ($calls as [$userid, $arguments]) {
            $result['requested']++;
            $users[$userid] = true;
            try {
                $adapter->call_tool($tool, $arguments, $userid);
                $result['erased']++;
            } catch (\Throwable $e) {
                $result['failed']++;
                self::log($tool . ' for user ' . $userid . ' failed: ' . $e->getMessage());
            }
        }

        // The calls may have minted a token for each person. It should not
        // outlive the deletion it was made for.
        foreach (array_keys($users) as $userid) {
            try {
                token_provider::revoke_for_user((int) $userid);
            } catch (\Throwable $e) {
                self::log('token revocation for user ' . $userid . ' failed: ' . $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * Log a failure where an administrator debugging the site will find it.
     *
     * @param string $message What went wrong; never a credential.
     * @return void
     */
    private static function log(string $message): void {
        debugging('local_elediaai_chatengine: backend erasure: ' . $message, DEBUG_DEVELOPER);
    }
}

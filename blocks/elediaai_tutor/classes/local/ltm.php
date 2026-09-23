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

namespace block_elediaai_tutor\local;

use local_elediaai_chatengine\adapter\adapter;
use local_elediaai_chatengine\backend_resolver;

/**
 * Long-term memory (LTM) opt-in handling.
 *
 * LTM lets the tutor remember helpful facts across conversations. It is an
 * explicit per-user opt-in stored as a Moodle user preference and is DISABLED
 * by default. Moodle itself never stores or transmits memory CONTENT — only
 * the consent boolean is communicated to the RAG server, and only when the
 * admin has configured the memory opt-in tool (declaring the server
 * memory-capable). Consent reaches the server through two complementary
 * channels: {@see self::sync_to_rag()} pushes toggle changes immediately (the
 * server erases stored memories on opt-out), and every chat call carries the
 * current state as the ltm_enabled argument so the server's behaviour is
 * correct per-request even if a sync was missed.
 *
 * A user preference (rather than a DB table) is used because this is a single
 * boolean per user with no relational or audit requirements of its own; the
 * preference is declared and exported through the Privacy API, and changes are
 * recorded via the ltm_preference_changed event.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ltm {
    /** @var string Name of the user preference holding the opt-in flag. */
    public const PREF = 'block_elediaai_tutor_ltm_enabled';

    /**
     * Whether the user has opted in to long-term memory.
     *
     * @param int $userid The user id.
     * @return bool False unless the user has explicitly opted in.
     */
    public static function is_enabled(int $userid): bool {
        return (bool) get_user_preferences(self::PREF, 0, $userid);
    }

    /**
     * Store the user's long-term memory opt-in choice.
     *
     * @param int $userid The user id.
     * @param bool $enabled True to opt in, false to opt out.
     * @return void
     */
    public static function set_enabled(int $userid, bool $enabled): void {
        set_user_preference(self::PREF, $enabled ? 1 : 0, $userid);
    }

    /**
     * Erase a user's long-term memory preference.
     *
     * @param int $userid The user id.
     * @return int One when a stored preference was deleted, otherwise zero.
     */
    public static function delete_for_user(int $userid): int {
        if (get_user_preferences(self::PREF, null, $userid) === null) {
            return 0;
        }
        unset_user_preference(self::PREF, $userid);
        return 1;
    }

    /**
     * Synchronise the user's consent state to the RAG server.
     *
     * Only acts when the admin has configured a memory opt-in tool (declaring
     * the RAG server memory-capable); otherwise it is a no-op and no consent
     * data leaves Moodle. The sync is best-effort: a failure is recorded via a
     * rag_request_failed event but never blocks the preference change — every
     * chat call independently carries the current consent as ltm_enabled, so
     * the server's behaviour stays correct even when this call is missed. The
     * server side is contractually required to erase stored memories when it
     * receives enabled=false.
     *
     * Note: only the consent BOOLEAN is transmitted, never memory content —
     * Moodle holds none.
     *
     * @param int $userid The user id whose consent state is synced.
     * @param \context $context Context for the failure audit event.
     * @param adapter|null $adapter Optional injected backend (tests).
     * @return bool True when the server acknowledged the sync.
     */
    public static function sync_to_rag(int $userid, \context $context, ?adapter $adapter = null): bool {
        if (!consent::has_consented($userid)) {
            return false;
        }

        $adapter ??= backend_resolver::active();
        if ($adapter === null) {
            // No backend to tell, so there is nothing to communicate. Whether
            // it supports memory at all is its own answer, given when asked.
            return false;
        }

        try {
            $adapter->call_tool('memoryoptin', ['enabled' => self::is_enabled($userid)], $userid);
            return true;
        } catch (\moodle_exception $e) {
            \block_elediaai_tutor\event\rag_request_failed::create([
                'context' => $context,
                'userid' => $userid,
                'other' => ['reason' => 'memory_optin'],
            ])->trigger();
            debugging(
                'block_elediaai_tutor: memory opt-in sync failed: ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            return false;
        }
    }
}

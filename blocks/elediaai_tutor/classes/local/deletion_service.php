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

use context;
use local_elediaai_chatengine\adapter\adapter;
use local_elediaai_chatengine\adapter\adapter_exception;
use local_elediaai_chatengine\backend_resolver;
use local_elediaai_chatengine\local\thread_store;
use local_elediaai_chatengine\local\usage;

/**
 * User-initiated deletion of all tutor data the plugin holds for a user.
 *
 * Separates the two storage locations honestly:
 *  - LOCAL data (conversation pointers, question logs, consent, usage,
 *    diagnostics and the LTM preference) is always deleted, unconditionally.
 *  - EXTERNAL data (transcripts and long-term memory on the RAG/Tutor server)
 *    can only be deleted when the admin has configured a delete tool AND the
 *    MCP connector is available. The user-level tool (deleteusertoolname) is
 *    preferred — one call erases everything for the user, including memory —
 *    with the per-conversation tool (deletetoolname) as fallback. When neither
 *    is configured, the result reports external deletion as unsupported so the
 *    UI can tell the user the truth (external data remains subject to the
 *    service's retention policy) instead of implying everything is gone.
 *
 * External deletion is best-effort: individual failures are counted, never
 * fatal, and never block the local erase — a user must always be able to
 * remove their own local data. One summarising data_deletion_requested event
 * is recorded per request.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class deletion_service {
    /**
     * Delete all tutor data for a user, propagating to the RAG server when possible.
     *
     * @param int $userid The user whose data is erased (also the requester).
     * @param context $context Context the request was made in (for the audit event).
     * @param adapter|null $adapter Optional injected backend (tests).
     * @return array Deletion counters and external deletion status.
     */
    public static function delete_all_for_user(int $userid, context $context, ?adapter $adapter = null): array {
        $threads = thread_store::threads_for_user('block_elediaai_tutor', $userid);
        $adapter ??= backend_resolver::active();
        $externaldeleted = 0;
        $externalfailed = 0;

        // The user-level tool is preferred: one call erases everything the
        // backend holds for the person, transcripts and long-term memory alike,
        // and stays complete even where this site holds no pointer any more.
        // The per-conversation tool is the fallback and reaches only what this
        // site still knows about. Which of the two exists is the backend's
        // answer now, not a setting kept here.
        if ($adapter !== null) {
            try {
                try {
                    $adapter->call_tool('deleteuser', [], $userid);
                    $externaldeleted = 1;
                } catch (adapter_exception $e) {
                    foreach ($threads as $thread) {
                        if (empty($thread->convkey)) {
                            continue;
                        }
                        try {
                            $adapter->call_tool('delete', ['conversation_id' => (string) $thread->convkey], $userid);
                            $externaldeleted++;
                        } catch (adapter_exception $inner) {
                            $externalfailed++;
                        }
                    }
                }
            } catch (\moodle_exception $e) {
                // Configuration or token failure: everything not yet deleted
                // counts as failed, and the local erase below still proceeds.
                $externalfailed = max(1, count($threads)) - $externaldeleted;
                debugging(
                    'block_elediaai_tutor: external deletion failed: ' . $e->getMessage(),
                    DEBUG_DEVELOPER
                );
            }
        }

        $conversationsdeleted = count($threads);
        thread_store::delete_where(['placement' => 'block_elediaai_tutor', 'userid' => $userid]);
        $usagedeleted = usage::delete_for_user($userid);
        // Die Fragen liegen seit dem 19.09.2026 im Turn-Speicher der Suite,
        // ohne Namen und mit einem Pseudonym-Schluessel. Geloescht wird ueber
        // den neu berechneten Schluessel -- aufgeloest wird er nie.
        $questionsdeleted = \local_elediaai_core\local\insights::delete_for_user($userid);
        $consentsdeleted = consent::delete_for_user($userid);
        $diagnosticsdeleted = diagnostics::delete_for_user($userid);
        $ltmpreferencedeleted = ltm::delete_for_user($userid);
        $localdeleted = $conversationsdeleted + $questionsdeleted + $consentsdeleted
            + $usagedeleted + $diagnosticsdeleted + $ltmpreferencedeleted;

        // Here, being supported now means a usable backend answered at all:
        // whether it offers a deletion tool is its own answer, not a setting
        // read here.
        $externalsupported = $adapter !== null;

        \block_elediaai_tutor\event\data_deletion_requested::create([
            'context' => $context,
            'userid' => $userid,
            'relateduserid' => $userid,
            'other' => [
                'localdeleted' => $localdeleted,
                'conversationsdeleted' => $conversationsdeleted,
                'questionsdeleted' => $questionsdeleted,
                'consentsdeleted' => $consentsdeleted,
                'usagedeleted' => $usagedeleted,
                'diagnosticsdeleted' => $diagnosticsdeleted,
                'ltmpreferencedeleted' => $ltmpreferencedeleted,
                'externalsupported' => (int) $externalsupported,
                'externaldeleted' => $externaldeleted,
                'externalfailed' => $externalfailed,
            ],
        ])->trigger();

        return [
            'localdeleted' => $localdeleted,
            'conversationsdeleted' => $conversationsdeleted,
            'questionsdeleted' => $questionsdeleted,
            'consentsdeleted' => $consentsdeleted,
            'usagedeleted' => $usagedeleted,
            'diagnosticsdeleted' => $diagnosticsdeleted,
            'ltmpreferencedeleted' => $ltmpreferencedeleted,
            'externalsupported' => $externalsupported,
            'externaldeleted' => $externaldeleted,
            'externalfailed' => $externalfailed,
        ];
    }
}

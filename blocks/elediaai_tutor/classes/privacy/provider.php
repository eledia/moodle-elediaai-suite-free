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

namespace block_elediaai_tutor\privacy;

use block_elediaai_tutor\local\deletion_service;
use context;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for the eLeDia.ai Tutor block.
 *
 * Local storage is limited to conversation metadata (a pointer to the
 * RAG-server-owned conversation plus a short last-message preview). Full chat
 * transcripts are NOT stored in Moodle — they live on the external RAG/Tutor
 * server, which is declared here as an external location. The user-scoped MCP
 * token's own metadata is owned and exported/erased by webservice_elediamcp.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\user_preference_provider {
    /**
     * Describe the personal data stored or transmitted by this plugin.
     *
     * @param collection $collection The metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {

        // Data sent to the external RAG/Tutor server, where full history lives.
        $collection->add_external_location_link('rag_server', [
            'userid' => 'privacy:metadata:rag_server:userid',
            'message' => 'privacy:metadata:rag_server:message',
            'courseid' => 'privacy:metadata:rag_server:courseid',
            'conversationid' => 'privacy:metadata:rag_server:conversationid',
        ], 'privacy:metadata:rag_server');

        // Opt-in question analytics (questions only, never answers).

        // Daily message counters for quota enforcement.

        // Short admin diagnostics for failed tutor calls; no prompts or answers.
        $collection->add_database_table('block_elediaai_tutor_diag', [
            'userid' => 'privacy:metadata:block_elediaai_tutor_diag:userid',
            'courseid' => 'privacy:metadata:block_elediaai_tutor_diag:courseid',
            'contextid' => 'privacy:metadata:block_elediaai_tutor_diag:contextid',
            'phase' => 'privacy:metadata:block_elediaai_tutor_diag:phase',
            'errorcode' => 'privacy:metadata:block_elediaai_tutor_diag:errorcode',
            'detail' => 'privacy:metadata:block_elediaai_tutor_diag:detail',
            'timecreated' => 'privacy:metadata:block_elediaai_tutor_diag:timecreated',
        ], 'privacy:metadata:block_elediaai_tutor_diag');

        // Documented first-use acknowledgement of the privacy guidelines.
        $collection->add_database_table('block_elediaai_tutor_consent', [
            'userid' => 'privacy:metadata:block_elediaai_tutor_consent:userid',
            'timecreated' => 'privacy:metadata:block_elediaai_tutor_consent:timecreated',
        ], 'privacy:metadata:block_elediaai_tutor_consent');

        // The long-term memory opt-in (a user preference; off by default).
        $collection->add_user_preference(
            \block_elediaai_tutor\local\ltm::PREF,
            'privacy:metadata:preference:ltm'
        );

        return $collection;
    }

    /**
     * Export the user's preferences for this plugin.
     *
     * @param int $userid The user id.
     * @return void
     */
    public static function export_user_preferences(int $userid): void {
        $value = get_user_preferences(\block_elediaai_tutor\local\ltm::PREF, null, $userid);
        if ($value === null) {
            return;
        }
        writer::export_user_preference(
            'block_elediaai_tutor',
            \block_elediaai_tutor\local\ltm::PREF,
            transform::yesno($value),
            get_string('privacy:metadata:preference:ltm', 'block_elediaai_tutor')
        );
    }

    /**
     * Conversation metadata lives at the system context.
     *
     * @param int $userid The user id.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        if (self::user_has_data($userid)) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * Find users with data in the given context.
     *
     * @param userlist $userlist The userlist.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        if (!$userlist->get_context() instanceof \core\context\system) {
            return;
        }
        $userlist->add_from_sql('userid', 'SELECT userid FROM {block_elediaai_tutor_consent}', []);
        $userlist->add_from_sql('userid', 'SELECT userid FROM {block_elediaai_tutor_diag}', []);
        $userlist->add_from_sql(
            'userid',
            'SELECT userid FROM {user_preferences} WHERE name = :name',
            ['name' => \block_elediaai_tutor\local\ltm::PREF]
        );
    }

    /**
     * Export conversation metadata for the approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (!self::contains_system_context($contextlist->get_contexts())) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        // Die Fragen selbst liegen seit dem 19.09.2026 im Turn-Speicher von
        // local_elediaai_core und werden von dessen Privacy-Provider
        // exportiert. Hier noch einmal auszugeben hiesse, sie doppelt zu
        // melden und den Eindruck zu erwecken, der Block halte sie weiter.

        $diagnostics = $DB->get_records('block_elediaai_tutor_diag', ['userid' => $userid], 'timecreated ASC');
        if (!empty($diagnostics)) {
            $data = [];
            foreach ($diagnostics as $record) {
                $data[] = (object) [
                    'courseid' => (int) $record->courseid,
                    'contextid' => (int) $record->contextid,
                    'phase' => $record->phase,
                    'errorcode' => $record->errorcode,
                    'detail' => $record->detail,
                    'timecreated' => transform::datetime($record->timecreated),
                ];
            }
            writer::with_context(\core\context\system::instance())->export_data(
                [get_string('privacy:diagnostics', 'block_elediaai_tutor')],
                (object) ['diagnostics' => $data]
            );
        }

        $consenttime = \block_elediaai_tutor\local\consent::time_consented((int) $userid);
        if ($consenttime !== null) {
            writer::with_context(\core\context\system::instance())->export_data(
                [get_string('privacy:consent', 'block_elediaai_tutor')],
                (object) ['timeconsented' => transform::datetime($consenttime)]
            );
        }
    }

    /**
     * Delete all conversation metadata in the given context.
     *
     * @param context $context The context.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;
        if (!$context instanceof \core\context\system) {
            return;
        }
        $userids = $DB->get_fieldset_sql(
            'SELECT userid FROM {block_elediaai_tutor_consent}
             UNION SELECT userid FROM {block_elediaai_tutor_diag}'
        );
        $prefusers = $DB->get_fieldset_select(
            'user_preferences',
            'userid',
            'name = :name',
            ['name' => \block_elediaai_tutor\local\ltm::PREF]
        );
        self::delete_data_for_userids(array_merge($userids, $prefusers));
    }

    /**
     * Delete a user's conversation metadata in the approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        if (!self::contains_system_context($contextlist->get_contexts())) {
            return;
        }
        $userid = (int) $contextlist->get_user()->id;
        self::delete_data_for_userids([$userid]);
    }

    /**
     * Delete conversation metadata for multiple users in the given context.
     *
     * @param approved_userlist $userlist Approved users.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        if (!$userlist->get_context() instanceof \core\context\system) {
            return;
        }
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        self::delete_data_for_userids($userids);
    }

    /**
     * Erase each user's complete local tutor data and request remote deletion.
     *
     * The shared deletion service keeps remote deletion best-effort while local
     * database failures remain visible to Moodle's privacy subsystem.
     *
     * @param int[] $userids User ids to erase.
     * @return void
     */
    private static function delete_data_for_userids(array $userids): void {
        $context = \core\context\system::instance();
        $userids = array_unique(array_filter(array_map('intval', $userids)));
        foreach ($userids as $userid) {
            deletion_service::delete_all_for_user($userid, $context);
        }
    }

    /**
     * Whether the block itself holds anything about this user.
     *
     * The conversation and the daily counters are deliberately not asked
     * about: they belong to local_elediaai_chatengine, whose provider reports
     * and erases them for every chat surface at once.
     *
     * @param int $userid The user id.
     * @return bool
     */
    protected static function user_has_data(int $userid): bool {
        global $DB;
        return $DB->record_exists('block_elediaai_tutor_consent', ['userid' => $userid])
            || $DB->record_exists('block_elediaai_tutor_diag', ['userid' => $userid])
            || $DB->record_exists('user_preferences', [
                'userid' => $userid,
                'name' => \block_elediaai_tutor\local\ltm::PREF,
            ]);
    }

    /**
     * Whether a context list contains the system context.
     *
     * @param context[] $contexts The contexts.
     * @return bool
     */
    protected static function contains_system_context(array $contexts): bool {
        foreach ($contexts as $context) {
            if ($context instanceof \core\context\system) {
                return true;
            }
        }
        return false;
    }
}

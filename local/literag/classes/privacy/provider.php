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

namespace local_literag\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_literag\local\user_eraser;

/**
 * Privacy (GDPR) provider for local_literag.
 *
 * Tutor transcripts, opt-in memory and query logs are user-scoped and held in
 * the system context. Question text and retrieved context are also sent to an
 * external OpenAI-compatible LLM, declared here as an external location.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the data this plugin stores and transmits.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_literag_conversations', [
            'userid' => 'privacy:metadata:local_literag_conversations:userid',
            'courseid' => 'privacy:metadata:local_literag_conversations:courseid',
            'timecreated' => 'privacy:metadata:local_literag_conversations:timecreated',
        ], 'privacy:metadata:local_literag_conversations');

        $collection->add_database_table('local_literag_messages', [
            'userid' => 'privacy:metadata:local_literag_messages:userid',
            'role' => 'privacy:metadata:local_literag_messages:role',
            'content' => 'privacy:metadata:local_literag_messages:content',
            'timecreated' => 'privacy:metadata:local_literag_messages:timecreated',
        ], 'privacy:metadata:local_literag_messages');

        $collection->add_database_table('local_literag_memory', [
            'userid' => 'privacy:metadata:local_literag_memory:userid',
            'mvalue' => 'privacy:metadata:local_literag_memory:mvalue',
            'timecreated' => 'privacy:metadata:local_literag_memory:timecreated',
        ], 'privacy:metadata:local_literag_memory');

        $collection->add_database_table('local_literag_query_log', [
            'userid' => 'privacy:metadata:local_literag_query_log:userid',
            'querytext' => 'privacy:metadata:local_literag_query_log:querytext',
            'timecreated' => 'privacy:metadata:local_literag_query_log:timecreated',
        ], 'privacy:metadata:local_literag_query_log');

        $collection->add_external_location_link('llm_provider', [
            'usermessage' => 'privacy:metadata:llm_provider:usermessage',
            'context' => 'privacy:metadata:llm_provider:context',
            'usersummary' => 'privacy:metadata:llm_provider:usersummary',
            'toolresults' => 'privacy:metadata:llm_provider:toolresults',
        ], 'privacy:metadata:llm_provider');

        return $collection;
    }

    /**
     * The contexts holding data for a user (system context only).
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        $has = $DB->record_exists('local_literag_conversations', ['userid' => $userid])
            || $DB->record_exists('local_literag_memory', ['userid' => $userid])
            || $DB->record_exists('local_literag_query_log', ['userid' => $userid]);
        if ($has) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * Users within a context (system context only).
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \core\context\system) {
            return;
        }
        foreach (['local_literag_conversations', 'local_literag_memory', 'local_literag_query_log'] as $table) {
            $userlist->add_from_sql('userid', "SELECT DISTINCT userid FROM {{$table}} WHERE userid > 0", []);
        }
    }

    /**
     * Export all stored data for the approved contexts.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $usesystem = false;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \core\context\system) {
                $usesystem = true;
                break;
            }
        }
        if (!$usesystem) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        $context = \core\context\system::instance();
        $root = [get_string('pluginname', 'local_literag')];

        $conversations = $DB->get_records('local_literag_conversations', ['userid' => $userid], 'timecreated ASC');
        foreach ($conversations as $conversation) {
            $messages = $DB->get_records(
                'local_literag_messages',
                ['conversationid' => $conversation->id],
                'timecreated ASC',
                'id, role, content, topic, timecreated'
            );
            $data = (object) [
                'courseid' => $conversation->courseid,
                'timecreated' => transform::datetime($conversation->timecreated),
                'messages' => array_map(static function ($m) {
                    return (object) [
                        'role' => $m->role,
                        'content' => $m->content,
                        'topic' => $m->topic,
                        'timecreated' => transform::datetime($m->timecreated),
                    ];
                }, array_values($messages)),
            ];
            writer::with_context($context)->export_data(
                array_merge($root, [get_string('privacy:conversations', 'local_literag'), $conversation->convkey]),
                $data
            );
        }

        $memory = $DB->get_records('local_literag_memory', ['userid' => $userid]);
        if ($memory) {
            writer::with_context($context)->export_data(
                array_merge($root, [get_string('privacy:memory', 'local_literag')]),
                (object) ['facts' => array_values(array_map(static fn($m) => $m->mvalue, $memory))]
            );
        }

        $logs = $DB->get_records(
            'local_literag_query_log',
            ['userid' => $userid],
            'timecreated ASC',
            'id, courseid, querytext, numcandidates, numreturned, usedllm, reranked, latencyms, timecreated'
        );
        if ($logs) {
            writer::with_context($context)->export_data(
                array_merge($root, [get_string('privacy:querylogs', 'local_literag')]),
                (object) ['queries' => array_values(array_map(static function ($log) {
                    return (object) [
                        'courseid' => $log->courseid,
                        'querytext' => $log->querytext,
                        'numcandidates' => $log->numcandidates,
                        'numreturned' => $log->numreturned,
                        'usedllm' => $log->usedllm,
                        'reranked' => $log->reranked,
                        'latencyms' => $log->latencyms,
                        'timecreated' => transform::datetime($log->timecreated),
                    ];
                }, $logs))]
            );
        }
    }

    /**
     * Delete all plugin data for all users in a context.
     *
     * @param \context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if (!$context instanceof \core\context\system) {
            return;
        }
        $DB->delete_records('local_literag_messages');
        $DB->delete_records('local_literag_conversations');
        $DB->delete_records('local_literag_memory');
        $DB->delete_records('local_literag_query_log');
    }

    /**
     * Delete all data for one user across approved contexts.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \core\context\system) {
                user_eraser::erase((int) $contextlist->get_user()->id);
                return;
            }
        }
    }

    /**
     * Delete data for multiple users in a context.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        if (!$userlist->get_context() instanceof \core\context\system) {
            return;
        }
        foreach ($userlist->get_userids() as $userid) {
            user_eraser::erase((int) $userid);
        }
    }
}

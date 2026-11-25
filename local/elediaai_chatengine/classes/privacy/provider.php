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

namespace local_elediaai_chatengine\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use local_elediaai_chatengine\local\backend_erasure;
use local_elediaai_chatengine\local\message;
use core_privacy\local\request\writer;
use local_elediaai_chatengine\local\thread_store;
use local_elediaai_chatengine\local\usage;

/**
 * Privacy implementation for the shared conversation store.
 *
 * One provider for every chat placement. That is the practical payoff of a
 * single schema: four plugins previously described four nearly identical
 * stores, and a change to what was kept had to be made — and reviewed — four
 * times.
 *
 * Guest conversations carry no user id and are therefore outside the subject
 * access mechanism, which is keyed by user. They are deleted with their
 * context and by the retention task instead.
 *
 * The daily message counters are kept per person across every placement and
 * therefore live in the system context.
 *
 * Deletions reach the backend as well, whatever placement held the
 * conversation (ai_suite#250). A complete deletion of a person asks it to
 * erase everything about them (tutor_delete_user_data); anything narrower,
 * a single context or part of a person's contexts, deletes only the
 * conversations concerned (tutor_delete_conversation). The calls are
 * best-effort and come after the Moodle data is gone; see
 * {@see backend_erasure}.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    #[\Override]
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            thread_store::THREAD_TABLE,
            [
                'userid' => 'privacy:metadata:thread:userid',
                'guestkey' => 'privacy:metadata:thread:guestkey',
                'placement' => 'privacy:metadata:thread:placement',
                'contextid' => 'privacy:metadata:thread:contextid',
                'courseid' => 'privacy:metadata:thread:courseid',
                'convkey' => 'privacy:metadata:thread:convkey',
                'mode' => 'privacy:metadata:thread:mode',
                'title' => 'privacy:metadata:thread:title',
                'lastpreview' => 'privacy:metadata:thread:lastpreview',
                'timecreated' => 'privacy:metadata:thread:timecreated',
                'timemodified' => 'privacy:metadata:thread:timemodified',
            ],
            'privacy:metadata:thread'
        );

        $collection->add_database_table(
            thread_store::MESSAGE_TABLE,
            [
                'role' => 'privacy:metadata:msg:role',
                'content' => 'privacy:metadata:msg:content',
                'sources' => 'privacy:metadata:msg:sources',
                'origin' => 'privacy:metadata:msg:origin',
                'timecreated' => 'privacy:metadata:msg:timecreated',
            ],
            'privacy:metadata:msg'
        );

        $collection->add_database_table(
            usage::TABLE,
            [
                'userid' => 'privacy:metadata:usage:userid',
                'daykey' => 'privacy:metadata:usage:daykey',
                'messagecount' => 'privacy:metadata:usage:messagecount',
            ],
            'privacy:metadata:usage'
        );

        $collection->add_external_location_link(
            'aibackend',
            [
                'usermessage' => 'privacy:metadata:backend:usermessage',
                'history' => 'privacy:metadata:backend:history',
                'userid' => 'privacy:metadata:backend:userid',
                'courseid' => 'privacy:metadata:backend:courseid',
                'language' => 'privacy:metadata:backend:language',
            ],
            'privacy:metadata:backend'
        );

        return $collection;
    }

    #[\Override]
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();
        $contextlist->add_from_sql(
            'SELECT DISTINCT contextid FROM {' . thread_store::THREAD_TABLE . '} WHERE userid = :userid',
            ['userid' => $userid]
        );

        if ($DB->record_exists(usage::TABLE, ['userid' => $userid])) {
            $contextlist->add_system_context();
        }

        return $contextlist;
    }

    #[\Override]
    public static function get_users_in_context(userlist $userlist): void {
        $userlist->add_from_sql(
            'userid',
            'SELECT userid FROM {' . thread_store::THREAD_TABLE . '} WHERE contextid = :contextid AND userid > 0',
            ['contextid' => $userlist->get_context()->id]
        );

        if ($userlist->get_context() instanceof \core\context\system) {
            $userlist->add_from_sql('userid', 'SELECT userid FROM {' . usage::TABLE . '}', []);
        }
    }

    #[\Override]
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $threads = $DB->get_records(
                thread_store::THREAD_TABLE,
                ['contextid' => $context->id, 'userid' => $userid],
                'timecreated ASC'
            );
            foreach ($threads as $thread) {
                $data = (object) [
                    'placement' => $thread->placement,
                    'title' => $thread->title,
                    'mode' => $thread->mode,
                    'timecreated' => transform::datetime((int) $thread->timecreated),
                    'timemodified' => transform::datetime((int) $thread->timemodified),
                    'messages' => array_map(
                        static fn($message): array => [
                            'role' => $message->role,
                            'content' => $message->content,
                            'sources' => $message->sources,
                            'origin' => $message->origin,
                            // Art. 50 Abs. 2 (#52): the export says which turns a model wrote.
                            'ai_generated' => message::is_ai_generated((string) $message->role, (string) $thread->backend),
                            'timecreated' => transform::datetime($message->timecreated),
                        ],
                        thread_store::messages((int) $thread->id)
                    ),
                ];
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_elediaai_chatengine'), 'conversation-' . $thread->id],
                    $data
                );
            }

            if ($context instanceof \core\context\system) {
                self::export_usage((int) $userid);
            }
        }
    }

    /**
     * Export the daily message counters of one user.
     *
     * @param int $userid The user id.
     * @return void
     */
    private static function export_usage(int $userid): void {
        global $DB;

        $rows = $DB->get_records(usage::TABLE, ['userid' => $userid], 'daykey ASC', 'id, daykey, messagecount');
        if (empty($rows)) {
            return;
        }

        $days = [];
        foreach ($rows as $row) {
            $days[] = (object) [
                'day' => (string) $row->daykey,
                'messagecount' => (int) $row->messagecount,
            ];
        }

        $path = [
            get_string('pluginname', 'local_elediaai_chatengine'),
            get_string('privacy:path:usage', 'local_elediaai_chatengine'),
        ];
        writer::with_context(\core\context\system::instance())->export_data($path, (object) ['days' => $days]);
    }

    #[\Override]
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        // Collected first: once the rows are gone, so are the conversation ids.
        $threads = self::threads_in([(int) $context->id]);

        thread_store::delete_where(['contextid' => $context->id]);

        if ($context instanceof \core\context\system) {
            $DB->delete_records(usage::TABLE);
        }

        // A context is narrower than a person: only its conversations go.
        backend_erasure::erase_conversations($threads);
    }

    #[\Override]
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = (int) $contextlist->get_user()->id;
        $approved = [];
        foreach ($contextlist->get_contexts() as $context) {
            $approved[] = (int) $context->id;
        }
        $threads = self::threads_in($approved, [$userid]);
        $complete = $threads !== [] && self::holds_threads_only_in($userid, $approved);

        foreach ($contextlist->get_contexts() as $context) {
            thread_store::delete_where(['contextid' => $context->id, 'userid' => $userid]);
            if ($context instanceof \core\context\system) {
                usage::delete_for_user($userid);
            }
        }

        // The Moodle data is gone first, so the backend can never stand in
        // its way; neither call throws (ai_suite#250). Everything at the
        // backend only when every conversation of the person was approved,
        // otherwise the approved conversations alone.
        if ($complete) {
            backend_erasure::erase_users([$userid]);
        } else {
            backend_erasure::erase_conversations($threads);
        }
    }

    #[\Override]
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $contextid = (int) $userlist->get_context()->id;
        $issystem = $userlist->get_context() instanceof \core\context\system;
        $userids = array_map('intval', $userlist->get_userids());
        $threads = self::threads_in([$contextid], $userids);

        foreach ($userids as $userid) {
            thread_store::delete_where(['contextid' => $contextid, 'userid' => $userid]);
            if ($issystem) {
                usage::delete_for_user($userid);
            }
        }

        // One context, never the whole person: only these conversations go.
        backend_erasure::erase_conversations($threads);
    }

    /**
     * The personal conversations held in the given contexts.
     *
     * @param int[] $contextids The contexts.
     * @param int[]|null $userids Only these owners, or every signed-in owner when null.
     * @return \stdClass[] Records with id, userid, backend and convkey.
     */
    private static function threads_in(array $contextids, ?array $userids = null): array {
        global $DB;

        if ($contextids === [] || $userids === []) {
            return [];
        }
        [$ctxsql, $params] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'ctx');
        $where = "contextid {$ctxsql} AND userid > 0";
        if ($userids !== null) {
            [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'usr');
            $where .= " AND userid {$usersql}";
            $params += $userparams;
        }

        return array_values($DB->get_records_select(
            thread_store::THREAD_TABLE,
            $where,
            $params,
            'id ASC',
            'id, userid, backend, convkey'
        ));
    }

    /**
     * Whether every conversation of the person lies in the given contexts.
     *
     * That is what tells a complete deletion request apart from a partial
     * one, such as the retention of a single course running out.
     *
     * @param int $userid The person.
     * @param int[] $contextids The approved contexts.
     * @return bool
     */
    private static function holds_threads_only_in(int $userid, array $contextids): bool {
        global $DB;

        [$ctxsql, $params] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'ctx', false);
        $params['userid'] = $userid;

        return !$DB->record_exists_select(thread_store::THREAD_TABLE, "userid = :userid AND contextid {$ctxsql}", $params);
    }
}

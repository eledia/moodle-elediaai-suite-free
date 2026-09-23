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
use core_privacy\local\request\writer;
use local_elediaai_chatengine\local\thread_store;

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
                'placement' => 'privacy:metadata:thread:placement',
                'courseid' => 'privacy:metadata:thread:courseid',
                'mode' => 'privacy:metadata:thread:mode',
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
        $contextlist = new contextlist();
        $contextlist->add_from_sql(
            'SELECT DISTINCT contextid FROM {' . thread_store::THREAD_TABLE . '} WHERE userid = :userid',
            ['userid' => $userid]
        );

        return $contextlist;
    }

    #[\Override]
    public static function get_users_in_context(userlist $userlist): void {
        $userlist->add_from_sql(
            'userid',
            'SELECT userid FROM {' . thread_store::THREAD_TABLE . '} WHERE contextid = :contextid AND userid > 0',
            ['contextid' => $userlist->get_context()->id]
        );
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
                    'mode' => $thread->mode,
                    'timecreated' => transform::datetime((int) $thread->timecreated),
                    'timemodified' => transform::datetime((int) $thread->timemodified),
                    'messages' => array_map(
                        static fn($message): array => [
                            'role' => $message->role,
                            'content' => $message->content,
                            'sources' => $message->sources,
                            'origin' => $message->origin,
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
        }
    }

    #[\Override]
    public static function delete_data_for_all_users_in_context(\context $context): void {
        thread_store::delete_where(['contextid' => $context->id]);
    }

    #[\Override]
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            thread_store::delete_where(['contextid' => $context->id, 'userid' => $userid]);
        }
    }

    #[\Override]
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $contextid = $userlist->get_context()->id;
        foreach ($userlist->get_userids() as $userid) {
            thread_store::delete_where(['contextid' => $contextid, 'userid' => (int) $userid]);
        }
    }
}

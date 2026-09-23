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

/**
 * The one conversation store, for every placement.
 *
 * Four plugins used to keep four thread tables that differed only in their
 * scoping column. One schema is what makes the privacy provider, the history
 * limit and the retention task exist once instead of four times, and it is the
 * reason a placement can be a thin one at all.
 *
 * A thread is addressed by placement, instance and owner. Several threads per
 * owner are supported because the tutor block lists a user's conversations;
 * placements that only ever show one simply never start a second.
 *
 * Threads are kept site-side even when the backend holds its own transcript.
 * That is deliberate: the history limit, the privacy export and a teacher's
 * view of what was said must not depend on an external service still being
 * reachable, and a site that switches destination must still be able to
 * answer what its users asked.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class thread_store {
    /** @var string The conversation table. */
    public const THREAD_TABLE = 'local_elediaai_chateng_thread';

    /** @var string The turns table. */
    public const MESSAGE_TABLE = 'local_elediaai_chateng_msg';

    /**
     * The owner's current thread here, started if there is none.
     *
     * A thread whose backend or persona no longer matches is not continued and
     * not wiped either: a new one is started beside it. Continuing would mix
     * turns that followed different instructions, or resume a conversation id
     * that belongs to a service the site no longer writes to. Wiping would
     * throw away the record of what a person actually asked.
     *
     * @param string $placement Frankenstyle component of the placement.
     * @param int $instanceid The placement's own instance id, or 0 for site-wide.
     * @param int $contextid The context the conversation happens in.
     * @param int $courseid The course, or 0.
     * @param int $userid The owner, or 0 for a guest.
     * @param string|null $guestkey Guest identifier when userid is 0.
     * @param string $backend The active backend id.
     * @param string $mode The answer mode.
     * @param string $personahash Fingerprint of the effective persona.
     * @return \stdClass The thread record.
     */
    public static function open(
        string $placement,
        int $instanceid,
        int $contextid,
        int $courseid,
        int $userid,
        ?string $guestkey,
        string $backend,
        string $mode,
        string $personahash
    ): \stdClass {
        $current = self::current($placement, $instanceid, $userid, $guestkey);

        if ($current !== null && $current->backend === $backend && (string) $current->personahash === $personahash) {
            if ($current->mode !== $mode) {
                self::set_mode((int) $current->id, $mode);
                $current->mode = $mode;
            }
            return $current;
        }

        return self::create(
            $placement,
            $instanceid,
            $contextid,
            $courseid,
            $userid,
            $guestkey,
            $backend,
            $mode,
            $personahash
        );
    }

    /**
     * The most recently used thread for an owner in one placement.
     *
     * @param string $placement Frankenstyle component of the placement.
     * @param int $instanceid The placement's instance id.
     * @param int $userid The owner, or 0 for a guest.
     * @param string|null $guestkey Guest identifier when userid is 0.
     * @return \stdClass|null The thread, or null when there is none.
     */
    public static function current(string $placement, int $instanceid, int $userid, ?string $guestkey = null): ?\stdClass {
        global $DB;

        $conditions = self::owner_conditions($placement, $instanceid, $userid, $guestkey);
        $records = $DB->get_records(self::THREAD_TABLE, $conditions, 'timemodified DESC, id DESC', '*', 0, 1);

        return $records === [] ? null : reset($records);
    }

    /**
     * Start a new thread.
     *
     * @param string $placement Frankenstyle component of the placement.
     * @param int $instanceid The placement's instance id.
     * @param int $contextid The context.
     * @param int $courseid The course, or 0.
     * @param int $userid The owner, or 0 for a guest.
     * @param string|null $guestkey Guest identifier when userid is 0.
     * @param string $backend The active backend id.
     * @param string $mode The answer mode.
     * @param string $personahash Fingerprint of the effective persona.
     * @return \stdClass The new thread record.
     */
    public static function create(
        string $placement,
        int $instanceid,
        int $contextid,
        int $courseid,
        int $userid,
        ?string $guestkey,
        string $backend,
        string $mode,
        string $personahash
    ): \stdClass {
        global $DB;

        $now = time();
        $thread = (object) [
            'placement' => $placement,
            'instanceid' => $instanceid,
            'contextid' => $contextid,
            'courseid' => $courseid,
            'userid' => $userid,
            'guestkey' => $userid === 0 ? $guestkey : null,
            'backend' => $backend,
            'convkey' => null,
            'personahash' => $personahash,
            'mode' => $mode,
            'title' => null,
            'lastpreview' => null,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $thread->id = $DB->insert_record(self::THREAD_TABLE, $thread);

        return $thread;
    }

    /**
     * A thread by id, only for its owner.
     *
     * @param int $threadid The thread id.
     * @param int $userid The claimed owner, or 0 for a guest.
     * @param string|null $guestkey Guest identifier when userid is 0.
     * @return \stdClass|null The thread, or null when it does not exist or is not theirs.
     */
    public static function owned(int $threadid, int $userid, ?string $guestkey = null): ?\stdClass {
        global $DB;

        $thread = $DB->get_record(self::THREAD_TABLE, ['id' => $threadid]);
        if (!$thread) {
            return null;
        }
        if ($userid > 0) {
            return (int) $thread->userid === $userid ? $thread : null;
        }
        $matches = $guestkey !== null && $guestkey !== '' && (string) $thread->guestkey === $guestkey;

        return $matches ? $thread : null;
    }

    /**
     * An owner's threads in one placement, most recent first.
     *
     * @param string $placement Frankenstyle component of the placement.
     * @param int $instanceid The placement's instance id.
     * @param int $userid The owner, or 0 for a guest.
     * @param string|null $guestkey Guest identifier when userid is 0.
     * @param int $limit Maximum rows, 0 for all.
     * @return \stdClass[] Thread records.
     */
    public static function threads_for(
        string $placement,
        int $instanceid,
        int $userid,
        ?string $guestkey = null,
        int $limit = 0
    ): array {
        global $DB;

        $conditions = self::owner_conditions($placement, $instanceid, $userid, $guestkey);

        return array_values($DB->get_records(
            self::THREAD_TABLE,
            $conditions,
            'timemodified DESC, id DESC',
            '*',
            0,
            max(0, $limit)
        ));
    }

    /**
     * Every thread one owner holds in a placement, across all its instances.
     *
     * Distinct from {@see threads_for()}, which is scoped to one instance: a
     * privacy erase or an account deletion has to reach a person's whole
     * history, not the part of it that happens to sit under the instance the
     * caller was thinking of.
     *
     * @param string $placement Frankenstyle component of the placement.
     * @param int $userid The owner.
     * @return \stdClass[] Thread records, most recent first.
     */
    public static function threads_for_user(string $placement, int $userid): array {
        global $DB;

        return array_values($DB->get_records(
            self::THREAD_TABLE,
            ['placement' => $placement, 'userid' => $userid],
            'timemodified DESC, id DESC'
        ));
    }

    /**
     * The turns of a thread, oldest first.
     *
     * @param int $threadid The thread id.
     * @param int $limit Keep only the latest N turns, 0 for all.
     * @return message[] The turns.
     */
    public static function messages(int $threadid, int $limit = 0): array {
        global $DB;

        $rows = $DB->get_records(
            self::MESSAGE_TABLE,
            ['threadid' => $threadid],
            'timecreated ASC, id ASC'
        );
        $messages = array_map(
            static fn(\stdClass $row): message => new message(
                (string) $row->role,
                (string) $row->content,
                $row->sources !== null ? (array) json_decode((string) $row->sources, true) : [],
                (int) $row->timecreated,
                $row->origin !== null ? (string) $row->origin : ''
            ),
            array_values($rows)
        );

        if ($limit > 0 && count($messages) > $limit) {
            $messages = array_slice($messages, -1 * $limit);
        }

        return $messages;
    }

    /**
     * Append a turn.
     *
     * @param int $threadid The thread id.
     * @param string $role One of the message ROLE_* constants.
     * @param string $content The turn text.
     * @param array $sources Citations, for an assistant turn.
     * @param string $origin Where an assistant turn came from, '' for a user turn.
     * @return int The new message id.
     * @throws \coding_exception On an unknown role.
     */
    public static function add_message(
        int $threadid,
        string $role,
        string $content,
        array $sources = [],
        string $origin = ''
    ): int {
        global $DB;

        if (!message::is_valid_role($role)) {
            throw new \coding_exception('Unknown chat engine message role: ' . $role);
        }

        return (int) $DB->insert_record(self::MESSAGE_TABLE, (object) [
            'threadid' => $threadid,
            'role' => $role,
            'content' => $content,
            'sources' => $sources === [] ? null : json_encode(array_values($sources)),
            'origin' => $origin === '' ? null : $origin,
            'timecreated' => time(),
        ]);
    }

    /**
     * Record the conversation id the backend issued.
     *
     * @param int $threadid The thread id.
     * @param string|null $convkey The backend's conversation id, or null to forget it.
     * @return void
     */
    public static function set_convkey(int $threadid, ?string $convkey): void {
        global $DB;

        $DB->set_field(self::THREAD_TABLE, 'convkey', $convkey, ['id' => $threadid]);
    }

    /**
     * Record the answer mode a thread is running in.
     *
     * @param int $threadid The thread id.
     * @param string $mode The answer mode.
     * @return void
     */
    public static function set_mode(int $threadid, string $mode): void {
        global $DB;

        $DB->set_field(self::THREAD_TABLE, 'mode', mode::normalise($mode), ['id' => $threadid]);
    }

    /**
     * Mark a thread as just used, and refresh its preview.
     *
     * @param int $threadid The thread id.
     * @param string $preview The latest user message, truncated for display.
     * @return void
     */
    public static function touch(int $threadid, string $preview = ''): void {
        global $DB;

        $update = (object) ['id' => $threadid, 'timemodified' => time()];
        if (trim($preview) !== '') {
            $update->lastpreview = \core_text::substr(trim($preview), 0, 255);
        }
        $DB->update_record(self::THREAD_TABLE, $update);
    }

    /**
     * Drop the turns of a thread but keep the thread itself.
     *
     * The backend's own conversation id is forgotten at the same time: leaving
     * it would let the next turn resume a transcript the user just asked to be
     * rid of, on a service that never heard about the deletion.
     *
     * @param int $threadid The thread id.
     * @return void
     */
    public static function clear(int $threadid): void {
        global $DB;

        $DB->delete_records(self::MESSAGE_TABLE, ['threadid' => $threadid]);
        $DB->update_record(self::THREAD_TABLE, (object) [
            'id' => $threadid,
            'convkey' => null,
            'lastpreview' => null,
            'timemodified' => time(),
        ]);
    }

    /**
     * Delete threads and their turns.
     *
     * @param array $conditions Conditions on the thread table.
     * @return void
     */
    public static function delete_where(array $conditions): void {
        global $DB;

        $ids = $DB->get_fieldset_select(self::THREAD_TABLE, 'id', self::where_clause($conditions), $conditions);
        if ($ids === []) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'tid');
        $DB->delete_records_select(self::MESSAGE_TABLE, "threadid $insql", $params);
        $DB->delete_records_select(self::THREAD_TABLE, "id $insql", $params);
    }

    /**
     * Trim a thread to its most recent turns.
     *
     * @param int $threadid The thread id.
     * @param int $keep How many turns to keep; 0 keeps everything.
     * @return void
     */
    public static function trim(int $threadid, int $keep): void {
        global $DB;

        if ($keep <= 0) {
            return;
        }
        $ids = $DB->get_fieldset_sql(
            'SELECT id FROM {' . self::MESSAGE_TABLE . '} WHERE threadid = :threadid ORDER BY timecreated DESC, id DESC',
            ['threadid' => $threadid]
        );
        if (count($ids) <= $keep) {
            return;
        }
        $obsolete = array_slice($ids, $keep);
        [$insql, $params] = $DB->get_in_or_equal($obsolete, SQL_PARAMS_NAMED, 'mid');
        $DB->delete_records_select(self::MESSAGE_TABLE, "id $insql", $params);
    }

    /**
     * Conditions that identify one owner's threads.
     *
     * @param string $placement Frankenstyle component of the placement.
     * @param int $instanceid The placement's instance id.
     * @param int $userid The owner, or 0 for a guest.
     * @param string|null $guestkey Guest identifier when userid is 0.
     * @return array Conditions for the thread table.
     */
    private static function owner_conditions(
        string $placement,
        int $instanceid,
        int $userid,
        ?string $guestkey
    ): array {
        $conditions = ['placement' => $placement, 'instanceid' => $instanceid];
        if ($userid > 0) {
            $conditions['userid'] = $userid;
        } else {
            $conditions['userid'] = 0;
            $conditions['guestkey'] = (string) $guestkey;
        }

        return $conditions;
    }

    /**
     * Build a WHERE clause from an equality condition map.
     *
     * @param array $conditions Field => value.
     * @return string The clause.
     */
    private static function where_clause(array $conditions): string {
        $parts = [];
        foreach (array_keys($conditions) as $field) {
            $parts[] = $field . ' = :' . $field;
        }

        return $parts === [] ? '1 = 1' : implode(' AND ', $parts);
    }
}

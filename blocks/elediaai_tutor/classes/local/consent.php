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

/**
 * Optional documented acknowledgement before the first chat turn.
 *
 * Off by default (ai_suite#203): privacy guidelines fulfil an information duty
 * (Art. 13 GDPR) and need no confirmation. An institution that nevertheless
 * wants a documented acknowledgement -- of usage rules or AI transparency
 * notes, say -- switches it on with the admin setting requireacknowledgement.
 * Then every user must confirm before the first chat turn. The confirmation is documented as one timestamped
 * row per user plus an audit event, and is enforced server-side (the UI gate
 * alone would not be evidence). The record is erased when the user is deleted
 * (event observer) or through the Privacy API; erasure re-arms the gate.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class consent {
    /** @var string Backing table. */
    public const TABLE = 'block_elediaai_tutor_consent';

    /**
     * Whether the site asks for the acknowledgement at all.
     *
     * @return bool
     */
    public static function is_required(): bool {
        return (bool) get_config('block_elediaai_tutor', 'requireacknowledgement');
    }

    /**
     * Whether the user may chat: the acknowledgement is off, or given.
     *
     * @param int $userid The user id.
     * @return bool
     */
    public static function is_satisfied(int $userid): bool {
        return !self::is_required() || self::has_consented($userid);
    }

    /**
     * Whether the user has acknowledged the privacy guidelines.
     *
     * @param int $userid The user id.
     * @return bool
     */
    public static function has_consented(int $userid): bool {
        global $DB;
        return $DB->record_exists(self::TABLE, ['userid' => $userid]);
    }

    /**
     * The timestamp of the user's acknowledgement, if any.
     *
     * @param int $userid The user id.
     * @return int|null Unix timestamp, or null when not consented.
     */
    public static function time_consented(int $userid): ?int {
        global $DB;
        $time = $DB->get_field(self::TABLE, 'timecreated', ['userid' => $userid]);
        return $time === false ? null : (int) $time;
    }

    /**
     * Record the user's acknowledgement. Idempotent.
     *
     * @param int $userid The consenting user.
     * @param \context $context Context for the audit event.
     * @return void
     */
    public static function give(int $userid, \context $context): void {
        global $DB;

        if (self::has_consented($userid)) {
            return;
        }

        try {
            $recordid = $DB->insert_record(self::TABLE, (object) [
                'userid' => $userid,
                'timecreated' => time(),
            ]);
        } catch (\dml_exception $e) {
            // Unique index hit by a concurrent request: the consent exists, done.
            if (self::has_consented($userid)) {
                return;
            }
            throw $e;
        }

        \block_elediaai_tutor\event\consent_given::create([
            'context' => $context,
            'objectid' => (int) $recordid,
            'userid' => $userid,
            'relateduserid' => $userid,
        ])->trigger();
    }

    /**
     * Enforce the gate: throw when it is on and the user has not consented.
     *
     * @param int $userid The acting user.
     * @return void
     * @throws \moodle_exception When the user has not acknowledged the guidelines.
     */
    public static function require_consent(int $userid): void {
        if (!self::is_satisfied($userid)) {
            throw new \moodle_exception('error_consentrequired', 'block_elediaai_tutor');
        }
    }

    /**
     * Erase a user's consent record (user deletion / Privacy API).
     *
     * @param int $userid The user id.
     * @return int Number of rows deleted (0 or 1).
     */
    public static function delete_for_user(int $userid): int {
        global $DB;
        $count = $DB->count_records(self::TABLE, ['userid' => $userid]);
        if ($count > 0) {
            $DB->delete_records(self::TABLE, ['userid' => $userid]);
        }
        return $count;
    }
}

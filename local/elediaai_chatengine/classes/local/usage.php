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
 * Per-user daily message counters.
 *
 * Cost governance, not rate limiting: the per-minute limiter in
 * {@see guard} stops a burst, this stops a month's budget going in an
 * afternoon. Counted per person across every placement, because the cost is
 * the site's whichever surface it was spent from.
 *
 * Only successful turns are counted. A turn that failed before an answer
 * arrived cost the site nothing it could bill for, and charging for it would
 * punish a person for an outage.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class usage {
    /** @var string The counter table. */
    public const TABLE = 'local_elediaai_chateng_usage';

    /**
     * @var int Days a daily counter is kept.
     *
     * Long enough to answer "was this person over budget last month", short
     * enough that a per-day, per-person record does not become a history of
     * when somebody worked.
     */
    public const RETENTION_DAYS = 60;

    /**
     * Messages this user has sent today.
     *
     * @param int $userid The user id.
     * @return int The count, 0 when there is no row yet.
     */
    public static function today(int $userid): int {
        global $DB;

        $count = $DB->get_field(self::TABLE, 'messagecount', [
            'userid' => $userid,
            'daykey' => self::daykey(),
        ]);

        return $count === false ? 0 : (int) $count;
    }

    /**
     * Fail when this user has reached the daily limit.
     *
     * @param int $userid The user id.
     * @param int $limit The limit; 0 means unlimited.
     * @return void
     * @throws \moodle_exception When the limit is reached.
     */
    public static function assert_within_limit(int $userid, int $limit): void {
        if ($limit <= 0) {
            return;
        }
        if (self::today($userid) >= $limit) {
            throw new \moodle_exception('error_daily_limit', 'local_elediaai_chatengine');
        }
    }

    /**
     * Count one successful turn.
     *
     * @param int $userid The user id.
     * @return void
     */
    public static function increment(int $userid): void {
        global $DB;

        if ($userid <= 0) {
            // A guest has no account to bill; the site-wide rate limit applies.
            return;
        }

        $daykey = self::daykey();

        // Incremented in the database rather than read, added to and written
        // back: two turns in the same second would otherwise both read the same
        // value and one of the two would be lost.
        $DB->execute(
            'UPDATE {' . self::TABLE . '} SET messagecount = messagecount + 1
              WHERE userid = :userid AND daykey = :daykey',
            ['userid' => $userid, 'daykey' => $daykey]
        );
        if ($DB->record_exists(self::TABLE, ['userid' => $userid, 'daykey' => $daykey])) {
            return;
        }

        try {
            $DB->insert_record(self::TABLE, (object) [
                'userid' => $userid,
                'daykey' => $daykey,
                'messagecount' => 1,
            ]);
        } catch (\dml_exception $e) {
            // Two turns raced for the first row of the day. The unique key
            // settles it; the loser increments what the winner inserted rather
            // than failing a chat turn over a counter.
            $DB->execute(
                'UPDATE {' . self::TABLE . '} SET messagecount = messagecount + 1
                  WHERE userid = :userid AND daykey = :daykey',
                ['userid' => $userid, 'daykey' => $daykey]
            );
        }
    }

    /**
     * Forget one user's counters.
     *
     * @param int $userid The user id.
     * @return int Rows removed, so a caller can report what a deletion did.
     */
    public static function delete_for_user(int $userid): int {
        global $DB;

        $count = $DB->count_records(self::TABLE, ['userid' => $userid]);
        if ($count > 0) {
            $DB->delete_records(self::TABLE, ['userid' => $userid]);
        }

        return $count;
    }

    /**
     * Today as YYYYMMDD in the server timezone.
     *
     * The server's day, not the user's: the budget being protected is the
     * site's, and a counter that rolled over at a different moment for each
     * person could not be reasoned about at all.
     *
     * @return int The day key.
     */
    public static function daykey(): int {
        return (int) date('Ymd');
    }

    /**
     * Delete counter rows older than a number of days.
     *
     * @param int $days How many days to keep; defaults to RETENTION_DAYS.
     * @return int Rows deleted.
     */
    public static function prune(int $days = self::RETENTION_DAYS): int {
        global $DB;

        $cutoff = (int) date('Ymd', time() - max(1, $days) * DAYSECS);
        $count = $DB->count_records_select(self::TABLE, 'daykey < :cutoff', ['cutoff' => $cutoff]);
        if ($count > 0) {
            $DB->delete_records_select(self::TABLE, 'daykey < :cutoff', ['cutoff' => $cutoff]);
        }

        return $count;
    }
}

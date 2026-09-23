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

/**
 * Per-user token quota enforcement for eLeDia.ai calls.
 *
 * The quota is enforced as a hard credit limit: before an external AI request
 * is sent, the estimated prompt tokens plus the configured completion buffer
 * are atomically reserved in every enforced window. Parallel requests can no
 * longer all pass the same preflight check because each reservation counts
 * against the limit until it is committed (actual usage) or released (error
 * path).
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Counts prompt + completion tokens in hourly, daily and monthly windows.
 */
final class quota_manager {
    /** @var string Table holding the per-user token counters. */
    public const TABLE = 'local_elediaai_core_usage';

    /** @var string Quota bucket for learners. */
    public const BUCKET_STUDENT = 'student';

    /** @var string Quota bucket for teachers and other editing roles. */
    public const BUCKET_TEACHER = 'teacher';

    /** @var string Rolling one-hour quota window. */
    public const WINDOW_HOUR = 'hour';

    /** @var string Rolling one-day quota window. */
    public const WINDOW_DAY = 'day';

    /** @var string Rolling one-month quota window. */
    public const WINDOW_MONTH = 'month';

    /** @var int Days a usage row is kept before prune() removes it. */
    public const RETENTION_DAYS = 90;

    /** @var int Token-equivalent cost of one 256px image by default. */
    public const DEFAULT_IMAGE_COST_256 = 1000;

    /** @var int Token-equivalent cost of one 512px image by default. */
    public const DEFAULT_IMAGE_COST_512 = 2000;

    /** @var int Token-equivalent cost of one 1024px image by default. */
    public const DEFAULT_IMAGE_COST_1024 = 4000;

    /** @var string Lock group used to serialise reservations per quota row. */
    private const LOCK_TYPE = 'local_elediaai_core_quota';

    /**
     * Static-only.
     */
    private function __construct() {
    }

    /**
     * Atomically reserve the pending request's tokens in every enforced window.
     *
     * The reserved amount is the estimated prompt tokens plus the configured
     * completion buffer. A reservation counts against the limit immediately,
     * so parallel requests cannot overdraw the window. On success the caller
     * must either {@see commit()} the actual usage or {@see release()} the
     * reservation on the error path.
     *
     * @param int $userid
     * @param int $estimatedtokens Conservative prompt estimate for the pending request.
     * @param string $component Calling component.
     * @return quota_reservation Reservation bound to the original bucket and windows.
     * @throws \moodle_exception When a window cannot take the reservation.
     */
    public static function reserve(int $userid, int $estimatedtokens, string $component): quota_reservation {
        $amount = max(0, $estimatedtokens) + self::completion_buffer();
        return self::reserve_amount($userid, $amount, $component);
    }

    /**
     * Reserve the configured cost of an action.
     *
     * The image contract uses token-equivalent credits so existing quota
     * limits remain compatible. Text callers should continue using reserve().
     *
     * @param int $userid
     * @param string $actiontype Core AI action name.
     * @param array $params Action parameters, including size/quality and numimages for images.
     * @param string $component Calling component.
     * @param int $estimatedtokens Text prompt estimate, ignored for images.
     * @return quota_reservation Reservation bound to the original bucket and windows.
     */
    public static function reserve_for_action(
        int $userid,
        string $actiontype,
        array $params,
        string $component,
        int $estimatedtokens = 0
    ): quota_reservation {
        return self::reserve_amount($userid, self::action_cost($actiontype, $params, $estimatedtokens), $component);
    }

    /**
     * Calculate the token-equivalent cost for an action.
     *
     * @param string $actiontype
     * @param array $params
     * @param int $estimatedtokens
     * @return int
     */
    public static function action_cost(string $actiontype, array $params = [], int $estimatedtokens = 0): int {
        if ($actiontype !== 'generate_image') {
            return max(0, $estimatedtokens) + self::completion_buffer();
        }

        $size = (string) ($params['size'] ?? '');
        if ($size === '') {
            $quality = (string) ($params['quality'] ?? 'standard');
            $size = match ($quality) {
                'low' => '256x256',
                'medium' => '512x512',
                default => '1024x1024',
            };
        }
        $edge = self::image_edge($size);
        $cost = (int) get_config('local_elediaai_core', 'quota_image_cost_' . $edge);
        if ($cost <= 0) {
            $cost = match ($edge) {
                256 => self::DEFAULT_IMAGE_COST_256,
                512 => self::DEFAULT_IMAGE_COST_512,
                default => self::DEFAULT_IMAGE_COST_1024,
            };
        }
        return $cost * max(1, (int) ($params['numimages'] ?? 1));
    }

    /**
     * Assert an action can be sent without mutating quota counters.
     *
     * @param int $userid
     * @param string $actiontype
     * @param array $params
     * @param int $estimatedtokens
     * @return void
     */
    public static function assert_can_request_for_action(
        int $userid,
        string $actiontype,
        array $params = [],
        int $estimatedtokens = 0
    ): void {
        self::assert_amount_can_request($userid, self::action_cost($actiontype, $params, $estimatedtokens));
    }

    /**
     * Commit the actual action cost after a successful response.
     *
     * For images, actualparams should contain the provider-confirmed size and
     * count. Text callers should continue using commit().
     *
     * @param int $userid
     * @param quota_reservation|int $reservation Reservation handle, or legacy reserved amount.
     * @param string $actiontype
     * @param array $actualparams
     * @param int $prompttokens
     * @param int $completiontokens
     * @param string $component
     * @return void
     */
    public static function commit_action(
        int $userid,
        quota_reservation|int $reservation,
        string $actiontype,
        array $actualparams,
        int $prompttokens,
        int $completiontokens,
        string $component
    ): void {
        if ($actiontype === 'generate_image') {
            self::commit($userid, $reservation, self::action_cost($actiontype, $actualparams), 0, $component);
            return;
        }
        self::commit($userid, $reservation, $prompttokens, $completiontokens, $component);
    }

    /**
     * Reserve a raw token-equivalent amount.
     *
     * @param int $userid
     * @param int $amount
     * @param string $component
     * @return quota_reservation
     */
    private static function reserve_amount(int $userid, int $amount, string $component): quota_reservation {
        $bucket = $userid > 0 ? self::role_bucket($userid) : self::BUCKET_STUDENT;
        $windowstarts = [];
        $now = time();
        foreach (self::windows() as $window) {
            $windowstarts[$window] = self::window_start($window, $now);
        }

        if ($userid <= 0 || $amount <= 0) {
            return new quota_reservation($userid, $bucket, 0, $windowstarts, []);
        }

        $reservedwindows = [];
        foreach (self::windows() as $window) {
            if (self::limit($bucket, $window) <= 0) {
                continue;
            }
            try {
                self::reserve_window(
                    $userid,
                    $bucket,
                    $window,
                    $windowstarts[$window],
                    $amount,
                    self::limit($bucket, $window),
                    $component
                );
                $reservedwindows[] = $window;
            } catch (\moodle_exception $e) {
                foreach ($reservedwindows as $reservedwindow) {
                    self::release_window(
                        $userid,
                        $bucket,
                        $reservedwindow,
                        $windowstarts[$reservedwindow],
                        $amount
                    );
                }
                throw $e;
            }
        }

        return new quota_reservation(
            $userid,
            $bucket,
            $reservedwindows === [] ? 0 : $amount,
            $windowstarts,
            $reservedwindows
        );
    }

    /**
     * Record measured or estimated usage and release the matching reservation.
     *
     * Called after a successful response: actual usage is charged and any
     * reservation that is no longer needed is returned to the window.
     *
     * @param int $userid
     * @param quota_reservation|int $reservation Reservation handle, or legacy reserved amount.
     * @param int $prompttokens Measured prompt tokens.
     * @param int $completiontokens Measured completion tokens.
     * @param string $component Calling component.
     * @return void
     */
    public static function commit(
        int $userid,
        quota_reservation|int $reservation,
        int $prompttokens,
        int $completiontokens,
        string $component
    ): void {
        if ($userid <= 0) {
            return;
        }

        $prompttokens = max(0, (int) $prompttokens);
        $completiontokens = max(0, (int) $completiontokens);
        $reservedamount = $reservation instanceof quota_reservation
            ? $reservation->amount
            : max(0, $reservation);
        if ($prompttokens === 0 && $completiontokens === 0 && $reservedamount === 0) {
            return;
        }

        if ($reservation instanceof quota_reservation && $reservation->userid !== $userid) {
            throw new \coding_exception('Quota reservation belongs to a different user.');
        }

        $bucket = $reservation instanceof quota_reservation
            ? $reservation->rolebucket
            : self::role_bucket($userid);
        foreach (self::windows() as $window) {
            $windowstart = $reservation instanceof quota_reservation
                ? $reservation->window_start($window)
                : self::window_start($window);
            $reservedtokens = $reservation instanceof quota_reservation
                && $reservation->includes_reservation($window)
                    ? $reservation->amount
                    : ($reservation instanceof quota_reservation ? 0 : $reservedamount);
            self::commit_window(
                $userid,
                $bucket,
                $window,
                $windowstart,
                $reservedtokens,
                $prompttokens,
                $completiontokens,
                $component
            );
        }
    }

    /**
     * Release a reservation without recording usage.
     *
     * @param int $userid
     * @param quota_reservation|int $reservation Reservation handle, or legacy reserved amount.
     * @return void
     */
    public static function release(int $userid, quota_reservation|int $reservation): void {
        $reservedamount = $reservation instanceof quota_reservation
            ? $reservation->amount
            : max(0, $reservation);
        if ($userid <= 0 || $reservedamount <= 0) {
            return;
        }

        if ($reservation instanceof quota_reservation && $reservation->userid !== $userid) {
            throw new \coding_exception('Quota reservation belongs to a different user.');
        }

        $bucket = $reservation instanceof quota_reservation
            ? $reservation->rolebucket
            : self::role_bucket($userid);
        foreach (self::windows() as $window) {
            if ($reservation instanceof quota_reservation && !$reservation->includes_reservation($window)) {
                continue;
            }
            $windowstart = $reservation instanceof quota_reservation
                ? $reservation->window_start($window)
                : self::window_start($window);
            self::release_window($userid, $bucket, $window, $windowstart, $reservedamount);
        }
    }

    /**
     * Enforce all configured windows before an external AI request is made.
     *
     * Non-mutating preflight kept for callers that do not use the reservation
     * flow. The check uses the same prompt estimate plus completion buffer as
     * {@see reserve()}.
     *
     * @param int $userid
     * @param int $estimatedtokens Conservative prompt estimate for the pending request.
     * @return void
     * @throws \moodle_exception
     */
    public static function assert_can_request(int $userid, int $estimatedtokens = 0): void {
        if ($userid <= 0) {
            return;
        }

        $amount = max(0, $estimatedtokens) + self::completion_buffer();
        self::assert_amount_can_request($userid, $amount);
    }

    /**
     * Check a raw token-equivalent amount against all configured windows.
     *
     * @param int $userid
     * @param int $amount
     * @return void
     */
    private static function assert_amount_can_request(int $userid, int $amount): void {
        if ($userid <= 0) {
            return;
        }
        $bucket = self::role_bucket($userid);
        foreach (self::windows() as $window) {
            $limit = self::limit($bucket, $window);
            if ($limit <= 0) {
                continue;
            }
            if (self::used_tokens($userid, $bucket, $window) + $amount > $limit) {
                self::throw_quota_exceeded($window, $limit);
            }
        }
    }

    /**
     * Record measured or estimated token usage in all quota windows.
     *
     * Backward-compatible convenience for callers without a reservation; it is
     * equivalent to {@see commit()} with zero reserved tokens.
     *
     * @param int $userid
     * @param int|null $prompttokens
     * @param int|null $completiontokens
     * @param string $component Calling component.
     * @return void
     */
    public static function record_usage(
        int $userid,
        ?int $prompttokens,
        ?int $completiontokens,
        string $component
    ): void {
        self::commit($userid, 0, (int) $prompttokens, (int) $completiontokens, $component);
    }

    /**
     * Rough tokenizer fallback for systems that do not return usage values.
     *
     * @param string $text
     * @return int
     */
    public static function estimate_tokens(string $text): int {
        $length = \core_text::strlen($text);
        if ($length <= 0) {
            return 0;
        }
        return max(1, (int) ceil($length / 4));
    }

    /**
     * How long ledger rows are kept, in days. 0 means indefinitely.
     *
     * Configurable, because this ledger is not only the quota counter but also
     * the source of the „which feature is being used" view: shortening it
     * silently shortens that report, and an operator should be able to see the
     * trade rather than discover it.
     *
     * @return int
     */
    public static function retention_days(): int {
        $value = get_config('local_elediaai_core', 'usage_retentiondays');
        if ($value === false || $value === null || $value === '') {
            return self::RETENTION_DAYS;
        }
        return max(0, (int) $value);
    }

    /**
     * Remove usage rows older than the given number of days.
     *
     * @param int $days
     * @return int
     */
    public static function prune(int $days = self::RETENTION_DAYS): int {
        global $DB;

        $cutoff = time() - ($days * DAYSECS);
        $count = $DB->count_records_select(self::TABLE, 'windowstart < :cutoff', ['cutoff' => $cutoff]);
        if ($count > 0) {
            $DB->delete_records_select(self::TABLE, 'windowstart < :cutoff', ['cutoff' => $cutoff]);
        }
        return $count;
    }

    /**
     * Delete all quota counters for a user.
     *
     * @param int $userid
     * @return int
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
     * Current token count for a quota window.
     *
     * @param int $userid
     * @param string $bucket
     * @param string $window
     * @return int
     */
    public static function used_tokens(int $userid, string $bucket, string $window): int {
        global $DB;

        $value = $DB->get_field(self::TABLE, 'totaltokens', [
            'userid' => $userid,
            'rolebucket' => $bucket,
            'windowtype' => $window,
            'windowstart' => self::window_start($window),
        ]);
        return (int) $value;
    }

    /**
     * Student/teacher bucket for quota settings.
     *
     * @param int $userid
     * @return string
     */
    public static function role_bucket(int $userid): string {
        global $DB;

        if (
            $DB->record_exists_sql(
                "SELECT 1
               FROM {role_assignments} ra
               JOIN {role} r ON r.id = ra.roleid
              WHERE ra.userid = :userid
                AND r.archetype IN ('editingteacher', 'teacher')",
                ['userid' => $userid]
            )
        ) {
            return self::BUCKET_TEACHER;
        }
        return self::BUCKET_STUDENT;
    }

    /**
     * Completion tokens reserved per request on top of the prompt estimate.
     *
     * @return int
     */
    private static function completion_buffer(): int {
        return max(0, (int) get_config('local_elediaai_core', 'quota_completion_buffer'));
    }

    /**
     * Resolve an image size to one of the configured pricing tiers.
     *
     * @param string $size
     * @return int
     */
    private static function image_edge(string $size): int {
        if (preg_match('/^(\d+)x(\d+)$/', strtolower(trim($size)), $matches)) {
            $edge = max((int) $matches[1], (int) $matches[2]);
        } else {
            $edge = 1024;
        }
        return $edge <= 256 ? 256 : ($edge <= 512 ? 512 : 1024);
    }

    /**
     * Configured token limit for a bucket/window.
     *
     * @param string $bucket
     * @param string $window
     * @return int
     */
    private static function limit(string $bucket, string $window): int {
        return max(0, (int) get_config('local_elediaai_core', 'quota_' . $bucket . '_' . $window));
    }

    /**
     * Supported quota windows.
     *
     * @return string[]
     */
    private static function windows(): array {
        return [
            self::WINDOW_HOUR,
            self::WINDOW_DAY,
            self::WINDOW_MONTH,
        ];
    }

    /**
     * Start timestamp for a quota window.
     *
     * @param string $window
     * @param int|null $timestamp Timestamp to classify, defaults to now.
     * @return int
     */
    private static function window_start(string $window, ?int $timestamp = null): int {
        $timestamp ??= time();
        if ($window === self::WINDOW_HOUR) {
            return (int) (floor($timestamp / HOURSECS) * HOURSECS);
        }
        if ($window === self::WINDOW_MONTH) {
            return make_timestamp((int) date('Y', $timestamp), (int) date('n', $timestamp), 1);
        }
        return make_timestamp(
            (int) date('Y', $timestamp),
            (int) date('n', $timestamp),
            (int) date('j', $timestamp)
        );
    }

    /**
     * Throw the quota-exceeded exception for a window.
     *
     * @param string $window
     * @param int $limit
     * @return void
     * @throws \moodle_exception
     */
    private static function throw_quota_exceeded(string $window, int $limit): void {
        $a = (object) [
            'limit' => $limit,
            'window' => get_string('quota_window_' . $window, 'local_elediaai_core'),
        ];
        throw new \moodle_exception('error_quota_token_exceeded', 'local_elediaai_core', '', $a);
    }

    /**
     * Atomically reserve tokens in one window.
     *
     * The read-check-write sequence runs under a per-row lock so that parallel
     * reservations see each other's outstanding reservations.
     *
     * @param int $userid
     * @param string $bucket
     * @param string $window
     * @param int $windowstart
     * @param int $amount
     * @param int $limit
     * @param string $component
     * @return void
     * @throws \moodle_exception
     */
    private static function reserve_window(
        int $userid,
        string $bucket,
        string $window,
        int $windowstart,
        int $amount,
        int $limit,
        string $component
    ): void {
        global $DB;

        $params = [
            'userid' => $userid,
            'rolebucket' => $bucket,
            'windowtype' => $window,
            'windowstart' => $windowstart,
        ];

        $lock = self::quota_lock($userid, $bucket, $window);
        if (!$lock) {
            throw new \moodle_exception('error_quota_unavailable', 'local_elediaai_core');
        }

        try {
            $row = $DB->get_record(self::TABLE, $params);
            $used = ((int) ($row->totaltokens ?? 0)) + ((int) ($row->reservedtokens ?? 0));
            if ($used + $amount > $limit) {
                self::throw_quota_exceeded($window, $limit);
            }

            $now = time();
            if ($row) {
                $row->reservedtokens = (int) $row->reservedtokens + $amount;
                $row->component = $component;
                $row->timemodified = $now;
                $DB->update_record(self::TABLE, $row);
            } else {
                $DB->insert_record(self::TABLE, (object) ($params + [
                    'prompttokens' => 0,
                    'completiontokens' => 0,
                    'totaltokens' => 0,
                    'reservedtokens' => $amount,
                    'requestcount' => 0,
                    'component' => $component,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]));
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Record usage in one window and release the matching reservation.
     *
     * @param int $userid
     * @param string $bucket
     * @param string $window
     * @param int $windowstart
     * @param int $reservedtokens
     * @param int $prompttokens
     * @param int $completiontokens
     * @param string $component
     * @return void
     */
    private static function commit_window(
        int $userid,
        string $bucket,
        string $window,
        int $windowstart,
        int $reservedtokens,
        int $prompttokens,
        int $completiontokens,
        string $component
    ): void {
        global $DB;

        $params = [
            'userid' => $userid,
            'rolebucket' => $bucket,
            'windowtype' => $window,
            'windowstart' => $windowstart,
        ];
        $now = time();
        $total = $prompttokens + $completiontokens;

        $DB->execute(
            'UPDATE {' . self::TABLE . '}
                SET prompttokens = prompttokens + :prompttokens,
                    completiontokens = completiontokens + :completiontokens,
                    totaltokens = totaltokens + :totaltokens,
                    reservedtokens = CASE
                        WHEN reservedtokens - :reservedrelease < 0 THEN 0
                        ELSE reservedtokens - :reservedrelease2
                    END,
                    requestcount = requestcount + 1,
                    component = :component,
                    timemodified = :timemodified
              WHERE userid = :userid
                AND rolebucket = :rolebucket
                AND windowtype = :windowtype
                AND windowstart = :windowstart',
            $params + [
                'prompttokens' => $prompttokens,
                'completiontokens' => $completiontokens,
                'totaltokens' => $total,
                'reservedrelease' => $reservedtokens,
                'reservedrelease2' => $reservedtokens,
                'component' => $component,
                'timemodified' => $now,
            ]
        );

        if ($DB->record_exists(self::TABLE, $params)) {
            return;
        }

        try {
            $DB->insert_record(self::TABLE, (object) ($params + [
                'prompttokens' => $prompttokens,
                'completiontokens' => $completiontokens,
                'totaltokens' => $total,
                'reservedtokens' => 0,
                'requestcount' => 1,
                'component' => $component,
                'timecreated' => $now,
                'timemodified' => $now,
            ]));
        } catch (\dml_exception $e) {
            $DB->execute(
                'UPDATE {' . self::TABLE . '}
                    SET prompttokens = prompttokens + :prompttokens,
                        completiontokens = completiontokens + :completiontokens,
                        totaltokens = totaltokens + :totaltokens,
                        reservedtokens = CASE
                            WHEN reservedtokens - :reservedrelease < 0 THEN 0
                            ELSE reservedtokens - :reservedrelease2
                        END,
                        requestcount = requestcount + 1,
                        component = :component,
                        timemodified = :timemodified
                  WHERE userid = :userid
                    AND rolebucket = :rolebucket
                    AND windowtype = :windowtype
                    AND windowstart = :windowstart',
                $params + [
                    'prompttokens' => $prompttokens,
                    'completiontokens' => $completiontokens,
                    'totaltokens' => $total,
                    'reservedrelease' => $reservedtokens,
                    'reservedrelease2' => $reservedtokens,
                    'component' => $component,
                    'timemodified' => $now,
                ]
            );
        }
    }

    /**
     * Release a reservation in one window.
     *
     * @param int $userid
     * @param string $bucket
     * @param string $window
     * @param int $windowstart
     * @param int $amount
     * @return void
     */
    private static function release_window(
        int $userid,
        string $bucket,
        string $window,
        int $windowstart,
        int $amount
    ): void {
        global $DB;

        $DB->execute(
            'UPDATE {' . self::TABLE . '}
                SET reservedtokens = CASE WHEN reservedtokens - :amountrelease < 0 THEN 0 ELSE reservedtokens - :amountrelease2 END,
                    timemodified = :timemodified
              WHERE userid = :userid
                AND rolebucket = :rolebucket
                AND windowtype = :windowtype
                AND windowstart = :windowstart',
            [
                'userid' => $userid,
                'rolebucket' => $bucket,
                'windowtype' => $window,
                'windowstart' => $windowstart,
                'amountrelease' => $amount,
                'amountrelease2' => $amount,
                'timemodified' => time(),
            ]
        );
    }

    /**
     * Per-row lock serialising reservations for one quota row.
     *
     * @param int $userid
     * @param string $bucket
     * @param string $window
     * @return \core\lock\lock|null
     */
    private static function quota_lock(int $userid, string $bucket, string $window): ?\core\lock\lock {
        $factory = \core\lock\lock_config::get_lock_factory(self::LOCK_TYPE);
        return $factory->get_lock('quota_' . $userid . '_' . $bucket . '_' . $window, 5);
    }
}

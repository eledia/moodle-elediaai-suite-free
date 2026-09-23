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

use cache;

/**
 * The checks every turn passes before anything leaves this site.
 *
 * Length and burst rate, in one place for every placement. A limit that three
 * surfaces each enforced would be three limits, and the one that was forgotten
 * would be the one that mattered.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class guard {
    /**
     * Validate and normalise an incoming message.
     *
     * @param string $message The raw message.
     * @return string The trimmed message.
     * @throws \moodle_exception When it is empty or too long.
     */
    public static function validate_message(string $message): string {
        $message = trim($message);
        if ($message === '') {
            throw new \moodle_exception('error_message_empty', 'local_elediaai_chatengine');
        }
        $max = connection::max_message_length();
        if (\core_text::strlen($message) > $max) {
            throw new \moodle_exception('error_message_too_long', 'local_elediaai_chatengine', '', $max);
        }

        return $message;
    }

    /**
     * Enforce the per-minute request limit for one requester.
     *
     * Counters live in the application cache so they survive between requests
     * and are shared across a cluster. A limit of 0 disables the check.
     *
     * @param int $userid The acting user id, or 0 for a guest.
     * @param string|null $guestkey Guest identifier when userid is 0.
     * @return void
     * @throws \moodle_exception When the limit is exceeded.
     */
    public static function enforce_rate_limit(int $userid, ?string $guestkey = null): void {
        $perminute = connection::rate_limit_per_minute();
        if ($perminute === 0) {
            return;
        }

        // A guest has no account, so the key is their session-scoped handle.
        // Without one there is nothing to count against and the request is
        // refused rather than counted against everybody at once.
        $identity = $userid > 0 ? 'u' . $userid : 'g' . (string) $guestkey;
        if ($identity === 'g') {
            throw new \moodle_exception('error_rate_limited', 'local_elediaai_chatengine', '', 60);
        }

        $cache = cache::make('local_elediaai_chatengine', 'ratelimit');
        $window = (int) floor(time() / MINSECS);
        $key = $identity . '_m_' . $window;
        $count = ((int) ($cache->get($key) ?: 0)) + 1;
        $cache->set($key, $count);

        if ($count > $perminute) {
            throw new \moodle_exception(
                'error_rate_limited',
                'local_elediaai_chatengine',
                '',
                MINSECS - (time() % MINSECS)
            );
        }
    }
}

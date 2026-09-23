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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

declare(strict_types=1);

namespace local_literag\local;

use cache;
use local_literag\local\mcp\tool_exception;

/**
 * Atomic per-user request limits for paid tutor chat turns.
 *
 * Counters are scoped to the tenant and user. The minute limit absorbs bursts;
 * the daily ceiling bounds worst-case provider spend even for a valid token.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class rate_limiter {
    /**
     * Count one valid tutor turn and reject exhausted windows.
     *
     * Rejected calls are still counted so repeated retries cannot extend a burst.
     *
     * @param int $userid Authenticated Moodle user id.
     * @param int|null $now Timestamp override for deterministic tests.
     * @throws tool_exception When either configured window is exhausted.
     */
    public static function check(int $userid, ?int $now = null): void {
        $now ??= time();
        $perminute = config::rate_limit_per_minute();
        $perday = config::rate_limit_per_day();
        $scope = substr(sha1(tenant::id() . ':' . $userid), 0, 24);
        $minutekey = 'm_' . $scope . '_' . (int) floor($now / MINSECS);
        $daykey = 'd_' . $scope . '_' . (int) floor($now / DAYSECS);
        $cache = cache::make('local_literag', 'tutorrate');

        $haslock = false;
        $lockkey = 'l_' . $scope;
        try {
            $haslock = (bool) $cache->acquire_lock($lockkey);
        } catch (\Throwable $e) {
            debugging('local_literag rate-limit lock unavailable: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        try {
            $minutecount = ((int) ($cache->get($minutekey) ?: 0)) + 1;
            $daycount = ((int) ($cache->get($daykey) ?: 0)) + 1;
            $cache->set($minutekey, $minutecount);
            $cache->set($daykey, $daycount);
        } finally {
            if ($haslock) {
                $cache->release_lock($lockkey);
            }
        }

        if ($minutecount > $perminute || $daycount > $perday) {
            throw new tool_exception('tutor rate limit exceeded; retry later', -32000);
        }
    }
}

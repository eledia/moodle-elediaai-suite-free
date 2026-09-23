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

namespace local_literag;

use local_literag\local\mcp\tool_exception;
use local_literag\local\rate_limiter;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for tutor cost-control windows.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(rate_limiter::class)]
final class rate_limiter_test extends \advanced_testcase {
    /**
     * The first N turns pass and the next turn is rejected.
     */
    public function test_minute_limit_rejects_next_turn(): void {
        $this->resetAfterTest();
        set_config('rate_limit_per_minute', 2, 'local_literag');
        set_config('rate_limit_per_day', 10, 'local_literag');
        \cache::make('local_literag', 'tutorrate')->purge();

        rate_limiter::check(17, 1_700_000_000);
        rate_limiter::check(17, 1_700_000_000);

        $this->expectException(tool_exception::class);
        rate_limiter::check(17, 1_700_000_000);
    }

    /**
     * Counters are isolated per Moodle user.
     */
    public function test_users_have_independent_windows(): void {
        $this->resetAfterTest();
        set_config('rate_limit_per_minute', 1, 'local_literag');
        set_config('rate_limit_per_day', 10, 'local_literag');
        \cache::make('local_literag', 'tutorrate')->purge();

        rate_limiter::check(17, 1_700_000_000);
        rate_limiter::check(18, 1_700_000_000);
        $this->addToAssertionCount(1);
    }

    /**
     * The daily ceiling remains effective across minute windows.
     */
    public function test_daily_limit_spans_minutes(): void {
        $this->resetAfterTest();
        set_config('rate_limit_per_minute', 10, 'local_literag');
        set_config('rate_limit_per_day', 2, 'local_literag');
        \cache::make('local_literag', 'tutorrate')->purge();

        rate_limiter::check(17, 1_700_000_000);
        rate_limiter::check(17, 1_700_000_061);

        $this->expectException(tool_exception::class);
        rate_limiter::check(17, 1_700_000_122);
    }
}

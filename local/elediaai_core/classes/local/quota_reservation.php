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

/**
 * Quota reservation value object.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core\local;

/**
 * Carries the exact bucket and windows selected when quota was reserved.
 */
final class quota_reservation {
    /**
     * Constructor.
     *
     * @param int $userid
     * @param string $rolebucket
     * @param int $amount
     * @param array<string, int> $windowstarts
     * @param string[] $reservedwindows
     */
    public function __construct(
        /** @var int Constructor-promoted value. */
        public readonly int $userid,
        /** @var string Constructor-promoted value. */
        public readonly string $rolebucket,
        /** @var int Constructor-promoted value. */
        public readonly int $amount,
        /** @var array Constructor-promoted value. */
        private readonly array $windowstarts,
        /** @var array Constructor-promoted value. */
        private readonly array $reservedwindows,
    ) {
    }

    /**
     * Original start timestamp for a quota window.
     *
     * @param string $window
     * @return int
     */
    public function window_start(string $window): int {
        if (!array_key_exists($window, $this->windowstarts)) {
            throw new \coding_exception('Unknown quota window: ' . $window);
        }

        return $this->windowstarts[$window];
    }

    /**
     * Whether the amount was reserved in this window.
     *
     * Unlimited windows are still captured for usage accounting, but do not
     * carry a reservation that commit or release needs to subtract.
     *
     * @param string $window
     * @return bool
     */
    public function includes_reservation(string $window): bool {
        return in_array($window, $this->reservedwindows, true);
    }
}

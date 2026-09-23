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
 * The two ways an answer can be produced.
 *
 * LLM-only is a mode, not a backend. Both adapters can answer without
 * retrieval; which one is asked follows the active sink, and the mode travels
 * with the request. Keeping this apart is what stops a placement from
 * silently reaching a different service than the one its course content was
 * written to.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mode {
    /** @var string Retrieval over the indexed course material. */
    public const GROUNDED = 'grounded';

    /** @var string The model answers on its own; no retrieval. */
    public const UNGROUNDED = 'ungrounded';

    /**
     * Whether a value is one of the two modes.
     *
     * @param string $mode The candidate.
     * @return bool
     */
    public static function is_valid(string $mode): bool {
        return $mode === self::GROUNDED || $mode === self::UNGROUNDED;
    }

    /**
     * Normalise a stored or submitted value, defaulting to grounded.
     *
     * Grounded is the safe default: it is the mode every adapter supports, so
     * a corrupted setting degrades to the answer that cites its sources rather
     * than to a free-running model.
     *
     * @param string|null $mode The candidate.
     * @return string A valid mode.
     */
    public static function normalise(?string $mode): string {
        return $mode !== null && self::is_valid($mode) ? $mode : self::GROUNDED;
    }

    /**
     * The options a placement may offer, given what the backend supports.
     *
     * A mode the adapter does not report is not offered at all, so an
     * administrator sees the restriction in the settings form instead of
     * meeting it as a runtime surprise.
     *
     * @param \local_elediaai_chatengine\adapter\capabilities $capabilities Adapter capabilities.
     * @return array<string, string> Mode => translated label.
     */
    public static function menu(\local_elediaai_chatengine\adapter\capabilities $capabilities): array {
        $menu = [self::GROUNDED => get_string('mode_grounded', 'local_elediaai_chatengine')];
        if ($capabilities->supportsungrounded) {
            $menu[self::UNGROUNDED] = get_string('mode_ungrounded', 'local_elediaai_chatengine');
        }
        return $menu;
    }
}

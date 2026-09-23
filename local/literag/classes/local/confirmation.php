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

namespace local_literag\local;

/**
 * Detects an explicit affirmative ("yes, send it") confirming a pending action.
 *
 * Deliberately conservative: only a short, clearly-affirmative message counts, so
 * a longer follow-up question is never mistaken for a confirmation to send.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class confirmation {
    /** @var string[] Exact-match affirmatives (English + German). */
    private const AFFIRMATIVES = [
        'yes', 'y', 'yeah', 'yep', 'yes please', 'ok', 'okay', 'sure', 'do it',
        'send', 'send it', 'send the message', 'confirm', 'confirmed', 'go ahead',
        'please do', 'ja', 'jawohl', 'ja bitte', 'senden', 'abschicken', 'bestätigen',
        'bestätigt', 'mach das', 'los',
    ];

    /** @var string[] Allowed short leading affirmatives ("yes, send it"). */
    private const PREFIXES = ['yes', 'ja', 'confirm', 'bestätig', 'send', 'senden', 'ok'];

    /**
     * Whether the message is a clear, standalone confirmation to proceed.
     *
     * @param string $message The learner's message.
     * @return bool
     */
    public static function is_yes(string $message): bool {
        $normalised = \core_text::strtolower(trim($message));
        $normalised = trim((string) preg_replace('/[!.,\s]+$/u', '', $normalised));
        if ($normalised === '') {
            return false;
        }
        if (in_array($normalised, self::AFFIRMATIVES, true)) {
            return true;
        }
        // A short message that starts with an affirmative ("yes, send it now").
        if (\core_text::strlen($normalised) <= 30) {
            foreach (self::PREFIXES as $prefix) {
                if (strncmp($normalised, $prefix, strlen($prefix)) === 0) {
                    return true;
                }
            }
        }
        return false;
    }
}

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
 * Salted pseudonym for the anonymous turn log.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_elediaai_core\local;

/**
 * Turns a user id into a key that counts people without naming one.
 *
 * The turn log deliberately holds no user id (operator decision 05.09.2026).
 * That alone, however, would cost two things the didactic report needs and the
 * data-protection side is owed:
 *
 * - **Counting.** „Five questions from five people" is a course problem, „five
 *   questions from one" is a single case. Without a per-person key the report
 *   cannot tell them apart.
 * - **Erasure.** A free-text prompt can carry personal data whatever the
 *   schema says. Without a key there is no way to answer „delete what is
 *   stored about me", because nothing in the row points at the person.
 *
 * The key is `sha256(userid | site salt)`. It is never displayed, no surface
 * and no MCP tool resolves it, and the salt never leaves the server. That makes
 * the data **pseudonymous, not anonymous** — which is the honest label, and at
 * the same time the reason erasure works: the key is recomputed from the user
 * id on the way in, never looked up on the way out.
 *
 * The salt is created once, in the upgrade step. The runtime fallback exists
 * for an installation that somehow lost it and takes a lock while it writes:
 * two requests each minting their own salt would split one person into two
 * keys, and nothing would ever report that.
 */
final class pseudonym {
    /** @var string Config key holding the site salt. */
    public const SALT_SETTING = 'turn_askersalt';

    /** @var int Salt length in characters. */
    private const SALT_LENGTH = 40;

    /** @var string Lock factory type for the one-time salt creation. */
    private const LOCK_TYPE = 'local_elediaai_core_pseudonym';

    /**
     * Static-only.
     */
    private function __construct() {
    }

    /**
     * The key for a user, or the empty string when there is no person.
     *
     * @param int $userid
     * @return string 64 hex characters, or ''.
     */
    public static function for_user(int $userid): string {
        if ($userid <= 0) {
            return '';
        }

        return hash('sha256', $userid . '|' . self::salt());
    }

    /**
     * Create the site salt if it is missing, and return it.
     *
     * Called by the upgrade step so the value exists before the first turn is
     * written. Safe to call again; an existing salt is never replaced, because
     * replacing it would orphan every key already stored.
     *
     * @return string
     */
    public static function ensure_salt(): string {
        return self::salt();
    }

    /**
     * The site salt, minted on first use.
     *
     * @return string
     */
    private static function salt(): string {
        $salt = get_config('local_elediaai_core', self::SALT_SETTING);
        if (is_string($salt) && $salt !== '') {
            return $salt;
        }

        $factory = \core\lock\lock_config::get_lock_factory(self::LOCK_TYPE);
        $lock = $factory->get_lock(self::SALT_SETTING, 10);
        if (!$lock) {
            // Someone else is minting it right now. Waiting longer would block
            // a turn that has already been answered; read once more and accept
            // whatever is there.
            $salt = get_config('local_elediaai_core', self::SALT_SETTING);
            return is_string($salt) && $salt !== '' ? $salt : '';
        }

        try {
            // Re-read inside the lock: the holder before us may have just set it.
            $salt = get_config('local_elediaai_core', self::SALT_SETTING);
            if (is_string($salt) && $salt !== '') {
                return $salt;
            }
            $salt = random_string(self::SALT_LENGTH);
            set_config(self::SALT_SETTING, $salt, 'local_elediaai_core');
            return $salt;
        } finally {
            $lock->release();
        }
    }
}

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

namespace block_elediaai_tutor;

use block_elediaai_tutor\local\deletion_service;

/**
 * Core event observers.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /** @var bool[] Users whose complete tutor deletion finished before core deleted the account. */
    private static array $prepareddeletions = [];

    /**
     * Delete all tutor data while the account and its user-scoped token are still active.
     *
     * Moodle invalidates external tokens and marks the account deleted before it
     * emits the user_deleted event. The before_user_deleted hook is therefore the
     * only safe point at which the external tutor can still authenticate the user.
     * Failures must never block Moodle's account deletion; the later event retries
     * the complete local cleanup as a fallback.
     *
     * @param \core_user\hook\before_user_deleted $hook The pre-deletion hook.
     * @return void
     */
    public static function before_user_deleted(\core_user\hook\before_user_deleted $hook): void {
        $userid = (int) $hook->user->id;
        try {
            deletion_service::delete_all_for_user($userid, \core\context\system::instance());
            self::$prepareddeletions[$userid] = true;
        } catch (\Throwable $exception) {
            debugging(
                'block_elediaai_tutor: pre-account-deletion cleanup failed: ' . $exception->getMessage(),
                DEBUG_DEVELOPER
            );
        }
    }

    /**
     * Ensure complete local tutor cleanup after Moodle deletes the account.
     *
     * Normally the pre-deletion hook has already removed local and external data.
     * Calling the shared deletion service here is the fallback for a failed or
     * unavailable pre-hook. External deletion may then fail because Moodle has
     * already invalidated the user's token, but local cleanup still completes.
     *
     * @param \core\event\user_deleted $event The deletion event.
     * @return void
     */
    public static function user_deleted(\core\event\user_deleted $event): void {
        $userid = (int) $event->objectid;
        if (!empty(self::$prepareddeletions[$userid])) {
            unset(self::$prepareddeletions[$userid]);
            return;
        }
        deletion_service::delete_all_for_user($userid, \core\context\system::instance());
    }
}

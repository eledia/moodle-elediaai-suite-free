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
 * Opt-in long-term memory store.
 *
 * Strict consent: memory is off by default and every read/write is gated on the
 * per-request ltm_enabled flag; opting out erases everything already stored.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class memory_store {
    /**
     * Whether memory may be used for this request.
     *
     * @param bool|null $ltmenabled The request's ltm_enabled value (null ⇒ false).
     * @return bool
     */
    public static function allowed(?bool $ltmenabled): bool {
        return config::enable_memory() && $ltmenabled === true;
    }

    /**
     * Fetch a user's memory facts as a short list of strings.
     *
     * @param int $userid
     * @return string[]
     */
    public function facts_for_user(int $userid): array {
        global $DB;
        $rows = $DB->get_records('local_literag_memory', ['userid' => $userid], 'timemodified DESC', 'id, mvalue', 0, 20);
        $facts = [];
        foreach ($rows as $row) {
            $value = trim((string) $row->mvalue);
            if ($value !== '') {
                $facts[] = $value;
            }
        }
        return $facts;
    }

    /**
     * Delete all stored memory for a user.
     *
     * @param int $userid
     * @return int Number of memory rows deleted.
     */
    public function delete_all_for_user(int $userid): int {
        global $DB;
        $count = $DB->count_records('local_literag_memory', ['userid' => $userid]);
        $DB->delete_records('local_literag_memory', ['userid' => $userid]);
        return $count;
    }
}

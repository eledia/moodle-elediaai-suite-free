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

namespace local_elediaai_chatengine\placement;

/**
 * Opt-in: the placement decides per instance whether a quiz in progress locks it.
 *
 * Without it a placement follows the engine's site setting `quizlock`
 * ({@see \local_elediaai_chatengine\local\quiz_lock}). A placement that
 * implements it can switch the lock on or off for one instance, or return
 * null to follow the site setting after all.
 *
 * A separate interface rather than a method on {@see placement}, for the same
 * reason as {@see user_request_framing}: a placement that says nothing keeps
 * working unchanged, including one outside this repository.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface quiz_lock_policy {
    /**
     * Whether an open quiz attempt locks this instance.
     *
     * @param int $instanceid The placement's instance id, as the engine passes it.
     * @return bool|null True to lock, false not to, null for the site setting.
     */
    public function quiz_lock(int $instanceid): ?bool;
}

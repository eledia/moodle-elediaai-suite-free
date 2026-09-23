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
 * Enforces Moodle access control on retrieved chunks for a specific user.
 *
 * The security invariant: a chunk whose course module is not visible to the
 * requesting user is NEVER returned. Visibility is evaluated with
 * {@see get_fast_modinfo()} for that user, whose {@code $cm->uservisible} already
 * folds in stealth/visibility, availability restrictions and access. Course
 * access is gated separately with {@see can_access_course()}.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class permission_filter {
    /** @var int The user whose permissions are enforced. */
    private int $userid;

    /** @var \stdClass The user record. */
    private \stdClass $user;

    /** @var array<int, \course_modinfo|null> Per-course modinfo cache. */
    private array $modinfo = [];

    /** @var array<int, bool> Per-course access cache. */
    private array $courseaccess = [];

    /**
     * Constructor.
     *
     * @param \stdClass $user The full user record to enforce permissions for.
     */
    public function __construct(\stdClass $user) {
        $this->user = $user;
        $this->userid = (int) $user->id;
    }

    /**
     * Return only the chunks the user is allowed to see, order preserved.
     *
     * @param array $chunks Chunk records (each needs ->courseid and ->cmid).
     * @return array Filtered chunk records.
     */
    public function filter(array $chunks): array {
        $allowed = [];
        foreach ($chunks as $chunk) {
            if ($this->can_see($chunk)) {
                $allowed[] = $chunk;
            }
        }
        return $allowed;
    }

    /**
     * Whether the user may see a single chunk.
     *
     * @param \stdClass $chunk Chunk record with ->courseid and ->cmid.
     * @return bool
     */
    public function can_see(\stdClass $chunk): bool {
        $courseid = (int) $chunk->courseid;
        $cmid = (int) $chunk->cmid;
        if ($courseid <= 0 || $cmid <= 0) {
            // Without a resolvable course module we cannot prove access — deny.
            return false;
        }

        if (!$this->can_access_course($courseid)) {
            return false;
        }

        $modinfo = $this->get_modinfo($courseid);
        if ($modinfo === null) {
            return false;
        }

        try {
            $cm = $modinfo->get_cm($cmid);
        } catch (\moodle_exception $e) {
            // The module no longer exists (stale chunk) — deny.
            return false;
        }

        return $cm->uservisible === true;
    }

    /**
     * Course-level access gate (enrolment / visibility), cached per course.
     *
     * @param int $courseid
     * @return bool
     */
    private function can_access_course(int $courseid): bool {
        if (!array_key_exists($courseid, $this->courseaccess)) {
            $access = false;
            try {
                $course = get_course($courseid);
                $access = can_access_course($course, $this->user, '', true);
            } catch (\moodle_exception $e) {
                $access = false;
            }
            $this->courseaccess[$courseid] = $access;
        }
        return $this->courseaccess[$courseid];
    }

    /**
     * Get (and cache) modinfo evaluated for this user.
     *
     * @param int $courseid
     * @return \course_modinfo|null
     */
    private function get_modinfo(int $courseid): ?\course_modinfo {
        if (!array_key_exists($courseid, $this->modinfo)) {
            try {
                $this->modinfo[$courseid] = get_fast_modinfo($courseid, $this->userid);
            } catch (\moodle_exception $e) {
                $this->modinfo[$courseid] = null;
            }
        }
        return $this->modinfo[$courseid];
    }
}

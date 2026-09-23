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

namespace local_elediaai_sources;

/**
 * Per-activity ingestion decisions.
 *
 * Stores only what a teacher explicitly decided; an activity without a row is
 * "untouched" and falls back to the site-wide default mode. That split is what
 * makes the mode switch safe: flipping the site to opt-in merely stops
 * ingesting untouched activities — it deletes nothing, because deletions are
 * driven solely by explicit exclusions. An explicit decision also survives any
 * later mode change.
 *
 * This gate is subordinate to {@see course_gate}: it only means anything
 * inside a course that is released for ingestion in the first place.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity_gate {
    /** @var string Backing table. */
    private const TABLE = 'local_elediaai_sources_cm';

    /** @var string Untouched activities are ingested (today's behaviour). */
    public const MODE_OPTOUT = 'optout';

    /** @var string Untouched activities are skipped until selected. */
    public const MODE_OPTIN = 'optin';

    /**
     * The configured default mode for untouched activities.
     *
     * @return string One of the MODE_* constants.
     */
    public static function mode(): string {
        $mode = (string) get_config('local_elediaai_sources', 'activitydefault');
        return $mode === self::MODE_OPTIN ? self::MODE_OPTIN : self::MODE_OPTOUT;
    }

    /**
     * React to a change of the default mode.
     *
     * Called from the setting's updated-callback, where the previous value is
     * already overwritten — a shadow config carries it across. Only the
     * opt-in→opt-out direction converges automatically (undecided activities
     * are queued for ingestion); the other direction deliberately does
     * nothing, because switching to opt-in must never remove content.
     *
     * @return void
     */
    public static function note_mode_change(): void {
        $previous = (string) get_config('local_elediaai_sources', 'activitydefault_shadow');
        $current = self::mode();

        if ($previous === self::MODE_OPTIN && $current === self::MODE_OPTOUT) {
            \core\task\manager::queue_adhoc_task(new task\converge_undecided_task(), true);
        }

        set_config('activitydefault_shadow', $current, 'local_elediaai_sources');
    }

    /**
     * Whether this activity should be ingested.
     *
     * An explicit decision wins; otherwise the site mode fills the gap.
     *
     * @param int $cmid The course module id.
     * @return bool
     */
    public static function should_ingest_cm(int $cmid): bool {
        global $DB;

        $included = $DB->get_field(self::TABLE, 'included', ['cmid' => $cmid]);
        if ($included !== false) {
            return (int) $included === 1;
        }
        return self::mode() === self::MODE_OPTOUT;
    }

    /**
     * Whether a teacher explicitly excluded this activity.
     *
     * Only this state triggers removal from the index. An untouched activity
     * in opt-in mode is merely skipped, so switching the mode never deletes.
     *
     * @param int $cmid The course module id.
     * @return bool
     */
    public static function is_explicitly_excluded(int $cmid): bool {
        global $DB;

        $included = $DB->get_field(self::TABLE, 'included', ['cmid' => $cmid]);
        return $included !== false && (int) $included === 0;
    }

    /**
     * Record an explicit decision for an activity.
     *
     * @param int $courseid The course id.
     * @param int $cmid The course module id.
     * @param bool $included True to include, false to exclude.
     * @return void
     */
    public static function set_included(int $courseid, int $cmid, bool $included): void {
        global $DB, $USER;

        $values = [
            'courseid' => $courseid,
            'included' => $included ? 1 : 0,
            'usermodified' => (int) $USER->id,
            'timemodified' => time(),
        ];

        $existing = $DB->get_record(self::TABLE, ['cmid' => $cmid]);
        if ($existing) {
            $DB->update_record(self::TABLE, (object) (['id' => $existing->id] + $values));
            return;
        }
        $DB->insert_record(self::TABLE, (object) (['cmid' => $cmid] + $values));
    }

    /**
     * Drop the explicit decision so the activity follows the mode again.
     *
     * @param int $cmid The course module id.
     * @return void
     */
    public static function clear(int $cmid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['cmid' => $cmid]);
    }

    /**
     * All explicit decisions of a course, keyed by cmid.
     *
     * @param int $courseid The course id.
     * @return array<int, bool> cmid => included.
     */
    public static function decisions(int $courseid): array {
        global $DB;

        $decisions = [];
        foreach ($DB->get_records(self::TABLE, ['courseid' => $courseid], '', 'cmid, included') as $row) {
            $decisions[(int) $row->cmid] = (int) $row->included === 1;
        }
        return $decisions;
    }

    /**
     * Forget the decision row of a deleted course module.
     *
     * @param int $cmid The course module id.
     * @return void
     */
    public static function forget_cm(int $cmid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['cmid' => $cmid]);
    }

    /**
     * Forget every decision row of a deleted course.
     *
     * @param int $courseid The course id.
     * @return void
     */
    public static function forget_course(int $courseid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['courseid' => $courseid]);
    }
}

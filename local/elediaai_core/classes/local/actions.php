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
 * Read API over the AI action log.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_elediaai_core\local;

use stdClass;

/**
 * What the action log can say -- and what a person may ask about themselves.
 */
final class actions {
    /** @var int Default period in days. */
    public const DEFAULT_DAYS = 30;

    /** @var int Default retention in days. */
    public const DEFAULT_RETENTION_DAYS = 730;

    /**
     * Static-only.
     */
    private function __construct() {
    }

    /**
     * Headline numbers.
     *
     * @param int $courseid 0 for site-wide.
     * @param int $days Period in days.
     * @return stdClass total, writes, failed, people
     */
    public static function summary(int $courseid = 0, int $days = self::DEFAULT_DAYS): stdClass {
        global $DB;

        $where = 'timecreated >= :cutoff';
        $params = ['cutoff' => time() - (max(1, $days) * DAYSECS)];
        if ($courseid > 0) {
            $where .= ' AND courseid = :courseid';
            $params['courseid'] = $courseid;
        }

        $row = $DB->get_record_sql(
            "SELECT COUNT(1) AS total,
                    SUM(CASE WHEN iswrite = 1 THEN 1 ELSE 0 END) AS writes,
                    SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) AS failed,
                    COUNT(DISTINCT CASE WHEN userid > 0 THEN userid END) AS people
               FROM {" . action_recorder::TABLE . "}
              WHERE {$where}",
            $params
        );

        $out = new stdClass();
        $out->total = (int) ($row->total ?? 0);
        $out->writes = (int) ($row->writes ?? 0);
        $out->failed = (int) ($row->failed ?? 0);
        $out->people = (int) ($row->people ?? 0);
        $out->writepct = $out->total > 0 ? round($out->writes * 100 / $out->total, 1) : 0.0;

        return $out;
    }

    /**
     * The actions the AI performed for one person.
     *
     * The data a person may see about themselves. Read-only and their own: the
     * self-disclosure tool passes no user parameter at all, so there is nothing
     * to point at somebody else.
     *
     * @param int $userid
     * @param int $limit
     * @return array<int, stdClass>
     */
    public static function for_user(int $userid, int $limit = 100): array {
        global $DB;

        if ($userid <= 0) {
            return [];
        }

        return array_values($DB->get_records(
            action_recorder::TABLE,
            ['userid' => $userid],
            'timecreated DESC',
            'id, toolname, component, iswrite, success, courseid, timecreated',
            0,
            max(1, $limit)
        ));
    }

    /**
     * How long actions keep their personal link, in days. 0 means forever.
     *
     * @return int
     */
    public static function retention_days(): int {
        $value = get_config('local_elediaai_core', 'action_retentiondays');
        if ($value === false || $value === null || $value === '') {
            return self::DEFAULT_RETENTION_DAYS;
        }
        return max(0, (int) $value);
    }

    /**
     * Remove the personal link from actions past the retention window.
     *
     * Anonymises rather than deletes, and that is the whole point: an oversight
     * record that vanishes on request proves nothing. Same mechanic as
     * `local_aitransparency`'s provenance records, for the same reason.
     *
     * @return int Rows anonymised.
     */
    public static function anonymise(): int {
        global $DB;

        $days = self::retention_days();
        if ($days <= 0) {
            return 0;
        }

        $cutoff = time() - ($days * DAYSECS);
        $count = $DB->count_records_select(
            action_recorder::TABLE,
            'userid <> 0 AND timecreated < :cutoff',
            ['cutoff' => $cutoff]
        );
        if ($count === 0) {
            return 0;
        }

        $DB->set_field_select(
            action_recorder::TABLE,
            'userid',
            0,
            'userid <> 0 AND timecreated < :cutoff',
            ['cutoff' => $cutoff]
        );
        return $count;
    }

    /**
     * Anonymise every action of one person, on request.
     *
     * A data-subject erasure does not take the row: what the AI did stays
     * documented, only the name goes. The alternative would let anybody erase
     * the record of a grade the AI wrote for them.
     *
     * @param int $userid
     * @return int Rows anonymised.
     */
    public static function anonymise_for_user(int $userid): int {
        global $DB;

        if ($userid <= 0) {
            return 0;
        }

        $count = $DB->count_records(action_recorder::TABLE, ['userid' => $userid]);
        if ($count > 0) {
            $DB->set_field(action_recorder::TABLE, 'userid', 0, ['userid' => $userid]);
        }
        return $count;
    }
}

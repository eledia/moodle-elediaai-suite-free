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
 * Provenance store API for local_aitransparency.
 *
 * @package    local_aitransparency
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aitransparency;

/**
 * Creates and reads one provenance record per AI-generated output (Art. 50).
 *
 * Kept deliberately small: 14 plugins depend on this surface. No product logic,
 * no marking, no rendering here — only the persistent provenance layer.
 */
class provenance {
    /** @var string Record table. */
    private const TABLE = 'local_aitransparency_rec';

    /** @var string[] Allowed marking states. */
    public const MARK_STATES = ['pending', 'marked', 'embedded', 'sidecar', 'failed', 'unsupported'];

    /**
     * Record a provenance entry before the output is delivered. Idempotent per
     * (contenthash, contextid): a repeat call for the same output returns the
     * existing UUID instead of creating a duplicate.
     *
     * @param record_request $request
     * @return string The record UUID
     */
    public static function record(record_request $request): string {
        global $DB;

        $contenthash = $request->resolve_contenthash();

        $existing = $DB->get_record(self::TABLE, [
            'contenthash' => $contenthash,
            'contextid' => $request->contextid,
        ], 'uuid', IGNORE_MULTIPLE);
        if ($existing) {
            return $existing->uuid;
        }

        $uuid = \core\uuid::generate();
        $DB->insert_record(self::TABLE, (object) [
            'uuid' => $uuid,
            'component' => $request->component,
            'actionname' => $request->actionname,
            'registerid' => $request->registerid,
            'turnid' => $request->turnid,
            'provider' => $request->provider,
            'model' => $request->model,
            'userid' => $request->userid,
            'contextid' => $request->contextid,
            'assettype' => $request->assettype,
            'contenthash' => $contenthash,
            'markstate' => 'pending',
            'timecreated' => time(),
        ]);

        return $uuid;
    }

    /**
     * Fetch a record by its UUID.
     *
     * @param string $uuid
     * @return record|null
     */
    public static function get(string $uuid): ?record {
        global $DB;
        $row = $DB->get_record(self::TABLE, ['uuid' => $uuid]);
        return $row ? record::from_row($row) : null;
    }

    /**
     * Fetch the most recent record for a content hash, optionally scoped to a context.
     *
     * @param string $contenthash
     * @param int|null $contextid
     * @return record|null
     */
    public static function get_by_contenthash(string $contenthash, ?int $contextid = null): ?record {
        global $DB;
        $conditions = ['contenthash' => $contenthash];
        if ($contextid !== null) {
            $conditions['contextid'] = $contextid;
        }
        $rows = $DB->get_records(self::TABLE, $conditions, 'timecreated DESC, id DESC', '*', 0, 1);
        $row = $rows ? reset($rows) : false;
        return $row ? record::from_row($row) : null;
    }

    /**
     * Recent records, newest first, for the site report.
     *
     * @param int $limit
     * @param int $offset
     * @param string $component Optional component filter.
     * @return record[]
     */
    public static function recent(int $limit = 50, int $offset = 0, string $component = ''): array {
        global $DB;

        $conditions = [];
        if ($component !== '') {
            $conditions['component'] = $component;
        }
        $rows = $DB->get_records(
            self::TABLE,
            $conditions,
            'timecreated DESC, id DESC',
            '*',
            max(0, $offset),
            max(1, $limit)
        );

        return array_map([record::class, 'from_row'], array_values($rows));
    }

    /**
     * How many records exist, optionally per component.
     *
     * @param string $component
     * @return int
     */
    public static function count_records(string $component = ''): int {
        global $DB;

        return $component === ''
            ? $DB->count_records(self::TABLE)
            : $DB->count_records(self::TABLE, ['component' => $component]);
    }

    /**
     * How many records are still waiting to be marked.
     *
     * The number the report exists for: a record in `pending` means an output
     * went out without its marking, and Art. 50 Abs. 2 is then unmet for that
     * one piece of content.
     *
     * @return int
     */
    public static function count_unmarked(): int {
        global $DB;

        return $DB->count_records_select(
            self::TABLE,
            'markstate IN (:pending, :failed, :unsupported)',
            ['pending' => 'pending', 'failed' => 'failed', 'unsupported' => 'unsupported']
        );
    }

    /**
     * Advance the marking state of a record.
     *
     * @param string $uuid
     * @param string $state One of {@see self::MARK_STATES}
     * @throws \coding_exception on an unknown state
     */
    public static function set_mark_state(string $uuid, string $state): void {
        global $DB;
        if (!in_array($state, self::MARK_STATES, true)) {
            throw new \coding_exception('Unknown provenance mark state: ' . $state);
        }
        $id = $DB->get_field(self::TABLE, 'id', ['uuid' => $uuid]);
        if ($id) {
            $DB->set_field(self::TABLE, 'markstate', $state, ['id' => $id]);
        }
    }
}

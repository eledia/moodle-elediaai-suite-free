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
 * Writes one row per action the AI performed through a tool.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_elediaai_core\local;

/**
 * Schicht A: what the AI did, for whom.
 *
 * **Why a table and not just the event log.** Every tool call already fires
 * `tool_invoked`, and a write also fires `write_performed` — the latter with the
 * comment that an audit consumer should be able to filter on it. Moodle's log
 * store keeps both, but encodes the event payload as JSON *or* PHP-serialised
 * depending on a configuration flag, so filtering by tool name means a LIKE over
 * an encoded blob whose encoding is not fixed. The store is also pluggable and
 * rotatable. This table is a projection for the questions an audit asks; the log
 * remains the record of the raw event.
 *
 * **Why the person stays.** The turn log drops the identity on purpose, because
 * oversight of what an AI *said* does not need to know who asked. Oversight of
 * what an AI *did* does: a grade written on somebody's submission is an act
 * performed on behalf of a named person, and a protocol that cannot say whose
 * behalf answers nothing. The retention task therefore anonymises rather than
 * deletes — the row survives, the link does not.
 *
 * **Who fills it.** Not an observer, and not the core reaching into a plugin.
 * `webservice_elediamcp` calls this when it records an invocation: it already
 * knows the tool, the outcome, the duration and — from the tool's own MCP
 * annotations — whether the call changed state. The core does not know its
 * plugins and must not start guessing which tool writes.
 */
final class action_recorder {
    /** @var string Backing table. */
    public const TABLE = 'local_elediaai_core_action';

    /**
     * Static-only.
     */
    private function __construct() {
    }

    /**
     * Record one action.
     *
     * Never throws into the caller: a tool call that succeeded must not be
     * reported as failed because the bookkeeping fell over.
     *
     * @param string $component Frankenstyle of the plugin owning the tool.
     * @param string $toolname The tool as the agent called it.
     * @param int $userid Who the AI acted for.
     * @param bool $iswrite Whether the call changed state.
     * @param bool $success Whether it completed.
     * @param int $durationms How long it took.
     * @param int $contextid Where it happened, 0 when unknown.
     * @param int $courseid Course it touched, 0 when none or unknown.
     * @param string|null $errorcode Error code on failure.
     * @param int|null $turnid The turn that caused it, when knowable.
     * @return void
     */
    public static function record(
        string $component,
        string $toolname,
        int $userid,
        bool $iswrite = false,
        bool $success = true,
        int $durationms = 0,
        int $contextid = 0,
        int $courseid = 0,
        ?string $errorcode = null,
        ?int $turnid = null
    ): void {
        global $DB;

        try {
            $DB->insert_record(self::TABLE, (object) [
                'userid' => max(0, $userid),
                'component' => self::clip($component, 100),
                'toolname' => self::clip($toolname, 100),
                'iswrite' => $iswrite ? 1 : 0,
                'success' => $success ? 1 : 0,
                'errorcode' => self::clip_or_null($errorcode, 100),
                'durationms' => max(0, $durationms),
                'contextid' => max(0, $contextid),
                'courseid' => max(0, $courseid),
                'turnid' => $turnid !== null && $turnid > 0 ? $turnid : null,
                'timecreated' => time(),
            ]);
        } catch (\Throwable $e) {
            debugging(
                'local_elediaai_core: could not record the AI action: ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
        }
    }

    /**
     * Truncate to the column width.
     *
     * @param string $value
     * @param int $limit
     * @return string
     */
    private static function clip(string $value, int $limit): string {
        return \core_text::strlen($value) > $limit
            ? \core_text::substr($value, 0, $limit)
            : $value;
    }

    /**
     * Truncate, or null for an empty value.
     *
     * @param string|null $value
     * @param int $limit
     * @return string|null
     */
    private static function clip_or_null(?string $value, int $limit): ?string {
        $value = $value === null ? '' : trim($value);
        return $value === '' ? null : self::clip($value, $limit);
    }
}

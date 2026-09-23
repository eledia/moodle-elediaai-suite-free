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

namespace block_elediaai_tutor\local;

use context;
use moodle_exception;
use Throwable;

/**
 * Small admin-facing diagnostic log for failed tutor calls.
 *
 * Stores only non-sensitive metadata: no prompts, no answers, no API keys.
 *
 * @package     block_elediaai_tutor
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class diagnostics {
    /** @var string Diagnostic table name. */
    private const TABLE = 'block_elediaai_tutor_diag';

    /** @var int How long lightweight diagnostics are retained. */
    private const RETENTION_DAYS = 30;

    /**
     * Record a failed tutor call without ever interrupting the user flow.
     *
     * @param int $userid The acting user id.
     * @param context $context The context where the failure happened.
     * @param string $phase Short phase label, e.g. configuration, mcp, rag.
     * @param Throwable|null $exception Optional exception to summarise.
     * @param int|null $courseid Course id, if known.
     * @param string|null $detail Optional non-sensitive detail.
     * @return void
     */
    public static function record(
        int $userid,
        context $context,
        string $phase,
        ?Throwable $exception = null,
        ?int $courseid = null,
        ?string $detail = null
    ): void {
        global $DB;

        try {
            if (!$DB->get_manager()->table_exists(self::TABLE)) {
                return;
            }

            $record = (object) [
                'userid' => $userid,
                'courseid' => (int) ($courseid ?? 0),
                'contextid' => (int) $context->id,
                'phase' => self::clean_phase($phase),
                'errorcode' => self::error_code($exception),
                'detail' => self::clean_detail($detail ?? self::exception_detail($exception)),
                'timecreated' => time(),
            ];
            $DB->insert_record(self::TABLE, $record);
            self::prune();
        } catch (Throwable $ignored) {
            debugging(
                'block_elediaai_tutor: diagnostic logging failed: ' . $ignored->getMessage(),
                DEBUG_DEVELOPER
            );
        }
    }

    /**
     * Return latest diagnostic entries for the dashboard.
     *
     * @param int $limit Maximum entries.
     * @return array<int,object>
     */
    public static function latest(int $limit = 5): array {
        global $DB;

        if (!$DB->get_manager()->table_exists(self::TABLE)) {
            return [];
        }
        return array_values($DB->get_records(self::TABLE, null, 'timecreated DESC, id DESC', '*', 0, $limit));
    }

    /**
     * Erase a user's diagnostic records.
     *
     * @param int $userid The user id.
     * @return int Number of rows deleted.
     */
    public static function delete_for_user(int $userid): int {
        global $DB;

        $count = $DB->count_records(self::TABLE, ['userid' => $userid]);
        if ($count > 0) {
            $DB->delete_records(self::TABLE, ['userid' => $userid]);
        }
        return $count;
    }

    /**
     * Remove old diagnostics.
     *
     * @return void
     */
    private static function prune(): void {
        global $DB;

        $cutoff = time() - (self::RETENTION_DAYS * DAYSECS);
        $DB->delete_records_select(self::TABLE, 'timecreated < ?', [$cutoff]);
    }

    /**
     * Keep phase labels predictable and short.
     *
     * @param string $phase Raw phase.
     * @return string
     */
    private static function clean_phase(string $phase): string {
        $phase = preg_replace('/[^a-z0-9_-]+/i', '_', $phase) ?? 'unknown';
        return \core_text::substr($phase, 0, 40);
    }

    /**
     * Extract a stable error code.
     *
     * @param Throwable|null $exception Optional exception.
     * @return string
     */
    private static function error_code(?Throwable $exception): string {
        if ($exception instanceof moodle_exception && !empty($exception->errorcode)) {
            return \core_text::substr((string) $exception->errorcode, 0, 100);
        }
        if ($exception !== null) {
            return \core_text::substr(get_class($exception), 0, 100);
        }
        return 'tool_error';
    }

    /**
     * Extract exception detail suitable for admins.
     *
     * @param Throwable|null $exception Optional exception.
     * @return string
     */
    private static function exception_detail(?Throwable $exception): string {
        if ($exception === null) {
            return '';
        }
        if ($exception instanceof moodle_exception && !empty($exception->debuginfo)) {
            return (string) $exception->debuginfo;
        }
        return $exception->getMessage();
    }

    /**
     * Remove sensitive fragments and cap the stored detail.
     *
     * @param string $detail Raw detail.
     * @return string
     */
    private static function clean_detail(string $detail): string {
        $detail = trim(strip_tags($detail));
        $patterns = [
            '/Bearer\s+[A-Za-z0-9._~+\/=:-]+/i' => 'Bearer [redacted]',
            '/((?:api[-_ ]?key|x-api-key|authorization|token)\s*[:=]\s*)[^\s,;]+/i' => '$1[redacted]',
        ];
        foreach ($patterns as $pattern => $replacement) {
            $detail = preg_replace($pattern, $replacement, $detail) ?? $detail;
        }
        return \core_text::substr($detail, 0, 255);
    }
}

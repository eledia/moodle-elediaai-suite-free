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
 * Turns a chat failure into a user-facing message string.
 *
 * @package    block_elediaai_tutor
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace block_elediaai_tutor\local;

/**
 * Maps a chat exception to a clear, role-appropriate error message.
 *
 * Learners get a neutral, category-level message (no internals). Users who
 * can manage the tutor or configure the site additionally get the technical
 * reason (already secret-scrubbed at the source, e.g. "http status 502",
 * "transport: Connection refused") so they can act without digging through
 * the logs.
 */
class error_message {
    /**
     * Classifies a failure into a phase: rag, mcp, config or generic.
     *
     * @param \Throwable $exception The chat failure.
     * @return string One of rag|mcp|config|generic.
     */
    public static function phase(\Throwable $exception): string {
        if ($exception instanceof \local_elediaai_chatengine\adapter\adapter_exception) {
            return 'rag';
        }
        if ($exception instanceof \moodle_exception) {
            $errorcode = (string) ($exception->errorcode ?? '');
            if (
                str_contains($errorcode, 'mcp') || str_contains($errorcode, 'service')
                    || str_contains($errorcode, 'token')
            ) {
                return 'mcp';
            }
            if (
                str_contains($errorcode, 'config') || str_contains($errorcode, 'url')
                    || str_contains($errorcode, 'rag')
            ) {
                return 'config';
            }
        }
        return 'generic';
    }

    /**
     * Builds the language-string key and its `$a` argument for a failure.
     *
     * @param \Throwable $exception The chat failure.
     * @param bool $candetail Whether the viewer may see the technical reason.
     * @return array{0: string, 1: string|null} [string key, $a argument].
     */
    public static function for_exception(\Throwable $exception, bool $candetail): array {
        $phase = self::phase($exception);
        if (!$candetail) {
            return ['error_' . $phase . '_learner', null];
        }
        $detail = '';
        if ($exception instanceof \moodle_exception) {
            $detail = trim((string) ($exception->debuginfo ?? ''));
        }
        if ($detail === '') {
            $detail = trim($exception->getMessage());
        }
        return ['error_' . $phase . '_detail', $detail];
    }
}

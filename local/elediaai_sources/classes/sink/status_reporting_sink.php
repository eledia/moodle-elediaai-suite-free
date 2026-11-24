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

namespace local_elediaai_sources\sink;

/**
 * A destination that accepts documents first and confirms their processing later.
 *
 * The external ingestion pipeline answers an upsert with `202 Accepted`, which
 * only means "the job is stored" (ai_suite#232). Whether the document reached
 * the index is asked afterwards, for many documents at once. A destination
 * that implements this interface is treated accordingly: an accepted upsert
 * is recorded as queued, not as indexed, and the status check confirms it.
 *
 * Optional on purpose. A synchronous destination such as LiteRAG answers
 * with the result itself and does not implement it; its documents are
 * indexed as soon as the upsert succeeds, exactly as before.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface status_reporting_sink {
    /** @var int Most source ids one status request may carry. */
    public const MAX_BATCH = 500;

    /**
     * The stored processing status of these documents.
     *
     * One request for at most {@see MAX_BATCH} ids; splitting a larger set is
     * the caller's job, so the number of requests stays visible where it is
     * decided. The answer is normalised:
     *
     * - `available` (bool): false for every API failure: transport, a non-2xx
     *   answer, an unreadable body, or an answer without an entry for one of
     *   the requested ids. A failure means "status not available", never
     *   "unknown" and never success.
     * - `documents` (array): keyed by source id, each with `source_id`,
     *   `status` (queued, running, succeeded, failed, deleted, unknown),
     *   `last_job_id`, `error_type`, `error_message`, `attempt_count`,
     *   `max_attempts`, `next_retry_at`, `last_success_at`, `updated_at`; the
     *   three times as Unix timestamps, 0 when absent. Empty when unavailable.
     * - `retryafter` (int|null): seconds from a `Retry-After` header.
     * - `http_code` (int) and `error` (string): what went wrong, for the display.
     *
     * @param string[] $sourceids The source ids to ask about, 1 to MAX_BATCH.
     * @return array Normalised answer as described above.
     * @throws \coding_exception When more than MAX_BATCH ids are passed.
     */
    public function document_status(array $sourceids): array;
}

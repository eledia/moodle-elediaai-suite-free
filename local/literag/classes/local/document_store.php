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
 * Receives ingestion payloads from local_elediaai_sources and stores chunks.
 *
 * Implements the upsert/delete semantics of the RAG ingestion API v1.2:
 * idempotent upsert keyed on source_id (re-chunk = delete-then-insert), and
 * exact/prefix-scoped delete (the ':' boundary prevents cmid99 matching cmid990).
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class document_store {
    /** @var string[] Content types the ingester is allowed to send. */
    private const ALLOWED_TYPES = ['text/plain', 'text/html', 'application/pdf'];

    /**
     * Upsert one ingested document: validate, extract, chunk and store.
     *
     * @param array $payload Decoded JSON upsert body.
     * @return void
     * @throws ingest_exception On malformed payload, bad base64, or tenant mismatch (4xx).
     */
    public function upsert(array $payload): void {
        global $CFG, $DB;

        $sourceid = trim((string) ($payload['source_id'] ?? ''));
        $contenttype = trim((string) ($payload['content_type'] ?? ''));
        $rawcontent = (string) ($payload['content'] ?? '');
        $meta = (array) ($payload['qdrant_metadata'] ?? []);

        if ($sourceid === '') {
            throw new ingest_exception('missing source_id', 400);
        }
        if (!in_array($contenttype, self::ALLOWED_TYPES, true)) {
            throw new ingest_exception('unsupported content_type: ' . $contenttype, 400);
        }

        $content = base64_decode($rawcontent, true);
        if ($content === false) {
            throw new ingest_exception('content is not valid base64', 400);
        }

        // Verify the payload belongs to this site's tenant (reject cross-tenant writes).
        $tenant = tenant::id();
        $claimed = trim((string) ($meta['tenant_id'] ?? ''));
        if ($claimed === '') {
            throw new ingest_exception('missing tenant_id', 400);
        }
        if ($claimed !== $tenant) {
            throw new ingest_exception('tenant mismatch', 403);
        }

        $siteurl = rtrim(trim((string) ($meta['site_url'] ?? '')), '/');
        if ($siteurl === '' || $siteurl !== rtrim((string) $CFG->wwwroot, '/')) {
            throw new ingest_exception('site_url mismatch', 403);
        }

        $rawcourseid = (string) ($meta['course_id'] ?? '');
        $rawcmid = (string) ($meta['cmid'] ?? '');
        if (!ctype_digit($rawcourseid) || !ctype_digit($rawcmid)) {
            throw new ingest_exception('invalid course_id or cmid', 400);
        }
        $courseid = (int) $rawcourseid;
        $cmid = (int) $rawcmid;
        if ($courseid <= 0 || $cmid <= 0) {
            throw new ingest_exception('invalid course_id or cmid', 400);
        }

        try {
            $cm = get_fast_modinfo($courseid)->get_cm($cmid);
        } catch (\moodle_exception $e) {
            throw new ingest_exception('cmid does not belong to course_id', 400);
        }
        if ((int) $cm->course !== $courseid) {
            throw new ingest_exception('cmid does not belong to course_id', 400);
        }

        $moduleurl = trim((string) ($meta['module_url'] ?? ''));
        $expectedurl = (new \moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cmid]))->out(false);
        if (
            $moduleurl === ''
                || clean_param($moduleurl, PARAM_URL) !== $moduleurl
                || !hash_equals($expectedurl, $moduleurl)
        ) {
            throw new ingest_exception('module_url does not match cmid', 400);
        }

        $contenthash = sha1($content);
        $now = time();

        $existing = $DB->get_record('local_literag_sources', ['sourceid' => $sourceid]);
        if ($existing && $existing->contenthash === $contenthash && $existing->parsestate === 'ok') {
            // Unchanged — nothing to do, but the upsert is still a success.
            return;
        }

        $chunker = new chunker();
        $text = $chunker->extract_text($content, $contenttype);
        $sourcetype = chunker::source_type($contenttype);
        $title = $this->resolve_title($courseid, $cmid, $text);
        $contextid = $this->resolve_contextid($cmid);

        $parsestate = 'ok';
        $chunks = [];
        if ($text === null) {
            // Extraction unavailable (e.g. PDF without pdftotext). Record the source
            // so a later re-ingest can succeed, but store no chunks. Still a success.
            $parsestate = 'skipped';
        } else {
            $chunks = $chunker->chunk($text);
            if (empty($chunks)) {
                $parsestate = 'skipped';
            }
        }

        $transaction = $DB->start_delegated_transaction();
        try {
            // Re-chunk = clear the old set first (idempotent upsert).
            $DB->delete_records('local_literag_chunks', ['sourceid' => $sourceid]);

            $sortorder = 0;
            foreach ($chunks as $chunktext) {
                $DB->insert_record('local_literag_chunks', (object) [
                    'sourceid' => $sourceid,
                    'tenant' => $tenant,
                    'courseid' => $courseid,
                    'contextid' => $contextid,
                    'cmid' => $cmid,
                    'sourcetype' => $sourcetype,
                    'sourcetitle' => $title,
                    'moduleurl' => $moduleurl,
                    'chunktext' => $chunktext,
                    'chunkhash' => chunker::hash($chunktext),
                    'sortorder' => $sortorder++,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]);
            }

            $record = (object) [
                'sourceid' => $sourceid,
                'tenant' => $tenant,
                'courseid' => $courseid,
                'cmid' => $cmid,
                'contextid' => $contextid,
                'moduleurl' => $moduleurl,
                'sourcetitle' => $title,
                'contenttype' => $contenttype,
                'contenthash' => $contenthash,
                'parsestate' => $parsestate,
                'timemodified' => $now,
            ];
            if ($existing) {
                $record->id = $existing->id;
                $record->timecreated = $existing->timecreated;
                $DB->update_record('local_literag_sources', $record);
            } else {
                $record->timecreated = $now;
                $DB->insert_record('local_literag_sources', $record);
            }

            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
            throw $e;
        }
    }

    /**
     * Delete a document (and, when prefix-scoped, all its sub-documents).
     *
     * @param string $sourceid The source_id to delete.
     * @param string $scope 'exact' (default) or 'prefix'.
     * @return void
     * @throws ingest_exception On a missing source_id (400).
     */
    public function delete(string $sourceid, string $scope = 'exact'): void {
        global $DB;

        $sourceid = trim($sourceid);
        if ($sourceid === '') {
            throw new ingest_exception('missing source_id', 400);
        }

        if ($scope === 'prefix') {
            // Exact id OR any sub-document beginning with "source_id:".
            $like = $DB->sql_like('sourceid', '?');
            $select = "sourceid = ? OR $like";
            $params = [$sourceid, $DB->sql_like_escape($sourceid) . ':%'];
            $DB->delete_records_select('local_literag_chunks', $select, $params);
            $DB->delete_records_select('local_literag_sources', $select, $params);
        } else {
            $DB->delete_records('local_literag_chunks', ['sourceid' => $sourceid]);
            $DB->delete_records('local_literag_sources', ['sourceid' => $sourceid]);
        }
        // Delete of a non-existent source_id is a no-op success (idempotent).
    }

    /**
     * Resolve a human-readable title for citations.
     *
     * @param int $courseid
     * @param int $cmid
     * @param string|null $text Extracted text (for a fallback first line).
     * @return string
     */
    private function resolve_title(int $courseid, int $cmid, ?string $text): string {
        if ($courseid > 0 && $cmid > 0) {
            try {
                $modinfo = get_fast_modinfo($courseid);
                $cm = $modinfo->get_cm($cmid);
                $name = trim((string) $cm->get_formatted_name());
                if ($name !== '') {
                    return \core_text::substr($name, 0, 255);
                }
            } catch (\moodle_exception $e) {
                // Fall through to a text-derived title.
                $name = '';
            }
        }
        if ($text !== null && trim($text) !== '') {
            $firstline = trim((string) strtok($text, "\n"));
            if ($firstline !== '') {
                return \core_text::substr($firstline, 0, 255);
            }
        }
        return get_string('untitledsource', 'local_literag');
    }

    /**
     * Resolve the module context id, or 0 when the cm cannot be resolved.
     *
     * @param int $cmid
     * @return int
     */
    private function resolve_contextid(int $cmid): int {
        if ($cmid <= 0) {
            return 0;
        }
        try {
            return (int) \core\context\module::instance($cmid)->id;
        } catch (\moodle_exception $e) {
            return 0;
        }
    }
}

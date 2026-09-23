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

use local_elediaai_sources\sink\sink;
use local_elediaai_sources\sink\sink_manager;

/**
 * Ingestion manager — orchestrates content extraction and RAG API submission.
 *
 * Discovers subplugin extractors, validates documents, enforces size and
 * MIME-type limits, builds payloads, and hands them to the active destination.
 *
 * Which destination that is, is resolved once per manager instance by
 * {@see sink_manager}; the manager itself neither knows nor cares whether the
 * documents end up in the external pipeline or in LiteRAG.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ingestion_manager {
    /** @var int Characters of text content shown in a preview. */
    private const PREVIEW_MAX_CHARS = 20000;

    /** @var string The module should be ingested. */
    public const VERDICT_INGEST = 'ingest';

    /** @var string The module's documents should be removed from the index. */
    public const VERDICT_DELETE = 'delete';

    /** @var string Nothing to do for this module. */
    public const VERDICT_SKIP = 'skip';

    /** @var sink The active destination. */
    private sink $sink;

    /**
     * Constructor.
     *
     * @param sink|null $sink Injected destination, for tests; the configured one when null.
     */
    public function __construct(?sink $sink = null) {
        $this->sink = $sink ?? sink_manager::active();
    }

    /**
     * The MIME types that may currently leave this site.
     *
     * The support matrix decides which formats exist, the active destination
     * decides which of them it can parse — and extractors ask this rather than
     * keeping lists of their own, which is what let the three former lists
     * drift apart.
     *
     * @return string[] MIME types.
     */
    public function allowed_content_types(): array {
        return format_matrix::offered($this->sink);
    }

    /**
     * Reindex all supported modules in a course.
     *
     * Iterates through every course module, finds a matching extractor,
     * extracts content, and sends it to the RAG API. Errors are logged
     * but never stop the loop.
     *
     * @param int $courseid The course ID to reindex.
     * @param bool $force Re-send even content the state records as unchanged.
     * @return array List of result arrays, one per module attempted.
     */
    public function reindex_course(int $courseid, bool $force = false): array {
        $results = [];

        if (!$this->sink->is_configured()) {
            $results[] = [
                'cmid' => 0,
                'module_name' => '-',
                'success' => false,
                'status' => 'error',
                'message' => get_string('apinotconfigured', 'local_elediaai_sources'),
            ];
            return $results;
        }

        $modinfo = get_fast_modinfo($courseid);

        foreach ($modinfo->get_cms() as $cm) {
            if ($cm->deletioninprogress) {
                continue;
            }

            // Availability restrictions etc. are respected as before; the
            // learner-visibility convergence happens inside the module path.
            if (self::visible_to_learners($cm) && !$cm->uservisible) {
                continue;
            }

            $result = $this->ingest_module_from_cm($cm, $force);
            $results[] = $result;
        }

        return $results;
    }

    /**
     * Whether learners can see the module at all.
     *
     * Deliberately NOT {@see \cm_info::$uservisible}: scheduled tasks run as a
     * user holding viewhiddenactivities, for whom hidden modules count as
     * visible — the exact blind spot this check exists to close. Stealth
     * modules (visible, just off the course page) remain visible here.
     *
     * @param \cm_info $cm The course module.
     * @return bool
     */
    public static function visible_to_learners(\cm_info $cm): bool {
        return (bool) $cm->visible && (bool) $cm->get_section_info()->visible;
    }

    /**
     * Ingest a single course module by course ID and cmid.
     *
     * Used by ad-hoc tasks triggered from event observers.
     *
     * @param int $courseid The course ID.
     * @param int $cmid The course module ID.
     * @return array Result array with keys 'cmid', 'module_name', 'success', 'status', 'message'.
     */
    public function ingest_module(int $courseid, int $cmid): array {
        if (!$this->sink->is_configured()) {
            return [
                'cmid' => $cmid,
                'module_name' => '',
                'success' => false,
                'status' => 'error',
                'message' => get_string('apinotconfigured', 'local_elediaai_sources'),
            ];
        }

        try {
            $modinfo = get_fast_modinfo($courseid);
            $cm = $modinfo->get_cm($cmid);
        } catch (\Exception $e) {
            return [
                'cmid' => $cmid,
                'module_name' => '',
                'success' => false,
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }

        return $this->ingest_module_from_cm($cm);
    }

    /**
     * Delete a module's document from the RAG index.
     *
     * @param int $courseid The course ID.
     * @param int $cmid The course module ID.
     * @return array Result array.
     */
    public function delete_module(int $courseid, int $cmid): array {
        if (!$this->sink->is_configured()) {
            return [
                'cmid' => $cmid,
                'success' => false,
                'status' => 'error',
                'message' => get_string('apinotconfigured', 'local_elediaai_sources'),
            ];
        }

        $sourceid = source_id_helper::build_from_ids($courseid, $cmid);

        // Prefix scope removes the module-level document AND any sub-documents
        // (e.g. per-file Folder documents) in one call.
        $apiresult = $this->sink->delete($sourceid, 'prefix');

        if ($apiresult['success']) {
            cm_state::forget($cmid);
            mtrace(get_string('deletionsuccess', 'local_elediaai_sources', $sourceid));
            return [
                'cmid' => $cmid,
                'success' => true,
                'status' => 'success',
                'message' => get_string('deletionsuccess', 'local_elediaai_sources', $sourceid),
            ];
        }

        $errorinfo = (object) ['source_id' => $sourceid, 'http_code' => $apiresult['http_code']];
        mtrace(get_string('deletionfailed', 'local_elediaai_sources', $errorinfo));
        return [
            'cmid' => $cmid,
            'success' => false,
            'status' => 'error',
            'message' => get_string('deletionfailed', 'local_elediaai_sources', $errorinfo),
        ];
    }

    /**
     * Remove every document of a course from the RAG index.
     *
     * Used when a course is un-marked for ingestion. Each module is removed
     * with a prefix-scoped delete, so module-level and per-file sub-documents
     * are all cleared. Idempotent: deleting absent documents is a no-op.
     *
     * @param int $courseid The course id.
     * @return array<int, array> Per-module result rows.
     */
    public function purge_course(int $courseid): array {
        if (!$this->sink->is_configured()) {
            return [[
                'cmid' => 0,
                'success' => false,
                'status' => 'error',
                'message' => get_string('apinotconfigured', 'local_elediaai_sources'),
            ]];
        }

        try {
            $modinfo = get_fast_modinfo($courseid);
        } catch (\Exception $e) {
            return [[
                'cmid' => 0,
                'success' => false,
                'status' => 'error',
                'message' => $e->getMessage(),
            ]];
        }

        $results = [];
        foreach ($modinfo->get_cms() as $cm) {
            $sourceid = source_id_helper::build($cm);
            $apiresult = $this->sink->delete($sourceid, 'prefix');
            $results[] = [
                'cmid' => $cm->id,
                'success' => (bool) $apiresult['success'],
                'status' => $apiresult['success'] ? 'success' : 'error',
                'message' => $sourceid,
            ];
            if ($apiresult['success']) {
                // Scoped to this manager's destination: purging a destination
                // the site left behind must not discard what is known about
                // the current one.
                cm_state::forget_for_sink((int) $cm->id, $this->sink::id());
                mtrace(get_string('deletionsuccess', 'local_elediaai_sources', $sourceid));
            }
        }
        return $results;
    }

    /**
     * Decide what should happen to a module, without doing any of it.
     *
     * Kept separate from acting on the decision for one reason: the dry-run
     * preview reports exactly this chain. A second copy of these rules would
     * drift, and the preview would then be wrong about the one thing it exists
     * to explain.
     *
     * Note the two state-dependent branches: an explicitly excluded module and
     * a module hidden from learners both converge the index by removal — but
     * only if something is actually there to remove. Otherwise there is
     * nothing to do and the module is merely skipped.
     *
     * @param \cm_info $cm The course module.
     * @return array{verdict: string, reason: string} One of the VERDICT_*
     *         constants and the language key explaining it.
     */
    private function gate_verdict(\cm_info $cm): array {
        // Opt-in gate: only ingest content from courses marked for ingestion.
        if (!course_gate::should_ingest((int) $cm->course)) {
            return ['verdict' => self::VERDICT_SKIP, 'reason' => 'coursenotmarked'];
        }

        // Per-activity selection. An explicit exclusion converges the index by
        // removing the module's documents (idempotent prefix delete). An
        // untouched activity in opt-in mode is only skipped — never deleted —
        // which is what keeps a site-mode switch from touching existing content.
        if (!activity_gate::should_ingest_cm((int) $cm->id)) {
            if (activity_gate::is_explicitly_excluded((int) $cm->id)) {
                return ['verdict' => self::VERDICT_DELETE, 'reason' => 'activityexcluded'];
            }
            return ['verdict' => self::VERDICT_SKIP, 'reason' => 'activitynotselected'];
        }

        // What learners cannot see, the tutor must not cite: a module hidden
        // from learners is removed from the index (same revocation principle
        // as un-marking a course). Checked here rather than via uservisible,
        // because scheduled tasks run as a user who sees hidden modules.
        if (!self::visible_to_learners($cm)) {
            $verdict = cm_state::get((int) $cm->id) !== null
                ? self::VERDICT_DELETE
                : self::VERDICT_SKIP;
            return ['verdict' => $verdict, 'reason' => 'activityhidden'];
        }

        if (!$this->has_extractor($cm)) {
            return ['verdict' => self::VERDICT_SKIP, 'reason' => 'noextractor'];
        }

        return ['verdict' => self::VERDICT_INGEST, 'reason' => ''];
    }

    /**
     * Ingest content from a cm_info object.
     *
     * @param \cm_info $cm The course module.
     * @param bool $force Re-send even content the state records as unchanged.
     * @return array Result array.
     */
    private function ingest_module_from_cm(\cm_info $cm, bool $force = false): array {
        $modulename = $cm->get_formatted_name();

        $verdict = $this->gate_verdict($cm);

        if ($verdict['verdict'] === self::VERDICT_DELETE) {
            $result = $this->delete_module((int) $cm->course, (int) $cm->id);
            $result['module_name'] = $modulename;
            if (!empty($result['success'])) {
                $result['message'] = get_string($verdict['reason'], 'local_elediaai_sources');
            }
            return $result;
        }

        if ($verdict['verdict'] === self::VERDICT_SKIP) {
            return [
                'cmid' => $cm->id,
                'module_name' => $modulename,
                'success' => false,
                'status' => 'skipped',
                'message' => get_string($verdict['reason'], 'local_elediaai_sources'),
            ];
        }

        // A module that has failed its way through the retry budget is left
        // alone until someone forces a reindex. Without this the quarter-hourly
        // reconcile would walk into the same error forever, and the course
        // would never stop counting as unfinished.
        if ($force) {
            cm_state::reset_attempts((int) $cm->id);
        } else if (cm_state::is_exhausted((int) $cm->id)) {
            return [
                'cmid' => $cm->id,
                'module_name' => $modulename,
                'success' => false,
                'status' => 'skipped',
                'message' => get_string('retriesexhausted', 'local_elediaai_sources', cm_state::MAX_ATTEMPTS),
            ];
        }

        $extractor = $this->find_extractor($cm);

        // Multi-document extractors (e.g. a Folder with several files) emit one
        // document per sub-item; everything else maps to a single document.
        if ($extractor instanceof multi_document_extractor) {
            return $this->ingest_multi($cm, $extractor, $modulename, $force);
        }

        // Extract content (single document).
        try {
            $document = $extractor->extract($cm);
        } catch (\Exception $e) {
            return [
                'cmid' => $cm->id,
                'module_name' => $modulename,
                'success' => false,
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }

        // An extractor that found a file it may not send says so; passing that
        // through keeps "unsupported file type" from arriving as the blanket
        // "no content" that hides which file was meant.
        if (is_array($document) && !empty($document['skipped'])) {
            $reason = (string) ($document['skipreason'] ?? '');
            mtrace(get_string('skippedfilelog', 'local_elediaai_sources', (object) [
                'cmid' => $cm->id,
                'reason' => $reason,
            ]));
            return [
                'cmid' => $cm->id,
                'module_name' => $modulename,
                'success' => false,
                'status' => 'skipped',
                'message' => $reason !== '' ? $reason : get_string('nocontent', 'local_elediaai_sources'),
            ];
        }

        if ($document === null || empty($document['content'])) {
            return [
                'cmid' => $cm->id,
                'module_name' => $modulename,
                'success' => false,
                'status' => 'skipped',
                'message' => get_string('nocontent', 'local_elediaai_sources'),
            ];
        }

        $modulesourceid = source_id_helper::build($cm);
        $prepared = $this->prepare_document($cm, $document, $modulesourceid, $modulename);
        if (empty($prepared['ready'])) {
            return $prepared['result'];
        }

        $contenthash = cm_state::aggregate_hash([$prepared['hash']]);
        $current = cm_state::is_current(
            (int) $cm->id,
            $modulesourceid,
            $contenthash,
            $this->sink::id(),
            $this->sink->embedding_model()
        );
        if (!$force && $current) {
            return [
                'cmid' => $cm->id,
                'module_name' => $modulename,
                'success' => false,
                'status' => 'skipped',
                'message' => get_string('contentunchanged', 'local_elediaai_sources'),
            ];
        }

        $result = $this->send_document($cm, $prepared, $modulename);
        if (!empty($result['success'])) {
            cm_state::record_success(
                (int) $cm->course,
                (int) $cm->id,
                $modulesourceid,
                $contenthash,
                $this->sink::id()
            );
        } else if (($result['status'] ?? '') === 'error') {
            cm_state::record_error(
                (int) $cm->course,
                (int) $cm->id,
                $modulesourceid,
                (string) $result['message'],
                $this->sink::id()
            );
        }
        return $result;
    }

    /**
     * Ingest a module that produces several documents (one per sub-item).
     *
     * The module's previous document set is cleared with a prefix-scoped delete
     * before the current set is sent, so added/removed/renamed sub-items never
     * leave orphaned vectors in the index. The first document is upserted as a
     * probe BEFORE that delete: if the API rejects it, the previous set stays
     * in the index instead of leaving a visibility hole (deleted old set, no
     * new set). The prefix-scoped delete also removes the probe document, so
     * the full set is sent again afterwards.
     *
     * @param \cm_info $cm The course module.
     * @param multi_document_extractor $extractor The extractor.
     * @param string $modulename The formatted module name.
     * @param bool $force Re-send even content the state records as unchanged.
     * @return array Aggregated result array.
     */
    private function ingest_multi(
        \cm_info $cm,
        multi_document_extractor $extractor,
        string $modulename,
        bool $force = false
    ): array {
        try {
            $documents = $extractor->extract_documents($cm);
        } catch (\Exception $e) {
            return [
                'cmid' => $cm->id,
                'module_name' => $modulename,
                'success' => false,
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }

        // Files the extractor found but may not send. They carry no content
        // and no suffix, so the filter below removes them from the send set —
        // their whole purpose is to be reported instead of vanishing.
        $notices = self::skip_notices($documents);
        foreach ($notices as $notice) {
            mtrace(get_string('skippedfilelog', 'local_elediaai_sources', (object) [
                'cmid' => $cm->id,
                'reason' => (string) ($notice['skipreason'] ?? ''),
            ]));
        }

        $documents = array_values(array_filter(
            $documents,
            static fn($d) => !empty($d['content']) && !empty($d['suffix'])
        ));

        if (empty($documents)) {
            return [
                'cmid' => $cm->id,
                'module_name' => $modulename,
                'success' => false,
                'status' => 'skipped',
                'message' => empty($notices)
                    ? get_string('nocontent', 'local_elediaai_sources')
                    : self::notice_summary($notices),
            ];
        }

        // Prepare every document up front: all mutations and the hash happen
        // before any HTTP, so the whole set can be compared against the state
        // and skipped without touching the index.
        $prepared = [];
        $hashes = [];
        foreach ($documents as $doc) {
            $sourceid = source_id_helper::build_sub($cm, (string) $doc['suffix']);
            $prep = $this->prepare_document($cm, $doc, $sourceid, $modulename);
            if (!empty($prep['ready'])) {
                $prepared[] = $prep;
                $hashes[] = $prep['hash'];
                continue;
            }

            // A file the destination cannot take today, or one too large to
            // send, is reported like any other skipped file — the reason
            // differs, the silence would be the same.
            $reason = (string) ($prep['result']['message'] ?? '');
            $notices[] = [
                'skipped' => true,
                'skipreason' => $reason,
                'title' => (string) ($doc['title'] ?? ''),
                'content_type' => (string) ($doc['content_type'] ?? ''),
            ];
            mtrace(get_string('skippedfilelog', 'local_elediaai_sources', (object) [
                'cmid' => $cm->id,
                'reason' => $reason,
            ]));
        }

        if (empty($prepared)) {
            // Every document was skipped (e.g. unsupported type or oversized).
            return [
                'cmid' => $cm->id,
                'module_name' => $modulename,
                'success' => false,
                'status' => 'skipped',
                'message' => empty($notices)
                    ? get_string('nocontent', 'local_elediaai_sources')
                    : self::notice_summary($notices),
            ];
        }

        $modulesourceid = source_id_helper::build($cm);
        $contenthash = cm_state::aggregate_hash($hashes);
        $current = cm_state::is_current(
            (int) $cm->id,
            $modulesourceid,
            $contenthash,
            $this->sink::id(),
            $this->sink->embedding_model()
        );
        if (!$force && $current) {
            return [
                'cmid' => $cm->id,
                'module_name' => $modulename,
                'success' => false,
                'status' => 'skipped',
                'message' => get_string('contentunchanged', 'local_elediaai_sources'),
            ];
        }

        // Probe with the first document before touching the previous set, so a
        // failing API never leaves the module invisible in the index.
        $probe = $this->send_document($cm, $prepared[0], $modulename);
        if (empty($probe['success'])) {
            cm_state::record_error(
                (int) $cm->course,
                (int) $cm->id,
                $modulesourceid,
                (string) $probe['message'],
                $this->sink::id()
            );
            return $probe;
        }

        // Clear the previous document set for this module (this also removes
        // the probe document), then send the full current set.
        $this->sink->delete($modulesourceid, 'prefix');

        $sent = 0;
        $failed = 0;
        foreach ($prepared as $prep) {
            $res = $this->send_document($cm, $prep, $modulename);
            if (!empty($res['success'])) {
                $sent++;
            } else {
                $failed++;
            }
        }

        $ok = $failed === 0 && $sent > 0;
        $summary = get_string(
            'ingestionmultisummary',
            'local_elediaai_sources',
            (object) ['sent' => $sent, 'failed' => $failed]
        );
        if (!empty($notices)) {
            $summary .= ' ' . get_string(
                'ingestionmultiskipped',
                'local_elediaai_sources',
                count($notices)
            );
        }
        if ($ok) {
            cm_state::record_success(
                (int) $cm->course,
                (int) $cm->id,
                $modulesourceid,
                $contenthash,
                $this->sink::id()
            );
        } else {
            cm_state::record_error(
                (int) $cm->course,
                (int) $cm->id,
                $modulesourceid,
                $summary,
                $this->sink::id()
            );
        }
        return [
            'cmid' => $cm->id,
            'module_name' => $modulename,
            'success' => $ok,
            'status' => $ok ? 'success' : 'error',
            'message' => $summary,
        ];
    }

    /**
     * Prepare one document (single- or sub-document) without sending it.
     *
     * Resolves embedded H5P, validates the content type, prepends the activity
     * heading and enforces the size limit (truncating text, skipping binary).
     * Splitting preparation from sending lets the content hash be computed
     * over exactly what would go over the wire — before anything does.
     *
     * @param \cm_info $cm The course module.
     * @param array $document The document data ('content', 'content_type', 'title').
     * @param string $sourceid The source id to upsert under.
     * @param string $modulename The formatted module name (heading).
     * @return array Either ['ready' => true, 'document', 'sourceid', 'hash']
     *               or ['ready' => false, 'result' => <skip result row>].
     */
    private function prepare_document(\cm_info $cm, array $document, string $sourceid, string $modulename): array {
        // Resolve embedded H5P placeholders in HTML so the RAG service receives
        // the actual H5P text instead of bare placeholder divs.
        if ($document['content_type'] === 'text/html') {
            $document['content'] = h5p_embed_helper::resolve_h5p_placeholders(
                $document['content'],
                (int) $cm->course
            );
        }

        // Validate content type. Normalising first means a `; charset=utf-8`
        // an extractor passed through is compared as the type it is, not
        // rejected as one the matrix has never heard of.
        $document['content_type'] = format_matrix::normalise((string) $document['content_type']);

        if (!in_array($document['content_type'], $this->allowed_content_types(), true)) {
            $title = (string) ($document['title'] ?? $modulename);
            return ['ready' => false, 'result' => [
                'cmid' => $cm->id,
                'module_name' => $modulename,
                'success' => false,
                'status' => 'skipped',
                'message' => format_matrix::skip_reason($document['content_type'], $title, $this->sink),
            ]];
        }

        // Prepend the activity name as a heading so every chunk the RAG service
        // derives is attributable to its activity. No-op for binary content or
        // when the extractor already supplied its own leading heading/title.
        $document['content'] = document::with_heading(
            $document['content'],
            $document['content_type'],
            $modulename,
        );

        // Enforce the size limit. Oversized TEXT content is truncated and still
        // sent (partial indexing beats none); oversized binary content (e.g.
        // PDF) cannot be safely truncated and is skipped.
        $maxsizemb = (int) (get_config('local_elediaai_sources', 'max_document_size_mb') ?: 20);
        $maxsizebytes = $maxsizemb * 1024 * 1024;

        if (strlen($document['content']) > $maxsizebytes) {
            [$document['content'], $wastruncated] = document::truncate(
                $document['content'],
                $document['content_type'],
                $maxsizebytes
            );

            if (!$wastruncated) {
                $sizemb = round(strlen($document['content']) / (1024 * 1024), 2);
                $sizeinfo = (object) ['size' => $sizemb, 'max' => $maxsizemb];
                return ['ready' => false, 'result' => [
                    'cmid' => $cm->id,
                    'module_name' => $modulename,
                    'success' => false,
                    'status' => 'skipped',
                    'message' => get_string('documentsizeexceeded', 'local_elediaai_sources', $sizeinfo),
                ]];
            }

            mtrace(get_string(
                'contenttruncatedlog',
                'local_elediaai_sources',
                (object) ['cmid' => $cm->id, 'max' => $maxsizemb]
            ));
        }

        return [
            'ready' => true,
            'document' => $document,
            'sourceid' => $sourceid,
            'hash' => cm_state::document_hash(
                $sourceid,
                $document['content_type'],
                $document['content'],
                (string) ($document['title'] ?? '')
            ),
        ];
    }

    /**
     * Send one prepared document to the destination.
     *
     * @param \cm_info $cm The course module.
     * @param array $prepared A ready entry from {@see prepare_document()}.
     * @param string $modulename The formatted module name.
     * @return array Per-document result array.
     */
    private function send_document(\cm_info $cm, array $prepared, string $modulename): array {
        $document = $prepared['document'];
        $sourceid = $prepared['sourceid'];
        $sizebytes = strlen($document['content']);

        $payload = $this->build_payload($cm, $document, $sourceid);
        $apiresult = $this->sink->upsert($payload);

        $loginfo = (object) [
            'source_id' => $sourceid,
            'content_type' => $document['content_type'],
            'size' => round($sizebytes / 1024, 1) . ' KB',
            'http_code' => $apiresult['http_code'],
        ];

        if ($apiresult['success']) {
            mtrace(get_string('ingestionsuccess', 'local_elediaai_sources', $loginfo));
            return [
                'cmid' => $cm->id,
                'module_name' => $modulename,
                'success' => true,
                'status' => 'success',
                'message' => get_string('ingestionsuccess', 'local_elediaai_sources', $loginfo),
            ];
        }

        mtrace(get_string('ingestionfailed', 'local_elediaai_sources', $loginfo));
        return [
            'cmid' => $cm->id,
            'module_name' => $modulename,
            'success' => false,
            'status' => 'error',
            'message' => get_string('ingestionfailed', 'local_elediaai_sources', $loginfo),
        ];
    }

    /**
     * Report what would be sent for a module — without sending anything.
     *
     * A dry run for one activity: it answers "why is this (not) in the index,
     * and what exactly would go there". Everything the real ingest does to the
     * content happens here too — H5P resolution, heading, truncation — because
     * the point is to show the wire form, not the source form.
     *
     * Side-effect free by construction: it never calls the destination and
     * never writes state. That is why it cannot be built by running the normal
     * ingest against a dummy destination — that path records state under the
     * dummy's id and would corrupt exactly the truth reported here.
     *
     * The wording of an OLDER indexed version cannot be shown: only its
     * fingerprint is stored, deliberately, so the plugin keeps no second copy
     * of course content.
     *
     * @param \cm_info $cm The course module.
     * @return array The preview report; see the keys assembled below.
     */
    public function preview_module(\cm_info $cm): array {
        $modulesourceid = source_id_helper::build($cm);
        $verdict = $this->gate_verdict($cm);
        $state = cm_state::get((int) $cm->id);

        $report = [
            'cmid' => (int) $cm->id,
            'modulename' => $cm->get_formatted_name(),
            'modname' => $cm->get_module_type_name(),
            'modulesourceid' => $modulesourceid,
            'verdict' => $verdict['verdict'],
            'reason' => $verdict['reason'],
            'reasontext' => $verdict['reason'] !== ''
                ? get_string($verdict['reason'], 'local_elediaai_sources')
                : '',
            'documents' => [],
            'aggregatehash' => null,
            'payloadmeta' => [],
            'sinkid' => $this->sink::id(),
            'sinkname' => $this->sink::name(),
            'embeddingmodel' => $this->sink->embedding_model(),
            'unchanged' => false,
            'state' => $state,
            'comparison' => self::comparison_state($state, $this->sink::id()),
            'accessibility' => null,
        ];

        if ($verdict['verdict'] !== self::VERDICT_INGEST) {
            return $report;
        }

        $extractor = $this->find_extractor($cm);
        if ($extractor === null) {
            return $report;
        }

        if ($extractor instanceof accessibility_reporter) {
            // A broken package must not cost the operator the rest of the
            // report — the caption question is the side note here, not the point.
            try {
                $report['accessibility'] = $extractor->accessibility_report($cm);
            } catch (\Exception $e) {
                $report['accessibility'] = null;
            }
        }

        try {
            $documents = $extractor instanceof multi_document_extractor
                ? $extractor->extract_documents($cm)
                : [$extractor->extract($cm)];
        } catch (\Exception $e) {
            $report['reason'] = '';
            $report['reasontext'] = $e->getMessage();
            $report['verdict'] = self::VERDICT_SKIP;
            return $report;
        }

        $ismulti = $extractor instanceof multi_document_extractor;
        $hashes = [];

        foreach ($documents as $document) {
            if (!is_array($document)) {
                continue;
            }
            if (!empty($document['skipped'])) {
                // The preview is where someone goes to find out why their file
                // is not in the index — the one place a notice must not be
                // filtered out.
                $report['documents'][] = self::notice_row($document);
                continue;
            }
            if (empty($document['content'])) {
                continue;
            }
            if ($ismulti && empty($document['suffix'])) {
                continue;
            }

            $sourceid = $ismulti
                ? source_id_helper::build_sub($cm, (string) $document['suffix'])
                : $modulesourceid;

            // Preparation reports truncation through mtrace(), which in a web
            // request would print into the page. Swallow it: the preview shows
            // truncation as a flag on the row instead.
            ob_start();
            $prepared = $this->prepare_document($cm, $document, $sourceid, $report['modulename']);
            ob_end_clean();

            if (empty($prepared['ready'])) {
                // Kept rather than dropped: "this file is too large" is exactly
                // what someone opened the preview to find out.
                $report['documents'][] = [
                    'sourceid' => $sourceid,
                    'title' => (string) ($document['title'] ?? ''),
                    'content_type' => (string) ($document['content_type'] ?? ''),
                    'bytes' => strlen((string) $document['content']),
                    'hash' => '',
                    'isbinary' => false,
                    'skipped' => true,
                    'skipreason' => (string) ($prepared['result']['message'] ?? ''),
                    'displaycontent' => null,
                    'displaytruncated' => false,
                ];
                continue;
            }

            $hashes[] = $prepared['hash'];
            $report['documents'][] = self::document_row($prepared);

            if (empty($report['payloadmeta'])) {
                $payload = $this->build_payload($cm, $prepared['document'], $prepared['sourceid']);
                $report['payloadmeta'] = $payload['qdrant_metadata'];
            }
        }

        if (!empty($hashes)) {
            $report['aggregatehash'] = cm_state::aggregate_hash($hashes);
            $report['unchanged'] = cm_state::is_current(
                (int) $cm->id,
                $modulesourceid,
                $report['aggregatehash'],
                $this->sink::id(),
                $this->sink->embedding_model()
            );
        }

        return $report;
    }

    /**
     * The skip notices among an extractor's returned entries.
     *
     * @param array $documents What the extractor returned.
     * @return array<int, array> The notices, in the order they were produced.
     */
    private static function skip_notices(array $documents): array {
        $notices = [];
        foreach ($documents as $document) {
            if (is_array($document) && !empty($document['skipped'])) {
                $notices[] = $document;
            }
        }
        return $notices;
    }

    /**
     * One line naming why nothing (or not everything) was sent.
     *
     * Three reasons are quoted in full and the rest counted: the message lands
     * in a table cell, and a Folder holding forty videos would otherwise
     * produce a wall of identical sentences instead of an answer.
     *
     * @param array<int, array> $notices Skip notices.
     * @return string The translated summary.
     */
    private static function notice_summary(array $notices): string {
        $reasons = [];
        foreach ($notices as $notice) {
            $reason = trim((string) ($notice['skipreason'] ?? ''));
            if ($reason !== '') {
                $reasons[] = $reason;
            }
        }

        if (empty($reasons)) {
            return get_string('nocontent', 'local_elediaai_sources');
        }

        $shown = array_slice($reasons, 0, 3);
        $summary = implode(' ', $shown);

        $remaining = count($reasons) - count($shown);
        if ($remaining > 0) {
            $summary .= ' ' . get_string('skippedmore', 'local_elediaai_sources', $remaining);
        }

        return $summary;
    }

    /**
     * Turn a skip notice into a display row for the preview.
     *
     * @param array $notice The notice as the extractor produced it.
     * @return array The display row.
     */
    private static function notice_row(array $notice): array {
        return [
            'sourceid' => '',
            'title' => (string) ($notice['title'] ?? ''),
            'content_type' => format_matrix::normalise((string) ($notice['content_type'] ?? '')),
            'bytes' => 0,
            'hash' => '',
            'isbinary' => false,
            'skipped' => true,
            'skipreason' => (string) ($notice['skipreason'] ?? ''),
            'displaycontent' => null,
            'displaytruncated' => false,
        ];
    }

    /**
     * Turn one prepared document into a display row.
     *
     * Binary content is never carried into the report: a PDF's bytes help
     * nobody on screen, and passing megabytes through a template only to
     * discard them is waste. Text is capped for display while the byte count
     * keeps reporting the true size.
     *
     * @param array $prepared A ready entry from {@see prepare_document()}.
     * @return array The display row.
     */
    private static function document_row(array $prepared): array {
        $content = (string) $prepared['document']['content'];
        $contenttype = (string) $prepared['document']['content_type'];
        $isbinary = format_matrix::is_binary($contenttype);

        $display = null;
        $truncated = false;
        if (!$isbinary) {
            $display = $content;
            if (\core_text::strlen($display) > self::PREVIEW_MAX_CHARS) {
                $display = \core_text::substr($display, 0, self::PREVIEW_MAX_CHARS);
                $truncated = true;
            }
        }

        return [
            'sourceid' => (string) $prepared['sourceid'],
            'title' => (string) ($prepared['document']['title'] ?? ''),
            'content_type' => $contenttype,
            'bytes' => strlen($content),
            'hash' => (string) $prepared['hash'],
            'isbinary' => $isbinary,
            'skipped' => false,
            'skipreason' => '',
            'displaycontent' => $display,
            'displaytruncated' => $truncated,
        ];
    }

    /**
     * How the recorded index state relates to the active destination.
     *
     * Five honest answers rather than a boolean, because "is it in the index"
     * genuinely has more than two truths once a destination switch or a failed
     * attempt is in play.
     *
     * @param \stdClass|null $state The state row, or null.
     * @param string $activesinkid The destination that would be written now.
     * @return string One of: absent, otherdestination, lastattemptfailed, indexed.
     */
    private static function comparison_state(?\stdClass $state, string $activesinkid): string {
        if ($state === null) {
            return 'absent';
        }
        if ((string) $state->sink !== $activesinkid) {
            // Comparing hashes across destinations would be meaningless.
            return 'otherdestination';
        }
        if ($state->laststatus !== cm_state::STATUS_SUCCESS) {
            return 'lastattemptfailed';
        }
        return 'indexed';
    }

    /**
     * Whether any extractor can read the given module.
     *
     * @param \cm_info $cm The course module.
     * @return bool
     */
    public function has_extractor(\cm_info $cm): bool {
        return $this->find_extractor($cm) !== null;
    }

    /**
     * Discover and return the first extractor subplugin that supports the given module.
     *
     * @param \cm_info $cm The course module.
     * @return content_extractor|null The matching extractor, or null if none found.
     */
    private function find_extractor(\cm_info $cm): ?content_extractor {
        $plugins = \core_component::get_plugin_list('aisourcesextractor');

        foreach ($plugins as $name => $dir) {
            $classname = "\\aisourcesextractor_{$name}\\extractor";
            if (!class_exists($classname)) {
                continue;
            }

            $extractor = new $classname();
            if (!($extractor instanceof content_extractor)) {
                debugging("Extractor {$classname} does not implement content_extractor interface.", DEBUG_DEVELOPER);
                continue;
            }

            if ($extractor->supports($cm)) {
                return $extractor;
            }
        }

        return null;
    }

    /**
     * Build the ingestion API payload for a document.
     *
     * @param \cm_info $cm The course module.
     * @param array $document The extracted document data.
     * @param string $sourceid The deterministic source ID.
     * @return array The payload array ready for JSON encoding.
     */
    private function build_payload(\cm_info $cm, array $document, string $sourceid): array {
        global $CFG;
        $moduleurl = new \moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cm->id]);

        // The original file name travels as metadata (api-specification v1.3):
        // a PDF called "Skript_Woche3.pdf" is cited by that name, and a parser
        // log that only knows a source id cannot tell anyone which upload went
        // wrong. Trimmed to the field length the specification fixes.
        $title = \core_text::substr(trim((string) ($document['title'] ?? '')), 0, 255);

        return [
            'source_id' => $sourceid,
            'content' => base64_encode($document['content']),
            'content_type' => $document['content_type'],
            'qdrant_metadata' => [
                'title' => $title,
                // Derived from wwwroot — the same canonical identity the RAG
                // server resolves at query time from the verified site.url.
                'tenant_id' => tenant::id(),
                'site_url' => (string) $CFG->wwwroot,
                'course_id' => (string) $cm->course,
                'cmid' => (string) $cm->id,
                'module_url' => $moduleurl->out(false),
            ],
            'parser_options' => null,
        ];
    }
}

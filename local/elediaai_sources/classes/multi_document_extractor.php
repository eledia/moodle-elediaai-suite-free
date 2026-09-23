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
 * Optional interface for extractors that emit several documents per module.
 *
 * Most activities map to a single document (the {@see content_extractor}
 * contract). Some — a Folder with several files, or potentially a Book split
 * per chapter — are better represented as multiple documents so that, for
 * example, each PDF keeps its native `application/pdf` content type and is
 * parsed independently by the RAG service.
 *
 * Each returned document is sent as its own sub-document
 * ({@see source_id_helper::build_sub()}); the ingestion manager clears the
 * module's previous document set with a prefix-scoped delete before sending the
 * current one, so added/removed/renamed files never leave orphaned vectors.
 *
 * An extractor implementing this interface must also implement
 * {@see content_extractor}; the manager prefers the multi-document method when
 * present and falls back to {@see content_extractor::extract()} otherwise.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface multi_document_extractor {
    /**
     * Extract one or more documents from a course module.
     *
     * The list may also contain **skip notices** for items that exist but are
     * not being sent: `['skipped' => true, 'skipreason' => <translated
     * reason>, 'title' => ..., 'content_type' => ...]`, without content and
     * without a suffix. They never reach the destination and never shift the
     * suffixes of the documents that do — a file left out must not renumber
     * the sub-documents around it — but they are logged and shown in the
     * preview, so an unsupported file has an answer rather than an absence.
     *
     * @param \cm_info $cm The course module info.
     * @return array<int, array{content: string, content_type: string, title: string, suffix: string}>
     *         A list of documents and, optionally, skip notices. Every document
     *         must include a non-empty `suffix` that is unique within the
     *         module. An empty list means "no content".
     */
    public function extract_documents(\cm_info $cm): array;
}

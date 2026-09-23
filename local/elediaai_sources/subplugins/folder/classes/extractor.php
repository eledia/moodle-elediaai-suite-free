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

namespace aisourcesextractor_folder;

use local_elediaai_sources\content_extractor;
use local_elediaai_sources\format_matrix;
use local_elediaai_sources\multi_document_extractor;

/**
 * Content extractor for mod_folder activities.
 *
 * Emits one document per exportable file, plus one for the folder description,
 * so each file keeps its native content type and is parsed independently by
 * the RAG service rather than being reduced to a filename reference. Which
 * types count as document formats is not decided here but in the support
 * matrix ({@see format_matrix::extractable_types()}); whether the active
 * destination can take one of them today is decided later, in the ingestion
 * manager. Everything else — an image, a video, a spreadsheet that is not
 * released — leaves a skip notice naming itself and the reason, because a
 * folder that silently exports four of its nine files is indistinguishable
 * from one that is fully indexed.
 *
 * @package    aisourcesextractor_folder
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class extractor implements content_extractor, multi_document_extractor {
    /**
     * Check whether this extractor supports the given module.
     *
     * @param \cm_info $cm The course module info.
     * @return bool True if this is a folder module.
     */
    public function supports(\cm_info $cm): bool {
        return $cm->modname === 'folder';
    }

    /**
     * Extract one document per supported file (plus the folder description).
     *
     * Each file keeps its native MIME type, so PDFs are forwarded as-is and
     * parsed by the RAG service rather than reduced to a filename reference.
     *
     * @param \cm_info $cm The course module info.
     * @return array<int, array{content: string, content_type: string, title: string, suffix: string}>
     */
    public function extract_documents(\cm_info $cm): array {
        [$introhtml, $files, $skipped] = self::collect($cm);

        $documents = [];

        if ($introhtml !== '') {
            $documents[] = [
                'content' => $introhtml,
                'content_type' => 'text/html',
                'title' => $cm->get_formatted_name(),
                'suffix' => 'intro',
            ];
        }

        $index = 0;
        foreach ($files as $file) {
            $index++;
            $documents[] = [
                'content' => $file['content'],
                'content_type' => $file['mimetype'],
                'title' => $file['filename'],
                'suffix' => 'file' . $index,
            ];
        }

        // Appended, and without a suffix of their own: a notice must never
        // shift the numbering of the files that are sent, or adding one
        // unreadable file would renumber — and therefore re-upsert — every
        // sub-document behind it.
        foreach ($skipped as $file) {
            $documents[] = [
                'skipped' => true,
                'skipreason' => $file['reason'],
                'content' => '',
                'content_type' => $file['mimetype'],
                'title' => $file['filename'],
            ];
        }

        return $documents;
    }

    /**
     * Single-document fallback (interface compliance).
     *
     * The ingestion manager prefers {@see extract_documents()}; this preserves
     * a usable single-document representation for any caller that uses the base
     * {@see content_extractor} contract directly.
     *
     * @param \cm_info $cm The course module info.
     * @return array|null Extracted document data, or null if no content.
     */
    public function extract(\cm_info $cm): ?array {
        [$introhtml, $files] = self::collect($cm);

        if ($introhtml === '' && empty($files)) {
            return null;
        }

        if ($introhtml === '' && count($files) === 1) {
            $single = reset($files);
            return [
                'content' => $single['content'],
                'content_type' => $single['mimetype'],
                'title' => $single['filename'],
            ];
        }

        $html = $introhtml;
        foreach ($files as $ef) {
            $html .= '<h2>' . htmlspecialchars($ef['filename'], ENT_QUOTES, 'UTF-8') . '</h2>' . "\n";
            if ($ef['mimetype'] === 'text/html') {
                $html .= $ef['content'] . "\n";
            } else if ($ef['mimetype'] === 'text/plain') {
                $html .= '<pre>' . htmlspecialchars($ef['content'], ENT_QUOTES, 'UTF-8') . '</pre>' . "\n";
            } else {
                $html .= '<p>[' . htmlspecialchars($ef['filename'], ENT_QUOTES, 'UTF-8') . ']</p>' . "\n";
            }
        }

        return $html === '' ? null : [
            'content' => $html,
            'content_type' => 'text/html',
            'title' => $cm->get_formatted_name(),
        ];
    }

    /**
     * Collect the folder description, its exportable files and the rest.
     *
     * @param \cm_info $cm The course module info.
     * @return array{0: string, 1: array<int, array{filename: string, mimetype: string, content: string}>,
     *         2: array<int, array{filename: string, mimetype: string, reason: string}>}
     */
    private static function collect(\cm_info $cm): array {
        global $DB;

        $folder = $DB->get_record('folder', ['id' => $cm->instance], 'id, name, intro', MUST_EXIST);
        $context = \core\context\module::instance($cm->id);

        $introhtml = '';
        if (!empty($folder->intro)) {
            $introhtml = file_rewrite_pluginfile_urls(
                $folder->intro,
                'pluginfile.php',
                $context->id,
                'mod_folder',
                'intro',
                0,
            );
        }

        $fs = get_file_storage();
        $rawfiles = $fs->get_area_files($context->id, 'mod_folder', 'content', 0, 'sortorder, id', false);

        $exportable = format_matrix::extractable_types();

        $files = [];
        $skipped = [];
        foreach ($rawfiles as $file) {
            $filename = $file->get_filename();
            $mimetype = format_matrix::resolve($file->get_mimetype(), $filename);

            if (!in_array($mimetype, $exportable, true)) {
                $skipped[] = [
                    'filename' => $filename,
                    'mimetype' => $mimetype,
                    'reason' => format_matrix::skip_reason($mimetype, $filename),
                ];
                continue;
            }

            $content = $file->get_content();
            if (empty($content)) {
                $skipped[] = [
                    'filename' => $filename,
                    'mimetype' => $mimetype,
                    'reason' => get_string('skipreason_empty', 'local_elediaai_sources', $filename),
                ];
                continue;
            }

            $files[] = [
                'filename' => $filename,
                'mimetype' => $mimetype,
                'content' => $content,
            ];
        }

        return [$introhtml, $files, $skipped];
    }
}

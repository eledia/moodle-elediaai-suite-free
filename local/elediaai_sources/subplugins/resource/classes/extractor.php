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

namespace aisourcesextractor_resource;

use local_elediaai_sources\content_extractor;
use local_elediaai_sources\format_matrix;

/**
 * Content extractor for mod_resource activities.
 *
 * Reads the main file from Moodle's file storage and returns its raw bytes.
 * Nothing is parsed here — the destination does that, which is also why this
 * file keeps no list of acceptable formats any more: the support matrix says
 * which formats are document formats at all ({@see
 * format_matrix::extractable_types()}), and the ingestion manager decides
 * whether the active destination can take this one today.
 *
 * @package    aisourcesextractor_resource
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class extractor implements content_extractor {
    /**
     * Check whether this extractor supports the given module.
     *
     * @param \cm_info $cm The course module info.
     * @return bool True if this is a resource module.
     */
    public function supports(\cm_info $cm): bool {
        return $cm->modname === 'resource';
    }

    /**
     * Extract the main file from a resource activity.
     *
     * Retrieves the primary file from Moodle file storage, checks its MIME
     * type against what may currently be exported, and returns the raw
     * content. A file that exists but may not be sent comes back as a skip
     * notice rather than as null: "this resource holds a .docx and the
     * pipeline cannot read it yet" is an answer, "no content" is not.
     *
     * @param \cm_info $cm The course module info.
     * @return array|null Extracted document data, a skip notice, or null if
     *                    the resource holds no file at all.
     */
    public function extract(\cm_info $cm): ?array {
        $context = \core\context\module::instance($cm->id);
        $fs = get_file_storage();

        // Get files in the resource content area (excluding directories).
        $files = $fs->get_area_files(
            $context->id,
            'mod_resource',
            'content',
            0,
            'sortorder DESC, id ASC',
            false
        );

        if (empty($files)) {
            return null;
        }

        // Get the main file (first non-directory file).
        $file = reset($files);

        if (!$file) {
            return null;
        }

        $filename = $file->get_filename();
        $mimetype = format_matrix::resolve($file->get_mimetype(), $filename);

        if (!in_array($mimetype, format_matrix::extractable_types(), true)) {
            return [
                'skipped' => true,
                'skipreason' => format_matrix::skip_reason($mimetype, $filename),
                'content' => '',
                'content_type' => $mimetype,
                'title' => $filename,
            ];
        }

        $content = $file->get_content();

        if (empty($content)) {
            return [
                'skipped' => true,
                'skipreason' => get_string('skipreason_empty', 'local_elediaai_sources', $filename),
                'content' => '',
                'content_type' => $mimetype,
                'title' => $filename,
            ];
        }

        return [
            'content' => $content,
            'content_type' => $mimetype,
            'title' => $filename,
        ];
    }
}

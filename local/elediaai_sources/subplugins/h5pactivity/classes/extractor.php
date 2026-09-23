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

namespace aisourcesextractor_h5pactivity;

use local_elediaai_sources\content_extractor;
use local_elediaai_sources\h5p_text_extractor;

/**
 * Content extractor for mod_h5pactivity activities.
 *
 * Extracts educational text from deployed H5P content by reading
 * the {@code h5p.jsoncontent} field and recursively harvesting
 * text values. Returns null if the H5P package has not been
 * deployed yet (no {@code h5p} row exists).
 *
 * @package    aisourcesextractor_h5pactivity
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class extractor implements content_extractor {
    /**
     * Check whether this extractor supports the given module.
     *
     * @param \cm_info $cm The course module info.
     * @return bool True if this is an h5pactivity module.
     */
    public function supports(\cm_info $cm): bool {
        return $cm->modname === 'h5pactivity';
    }

    /**
     * Extract text content from a deployed H5P activity.
     *
     * Resolves the activity's .h5p package file, looks up the deployed
     * {@code h5p} record via pathnamehash, and extracts text from the
     * content JSON. Returns null when the package has not been deployed.
     *
     * @param \cm_info $cm The course module info.
     * @return array|null Extracted document data, or null if not extractable.
     */
    public function extract(\cm_info $cm): ?array {
        global $DB;

        $activity = $DB->get_record('h5pactivity', ['id' => $cm->instance], 'id, name, intro', MUST_EXIST);

        // Get the .h5p package file from the activity's file area.
        $context = \core\context\module::instance($cm->id);
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $context->id,
            'mod_h5pactivity',
            'package',
            0,
            'id',
            false,
        );

        $file = reset($files);
        if (!$file) {
            return null;
        }

        // Resolve the content JSON: the deployed record if available, otherwise
        // straight from the package zip (so undeployed activities still index).
        $json = h5p_text_extractor::jsoncontent_from_file($file);
        if ($json === null) {
            return null;
        }

        $text = h5p_text_extractor::extract_text_from_json($json);
        if ($text === '') {
            return null;
        }

        // Lead with the activity description (intro) when present; the activity
        // name itself is added centrally by the ingestion manager.
        $intro = self::intro_text($activity->intro ?? '');
        $content = $intro !== '' ? $intro . "\n\n" . $text : $text;

        return [
            'content' => $content,
            'content_type' => 'text/plain',
            'title' => $activity->name,
        ];
    }

    /**
     * Reduce an intro HTML field to clean plain text.
     *
     * @param string $intro The raw intro HTML.
     * @return string Plain-text intro (may be empty).
     */
    private static function intro_text(string $intro): string {
        if (trim($intro) === '') {
            return '';
        }
        return trim(html_entity_decode(strip_tags($intro), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}

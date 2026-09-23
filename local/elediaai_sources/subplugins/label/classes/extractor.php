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

namespace aisourcesextractor_label;

use local_elediaai_sources\content_extractor;

/**
 * Content extractor for mod_label activities.
 *
 * Extracts the HTML intro content from a label activity.
 *
 * @package    aisourcesextractor_label
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class extractor implements content_extractor {
    /**
     * Check whether this extractor supports the given module.
     *
     * @param \cm_info $cm The course module info.
     * @return bool True if this is a label module.
     */
    public function supports(\cm_info $cm): bool {
        return $cm->modname === 'label';
    }

    /**
     * Extract content from a label activity.
     *
     * Labels store their content in the intro field.
     *
     * @param \cm_info $cm The course module info.
     * @return array|null Extracted document data, or null if no content.
     */
    public function extract(\cm_info $cm): ?array {
        global $DB;

        $label = $DB->get_record('label', ['id' => $cm->instance], 'id, name, intro', MUST_EXIST);

        if (empty($label->intro)) {
            return null;
        }

        // Rewrite @@PLUGINFILE@@ tokens to full URLs so that the
        // H5P embed helper can resolve any embedded H5P content.
        $context = \core\context\module::instance($cm->id);
        $content = file_rewrite_pluginfile_urls(
            $label->intro,
            'pluginfile.php',
            $context->id,
            'mod_label',
            'intro',
            0,
        );

        return [
            'content' => $content,
            'content_type' => 'text/html',
            'title' => $label->name ?: get_string('pluginname', 'mod_label'),
        ];
    }
}

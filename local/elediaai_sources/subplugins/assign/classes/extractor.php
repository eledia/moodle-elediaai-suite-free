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

namespace aisourcesextractor_assign;

use local_elediaai_sources\content_extractor;
use local_elediaai_sources\grading_criteria;

/**
 * Content extractor for mod_assign activities.
 *
 * Extracts the assignment description (intro) and optional additional
 * activity instructions. No student submissions are included.
 *
 * @package    aisourcesextractor_assign
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class extractor implements content_extractor {
    /**
     * Check whether this extractor supports the given module.
     *
     * @param \cm_info $cm The course module info.
     * @return bool True if this is an assign module.
     */
    public function supports(\cm_info $cm): bool {
        return $cm->modname === 'assign';
    }

    /**
     * Extract content from an assignment activity.
     *
     * @param \cm_info $cm The course module info.
     * @return array|null Extracted document data, or null if no content.
     */
    public function extract(\cm_info $cm): ?array {
        global $DB;

        $assign = $DB->get_record('assign', ['id' => $cm->instance], 'id, name, intro, activity', MUST_EXIST);

        $context = \core\context\module::instance($cm->id);
        $html = '';

        if (!empty($assign->intro)) {
            $html .= file_rewrite_pluginfile_urls(
                $assign->intro,
                'pluginfile.php',
                $context->id,
                'mod_assign',
                'intro',
                0,
            );
        }

        if (!empty($assign->activity)) {
            $html .= file_rewrite_pluginfile_urls(
                $assign->activity,
                'pluginfile.php',
                $context->id,
                'mod_assign',
                'activity',
                0,
            );
        }

        // Advanced grading criteria (rubric / marking guide) when configured —
        // describes what the submission is assessed on.
        $html .= grading_criteria::html($context->id, 'mod_assign', 'submissions');

        if (empty($html)) {
            return null;
        }

        return [
            'content' => $html,
            'content_type' => 'text/html',
            'title' => $assign->name,
        ];
    }
}

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

namespace aisourcesextractor_quiz;

use local_elediaai_sources\content_extractor;

/**
 * Content extractor for mod_quiz activities.
 *
 * Extracts the quiz name and intro only (ingestionpipeline#23). Question
 * texts, answer options, answer feedback, general feedback, hints and the
 * overall feedback bands are deliberately left out: the index knows neither
 * the quiz's review options nor whether a learner is in an attempt, so any
 * of it would let the tutor reveal solutions during a running quiz. The
 * tutor reads a learner's own attempt through the quiz tools, which apply
 * the teacher's review options.
 *
 * @package    aisourcesextractor_quiz
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class extractor implements content_extractor {
    /**
     * Check whether this extractor supports the given module.
     *
     * @param \cm_info $cm The course module info.
     * @return bool True if this is a quiz module.
     */
    public function supports(\cm_info $cm): bool {
        return $cm->modname === 'quiz';
    }

    /**
     * Extract content from a quiz activity: its intro, titled with its name.
     *
     * A quiz without an intro yields no document; the ingestion manager then
     * removes whatever an earlier version of this extractor had sent for it.
     *
     * @param \cm_info $cm The course module info.
     * @return array|null Extracted document data, or null if no content.
     */
    public function extract(\cm_info $cm): ?array {
        global $DB;

        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], 'id, name, intro', MUST_EXIST);

        if (empty($quiz->intro)) {
            return null;
        }

        $context = \core\context\module::instance($cm->id);
        $html = file_rewrite_pluginfile_urls(
            $quiz->intro,
            'pluginfile.php',
            $context->id,
            'mod_quiz',
            'intro',
            0,
        );

        return [
            'content' => $html,
            'content_type' => 'text/html',
            'title' => $quiz->name,
        ];
    }
}

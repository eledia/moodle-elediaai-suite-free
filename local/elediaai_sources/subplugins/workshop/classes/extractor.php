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

namespace aisourcesextractor_workshop;

use local_elediaai_sources\content_extractor;

/**
 * Content extractor for mod_workshop activities.
 *
 * Extracts the workshop intro, author/reviewer instructions, and
 * conclusion. No student submissions or assessments are included.
 *
 * @package    aisourcesextractor_workshop
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class extractor implements content_extractor {
    /**
     * Check whether this extractor supports the given module.
     *
     * @param \cm_info $cm The course module info.
     * @return bool True if this is a workshop module.
     */
    public function supports(\cm_info $cm): bool {
        return $cm->modname === 'workshop';
    }

    /**
     * Extract content from a workshop activity.
     *
     * @param \cm_info $cm The course module info.
     * @return array|null Extracted document data, or null if no content.
     */
    public function extract(\cm_info $cm): ?array {
        global $DB;

        $workshop = $DB->get_record(
            'workshop',
            ['id' => $cm->instance],
            'id, name, intro, instructauthors, instructreviewers, conclusion, strategy',
            MUST_EXIST,
        );

        $context = \core\context\module::instance($cm->id);
        $parts = [];

        $fields = [
            'intro' => 'intro',
            'instructauthors' => 'instructauthors',
            'instructreviewers' => 'instructreviewers',
            'conclusion' => 'conclusion',
        ];

        foreach ($fields as $dbfield => $filearea) {
            if (!empty($workshop->$dbfield)) {
                $parts[] = file_rewrite_pluginfile_urls(
                    $workshop->$dbfield,
                    'pluginfile.php',
                    $context->id,
                    'mod_workshop',
                    $filearea,
                    0,
                );
            }
        }

        // Assessment criteria: the dimensions of the configured grading
        // strategy describe what reviewers evaluate — valuable guidance.
        $criteria = self::strategy_criteria($DB, $workshop);
        if ($criteria !== '') {
            $parts[] = $criteria;
        }

        if (empty($parts)) {
            return null;
        }

        return [
            'content' => implode("\n", $parts),
            'content_type' => 'text/html',
            'title' => $workshop->name,
        ];
    }

    /**
     * Render the dimension descriptions of the workshop's grading strategy.
     *
     * All four core strategies (accumulative, comments, numerrors, rubric)
     * store one row per assessment dimension with a `description` field in a
     * table named workshopform_{strategy}, keyed by workshopid. The rubric
     * strategy additionally has level definitions.
     *
     * @param \moodle_database $db The database.
     * @param \stdClass $workshop The workshop record (needs id, strategy).
     * @return string An HTML fragment (may be empty).
     */
    private static function strategy_criteria(\moodle_database $db, \stdClass $workshop): string {
        $strategy = (string) ($workshop->strategy ?? '');
        $allowed = ['accumulative', 'comments', 'numerrors', 'rubric'];
        if (!in_array($strategy, $allowed, true)) {
            return '';
        }

        $table = 'workshopform_' . $strategy;
        if (!$db->get_manager()->table_exists($table)) {
            return '';
        }

        $dimensions = $db->get_records(
            $table,
            ['workshopid' => (int) $workshop->id],
            'sort ASC',
            'id, description'
        );
        if (empty($dimensions)) {
            return '';
        }

        // Rubric level definitions, grouped by dimension.
        $levelsbydim = [];
        if ($strategy === 'rubric' && $db->get_manager()->table_exists('workshopform_rubric_levels')) {
            [$insql, $params] = $db->get_in_or_equal(array_keys($dimensions), SQL_PARAMS_NAMED);
            $levels = $db->get_records_select(
                'workshopform_rubric_levels',
                "dimensionid {$insql}",
                $params,
                'dimensionid ASC, grade ASC',
                'id, dimensionid, definition'
            );
            foreach ($levels as $level) {
                $levelsbydim[$level->dimensionid][] = $level->definition;
            }
        }

        $items = '';
        foreach ($dimensions as $dim) {
            $desc = trim(html_entity_decode(
                strip_tags((string) $dim->description),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            ));
            if ($desc === '') {
                continue;
            }
            $items .= '<li>' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8');
            if (!empty($levelsbydim[$dim->id])) {
                $opts = [];
                foreach ($levelsbydim[$dim->id] as $def) {
                    $def = trim(html_entity_decode(strip_tags((string) $def), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    if ($def !== '') {
                        $opts[] = htmlspecialchars($def, ENT_QUOTES, 'UTF-8');
                    }
                }
                if (!empty($opts)) {
                    $items .= '<ul><li>' . implode('</li><li>', $opts) . '</li></ul>';
                }
            }
            $items .= '</li>' . "\n";
        }

        if ($items === '') {
            return '';
        }

        return '<h2>' . get_string('gradingcriteria', 'local_elediaai_sources') . '</h2>' . "\n"
            . '<ul>' . "\n" . $items . '</ul>' . "\n";
    }
}

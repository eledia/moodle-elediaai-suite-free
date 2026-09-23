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
 * Extracts advanced-grading criteria (rubric / marking guide) as readable HTML.
 *
 * Many activities are graded with a rubric or marking guide defined through
 * Moodle's core advanced-grading framework (the `gradingform_*` plugins). The
 * criteria describe *what learners are assessed on* — high-value content for a
 * tutoring agent ("how will I be graded?") that is otherwise never sent to the
 * RAG service. This reads the active grading definition for a given context +
 * component + area and renders its criteria and rubric levels as an HTML
 * fragment, or returns '' when no advanced grading is configured.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grading_criteria {
    /**
     * Render the active grading definition's criteria for one grading area.
     *
     * @param int $contextid The module context id.
     * @param string $component The owning component, e.g. 'mod_assign'.
     * @param string $area The grading area name, e.g. 'submissions'.
     * @return string An HTML fragment (may be empty).
     */
    public static function html(int $contextid, string $component, string $area): string {
        global $DB;

        $definition = $DB->get_record_sql(
            "SELECT gd.id, gd.method, gd.name, gd.description
               FROM {grading_areas} ga
               JOIN {grading_definitions} gd ON gd.areaid = ga.id AND gd.method = ga.activemethod
              WHERE ga.contextid = :contextid
                AND ga.component = :component
                AND ga.areaname = :area",
            ['contextid' => $contextid, 'component' => $component, 'area' => $area],
        );
        if (!$definition) {
            return '';
        }

        $body = '';
        if (trim((string) $definition->description) !== '') {
            $body .= '<p>' . self::esc(self::clean($definition->description)) . '</p>' . "\n";
        }

        if ($definition->method === 'rubric') {
            $body .= self::rubric((int) $definition->id);
        } else if ($definition->method === 'guide') {
            $body .= self::guide((int) $definition->id);
        }

        if (trim($body) === '') {
            return '';
        }

        return '<h2>' . get_string('gradingcriteria', 'local_elediaai_sources') . '</h2>' . "\n" . $body;
    }

    /**
     * Render rubric criteria and their levels.
     *
     * @param int $definitionid The grading definition id.
     * @return string HTML.
     */
    private static function rubric(int $definitionid): string {
        global $DB;

        $criteria = $DB->get_records(
            'gradingform_rubric_criteria',
            ['definitionid' => $definitionid],
            'sortorder ASC',
            'id, description'
        );
        if (empty($criteria)) {
            return '';
        }

        $criterionids = array_keys($criteria);
        [$insql, $params] = $DB->get_in_or_equal($criterionids, SQL_PARAMS_NAMED);
        $levels = $DB->get_records_select(
            'gradingform_rubric_levels',
            "criterionid {$insql}",
            $params,
            'criterionid ASC, score ASC',
            'id, criterionid, score, definition'
        );
        $levelsbycriterion = [];
        foreach ($levels as $level) {
            $levelsbycriterion[$level->criterionid][] = $level;
        }

        $html = '<ul>' . "\n";
        foreach ($criteria as $criterion) {
            $desc = self::clean($criterion->description);
            if ($desc === '') {
                continue;
            }
            $html .= '<li>' . self::esc($desc);
            if (!empty($levelsbycriterion[$criterion->id])) {
                $opts = [];
                foreach ($levelsbycriterion[$criterion->id] as $level) {
                    $leveltext = self::clean($level->definition);
                    if ($leveltext !== '') {
                        $opts[] = self::esc($leveltext . ' (' . self::num($level->score) . ')');
                    }
                }
                if (!empty($opts)) {
                    $html .= '<ul><li>' . implode('</li><li>', $opts) . '</li></ul>';
                }
            }
            $html .= '</li>' . "\n";
        }
        $html .= '</ul>' . "\n";
        return $html;
    }

    /**
     * Render marking-guide criteria and their marker descriptions.
     *
     * @param int $definitionid The grading definition id.
     * @return string HTML.
     */
    private static function guide(int $definitionid): string {
        global $DB;

        $criteria = $DB->get_records(
            'gradingform_guide_criteria',
            ['definitionid' => $definitionid],
            'sortorder ASC',
            'id, shortname, description, descriptionmarkers'
        );
        if (empty($criteria)) {
            return '';
        }

        $html = '<ul>' . "\n";
        foreach ($criteria as $criterion) {
            $parts = array_filter([
                self::clean((string) $criterion->shortname),
                self::clean((string) $criterion->description),
                self::clean((string) $criterion->descriptionmarkers),
            ], static fn($p) => $p !== '');
            if (!empty($parts)) {
                $html .= '<li>' . self::esc(implode(' — ', $parts)) . '</li>' . "\n";
            }
        }
        $html .= '</ul>' . "\n";
        return $html;
    }

    /**
     * Strip tags / decode entities / trim a stored field.
     *
     * @param string $value The raw value.
     * @return string Clean text.
     */
    private static function clean(string $value): string {
        return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * Escape text for the HTML fragments sent to the RAG service.
     *
     * @param string $value The plain text value.
     * @return string HTML-safe text.
     */
    private static function esc(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Format a rubric score, dropping trailing zeros.
     *
     * @param mixed $score The score value.
     * @return string
     */
    private static function num($score): string {
        return rtrim(rtrim(number_format((float) $score, 2, '.', ''), '0'), '.');
    }
}

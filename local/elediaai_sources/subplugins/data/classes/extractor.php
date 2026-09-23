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

namespace aisourcesextractor_data;

use local_elediaai_sources\content_extractor;

/**
 * Content extractor for mod_data (Database) activities.
 *
 * Extracts the activity intro and all approved text-type field values.
 * Only text and textarea fields are included; file/picture fields are
 * skipped. Records that require approval must be approved to be indexed.
 *
 * @package    aisourcesextractor_data
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class extractor implements content_extractor {
    /** @var string[] Field types that contain extractable text. */
    private const TEXT_FIELD_TYPES = [
        'text',
        'textarea',
        'url',
        'menu',
        'checkbox',
        'radiobutton',
        'multimenu',
    ];

    /**
     * Check whether this extractor supports the given module.
     *
     * @param \cm_info $cm The course module info.
     * @return bool True if this is a data module.
     */
    public function supports(\cm_info $cm): bool {
        return $cm->modname === 'data';
    }

    /**
     * Extract content from a database activity.
     *
     * Gathers the activity intro and all approved records' text-type
     * field values, formatted as an HTML document with field labels.
     *
     * @param \cm_info $cm The course module info.
     * @return array|null Extracted document data, or null if no content.
     */
    public function extract(\cm_info $cm): ?array {
        global $DB;

        $data = $DB->get_record(
            'data',
            ['id' => $cm->instance],
            'id, name, intro, approval',
            MUST_EXIST,
        );
        $context = \core\context\module::instance($cm->id);

        $html = '';

        // Activity intro.
        if (!empty($data->intro)) {
            $html .= file_rewrite_pluginfile_urls(
                $data->intro,
                'pluginfile.php',
                $context->id,
                'mod_data',
                'intro',
                0,
            );
        }

        // Build the list of text-type field IDs and their names.
        [$typesql, $typeparams] = $DB->get_in_or_equal(self::TEXT_FIELD_TYPES, SQL_PARAMS_NAMED);
        $fields = $DB->get_records_select(
            'data_fields',
            "dataid = :dataid AND type {$typesql}",
            array_merge(['dataid' => $data->id], $typeparams),
            'id ASC',
            'id, name, type, description',
        );

        // Field definitions (schema): name + description give the records
        // meaning. Emitted only alongside actual record content (below), so an
        // empty database is still skipped.
        $fielddefs = '';
        foreach ($fields as $field) {
            $fname = htmlspecialchars((string) $field->name, ENT_QUOTES, 'UTF-8');
            $fdesc = trim(html_entity_decode(
                strip_tags((string) ($field->description ?? '')),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            ));
            $fielddefs .= '<li><strong>' . $fname . '</strong>'
                . ($fdesc !== '' ? ': ' . htmlspecialchars($fdesc, ENT_QUOTES, 'UTF-8') : '')
                . '</li>' . "\n";
        }

        if (empty($fields)) {
            // No text fields defined — return intro only if present.
            return empty($html) ? null : [
                'content' => $html,
                'content_type' => 'text/html',
                'title' => $data->name,
            ];
        }

        // Get approved text content joined with field info.
        $fieldids = array_keys($fields);
        [$finsql, $finparams] = $DB->get_in_or_equal($fieldids, SQL_PARAMS_NAMED);

        $sql = "SELECT dc.id,
                       dc.content,
                       dc.content1,
                       dc.fieldid,
                       dc.recordid
                  FROM {data_content} dc
                  JOIN {data_records} dr ON dr.id = dc.recordid
                 WHERE dr.dataid = :dataid
                   AND dr.approved = 1
                   AND dc.fieldid {$finsql}
                   AND dc.content IS NOT NULL
                   AND dc.content <> ''
              ORDER BY dr.id ASC, dc.fieldid ASC";

        $contents = $DB->get_records_sql(
            $sql,
            array_merge(['dataid' => $data->id], $finparams),
        );

        if (!empty($contents)) {
            // Lead the records with the field schema for context.
            if ($fielddefs !== '') {
                $html .= '<ul>' . "\n" . $fielddefs . '</ul>' . "\n";
            }
            $currentrecord = null;
            foreach ($contents as $c) {
                if ($currentrecord !== $c->recordid) {
                    $currentrecord = $c->recordid;
                    $html .= '<hr>' . "\n";
                }

                $fieldname = $fields[$c->fieldid]->name ?? '';
                $value = $c->content;

                $html .= '<p><strong>' . htmlspecialchars($fieldname, ENT_QUOTES, 'UTF-8') . ':</strong> ';
                $html .= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '</p>' . "\n";
            }
        }

        if (empty($html)) {
            return null;
        }

        return [
            'content' => $html,
            'content_type' => 'text/html',
            'title' => $data->name,
        ];
    }
}

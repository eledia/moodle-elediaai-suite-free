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

namespace aisourcesextractor_glossary;

use local_elediaai_sources\content_extractor;

/**
 * Content extractor for mod_glossary activities.
 *
 * Collects all approved glossary entries, combines each term and its
 * definition into a single HTML document, and sends it as one payload.
 * This allows the RAG service to search across all glossary terms.
 *
 * @package    aisourcesextractor_glossary
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class extractor implements content_extractor {
    /**
     * Check whether this extractor supports the given module.
     *
     * @param \cm_info $cm The course module info.
     * @return bool True if this is a glossary module.
     */
    public function supports(\cm_info $cm): bool {
        return $cm->modname === 'glossary';
    }

    /**
     * Extract content from a glossary activity.
     *
     * Retrieves all approved entries and combines them into a single
     * HTML document with definition-list markup. Empty glossaries
     * return null.
     *
     * @param \cm_info $cm The course module info.
     * @return array|null Extracted document data, or null if no entries.
     */
    public function extract(\cm_info $cm): ?array {
        global $DB;

        $glossary = $DB->get_record('glossary', ['id' => $cm->instance], 'id, name, intro', MUST_EXIST);

        // Get all approved entries, ordered alphabetically by concept.
        $entries = $DB->get_records('glossary_entries', [
            'glossaryid' => $glossary->id,
            'approved' => 1,
        ], 'concept ASC', 'id, concept, definition');

        if (empty($entries)) {
            return null;
        }

        $context = \core\context\module::instance($cm->id);

        // Pre-fetch entry aliases (secondary search terms / synonyms) so they
        // are indexed alongside the primary concept.
        [$insql, $inparams] = $DB->get_in_or_equal(array_keys($entries), SQL_PARAMS_NAMED);
        $aliasrows = $DB->get_records_select(
            'glossary_alias',
            "entryid {$insql}",
            $inparams,
            'id ASC',
            'id, entryid, alias'
        );
        $aliasesbyentry = [];
        foreach ($aliasrows as $row) {
            $alias = trim((string) $row->alias);
            if ($alias !== '') {
                $aliasesbyentry[$row->entryid][] = $alias;
            }
        }

        // Build a single HTML document from all entries.
        $html = '<h1>' . htmlspecialchars($glossary->name, ENT_QUOTES, 'UTF-8') . '</h1>' . "\n";

        // Glossary description (intro), when set.
        if (!empty($glossary->intro)) {
            $html .= file_rewrite_pluginfile_urls(
                $glossary->intro,
                'pluginfile.php',
                $context->id,
                'mod_glossary',
                'intro',
                0,
            ) . "\n";
        }

        $html .= '<dl>' . "\n";

        foreach ($entries as $entry) {
            // Rewrite @@PLUGINFILE@@ tokens to full URLs so that the
            // H5P embed helper can resolve any embedded H5P content.
            $definition = file_rewrite_pluginfile_urls(
                $entry->definition,
                'pluginfile.php',
                $context->id,
                'mod_glossary',
                'entry',
                $entry->id,
            );
            $html .= '  <dt>' . htmlspecialchars($entry->concept, ENT_QUOTES, 'UTF-8') . '</dt>' . "\n";
            $html .= '  <dd>' . $definition;
            if (!empty($aliasesbyentry[$entry->id])) {
                $aliases = array_map(
                    static fn($a) => htmlspecialchars($a, ENT_QUOTES, 'UTF-8'),
                    $aliasesbyentry[$entry->id],
                );
                $html .= '<p>' . get_string('alsoknownas', 'local_elediaai_sources') . ' '
                    . implode(', ', $aliases) . '</p>';
            }
            $html .= '</dd>' . "\n";
        }

        $html .= '</dl>' . "\n";

        return [
            'content' => $html,
            'content_type' => 'text/html',
            'title' => $glossary->name,
        ];
    }
}

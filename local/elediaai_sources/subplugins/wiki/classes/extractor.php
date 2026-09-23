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

namespace aisourcesextractor_wiki;

use local_elediaai_sources\content_extractor;

/**
 * Content extractor for mod_wiki activities.
 *
 * Extracts the wiki intro and all wiki pages' cached HTML content.
 * Pages are retrieved via the wiki → sub-wikis → pages hierarchy.
 *
 * @package    aisourcesextractor_wiki
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class extractor implements content_extractor {
    /**
     * Check whether this extractor supports the given module.
     *
     * @param \cm_info $cm The course module info.
     * @return bool True if this is a wiki module.
     */
    public function supports(\cm_info $cm): bool {
        return $cm->modname === 'wiki';
    }

    /**
     * Extract content from a wiki activity.
     *
     * Gathers the activity intro plus all page content from every
     * sub-wiki belonging to this wiki instance.
     *
     * @param \cm_info $cm The course module info.
     * @return array|null Extracted document data, or null if no content.
     */
    public function extract(\cm_info $cm): ?array {
        global $DB;

        $wiki = $DB->get_record('wiki', ['id' => $cm->instance], 'id, name, intro', MUST_EXIST);
        $context = \core\context\module::instance($cm->id);

        $html = '';

        // Wiki intro.
        if (!empty($wiki->intro)) {
            $html .= file_rewrite_pluginfile_urls(
                $wiki->intro,
                'pluginfile.php',
                $context->id,
                'mod_wiki',
                'intro',
                0,
            );
        }

        // Get all sub-wiki IDs for this wiki.
        $subwikiids = $DB->get_fieldset('wiki_subwikis', 'id', ['wikiid' => $wiki->id]);
        if (!empty($subwikiids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($subwikiids, SQL_PARAMS_NAMED);
            $pages = $DB->get_records_select(
                'wiki_pages',
                "subwikiid {$insql}",
                $inparams,
                'title ASC',
                'id, title, cachedcontent',
            );

            foreach ($pages as $page) {
                if (empty($page->cachedcontent)) {
                    continue;
                }
                $html .= '<h2>' . htmlspecialchars($page->title, ENT_QUOTES, 'UTF-8') . '</h2>' . "\n";
                $html .= $page->cachedcontent . "\n";
            }
        }

        if (empty($html)) {
            return null;
        }

        return [
            'content' => $html,
            'content_type' => 'text/html',
            'title' => $wiki->name,
        ];
    }
}

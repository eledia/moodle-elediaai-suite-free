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

namespace aisourcesextractor_book;

use local_elediaai_sources\content_extractor;

/**
 * Content extractor for mod_book activities.
 *
 * Collects all visible book chapters and combines them into a single
 * HTML document preserving chapter/subchapter hierarchy. This gives
 * the RAG service a complete view of the book content.
 *
 * @package    aisourcesextractor_book
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class extractor implements content_extractor {
    /**
     * Check whether this extractor supports the given module.
     *
     * @param \cm_info $cm The course module info.
     * @return bool True if this is a book module.
     */
    public function supports(\cm_info $cm): bool {
        return $cm->modname === 'book';
    }

    /**
     * Extract content from a book activity.
     *
     * Retrieves all visible chapters in page order and combines them
     * into a single HTML document. Chapters use <h2>, subchapters use <h3>.
     * Books with no visible chapters return null.
     *
     * @param \cm_info $cm The course module info.
     * @return array|null Extracted document data, or null if no chapters.
     */
    public function extract(\cm_info $cm): ?array {
        global $DB;

        $book = $DB->get_record('book', ['id' => $cm->instance], 'id, name', MUST_EXIST);

        // Get all visible chapters in page order.
        $chapters = $DB->get_records('book_chapters', [
            'bookid' => $book->id,
            'hidden' => 0,
        ], 'pagenum ASC', 'id, title, content, subchapter');

        if (empty($chapters)) {
            return null;
        }

        $context = \core\context\module::instance($cm->id);

        // Build a single HTML document from all chapters.
        $html = '<h1>' . htmlspecialchars($book->name, ENT_QUOTES, 'UTF-8') . '</h1>' . "\n";

        foreach ($chapters as $chapter) {
            // Use <h2> for chapters, <h3> for subchapters.
            $tag = $chapter->subchapter ? 'h3' : 'h2';
            $html .= '<' . $tag . '>' . htmlspecialchars($chapter->title, ENT_QUOTES, 'UTF-8')
                . '</' . $tag . '>' . "\n";

            // Rewrite @@PLUGINFILE@@ tokens to full URLs so that the
            // H5P embed helper can resolve any embedded H5P content.
            $content = file_rewrite_pluginfile_urls(
                $chapter->content,
                'pluginfile.php',
                $context->id,
                'mod_book',
                'chapter',
                $chapter->id,
            );
            $html .= $content . "\n";
        }

        return [
            'content' => $html,
            'content_type' => 'text/html',
            'title' => $book->name,
        ];
    }
}

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
 * Helpers for assembling the document content sent to the RAG service.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class document {
    /**
     * Prepend the activity name as a heading so every document (and therefore
     * every chunk the RAG service derives from it) is attributable to its
     * activity — enabling "which activity covers X?" retrieval.
     *
     * Applied centrally so all extractors benefit uniformly. It is a no-op when:
     * - the content is binary (e.g. application/pdf) — never corrupt the bytes;
     * - the title is empty;
     * - an HTML document already opens with an <h1> (the extractor titled it);
     * - a plain-text document already begins with the title line.
     *
     * @param string $content The extracted content.
     * @param string $contenttype The MIME type; see {@see format_matrix}.
     * @param string $title The activity name to use as the heading.
     * @return string The content with the heading prepended where appropriate.
     */
    public static function with_heading(string $content, string $contenttype, string $title): string {
        $title = trim($title);
        if ($title === '') {
            return $content;
        }

        if ($contenttype === 'text/html') {
            // Don't double up when the extractor already leads with a title.
            if (preg_match('/^\s*<h1\b/i', $content)) {
                return $content;
            }
            return '<h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>' . "\n" . $content;
        }

        if ($contenttype === 'text/markdown') {
            // Don't double up when the file already opens with a heading.
            if (preg_match('/^\s*#/', $content)) {
                return $content;
            }
            return '# ' . $title . "\n\n" . $content;
        }

        if ($contenttype === 'text/plain') {
            if (str_starts_with(ltrim($content), $title)) {
                return $content;
            }
            return $title . "\n\n" . $content;
        }

        // Binary or unknown content type: leave untouched.
        return $content;
    }

    /**
     * Truncate text content to a byte budget without splitting a multibyte
     * character, appending a short notice. Binary content (e.g. PDF) cannot be
     * safely truncated and is returned unchanged for the caller to skip.
     *
     * @param string $content The content.
     * @param string $contenttype The MIME type.
     * @param int $maxbytes The maximum allowed byte length.
     * @return array{0: string, 1: bool} The (possibly truncated) content and
     *         whether truncation was applied.
     */
    public static function truncate(string $content, string $contenttype, int $maxbytes): array {
        if ($maxbytes <= 0 || strlen($content) <= $maxbytes) {
            return [$content, false];
        }
        if (format_matrix::is_binary($contenttype)) {
            // Don't corrupt binary documents — leave for the caller to skip.
            // Asking the matrix rather than naming PDF is what keeps DOCX and
            // PPTX from being cut in half the day they are switched on.
            return [$content, false];
        }

        $notice = $contenttype === 'text/html'
            ? "\n<!-- " . get_string('contenttruncated', 'local_elediaai_sources') . ' -->'
            : "\n\n" . get_string('contenttruncated', 'local_elediaai_sources');

        $budget = max(0, $maxbytes - strlen($notice));
        $truncated = \core_text::str_max_bytes($content, $budget);

        return [$truncated . $notice, true];
    }
}

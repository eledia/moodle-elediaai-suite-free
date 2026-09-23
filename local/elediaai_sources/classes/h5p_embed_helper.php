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
 * Helper to resolve H5P placeholders embedded via the text editor.
 *
 * When a teacher inserts H5P content into a rich-text field (e.g., a
 * label or page) via TinyMCE or Atto, the editor stores a
 * {@code <div class="h5p-placeholder">URL</div>} in the HTML.
 * The display filter converts these to iframes at render time, but
 * the stored HTML never contains the actual H5P text.
 *
 * This helper detects those placeholders, resolves each URL to the
 * deployed H5P content record, extracts the educational text via
 * {@see h5p_text_extractor}, and replaces the placeholder inline.
 * If the H5P is not deployed or the file cannot be found, the
 * placeholder is silently stripped.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class h5p_embed_helper {
    /**
     * Resolve H5P placeholders in an HTML string.
     *
     * Finds all {@code <div class="h5p-placeholder"...>URL</div>} blocks,
     * attempts to resolve each URL to the H5P content JSON, extracts
     * text, and replaces the placeholder. Non-resolvable placeholders
     * are stripped entirely.
     *
     * @param string $html The raw HTML content (e.g., from a label intro).
     * @param int|null $courseid Course whose content is currently being indexed.
     * @return string The HTML with H5P placeholders replaced or stripped.
     */
    public static function resolve_h5p_placeholders(string $html, ?int $courseid = null): string {
        // Match <div class="h5p-placeholder" ...>URL</div>.
        // The URL may be wrapped in whitespace and the div may have extra attributes.
        $pattern = '#<div\b[^>]*class\s*=\s*["\']h5p-placeholder["\'][^>]*>(.*?)</div>#is';

        return preg_replace_callback($pattern, function ($matches) use ($courseid) {
            $url = trim(strip_tags($matches[1]));

            if (empty($url)) {
                return '';
            }

            $blocks = self::extract_blocks_from_url($url, $courseid);

            if (empty($blocks)) {
                // Cannot resolve (not an .h5p, file missing, or no content) — strip.
                return '';
            }

            // Render each labelled block as its own paragraph so the structure
            // (question / answer / section) survives into the ingested HTML.
            $paragraphs = array_map(
                static fn($b) => '<p>' . htmlspecialchars($b, ENT_QUOTES, 'UTF-8') . '</p>',
                $blocks,
            );
            return implode('', $paragraphs);
        }, $html);
    }

    /**
     * Attempt to extract labelled text blocks from an H5P content URL.
     *
     * Resolves the URL to a Moodle stored_file and reads its content JSON via
     * {@see h5p_text_extractor::jsoncontent_from_file()} (deployed record, or
     * the package zip as a fallback), then extracts labelled blocks.
     *
     * @param string $url The pluginfile URL pointing to the .h5p file.
     * @param int|null $courseid Course whose content is currently being indexed.
     * @return string[] Extracted text blocks (empty if not resolvable).
     */
    private static function extract_blocks_from_url(string $url, ?int $courseid): array {
        // Only handle local Moodle URLs that end in .h5p.
        if (!preg_match('/\.h5p(\?|$)/i', $url)) {
            return [];
        }

        try {
            $file = self::resolve_stored_file($url, $courseid);
        } catch (\Exception $e) {
            // Malformed pluginfile URL — unusual enough to be worth a breadcrumb.
            debugging('[h5p_embed_helper] File resolution failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }

        if ($file === null) {
            return [];
        }

        $json = h5p_text_extractor::jsoncontent_from_file($file);
        if ($json === null) {
            return [];
        }

        return h5p_text_extractor::extract_blocks_from_json($json);
    }

    /**
     * Resolve a pluginfile.php URL to a stored_file object.
     *
     * Parses the URL path to extract component, filearea, itemid, and
     * filename, then looks up the file in Moodle's file storage.
     *
     * @param string $url The pluginfile URL.
     * @param int|null $courseid Course whose content is currently being indexed.
     * @return \stored_file|null The file, or null if not found.
     */
    private static function resolve_stored_file(string $url, ?int $courseid): ?\stored_file {
        global $CFG;

        // Parse the URL and extract the path after pluginfile.php or draftfile.php.
        $parsed = parse_url($url);
        if (empty($parsed['path'])) {
            return null;
        }

        $path = $parsed['path'];

        // Find the pluginfile.php part and get everything after it.
        $pluginfilepos = strpos($path, '/pluginfile.php/');
        if ($pluginfilepos === false) {
            return null;
        }

        $relativepath = substr($path, $pluginfilepos + strlen('/pluginfile.php/'));
        $parts = explode('/', $relativepath);

        // Minimum: contextid/component/filearea/itemid/filename.
        if (count($parts) < 5) {
            return null;
        }

        $contextid = (int) array_shift($parts);
        $component = array_shift($parts);
        $filearea = array_shift($parts);

        if (!self::context_allowed($contextid, (string) $component, $courseid)) {
            return null;
        }

        // The next part could be itemid (numeric) or part of the filepath.
        // For contentbank, the pattern is: contextid/contentbank/public/itemid/filename.
        $itemid = 0;
        if (is_numeric($parts[0])) {
            $itemid = (int) array_shift($parts);
        }

        $filename = array_pop($parts);
        $filepath = '/' . (!empty($parts) ? implode('/', $parts) . '/' : '');

        $fs = get_file_storage();
        $file = $fs->get_file($contextid, $component, $filearea, $itemid, $filepath, $filename);

        return ($file && !$file->is_directory()) ? $file : null;
    }

    /**
     * Restrict placeholder resolution to the current course's context tree.
     *
     * @param int $contextid Referenced file context id.
     * @param string $component File component.
     * @param int|null $courseid Current course id, if known.
     * @return bool
     */
    private static function context_allowed(int $contextid, string $component, ?int $courseid): bool {
        if ($courseid === null) {
            return true;
        }
        if ($courseid <= 0 || $contextid <= 0) {
            return false;
        }
        if ($component !== 'contentbank' && !str_starts_with($component, 'mod_')) {
            return false;
        }

        $coursecontext = \core\context\course::instance($courseid, IGNORE_MISSING);
        $filecontext = \context::instance_by_id($contextid, IGNORE_MISSING);
        if (!$coursecontext || !$filecontext) {
            return false;
        }

        return $filecontext->id === $coursecontext->id
            || str_starts_with((string) $filecontext->path, $coursecontext->path . '/');
    }
}

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

namespace aisourcesextractor_imscp;

use local_elediaai_sources\content_extractor;

/**
 * Content extractor for mod_imscp (IMS Content Package) activities.
 *
 * Extracts the activity intro, the table of contents from the manifest
 * structure, and the text content of all HTML pages in the package.
 *
 * @package    aisourcesextractor_imscp
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class extractor implements content_extractor {
    /** @var int Maximum HTML size for regex body extraction. */
    private const MAX_BODY_REGEX_BYTES = 2097152;

    /**
     * Check whether this extractor supports the given module.
     *
     * @param \cm_info $cm The course module info.
     * @return bool True if this is an imscp module.
     */
    public function supports(\cm_info $cm): bool {
        return $cm->modname === 'imscp';
    }

    /**
     * Extract content from an IMS Content Package activity.
     *
     * Parses the manifest structure for page titles and retrieves
     * the text content of all HTML files in the deployed package.
     *
     * @param \cm_info $cm The course module info.
     * @return array|null Extracted document data, or null if no content.
     */
    public function extract(\cm_info $cm): ?array {
        global $DB;

        $imscp = $DB->get_record(
            'imscp',
            ['id' => $cm->instance],
            'id, name, intro, revision, structure',
            MUST_EXIST,
        );
        $context = \core\context\module::instance($cm->id);

        $html = '';

        // Activity intro.
        if (!empty($imscp->intro)) {
            $html .= file_rewrite_pluginfile_urls(
                $imscp->intro,
                'pluginfile.php',
                $context->id,
                'mod_imscp',
                'intro',
                0,
            );
        }

        // Get all content files for the current revision.
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $context->id,
            'mod_imscp',
            'content',
            $imscp->revision,
            'sortorder, filepath, filename',
            false,
        );

        // Index files by their relative path for lookup.
        $filesbypath = [];
        foreach ($files as $file) {
            $relpath = ltrim($file->get_filepath() . $file->get_filename(), '/');
            $filesbypath[$relpath] = $file;
        }

        // Parse the manifest structure to get page titles and resource paths.
        $pages = [];
        if (!empty($imscp->structure)) {
            $structure = json_decode($imscp->structure);
            if ($structure) {
                self::collect_pages($structure, $pages);
            }
        }

        // Extract text from HTML files referenced in the manifest.
        if (!empty($pages)) {
            foreach ($pages as $page) {
                if (!empty($page['title'])) {
                    $html .= '<h2>' . htmlspecialchars($page['title'], ENT_QUOTES, 'UTF-8') . '</h2>' . "\n";
                }

                if (!empty($page['href']) && isset($filesbypath[$page['href']])) {
                    $file = $filesbypath[$page['href']];
                    $mimetype = $file->get_mimetype();

                    if ($mimetype === 'text/html' || $mimetype === 'application/xhtml+xml') {
                        $content = $file->get_content();
                        if (!empty($content)) {
                            // Strip full HTML document wrappers if present.
                            $bodytext = self::extract_body_content($content);
                            $html .= $bodytext . "\n";
                        }
                    } else if ($mimetype === 'text/plain') {
                        $content = $file->get_content();
                        if (!empty($content)) {
                            $html .= '<pre>' . htmlspecialchars($content, ENT_QUOTES, 'UTF-8') . '</pre>' . "\n";
                        }
                    }
                }
            }
        } else {
            // No structure — fall back to extracting all HTML files.
            foreach ($files as $file) {
                $mimetype = $file->get_mimetype();
                if ($mimetype !== 'text/html' && $mimetype !== 'application/xhtml+xml') {
                    continue;
                }

                $content = $file->get_content();
                if (empty($content)) {
                    continue;
                }

                $html .= '<h2>' . htmlspecialchars($file->get_filename(), ENT_QUOTES, 'UTF-8') . '</h2>' . "\n";
                $html .= self::extract_body_content($content) . "\n";
            }
        }

        if (empty($html)) {
            return null;
        }

        return [
            'content' => $html,
            'content_type' => 'text/html',
            'title' => $imscp->name,
        ];
    }

    /**
     * Recursively collect pages from the IMS manifest structure.
     *
     * The structure is a nested array/object with 'title', 'href' (resource
     * path), and 'subitems' (child pages).
     *
     * @param mixed $node The current structure node (object or array).
     * @param array $pages Collected pages (passed by reference).
     */
    private static function collect_pages($node, array &$pages): void {
        // Handle both arrays and objects from json_decode.
        if (is_array($node)) {
            foreach ($node as $child) {
                self::collect_pages($child, $pages);
            }
            return;
        }

        if (!is_object($node)) {
            return;
        }

        $title = $node->title ?? '';
        $href = $node->href ?? '';

        // Strip query string / fragment from href.
        if (!empty($href)) {
            $href = preg_replace('/[?#].*$/', '', $href);
        }

        if (!empty($title) || !empty($href)) {
            $pages[] = ['title' => $title, 'href' => $href];
        }

        // Recurse into subitems.
        if (!empty($node->subitems)) {
            foreach ($node->subitems as $child) {
                self::collect_pages($child, $pages);
            }
        }
    }

    /**
     * Extract the body content from a full HTML document.
     *
     * If the content has a <body> tag, returns only its inner HTML.
     * Otherwise returns the content as-is.
     *
     * @param string $html The full HTML document.
     * @return string The body content.
     */
    private static function extract_body_content(string $html): string {
        if (strlen($html) > self::MAX_BODY_REGEX_BYTES) {
            return $html;
        }
        // Try to extract content between <body> and </body>.
        if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $html, $matches)) {
            return trim($matches[1]);
        }
        return $html;
    }
}

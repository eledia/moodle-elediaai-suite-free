<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

declare(strict_types=1);

namespace webservice_elediamcp\local;

use DOMDocument;
use DOMElement;
use DOMXPath;
use webservice_elediamcp\local\ai\tool_exception;

/**
 * A block of learning objectives inside a description (course, section, activity).
 *
 * The block is one element with the class {@see MARKER}, so it can be found
 * again and replaced without touching the rest of the description. A box the
 * teacher inserted by hand with "Components for Learning" (tiny_c4l, component
 * "Learning outcomes") counts as the block too, so the tool does not add a
 * second one next to it.
 *
 * With tiny_c4l installed the block uses its markup and looks like the boxes
 * teachers insert themselves; the plugin's styles.css is part of every page.
 * Without it the block is a plain heading and list.
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class learning_objectives {
    /** @var string Class that marks the block written by this tool. */
    public const MARKER = 'elediaai-learningobjectives';

    /** @var string Class of the C4L "Learning outcomes" box. */
    public const C4L_CLASS = 'c4lv-learningoutcomes';

    /** @var string Style: C4L markup. */
    public const STYLE_C4L = 'c4l';

    /** @var string Style: plain heading and list. */
    public const STYLE_PLAIN = 'plain';

    /** @var int Most objectives in one block. */
    public const MAX_ITEMS = 20;

    /** @var int Longest objective in characters. */
    public const MAX_LENGTH = 500;

    /** @var string Id of the wrapper used while parsing. */
    private const ROOT = 'elediamcp-objectives-root';

    /**
     * Static-only.
     */
    private function __construct() {
    }

    /**
     * Whether "Components for Learning" is installed on this site.
     *
     * @return bool
     */
    public static function c4l_available(): bool {
        return \core_plugin_manager::instance()->get_plugin_info('tiny_c4l') !== null;
    }

    /**
     * The style to use for a requested one ('auto', 'c4l', 'plain').
     *
     * @param string $requested
     * @return string One of the STYLE_ constants.
     */
    public static function resolve_style(string $requested): string {
        if ($requested === self::STYLE_PLAIN) {
            return self::STYLE_PLAIN;
        }
        if ($requested === self::STYLE_C4L && !self::c4l_available()) {
            throw new tool_exception('style c4l needs the plugin tiny_c4l (Components for Learning), which is not installed.');
        }
        return self::c4l_available() ? self::STYLE_C4L : self::STYLE_PLAIN;
    }

    /**
     * Clean a list of objectives: trimmed, without empty or duplicate entries.
     *
     * @param array $items
     * @return string[]
     * @throws tool_exception When there are too many or too long entries.
     */
    public static function normalise(array $items): array {
        $clean = [];
        foreach ($items as $item) {
            $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $item)));
            if ($text === '' || in_array($text, $clean, true)) {
                continue;
            }
            if (\core_text::strlen($text) > self::MAX_LENGTH) {
                throw new tool_exception('An objective is longer than ' . self::MAX_LENGTH . ' characters.');
            }
            $clean[] = $text;
        }
        if (count($clean) > self::MAX_ITEMS) {
            throw new tool_exception('At most ' . self::MAX_ITEMS . ' objectives are allowed.');
        }
        return $clean;
    }

    /**
     * The HTML of a block.
     *
     * @param string[] $items Normalised objectives.
     * @param string $heading Heading text.
     * @param string $style One of the STYLE_ constants.
     * @param bool $ordered Numbered list instead of bullets.
     * @return string
     */
    public static function render(array $items, string $heading, string $style, bool $ordered = false): string {
        $list = '';
        foreach ($items as $item) {
            $list .= '<li>' . s($item) . '</li>';
        }
        $heading = s($heading);
        if ($style === self::STYLE_C4L) {
            $classes = self::C4L_CLASS . ' ' . self::MARKER . ($ordered ? ' c4l-ordered-list-variant' : '');
            return '<div class="' . $classes . '" role="note" aria-label="' . $heading . '">'
                . '<h6 class="c4l-learningoutcomes-title">' . $heading . '</h6>'
                . '<ul class="c4l-learningoutcomes-list">' . $list . '</ul></div>';
        }
        $tag = $ordered ? 'ol' : 'ul';
        return '<div class="' . self::MARKER . '"><h4>' . $heading . '</h4><' . $tag . '>' . $list . '</' . $tag . '></div>';
    }

    /**
     * The objectives of the block in a description, or null without a block.
     *
     * @param string $html Description as HTML.
     * @return string[]|null
     */
    public static function extract(string $html): ?array {
        [, $block] = self::find($html);
        if ($block === null) {
            return null;
        }
        $items = [];
        foreach ($block->getElementsByTagName('li') as $li) {
            $text = trim(preg_replace('/\s+/u', ' ', $li->textContent));
            if ($text !== '') {
                $items[] = $text;
            }
        }
        return $items;
    }

    /**
     * The heading of the existing block, or null.
     *
     * @param string $html
     * @return string|null
     */
    public static function extract_heading(string $html): ?string {
        [, $block] = self::find($html);
        if ($block === null) {
            return null;
        }
        foreach (['h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as $tag) {
            $headings = $block->getElementsByTagName($tag);
            if ($headings->length > 0) {
                $text = trim($headings->item(0)->textContent);
                return $text === '' ? null : $text;
            }
        }
        return null;
    }

    /**
     * Put a block into a description.
     *
     * An existing block is replaced in place; otherwise the block goes to the
     * top or the bottom. An empty block HTML removes the existing one.
     *
     * @param string $html Description as HTML.
     * @param string $block New block HTML, empty to remove.
     * @param string $position 'top' or 'bottom' for a new block.
     * @return string
     */
    public static function apply(string $html, string $block, string $position = 'top'): string {
        [$doc, $existing] = self::find($html);
        $root = $doc->getElementById(self::ROOT);

        $new = null;
        if ($block !== '') {
            $new = self::fragment($doc, $block);
        }

        if ($existing !== null) {
            if ($new !== null) {
                $existing->parentNode->replaceChild($new, $existing);
            } else {
                $existing->parentNode->removeChild($existing);
            }
        } else if ($new !== null) {
            if ($position === 'bottom' || !$root->firstChild) {
                $root->appendChild($new);
            } else {
                $root->insertBefore($new, $root->firstChild);
            }
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        return trim($out);
    }

    /**
     * A description in any Moodle text format, as HTML.
     *
     * @param string $text
     * @param int $format FORMAT_ constant.
     * @param \context $context
     * @return string
     */
    public static function to_html(string $text, int $format, \context $context): string {
        if ($format === (int) FORMAT_HTML || trim($text) === '') {
            return $text;
        }
        return format_text($text, $format, ['context' => $context, 'filter' => false, 'noclean' => true, 'para' => false]);
    }

    /**
     * Parse a description and find its block.
     *
     * @param string $html
     * @return array{0: DOMDocument, 1: ?DOMElement}
     */
    private static function find(string $html): array {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<?xml encoding="UTF-8"?><html><body><div id="' . self::ROOT . '">' . $html . '</div></body></html>',
            LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($doc);
        $match = static fn(string $class): string =>
            "contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')";
        $nodes = $xpath->query('//*[' . $match(self::MARKER) . ']');
        if (!$nodes || $nodes->length === 0) {
            $nodes = $xpath->query('//*[' . $match(self::C4L_CLASS) . ']');
        }
        $block = ($nodes && $nodes->length > 0 && $nodes->item(0) instanceof DOMElement) ? $nodes->item(0) : null;
        return [$doc, $block];
    }

    /**
     * Import an HTML snippet into a document.
     *
     * @param DOMDocument $doc
     * @param string $html
     * @return \DOMNode
     */
    private static function fragment(DOMDocument $doc, string $html): \DOMNode {
        $source = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $source->loadHTML('<?xml encoding="UTF-8"?><html><body>' . $html . '</body></html>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $body = $source->getElementsByTagName('body')->item(0);
        $wrapper = $doc->createDocumentFragment();
        foreach ($body->childNodes as $child) {
            $wrapper->appendChild($doc->importNode($child, true));
        }
        return $wrapper;
    }
}

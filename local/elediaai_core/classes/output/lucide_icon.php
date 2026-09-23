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

/**
 * Lucide SVG sprite renderer.
 *
 * Replaces the former Font Awesome `<i class="fa fa-*">` glyphs with
 * inline `<svg>` markup loaded from a plugin sprite, so the suite no longer
 * depends on a webfont being present in the active theme. Callers pass the
 * historic Font-Awesome name (without the `fa-` prefix); the renderer resolves
 * it to the matching sprite symbol and wraps it in a fixed SVG frame.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Resolves a Font-Awesome icon name to inline Lucide SVG markup.
 */
final class lucide_icon {
    /** @var string Relative path to the Lucide SVG sprite. */
    private const SPRITE_FILE = '/pix/lucide.svg';

    /** @var string|null Cached SVG sprite contents. */
    private static ?string $sprite = null;

    /**
     * Read one icon body from the SVG sprite.
     *
     * @param string $key Normalised icon name.
     * @return string|null Inner SVG markup, or null when the name is unknown.
     */
    private static function icon_body(string $key): ?string {
        if (self::$sprite === null) {
            $content = file_get_contents(dirname(__DIR__, 2) . self::SPRITE_FILE);
            if ($content === false) {
                throw new \coding_exception('The local_elediaai_core Lucide sprite is missing.');
            }
            self::$sprite = $content;
        }

        $pattern = '/<symbol id="lucide-' . preg_quote($key, '/')
            . '" viewBox="0 0 24 24">(.*?)<\/symbol>/s';
        if (!preg_match($pattern, self::$sprite, $matches)) {
            return null;
        }

        return $matches[1];
    }

    /**
     * Render an inline Lucide SVG for the given Font-Awesome icon name.
     *
     * @param string $faname Historic Font-Awesome name, with or without the `fa-` prefix.
     * @param string $extraclasses Extra CSS classes appended to the default `lucide` class.
     * @return string Inline SVG markup, or empty string when the name is unknown.
     */
    public static function render(string $faname, string $extraclasses = ''): string {
        $key = preg_replace('/^fa-/', '', trim($faname));
        $inner = self::icon_body($key);
        if ($inner === null) {
            return '';
        }

        // Follow Lucide's own DOM convention (`lucide lucide-<name>`) so the
        // rendered glyph is self-describing and testable.
        $class = 'lucide lucide-' . $key;
        if ($extraclasses !== '') {
            $class .= ' ' . $extraclasses;
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"'
            . ' fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"'
            . ' stroke-linejoin="round" aria-hidden="true" focusable="false" class="' . $class . '">'
            . $inner
            . '</svg>';
    }

    /**
     * Whether the map knows the given Font-Awesome name.
     *
     * @param string $faname Historic Font-Awesome name, with or without the `fa-` prefix.
     * @return bool
     */
    public static function has(string $faname): bool {
        $key = preg_replace('/^fa-/', '', trim($faname));
        return self::icon_body($key) !== null;
    }
}

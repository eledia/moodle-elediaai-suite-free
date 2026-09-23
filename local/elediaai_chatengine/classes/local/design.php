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

declare(strict_types=1);

namespace local_elediaai_chatengine\local;

/**
 * The look a chat surface is rendered in.
 *
 * A design is a bundle of token values with a name. It is deliberately
 * incomplete: a design states only what it changes, everything else falls back
 * to the defaults declared in the engine's stylesheet. That is what makes it
 * safe to introduce — a site that defines no design, and a placement that
 * picks none, look exactly as they did before designs existed.
 *
 * Resolution runs outside in:
 *
 *   built-in defaults  <  site design  <  design picked on the instance
 *   <  values the placement resolves for itself
 *
 * The last step exists for the tutor block, which resolves a full design from
 * its own per-instance settings. Those keep the last word, so a block that was
 * branded before this existed is untouched by it.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class design {
    /** @var string The design table. */
    public const TABLE = 'local_elediaai_chateng_design';

    /** @var int Means "no design": the built-in defaults stand. */
    public const NONE = 0;

    /**
     * The design the site falls back to.
     *
     * @return int A design id, or NONE.
     */
    public static function site_default(): int {
        return max(0, (int) connection::get('sitedesign', 0));
    }

    /**
     * Designs an administrator can pick from.
     *
     * @return array<int, string> Design id => name, with NONE first.
     */
    public static function menu(): array {
        global $DB;

        $menu = [self::NONE => get_string('design_none', 'local_elediaai_chatengine')];
        foreach ($DB->get_records(self::TABLE, null, 'sortorder ASC, name ASC', 'id, name') as $design) {
            $menu[(int) $design->id] = format_string($design->name);
        }

        return $menu;
    }

    /**
     * Designs a placement instance can pick from.
     *
     * Adds the "inherit the site design" entry, which is what an instance that
     * has never been touched should mean — not "no design at all".
     *
     * @return array<int, string> Design id => name.
     */
    public static function instance_menu(): array {
        $menu = [-1 => get_string('design_inherit', 'local_elediaai_chatengine')];

        return $menu + self::menu();
    }

    /**
     * The token values of one design.
     *
     * @param int $designid The design id, or NONE.
     * @return array<string, string> Token name => value; empty for NONE.
     */
    public static function tokens(int $designid): array {
        global $DB;

        if ($designid <= self::NONE) {
            return [];
        }
        $tokens = $DB->get_field(self::TABLE, 'tokens', ['id' => $designid]);
        if ($tokens === false) {
            // A design that was deleted while an instance still pointed at it.
            // The defaults are the honest answer; guessing a replacement would
            // change a look nobody asked to change.
            return [];
        }
        $decoded = json_decode((string) $tokens, true);

        return is_array($decoded) ? self::sanitise($decoded) : [];
    }

    /**
     * The tokens that actually apply, in precedence order.
     *
     * @param int $instancedesign The design picked on the instance; -1 inherits the site.
     * @param array<string, string> $overrides Values the placement resolved itself.
     * @return array<string, string> Token name => value.
     */
    public static function resolve(int $instancedesign = -1, array $overrides = []): array {
        $designid = $instancedesign < 0 ? self::site_default() : $instancedesign;

        return array_merge(self::tokens($designid), self::sanitise($overrides));
    }

    /**
     * Serialise tokens into a scoped inline style.
     *
     * Inline rather than in a stylesheet so two differently designed surfaces
     * can sit on one page — a course chat and a site-wide one, for instance.
     *
     * @param array<string, string> $tokens Token name => value.
     * @return string The style attribute value, or '' when nothing is set.
     */
    public static function css_variables(array $tokens): string {
        $out = '';
        foreach ($tokens as $name => $value) {
            $out .= $name . ':' . $value . ';';
        }

        return $out;
    }

    /**
     * Keep anything out of a style attribute that does not belong in one.
     *
     * The values reach an inline style, so a stray brace or semicolon would let
     * a token close the declaration and start a rule of its own.
     *
     * @param array $tokens Raw token map.
     * @return array<string, string> The safe subset.
     */
    private static function sanitise(array $tokens): array {
        $safe = [];
        foreach ($tokens as $name => $value) {
            $name = (string) $name;
            $value = trim((string) $value);
            if (!preg_match('/^--[a-z0-9-]+$/', $name) || $value === '') {
                continue;
            }
            if (preg_match('/[{}<>;"\']|url\s*\(|expression\s*\(|javascript:/i', $value)) {
                continue;
            }
            $safe[$name] = $value;
        }

        return $safe;
    }
}

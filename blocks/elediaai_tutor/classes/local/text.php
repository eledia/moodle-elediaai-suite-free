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

namespace block_elediaai_tutor\local;

/**
 * Multilingual configurable texts.
 *
 * The tutor's texts (greeting, persona, prompt starters, launcher label, footer)
 * are written by admins and teachers in one field. A multilingual site writes
 * them the Moodle way, with the "Multi-language content" filter's markup
 * (`<span lang="de" class="multilang">…</span>`, or `{mlang de}…{mlang}` with
 * filter_multilang2).
 *
 * Running the whole filter stack over them would do that, but it would also
 * let glossary and activity-name autolinking into a greeting and a launcher
 * button, which is why the greeting has never been filtered. And on the
 * single-line fields Moodle only filters at all when "Filter all strings" is on.
 * So this applies the language filters and nothing else, wherever they are
 * active, whatever "Filter all strings" says.
 *
 * Texts for the model go through here as well: a persona instruction with two
 * languages in it would otherwise reach the model as both.
 *
 * @package     block_elediaai_tutor
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class text {
    /** @var string[] The language filters, in the order they are applied. */
    public const LANGUAGE_FILTERS = ['multilang2', 'multilang'];

    /**
     * The text in the current user's language.
     *
     * Unchanged when it carries no language markup, or when no language filter
     * is active in the context: then it reads exactly as it did before.
     *
     * @param string $text Raw configured text.
     * @param \context|null $context Context the text is shown in; system when null.
     * @return string
     */
    public static function localise(string $text, ?\context $context = null): string {
        if (!self::has_markup($text)) {
            return $text;
        }
        $context = $context ?? \context_system::instance();
        // Line lists (prompt starters: one question per line, each with its
        // own language blocks) are resolved line by line. The filter joins
        // blocks separated only by whitespace into one group, a line break
        // included, so two questions in two languages came out as one. A text
        // whose blocks run over several lines (a longer greeting) is resolved
        // as a whole, because a single line of it is not complete on its own.
        $lines = preg_split('/(\R)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (count($lines) > 1 && self::lines_are_complete($lines)) {
            $out = '';
            foreach ($lines as $i => $line) {
                $out .= ($i % 2 === 1 || !self::has_markup($line)) ? $line : self::filter($line, $context);
            }
            return $out;
        }
        return self::filter($text, $context);
    }

    /**
     * Whether every line carries complete language blocks of its own.
     *
     * @param string[] $lines Lines with their separators at odd positions.
     * @return bool
     */
    private static function lines_are_complete(array $lines): bool {
        foreach ($lines as $i => $line) {
            if ($i % 2 === 1) {
                continue;
            }
            $spans = substr_count($line, '<span') === substr_count($line, '</span>');
            $langs = substr_count($line, '<lang') === substr_count($line, '</lang>');
            $mlang = preg_match_all('/\{mlang\s+[^}]+\}/i', $line) === substr_count(strtolower($line), '{mlang}');
            if (!$spans || !$langs || !$mlang) {
                return false;
            }
        }
        return true;
    }

    /**
     * Run the active language filters over a text.
     *
     * @param string $text Text with language markup.
     * @param \context $context Context.
     * @return string
     */
    private static function filter(string $text, \context $context): string {
        global $CFG;
        require_once($CFG->libdir . '/filterlib.php');
        $active = filter_get_active_in_context($context);
        foreach (self::LANGUAGE_FILTERS as $name) {
            if (!array_key_exists($name, $active)) {
                continue;
            }
            $filter = self::make_filter($name, $context, (array) $active[$name]);
            if ($filter !== null) {
                $text = (string) $filter->filter($text);
            }
        }
        return $text;
    }

    /**
     * Whether a text carries language markup of either filter.
     *
     * A cheap test, so the filter manager is only asked for texts that need it.
     *
     * @param string $text Raw text.
     * @return bool
     */
    public static function has_markup(string $text): bool {
        return $text !== '' && (
            stripos($text, 'multilang') !== false
            || stripos($text, '{mlang') !== false
            || stripos($text, '</lang>') !== false
        );
    }

    /**
     * Whether a language filter is switched on for the site.
     *
     * The settings use it to say so where an admin writes multilingual text.
     *
     * @return bool
     */
    public static function language_filter_enabled(): bool {
        global $CFG;
        require_once($CFG->libdir . '/filterlib.php');
        foreach (self::LANGUAGE_FILTERS as $name) {
            if (filter_is_enabled($name)) {
                return true;
            }
        }
        return false;
    }

    /**
     * An instance of a language filter, or null when it is not installed.
     *
     * @param string $name Filter name without the prefix.
     * @param \context $context Context.
     * @param array $localconfig The filter's local configuration.
     * @return object|null
     */
    private static function make_filter(string $name, \context $context, array $localconfig): ?object {
        $class = '\\filter_' . $name . '\\text_filter';
        if (class_exists($class)) {
            return new $class($context, $localconfig);
        }
        // Older filter_multilang2 releases still ship the legacy class.
        global $CFG;
        $legacy = $CFG->dirroot . '/filter/' . $name . '/filter.php';
        if (is_readable($legacy)) {
            require_once($legacy);
            $class = 'filter_' . $name;
            if (class_exists($class)) {
                return new $class($context, $localconfig);
            }
        }
        return null;
    }
}

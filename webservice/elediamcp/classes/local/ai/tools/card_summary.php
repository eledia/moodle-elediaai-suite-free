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

namespace webservice_elediamcp\local\ai\tools;

/**
 * The text a confirmation card shows (`preview.card_summary`).
 *
 * Every tool with a confirm step hands out one or two plain sentences in the language of the
 * person: no tool names, no instructions for the model. The English `summary` stays what it
 * is, a note for the model. The sentences come from the plugin's language files.
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class card_summary {
    /**
     * The sentence for a card, in the language of the person.
     *
     * @param string $key String key of this plugin (cardsummary_*).
     * @param mixed $a Placeholders.
     * @return string
     */
    public static function text(string $key, mixed $a = null): string {
        return quiz_attempt_helper::person_string($key, 'webservice_elediamcp', self::plain($a));
    }

    /**
     * The placeholders of a card as plain text (#201).
     *
     * The tools build names with format_string(), which returns HTML-escaped text: a course "A & B" arrives
     * as "A &amp; B". The card is plain text that the browser escapes once more, so the entities would show.
     * Only the card gets this; the fields for the model keep the escaped values.
     *
     * @param mixed $a Placeholders (object, array or string).
     * @return mixed The same shape with the entities of string values decoded.
     */
    private static function plain(mixed $a): mixed {
        if (is_string($a)) {
            return html_entity_decode($a, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        if (is_array($a)) {
            return array_map(static fn($value) => self::plain($value), $a);
        }
        if (is_object($a)) {
            $copy = clone $a;
            foreach (get_object_vars($copy) as $name => $value) {
                $copy->{$name} = self::plain($value);
            }
            return $copy;
        }
        return $a;
    }

    /**
     * The `preview` of a tool without other preview facts: only the card text.
     *
     * @param string $key String key of this plugin.
     * @param mixed $a Placeholders.
     * @return array{card_summary: string}
     */
    public static function only(string $key, mixed $a = null): array {
        return ['card_summary' => self::text($key, $a)];
    }

    /**
     * The name of an activity type in the language of the person.
     *
     * @param string $type Module name (page, url, assign, ...).
     * @return string
     */
    public static function module_name(string $type): string {
        $manager = get_string_manager();
        if (!$manager->string_exists('modulename', 'mod_' . $type)) {
            return $type;
        }
        return quiz_attempt_helper::person_string('modulename', 'mod_' . $type);
    }

    /**
     * The name of an H5P content type in the language of the person.
     *
     * @param string $type Type of the H5P author (dialogcards, blanks, ...).
     * @return string
     */
    public static function h5p_type_name(string $type): string {
        if (!get_string_manager()->string_exists('type_' . $type, 'local_elediaai_h5pauthor')) {
            return $type;
        }
        return quiz_attempt_helper::person_string('type_' . $type, 'local_elediaai_h5pauthor');
    }

    /**
     * The schema of the field, for the `preview` of a tool's output schema.
     *
     * @return array<string, mixed>
     */
    public static function schema(): array {
        return [
            'type' => 'string',
            'description' => 'Plain text for the confirmation card, in the language of the user. '
                . 'Show this and not the English summary.',
        ];
    }
}

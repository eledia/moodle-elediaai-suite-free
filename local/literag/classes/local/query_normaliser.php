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

namespace local_literag\local;

/**
 * Normalises a natural-language question into keyword search terms.
 *
 * Lower-cases, strips punctuation, drops a small English/German stopword set and
 * de-duplicates. Used to build full-text and LIKE queries.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class query_normaliser {
    /** @var string[] Common English + German stopwords. */
    private const STOPWORDS = [
        // English.
        'the', 'a', 'an', 'and', 'or', 'but', 'of', 'to', 'in', 'on', 'for', 'is',
        'are', 'was', 'were', 'be', 'been', 'it', 'this', 'that', 'these', 'those',
        'with', 'as', 'at', 'by', 'from', 'about', 'into', 'how', 'what', 'when',
        'where', 'who', 'which', 'why', 'do', 'does', 'did', 'i', 'you', 'my', 'me',
        'we', 'us', 'can', 'should', 'would', 'could', 'will', 'shall',
        // German.
        'der', 'die', 'das', 'und', 'oder', 'ein', 'eine', 'einen', 'ist', 'sind',
        'war', 'waren', 'mit', 'von', 'zu', 'im', 'in', 'auf', 'für', 'wie', 'was',
        'wann', 'wo', 'wer', 'welche', 'warum', 'ich', 'du', 'mein', 'wir', 'kann',
        'soll', 'wird', 'den', 'dem', 'des',
    ];

    /**
     * Extract unique search terms (length >= 2) from a question.
     *
     * @param string $question Raw user question.
     * @return string[] Normalised, de-duplicated terms (possibly empty).
     */
    public static function terms(string $question): array {
        $clean = \core_text::strtolower($question);
        $clean = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $clean);
        $parts = preg_split('/\s+/u', (string) $clean, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $terms = [];
        foreach ($parts as $word) {
            if (\core_text::strlen($word) < 2) {
                continue;
            }
            if (in_array($word, self::STOPWORDS, true)) {
                continue;
            }
            $terms[$word] = true;
        }
        return array_keys($terms);
    }

    /**
     * A cleaned query string suitable for plainto_tsquery / MATCH AGAINST.
     *
     * Falls back to the trimmed raw question when stopword removal empties it.
     *
     * @param string $question Raw user question.
     * @return string
     */
    public static function clean_string(string $question): string {
        $terms = self::terms($question);
        if (!empty($terms)) {
            return implode(' ', $terms);
        }
        $clean = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', \core_text::strtolower($question));
        return trim((string) preg_replace('/\s+/u', ' ', (string) $clean));
    }
}

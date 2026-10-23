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

namespace webservice_elediamcp\local\ai;

use core_text;
use stdClass;
use webservice_elediamcp\local\security;

/**
 * Keeps the answers of paged list tools within a character budget.
 *
 * Some MCP clients turn a tool result into indented JSON text and cut it
 * off at a fixed number of characters. Because such clients usually sort the
 * keys alphabetically, the list comes before has_more, total and summary, so
 * a cut removes exactly the fields the model needs to page on. The list tools
 * therefore choose their page size so that the whole answer, measured in that
 * text form, stays below the configured limit (setting response_max_chars).
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class response_budget {
    /** @var int Default character limit. */
    public const DEFAULT_MAX_CHARS = 12000;

    /** @var int Lowest accepted character limit. */
    public const MIN_MAX_CHARS = 2000;

    /** @var int Highest accepted character limit. */
    public const MAX_MAX_CHARS = 100000;

    /** @var int Share of the limit a page may use, in percent (safety margin of 5 percent). */
    public const USABLE_PERCENT = 95;

    /** @var string Sentence added to the summary when more entries follow. */
    public const MORE_SENTENCE = 'More entries follow: call the tool again with offset set to next_offset to get them.';

    /** @var string Sentence for tool descriptions. */
    public const DESCRIPTION_SENTENCE =
        'If has_more is true, call again with offset set to next_offset to get the remaining entries.';

    /**
     * Configured character limit for one tool answer.
     *
     * A missing or non-numeric setting gives the default; numeric values
     * outside the accepted range are clamped to it.
     *
     * @return int
     */
    public static function max_chars(): int {
        $value = security::get_config('response_max_chars', self::DEFAULT_MAX_CHARS);
        if (!is_numeric($value)) {
            return self::DEFAULT_MAX_CHARS;
        }
        return max(self::MIN_MAX_CHARS, min(self::MAX_MAX_CHARS, (int) $value));
    }

    /**
     * Characters a page may use: the limit minus the safety margin.
     *
     * @param int $maxchars Character limit.
     * @return int
     */
    public static function usable_chars(int $maxchars): int {
        return intdiv($maxchars * self::USABLE_PERCENT, 100);
    }

    /**
     * Length of a payload as a client sees it after indenting it as JSON.
     *
     * Reproduces Python's json.dumps(value, ensure_ascii=False, indent=2,
     * sort_keys=True) applied to the decoded structured content: two spaces
     * per level, keys sorted by code point, ", " replaced by ",\n", ": "
     * between key and value, Unicode and "/" not escaped, null/true/false and
     * numbers as Python writes them. The payload first goes through JSON with
     * the same flags as the server (server::safe_json_encode()), so integral
     * floats become integers as on the client. Counts characters (code
     * points), not bytes.
     *
     * By default the payload is measured inside the {"result": ...} envelope
     * of protocol version 2025-03-26 (server::emit_tool_result()). The server
     * also uses that version when a client sends no MCP-Protocol-Version
     * header, and the tool does not know which version applies. The envelope
     * indents every line by two more characters, so measuring with it is the
     * conservative choice: clients without envelope get slightly smaller pages.
     *
     * Invalid UTF-8 makes the server answer with an encoding error instead of
     * the payload; measure() then returns PHP_INT_MAX, so such an entry never
     * counts as fitting and ends up alone on its page.
     *
     * @param array $payload Tool result.
     * @param bool $envelope Measure inside the {"result": ...} envelope.
     * @return int
     */
    public static function measure(array $payload, bool $envelope = true): int {
        $wire = json_encode($envelope ? ['result' => $payload] : $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($wire === false) {
            return PHP_INT_MAX;
        }
        $decoded = json_decode($wire, false);
        return core_text::strlen(self::dump($decoded, 0));
    }

    /**
     * Choose the largest page that keeps the whole answer within the limit.
     *
     * $candidates are the entries from the requested offset on, already
     * sorted and at most as many as the caller's limit. $build receives the
     * first n of them and returns the complete answer for that page: the
     * frame (all fields except the list, including has_more, next_offset and
     * summary, which depend on the page). The answer for the largest n whose
     * measured length stays within {@see usable_chars()} is returned.
     *
     * At least one entry is always returned when there is one: if a single
     * entry alone is already longer than the limit, the answer with that one
     * entry is returned anyway, so that paging always moves forward and no
     * entry is ever skipped or cut. A client that truncates will then cut that
     * one answer.
     *
     * @param array $candidates Entries from the offset on (list).
     * @param callable $build function(array $page): array, builds the full answer.
     * @param int|null $maxchars Character limit, null for the configured one.
     * @return array The answer for the chosen page.
     */
    public static function fit_page(array $candidates, callable $build, ?int $maxchars = null): array {
        $candidates = array_values($candidates);
        $budget = self::usable_chars($maxchars ?? self::max_chars());
        $count = count($candidates);

        $full = $build($candidates);
        if ($count <= 1 || self::measure($full) <= $budget) {
            return $full;
        }

        // The answer grows with every entry as long as further entries follow
        // (same frame), so a binary search over 1 .. count - 1 finds the largest page.
        $low = 1;
        $high = $count - 1;
        $best = null;
        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);
            $answer = $build(array_slice($candidates, 0, $mid));
            if (self::measure($answer) <= $budget) {
                $best = $answer;
                $low = $mid + 1;
            } else {
                $high = $mid - 1;
            }
        }
        return $best ?? $build(array_slice($candidates, 0, 1));
    }

    /**
     * Paging fields for a page of a list whose total is known.
     *
     * @param int $offset Offset of the first entry of the page.
     * @param int $returned Entries in the page.
     * @param int $total All matching entries.
     * @return array{has_more: bool, next_offset: ?int}
     */
    public static function paging(int $offset, int $returned, int $total): array {
        $next = $offset + $returned;
        return [
            'has_more' => $next < $total,
            'next_offset' => $next < $total ? $next : null,
        ];
    }

    /**
     * Append the paging sentence to a summary when more entries follow.
     *
     * @param string $summary Summary of the tool.
     * @param bool $hasmore Whether more entries follow.
     * @return string
     */
    public static function summary(string $summary, bool $hasmore): string {
        return $hasmore ? rtrim($summary) . ' ' . self::MORE_SENTENCE : $summary;
    }

    /**
     * Write a decoded JSON value the way Python's json.dumps(indent=2, sort_keys=True) does.
     *
     * @param mixed $value Value from json_decode($json, false).
     * @param int $level Nesting level.
     * @return string
     */
    private static function dump(mixed $value, int $level): string {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            return self::python_float($value);
        }
        if (is_string($value)) {
            return self::string_literal($value);
        }
        $inner = str_repeat('  ', $level + 1);
        $outer = str_repeat('  ', $level);
        if (is_array($value)) {
            if ($value === []) {
                return '[]';
            }
            $items = [];
            foreach ($value as $item) {
                $items[] = $inner . self::dump($item, $level + 1);
            }
            return "[\n" . implode(",\n", $items) . "\n" . $outer . ']';
        }
        $properties = $value instanceof stdClass ? get_object_vars($value) : (array) $value;
        if ($properties === []) {
            return '{}';
        }
        ksort($properties, SORT_STRING);
        $items = [];
        foreach ($properties as $key => $item) {
            $items[] = $inner . self::string_literal((string) $key) . ': ' . self::dump($item, $level + 1);
        }
        return "{\n" . implode(",\n", $items) . "\n" . $outer . '}';
    }

    /**
     * JSON string literal as Python writes it with ensure_ascii=False.
     *
     * The value comes from json_decode() and is therefore valid UTF-8.
     *
     * @param string $value String.
     * @return string
     */
    private static function string_literal(string $value): string {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS;
        return (string) json_encode($value, $flags);
    }

    /**
     * Python's repr() of a float: shortest digits, exponent below 1e-4 and from 1e16 on.
     *
     * @param float $value Finite float.
     * @return string
     */
    private static function python_float(float $value): string {
        if (!is_finite($value)) {
            return is_nan($value) ? 'NaN' : ($value > 0 ? 'Infinity' : '-Infinity');
        }
        // Shortest round-trip digits (serialize_precision = -1, the PHP default).
        $repr = (string) json_encode($value, JSON_PRESERVE_ZERO_FRACTION);
        if (!preg_match('/^(-?)(\d+)(?:\.(\d+))?(?:[eE]([+-]?\d+))?$/', $repr, $m)) {
            return $repr;
        }
        $sign = $m[1];
        $intpart = $m[2];
        $fracpart = $m[3] ?? '';
        $exp = isset($m[4]) ? (int) $m[4] : 0;
        $digits = ltrim($intpart . $fracpart, '0');
        if ($digits === '') {
            return $sign . '0.0';
        }
        // Decimal exponent of the first significant digit.
        $leadingzeros = strlen($intpart . $fracpart) - strlen(ltrim($intpart . $fracpart, '0'));
        $point = strlen($intpart) + $exp - $leadingzeros;
        $digits = rtrim($digits, '0');
        $sciexp = $point - 1;
        if ($sciexp < -4 || $sciexp >= 16) {
            $mantissa = $digits[0] . (strlen($digits) > 1 ? '.' . substr($digits, 1) : '');
            return sprintf('%s%se%s%02d', $sign, $mantissa, $sciexp < 0 ? '-' : '+', abs($sciexp));
        }
        if ($point <= 0) {
            return $sign . '0.' . str_repeat('0', -$point) . $digits;
        }
        if ($point >= strlen($digits)) {
            return $sign . $digits . str_repeat('0', $point - strlen($digits)) . '.0';
        }
        return $sign . substr($digits, 0, $point) . '.' . substr($digits, $point);
    }
}

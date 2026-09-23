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
 * Shared "stale / regenerate" fingerprint helper.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Deterministic fingerprint of the inputs a generated artefact depends on.
 *
 * The LernHive suite reuses one "stale" pattern in several places: a piece of
 * AI output (a feedback draft, a chat thread, ...) is generated from one or
 * more input parts (a teacher prompt, a system prompt, a model id, ...). When
 * any of those inputs later change, the stored output is "stale" and the user
 * should be offered a regenerate / reset action. This helper centralises the
 * hashing and the comparison so every consumer uses the exact same semantics.
 *
 * Normalisation contract (stable — stored hashes must keep matching):
 *  - Each part is cast to string and trimmed (leading/trailing whitespace
 *    removed) before hashing.
 *  - A SINGLE part is hashed as sha1(trim($value)) directly, with NO array or
 *    JSON wrapping. This guarantees byte-for-byte compatibility with the
 *    historical mod_aifeedback fingerprint sha1(trim((string) $prompt)), so
 *    existing stored prompthash values never suddenly read as stale.
 *  - MULTIPLE parts are hashed as sha1 over the trimmed parts joined by a
 *    record-separator (\x1e) control character. The separator cannot occur in
 *    normal text, so different part boundaries can never collide
 *    (e.g. ['ab','c'] and ['a','bc'] hash differently).
 *  - Array keys are ignored; only the ORDER of the values matters. Callers
 *    must therefore always pass parts in a stable order.
 */
final class stale_marker {
    /** @var string Record separator joining multiple parts (never appears in text). */
    private const SEPARATOR = "\x1e";

    /**
     * Static-only helper.
     */
    private function __construct() {
    }

    /**
     * Build a deterministic fingerprint over the given input parts.
     *
     * For a single element the result equals sha1(trim($value)) so it stays
     * compatible with pre-existing single-input hashes (see class contract).
     *
     * @param array $parts Ordered list of inputs (strings or string-castable).
     *                     Keys are ignored; order is significant.
     * @return string 40-char lowercase sha1 hex digest. Empty input hashes the
     *                empty string for a stable, comparable value.
     */
    public static function hash(array $parts): string {
        $normalised = array_map(
            static fn($part): string => trim((string) $part),
            array_values($parts)
        );

        if (count($normalised) === 1) {
            return sha1($normalised[0]);
        }

        return sha1(implode(self::SEPARATOR, $normalised));
    }

    /**
     * Decide whether a stored fingerprint is out of date.
     *
     * Backwards compatible by design: a missing/empty stored hash is treated as
     * "not stale" so artefacts created before fingerprinting was introduced are
     * never flagged.
     *
     * @param string|null $storedhash Previously persisted hash, or null/'' if none.
     * @param array $currentparts Current input parts (same order as when stored).
     * @return bool True only when a stored hash exists and no longer matches.
     */
    public static function is_stale(?string $storedhash, array $currentparts): bool {
        $storedhash = (string) $storedhash;
        if ($storedhash === '') {
            return false;
        }

        return $storedhash !== self::hash($currentparts);
    }
}

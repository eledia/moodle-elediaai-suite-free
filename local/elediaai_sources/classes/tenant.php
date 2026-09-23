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
 * The site's tenant identity for the RAG service.
 *
 * The tenant is DERIVED from the site's wwwroot, never configured: the same
 * canonical identity is independently verifiable on the retrieval path (the
 * RAG server validates the chat token against `system_url` and receives
 * `site.url` from `moodle_verify_user_context`). Deriving instead of
 * configuring removes the config-drift / tenant-collision failure class of a
 * free-text tenant string. See API_SPECIFICATION.md (v1.2).
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tenant {
    /**
     * The canonical tenant id of this site.
     *
     * Canonical form: lowercase host, plus the path when Moodle lives in a
     * subdirectory, joined and reduced to the `[a-z0-9._-]` alphabet (so it can
     * never contain the `:` source_id separator). Examples:
     * `https://moodle.uni-x.de` → `moodle.uni-x.de`;
     * `https://Example.com/Lms/` → `example.com-lms`.
     *
     * @return string The tenant id (never empty).
     */
    public static function id(): string {
        global $CFG;
        return self::from_url((string) $CFG->wwwroot);
    }

    /**
     * Canonicalise any site URL into a tenant id.
     *
     * The RAG server applies the same canonicalisation to the verified
     * `site.url` at query time, so both paths resolve to the same key.
     *
     * @param string $url The site URL (wwwroot).
     * @return string The canonical tenant id (never empty).
     */
    public static function from_url(string $url): string {
        $parts = parse_url(trim($url));
        $host = \core_text::strtolower(trim((string) ($parts['host'] ?? '')));
        $path = trim((string) ($parts['path'] ?? ''), '/');

        // Tenant identity is host-anchored: no host, no derivable tenant.
        if ($host === '') {
            return 'default';
        }

        $raw = $host . ($path !== '' ? '-' . $path : '');
        $clean = preg_replace('/[^a-z0-9._-]+/', '-', \core_text::strtolower($raw));
        $clean = trim((string) $clean, '-.');

        return $clean !== '' ? $clean : 'default';
    }
}

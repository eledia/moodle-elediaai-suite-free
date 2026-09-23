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
 * Derives the site's canonical tenant id from its wwwroot.
 *
 * This MUST match local_elediaai_sources's canonicalisation byte-for-byte so the
 * tenant stored on the write path (ingestion) equals the tenant resolved on the
 * read path (retrieval). See local/elediaai_sources/docs/api-specification.md v1.2.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tenant {
    /**
     * The canonical tenant id of this site.
     *
     * @return string Never empty.
     */
    public static function id(): string {
        global $CFG;
        return self::from_url((string) $CFG->wwwroot);
    }

    /**
     * Canonicalise a site URL into a tenant id.
     *
     * Lowercase host, plus the path when Moodle lives in a subdirectory, joined
     * with '-' and reduced to the [a-z0-9._-] alphabet (so it can never contain
     * the ':' source_id separator).
     *
     * @param string $url The site URL (wwwroot).
     * @return string The canonical tenant id (never empty).
     */
    public static function from_url(string $url): string {
        $parts = parse_url(trim($url));
        $host = \core_text::strtolower(trim((string) ($parts['host'] ?? '')));
        $path = trim((string) ($parts['path'] ?? ''), '/');

        if ($host === '') {
            return 'default';
        }

        $raw = $host . ($path !== '' ? '-' . $path : '');
        $clean = preg_replace('/[^a-z0-9._-]+/', '-', \core_text::strtolower($raw));
        $clean = trim((string) $clean, '-.');

        return $clean !== '' ? $clean : 'default';
    }
}

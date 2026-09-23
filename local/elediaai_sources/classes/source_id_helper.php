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
 * Helper class for building deterministic source IDs.
 *
 * Source IDs follow the format: {tenant}:course{courseid}:cmid{cmid}
 * They are stable and deterministic so that re-indexing replaces existing documents.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class source_id_helper {
    /**
     * Build a source ID from a course module info object.
     *
     * @param \cm_info $cm The course module info.
     * @return string The deterministic source ID.
     */
    public static function build(\cm_info $cm): string {
        return self::build_from_ids((int) $cm->course, (int) $cm->id);
    }

    /**
     * Build a source ID from raw course and cmid values.
     *
     * @param int $courseid The course ID.
     * @param int $cmid The course module ID.
     * @return string The deterministic source ID.
     */
    public static function build_from_ids(int $courseid, int $cmid): string {
        $tenant = tenant::id();
        return "{$tenant}:course{$courseid}:cmid{$cmid}";
    }

    /**
     * Build a sub-document source ID within a module.
     *
     * Appends a `:`-separated, sanitised suffix to the module-level id so the
     * module id remains a clean prefix of all its sub-documents (required for
     * prefix-scoped deletes). See API_SPECIFICATION.md.
     *
     * @param \cm_info $cm The course module info.
     * @param string $suffix An identifier for the sub-document (e.g. 'file3').
     * @return string The sub-document source ID.
     */
    public static function build_sub(\cm_info $cm, string $suffix): string {
        return self::build($cm) . ':' . self::sanitise_suffix($suffix);
    }

    /**
     * Normalise a sub-document suffix to a safe, separator-free token.
     *
     * Lower-cases and replaces anything outside [a-z0-9_-] (notably the `:`
     * separator) so the suffix can never break the prefix boundary.
     *
     * @param string $suffix The raw suffix.
     * @return string The sanitised suffix (never empty).
     */
    public static function sanitise_suffix(string $suffix): string {
        $clean = preg_replace('/[^a-z0-9_-]+/', '-', \core_text::strtolower(trim($suffix)));
        $clean = trim((string) $clean, '-');
        return $clean !== '' ? $clean : 'x';
    }
}

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
 * Interface for content extractors.
 *
 * Each subplugin (aisourcesextractor_*) must implement this interface
 * to provide content extraction for a specific Moodle activity module.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface content_extractor {
    /**
     * Check whether this extractor supports the given course module.
     *
     * @param \cm_info $cm The course module info object.
     * @return bool True if this extractor can handle the module type.
     */
    public function supports(\cm_info $cm): bool;

    /**
     * Extract content from the given course module.
     *
     * Instead of null, an extractor may return a **skip notice**:
     * `['skipped' => true, 'skipreason' => <translated reason>, 'title' => ...,
     * 'content_type' => ...]` with no content. Null says "nothing here" and
     * reads, in a report, exactly like a file that was quietly dropped; a
     * notice says which file was left out and why. Use it whenever something
     * existed but could not be used.
     *
     * @param \cm_info $cm The course module info object.
     * @return array|null Associative array with keys 'content', 'content_type', 'title',
     *                    a skip notice as described above, or null if there is
     *                    nothing to extract at all.
     */
    public function extract(\cm_info $cm): ?array;
}

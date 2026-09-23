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
 * An extractor that can say whether an activity's media carries captions.
 *
 * Optional on purpose. Most activity types hold no audio or video, and those
 * that do cannot all answer the question equally well — an extractor that
 * cannot must not be forced to invent an answer. The dry run asks, and stays
 * silent where nothing answers.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface accessibility_reporter {
    /**
     * Judge the caption coverage of this activity's media.
     *
     * @param \cm_info $cm The activity.
     * @return array|null The report as returned by {@see media_accessibility::scan()},
     *                    or null when this activity carries nothing to judge.
     */
    public function accessibility_report(\cm_info $cm): ?array;
}

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

namespace local_elediaai_sources\privacy;

use core_privacy\local\metadata\collection;

/**
 * Privacy provider for local_elediaai_sources.
 *
 * The plugin sends course content and course/module metadata to the configured
 * external AI Sources service, and records which user last changed an
 * activity's ingestion selection. Both are declared here for Moodle's privacy
 * subsystem.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\provider {
    /**
     * Describe stored data and data sent to external services.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_elediaai_sources_cm', [
            'usermodified' => 'privacy:metadata:cm:usermodified',
            'included' => 'privacy:metadata:cm:included',
            'timemodified' => 'privacy:metadata:cm:timemodified',
        ], 'privacy:metadata:cm');

        $collection->add_external_location_link('rag_service', [
            'site_url' => 'privacy:metadata:rag_service:site_url',
            'course_id' => 'privacy:metadata:rag_service:course_id',
            'cmid' => 'privacy:metadata:rag_service:cmid',
            'module_url' => 'privacy:metadata:rag_service:module_url',
            'content' => 'privacy:metadata:rag_service:content',
        ], 'privacy:metadata:rag_service');

        return $collection;
    }
}

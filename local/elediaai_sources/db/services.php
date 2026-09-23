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
 * External function declarations for the AI Sources plugin.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_elediaai_sources_set_activity_selection' => [
        'classname' => \local_elediaai_sources\external\set_activity_selection::class,
        'description' => 'Store a per-activity ingestion decision.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/elediaai_sources:selectactivities',
    ],
    'local_elediaai_sources_set_activity_selection_bulk' => [
        'classname' => \local_elediaai_sources\external\set_activity_selection_bulk::class,
        'description' => 'Store one ingestion decision for several activities of a course.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/elediaai_sources:selectactivities',
    ],
];

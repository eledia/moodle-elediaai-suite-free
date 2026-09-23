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
 * Plugin version and other metadata.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2026092300;
$plugin->requires  = 2024100700;
$plugin->supported = [405, 502];
$plugin->component = 'local_elediaai_chatengine';
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.0';
// The engine books every turn against the shared token quota and writes the
// AI audit trail, both of which live in the core plugin. Placements depend on
// the engine; the engine never depends on a placement.
$plugin->dependencies = [
    'local_elediaai_core' => 2026082000,
];

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
 * eLeDia.ai Tutor block version information.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version = 2026092301;
$plugin->requires = 2024100700;
$plugin->supported = [405, 502];
$plugin->component = 'block_elediaai_tutor';
$plugin->maturity = MATURITY_STABLE;
$plugin->release = '1.0.0';
$plugin->dependencies = [
    // The block always talks to the backend through a user-scoped Moodle MCP
    // token, on grounded and model-only turns alike, so the connector is
    // required rather than optional.
    'webservice_elediamcp' => 2026080600,
    // The block is a placement on the shared chat engine: it owns where the
    // chat appears, who may use it and the tutor's voice; the engine owns the
    // conversation, the backend and the safety framing.
    'local_elediaai_chatengine' => 2026083101,
];
// The local_elediaai_core companion stays optional and is guarded at runtime:
// without it a turn is dispatched unmetered.

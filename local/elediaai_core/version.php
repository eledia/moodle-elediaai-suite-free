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
 * Plugin version metadata for local_elediaai_core.
 *
 * Umbrella plugin that consolidates AI integrations LernHive ships out of
 * the box. Forked features from upstream community plugins are imported
 * here piece by piece, then re-licensed and re-namespaced under the
 * LernHive shell so they share UI, capabilities and config with the rest
 * of the suite.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_elediaai_core';
$plugin->version   = 2026092300;
$plugin->release   = '1.0.0';
$plugin->requires  = 2024100700; // Moodle 4.5+.
$plugin->maturity  = MATURITY_STABLE;

// The AI Suite Core has no plugin dependencies. Its complete runtime UI is
// implemented in this component.
$plugin->dependencies = [];

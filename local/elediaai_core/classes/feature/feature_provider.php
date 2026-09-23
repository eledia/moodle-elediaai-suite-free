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
 * Feature-provider contract.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core\feature;

defined('MOODLE_INTERNAL') || die();

/**
 * Plugins that want to appear in the LernHive AI dashboard implement
 * this interface in a class named `<frankenstyle>\elediaai_core\feature_provider`.
 *
 * The registry discovers providers via `core_component::get_plugin_list_with_class()`,
 * so no manual registration code is required — installing the plugin
 * is enough.
 */
interface feature_provider {
    /**
     * Return one or more descriptors describing the AI feature(s) this
     * plugin exposes.
     *
     * @return descriptor[]
     */
    public static function get_descriptors(): array;
}

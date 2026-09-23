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
 * Premium policy-provider contract.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core\feature;

defined('MOODLE_INTERNAL') || die();

/**
 * Premium add-ons implement this interface in
 * `<frankenstyle>\elediaai_core_policy\policy_provider`.
 */
interface policy_provider {
    /**
     * Whether the current site may use the given premium feature.
     *
     * @param descriptor $descriptor
     * @return bool
     */
    public static function is_feature_allowed(descriptor $descriptor): bool;
}

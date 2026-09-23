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
 * Admin settings for local_aitransparency.
 *
 * @package    local_aitransparency
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_aitransparency',
        get_string('pluginname', 'local_aitransparency')
    );
    $ADMIN->add('localplugins', $settings);

    // Retention window (days) after which the anonymise_records task removes the
    // userid link from a provenance record. 0 keeps the link indefinitely.
    $settings->add(new admin_setting_configtext(
        'local_aitransparency/retentiondays',
        get_string('setting_retentiondays', 'local_aitransparency'),
        get_string('setting_retentiondays_desc', 'local_aitransparency'),
        365,
        PARAM_INT
    ));
}

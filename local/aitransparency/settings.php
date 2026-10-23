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

    // How the visible notice looks (#42). The machine-readable marking is the
    // same in every style; this only decides what a reader sees.
    $full = get_string('noticestyle_full', 'local_aitransparency');
    $compact = get_string('noticestyle_compact', 'local_aitransparency');
    $settings->add(new admin_setting_configselect(
        'local_aitransparency/noticestyle_chat',
        get_string('setting_noticestyle_chat', 'local_aitransparency'),
        get_string('setting_noticestyle_chat_desc', 'local_aitransparency'),
        \local_aitransparency\marker::STYLE_FULL,
        [
            \local_aitransparency\marker::STYLE_FULL => $full,
            \local_aitransparency\marker::STYLE_COMPACT => $compact,
            \local_aitransparency\marker::STYLE_ONCE => get_string('noticestyle_once', 'local_aitransparency'),
        ]
    ));
    $settings->add(new admin_setting_configselect(
        'local_aitransparency/noticestyle',
        get_string('setting_noticestyle', 'local_aitransparency'),
        get_string('setting_noticestyle_desc', 'local_aitransparency'),
        \local_aitransparency\marker::STYLE_FULL,
        [
            \local_aitransparency\marker::STYLE_FULL => $full,
            \local_aitransparency\marker::STYLE_COMPACT => $compact,
        ]
    ));
}

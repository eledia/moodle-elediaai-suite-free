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
 * Uninstall cleanup for the eLeDia.ai Tutor block.
 *
 * @package     block_elediaai_tutor
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Remove data that lives outside this plugin's own tables.
 *
 * Moodle core removes the plugin tables, config, capabilities, tasks and
 * service declarations automatically. The long-term-memory user preference
 * and the lazily-created webservice-only maintenance user need explicit
 * cleanup because they are stored in core tables.
 *
 * @return bool
 */
function xmldb_block_elediaai_tutor_uninstall(): bool {
    global $CFG, $DB;

    require_once(__DIR__ . '/../classes/local/ltm.php');
    require_once(__DIR__ . '/../classes/local/service_user.php');

    $DB->delete_records('user_preferences', [
        'name' => \block_elediaai_tutor\local\ltm::PREF,
    ]);

    $serviceuser = $DB->get_record('user', [
        'username' => \block_elediaai_tutor\local\service_user::USERNAME,
        'mnethostid' => $CFG->mnet_localhost_id,
        'deleted' => 0,
    ]);
    if ($serviceuser) {
        require_once($CFG->dirroot . '/user/lib.php');
        delete_user($serviceuser);
    }

    return true;
}

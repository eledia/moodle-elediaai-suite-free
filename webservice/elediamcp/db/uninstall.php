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
 * Uninstall hook for webservice_elediamcp.
 *
 * @package     webservice_elediamcp
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Remove the default MCP external service and its tokens.
 *
 * The one-click service carries no component, so that upgrades leave it alone
 * (see token_manager::ensure_default_service_configured()). The same choice
 * means Moodle does not remove it on uninstall either, so this hook does.
 * Other services an administrator added to the MCP list are left untouched.
 *
 * @return bool
 */
function xmldb_webservice_elediamcp_uninstall(): bool {
    global $CFG, $DB;

    require_once($CFG->dirroot . '/webservice/lib.php');

    $serviceid = $DB->get_field('external_services', 'id', [
        'shortname' => \webservice_elediamcp\local\token_manager::DEFAULT_SERVICE_SHORTNAME,
    ]);
    if ($serviceid) {
        (new webservice())->delete_service((int) $serviceid);
    }

    return true;
}

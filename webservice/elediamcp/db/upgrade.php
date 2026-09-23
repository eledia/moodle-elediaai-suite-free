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
 * Upgrade steps for the MCP web service plugin.
 *
 * @package     webservice_elediamcp
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Run the MCP plugin upgrade steps.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool
 */
function xmldb_webservice_elediamcp_upgrade($oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026060904) {
        // Define table webservice_elediamcp_token to be created.
        $table = new xmldb_table('webservice_elediamcp_token');

        // Adding fields to table webservice_elediamcp_token.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('externaltokenid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('tokenhash', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('externalserviceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('creatorid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('component', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $table->add_field('validuntil', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('lastaccess', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('revoked', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timerevoked', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('revokedby', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys to table webservice_elediamcp_token.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('externalserviceid', XMLDB_KEY_FOREIGN, ['externalserviceid'], 'external_services', ['id']);
        $table->add_key('creatorid', XMLDB_KEY_FOREIGN, ['creatorid'], 'user', ['id']);

        // Adding indexes to table webservice_elediamcp_token.
        $table->add_index('tokenhash', XMLDB_INDEX_NOTUNIQUE, ['tokenhash']);
        $table->add_index('externaltokenid', XMLDB_INDEX_NOTUNIQUE, ['externaltokenid']);
        $table->add_index('userid-revoked', XMLDB_INDEX_NOTUNIQUE, ['userid', 'revoked']);

        // Conditionally launch create table for webservice_elediamcp_token.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // MCP savepoint reached.
        upgrade_plugin_savepoint(true, 2026060904, 'webservice', 'elediamcp');
    }

    if ($oldversion < 2026080500) {
        // Define table webservice_elediamcp_oauth_client to be created.
        $table = new xmldb_table('webservice_elediamcp_oauth_client');

        // Adding fields to table webservice_elediamcp_oauth_client.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('clientid', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('clientname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('redirecturis', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('granttypes', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, 'authorization_code');
        $table->add_field('responsetypes', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, 'code');
        $table->add_field('tokenendpointauthmethod', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, 'none');
        $table->add_field('scope', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table webservice_elediamcp_oauth_client.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Adding indexes to table webservice_elediamcp_oauth_client.
        $table->add_index('clientid', XMLDB_INDEX_UNIQUE, ['clientid']);

        // Conditionally launch create table for webservice_elediamcp_oauth_client.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table webservice_elediamcp_oauth_code to be created.
        $table = new xmldb_table('webservice_elediamcp_oauth_code');

        // Adding fields to table webservice_elediamcp_oauth_code.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('codehash', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('clientid', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('redirecturi', XMLDB_TYPE_CHAR, '1333', null, XMLDB_NOTNULL, null, null);
        $table->add_field('codechallenge', XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL, null, null);
        $table->add_field('codechallengemethod', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'S256');
        $table->add_field('scope', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('expires', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table webservice_elediamcp_oauth_code.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        // Adding indexes to table webservice_elediamcp_oauth_code.
        $table->add_index('codehash', XMLDB_INDEX_UNIQUE, ['codehash']);
        $table->add_index('expires', XMLDB_INDEX_NOTUNIQUE, ['expires']);

        // Conditionally launch create table for webservice_elediamcp_oauth_code.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // MCP savepoint reached.
        upgrade_plugin_savepoint(true, 2026080500, 'webservice', 'elediamcp');
    }

    return true;
}

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
 * Upgrade steps for the AI chat engine.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Perform the upgrade.
 *
 * @param int $oldversion The currently installed version.
 * @return bool Always true.
 */
function xmldb_local_elediaai_chatengine_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026082702) {
        // block_elediaai_chat was removed rather than converted: its placement
        // is now the tutor block in its ungrounded configuration. The other
        // three chat plugins drop their own conversation tables in their own
        // upgrade steps, but a deleted plugin has no upgrade step left to run,
        // and Moodle cannot read a schema from a directory that is gone. The
        // successor therefore clears the remains, or they stay in the database
        // for good.
        foreach (['block_elediaai_chat_msg', 'block_elediaai_chat_thread'] as $tablename) {
            $table = new xmldb_table($tablename);
            if ($dbman->table_exists($table)) {
                $dbman->drop_table($table);
            }
        }
        // Its settings would otherwise linger in the config table, where an
        // administrator would keep finding a plugin that no longer exists.
        $DB->delete_records('config_plugins', ['plugin' => 'block_elediaai_chat']);

        upgrade_plugin_savepoint(true, 2026082702, 'local', 'elediaai_chatengine');
    }

    if ($oldversion < 2026082800) {
        // Named chat designs, so one look can be defined once and pointed at
        // from every chat surface. Nothing is applied by default: a site that
        // defines no design, and an instance that picks none, keep the built-in
        // look they had before this existed.
        $table = new xmldb_table('local_elediaai_chateng_design');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
            $table->add_field('shortname', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
            $table->add_field('description', XMLDB_TYPE_TEXT);
            $table->add_field('tokens', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
            $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('shortname', XMLDB_KEY_UNIQUE, ['shortname']);
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026082800, 'local', 'elediaai_chatengine');
    }

    if ($oldversion < 2026083102) {
        // Where an answer came from, kept with the turn. The citations were
        // already stored, but the origin was not, so a reloaded conversation
        // could not tell a grounded answer from an ungrounded one — and an
        // answer read out of Moodle through the connector carries no citations
        // at all, which made it indistinguishable from the model talking to
        // itself. Existing turns stay null: the origin they had was never
        // recorded and must not be guessed after the fact.
        $table = new xmldb_table('local_elediaai_chateng_msg');
        $field = new xmldb_field('origin', XMLDB_TYPE_CHAR, '16', null, null, null, null, 'sources');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026083102, 'local', 'elediaai_chatengine');
    }

    return true;
}

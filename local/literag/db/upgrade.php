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
 * Database upgrade steps.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the local_literag database.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool
 */
function xmldb_local_literag_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026061700) {
        // Per-message structured sources, so resumed conversations can carry the
        // same citation cards as live answers (no more inline footer hack).
        $table = new xmldb_table('local_literag_messages');
        $field = new xmldb_field('sourcesjson', XMLDB_TYPE_TEXT, null, null, null, null, null, 'content');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026061700, 'local', 'literag');
    }

    if ($oldversion < 2026061900) {
        // A previewed write action (e.g. a message send) awaiting the learner's
        // explicit confirmation on the next turn.
        $table = new xmldb_table('local_literag_conversations');
        $field = new xmldb_field('pendingaction', XMLDB_TYPE_TEXT, null, null, null, null, null, 'lastanswerstyle');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026061900, 'local', 'literag');
    }

    if ($oldversion < 2026061901) {
        // No schema changes. Keep the upgrade savepoint aligned with the release
        // version used for the review branch.
        upgrade_plugin_savepoint(true, 2026061901, 'local', 'literag');
    }

    return true;
}

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
 * Upgrade steps for local_aitransparency.
 *
 * @package    local_aitransparency
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade hook. New schema changes are appended as guarded savepoint blocks.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_aitransparency_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    // No upgrade steps yet: the initial schema is created from db/install.xml.
    // Later schema changes are appended here as guarded upgrade_plugin_savepoint
    // blocks in the same commit as the version bump (never edited in place).
    if ($oldversion < 2026091901) {
        // Der Nachweis zeigt jetzt auf den Turn, aus dem die Ausgabe kam.
        // registerid war dafuer gedacht und wurde nie gesetzt -- die Suite
        // schreibt inzwischen ihr eigenes Turn-Protokoll, und das hat eine Id,
        // die der Schreiber zurueckgeben kann.
        $table = new xmldb_table('local_aitransparency_rec');
        $field = new xmldb_field('turnid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'registerid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026091901, 'local', 'aitransparency');
    }

    return true;
}

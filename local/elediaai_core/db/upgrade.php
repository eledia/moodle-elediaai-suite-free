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
 * Upgrade steps for local_elediaai_core.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade hook.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_elediaai_core_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026070800) {
        $table = new xmldb_table('local_elediaai_core_usage');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('rolebucket', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'student');
            $table->add_field('windowtype', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'day');
            $table->add_field('windowstart', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('prompttokens', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('completiontokens', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('totaltokens', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('requestcount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('component', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('userwindow_uix', XMLDB_INDEX_UNIQUE, ['userid', 'rolebucket', 'windowtype', 'windowstart']);
            $table->add_index('windowstart_ix', XMLDB_INDEX_NOTUNIQUE, ['windowstart']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026070800, 'local', 'elediaai_core');
    }

    if ($oldversion < 2026070900) {
        $table = new xmldb_table('local_elediaai_core_usage');
        $field = new xmldb_field('component', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);

        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_default($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026070900, 'local', 'elediaai_core');
    }

    if ($oldversion < 2026072600) {
        // Version gate for the course-level Teacher Dashboard capability.
        // Moodle refreshes db/access.php capabilities during plugin upgrade.
        upgrade_plugin_savepoint(true, 2026072600, 'local', 'elediaai_core');
    }

    if ($oldversion < 2026080200) {
        // Hard credit reserve for in-flight requests: tokens reserved before
        // an external call are counted against the limit until the request is
        // committed (actual usage) or released (error path).
        $table = new xmldb_table('local_elediaai_core_usage');
        $field = new xmldb_field('reservedtokens', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026080200, 'local', 'elediaai_core');
    }

    if ($oldversion < 2026090800) {
        // Die Kachel des Lernpfads hiess 'lernhivepath'. LernHive ist nicht
        // Teil dieser Suite, also heisst sie jetzt 'learningpath' -- aber der
        // An/Aus-Schalter einer Website haengt an
        // feature_<id>_enabled. Ohne diesen Umzug faellt ein bewusst
        // abgeschaltetes Feature beim Update stillschweigend auf "an"
        // zurueck, und niemand sieht warum.
        $alt = get_config('local_elediaai_core', 'feature_lernhivepath_enabled');
        if ($alt !== false) {
            set_config('feature_learningpath_enabled', $alt, 'local_elediaai_core');
            unset_config('feature_lernhivepath_enabled', 'local_elediaai_core');
        }

        upgrade_plugin_savepoint(true, 2026090800, 'local', 'elediaai_core');
    }

    if ($oldversion < 2026090809) {
        // Der KI-Session-Komponist ist entfallen: sein einziger Einstieg war
        // ein Hook, den nur local_lernhive aufgerufen haette, und dieses
        // Plugin gibt es nicht. Der Schalter bleibt sonst als Karteileiche in
        // der Konfiguration stehen und behauptet eine Funktion, die keine
        // Zeile Code mehr hat.
        unset_config('enable_session_composer', 'local_elediaai_core');

        upgrade_plugin_savepoint(true, 2026090809, 'local', 'elediaai_core');
    }

    if ($oldversion < 2026091901) {
        // Der eigene Turn-Speicher der Suite. Bisher war das Audit eine Sicht
        // auf Moodles ai_action_register und erbte dessen Grenzen: keine
        // component-Spalte, also war "welches Feature wird benutzt" dort
        // unbeantwortbar, und alles ausserhalb von core_ai fehlte.
        $table = new xmldb_table('local_elediaai_core_turn');
        if (!$dbman->table_exists($table)) {
            $dbman->install_one_table_from_xmldb_file(
                __DIR__ . '/install.xml',
                'local_elediaai_core_turn'
            );
        }

        // Das Salz fuer den Pseudonym-Schluessel entsteht genau einmal, und es
        // entsteht hier: der Upgrade-Schritt laeuft einzeln, waehrend zwei
        // parallele Anfragen sich zwei verschiedene Salze geben koennten. Dann
        // haette eine Person zwei Schluessel, und niemand wuerde es je melden.
        \local_elediaai_core\local\pseudonym::ensure_salt();

        upgrade_plugin_savepoint(true, 2026091901, 'local', 'elediaai_core');
    }

    if ($oldversion < 2026091902) {
        // Schicht A: was die KI getan hat, in wessen Auftrag. Eine Projektion
        // der Werkzeug-Ereignisse, kein zweites Protokoll -- Moodles Log haelt
        // sie weiter, kodiert seine Nutzlast aber als JSON ODER
        // PHP-serialisiert, je nach Konfiguration, und ist damit nicht
        // auswertbar.
        $table = new xmldb_table('local_elediaai_core_action');
        if (!$dbman->table_exists($table)) {
            $dbman->install_one_table_from_xmldb_file(
                __DIR__ . '/install.xml',
                'local_elediaai_core_action'
            );
        }

        upgrade_plugin_savepoint(true, 2026091902, 'local', 'elediaai_core');
    }

    if ($oldversion < 2026091903) {
        // Nicht jede Antwort bringt ihren Verbrauch mit. Der RAG-Agent meldet
        // kein usage-Feld, und dann bucht der Engpass eine Schaetzung auf das
        // Guthaben -- ins Protokoll schrieb er bis hierher die rohe Null. Der
        // Bericht sagte damit "hat nichts gekostet" ueber eine Anfrage, die
        // sehr wohl abgerechnet wurde. Diese Spalte haelt fest, welche der
        // beiden Zahlen man vor sich hat.
        $table = new xmldb_table('local_elediaai_core_turn');
        $field = new xmldb_field(
            'tokensestimated',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'completiontokens'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026091903, 'local', 'elediaai_core');
    }

    if ($oldversion < 2026092000) {
        // Die beiden Listen auf „Jede KI-Anfrage" ueberschnitten sich: eine
        // Anfrage der Suite laeuft durch core_ai und steht deshalb auch in
        // Moodles Register. Trennen liess sich das nicht, weil das Register
        // keine Komponente fuehrt. Jetzt merkt sich der Turn die Zeilennummer,
        // die sein Aufruf dort erzeugt hat, und die Registeransicht laesst
        // genau diese Zeilen aus.
        $table = new xmldb_table('local_elediaai_core_turn');
        $field = new xmldb_field(
            'registerid',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            null,
            null,
            null,
            'tokensestimated'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $index = new xmldb_index('registerid_ix', XMLDB_INDEX_NOTUNIQUE, ['registerid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026092000, 'local', 'elediaai_core');
    }

    return true;
}

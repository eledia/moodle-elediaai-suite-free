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
 * Upgrade steps for the eLeDia.ai Tutor block.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute upgrade steps between versions.
 *
 * @param int $oldversion The currently installed version.
 * @return bool
 */
function xmldb_block_elediaai_tutor_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026061102) {
        // Question analytics log (opt-in; see classes/local/question_log.php).
        $table = new xmldb_table('block_elediaai_tutor_qlog');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('question', XMLDB_TYPE_CHAR, '1000', null, XMLDB_NOTNULL);
        $table->add_field('grounded', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('answerstyle', XMLDB_TYPE_CHAR, '10');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_index('courseid-timecreated', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'timecreated']);
        $table->add_index('timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_block_savepoint(true, 2026061102, 'elediaai_tutor');
    }

    if ($oldversion < 2026061103) {
        // Analytics clustering anchors: server-supplied topic label plus the
        // primary cited source (title + resolved cmid).
        $table = new xmldb_table('block_elediaai_tutor_qlog');
        $fields = [
            new xmldb_field('topic', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'answerstyle'),
            new xmldb_field('sourcetitle', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'topic'),
            new xmldb_field('cmid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'sourcetitle'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_block_savepoint(true, 2026061103, 'elediaai_tutor');
    }

    if ($oldversion < 2026061106) {
        // Documented first-use privacy consent (see classes/local/consent.php).
        $table = new xmldb_table('block_elediaai_tutor_consent');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN_UNIQUE, ['userid'], 'user', ['id']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_block_savepoint(true, 2026061106, 'elediaai_tutor');
    }

    if ($oldversion < 2026061114) {
        // Per-user daily message counters (see classes/local/usage.php).
        $table = new xmldb_table('block_elediaai_tutor_usage');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('daykey', XMLDB_TYPE_INTEGER, '8', null, XMLDB_NOTNULL);
        $table->add_field('messagecount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('userid-daykey', XMLDB_INDEX_UNIQUE, ['userid', 'daykey']);
        $table->add_index('daykey', XMLDB_INDEX_NOTUNIQUE, ['daykey']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_block_savepoint(true, 2026061114, 'elediaai_tutor');
    }

    if ($oldversion < 2026061320) {
        // Saved site-wide tutor profiles (see classes/local/tutor_profile.php).
        $table = new xmldb_table('block_elediaai_tutor_tutor');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        $table->add_field('shortname', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $table->add_field('description', XMLDB_TYPE_TEXT);
        $table->add_field('settings', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('shortname', XMLDB_INDEX_UNIQUE, ['shortname']);
        $table->add_index('sortorder', XMLDB_INDEX_NOTUNIQUE, ['sortorder']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Themes are replaced by tutor profiles. Preserve any existing look by
        // snapshotting the old theme's palette into the individual token
        // settings (site + each instance), then dropping the obsolete 'theme'.
        $sitetheme = (string) get_config('block_elediaai_tutor', 'theme');
        if (
            $sitetheme !== '' && \block_elediaai_tutor\local\presets::exists($sitetheme)
                && $sitetheme !== \block_elediaai_tutor\local\presets::DEFAULT
        ) {
            foreach (\block_elediaai_tutor\local\presets::settings($sitetheme) as $key => $value) {
                $cfgkey = \block_elediaai_tutor\local\registry::sitekey($key);
                if ((string) get_config('block_elediaai_tutor', $cfgkey) === '') {
                    set_config($cfgkey, $value, 'block_elediaai_tutor');
                }
            }
        }
        unset_config('theme', 'block_elediaai_tutor');

        // Per-instance theme overrides → explicit token overrides in the config.
        $instances = $DB->get_records('block_instances', ['blockname' => 'elediaai_tutor']);
        foreach ($instances as $bi) {
            if (empty($bi->configdata)) {
                continue;
            }
            $config = unserialize_object(base64_decode($bi->configdata));
            if (!is_object($config) || empty($config->theme)) {
                continue;
            }
            $theme = (string) $config->theme;
            if (
                \block_elediaai_tutor\local\presets::exists($theme)
                    && $theme !== \block_elediaai_tutor\local\presets::DEFAULT
            ) {
                foreach (\block_elediaai_tutor\local\presets::settings($theme) as $key => $value) {
                    if (!isset($config->$key) || $config->$key === '') {
                        $config->$key = $value;
                    }
                }
            }
            unset($config->theme);
            $DB->set_field(
                'block_instances',
                'configdata',
                base64_encode(serialize($config)),
                ['id' => $bi->id]
            );
        }

        upgrade_block_savepoint(true, 2026061320, 'elediaai_tutor');
    }

    if ($oldversion < 2026061321) {
        // Every optical/persona setting is now overridable per instance by
        // default. The 0.13.0 install persisted the old (mostly off) expose_*
        // defaults to config, so re-sync each instanceable key's expose flag to
        // its new default. Safe one-off: 0.13.0 was never released, so no admin
        // could have chosen these yet.
        foreach (\block_elediaai_tutor\local\registry::all() as $key => $entry) {
            if (empty($entry['instanceable'])) {
                continue;
            }
            set_config(
                \block_elediaai_tutor\local\registry::EXPOSE_PREFIX . $key,
                !empty($entry['exposedefault']) ? 1 : 0,
                'block_elediaai_tutor'
            );
        }

        upgrade_block_savepoint(true, 2026061321, 'elediaai_tutor');
    }

    if ($oldversion < 2026061330) {
        // The default presentation is now the docked floating panel; migrate the
        // old 'embedded' default (the new default reaches fresh installs via
        // settings.php). An admin who deliberately chose another mode is left be.
        if ((string) get_config('block_elediaai_tutor', 'defaultdisplaymode') === 'embedded') {
            set_config('defaultdisplaymode', 'docked', 'block_elediaai_tutor');
        }

        upgrade_block_savepoint(true, 2026061330, 'elediaai_tutor');
    }

    if ($oldversion < 2026061602) {
        // MCP is now optional. Preserve existing installations that had already
        // selected a Moodle-MCP service by enabling the new switch for them.
        if ((int) get_config('block_elediaai_tutor', 'mcpserviceid') > 0) {
            set_config('enablemcp', 1, 'block_elediaai_tutor');
        }

        upgrade_block_savepoint(true, 2026061602, 'elediaai_tutor');
    }

    if ($oldversion < 2026062901) {
        // Lightweight admin diagnostics for failed tutor calls.
        $table = new xmldb_table('block_elediaai_tutor_diag');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('phase', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL);
        $table->add_field('errorcode', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $table->add_field('detail', XMLDB_TYPE_CHAR, '255');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
        $table->add_index('userid-timecreated', XMLDB_INDEX_NOTUNIQUE, ['userid', 'timecreated']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_block_savepoint(true, 2026062901, 'elediaai_tutor');
    }

    if ($oldversion < 2026073106) {
        // SUI-560: carried the one-time migration from the former
        // block_eledia_aitutor component. Both sites have passed this point and
        // the suite is reinstalled from scratch, so the step is a version gate.
        upgrade_block_savepoint(true, 2026073106, 'elediaai_tutor');
    }

    if ($oldversion < 2026080101) {
        // SUI-560: carried the File API repair for the same rename (SUI-382).
        // Retired together with the migration helper; version gate only.
        upgrade_block_savepoint(true, 2026080101, 'elediaai_tutor');
    }

    if ($oldversion < 2026080102) {
        // SUI-416: the RAG auth token setting moved from configpasswordunmask
        // (plaintext at rest) to admin_setting_encryptedpassword. Encrypt any
        // existing plaintext token in place exactly once (idempotent: empty and
        // already-encrypted values are left untouched).
        \block_elediaai_tutor\local\security::migrate_plaintext_token();

        upgrade_block_savepoint(true, 2026080102, 'elediaai_tutor');
    }

    if ($oldversion < 2026080200) {
        // SUI-517: chat turns now reserve the shared token quota atomically
        // before the RAG call. Version gate only; no schema change.
        upgrade_block_savepoint(true, 2026080200, 'elediaai_tutor');
    }

    if ($oldversion < 2026082700) {
        // Conversations and the daily counters moved to
        // local_elediaai_chatengine, which keeps one store for every chat
        // surface. Nothing is copied over: the pointers here held a
        // server-issued conversation id and no message text, so carrying them
        // across would produce threads with a transcript nobody can read and a
        // history that starts in the middle.
        foreach (['block_elediaai_tutor_conv', 'block_elediaai_tutor_usage'] as $tablename) {
            $table = new xmldb_table($tablename);
            if ($dbman->table_exists($table)) {
                $dbman->drop_table($table);
            }
        }

        // The backend connection is the engine's now. The values are carried
        // over rather than dropped: an administrator configured a working
        // endpoint and a credential once, and making them do it again for a
        // move they did not ask for is how a working site ends up broken after
        // an upgrade. The token travels as stored - both sides encrypt with the
        // site key and decrypt the same way, so the ciphertext is portable.
        $moved = [
            'ragserverurl' => 'backend_ingestionapi_url',
            'ragauthmethod' => 'backend_ingestionapi_authmethod',
            'ragauthtoken' => 'backend_ingestionapi_authtoken',
            'allowinsecuretransport' => 'backend_ingestionapi_allowinsecure',
            'allowprivatenetwork' => 'backend_ingestionapi_allowprivate',
            'chattoolname' => 'tool_chat',
            'historytoolname' => 'tool_history',
            'deletetoolname' => 'tool_delete',
            'deleteusertoolname' => 'tool_deleteuser',
            'memoryoptintoolname' => 'tool_memoryoptin',
            'reclustertoolname' => 'tool_recluster',
            'mcpserviceid' => 'mcpserviceid',
            'tokenlifetime' => 'tokenlifetime',
            'requesttimeout' => 'requesttimeout',
            'maxmessagelength' => 'maxmessagelength',
            'ratelimitperminute' => 'ratelimitperminute',
            'dailymessagelimit' => 'dailymessagelimit',
            'streamingenabled' => 'streamingenabled',
        ];
        foreach ($moved as $old => $new) {
            $value = get_config('block_elediaai_tutor', $old);
            // Never overwrite a value an administrator already entered on the
            // engine's own settings page: that one is the deliberate choice.
            $existing = get_config('local_elediaai_chatengine', $new);
            if ($value !== false && $value !== '' && ($existing === false || $existing === '')) {
                set_config($new, $value, 'local_elediaai_chatengine');
            }
            unset_config($old, 'block_elediaai_tutor');
        }

        upgrade_block_savepoint(true, 2026082700, 'elediaai_tutor');
    }

    if ($oldversion < 2026082800) {
        // The live check of the language model moved to the chat engine, which
        // asks whichever backend is active instead of one particular client.
        // Its cache went with it; this row would sit here unread.
        unset_config('llmhealthcache', 'block_elediaai_tutor');

        upgrade_block_savepoint(true, 2026082800, 'elediaai_tutor');
    }

    if ($oldversion < 2026091901) {
        // Der Frage-Log wird abgeworfen. Er wurde seit dem 27.08.2026 nicht
        // mehr gefuellt -- der Schreiber verschwand, als der Block ein
        // Placement auf der Engine wurde -- und seine Aufgabe hat seit dem
        // 19.09.2026 der Turn-Speicher in local_elediaai_core, ohne Namen und
        // mit einem Pseudonym-Schluessel. Nicht migriert: der Inhalt ist auf
        // jeder Installation aelter als jedes sinnvolle Auswertungsfenster.
        $table = new xmldb_table('block_elediaai_tutor_qlog');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        // Der Opt-in-Schalter davor faellt mit. Er stand standardmaessig auf
        // aus und machte damit auf den meisten Websites die Deutung und den
        // Navigationseintrag unerreichbar.
        unset_config('enableanalytics', 'block_elediaai_tutor');

        upgrade_plugin_savepoint(true, 2026091901, 'block', 'elediaai_tutor');
    }

    return true;
}

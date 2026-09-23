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
 * Privacy API implementation for local_elediaai_core.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core\privacy;

defined('MOODLE_INTERNAL') || die();

use context;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for the LernHive AI suite shell and its audit view.
 *
 * The audit view reads its base data straight from Moodle core's `core_ai`
 * subsystem (`ai_action_register` and the per-action detail tables). Exporting
 * and deleting those rows stays with Moodle core. This plugin additionally
 * stores compact per-user token counters for quota enforcement; those counters
 * are exported and erased here.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the (external) data this plugin relies on.
     *
     * @param collection $collection The metadata collection to add items to.
     * @return collection The updated collection.
     */
    public static function get_metadata(collection $collection): collection {
        // The audit base data is owned by Moodle core's core_ai subsystem;
        // this plugin only renders it and never copies it into its own tables.
        $collection->add_subsystem_link(
            'core_ai',
            [],
            'privacy:metadata:core_ai'
        );

        $collection->add_database_table('local_elediaai_core_usage', [
            'userid' => 'privacy:metadata:local_elediaai_core_usage:userid',
            'rolebucket' => 'privacy:metadata:local_elediaai_core_usage:rolebucket',
            'windowtype' => 'privacy:metadata:local_elediaai_core_usage:windowtype',
            'windowstart' => 'privacy:metadata:local_elediaai_core_usage:windowstart',
            'prompttokens' => 'privacy:metadata:local_elediaai_core_usage:prompttokens',
            'completiontokens' => 'privacy:metadata:local_elediaai_core_usage:completiontokens',
            'totaltokens' => 'privacy:metadata:local_elediaai_core_usage:totaltokens',
            'reservedtokens' => 'privacy:metadata:local_elediaai_core_usage:reservedtokens',
            'requestcount' => 'privacy:metadata:local_elediaai_core_usage:requestcount',
            'component' => 'privacy:metadata:local_elediaai_core_usage:component',
        ], 'privacy:metadata:local_elediaai_core_usage');

        // Der Turn-Speicher haelt keine Nutzerkennung, aber freien Text und
        // einen gesalzenen Pseudonym-Schluessel. Das ist pseudonym, nicht
        // anonym -- und wird hier auch so erklaert, statt die Tabelle
        // weglassen zu koennen, weil "kein userid" bequemer klingt.
        $collection->add_database_table('local_elediaai_core_turn', [
            'askerkey' => 'privacy:metadata:local_elediaai_core_turn:askerkey',
            'component' => 'privacy:metadata:local_elediaai_core_turn:component',
            'courseid' => 'privacy:metadata:local_elediaai_core_turn:courseid',
            'prompt' => 'privacy:metadata:local_elediaai_core_turn:prompt',
            'response' => 'privacy:metadata:local_elediaai_core_turn:response',
            'origin' => 'privacy:metadata:local_elediaai_core_turn:origin',
            'topic' => 'privacy:metadata:local_elediaai_core_turn:topic',
            'timecreated' => 'privacy:metadata:local_elediaai_core_turn:timecreated',
        ], 'privacy:metadata:local_elediaai_core_turn');

        // Das Handlungsprotokoll behaelt die Person absichtlich: Aufsicht ueber
        // das, was eine KI GETAN hat, muss sagen koennen, in wessen Auftrag.
        // Eine Loeschanfrage entfernt deshalb den Bezug und behaelt die Zeile.
        $collection->add_database_table('local_elediaai_core_action', [
            'userid' => 'privacy:metadata:local_elediaai_core_action:userid',
            'toolname' => 'privacy:metadata:local_elediaai_core_action:toolname',
            'iswrite' => 'privacy:metadata:local_elediaai_core_action:iswrite',
            'success' => 'privacy:metadata:local_elediaai_core_action:success',
            'courseid' => 'privacy:metadata:local_elediaai_core_action:courseid',
            'timecreated' => 'privacy:metadata:local_elediaai_core_action:timecreated',
        ], 'privacy:metadata:local_elediaai_core_action');

        return $collection;
    }

    /**
     * Quota counters live in the system context.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();
        if ($DB->record_exists('local_elediaai_core_usage', ['userid' => $userid])) {
            $contextlist->add_system_context();
            return $contextlist;
        }
        // Der Turn-Speicher wird ueber den neu berechneten Schluessel
        // gefunden, nie ueber eine Aufloesung. Genau das macht das Pseudonym
        // beantwortbar, ohne es aufloesbar zu machen.
        $key = \local_elediaai_core\local\pseudonym::for_user($userid);
        $table = \local_elediaai_core\local\turn_recorder::TABLE;
        if ($key !== '' && $DB->record_exists($table, ['askerkey' => $key])) {
            $contextlist->add_system_context();
            return $contextlist;
        }
        $actions = \local_elediaai_core\local\action_recorder::TABLE;
        if ($DB->record_exists($actions, ['userid' => $userid])) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * Find users with quota data in the given context.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        if (!$userlist->get_context() instanceof \core\context\system) {
            return;
        }
        // Nur das Guthaben-Hauptbuch kann Nutzer aufzaehlen. Der Turn-Speicher
        // haelt einen gesalzenen Hash, und ein Hash laesst sich nicht
        // rueckwaerts in eine Nutzerliste verwandeln -- das ist der Preis des
        // Pseudonyms und ausdruecklich gewollt. Loeschen und Exportieren
        // funktionieren trotzdem, weil sie vom Nutzer ausgehen (der Schluessel
        // wird neu berechnet), nicht von der Zeile.
        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_elediaai_core_usage}', []);
        $userlist->add_from_sql(
            'userid',
            'SELECT userid FROM {local_elediaai_core_action} WHERE userid <> 0',
            []
        );
    }

    /**
     * Export quota counters.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (!self::contains_system_context($contextlist->get_contexts())) {
            return;
        }

        self::export_turns((int) $contextlist->get_user()->id);
        self::export_actions((int) $contextlist->get_user()->id);

        $records = $DB->get_records(
            'local_elediaai_core_usage',
            ['userid' => (int) $contextlist->get_user()->id],
            'windowstart ASC, windowtype ASC'
        );
        if (empty($records)) {
            return;
        }

        $data = [];
        foreach ($records as $record) {
            $data[] = (object) [
                'rolebucket' => $record->rolebucket,
                'windowtype' => $record->windowtype,
                'windowstart' => transform::datetime($record->windowstart),
                'prompttokens' => (int) $record->prompttokens,
                'completiontokens' => (int) $record->completiontokens,
                'totaltokens' => (int) $record->totaltokens,
                'reservedtokens' => (int) $record->reservedtokens,
                'requestcount' => (int) $record->requestcount,
                'component' => $record->component,
            ];
        }

        writer::with_context(\core\context\system::instance())->export_data(
            [get_string('quota_settings_heading', 'local_elediaai_core')],
            (object) ['quotas' => $data]
        );
    }

    /**
     * Delete all quota counters in the system context.
     *
     * @param context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if ($context instanceof \core\context\system) {
            $DB->delete_records('local_elediaai_core_usage');
            $DB->delete_records(\local_elediaai_core\local\turn_recorder::TABLE);
            $DB->set_field(\local_elediaai_core\local\action_recorder::TABLE, 'userid', 0, []);
        }
    }

    /**
     * Delete quota counters for one user.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        if (self::contains_system_context($contextlist->get_contexts())) {
            $userid = (int) $contextlist->get_user()->id;
            $DB->delete_records('local_elediaai_core_usage', ['userid' => $userid]);
            \local_elediaai_core\local\insights::delete_for_user($userid);
            // Anonymisieren, nicht loeschen: sonst koennte jede Person den
            // Nachweis der Bewertung tilgen, die die KI fuer sie geschrieben hat.
            \local_elediaai_core\local\actions::anonymise_for_user($userid);
        }
    }

    /**
     * Delete quota counters for multiple approved users.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        if (!$userlist->get_context() instanceof \core\context\system) {
            return;
        }
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_elediaai_core_usage', "userid {$insql}", $params);
        foreach ($userids as $userid) {
            \local_elediaai_core\local\insights::delete_for_user((int) $userid);
            \local_elediaai_core\local\actions::anonymise_for_user((int) $userid);
        }
    }

    /**
     * Export the turns a person asked.
     *
     * The answer travels with the question: a data subject who asks what is
     * stored about them is owed the whole exchange, not half of it.
     *
     * @param int $userid
     * @return void
     */
    private static function export_turns(int $userid): void {
        $turns = \local_elediaai_core\local\insights::turns_for_user($userid);
        if ($turns === []) {
            return;
        }

        $data = [];
        foreach ($turns as $turn) {
            $data[] = (object) [
                'timecreated' => transform::datetime((int) $turn->timecreated),
                'component' => $turn->component,
                'courseid' => (int) $turn->courseid,
                'origin' => $turn->origin,
                'topic' => $turn->topic,
                'prompt' => $turn->prompt,
                'response' => $turn->response,
            ];
        }

        writer::with_context(\core\context\system::instance())->export_data(
            [get_string('privacy:path:turns', 'local_elediaai_core')],
            (object) ['turns' => $data]
        );
    }

    /**
     * Export the actions the AI performed for a person.
     *
     * @param int $userid
     * @return void
     */
    private static function export_actions(int $userid): void {
        $rows = \local_elediaai_core\local\actions::for_user($userid, 1000);
        if ($rows === []) {
            return;
        }

        $data = [];
        foreach ($rows as $row) {
            $data[] = (object) [
                'timecreated' => transform::datetime((int) $row->timecreated),
                'toolname' => $row->toolname,
                'component' => $row->component,
                'iswrite' => transform::yesno((int) $row->iswrite),
                'success' => transform::yesno((int) $row->success),
                'courseid' => (int) $row->courseid,
            ];
        }

        writer::with_context(\core\context\system::instance())->export_data(
            [get_string('privacy:path:actions', 'local_elediaai_core')],
            (object) ['actions' => $data]
        );
    }

    /**
     * Whether a context list contains the system context.
     *
     * @param context[] $contexts
     * @return bool
     */
    private static function contains_system_context(array $contexts): bool {
        foreach ($contexts as $context) {
            if ($context instanceof \core\context\system) {
                return true;
            }
        }
        return false;
    }
}

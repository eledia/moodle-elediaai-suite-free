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
 * Privacy provider for local_aitransparency.
 *
 * @package    local_aitransparency
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aitransparency\privacy;

use context;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Personal data is limited to the userid on a provenance record.
 *
 * Provenance records are legal compliance evidence (Art. 50 EU AI Act) and are
 * retained after the generated content is gone. On an erasure request the
 * record is therefore not deleted but anonymised: userid is set to 0, which
 * removes the only personal identifier while keeping the audit trail. The
 * scheduled task local_aitransparency\task\anonymise_records applies the same
 * anonymisation automatically once the retention window has passed.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    #[\Override]
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_aitransparency_rec', [
            'userid' => 'privacy:metadata:local_aitransparency_rec:userid',
            'contextid' => 'privacy:metadata:local_aitransparency_rec:contextid',
            'actionname' => 'privacy:metadata:local_aitransparency_rec:actionname',
            'provider' => 'privacy:metadata:local_aitransparency_rec:provider',
            'model' => 'privacy:metadata:local_aitransparency_rec:model',
            'contenthash' => 'privacy:metadata:local_aitransparency_rec:contenthash',
            'timecreated' => 'privacy:metadata:local_aitransparency_rec:timecreated',
        ], 'privacy:metadata:local_aitransparency_rec');
        $collection->add_database_table('local_aitransparency_file', [
            'filecontenthash' => 'privacy:metadata:local_aitransparency_file:filecontenthash',
            'timecreated' => 'privacy:metadata:local_aitransparency_file:timecreated',
        ], 'privacy:metadata:local_aitransparency_file');
        return $collection;
    }

    #[\Override]
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql(
            'SELECT DISTINCT contextid FROM {local_aitransparency_rec} WHERE userid = :userid',
            ['userid' => $userid]
        );
        return $contextlist;
    }

    #[\Override]
    public static function get_users_in_context(userlist $userlist) {
        $userlist->add_from_sql(
            'userid',
            'SELECT userid FROM {local_aitransparency_rec} WHERE contextid = :contextid AND userid <> 0',
            ['contextid' => $userlist->get_context()->id]
        );
    }

    #[\Override]
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $records = $DB->get_records('local_aitransparency_rec', [
                'contextid' => $context->id,
                'userid' => $userid,
            ], 'timecreated ASC');
            if (!$records) {
                continue;
            }
            $export = array_values(array_map(static fn(\stdClass $rec): array => [
                'uuid' => $rec->uuid,
                'component' => $rec->component,
                'actionname' => $rec->actionname,
                'provider' => $rec->provider,
                'model' => $rec->model,
                'assettype' => $rec->assettype,
                'markstate' => $rec->markstate,
                'timecreated' => transform::datetime($rec->timecreated),
            ], $records));
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_aitransparency')],
                (object) ['records' => $export]
            );
        }
    }

    #[\Override]
    public static function delete_data_for_all_users_in_context(context $context) {
        self::anonymise_select('contextid = :contextid AND userid <> 0', ['contextid' => $context->id]);
    }

    #[\Override]
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userid = (int) $contextlist->get_user()->id;
        $contextids = $contextlist->get_contextids();
        if (!$contextids) {
            return;
        }
        [$insql, $params] = self::in_or_equal_contexts($contextids);
        $params['userid'] = $userid;
        self::anonymise_select("userid = :userid AND contextid {$insql}", $params);
    }

    #[\Override]
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        $params['contextid'] = $userlist->get_context()->id;
        self::anonymise_select("contextid = :contextid AND userid {$insql}", $params);
    }

    /**
     * Build a named IN clause for context ids.
     *
     * @param int[] $contextids
     * @return array{0: string, 1: array<string, mixed>}
     */
    private static function in_or_equal_contexts(array $contextids): array {
        global $DB;
        return $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'ctx');
    }

    /**
     * Anonymise (userid = 0) the records matching a WHERE clause, keeping the
     * compliance evidence intact.
     *
     * @param string $select
     * @param array<string, mixed> $params
     */
    private static function anonymise_select(string $select, array $params): void {
        global $DB;
        $DB->set_field_select('local_aitransparency_rec', 'userid', 0, $select, $params);
    }
}

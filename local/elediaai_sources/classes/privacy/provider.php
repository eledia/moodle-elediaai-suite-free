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

declare(strict_types=1);

namespace local_elediaai_sources\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for local_elediaai_sources.
 *
 * The plugin sends course content and course/module metadata to the configured
 * external AI Sources service, and records which user last changed an
 * activity's ingestion selection. Both are declared here for Moodle's privacy
 * subsystem.
 *
 * An ingestion decision belongs to the activity, not to the person who made
 * it: removing it would silently change what the course sends to the index.
 * An erasure request therefore removes the person from the decision
 * (usermodified becomes 0) and keeps the decision itself.
 *
 * The extractor subplugins only read activity content and store nothing; the
 * transfer of that content, including entries contributed by participants
 * (database and glossary entries, wiki pages), is made by this plugin and
 * declared here.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /** @var string The per-activity decision table. */
    private const TABLE = 'local_elediaai_sources_cm';

    /**
     * Describe stored data and data sent to external services.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(self::TABLE, [
            'usermodified' => 'privacy:metadata:cm:usermodified',
            'included' => 'privacy:metadata:cm:included',
            'timemodified' => 'privacy:metadata:cm:timemodified',
        ], 'privacy:metadata:cm');

        $collection->add_external_location_link('rag_service', [
            'site_url' => 'privacy:metadata:rag_service:site_url',
            'course_id' => 'privacy:metadata:rag_service:course_id',
            'cmid' => 'privacy:metadata:rag_service:cmid',
            'module_url' => 'privacy:metadata:rag_service:module_url',
            'content' => 'privacy:metadata:rag_service:content',
            'usercontent' => 'privacy:metadata:rag_service:usercontent',
        ], 'privacy:metadata:rag_service');

        return $collection;
    }

    /**
     * Module contexts of the activities whose decision the user last changed.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql(
            'SELECT ctx.id
               FROM {' . self::TABLE . '} d
               JOIN {context} ctx ON ctx.instanceid = d.cmid AND ctx.contextlevel = :contextlevel
              WHERE d.usermodified = :userid',
            ['contextlevel' => CONTEXT_MODULE, 'userid' => $userid]
        );

        return $contextlist;
    }

    /**
     * The user who last changed the decision of this activity.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \core\context\module) {
            return;
        }

        $userlist->add_from_sql(
            'usermodified',
            'SELECT usermodified FROM {' . self::TABLE . '} WHERE cmid = :cmid AND usermodified > 0',
            ['cmid' => $context->instanceid]
        );
    }

    /**
     * Export the ingestion decisions the user last changed.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \core\context\module) {
                continue;
            }
            $record = $DB->get_record(self::TABLE, ['cmid' => $context->instanceid, 'usermodified' => $userid]);
            if (!$record) {
                continue;
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_elediaai_sources')],
                (object) [
                    'included' => transform::yesno((int) $record->included === 1),
                    'timemodified' => transform::datetime((int) $record->timemodified),
                ]
            );
        }
    }

    /**
     * Remove the person from the decision of this activity; keep the decision.
     *
     * @param \context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if (!$context instanceof \core\context\module) {
            return;
        }
        $DB->set_field(self::TABLE, 'usermodified', 0, ['cmid' => $context->instanceid]);
    }

    /**
     * Remove the user from the decisions they last changed; keep the decisions.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \core\context\module) {
                continue;
            }
            $DB->set_field(self::TABLE, 'usermodified', 0, ['cmid' => $context->instanceid, 'usermodified' => $userid]);
        }
    }

    /**
     * Remove the given users from the decision of this activity; keep it.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        $userids = $userlist->get_userids();
        if (!$context instanceof \core\context\module || empty($userids)) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['cmid'] = $context->instanceid;
        $DB->set_field_select(self::TABLE, 'usermodified', 0, "cmid = :cmid AND usermodified {$insql}", $params);
    }
}

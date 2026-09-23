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

namespace local_aitransparency\privacy;

use context_system;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;

/**
 * Tests for the local_aitransparency privacy provider.
 *
 * @package    local_aitransparency
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_aitransparency\privacy\provider
 */
final class provider_test extends \advanced_testcase {
    /**
     * Insert one provenance record for a user in the system context.
     *
     * @param int $userid
     * @param int $timecreated
     * @return int inserted record id
     */
    private function make_record(int $userid, int $timecreated = 0): int {
        global $DB;
        return (int) $DB->insert_record('local_aitransparency_rec', (object) [
            'uuid' => \core\uuid::generate(),
            'component' => 'local_elediaai_coursegen',
            'actionname' => 'generate_text',
            'provider' => 'aiprovider_eledia',
            'model' => 'test-model',
            'userid' => $userid,
            'contextid' => context_system::instance()->id,
            'assettype' => 'text',
            'contenthash' => hash('sha256', 'output-' . $userid . '-' . $timecreated),
            'markstate' => 'pending',
            'timecreated' => $timecreated ?: time(),
        ]);
    }

    public function test_get_metadata_covers_both_tables(): void {
        $collection = provider::get_metadata(new \core_privacy\local\metadata\collection('local_aitransparency'));
        $tables = array_map(static fn($item) => $item->get_name(), $collection->get_collection());
        $this->assertContains('local_aitransparency_rec', $tables);
        $this->assertContains('local_aitransparency_file', $tables);
    }

    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->make_record((int) $user->id);

        $contextlist = provider::get_contexts_for_userid((int) $user->id);
        $this->assertEqualsCanonicalizing(
            [context_system::instance()->id],
            $contextlist->get_contextids()
        );
    }

    public function test_delete_for_user_anonymises_but_keeps_record(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $recordid = $this->make_record((int) $user->id);

        $contextlist = new approved_contextlist($user, 'local_aitransparency', [context_system::instance()->id]);
        provider::delete_data_for_user($contextlist);

        $record = $DB->get_record('local_aitransparency_rec', ['id' => $recordid]);
        $this->assertNotFalse($record, 'Compliance record must be retained');
        $this->assertEquals(0, (int) $record->userid, 'User must be anonymised to 0');
    }

    public function test_delete_for_users_in_context_anonymises(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $recordid = $this->make_record((int) $user->id);

        $approved = new approved_userlist(context_system::instance(), 'local_aitransparency', [(int) $user->id]);
        provider::delete_data_for_users($approved);

        $this->assertEquals(0, (int) $DB->get_field('local_aitransparency_rec', 'userid', ['id' => $recordid]));
    }

    public function test_get_users_in_context_skips_anonymised(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->make_record((int) $user->id);
        $this->make_record(0);

        $userlist = new userlist(context_system::instance(), 'local_aitransparency');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([(int) $user->id], $userlist->get_userids());
    }
}

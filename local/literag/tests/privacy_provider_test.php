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

namespace local_literag;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_literag\local\conversation_repository;
use local_literag\local\tenant;
use local_literag\privacy\provider;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Privacy provider tests for LiteRAG user data.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(provider::class)]
final class privacy_provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Create all user-owned LiteRAG rows exported by the privacy provider.
     *
     * @param int $userid Owner id.
     * @param int $courseid Course id.
     * @return \stdClass Conversation row.
     */
    private function create_user_data(int $userid, int $courseid = 42): \stdClass {
        global $DB;

        $now = time();
        $tenant = tenant::id();
        $repo = new conversation_repository();
        $conversation = $repo->create($userid, $courseid, 'explain');
        $repo->add_message($conversation, 'user', 'Explain photosynthesis.', 'biology', 7);
        $repo->add_message($conversation, 'assistant', 'Photosynthesis creates glucose.', 'biology', 7);

        $DB->insert_record('local_literag_memory', (object) [
            'userid' => $userid,
            'tenant' => $tenant,
            'mkey' => 'preference',
            'mvalue' => 'Likes concise hints.',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $DB->insert_record('local_literag_query_log', (object) [
            'userid' => $userid,
            'courseid' => $courseid,
            'tenant' => $tenant,
            'querytext' => 'Explain photosynthesis',
            'numcandidates' => 4,
            'numreturned' => 2,
            'usedllm' => 1,
            'reranked' => 0,
            'latencyms' => 123,
            'timecreated' => $now,
        ]);

        return $conversation;
    }

    /**
     * Metadata declares all personal-data tables and the external LLM location.
     */
    public function test_get_metadata_declares_tables_and_external_llm(): void {
        $collection = provider::get_metadata(new collection('local_literag'));
        $items = [];
        foreach ($collection->get_collection() as $item) {
            $items[] = $item->get_name();
        }

        $this->assertContains('local_literag_conversations', $items);
        $this->assertContains('local_literag_messages', $items);
        $this->assertContains('local_literag_memory', $items);
        $this->assertContains('local_literag_query_log', $items);
        $this->assertContains('llm_provider', $items);
    }

    /**
     * A user with LiteRAG data is reported at the system context only.
     */
    public function test_get_contexts_for_userid_reports_system_context(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->create_user_data((int) $user->id);

        $contextlist = provider::get_contexts_for_userid((int) $user->id);

        $this->assertCount(1, $contextlist);
        $this->assertEquals(
            [\core\context\system::instance()->id],
            array_map('intval', $contextlist->get_contextids())
        );
        $this->assertCount(0, provider::get_contexts_for_userid((int) $other->id));
    }

    /**
     * User discovery only reports users with data in the system context.
     */
    public function test_get_users_in_context_reports_user_ids(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->create_user_data((int) $user->id);

        $userlist = new userlist(\core\context\system::instance(), 'local_literag');
        provider::get_users_in_context($userlist);

        $this->assertContains((int) $user->id, $userlist->get_userids());

        $courselist = new userlist(\core\context\course::instance(SITEID), 'local_literag');
        provider::get_users_in_context($courselist);
        $this->assertSame([], $courselist->get_userids());
    }

    /**
     * Export includes conversations, memory and query logs for the approved user.
     */
    public function test_export_user_data(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $conversation = $this->create_user_data((int) $user->id, 23);
        $context = \core\context\system::instance();

        $contextlist = new approved_contextlist($user, 'local_literag', [$context->id]);
        provider::export_user_data($contextlist);

        $writer = writer::with_context($context);
        $conversationdata = $writer->get_data([
            get_string('pluginname', 'local_literag'),
            get_string('privacy:conversations', 'local_literag'),
            $conversation->convkey,
        ]);
        $this->assertEquals(23, $conversationdata->courseid);
        $this->assertCount(2, $conversationdata->messages);
        $this->assertSame('Explain photosynthesis.', $conversationdata->messages[0]->content);

        $memory = $writer->get_data([
            get_string('pluginname', 'local_literag'),
            get_string('privacy:memory', 'local_literag'),
        ]);
        $this->assertSame(['Likes concise hints.'], $memory->facts);

        $logs = $writer->get_data([
            get_string('pluginname', 'local_literag'),
            get_string('privacy:querylogs', 'local_literag'),
        ]);
        $this->assertCount(1, $logs->queries);
        $this->assertSame('Explain photosynthesis', $logs->queries[0]->querytext);
    }

    /**
     * Deleting one approved user delegates to the shared eraser and spares others.
     */
    public function test_delete_data_for_user_removes_only_approved_user(): void {
        global $DB;

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->create_user_data((int) $user->id);
        $this->create_user_data((int) $other->id);

        provider::delete_data_for_user(new approved_contextlist(
            $user,
            'local_literag',
            [\core\context\system::instance()->id]
        ));

        $this->assertFalse($DB->record_exists('local_literag_conversations', ['userid' => $user->id]));
        $this->assertFalse($DB->record_exists('local_literag_messages', ['userid' => $user->id]));
        $this->assertFalse($DB->record_exists('local_literag_memory', ['userid' => $user->id]));
        $this->assertFalse($DB->record_exists('local_literag_query_log', ['userid' => $user->id]));

        $this->assertTrue($DB->record_exists('local_literag_conversations', ['userid' => $other->id]));
        $this->assertTrue($DB->record_exists('local_literag_messages', ['userid' => $other->id]));
        $this->assertTrue($DB->record_exists('local_literag_memory', ['userid' => $other->id]));
        $this->assertTrue($DB->record_exists('local_literag_query_log', ['userid' => $other->id]));
    }

    /**
     * Bulk deletion removes only the approved users in the system context.
     */
    public function test_delete_data_for_users_removes_approved_users_only(): void {
        global $DB;

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->create_user_data((int) $user->id);
        $this->create_user_data((int) $other->id);

        $userlist = new approved_userlist(
            \core\context\system::instance(),
            'local_literag',
            [(int) $user->id]
        );
        provider::delete_data_for_users($userlist);

        $this->assertFalse($DB->record_exists('local_literag_conversations', ['userid' => $user->id]));
        $this->assertTrue($DB->record_exists('local_literag_conversations', ['userid' => $other->id]));
        $this->assertTrue($DB->record_exists('local_literag_memory', ['userid' => $other->id]));
        $this->assertTrue($DB->record_exists('local_literag_query_log', ['userid' => $other->id]));
    }

    /**
     * System-context deletion clears all LiteRAG user-data tables.
     */
    public function test_delete_data_for_all_users_in_context_clears_user_tables(): void {
        global $DB;

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->create_user_data((int) $user->id);

        provider::delete_data_for_all_users_in_context(\core\context\system::instance());

        $this->assertSame(0, $DB->count_records('local_literag_conversations'));
        $this->assertSame(0, $DB->count_records('local_literag_messages'));
        $this->assertSame(0, $DB->count_records('local_literag_memory'));
        $this->assertSame(0, $DB->count_records('local_literag_query_log'));
    }
}

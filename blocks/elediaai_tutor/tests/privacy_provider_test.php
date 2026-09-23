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

namespace block_elediaai_tutor;

use PHPUnit\Framework\Attributes\CoversClass;
use block_elediaai_tutor\local\consent;
use local_elediaai_chatengine\local\message;
use local_elediaai_chatengine\local\mode;
use local_elediaai_chatengine\local\thread_store;
use block_elediaai_tutor\privacy\provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider tests.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\block_elediaai_tutor\privacy\provider::class)]
final class privacy_provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Metadata describes the conversation table and the external RAG location.
     */
    public function test_get_metadata(): void {
        $collection = new \core_privacy\local\metadata\collection('block_elediaai_tutor');
        $collection = provider::get_metadata($collection);
        $items = $collection->get_collection();
        $this->assertNotEmpty($items);
    }

    /**
     * A user who confirmed the privacy guidelines is reported at the system context.
     *
     * Two things are deliberately NOT what puts somebody here any more.
     * Conversations belong to the chat engine, whose provider reports them; and
     * the questions themselves belong to the turn log in local_elediaai_core
     * since 19.09.2026, where they are stored without a name.
     *
     * @return void
     */
    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        consent::give((int) $user->id, \core\context\system::instance());

        $contextlist = provider::get_contexts_for_userid((int) $user->id);

        $this->assertCount(1, $contextlist);
        $this->assertEquals(\core\context\system::instance()->id, $contextlist->get_contextids()[0]);
    }

    /**
     * Export writes what the block itself keeps.
     *
     * @return void
     */
    public function test_export_user_data(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        consent::give((int) $user->id, \core\context\system::instance());

        $contextlist = new approved_contextlist($user, 'block_elediaai_tutor', [\core\context\system::instance()->id]);
        provider::export_user_data($contextlist);

        $writer = writer::with_context(\core\context\system::instance());
        $this->assertTrue($writer->has_any_data());
    }

    /**
     * Deleting a user's data removes only their rows.
     *
     * @return void
     */
    public function test_delete_data_for_user(): void {
        global $DB;

        $this->resetAfterTest();
        $alice = $this->getDataGenerator()->create_user();
        $bob = $this->getDataGenerator()->create_user();
        consent::give((int) $alice->id, \core\context\system::instance());
        consent::give((int) $bob->id, \core\context\system::instance());

        $contextlist = new approved_contextlist($alice, 'block_elediaai_tutor', [\core\context\system::instance()->id]);
        provider::delete_data_for_user($contextlist);

        $this->assertSame(0, $DB->count_records('block_elediaai_tutor_consent', ['userid' => $alice->id]));
        $this->assertSame(1, $DB->count_records('block_elediaai_tutor_consent', ['userid' => $bob->id]));
    }

    /**
     * Deleting everything in the context clears the block's own tables.
     *
     * @return void
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        consent::give((int) $user->id, \core\context\system::instance());

        provider::delete_data_for_all_users_in_context(\core\context\system::instance());

        $this->assertSame(0, $DB->count_records('block_elediaai_tutor_consent', ['userid' => $user->id]));
    }

    /**
     * The block does not claim the conversation any more.
     *
     * Naming it in two providers would export the same turns twice and let one
     * erase report success while the other still held the rows.
     *
     * @return void
     */
    public function test_conversations_belong_to_the_engine(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->open_conversation((int) $user->id, 0, 'hi');

        $contextlist = provider::get_contexts_for_userid((int) $user->id);

        $this->assertCount(0, $contextlist);
        $this->assertSame(1, $this->count_conversations((int) $user->id));
    }

    /**
     * The consent record is reported, exported and erased like any other
     * personal data (and erasure re-arms the first-use gate).
     */
    public function test_consent_record_covered(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        consent::give((int) $user->id, \core\context\system::instance());

        // A consent-only user is reported at the system context.
        $contextlist = provider::get_contexts_for_userid((int) $user->id);
        $this->assertCount(1, $contextlist);

        // Export contains the consent timestamp.
        $approved = new approved_contextlist($user, 'block_elediaai_tutor', [\core\context\system::instance()->id]);
        provider::export_user_data($approved);
        $writer = writer::with_context(\core\context\system::instance());
        $exported = $writer->get_data([get_string('privacy:consent', 'block_elediaai_tutor')]);
        $this->assertNotEmpty($exported->timeconsented);

        // Erasure removes the record.
        provider::delete_data_for_user($approved);
        $this->assertFalse(consent::has_consented((int) $user->id));
    }

    /**
     * The LTM opt-in preference is erased in all three deletion paths.
     */
    public function test_ltm_preference_deleted_in_all_paths(): void {
        $this->resetAfterTest();
        $systemcontext = \core\context\system::instance();

        // Path 1: delete_data_for_user.
        $alice = $this->getDataGenerator()->create_user();
        set_user_preference(local\ltm::PREF, 1, (int) $alice->id);
        $contexts = provider::get_contexts_for_userid((int) $alice->id);
        $this->assertCount(1, $contexts, 'An LTM-only user must be discoverable by the privacy API.');
        $userlist = new \core_privacy\local\request\userlist($systemcontext, 'block_elediaai_tutor');
        provider::get_users_in_context($userlist);
        $this->assertContains((int) $alice->id, $userlist->get_userids());
        $contextlist = new approved_contextlist($alice, 'block_elediaai_tutor', [$systemcontext->id]);
        provider::delete_data_for_user($contextlist);
        $this->assertNull(get_user_preferences(local\ltm::PREF, null, (int) $alice->id));

        // Path 2: delete_data_for_users.
        $bob = $this->getDataGenerator()->create_user();
        $carol = $this->getDataGenerator()->create_user();
        set_user_preference(local\ltm::PREF, 1, (int) $bob->id);
        set_user_preference(local\ltm::PREF, 1, (int) $carol->id);
        $userlist = new \core_privacy\local\request\approved_userlist(
            $systemcontext,
            'block_elediaai_tutor',
            [(int) $bob->id]
        );
        provider::delete_data_for_users($userlist);
        $this->assertNull(get_user_preferences(local\ltm::PREF, null, (int) $bob->id));
        $this->assertSame('1', get_user_preferences(local\ltm::PREF, null, (int) $carol->id));

        // Path 3: delete_data_for_all_users_in_context.
        provider::delete_data_for_all_users_in_context($systemcontext);
        $this->assertNull(get_user_preferences(local\ltm::PREF, null, (int) $carol->id));
    }

    /**
     * The long-term memory opt-in is exported as a user preference.
     */
    public function test_export_user_preferences(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        \block_elediaai_tutor\local\ltm::set_enabled((int) $user->id, true);

        provider::export_user_preferences((int) $user->id);

        $writer = writer::with_context(\core\context\system::instance());
        $prefs = $writer->get_user_preferences('block_elediaai_tutor');
        $prefname = \block_elediaai_tutor\local\ltm::PREF;
        $this->assertNotEmpty($prefs->$prefname);
        $this->assertEquals(
            get_string('privacy:metadata:preference:ltm', 'block_elediaai_tutor'),
            $prefs->$prefname->description
        );
    }

    /**
     * A user without the preference set exports nothing for it.
     */
    public function test_export_user_preferences_unset(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        provider::export_user_preferences((int) $user->id);

        $writer = writer::with_context(\core\context\system::instance());
        $prefs = $writer->get_user_preferences('block_elediaai_tutor');
        $prefname = \block_elediaai_tutor\local\ltm::PREF;
        $this->assertTrue(empty($prefs->$prefname));
    }

    /**
     * Open a tutor conversation in the shared store.
     *
     * The block no longer owns a conversation table; the engine does. Tests
     * that need a conversation to exist create it the same way the placement
     * does at runtime.
     *
     * @param int $userid The owner.
     * @param int $courseid The course scope, 0 for the site-wide chat.
     * @param string $preview A first user message.
     * @return \stdClass The thread.
     */
    private function open_conversation(int $userid, int $courseid = 0, string $preview = 'hi'): \stdClass {
        // Deliberately create() and not open(): each call is a distinct
        // conversation.
        // open() would resume the one already there and the test would be
        // counting a single thread while believing it had made several.
        $thread = thread_store::create(
            'block_elediaai_tutor',
            $courseid,
            (int) \context_system::instance()->id,
            $courseid,
            $userid,
            null,
            'literag',
            mode::GROUNDED,
            'h'
        );
        thread_store::add_message((int) $thread->id, message::ROLE_USER, $preview);
        thread_store::touch((int) $thread->id, $preview);
        // A backend that keeps its own transcript issues an id; without one
        // there would be nothing for a per-conversation deletion to address.
        thread_store::set_convkey((int) $thread->id, 'conv-' . $thread->id);
        $thread->convkey = 'conv-' . $thread->id;

        return $thread;
    }

    /**
     * How many conversations this user holds in the tutor, across all scopes.
     *
     * @param int $userid The owner.
     * @return int The count.
     */
    private function count_conversations(int $userid): int {
        return count(thread_store::threads_for_user('block_elediaai_tutor', $userid));
    }
}

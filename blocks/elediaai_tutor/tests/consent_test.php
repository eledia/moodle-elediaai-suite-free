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
use block_elediaai_tutor\external\give_consent;
use block_elediaai_tutor\local\consent;
use local_elediaai_chatengine\local\message;
use local_elediaai_chatengine\local\usage;
use local_elediaai_chatengine\local\mode;
use local_elediaai_chatengine\local\thread_store;
use block_elediaai_tutor\local\ltm;

/**
 * Unit tests for the documented first-use privacy consent.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\block_elediaai_tutor\local\consent::class)]
#[CoversClass(\block_elediaai_tutor\external\give_consent::class)]
#[CoversClass(\block_elediaai_tutor\observer::class)]
final class consent_test extends \advanced_testcase {
    /**
     * Consent starts absent, give() documents it once (idempotent) and fires
     * the audit event exactly once.
     */
    public function test_give_is_documented_and_idempotent(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $uid = (int) $user->id;

        $this->assertFalse(consent::has_consented($uid));
        $this->assertNull(consent::time_consented($uid));

        $sink = $this->redirectEvents();
        consent::give($uid, \core\context\system::instance());
        consent::give($uid, \core\context\system::instance());

        $this->assertTrue(consent::has_consented($uid));
        $this->assertIsInt(consent::time_consented($uid));
        $this->assertSame(1, $DB->count_records(consent::TABLE, ['userid' => $uid]));

        $events = array_filter(
            $sink->get_events(),
            static fn($e) => $e instanceof \block_elediaai_tutor\event\consent_given
        );
        $this->assertCount(1, $events);
    }

    /**
     * The gate throws for users without a consent record.
     */
    public function test_require_consent_throws(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('error_consentrequired', 'block_elediaai_tutor'));
        consent::require_consent((int) $user->id);
    }

    /**
     * delete_for_user erases the record and re-arms the gate.
     */
    public function test_delete_for_user(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $uid = (int) $user->id;
        consent::give($uid, \core\context\system::instance());

        $this->assertSame(1, consent::delete_for_user($uid));
        $this->assertFalse(consent::has_consented($uid));
        $this->assertSame(0, consent::delete_for_user($uid));
    }

    /**
     * The real Moodle account-deletion flow erases every user-owned tutor store.
     */
    public function test_user_deleted_observer(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->seed_complete_local_data((int) $user->id, 'deleted');
        $this->seed_complete_local_data((int) $other->id, 'retained');

        $sink = $this->redirectEvents();
        delete_user($user);

        $this->assertSame('1', $DB->get_field('user', 'deleted', ['id' => $user->id]));
        foreach ($this->local_tables() as $table) {
            $this->assertFalse($DB->record_exists($table, ['userid' => $user->id]));
            $this->assertTrue($DB->record_exists($table, ['userid' => $other->id]));
        }
        $this->assertNull(get_user_preferences(ltm::PREF, null, (int) $user->id));
        $this->assertSame('1', get_user_preferences(ltm::PREF, null, (int) $other->id));

        // A single deletion audit with all six counters proves the pre-delete
        // hook ran before core purged the user's LTM preference.
        $events = array_values(array_filter(
            $sink->get_events(),
            static fn($event) => $event instanceof \block_elediaai_tutor\event\data_deletion_requested
        ));
        $this->assertCount(1, $events);
        $this->assertSame(6, $events[0]->other['localdeleted']);
        $this->assertSame(1, $events[0]->other['conversationsdeleted']);
        $this->assertSame(1, $events[0]->other['questionsdeleted']);
        $this->assertSame(1, $events[0]->other['consentsdeleted']);
        $this->assertSame(1, $events[0]->other['usagedeleted']);
        $this->assertSame(1, $events[0]->other['diagnosticsdeleted']);
        $this->assertSame(1, $events[0]->other['ltmpreferencedeleted']);
    }

    /**
     * The external function records consent for the calling user only.
     */
    public function test_give_consent_external(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $result = give_consent::execute(\core\context\system::instance()->id);
        $result = \core_external\external_api::clean_returnvalue(give_consent::execute_returns(), $result);

        $this->assertTrue($result['consented']);
        $this->assertTrue(consent::has_consented((int) $user->id));

        // Repeated calls stay idempotent.
        $result = give_consent::execute(\core\context\system::instance()->id);
        $result = \core_external\external_api::clean_returnvalue(give_consent::execute_returns(), $result);
        $this->assertTrue($result['consented']);
    }

    /**
     * Seed all five user-owned tutor tables and the LTM preference.
     *
     * @param int $userid User id.
     * @param string $suffix Unique fixture suffix.
     * @return void
     */
    private function seed_complete_local_data(int $userid, string $suffix): void {
        global $DB;

        $this->open_conversation($userid, 0, 'preview');
        // Die Fragen selbst liegen seit dem 19.09.2026 im Turn-Speicher
        // von local_elediaai_core, ohne Namen. Was eine Loeschung dort
        // erreicht, prueft dessen eigener Test.
        \local_elediaai_core\local\turn_recorder::record(
            component: 'block_elediaai_tutor',
            actionname: 'chat_turn',
            contextid: (int) \core\context\system::instance()->id,
            userid: $userid,
            prompt: 'Question ' . $suffix,
            response: 'Antwort',
        );
        $DB->insert_record('block_elediaai_tutor_consent', (object) [
            'userid' => $userid,
            'timecreated' => time(),
        ]);
        // The daily counter is the engine's table now, but it is still part of
        // what an erase has to reach.
        usage::increment($userid);
        $DB->insert_record('block_elediaai_tutor_diag', (object) [
            'userid' => $userid,
            'courseid' => 0,
            'contextid' => \core\context\system::instance()->id,
            'phase' => 'test',
            'errorcode' => 'test_error',
            'detail' => 'Fixture ' . $suffix,
            'timecreated' => time(),
        ]);
        ltm::set_enabled($userid, true);
    }

    /**
     * Return all user-owned local table names.
     *
     * @return string[]
     */
    private function local_tables(): array {
        // The conversation and the daily counter moved to the chat engine; what
        // is left is what the block itself owns.
        return [
            'block_elediaai_tutor_consent',
            'block_elediaai_tutor_diag',
        ];
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
}

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

namespace local_elediaai_chatengine;

use local_elediaai_chatengine\local\message;
use local_elediaai_chatengine\local\mode;
use local_elediaai_chatengine\local\thread_store;

/**
 * One store, for every placement.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_chatengine\local\thread_store
 */
final class thread_store_test extends \advanced_testcase {
    /**
     * Reopening returns the same conversation, not a second one.
     *
     * @return void
     */
    public function test_open_resumes_the_same_thread(): void {
        $this->resetAfterTest();

        $first = thread_store::open('mod_aichat', 7, 1, 3, 42, null, 'literag', mode::GROUNDED, 'hash-a');
        $second = thread_store::open('mod_aichat', 7, 1, 3, 42, null, 'literag', mode::GROUNDED, 'hash-a');

        $this->assertSame((int) $first->id, (int) $second->id);
    }

    /**
     * Two placements never see each other's conversations.
     *
     * @return void
     */
    public function test_placements_are_scoped_apart(): void {
        $this->resetAfterTest();

        $aichat = thread_store::open('mod_aichat', 7, 1, 3, 42, null, 'literag', mode::GROUNDED, 'h');
        $elli = thread_store::open('mod_elli', 7, 1, 3, 42, null, 'literag', mode::GROUNDED, 'h');

        $this->assertNotSame((int) $aichat->id, (int) $elli->id);
    }

    /**
     * A changed persona starts a new conversation beside the old one.
     *
     * Continuing would mix turns that followed different instructions; wiping
     * would throw away what a person actually asked.
     *
     * @return void
     */
    public function test_changed_persona_starts_a_new_thread(): void {
        $this->resetAfterTest();

        $first = thread_store::open('mod_aichat', 7, 1, 3, 42, null, 'literag', mode::GROUNDED, 'hash-a');
        thread_store::add_message((int) $first->id, message::ROLE_USER, 'erste Frage');

        $second = thread_store::open('mod_aichat', 7, 1, 3, 42, null, 'literag', mode::GROUNDED, 'hash-b');

        $this->assertNotSame((int) $first->id, (int) $second->id);
        $this->assertCount(1, thread_store::messages((int) $first->id));
        $this->assertCount(0, thread_store::messages((int) $second->id));
    }

    /**
     * A switched destination starts a new conversation too.
     *
     * The old thread's conversation id belongs to a service the site no longer
     * writes to; resuming it would ask the wrong index.
     *
     * @return void
     */
    public function test_switched_backend_starts_a_new_thread(): void {
        $this->resetAfterTest();

        $first = thread_store::open('mod_aichat', 7, 1, 3, 42, null, 'literag', mode::GROUNDED, 'h');
        $second = thread_store::open('mod_aichat', 7, 1, 3, 42, null, 'ingestionapi', mode::GROUNDED, 'h');

        $this->assertNotSame((int) $first->id, (int) $second->id);
        $this->assertSame('ingestionapi', $second->backend);
    }

    /**
     * Turns come back in order, with their citations.
     *
     * @return void
     */
    public function test_messages_round_trip_in_order(): void {
        $this->resetAfterTest();

        $thread = thread_store::open('block_elediaai_tutor', 0, 1, 0, 42, null, 'literag', mode::GROUNDED, 'h');
        $sources = [['title' => 'Kapitel 3', 'url' => 'https://example.org/3', 'snippet' => '…']];

        thread_store::add_message((int) $thread->id, message::ROLE_USER, 'Frage');
        thread_store::add_message((int) $thread->id, message::ROLE_ASSISTANT, 'Antwort', $sources);

        $messages = thread_store::messages((int) $thread->id);

        $this->assertCount(2, $messages);
        $this->assertSame(message::ROLE_USER, $messages[0]->role);
        $this->assertSame('Frage', $messages[0]->content);
        $this->assertSame(message::ROLE_ASSISTANT, $messages[1]->role);
        $this->assertSame('Kapitel 3', $messages[1]->sources[0]['title']);
    }

    /**
     * Where an answer came from is stored with it, not recomputed later.
     *
     * An answer built from Moodle's own data carries no citations, so its
     * origin cannot be inferred from them afterwards - it has to survive the
     * round trip on its own or a reloaded conversation misattributes it.
     *
     * @return void
     */
    public function test_the_origin_of_a_turn_round_trips(): void {
        $this->resetAfterTest();

        $thread = thread_store::open('block_elediaai_tutor', 0, 1, 0, 42, null, 'literag', mode::GROUNDED, 'h');

        thread_store::add_message((int) $thread->id, message::ROLE_USER, 'Frage');
        thread_store::add_message((int) $thread->id, message::ROLE_ASSISTANT, 'Aus Moodle gelesen', [], 'mcp');

        $messages = thread_store::messages((int) $thread->id);

        // The user turn has no origin to speak of, and does not invent one.
        $this->assertSame('', $messages[0]->origin);
        $this->assertSame('mcp', $messages[1]->origin);
        $this->assertSame([], $messages[1]->sources);
    }

    /**
     * A turn stored without an origin reads back as having none.
     *
     * Turns written before the origin was kept must stay recognisably unknown
     * rather than defaulting to a value nobody recorded.
     *
     * @return void
     */
    public function test_a_turn_without_an_origin_stays_without_one(): void {
        $this->resetAfterTest();

        $thread = thread_store::open('block_elediaai_tutor', 0, 1, 0, 42, null, 'literag', mode::GROUNDED, 'h');

        thread_store::add_message((int) $thread->id, message::ROLE_ASSISTANT, 'Antwort');

        $messages = thread_store::messages((int) $thread->id);

        $this->assertSame('', $messages[0]->origin);
    }

    /**
     * An unknown role is a programming error, not silently stored.
     *
     * @return void
     */
    public function test_unknown_role_is_rejected(): void {
        $this->resetAfterTest();

        $thread = thread_store::open('mod_aichat', 7, 1, 3, 42, null, 'literag', mode::GROUNDED, 'h');

        $this->expectException(\coding_exception::class);
        thread_store::add_message((int) $thread->id, 'root', 'darf nicht');
    }

    /**
     * Clearing drops the turns and forgets the backend's transcript.
     *
     * @return void
     */
    public function test_clear_forgets_the_backend_conversation(): void {
        $this->resetAfterTest();

        $thread = thread_store::open('mod_aichat', 7, 1, 3, 42, null, 'literag', mode::GROUNDED, 'h');
        thread_store::set_convkey((int) $thread->id, 'conv-123');
        thread_store::add_message((int) $thread->id, message::ROLE_USER, 'Frage');

        thread_store::clear((int) $thread->id);

        $reloaded = thread_store::current('mod_aichat', 7, 42);
        $this->assertNotNull($reloaded);
        $this->assertNull($reloaded->convkey);
        $this->assertCount(0, thread_store::messages((int) $thread->id));
    }

    /**
     * Trimming keeps the most recent turns, not the first ones.
     *
     * @return void
     */
    public function test_trim_keeps_the_latest_turns(): void {
        $this->resetAfterTest();

        $thread = thread_store::open('mod_aichat', 7, 1, 3, 42, null, 'literag', mode::GROUNDED, 'h');
        foreach (range(1, 10) as $index) {
            thread_store::add_message((int) $thread->id, message::ROLE_USER, 'Frage ' . $index);
        }

        thread_store::trim((int) $thread->id, 4);
        $messages = thread_store::messages((int) $thread->id);

        $this->assertCount(4, $messages);
        $this->assertSame('Frage 7', $messages[0]->content);
        $this->assertSame('Frage 10', $messages[3]->content);
    }

    /**
     * A guest owns their thread through their key, and only through it.
     *
     * @return void
     */
    public function test_guest_threads_are_keyed_not_shared(): void {
        $this->resetAfterTest();

        $mine = thread_store::open('mod_elli', 3, 1, 3, 0, 'guest-aaa', 'literag', mode::GROUNDED, 'h');
        $theirs = thread_store::open('mod_elli', 3, 1, 3, 0, 'guest-bbb', 'literag', mode::GROUNDED, 'h');

        $this->assertNotSame((int) $mine->id, (int) $theirs->id);
        $this->assertNotNull(thread_store::owned((int) $mine->id, 0, 'guest-aaa'));
        $this->assertNull(thread_store::owned((int) $mine->id, 0, 'guest-bbb'));
        $this->assertNull(thread_store::owned((int) $mine->id, 0, null));
    }

    /**
     * A conversation is not another user's to resume.
     *
     * @return void
     */
    public function test_threads_are_not_readable_by_others(): void {
        $this->resetAfterTest();

        $thread = thread_store::open('mod_aichat', 7, 1, 3, 42, null, 'literag', mode::GROUNDED, 'h');

        $this->assertNotNull(thread_store::owned((int) $thread->id, 42));
        $this->assertNull(thread_store::owned((int) $thread->id, 43));
    }

    /**
     * Deleting a context takes its conversations and their turns with it.
     *
     * @return void
     */
    public function test_delete_where_removes_turns_too(): void {
        global $DB;
        $this->resetAfterTest();

        $thread = thread_store::open('mod_aichat', 7, 55, 3, 42, null, 'literag', mode::GROUNDED, 'h');
        thread_store::add_message((int) $thread->id, message::ROLE_USER, 'Frage');

        thread_store::delete_where(['contextid' => 55]);

        $this->assertSame(0, $DB->count_records(thread_store::THREAD_TABLE, ['contextid' => 55]));
        $this->assertSame(0, $DB->count_records(thread_store::MESSAGE_TABLE, ['threadid' => (int) $thread->id]));
    }
}

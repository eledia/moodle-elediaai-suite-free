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
use local_elediaai_chatengine\local\usage;
use local_elediaai_chatengine\local\mode;
use local_elediaai_chatengine\local\thread_store;
use block_elediaai_tutor\local\deletion_service;
use block_elediaai_tutor\local\ltm;
use local_elediaai_chatengine\tests\fixtures\fake_adapter;
use moodle_url;

/**
 * Unit tests for the user data deletion service.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\block_elediaai_tutor\local\deletion_service::class)]
final class deletion_service_test extends \advanced_testcase {
    /**
     * Load the fake transport helper.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/elediaai_chatengine/tests/fixtures/fake_adapter.php');
        parent::setUpBeforeClass();
    }

    /**
     * Without a configured delete tool, local data is erased, external deletion
     * is honestly reported as unsupported, and other users are untouched.
     */
    public function test_local_only_deletion(): void {
        $this->resetAfterTest();
        $alice = $this->getDataGenerator()->create_user();
        $bob = $this->getDataGenerator()->create_user();
        $this->open_conversation((int) $alice->id, 0, 'one');
        $this->open_conversation((int) $alice->id, 5, 'two');
        $this->open_conversation((int) $bob->id, 0, 'bob');

        $sink = $this->redirectEvents();
        $result = deletion_service::delete_all_for_user((int) $alice->id, \core\context\system::instance());

        $this->assertSame(2, $result['localdeleted']);
        $this->assertFalse($result['externalsupported']);
        $this->assertSame(0, $result['externaldeleted']);
        $this->assertSame(0, $this->count_conversations((int) $alice->id));
        $this->assertSame(1, $this->count_conversations((int) $bob->id));

        $events = array_filter(
            $sink->get_events(),
            static fn($e) => $e instanceof \block_elediaai_tutor\event\data_deletion_requested
        );
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertEquals(2, $event->other['localdeleted']);
        $this->assertEquals(0, $event->other['externalsupported']);
    }

    /**
     * Complete local deletion covers every user-owned store and remains scoped.
     */
    public function test_complete_local_deletion_is_scoped_to_user(): void {
        $this->resetAfterTest();
        $alice = $this->getDataGenerator()->create_user();
        $bob = $this->getDataGenerator()->create_user();
        $this->seed_complete_local_data((int) $alice->id, 'alice');
        $this->seed_complete_local_data((int) $bob->id, 'bob');

        $sink = $this->redirectEvents();
        $result = deletion_service::delete_all_for_user((int) $alice->id, \core\context\system::instance());

        $this->assertSame(6, $result['localdeleted']);
        $this->assertSame(1, $result['conversationsdeleted']);
        $this->assertSame(1, $result['questionsdeleted']);
        $this->assertSame(1, $result['consentsdeleted']);
        $this->assertSame(1, $result['usagedeleted']);
        $this->assertSame(1, $result['diagnosticsdeleted']);
        $this->assertSame(1, $result['ltmpreferencedeleted']);
        $this->assertFalse($result['externalsupported']);

        global $DB;
        foreach ($this->local_tables() as $table) {
            $this->assertFalse($DB->record_exists($table, ['userid' => $alice->id]));
            $this->assertTrue($DB->record_exists($table, ['userid' => $bob->id]));
        }
        $this->assertFalse(ltm::is_enabled((int) $alice->id));
        $this->assertTrue(ltm::is_enabled((int) $bob->id));

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

        try {
            consent::require_consent((int) $alice->id);
            $this->fail('Deleting all tutor data must re-arm the first-use consent gate.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('error_consentrequired', $exception->errorcode);
        }
    }

    /**
     * Deleting with no data at all still succeeds and reports zero.
     */
    public function test_deletion_with_no_data(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $result = deletion_service::delete_all_for_user((int) $user->id, \core\context\system::instance());

        $this->assertSame(0, $result['localdeleted']);
        $this->assertSame(0, $result['externaldeleted']);
        $this->assertSame(0, $result['externalfailed']);
    }

    /**
     * With a delete tool configured and a working client, deletion is propagated
     * per conversation before the local erase.
     */
    public function test_external_deletion_with_injected_client(): void {
        $this->resetAfterTest();

        if (!class_exists('\\webservice_elediamcp\\api')) {
            $this->markTestSkipped('webservice_elediamcp connector plugin is not installed.');
        }

        // Configure an MCP service so the token provider can mint a real token.
        global $DB;
        $service = (object) [
            'name' => 'MCP test service', 'shortname' => 'mcptest', 'enabled' => 1,
            'restrictedusers' => 0, 'downloadfiles' => 0, 'uploadfiles' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ];
        $service->id = $DB->insert_record('external_services', $service);
        set_config('services', (string) $service->id, 'webservice_elediamcp');
        set_config('mcpserviceid', $service->id, 'block_elediaai_tutor');
        set_config('ragserverurl', 'https://rag.example.com/mcp', 'block_elediaai_tutor');
        set_config('deletetoolname', 'tutor_delete_conversation', 'block_elediaai_tutor');

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->open_conversation((int) $user->id, 0, 'one');
        $this->open_conversation((int) $user->id, 0, 'two');

        $adapter = new fake_adapter();
        // A backend without a user-level tool: the per-conversation fallback
        // then has to reach both conversations.
        $adapter->toolqueue = [new \local_elediaai_chatengine\adapter\adapter_exception(
            'error_backend_tool_missing',
            'no deleteuser tool'
        )];

        $result = deletion_service::delete_all_for_user(
            (int) $user->id,
            \core\context\system::instance(),
            $adapter
        );

        $this->assertTrue($result['externalsupported']);
        $this->assertSame(2, $result['externaldeleted']);
        $this->assertSame(0, $result['externalfailed']);
        $this->assertSame(2, $result['localdeleted']);
        $this->assertSame(0, $this->count_conversations((int) $user->id));
    }

    /**
     * An external failure is counted but never blocks complete local deletion.
     */
    public function test_external_failure_does_not_block_local_deletion(): void {
        $this->resetAfterTest();

        if (!class_exists('\\webservice_elediamcp\\api')) {
            $this->markTestSkipped('webservice_elediamcp connector plugin is not installed.');
        }

        global $DB;
        $service = (object) [
            'name' => 'MCP test service', 'shortname' => 'mcptest', 'enabled' => 1,
            'restrictedusers' => 0, 'downloadfiles' => 0, 'uploadfiles' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ];
        $service->id = $DB->insert_record('external_services', $service);
        set_config('services', (string) $service->id, 'webservice_elediamcp');
        set_config('mcpserviceid', $service->id, 'block_elediaai_tutor');
        set_config('ragserverurl', 'https://rag.example.com/mcp', 'block_elediaai_tutor');
        set_config('deleteusertoolname', 'tutor_delete_user_data', 'block_elediaai_tutor');

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->seed_complete_local_data((int) $user->id, 'failed');
        $adapter = new fake_adapter();
        // The backend is down: the user-level tool fails and so does the
        // per-conversation fallback for the one seeded conversation.
        $adapter->toolqueue = array_fill(0, 2, new \local_elediaai_chatengine\adapter\adapter_exception(
            'error_backend_unavailable',
            'http status 503',
            503
        ));

        $result = deletion_service::delete_all_for_user((int) $user->id, \core\context\system::instance(), $adapter);

        $this->assertTrue($result['externalsupported']);
        $this->assertSame(0, $result['externaldeleted']);
        $this->assertSame(1, $result['externalfailed']);
        $this->assertSame(6, $result['localdeleted']);
        foreach ($this->local_tables() as $table) {
            $this->assertFalse($DB->record_exists($table, ['userid' => $user->id]));
        }
        $this->assertFalse(ltm::is_enabled((int) $user->id));
    }

    /**
     * The user-level delete tool is preferred over per-conversation deletion:
     * exactly one call, even with multiple conversations, and it is attempted
     * even when Moodle holds no local pointers.
     */
    public function test_user_level_delete_tool_preferred(): void {
        $this->resetAfterTest();

        if (!class_exists('\\webservice_elediamcp\\api')) {
            $this->markTestSkipped('webservice_elediamcp connector plugin is not installed.');
        }

        global $DB;
        $service = (object) [
            'name' => 'MCP test service', 'shortname' => 'mcptest', 'enabled' => 1,
            'restrictedusers' => 0, 'downloadfiles' => 0, 'uploadfiles' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ];
        $service->id = $DB->insert_record('external_services', $service);
        set_config('services', (string) $service->id, 'webservice_elediamcp');
        set_config('mcpserviceid', $service->id, 'block_elediaai_tutor');
        set_config('ragserverurl', 'https://rag.example.com/mcp', 'block_elediaai_tutor');
        // Both tools configured: the user-level one must win.
        set_config('deletetoolname', 'tutor_delete_conversation', 'block_elediaai_tutor');
        set_config('deleteusertoolname', 'tutor_delete_user_data', 'block_elediaai_tutor');

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->open_conversation((int) $user->id, 0, 'one');
        $this->open_conversation((int) $user->id, 0, 'two');

        $adapter = new fake_adapter();

        $result = deletion_service::delete_all_for_user(
            (int) $user->id,
            \core\context\system::instance(),
            $adapter
        );

        $this->assertTrue($result['externalsupported']);
        $this->assertSame(1, $result['externaldeleted']);
        $this->assertSame(2, $result['localdeleted']);
        // One call, the user-level tool, addressing no single conversation.
        $this->assertCount(1, $adapter->toolcalls);
        [$tool, $arguments] = $adapter->toolcalls[0];
        $this->assertSame('deleteuser', $tool);
        $this->assertArrayNotHasKey('conversation_id', $arguments);

        // With nothing left locally it is still attempted: the backend may hold
        // more than this site has a pointer to.
        $adapter->toolcalls = [];
        $result = deletion_service::delete_all_for_user((int) $user->id, \core\context\system::instance(), $adapter);
        $this->assertSame(0, $result['localdeleted']);
        $this->assertSame(1, $result['externaldeleted']);
        $this->assertSame('deleteuser', $adapter->toolcalls[0][0]);
    }

    /**
     * Seed all five user-owned tables plus the LTM preference.
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

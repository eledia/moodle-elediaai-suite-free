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

use local_literag\local\conversation_repository;
use local_literag\local\http\transport;
use local_literag\local\llm\client;
use local_literag\local\mcp\moodle_client;
use local_literag\local\mcp\tools\tutor_chat;
use local_literag\local\tenant;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * End-to-end tests for the tutor_chat tool with a mocked LLM.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(tutor_chat::class)]
final class tutor_chat_test extends \advanced_testcase {
    /**
     * Isolate the application-cache rate counters between test methods.
     */
    protected function setUp(): void {
        parent::setUp();
        \cache::make('local_literag', 'tutorrate')->purge();
    }

    /**
     * A fake transport returning a canned chat completion and capturing the request.
     *
     * @param string $answer The assistant content to return.
     * @return transport
     */
    private function fake_llm(string $answer): transport {
        return new class ($answer) implements transport {
            /** @var string */
            public string $lastbody = '';
            /** @var string */
            private string $answer;
            /**
             * Constructor.
             *
             * @param string $answer Canned assistant answer to return.
             */
            public function __construct(string $answer) {
                $this->answer = $answer;
            }
            /**
             * Return the canned chat completion.
             *
             * @param string $url
             * @param array $headers
             * @param string $body
             * @param int $timeout
             * @return array
             */
            public function post(string $url, array $headers, string $body, int $timeout): array {
                $this->lastbody = $body;
                $payload = ['choices' => [['message' => ['role' => 'assistant', 'content' => $this->answer]]]];
                return ['status' => 200, 'body' => json_encode($payload), 'error' => ''];
            }
        };
    }

    /**
     * Mint a usable MCP token for a user.
     *
     * @param int $userid
     * @return string
     */
    private function mint_token(int $userid): string {
        global $DB;
        set_config('services', '1', 'webservice_elediamcp');
        $token = 'lt' . random_string(40);
        $DB->insert_record('external_tokens', (object) [
            'token' => $token, 'privatetoken' => null, 'tokentype' => 0, 'userid' => $userid,
            'externalserviceid' => 1, 'sid' => null, 'contextid' => \context_system::instance()->id,
            'creatorid' => $userid, 'iprestriction' => null, 'validuntil' => null,
            'timecreated' => time(), 'lastaccess' => null, 'name' => 'test',
        ]);
        return $token;
    }

    /**
     * Insert a chunk for a course module.
     *
     * @param int $courseid
     * @param int $cmid
     * @param string $url
     * @return void
     */
    private function insert_chunk(int $courseid, int $cmid, string $url): void {
        global $DB;
        $tenant = tenant::id();
        $text = 'Photosynthesis converts sunlight, carbon dioxide and water into glucose and oxygen.';
        $DB->insert_record('local_literag_chunks', (object) [
            'sourceid' => "$tenant:course$courseid:cmid$cmid", 'tenant' => $tenant,
            'courseid' => $courseid, 'contextid' => \context_module::instance($cmid)->id, 'cmid' => $cmid,
            'sourcetype' => 'text', 'sourcetitle' => 'Photosynthesis', 'moduleurl' => $url,
            'chunktext' => $text, 'chunkhash' => sha1($text), 'sortorder' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * A grounded chat turn returns an answer, a conversation id and module-url sources.
     */
    public function test_grounded_chat(): void {
        global $CFG;
        $this->resetAfterTest();
        set_config('llm_api_key', 'test-key', 'local_literag');
        set_config('enable_mcp_tools', 0, 'local_literag'); // These tests cover RAG, not live tools.

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $page = $gen->create_module('page', ['course' => $course->id]);
        $student = $gen->create_and_enrol($course, 'student');
        $url = $CFG->wwwroot . '/mod/page/view.php?id=' . $page->cmid;
        $this->insert_chunk((int) $course->id, (int) $page->cmid, $url);

        $token = $this->mint_token((int) $student->id);
        $handler = new tutor_chat(new client($this->fake_llm('Photosynthesis makes **glucose**. [S1]')));

        $result = $handler->handle([
            'system_url' => $CFG->wwwroot,
            'moodle_token' => $token,
            'user_message' => 'How does photosynthesis work?',
            'course_id' => (string) $course->id,
        ]);

        $this->assertFalse($result['isError']);
        $structured = $result['structuredContent'];
        $this->assertStringContainsString('glucose', $structured['answer']);
        $this->assertNotEmpty($structured['conversation_id']);
        $this->assertSame('rag', $structured['answer_origin']);
        $this->assertNotEmpty($structured['sources']);
        $this->assertSame($url, $structured['sources'][0]['url']);
    }

    /**
     * Several retrieved passages of the same module collapse to one source card.
     */
    /**
     * A fake transport that also reports token usage, like a real backend does.
     *
     * @param string $answer The assistant content to return.
     * @param int $prompt Prompt tokens to report per completion.
     * @param int $completion Completion tokens to report per completion.
     * @return transport
     */
    private function fake_llm_with_usage(string $answer, int $prompt, int $completion): transport {
        return new class ($answer, $prompt, $completion) implements transport {
            /** @var int Number of completions served. */
            public int $calls = 0;
            /** @var string */
            private string $answer;
            /** @var int */
            private int $prompt;
            /** @var int */
            private int $completion;
            /**
             * Constructor.
             *
             * @param string $answer Canned assistant answer.
             * @param int $prompt Prompt tokens per completion.
             * @param int $completion Completion tokens per completion.
             */
            public function __construct(string $answer, int $prompt, int $completion) {
                $this->answer = $answer;
                $this->prompt = $prompt;
                $this->completion = $completion;
            }
            /**
             * Return the canned completion including a usage block.
             *
             * @param string $url
             * @param array $headers
             * @param string $body
             * @param int $timeout
             * @return array
             */
            public function post(string $url, array $headers, string $body, int $timeout): array {
                $this->calls++;
                $payload = [
                    'choices' => [['message' => ['role' => 'assistant', 'content' => $this->answer]]],
                    'usage' => [
                        'prompt_tokens' => $this->prompt,
                        'completion_tokens' => $this->completion,
                    ],
                ];
                return ['status' => 200, 'body' => json_encode($payload), 'error' => ''];
            }
        };
    }

    /**
     * A backend that reports usage has it passed on to the tutor.
     *
     * The tutor books the turn on the Moodle side, so it needs the measured
     * counts; without them it can only estimate from the visible text.
     */
    public function test_usage_is_reported_when_backend_measures_it(): void {
        global $CFG;
        $this->resetAfterTest();
        set_config('llm_api_key', 'test-key', 'local_literag');
        set_config('enable_mcp_tools', 0, 'local_literag');

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $page = $gen->create_module('page', ['course' => $course->id]);
        $student = $gen->create_and_enrol($course, 'student');
        $this->insert_chunk(
            (int) $course->id,
            (int) $page->cmid,
            $CFG->wwwroot . '/mod/page/view.php?id=' . $page->cmid
        );
        $token = $this->mint_token((int) $student->id);

        $transport = $this->fake_llm_with_usage('Glucose. [S1]', 120, 30);
        $handler = new tutor_chat(new client($transport));
        $result = $handler->handle([
            'system_url' => $CFG->wwwroot, 'moodle_token' => $token,
            'user_message' => 'How does photosynthesis work?', 'course_id' => (string) $course->id,
        ]);

        $this->assertArrayHasKey('usage', $result['structuredContent']);
        // Every completion of the turn is counted, not just the last one.
        $this->assertSame(
            120 * $transport->calls,
            $result['structuredContent']['usage']['prompt_tokens']
        );
        $this->assertSame(
            30 * $transport->calls,
            $result['structuredContent']['usage']['completion_tokens']
        );
        $this->assertGreaterThan(0, $transport->calls);
    }

    /**
     * A backend that reports nothing omits the field entirely.
     *
     * Sending zeros would be worse than sending nothing: the tutor would book a
     * turn as free instead of falling back to its own estimate.
     */
    public function test_usage_omitted_when_backend_reports_none(): void {
        global $CFG;
        $this->resetAfterTest();
        set_config('llm_api_key', 'test-key', 'local_literag');
        set_config('enable_mcp_tools', 0, 'local_literag');

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $page = $gen->create_module('page', ['course' => $course->id]);
        $student = $gen->create_and_enrol($course, 'student');
        $this->insert_chunk(
            (int) $course->id,
            (int) $page->cmid,
            $CFG->wwwroot . '/mod/page/view.php?id=' . $page->cmid
        );
        $token = $this->mint_token((int) $student->id);

        $handler = new tutor_chat(new client($this->fake_llm('Glucose. [S1]')));
        $result = $handler->handle([
            'system_url' => $CFG->wwwroot, 'moodle_token' => $token,
            'user_message' => 'How does photosynthesis work?', 'course_id' => (string) $course->id,
        ]);

        // Reporting zeros would book the turn as free; omitting the field lets
        // the tutor fall back to its own estimate.
        $this->assertArrayNotHasKey('usage', $result['structuredContent']);
    }

    public function test_sources_deduplicated_by_document(): void {
        global $CFG;
        $this->resetAfterTest();
        set_config('llm_api_key', 'test-key', 'local_literag');
        set_config('enable_mcp_tools', 0, 'local_literag'); // These tests cover RAG, not live tools.

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $page = $gen->create_module('page', ['course' => $course->id]);
        $student = $gen->create_and_enrol($course, 'student');
        $url = $CFG->wwwroot . '/mod/page/view.php?id=' . $page->cmid;
        // Two passages (chunks) of the SAME module.
        $this->insert_chunk((int) $course->id, (int) $page->cmid, $url);
        $this->insert_chunk((int) $course->id, (int) $page->cmid, $url);
        $token = $this->mint_token((int) $student->id);

        $handler = new tutor_chat(new client($this->fake_llm('Glucose. [S1]')));
        $result = $handler->handle([
            'system_url' => $CFG->wwwroot, 'moodle_token' => $token,
            'user_message' => 'How does photosynthesis work?', 'course_id' => (string) $course->id,
        ]);

        $this->assertCount(1, $result['structuredContent']['sources']);
        $this->assertSame($url, $result['structuredContent']['sources'][0]['url']);
    }

    /**
     * A follow-up turn reuses the conversation and accumulates history.
     */
    public function test_followup_keeps_conversation(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        set_config('llm_api_key', 'test-key', 'local_literag');
        set_config('enable_mcp_tools', 0, 'local_literag'); // These tests cover RAG, not live tools.

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $page = $gen->create_module('page', ['course' => $course->id]);
        $student = $gen->create_and_enrol($course, 'student');
        $this->insert_chunk((int) $course->id, (int) $page->cmid, $CFG->wwwroot . '/mod/page/view.php?id=' . $page->cmid);
        $token = $this->mint_token((int) $student->id);
        $handler = new tutor_chat(new client($this->fake_llm('Answer. [S1]')));

        $first = $handler->handle([
            'system_url' => $CFG->wwwroot, 'moodle_token' => $token,
            'user_message' => 'Question one?', 'course_id' => (string) $course->id,
        ]);
        $convid = $first['structuredContent']['conversation_id'];

        $second = $handler->handle([
            'system_url' => $CFG->wwwroot, 'moodle_token' => $token,
            'user_message' => 'Question two?', 'course_id' => (string) $course->id,
            'conversation_id' => $convid,
        ]);

        $this->assertSame($convid, $second['structuredContent']['conversation_id']);
        $conv = $DB->get_record('local_literag_conversations', ['convkey' => $convid]);
        $this->assertSame(4, (int) $DB->count_records('local_literag_messages', ['conversationid' => $conv->id]));
    }

    /**
     * Citations survive a history reload as STRUCTURED sources: the stored answer
     * stays clean (no inline footer) and tutor_get_history returns the same
     * {title,url,snippet} sources the block renders as cards.
     */
    public function test_resumed_history_carries_sources(): void {
        global $CFG;
        $this->resetAfterTest();
        set_config('llm_api_key', 'test-key', 'local_literag');
        set_config('enable_mcp_tools', 0, 'local_literag'); // These tests cover RAG, not live tools.

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $page = $gen->create_module('page', ['course' => $course->id]);
        $student = $gen->create_and_enrol($course, 'student');
        $url = $CFG->wwwroot . '/mod/page/view.php?id=' . $page->cmid;
        $this->insert_chunk((int) $course->id, (int) $page->cmid, $url);
        $token = $this->mint_token((int) $student->id);

        $first = new tutor_chat(new client($this->fake_llm('Photosynthesis makes glucose. [S1]')));
        $result = $first->handle([
            'system_url' => $CFG->wwwroot, 'moodle_token' => $token,
            'user_message' => 'How does photosynthesis work?', 'course_id' => (string) $course->id,
        ]);
        $this->assertNotEmpty($result['structuredContent']['sources']);

        // Reloading history (what the block does after a refresh) returns the same
        // structured sources, and the stored answer carries no inline footer.
        $history = (new \local_literag\local\mcp\tools\tutor_get_history())->handle([
            'system_url' => $CFG->wwwroot, 'moodle_token' => $token,
            'conversation_id' => $result['structuredContent']['conversation_id'],
        ]);
        $assistant = null;
        foreach ($history['structuredContent']['messages'] as $m) {
            if ($m['role'] === 'assistant') {
                $assistant = $m;
            }
        }
        $this->assertNotNull($assistant);
        $this->assertStringNotContainsStringIgnoringCase('Sources', $assistant['content']);
        $this->assertNotEmpty($assistant['sources']);
        $this->assertSame($url, $assistant['sources'][0]['url']);
    }

    /**
     * LLM-only mode (rag_enabled=false) skips retrieval and returns no sources.
     */
    public function test_llm_only_mode_has_no_sources(): void {
        global $CFG;
        $this->resetAfterTest();
        set_config('llm_api_key', 'test-key', 'local_literag');
        set_config('enable_mcp_tools', 0, 'local_literag'); // These tests cover RAG, not live tools.

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $page = $gen->create_module('page', ['course' => $course->id]);
        $student = $gen->create_and_enrol($course, 'student');
        $this->insert_chunk((int) $course->id, (int) $page->cmid, $CFG->wwwroot . '/mod/page/view.php?id=' . $page->cmid);
        $token = $this->mint_token((int) $student->id);
        $handler = new tutor_chat(new client($this->fake_llm('General knowledge answer.')));

        $result = $handler->handle([
            'system_url' => $CFG->wwwroot, 'moodle_token' => $token,
            'user_message' => 'How does photosynthesis work?',
            'course_id' => (string) $course->id, 'rag_enabled' => false,
        ]);

        $this->assertFalse($result['isError']);
        $this->assertSame('general', $result['structuredContent']['answer_origin']);
        $this->assertArrayNotHasKey('sources', $result['structuredContent']);
    }

    /**
     * The 'action' intent skips retrieval even in grounded mode: no sources,
     * and the answer is never labelled 'rag' (tools would flip it to 'mcp').
     */
    public function test_action_intent_skips_retrieval(): void {
        global $CFG;
        $this->resetAfterTest();
        set_config('llm_api_key', 'test-key', 'local_literag');
        set_config('enable_mcp_tools', 0, 'local_literag');

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $page = $gen->create_module('page', ['course' => $course->id]);
        $student = $gen->create_and_enrol($course, 'student');
        $this->insert_chunk((int) $course->id, (int) $page->cmid, $CFG->wwwroot . '/mod/page/view.php?id=' . $page->cmid);
        $token = $this->mint_token((int) $student->id);
        $handler = new tutor_chat(new client($this->fake_llm('I will create the course for you.')));

        $result = $handler->handle([
            'system_url' => $CFG->wwwroot, 'moodle_token' => $token,
            'user_message' => 'Please create a course.',
            'course_id' => (string) $course->id, 'intent' => 'action',
        ]);

        $this->assertFalse($result['isError']);
        $this->assertNotSame('rag', $result['structuredContent']['answer_origin']);
        $this->assertArrayNotHasKey('sources', $result['structuredContent']);
    }

    /**
     * An unknown intent value falls back to 'auto' (current behaviour intact).
     */
    public function test_unknown_intent_behaves_like_auto(): void {
        global $CFG;
        $this->resetAfterTest();
        set_config('llm_api_key', 'test-key', 'local_literag');
        set_config('enable_mcp_tools', 0, 'local_literag');

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $page = $gen->create_module('page', ['course' => $course->id]);
        $student = $gen->create_and_enrol($course, 'student');
        $this->insert_chunk((int) $course->id, (int) $page->cmid, $CFG->wwwroot . '/mod/page/view.php?id=' . $page->cmid);
        $token = $this->mint_token((int) $student->id);
        $handler = new tutor_chat(new client($this->fake_llm('Grounded answer. [S1]')));

        $result = $handler->handle([
            'system_url' => $CFG->wwwroot, 'moodle_token' => $token,
            'user_message' => 'How does photosynthesis work?',
            'course_id' => (string) $course->id, 'intent' => 'bogus',
        ]);

        $this->assertSame('rag', $result['structuredContent']['answer_origin']);
        $this->assertNotEmpty($result['structuredContent']['sources']);
    }

    /**
     * An invalid token surfaces as a tool_exception (JSON-RPC error to the block).
     */
    public function test_invalid_token_throws(): void {
        global $CFG;
        $this->resetAfterTest();
        set_config('llm_api_key', 'test-key', 'local_literag');
        set_config('enable_mcp_tools', 0, 'local_literag'); // These tests cover RAG, not live tools.
        $handler = new tutor_chat(new client($this->fake_llm('x')));

        $this->expectException(\local_literag\local\mcp\tool_exception::class);
        $handler->handle([
            'system_url' => $CFG->wwwroot,
            'moodle_token' => 'invalid-token',
            'user_message' => 'Hello',
        ]);
    }

    /**
     * Overlong learner messages are rejected before prompt construction.
     */
    public function test_overlong_user_message_throws(): void {
        global $CFG;
        $this->resetAfterTest();
        set_config('llm_api_key', 'test-key', 'local_literag');
        set_config('enable_mcp_tools', 0, 'local_literag');

        $user = $this->getDataGenerator()->create_user();
        $token = $this->mint_token((int) $user->id);
        $handler = new tutor_chat(new client($this->fake_llm('x')));

        $this->expectException(\local_literag\local\mcp\tool_exception::class);
        $this->expectExceptionMessage('user_message too long');
        $handler->handle([
            'system_url' => $CFG->wwwroot,
            'moodle_token' => $token,
            'user_message' => str_repeat('x', 4001),
        ]);
    }

    /**
     * Request-supplied system_url is ignored for live Moodle tools.
     */
    public function test_live_tools_use_cfg_wwwroot_not_request_system_url(): void {
        global $CFG;

        $this->resetAfterTest();
        set_config('llm_api_key', 'test-key', 'local_literag');
        set_config('enable_mcp_tools', 1, 'local_literag');
        set_config('enable_write_tools', 0, 'local_literag');

        $user = $this->getDataGenerator()->create_user();
        $token = $this->mint_token((int) $user->id);
        $mcpresponse = json_encode(['jsonrpc' => '2.0', 'id' => 1, 'result' => [
            'content' => [['type' => 'text', 'text' => 'ok']],
            'structuredContent' => ['summary' => 'Test user'],
            'isError' => false,
            'tools' => [],
        ]]);
        $mcpt = $this->fake_json_transport($mcpresponse);
        $handler = new class (new client($this->fake_llm('Plain answer.')), $mcpt) extends tutor_chat {
            /** @var string Captured system URL used to build the MCP client. */
            public string $capturedsystemurl = '';

            /** @var transport Fake MCP transport. */
            private transport $transport;

            /**
             * Constructor.
             *
             * @param client $llm Fake LLM client.
             * @param transport $transport Fake MCP transport.
             */
            public function __construct(client $llm, transport $transport) {
                parent::__construct($llm);
                $this->transport = $transport;
            }

            /**
             * Capture the URL and return a fake MCP client.
             *
             * @param string $systemurl Canonical Moodle wwwroot.
             * @param string $moodletoken User-scoped MCP token.
             * @return moodle_client
             */
            protected function moodle_client(string $systemurl, string $moodletoken): moodle_client {
                $this->capturedsystemurl = $systemurl;
                return new moodle_client($systemurl, $moodletoken, $this->transport);
            }
        };

        $result = $handler->handle([
            'system_url' => 'https://evil.example',
            'moodle_token' => $token,
            'user_message' => 'Hello',
            'rag_enabled' => true,
        ]);

        $this->assertFalse($result['isError']);
        $this->assertSame($CFG->wwwroot, $handler->capturedsystemurl);
        $this->assertCount(2, $mcpt->bodies); // Verify user context + tools/list.
    }

    /**
     * A transport that always replies with one JSON body and records request bodies.
     *
     * @param string $body Response body.
     * @return transport
     */
    private function fake_json_transport(string $body): transport {
        return new class ($body) implements transport {
            /** @var string Canned response body. */
            private string $body;
            /** @var string[] Captured request bodies. */
            public array $bodies = [];
            /**
             * Constructor.
             *
             * @param string $body Canned response body.
             */
            public function __construct(string $body) {
                $this->body = $body;
            }
            /**
             * Record the request and return the canned response.
             *
             * @param string $url
             * @param array $headers
             * @param string $body
             * @param int $timeout
             * @return array
             */
            public function post(string $url, array $headers, string $body, int $timeout): array {
                $this->bodies[] = $body;
                return ['status' => 200, 'body' => $this->body, 'error' => ''];
            }
        };
    }

    /**
     * On an explicit "yes", a pending message is actually sent (confirm=true) and cleared.
     */
    public function test_confirmation_sends_pending(): void {
        global $CFG;
        $this->resetAfterTest();
        set_config('llm_api_key', 'test-key', 'local_literag');
        set_config('enable_write_tools', 1, 'local_literag');

        $user = $this->getDataGenerator()->create_user();
        $token = $this->mint_token((int) $user->id);
        $repo = new conversation_repository();
        $conv = $repo->create((int) $user->id, 0, 'explain');
        $repo->set_pending_action($conv, ['tool' => 'moodle_send_message',
            'arguments' => ['to_user_id' => 42, 'message' => 'I created this tutor.']]);

        $sendresult = json_encode(['jsonrpc' => '2.0', 'id' => 1, 'result' => [
            'content' => [['type' => 'text', 'text' => 'sent']],
            'structuredContent' => ['sent' => true, 'recipient' => ['id' => 42, 'fullname' => 'Erika'],
                'message_id' => 99, 'summary' => 'Message sent to Erika (id 42).'],
            'isError' => false,
        ]]);
        $mcpt = $this->fake_json_transport($sendresult);
        $handler = new tutor_chat(
            new client($this->fake_llm('Done — I let Erika know.')),
            null,
            new moodle_client('https://x', $token, $mcpt)
        );

        $result = $handler->handle(['system_url' => $CFG->wwwroot, 'moodle_token' => $token,
            'user_message' => 'yes', 'conversation_id' => $conv->convkey]);

        $this->assertFalse($result['isError']);
        $this->assertSame('Done — I let Erika know.', $result['structuredContent']['answer']);
        $this->assertStringContainsString('moodle_send_message', $mcpt->bodies[0]);
        $this->assertStringContainsString('"confirm":true', $mcpt->bodies[0]); // Sent for real.
        $reloaded = $repo->find_owned($conv->convkey, (int) $user->id);
        $this->assertNull($repo->get_pending_action($reloaded)); // Consumed.
    }

    /**
     * A confirmed generic Moodle write action is replayed with confirm=true.
     */
    public function test_confirmation_runs_pending_course_creation(): void {
        global $CFG;
        $this->resetAfterTest();
        set_config('llm_api_key', 'test-key', 'local_literag');
        set_config('enable_write_tools', 1, 'local_literag');

        $user = $this->getDataGenerator()->create_user();
        $token = $this->mint_token((int) $user->id);
        $repo = new conversation_repository();
        $conv = $repo->create((int) $user->id, 0, 'explain');
        $repo->set_pending_action($conv, ['tool' => 'moodle_create_course', 'arguments' => [
            'fullname' => 'Introduction to Statistics',
            'shortname' => 'STAT101',
            'category_id' => 1,
        ]]);

        $createresult = json_encode(['jsonrpc' => '2.0', 'id' => 1, 'result' => [
            'content' => [['type' => 'text', 'text' => 'created']],
            'structuredContent' => ['created' => true, 'requires_confirmation' => false,
                'course' => ['id' => 7, 'shortname' => 'STAT101',
                    'url' => 'https://example.test/course/view.php?id=7'],
                'summary' => 'Created Moodle course Introduction to Statistics (STAT101).'],
            'isError' => false,
        ]]);
        $mcpt = $this->fake_json_transport($createresult);
        $handler = new tutor_chat(
            new client($this->fake_llm('Done — I created the course.')),
            null,
            new moodle_client('https://x', $token, $mcpt)
        );

        $result = $handler->handle(['system_url' => $CFG->wwwroot, 'moodle_token' => $token,
            'user_message' => 'Ja!', 'conversation_id' => $conv->convkey]);

        $this->assertFalse($result['isError']);
        $this->assertSame('mcp', $result['structuredContent']['answer_origin']);
        $this->assertStringContainsString('Done — I created the course.', $result['structuredContent']['answer']);
        $this->assertStringContainsString(
            '[Kurs öffnen](https://example.test/course/view.php?id=7)',
            $result['structuredContent']['answer']
        );
        $this->assertStringContainsString('moodle_create_course', $mcpt->bodies[0]);
        $this->assertStringContainsString('"confirm":true', $mcpt->bodies[0]);
        $this->assertStringContainsString('STAT101', $mcpt->bodies[0]);
        $reloaded = $repo->find_owned($conv->convkey, (int) $user->id);
        $this->assertNull($repo->get_pending_action($reloaded));
    }

    /**
     * A non-affirmative reply abandons the pending send (nothing is sent, pending cleared).
     */
    public function test_non_affirmative_clears_pending(): void {
        global $CFG;
        $this->resetAfterTest();
        set_config('llm_api_key', 'test-key', 'local_literag');
        set_config('enable_write_tools', 1, 'local_literag');
        set_config('enable_mcp_tools', 0, 'local_literag'); // Keep the fall-through a plain answer.

        $user = $this->getDataGenerator()->create_user();
        $token = $this->mint_token((int) $user->id);
        $repo = new conversation_repository();
        $conv = $repo->create((int) $user->id, 0, 'explain');
        $repo->set_pending_action($conv, ['tool' => 'moodle_send_message',
            'arguments' => ['to_user_id' => 42, 'message' => 'hi']]);

        $mcpt = $this->fake_json_transport('{}');
        $handler = new tutor_chat(
            new client($this->fake_llm('Here is the deadline information.')),
            null,
            new moodle_client('https://x', $token, $mcpt)
        );

        $result = $handler->handle(['system_url' => $CFG->wwwroot, 'moodle_token' => $token,
            'user_message' => 'actually, when is the essay due?', 'conversation_id' => $conv->convkey]);

        $this->assertFalse($result['isError']);
        $this->assertCount(0, $mcpt->bodies); // Nothing sent.
        $reloaded = $repo->find_owned($conv->convkey, (int) $user->id);
        $this->assertNull($repo->get_pending_action($reloaded)); // Abandoned.
    }

    /**
     * A turn sent with moodle_tools_enabled=false calls no live Moodle tool.
     *
     * The site setting says the tools exist here; the request says this surface
     * may not use them. Not even moodle_verify_user_context is called: the token
     * was enough to say who is asking, and this argument says the answer must
     * not act for them. The turn still produces an answer.
     */
    public function test_moodle_tools_disabled_by_request_skips_live_tools(): void {
        global $CFG;

        $this->resetAfterTest();
        set_config('llm_api_key', 'test-key', 'local_literag');
        set_config('enable_mcp_tools', 1, 'local_literag');

        $user = $this->getDataGenerator()->create_user();
        $token = $this->mint_token((int) $user->id);
        $mcpt = $this->fake_json_transport(json_encode(['jsonrpc' => '2.0', 'id' => 1, 'result' => [
            'content' => [['type' => 'text', 'text' => 'ok']],
            'structuredContent' => ['summary' => 'Test user'],
            'isError' => false,
            'tools' => [],
        ]]));
        $handler = new tutor_chat(
            new client($this->fake_llm('Plain answer.')),
            null,
            new moodle_client('https://x', $token, $mcpt)
        );

        $result = $handler->handle([
            'system_url' => $CFG->wwwroot,
            'moodle_token' => $token,
            'user_message' => 'Hello',
            'rag_enabled' => true,
            'moodle_tools_enabled' => false,
        ]);

        $this->assertFalse($result['isError']);
        $this->assertSame('Plain answer.', $result['structuredContent']['answer']);
        $this->assertCount(0, $mcpt->bodies);
    }

    /**
     * A client that predates the argument keeps the tools it always had.
     *
     * Absence is not a ban -- otherwise this release would silently switch the
     * live tools off on every site whose chat client has not been updated yet.
     */
    public function test_absent_moodle_tools_argument_leaves_live_tools_on(): void {
        global $CFG;

        $this->resetAfterTest();
        set_config('llm_api_key', 'test-key', 'local_literag');
        set_config('enable_mcp_tools', 1, 'local_literag');

        $user = $this->getDataGenerator()->create_user();
        $token = $this->mint_token((int) $user->id);
        $mcpt = $this->fake_json_transport(json_encode(['jsonrpc' => '2.0', 'id' => 1, 'result' => [
            'content' => [['type' => 'text', 'text' => 'ok']],
            'structuredContent' => ['summary' => 'Test user'],
            'isError' => false,
            'tools' => [],
        ]]));
        $handler = new tutor_chat(
            new client($this->fake_llm('Plain answer.')),
            null,
            new moodle_client('https://x', $token, $mcpt)
        );

        $result = $handler->handle([
            'system_url' => $CFG->wwwroot,
            'moodle_token' => $token,
            'user_message' => 'Hello',
            'rag_enabled' => true,
        ]);

        $this->assertFalse($result['isError']);
        $this->assertCount(2, $mcpt->bodies); // Verify user context + tools/list.
    }

    /**
     * A pending write is not carried out once the caller has withdrawn the tools.
     *
     * The preview was created on a turn that still allowed them and outlives that
     * turn by one; without this gate the confirmation would execute in Moodle
     * exactly what the surface has since been forbidden to do.
     */
    public function test_moodle_tools_disabled_by_request_blocks_pending_write(): void {
        global $CFG;

        $this->resetAfterTest();
        set_config('llm_api_key', 'test-key', 'local_literag');
        set_config('enable_write_tools', 1, 'local_literag');
        set_config('enable_mcp_tools', 1, 'local_literag');

        $user = $this->getDataGenerator()->create_user();
        $token = $this->mint_token((int) $user->id);
        $repo = new conversation_repository();
        $conv = $repo->create((int) $user->id, 0, 'explain');
        $repo->set_pending_action($conv, ['tool' => 'moodle_send_message',
            'arguments' => ['to_user_id' => 42, 'message' => 'I created this tutor.']]);

        $mcpt = $this->fake_json_transport(json_encode(['jsonrpc' => '2.0', 'id' => 1, 'result' => [
            'content' => [['type' => 'text', 'text' => 'sent']],
            'structuredContent' => ['sent' => true],
            'isError' => false,
            'tools' => [],
        ]]));
        $handler = new tutor_chat(
            new client($this->fake_llm('Plain answer.')),
            null,
            new moodle_client('https://x', $token, $mcpt)
        );

        $result = $handler->handle([
            'system_url' => $CFG->wwwroot,
            'moodle_token' => $token,
            'user_message' => 'yes',
            'conversation_id' => $conv->convkey,
            'moodle_tools_enabled' => false,
        ]);

        $this->assertFalse($result['isError']);
        $this->assertCount(0, $mcpt->bodies); // Nothing sent.
        $reloaded = $repo->find_owned($conv->convkey, (int) $user->id);
        $this->assertNull($repo->get_pending_action($reloaded)); // Still consumed.
    }
}

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

use local_elediaai_chatengine\adapter\adapter_exception;
use local_elediaai_chatengine\adapter\backend_refusal;
use local_elediaai_chatengine\local\http\transport;
use local_elediaai_chatengine\local\mcp_client;

/**
 * How the agent's failures reach the engine (#39).
 *
 * The bodies are the agent's documented error formats: a protocol error as a
 * JSON-RPC `error`, a business error as an MCP tool result with `isError` and
 * `structuredContent.error`, and the same during a stream as `event: error`.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(mcp_client::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(backend_refusal::class)]
final class mcp_client_test extends \advanced_testcase {
    /**
     * A client whose transport answers with one canned response.
     *
     * @param array $response The transport response.
     * @return mcp_client
     */
    private function client(array $response): mcp_client {
        $transport = new class ($response) implements transport {
            /**
             * Constructor.
             *
             * @param array $response The canned response.
             */
            public function __construct(
                /** @var array The canned response. */
                private array $response
            ) {
            }

            #[\Override]
            public function post(string $url, array $headers, string $body, int $timeout): array {
                return $this->response + ['status' => 0, 'headers' => [], 'body' => '', 'error' => ''];
            }

            #[\Override]
            public function post_streaming(string $url, array $headers, string $body, int $timeout, callable $onchunk): array {
                return $this->post($url, $headers, $body, $timeout);
            }
        };

        return new mcp_client(new \moodle_url('https://agent.example.com/mcp'), [], $transport, 120);
    }

    /**
     * A business error in the documented shape.
     *
     * @param string $code The error code.
     * @param string $answer The message for the learner.
     * @param bool $retryable Whether a retry can help.
     * @param int $status The HTTP status inside the body.
     * @param int $retryafter Seconds to wait.
     * @return string JSON body.
     */
    private static function business_error(
        string $code,
        string $answer,
        bool $retryable,
        int $status,
        int $retryafter = 0
    ): string {
        return json_encode(['jsonrpc' => '2.0', 'id' => 1, 'result' => [
            'isError' => true,
            'content' => [['type' => 'text', 'text' => $answer]],
            'structuredContent' => [
                'answer' => $answer,
                'error' => [
                    'code' => $code,
                    'http_status' => $status,
                    'retryable' => $retryable,
                    'retry_after' => $retryafter,
                ],
            ],
        ]]);
    }

    /**
     * An unregistered site is told so, and nobody offers a retry.
     */
    public function test_business_error_is_passed_on(): void {
        $this->resetAfterTest();
        $answer = 'Der KI-Tutor ist für diese Moodle-Instanz nicht eingerichtet.';
        $client = $this->client([
            'status' => 403,
            'body' => self::business_error('tenant_not_found', $answer, false, 403),
        ]);

        try {
            $client->call_tool('tutor_chat', []);
            $this->fail('A refusal was expected.');
        } catch (backend_refusal $e) {
            $this->assertSame('tenant_not_found', $e->backend_code());
            $this->assertSame('error_backend_refused', $e->errorcode);
            // The message parameter: getMessage() adds the debug info under developer debugging.
            $this->assertSame($answer, $e->a);
            $this->assertFalse($e->is_retryable());
            // A 403 refusal is about the site, not about the token.
            $this->assertFalse($e->is_auth_failure());
        }
    }

    /**
     * A rate limit stays retryable and keeps its waiting time from the header.
     */
    public function test_retryable_error_carries_its_wait(): void {
        $this->resetAfterTest();
        $client = $this->client([
            'status' => 429,
            'headers' => ['Retry-After' => '30'],
            'body' => self::business_error('rate_limited', 'Bitte warten Sie 30 Sekunden.', true, 429),
        ]);

        try {
            $client->call_tool('tutor_chat', []);
            $this->fail('A refusal was expected.');
        } catch (backend_refusal $e) {
            $this->assertSame('error_backend_busy', $e->errorcode);
            $this->assertTrue($e->is_retryable());
            $this->assertSame(30, $e->retry_after());
        }
    }

    /**
     * A failure during a stream comes with status 200 and the real one in the body.
     */
    public function test_error_event_in_stream_is_a_refusal(): void {
        $this->resetAfterTest();
        $body = "event: error\ndata: " . self::business_error('timeout', 'Die Anfrage hat zu lange gedauert.', true, 504)
            . "\n\nevent: done\ndata: [DONE]\n\n";
        $client = $this->client([
            'status' => 200,
            'headers' => ['Content-Type' => 'text/event-stream'],
            'body' => $body,
        ]);

        try {
            $client->call_tool('tutor_chat', []);
            $this->fail('A refusal was expected.');
        } catch (backend_refusal $e) {
            $this->assertSame('timeout', $e->backend_code());
            $this->assertSame(504, $e->http_status());
            $this->assertSame('Die Anfrage hat zu lange gedauert.', $e->a);
        }
    }

    /**
     * A protocol error stays generic for the learner, with the reason for administrators.
     */
    public function test_protocol_error_stays_generic(): void {
        $this->resetAfterTest();
        $client = $this->client([
            'status' => 400,
            'body' => json_encode(['jsonrpc' => '2.0', 'id' => 1, 'error' => [
                'code' => -32602,
                'message' => 'Missing required argument: system_url',
            ]]),
        ]);

        try {
            $client->call_tool('tutor_chat', []);
            $this->fail('An exception was expected.');
        } catch (backend_refusal $e) {
            $this->fail('A protocol error is not written for the learner.');
        } catch (adapter_exception $e) {
            $this->assertSame('error_backend_unavailable', $e->errorcode);
            $this->assertStringContainsString('-32602: Missing required argument: system_url', (string) $e->debuginfo);
            $this->assertFalse($e->is_auth_failure());
        }
    }

    /**
     * An expired token is still the one failure a fresh credential cures.
     */
    public function test_rejected_token_is_an_auth_failure(): void {
        $this->resetAfterTest();
        $client = $this->client([
            'status' => 401,
            'body' => json_encode(['jsonrpc' => '2.0', 'id' => 1, 'error' => [
                'code' => -32000,
                'message' => 'Authentication failed: token invalid or expired',
            ]]),
        ]);

        try {
            $client->call_tool('tutor_chat', []);
            $this->fail('An exception was expected.');
        } catch (adapter_exception $e) {
            $this->assertNotInstanceOf(backend_refusal::class, $e);
            $this->assertTrue($e->is_auth_failure());
        }
    }

    /**
     * A timeout says that it was one, and how long was waited.
     */
    public function test_timeout_names_the_limit(): void {
        $this->resetAfterTest();
        $client = $this->client(['error' => 'Operation timed out after 120001 milliseconds', 'errno' => 28]);

        try {
            $client->call_tool('tutor_chat', []);
            $this->fail('An exception was expected.');
        } catch (adapter_exception $e) {
            $this->assertSame('error_backend_timeout', $e->errorcode);
            $this->assertStringContainsString('120', $e->getMessage());
        }
    }

    /**
     * A tool error without the catalogue block stays an answer flagged as failed.
     */
    public function test_plain_tool_error_is_still_returned(): void {
        $this->resetAfterTest();
        $client = $this->client([
            'status' => 200,
            'body' => json_encode(['jsonrpc' => '2.0', 'id' => 1, 'result' => [
                'isError' => true,
                'content' => [['type' => 'text', 'text' => 'Werkzeug fehlgeschlagen']],
            ]]),
        ]);

        $result = $client->call_tool('tutor_chat', []);
        $this->assertTrue($result['isError']);
    }

    /**
     * Markup in a backend message does not reach the chat window.
     */
    public function test_refusal_message_is_plain_text(): void {
        $this->resetAfterTest();
        $client = $this->client([
            'status' => 402,
            'body' => self::business_error('llm_budget_exceeded', '<b>Kontingent</b> aufgebraucht<script>x()</script>', false, 402),
        ]);

        try {
            $client->call_tool('tutor_chat', []);
            $this->fail('A refusal was expected.');
        } catch (backend_refusal $e) {
            $this->assertStringNotContainsString('<', $e->a);
            $this->assertStringContainsString('Kontingent', $e->a);
        }
    }

    /**
     * A course outline on a confirmation reaches the card as bounded plain text (#79).
     */
    public function test_confirmation_outline_is_normalised(): void {
        $this->resetAfterTest();
        $result = mcp_client::normalise_tool_result(['structuredContent' => [
            'answer' => 'Soll ich den Kurs anlegen?',
            'confirmation' => [
                'required' => true,
                'outline' => [
                    'title' => 'Statistik <b>kompakt</b>',
                    'sections' => [
                        ['title' => 'Daten beschreiben', 'summary' => 'Mittelwert und Streuung'],
                        ['title' => ''],
                        'kein Abschnitt',
                        ['title' => 'Wahrscheinlichkeit<script>x()</script>'],
                    ],
                ],
            ],
        ]]);

        $outline = $result['confirmation']['outline'];
        $this->assertSame('Statistik kompakt', $outline['title']);
        $this->assertCount(2, $outline['sections']);
        $this->assertSame('Daten beschreiben', $outline['sections'][0]['title']);
        $this->assertSame('Mittelwert und Streuung', $outline['sections'][0]['summary']);
        $this->assertStringNotContainsString('<', $outline['sections'][1]['title']);
        $this->assertSame('', $outline['sections'][1]['summary']);
    }

    /**
     * A confirmation without an outline has none, rather than an empty one.
     */
    public function test_confirmation_without_outline(): void {
        $this->resetAfterTest();
        $result = mcp_client::normalise_tool_result(['structuredContent' => [
            'answer' => 'Nachricht senden?',
            'confirmation' => ['required' => true, 'outline' => ['sections' => []]],
        ]]);

        $this->assertNull($result['confirmation']['outline']);
    }
}

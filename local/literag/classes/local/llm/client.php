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

namespace local_literag\local\llm;

use local_literag\local\config;
use local_literag\local\http\curl_transport;
use local_literag\local\http\transport;

/**
 * OpenAI-compatible Chat Completions client.
 *
 * Provider-agnostic: the same client works against api.openai.com/v1 and a
 * LiteLLM proxy. Buffers the full completion and returns it (the tutor block
 * only consumes a final answer, so token streaming buys nothing).
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class client {
    /** @var transport HTTP transport. */
    private transport $transport;

    /**
     * Constructor.
     *
     * @param transport|null $transport Optional override for testing.
     */
    public function __construct(?transport $transport = null) {
        $this->transport = $transport ?? new curl_transport(config::llm_allow_private());
    }

    /**
     * Run a chat completion and return the assistant's message content.
     *
     * @param array $messages OpenAI-style messages: [{role, content}, ...].
     * @param string|null $model Optional model override (e.g. the rerank model).
     * @param int|null $maxtokens Optional max-tokens override.
     * @return string The assistant message content (Markdown).
     * @throws llm_exception On configuration, transport or protocol failure.
     */
    public function chat(array $messages, ?string $model = null, ?int $maxtokens = null): string {
        $result = $this->chat_with_usage($messages, $model, $maxtokens);
        return $result['content'];
    }

    /**
     * Same as chat(), but also returns the measured token usage.
     *
     * chat() keeps its string return so existing callers stay untouched; callers
     * that settle quota use this variant and add the usage up over the turn.
     *
     * @param array $messages Chat messages.
     * @param string|null $model Optional model override.
     * @param int|null $maxtokens Optional max-tokens override.
     * @return array{content: string, usage: array{prompttokens: ?int, completiontokens: ?int}}
     * @throws llm_exception When the completion is empty.
     */
    public function chat_with_usage(
        array $messages,
        ?string $model = null,
        ?int $maxtokens = null
    ): array {
        $result = $this->complete($messages, [], $model, $maxtokens);
        $content = $result['content'];
        if (!is_string($content) || trim($content) === '') {
            throw new llm_exception('empty completion');
        }
        return ['content' => $content, 'usage' => $result['usage']];
    }

    /**
     * Read the token counts from a backend response.
     *
     * Accepts the OpenAI-style names and the variants LiteLLM proxies emit; a
     * missing or unusable value stays null so the caller can fall back to an
     * estimate rather than booking a wrong number.
     *
     * @param array $decoded The decoded response body.
     * @return array{prompttokens: ?int, completiontokens: ?int}
     */
    private static function read_usage(array $decoded): array {
        $usage = (isset($decoded['usage']) && is_array($decoded['usage'])) ? $decoded['usage'] : [];
        return [
            'prompttokens' => self::first_int($usage, ['prompt_tokens', 'prompttokens', 'input_tokens']),
            'completiontokens' => self::first_int($usage, ['completion_tokens', 'completiontokens', 'output_tokens']),
        ];
    }

    /**
     * First usable non-negative integer among the given keys.
     *
     * @param array $source The array to read from.
     * @param string[] $keys Candidate keys in order of preference.
     * @return int|null The value, or null when none is usable.
     */
    private static function first_int(array $source, array $keys): ?int {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $source)) {
                continue;
            }
            $value = $source[$key];
            if (is_numeric($value) && (int) $value >= 0) {
                return (int) $value;
            }
        }
        return null;
    }

    /**
     * Run a chat completion that may use tools (OpenAI function calling).
     *
     * Returns the assistant message's content (may be null on a tool-call turn),
     * any tool calls it requested, and the raw assistant message (which the caller
     * must append verbatim before the corresponding tool results).
     *
     * @param array $messages OpenAI-style messages.
     * @param array $tools OpenAI tool definitions; empty for a plain completion.
     * @param string|null $model Optional model override.
     * @param int|null $maxtokens Optional max-tokens override.
     * @return array{content: ?string, toolcalls: array, message: array}
     * @throws llm_exception On configuration, transport or protocol failure.
     */
    public function complete(array $messages, array $tools = [], ?string $model = null, ?int $maxtokens = null): array {
        $apikey = config::llm_api_key();
        if ($apikey === '') {
            throw new llm_exception('LLM API key is not configured');
        }

        $payload = [
            'model' => $model ?? config::llm_model(),
            'messages' => array_values($messages),
            'temperature' => config::llm_temperature(),
            'max_tokens' => $maxtokens ?? config::llm_max_tokens(),
            'stream' => false,
        ];
        if (!empty($tools)) {
            $payload['tools'] = array_values($tools);
            $payload['tool_choice'] = 'auto';
        }

        $url = config::llm_base_url() . '/chat/completions';
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apikey,
        ];
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $response = $this->transport->post($url, $headers, (string) $body, config::llm_timeout());

        if ($response['error'] !== '') {
            throw new llm_exception('transport error: ' . $response['error']);
        }
        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new llm_exception('http status ' . $response['status']);
        }

        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            throw new llm_exception('invalid JSON from LLM');
        }
        if (isset($decoded['error'])) {
            $message = is_array($decoded['error']) ? ($decoded['error']['message'] ?? 'unknown') : (string) $decoded['error'];
            throw new llm_exception('LLM error: ' . $message);
        }

        $message = $decoded['choices'][0]['message'] ?? null;
        if (!is_array($message)) {
            throw new llm_exception('missing message in completion');
        }

        return [
            'content' => (isset($message['content']) && is_string($message['content'])) ? $message['content'] : null,
            'toolcalls' => (isset($message['tool_calls']) && is_array($message['tool_calls'])) ? $message['tool_calls'] : [],
            'message' => $message,
            // Measured token counts as reported by the backend. Callers add these
            // up across every completion of a turn, so Moodle's quota reflects
            // what was really consumed instead of an estimate made from the
            // visible question and answer (see the chatengine's rag_server_spec, A.1).
            'usage' => self::read_usage($decoded),
        ];
    }
}

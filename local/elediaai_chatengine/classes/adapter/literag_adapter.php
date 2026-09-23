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

namespace local_elediaai_chatengine\adapter;

use local_elediaai_chatengine\local\mcp_client;
use local_elediaai_chatengine\local\token_provider;

/**
 * LiteRAG, running inside this Moodle site.
 *
 * LiteRAG serves the same tool catalogue as the external agent, so the two
 * adapters differ in transport rather than in protocol: this one hands the
 * JSON-RPC request straight to LiteRAG's dispatcher instead of sending it over
 * the network. Going in-process avoids a site calling itself over HTTP, which
 * would need a second credential and would fail on every host whose own name
 * does not resolve from inside its container.
 *
 * Tool names come from LiteRAG's own configuration: its dispatcher resolves
 * handlers against `local_literag` settings, so asking the engine's setting
 * here would let the two drift apart and produce "unknown tool".
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class literag_adapter implements adapter {
    /** @var string LiteRAG's configuration class, present only when it is installed. */
    private const CONFIG_CLASS = '\local_literag\local\config';

    /** @var string LiteRAG's in-process MCP dispatcher. */
    private const DISPATCHER_CLASS = '\local_literag\local\mcp\dispatcher';

    /** @var object|null Injected dispatcher, built on demand when null. */
    private ?object $dispatcher;

    /**
     * Constructor.
     *
     * @param object|null $dispatcher Injected dispatcher exposing dispatch(array): array, for tests.
     */
    public function __construct(?object $dispatcher = null) {
        $this->dispatcher = $dispatcher;
    }

    #[\Override]
    public static function id(): string {
        return 'literag';
    }

    #[\Override]
    public static function name(): string {
        return get_string('backend_literag', 'local_elediaai_chatengine');
    }

    /**
     * Available when LiteRAG is installed, enabled and holds an LLM key.
     *
     * The base URL always ships with a default, so the API key is the only
     * reliable signal that an administrator configured the model behind it.
     *
     * @return bool True when chat requests can be served.
     */
    #[\Override]
    public function is_available(): bool {
        if ($this->dispatcher !== null) {
            return true;
        }
        if (!class_exists(self::CONFIG_CLASS) || !class_exists(self::DISPATCHER_CLASS)) {
            return false;
        }
        if ((bool) call_user_func([self::CONFIG_CLASS, 'is_disabled'])) {
            return false;
        }
        return trim((string) call_user_func([self::CONFIG_CLASS, 'llm_api_key'])) !== '';
    }

    /**
     * What LiteRAG supports.
     *
     * Streaming is reported false: the dispatcher returns a complete envelope,
     * so there is nothing to emit fragment by fragment. Saying otherwise would
     * make the interface wait for deltas that never arrive.
     *
     * @return capabilities The capability report.
     */
    #[\Override]
    public function capabilities(): capabilities {
        $tools = class_exists(self::CONFIG_CLASS)
            && (bool) call_user_func([self::CONFIG_CLASS, 'enable_mcp_tools']);

        return new capabilities(
            supportsungrounded: true,
            supportstools: $tools,
            supportsstreaming: false,
            stateful: true,
        );
    }

    #[\Override]
    public function health(): health {
        if (!$this->is_available()) {
            return health::unconfigured();
        }

        $config = '\\local_literag\\local\\config';
        $client = '\\local_literag\\local\\llm\\client';
        if (!class_exists($config) || !class_exists($client)) {
            return new health(false, 'LiteRAG LLM client not available');
        }

        // Keyed on the connection, so changing the model or the key is checked
        // again at once, and briefly cached, so a dashboard that refreshes does
        // not send a language model a question every few seconds.
        $cache = \cache::make('local_elediaai_chatengine', 'backendhealth');
        $key = sha1(implode('|', [
            'literag',
            $config::llm_base_url(),
            $config::llm_model(),
            $config::llm_api_key() === '' ? '' : sha1($config::llm_api_key()),
        ]));
        $cached = $cache->get($key);
        if (is_array($cached)) {
            return new health((bool) $cached['healthy'], (string) $cached['message']);
        }

        try {
            if ($config::llm_api_key() === '') {
                throw new \moodle_exception('status_llm_missing_key', 'local_elediaai_chatengine');
            }

            $answer = (new $client())->chat([
                ['role' => 'system', 'content' => 'You are a health check. Reply with OK only.'],
                ['role' => 'user', 'content' => 'OK?'],
            ], null, 4);
            $healthy = trim((string) $answer) !== '';
            $message = $healthy ? 'OK' : 'Empty response';
        } catch (\Throwable $e) {
            $healthy = false;
            $message = $e->getMessage();
        }

        $cache->set($key, ['healthy' => $healthy, 'message' => $message]);

        return new health($healthy, $message);
    }

    #[\Override]
    public function chat(chat_request $request): chat_response {
        if (!$this->is_available()) {
            throw new adapter_exception('error_backend_unavailable', 'literag: not installed or unconfigured');
        }

        $arguments = [
            'moodle_token' => token_provider::get_token($request->userid),
            'user_message' => $request->usermessage,
            'rag_enabled' => $request->is_grounded(),
            'moodle_tools_enabled' => $request->allowtools,
        ];
        $coursearg = $request->course_argument();
        if ($coursearg !== null) {
            $arguments['course_id'] = $coursearg;
        }
        if ($request->convkey !== null && $request->convkey !== '') {
            $arguments['conversation_id'] = $request->convkey;
        }
        if ($request->language !== null && $request->language !== '') {
            $arguments['user_lang'] = $request->language;
        }
        if (!$request->persona->is_empty()) {
            $arguments['persona'] = $request->persona->as_array();
        }
        $arguments['system_prompt_id'] = $request->systempromptid;
        foreach (['answerstyle' => 'answer_style', 'pendingdecision' => 'pending_decision'] as $option => $argument) {
            $value = $request->option($option);
            if ($value !== null) {
                $arguments[$argument] = $value;
            }
        }
        // `moodle_tools_enabled` above is the switch; this degradation is the
        // fallback for a LiteRAG that predates the argument and reads "no
        // callbacks" only out of the intent. Both are sent, and they agree: a
        // placement barred from acting in Moodle also asks for retrieval only.
        // Drop this line once no supported LiteRAG predates the flag -- until
        // then it is what keeps the ban working on older sites.
        $intent = $request->allowtools ? $request->option('intent') : 'knowledge';
        if ($intent !== null) {
            $arguments['intent'] = $intent;
        }

        $result = $this->dispatch($arguments);
        $normalised = mcp_client::normalise_tool_result($result);

        return new chat_response(
            answer: (string) $normalised['answer'],
            convkey: $normalised['conversation_id'] !== null ? (string) $normalised['conversation_id'] : null,
            sources: $normalised['sources'],
            origin: self::origin($normalised['answer_origin']),
            topic: $normalised['topic'],
            confirmation: $normalised['confirmation'],
            iserror: (bool) $normalised['iserror'],
            prompttokens: $normalised['prompttokens'],
            completiontokens: $normalised['completiontokens'],
        );
    }

    #[\Override]
    public function call_tool(string $tool, array $arguments, int $userid): array {
        if (!$this->is_available()) {
            throw new adapter_exception('error_backend_unavailable', 'literag: not installed or unconfigured');
        }

        $name = $this->tool_name($tool);
        if ($name === '') {
            throw new adapter_exception('error_backend_tool_missing', 'literag: no tool for ' . $tool);
        }

        global $CFG;
        $arguments = array_merge([
            'system_url' => $CFG->wwwroot,
            'moodle_token' => token_provider::get_token($userid),
        ], $arguments);

        return mcp_client::normalise_tool_result($this->dispatch($arguments, $name));
    }

    /**
     * LiteRAG's own name for a logical tool.
     *
     * Its dispatcher resolves handlers against its settings, so asking the
     * engine's setting here would let the two drift apart into "unknown tool".
     *
     * @param string $tool The logical tool key.
     * @return string The configured name, or '' when LiteRAG does not offer it.
     */
    private function tool_name(string $tool): string {
        $methods = [
            'chat' => 'tool_chat',
            'history' => 'tool_history',
            'delete' => 'tool_delete',
            'deleteuser' => 'tool_delete_user',
            'memoryoptin' => 'tool_memory_optin',
            'recluster' => 'tool_recluster',
        ];
        if (!isset($methods[$tool])) {
            throw new \coding_exception('Unknown chat engine tool key: ' . $tool);
        }

        return trim((string) call_user_func([self::CONFIG_CLASS, $methods[$tool]]));
    }

    /**
     * Map the backend's answer origin onto the contract's.
     *
     * @param string|null $origin The backend's value.
     * @return string An ORIGIN_* constant.
     */
    private static function origin(?string $origin): string {
        return match ($origin) {
            'rag' => chat_response::ORIGIN_GROUNDED,
            'mcp' => chat_response::ORIGIN_MCP,
            default => chat_response::ORIGIN_GENERAL,
        };
    }

    /**
     * Hand one tools/call to LiteRAG and unwrap the envelope.
     *
     * @param array $arguments Tool arguments.
     * @param string|null $name The tool to call, or null for the chat tool.
     * @return array The MCP tool result.
     * @throws adapter_exception When LiteRAG reports a JSON-RPC error.
     */
    private function dispatch(array $arguments, ?string $name = null): array {
        $dispatcher = $this->dispatcher ?? new \local_literag\local\mcp\dispatcher();
        $envelope = $dispatcher->dispatch([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => [
                'name' => $name ?? $this->tool_name('chat'),
                'arguments' => $arguments,
            ],
        ]);

        if (isset($envelope['error'])) {
            $code = (int) ($envelope['error']['code'] ?? 0);
            // LiteRAG reports a rejected user token as -32001. Reported as an
            // authentication failure so the engine drops the cached token and
            // retries once, exactly as it does for the external agent.
            throw new adapter_exception(
                'error_backend_unavailable',
                'literag jsonrpc error ' . $code,
                $code === -32001 ? 401 : 0
            );
        }
        if (!isset($envelope['result']) || !is_array($envelope['result'])) {
            throw new adapter_exception('error_backend_bad_response', 'literag: missing result');
        }

        return $envelope['result'];
    }
}

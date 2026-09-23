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

use local_elediaai_chatengine\local\connection;
use local_elediaai_chatengine\local\mcp_client;
use local_elediaai_chatengine\local\token_provider;

/**
 * The external RAG agent that accompanies the ingestion pipeline.
 *
 * Reached over MCP at its own address. That address is configured separately
 * from the ingestion base URL in local_elediaai_sources because the two are
 * separate services: the pipeline answers `/documents/upsert`, the agent
 * answers `/mcp`. Deriving one from the other would reintroduce the URL guess
 * that DEL-516 removed.
 *
 * The id matches the sink id, which is what lets a site that writes its course
 * material to this pipeline have its questions answered by this agent without
 * a second setting saying so.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ingestionapi_adapter implements adapter {
    /** @var mcp_client|null Injected client, built on demand when null. */
    private ?mcp_client $client;

    /**
     * Constructor.
     *
     * @param mcp_client|null $client Injected client, for tests.
     */
    public function __construct(?mcp_client $client = null) {
        $this->client = $client;
    }

    #[\Override]
    public static function id(): string {
        return 'ingestionapi';
    }

    #[\Override]
    public static function name(): string {
        return get_string('backend_ingestionapi', 'local_elediaai_chatengine');
    }

    /**
     * Configured when an endpoint and a chat tool name are known.
     *
     * Deliberately no network call: this decides whether a placement renders a
     * chat box or a configuration notice, and that happens on every page load.
     * A reachability problem surfaces on the first turn as an error, which is
     * the honest place for it.
     *
     * @return bool True when chat requests can be attempted.
     */
    #[\Override]
    public function is_available(): bool {
        if ($this->client !== null) {
            return true;
        }
        if (connection::agent_url() === '' || connection::tool_name('chat') === '') {
            return false;
        }
        // The agent calls back into Moodle with a user-scoped token, so the
        // connector has to be installed and its service enabled.
        return token_provider::is_connector_available();
    }

    #[\Override]
    public function capabilities(): capabilities {
        return new capabilities(
            supportsungrounded: true,
            supportstools: true,
            supportsstreaming: connection::streaming_enabled(),
            stateful: true,
        );
    }

    #[\Override]
    public function health(): health {
        if (!$this->is_available()) {
            return health::unconfigured();
        }

        // The protocol contract has no health endpoint, and it says the client
        // does not call tools/list and a server need not implement it. Probing
        // something that is not promised would report a fault where there is
        // only an unimplemented convenience. Whether the ingestion side is
        // reachable is answered by the sink of local_elediaai_sources, which is
        // the component that actually talks to it.
        return new health(true, get_string('status_health_notprobed', 'local_elediaai_chatengine'));
    }

    #[\Override]
    public function chat(chat_request $request): chat_response {
        global $CFG;

        $client = $this->client();
        $token = token_provider::get_token($request->userid);

        $result = $client->chat(
            $CFG->wwwroot,
            $token,
            $request->usermessage,
            $request->course_argument(),
            $request->convkey,
            connection::tool_name('chat'),
            $request->option('ltmenabled'),
            $request->option('answerstyle'),
            $request->language,
            $request->is_grounded(),
            $request->allowtools,
            $request->persona->is_empty() ? null : $request->persona->as_array(),
            $request->systempromptid,
            // The intent degradation stays next to the explicit flag on purpose:
            // it is what a server built before spec 0.28.0 understands, and
            // dropping it here would silently re-open callbacks on every
            // deployment that has not caught up. Same pairing in literag_adapter.
            $request->allowtools ? $request->option('intent') : 'knowledge',
            $request->option('pendingdecision'),
            $request->ondelta,
        );

        return new chat_response(
            answer: (string) $result['answer'],
            convkey: $result['conversation_id'] !== null ? (string) $result['conversation_id'] : null,
            sources: $result['sources'],
            origin: self::origin($result['answer_origin']),
            topic: $result['topic'],
            confirmation: $result['confirmation'],
            iserror: (bool) $result['iserror'],
            prompttokens: $result['prompttokens'],
            completiontokens: $result['completiontokens'],
        );
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
     * The configured client, built on demand.
     *
     * @return mcp_client The client.
     * @throws \moodle_exception When the agent URL is missing or invalid.
     */
    private function client(): mcp_client {
        return $this->client ?? mcp_client::for_agent();
    }

    #[\Override]
    public function call_tool(string $tool, array $arguments, int $userid): array {
        global $CFG;

        $name = connection::tool_name($tool);
        if ($name === '') {
            throw new adapter_exception('error_backend_tool_missing', 'agent: no tool configured for ' . $tool);
        }

        $arguments = array_merge([
            'system_url' => $CFG->wwwroot,
            'moodle_token' => token_provider::get_token($userid),
        ], $arguments);

        return mcp_client::normalise_tool_result($this->client()->call_tool($name, $arguments));
    }
}

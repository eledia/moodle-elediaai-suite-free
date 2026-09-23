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

namespace local_elediaai_chatengine\local;

use moodle_url;

/**
 * Configuration the engine reads, in one place.
 *
 * Two kinds of value live here and the difference matters. The *choice* of
 * backend is not one of them: that follows the active sink of
 * local_elediaai_sources and is resolved in {@see \local_elediaai_chatengine\backend_resolver}.
 * What this class holds is how to *reach* a backend once it has been chosen,
 * plus the engine-wide limits that apply whichever backend answers.
 *
 * The external RAG agent needs its own address because it is a separate
 * service from the ingestion pipeline: the pipeline serves `/documents/upsert`
 * beneath the base URL configured in local_elediaai_sources, the agent serves
 * `/mcp` somewhere else entirely. Deriving one from the other would be exactly
 * the guess that DEL-516 removed. LiteRAG needs no address at all — it runs in
 * this site and is dispatched in-process.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class connection {
    /** @var string Configuration component name. */
    public const COMPONENT = 'local_elediaai_chatengine';

    /**
     * Read a plugin configuration value with a default fallback.
     *
     * @param string $name Configuration setting name.
     * @param mixed $default Default returned when the setting is unset or empty.
     * @return mixed
     */
    public static function get(string $name, mixed $default = null): mixed {
        $value = get_config(self::COMPONENT, $name);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }
        return $value;
    }

    /**
     * The configured MCP endpoint of the external RAG agent.
     *
     * @return string Empty string when unconfigured.
     */
    public static function agent_url(): string {
        return trim((string) self::get('backend_ingestionapi_url', ''));
    }

    /**
     * Validate the configured agent URL and return it.
     *
     * This is the SSRF trust boundary: only this admin-set value is ever used
     * as a request destination. Plain http:// is rejected unless an admin has
     * explicitly opted into insecure transport.
     *
     * @return moodle_url The validated endpoint.
     * @throws \moodle_exception When the URL is missing or unacceptable.
     */
    public static function validated_agent_url(): moodle_url {
        $raw = self::agent_url();
        if ($raw === '') {
            throw new \moodle_exception('error_agent_url_missing', self::COMPONENT);
        }

        $parts = parse_url($raw);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            throw new \moodle_exception('error_agent_url_invalid', self::COMPONENT);
        }

        $scheme = strtolower($parts['scheme']);
        $allowinsecure = (int) self::get('backend_ingestionapi_allowinsecure', 0) === 1;
        if ($scheme !== 'https' && !($scheme === 'http' && $allowinsecure)) {
            throw new \moodle_exception('error_agent_url_insecure', self::COMPONENT);
        }

        // moodle_url normalises, and the cURL wrapper downstream applies the
        // site's blocked-hosts / allowed-ports policy as defence in depth.
        return new moodle_url($raw);
    }

    /**
     * The decrypted agent authorization token.
     *
     * Persisted encrypted at rest, so the stored value is ciphertext and has to
     * be decrypted before it can be sent. Failure to decrypt fails closed with
     * an empty string rather than sending ciphertext as a credential.
     *
     * @return string The plaintext token, or '' when unset or undecryptable.
     */
    public static function agent_auth_token(): string {
        $stored = (string) self::get('backend_ingestionapi_authtoken', '');
        if ($stored === '') {
            return '';
        }
        if (!preg_match('~^' . \core\encryption::METHOD_SODIUM . ':~', $stored)) {
            return $stored;
        }
        try {
            return \core\encryption::decrypt($stored);
        } catch (\moodle_exception $e) {
            debugging('local_elediaai_chatengine: agent auth token could not be decrypted', DEBUG_DEVELOPER);
            return '';
        }
    }

    /**
     * The HTTP headers that authenticate this site to the agent.
     *
     * @return string[] Complete header lines.
     */
    public static function agent_auth_headers(): array {
        $method = (string) self::get('backend_ingestionapi_authmethod', 'none');
        $token = trim(self::agent_auth_token());
        if ($token === '') {
            return [];
        }
        if ($method === 'bearer') {
            return ['Authorization: Bearer ' . $token];
        }
        if ($method === 'header') {
            // The admin supplies one or more complete "Name: value" headers.
            return array_values(array_filter(array_map('trim', preg_split('/\R/', $token) ?: [])));
        }
        return [];
    }

    /**
     * Whether to bypass Moodle's cURL security helper for the agent host.
     *
     * Off by default. Intended only for an agent on an internal network or a
     * local development host, whose private address or non-standard port the
     * site policy would otherwise reject.
     *
     * @return bool
     */
    public static function allow_private_network(): bool {
        return (int) self::get('backend_ingestionapi_allowprivate', 0) === 1;
    }

    /**
     * Request timeout in seconds.
     *
     * @return int At least one second.
     */
    public static function request_timeout(): int {
        return max(1, (int) self::get('requesttimeout', 30));
    }

    /**
     * The name of one tool in the backend's catalogue.
     *
     * The six tool names are part of the documented backend contract
     * (rag_server_spec Part A), not of any one placement, which is why they are
     * configured here rather than in a block.
     *
     * @param string $key One of chat, history, delete, deleteuser, memoryoptin, recluster.
     * @return string The configured name, or '' when the backend does not offer it.
     */
    public static function tool_name(string $key): string {
        $defaults = [
            'chat' => 'tutor_chat',
            'history' => '',
            'delete' => '',
            'deleteuser' => '',
            'memoryoptin' => '',
            'recluster' => '',
        ];
        if (!array_key_exists($key, $defaults)) {
            throw new \coding_exception('Unknown chat engine tool key: ' . $key);
        }
        return trim((string) self::get('tool_' . $key, $defaults[$key]));
    }

    /**
     * Maximum accepted length of a user message, in characters.
     *
     * @return int At least one character.
     */
    public static function max_message_length(): int {
        return max(1, (int) self::get('maxmessagelength', 4000));
    }

    /**
     * How many prior turns travel with a request.
     *
     * Applies to every backend. A stateful backend keeps its own transcript,
     * but the engine still bounds what it sends so one long conversation
     * cannot grow a request without limit.
     *
     * @return int At least two turns.
     */
    public static function history_limit(): int {
        return max(2, (int) self::get('historylimit', 20));
    }

    /**
     * How many course ids may travel with one request.
     *
     * **The backend's number, not ours.** The retrieval tool behind the agent
     * validates the list it is handed and refuses one that is too long, so
     * this has to match what that tool accepts -- twenty when this was
     * written (MCP_Tools/server/validation.py, 20.09.2026), and a setting
     * rather than a constant because a server may raise it without asking us.
     *
     * When it does, raise this. Setting it higher than the backend allows does
     * not widen anything; it makes the turn fail at the far end, which is
     * worse than the fallback this number exists to trigger.
     *
     * @return int At least one course.
     */
    public static function max_scope_courses(): int {
        return max(1, (int) self::get('maxscopecourses', 20));
    }

    /**
     * Per-user chat requests allowed per minute; 0 disables the limit.
     *
     * @return int
     */
    public static function rate_limit_per_minute(): int {
        return max(0, (int) self::get('ratelimitperminute', 20));
    }

    /**
     * Per-user messages allowed per day; 0 means unlimited.
     *
     * @return int
     */
    public static function daily_message_limit(): int {
        return max(0, (int) self::get('dailymessagelimit', 0));
    }

    /**
     * Whether answers may be streamed to the browser as they are produced.
     *
     * @return bool
     */
    public static function streaming_enabled(): bool {
        return (int) self::get('streamingenabled', 1) === 1;
    }

    /**
     * Days a conversation is kept after its last turn; 0 keeps them forever.
     *
     * @return int
     */
    public static function retention_days(): int {
        return max(0, (int) self::get('retentiondays', 0));
    }
}

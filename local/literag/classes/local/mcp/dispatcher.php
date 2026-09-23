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

namespace local_literag\local\mcp;

use local_literag\local\config;
use local_literag\local\mcp\tools\tutor_chat;
use local_literag\local\mcp\tools\tutor_delete_conversation;
use local_literag\local\mcp\tools\tutor_delete_user_data;
use local_literag\local\mcp\tools\tutor_get_history;
use local_literag\local\mcp\tools\tutor_recluster_questions;
use local_literag\local\mcp\tools\tutor_set_memory_optin;

/**
 * Routes a JSON-RPC 2.0 tools/call request to the matching tutor tool.
 *
 * Tool names are admin-configurable and must mirror the tutor block's settings;
 * the dispatcher resolves the incoming name against this plugin's configured
 * names. A handful of MCP discovery methods (initialize, tools/list) are
 * answered minimally for debugging even though the block does not use them.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dispatcher {
    /** @var string MCP protocol version advertised. */
    private const PROTOCOL_VERSION = '2025-06-18';

    /**
     * Process a decoded JSON-RPC request and return the response envelope.
     *
     * @param array $request Decoded JSON-RPC request.
     * @return array JSON-RPC response envelope.
     */
    public function dispatch(array $request): array {
        $id = $request['id'] ?? null;
        $method = (string) ($request['method'] ?? '');

        if (($request['jsonrpc'] ?? '') !== '2.0') {
            return result::error($id, -32600, 'invalid request: not jsonrpc 2.0');
        }

        // Minimal discovery support (the block does not use these).
        if ($method === 'initialize') {
            return result::success($id, [
                'protocolVersion' => self::PROTOCOL_VERSION,
                'capabilities' => ['tools' => new \stdClass()],
                'serverInfo' => ['name' => 'local_literag', 'version' => $this->server_version()],
            ]);
        }
        if ($method === 'tools/list') {
            return result::success($id, ['tools' => $this->tool_descriptors()]);
        }
        if ($method !== 'tools/call') {
            return result::error($id, -32601, 'method not found: ' . $method);
        }

        $params = (array) ($request['params'] ?? []);
        $name = (string) ($params['name'] ?? '');
        $arguments = (array) ($params['arguments'] ?? []);

        $handler = $this->resolve($name);
        if ($handler === null) {
            return result::error($id, -32601, 'unknown tool: ' . $name);
        }

        try {
            $toolresult = $handler->handle($arguments);
            return result::success($id, $toolresult);
        } catch (tool_exception $e) {
            return result::error($id, $e->rpccode, $e->getMessage());
        } catch (\Throwable $e) {
            debugging('local_literag tool error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return result::error($id, -32000, 'internal error');
        }
    }

    /**
     * Resolve a configured tool name to its handler instance.
     *
     * @param string $name Incoming tool name.
     * @return tool|null
     */
    private function resolve(string $name): ?tool {
        if ($name === '') {
            return null;
        }
        $map = [
            config::tool_chat() => tutor_chat::class,
            config::tool_history() => tutor_get_history::class,
            config::tool_delete() => tutor_delete_conversation::class,
            config::tool_delete_user() => tutor_delete_user_data::class,
            config::tool_memory_optin() => tutor_set_memory_optin::class,
            config::tool_recluster() => tutor_recluster_questions::class,
        ];
        if (!isset($map[$name])) {
            return null;
        }
        $class = $map[$name];
        return new $class();
    }

    /**
     * Get the installed plugin release for MCP discovery metadata.
     *
     * @return string
     */
    private function server_version(): string {
        $plugininfo = \core_plugin_manager::instance()->get_plugin_info('local_literag');
        if ($plugininfo !== null && $plugininfo->release !== null && $plugininfo->release !== '') {
            return (string) $plugininfo->release;
        }
        return (string) get_config('local_literag', 'version');
    }

    /**
     * Lightweight tool descriptors for tools/list.
     *
     * @return array
     */
    private function tool_descriptors(): array {
        $names = [
            config::tool_chat(),
            config::tool_history(),
            config::tool_delete(),
            config::tool_delete_user(),
            config::tool_memory_optin(),
            config::tool_recluster(),
        ];
        $tools = [];
        foreach ($names as $name) {
            if ($name === '') {
                continue;
            }
            $tools[] = [
                'name' => $name,
                'inputSchema' => ['type' => 'object'],
            ];
        }
        return $tools;
    }
}

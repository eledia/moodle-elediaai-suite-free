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

namespace webservice_elediamcp;

use advanced_testcase;
use ReflectionClass;
use webservice_elediamcp\local\scope_gate;
use webservice_elediamcp\local\request;
use webservice_elediamcp\local\server;
use webservice_elediamcp\local\token_manager;
use webservice_elediamcp\local\tool_provider;

/**
 * Scopes of OAuth tokens are enforced on MCP requests.
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \webservice_elediamcp\local\scope_gate
 * @covers      \webservice_elediamcp\local\server
 * @covers      \webservice_elediamcp\local\token_manager
 */
final class scope_gate_test extends advanced_testcase {
    /**
     * Each method asks for the scope that belongs to it.
     */
    public function test_required_scopes(): void {
        $this->assertSame(['mcp:tools.read', 'mcp:tools.call'], scope_gate::required_scopes('tools/list'));
        $this->assertSame(['mcp:tools.read', 'mcp:tools.call'], scope_gate::required_scopes('tools/call', false));
        $this->assertSame(['mcp:tools.call'], scope_gate::required_scopes('tools/call', true));
        $this->assertSame(['mcp:prompts.read'], scope_gate::required_scopes('prompts/get'));
        $this->assertSame(['mcp:resources.read'], scope_gate::required_scopes('resources/read'));
        $this->assertSame([], scope_gate::required_scopes('resources/list'), 'Nothing is served behind resources yet.');
        $this->assertTrue(scope_gate::is_soft_method('prompts/list'));
        $this->assertFalse(scope_gate::is_soft_method('prompts/get'));
        $this->assertSame([], scope_gate::required_scopes('initialize'));
        $this->assertSame([], scope_gate::required_scopes('ping'));
    }

    /**
     * Reading scopes allow read tools only; a token without scope is not limited.
     */
    public function test_allows(): void {
        $this->resetAfterTest(true);
        $read = scope_gate::required_scopes('tools/call', false);
        $write = scope_gate::required_scopes('tools/call', true);

        $this->assertTrue(scope_gate::allows('mcp:tools.read', $read));
        $this->assertFalse(scope_gate::allows('mcp:tools.read', $write));
        $this->assertTrue(scope_gate::allows('mcp:tools.call', $write));
        $this->assertTrue(scope_gate::allows('mcp:tools.call', $read));
        $this->assertFalse(scope_gate::allows('mcp:prompts.read', $read));
        $this->assertTrue(scope_gate::allows(null, $write), 'No scope on the token: not limited.');
        $this->assertTrue(scope_gate::allows('mcp:tools.read', []), 'Methods without scope need none.');
    }

    /**
     * The host setting in config.php switches the check off (default: on).
     */
    public function test_host_setting_switches_enforcement_off(): void {
        global $CFG;
        $this->resetAfterTest(true);
        $write = scope_gate::required_scopes('tools/call', true);

        $this->assertTrue(scope_gate::is_enforced());
        $CFG->webservice_elediamcp_enforce_scopes = false;
        $this->assertFalse(scope_gate::is_enforced());
        $this->assertTrue(scope_gate::allows('mcp:tools.read', $write));
        $CFG->webservice_elediamcp_enforce_scopes = true;
        $this->assertTrue(scope_gate::is_enforced());
        $this->assertFalse(scope_gate::allows('mcp:tools.read', $write));

        // Values that switch off, and values that keep the check on, also as text from the environment.
        foreach ([0, '0', 'false', 'off', 'no', ''] as $off) {
            $CFG->webservice_elediamcp_enforce_scopes = $off;
            $this->assertFalse(scope_gate::is_enforced(), var_export($off, true));
        }
        foreach ([1, '1', 'true', 'on', 'yes'] as $on) {
            $CFG->webservice_elediamcp_enforce_scopes = $on;
            $this->assertTrue(scope_gate::is_enforced(), var_export($on, true));
        }
        unset($CFG->webservice_elediamcp_enforce_scopes);
        $this->assertTrue(scope_gate::is_enforced(), 'Not set: enforced.');
    }

    /**
     * Create a token with a scope and ask the server whether it covers a request.
     *
     * @param string|null $scope Scope of the token.
     * @param string $method MCP method.
     * @param bool $write Write tool.
     * @return bool
     */
    private function server_allows(?string $scope, string $method, bool $write): bool {
        $user = $this->getDataGenerator()->create_user();
        $context = \context_system::instance();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('webservice/elediamcp:use', CAP_ALLOW, $roleid, $context->id, true);
        role_assign($roleid, $user->id, $context->id);
        accesslib_clear_all_caches_for_unit_testing();
        $serviceid = token_manager::ensure_default_service_configured();
        $created = token_manager::create_token((int) $user->id, $serviceid, 'Scope test', 0, null, (int) $user->id, $scope);

        $server = new server(WEBSERVICE_AUTHMETHOD_PERMANENT_TOKEN);
        $reflection = new ReflectionClass($server);
        $property = $reflection->getProperty('token');
        $property->setAccessible(true);
        $property->setValue($server, $created->token);
        $check = $reflection->getMethod('token_scope_allows');
        $check->setAccessible(true);
        return $check->invoke($server, $method, $write);
    }

    /**
     * The server refuses what the token scope does not cover.
     */
    public function test_server_enforces_token_scope(): void {
        $this->resetAfterTest(true);

        $this->assertTrue($this->server_allows('mcp:tools.read', 'tools/call', false));
        $this->assertFalse($this->server_allows('mcp:tools.read', 'tools/call', true));
        $this->assertFalse($this->server_allows('mcp:tools.read', 'prompts/get', false));
        $this->assertTrue($this->server_allows('mcp:tools.call', 'tools/call', true));
        $this->assertTrue($this->server_allows('mcp:tools.read mcp:prompts.read', 'prompts/list', false));
        $this->assertTrue($this->server_allows('mcp:tools.read', 'initialize', false));
        $this->assertTrue($this->server_allows(null, 'tools/call', true), 'A token without scope is not limited.');
    }

    /**
     * With the host setting off, a narrow token may call write tools (behaviour before scopes).
     */
    public function test_server_ignores_scope_when_enforcement_is_off(): void {
        global $CFG;
        $this->resetAfterTest(true);
        $CFG->webservice_elediamcp_enforce_scopes = false;
        $this->assertTrue($this->server_allows('mcp:tools.read', 'tools/call', true));
    }

    /**
     * Unknown tokens have no scope entry to look up.
     */
    public function test_get_scope_for_unknown_token_is_null(): void {
        $this->resetAfterTest(true);
        $this->assertNull(token_manager::get_scope_for_token('does-not-exist'));
    }

    /**
     * Create a token for a new user, optionally limited to a scope.
     *
     * @param string|null $scope Scope.
     * @return \stdClass {token, record}
     */
    private function create_scoped_token(?string $scope): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $serviceid = token_manager::ensure_default_service_configured();
        return token_manager::create_token((int) $user->id, $serviceid, 'Scope lookup', 0, null, (int) $user->id, $scope);
    }

    /**
     * The scope is found through the core token, not through the hash of the presented text.
     */
    public function test_scope_lookup_does_not_depend_on_the_token_hash(): void {
        global $DB;
        $this->resetAfterTest(true);
        $created = $this->create_scoped_token('mcp:tools.read');
        $this->assertSame('mcp:tools.read', token_manager::get_scope_for_token($created->token));

        // A database with a case-insensitive collation accepts the token in another spelling; the
        // hash of that text matches no row. The lookup must not rely on the hash.
        $DB->set_field('webservice_elediamcp_token', 'tokenhash', hash('sha256', 'other spelling'), ['id' => $created->record->id]);
        $this->assertSame('mcp:tools.read', token_manager::get_scope_for_token($created->token));
    }

    /**
     * A revoked token has no core token any more, so nothing is looked up.
     */
    public function test_revoked_token_has_no_scope(): void {
        $this->resetAfterTest(true);
        $created = $this->create_scoped_token('mcp:tools.read');
        token_manager::revoke_token((int) $created->record->id);
        $this->assertNull(token_manager::get_scope_for_token($created->token));
    }

    /**
     * Build a server holding a token and a request, and run one of its list responses.
     *
     * @param string|null $scope Scope of the token.
     * @param string $method MCP method.
     * @param string $responder Name of the protected method that answers the request.
     * @return array Decoded response.
     */
    private function list_response(?string $scope, string $method, string $responder): array {
        $created = $this->create_scoped_token($scope);
        $server = new server(WEBSERVICE_AUTHMETHOD_PERMANENT_TOKEN);
        $reflection = new ReflectionClass($server);
        $mcprequest = new request(['jsonrpc' => '2.0', 'method' => $method, 'id' => 7, 'params' => []]);
        $values = ['token' => $created->token, 'mcprequest' => $mcprequest, 'userid' => (int) $created->record->userid];
        foreach ($values as $name => $value) {
            $property = $reflection->getProperty($name);
            $property->setAccessible(true);
            $property->setValue($server, $value);
        }
        $respond = $reflection->getMethod($responder);
        $respond->setAccessible(true);
        ob_start();
        $respond->invoke($server);
        return json_decode(ob_get_clean(), true);
    }

    /**
     * Without mcp:tools.call the tool list holds only read-only tools; with it, all of them.
     */
    public function test_tools_list_hides_write_tools_without_tools_call(): void {
        $this->resetAfterTest(true);
        $read = $this->list_response('mcp:tools.read', 'tools/list', 'send_tools_list_response')['result']['tools'];
        $all = $this->list_response('mcp:tools.read mcp:tools.call', 'tools/list', 'send_tools_list_response')['result']['tools'];

        $this->assertNotEmpty($read);
        $this->assertGreaterThan(count($read), count($all));
        foreach ($read as $tool) {
            $class = tool_provider::find_ai_tool($tool['name']);
            $this->assertNotNull($class, $tool['name']);
            $this->assertTrue(!empty($class::annotations()['readOnlyHint']), $tool['name']);
        }
        $names = array_column($read, 'name');
        $this->assertNotContains('moodle_quiz_save_answers', $names);
        $this->assertContains('moodle_quiz_save_answers', array_column($all, 'name'));
    }

    /**
     * prompts/list answers with an empty list instead of a refusal when the prompts scope is missing.
     */
    public function test_prompts_list_is_empty_without_prompts_scope(): void {
        $this->resetAfterTest(true);
        $without = $this->list_response('mcp:tools.read', 'prompts/list', 'send_prompts_list_response');
        $this->assertSame([], $without['result']['prompts']);
        $with = $this->list_response('mcp:prompts.read', 'prompts/list', 'send_prompts_list_response');
        $this->assertNotEmpty($with['result']['prompts']);
    }
}

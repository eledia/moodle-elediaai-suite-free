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
use local_literag\local\mcp\dispatcher;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for JSON-RPC routing and tool-name resolution.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(dispatcher::class)]
final class dispatcher_test extends \advanced_testcase {
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
     * Non-2.0 requests are rejected.
     */
    public function test_invalid_jsonrpc(): void {
        $this->resetAfterTest();
        $response = (new dispatcher())->dispatch(['method' => 'tools/call', 'id' => 1]);
        $this->assertSame(-32600, $response['error']['code']);
    }

    /**
     * Unknown methods are rejected.
     */
    public function test_unknown_method(): void {
        $this->resetAfterTest();
        $response = (new dispatcher())->dispatch(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'foo/bar']);
        $this->assertSame(-32601, $response['error']['code']);
    }

    /**
     * Unknown tool names are rejected.
     */
    public function test_unknown_tool(): void {
        $this->resetAfterTest();
        $response = (new dispatcher())->dispatch([
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
            'params' => ['name' => 'not_a_tool', 'arguments' => []],
        ]);
        $this->assertSame(-32601, $response['error']['code']);
    }

    /**
     * tools/list returns the configured tool names.
     */
    public function test_tools_list(): void {
        $this->resetAfterTest();
        $response = (new dispatcher())->dispatch(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']);
        $names = array_map(static fn($t) => $t['name'], $response['result']['tools']);
        $this->assertContains('tutor_chat', $names);
    }

    /**
     * The history tool routes and returns owned messages.
     */
    public function test_history_routing(): void {
        global $CFG;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $token = $this->mint_token((int) $user->id);

        $repo = new conversation_repository();
        $conversation = $repo->create((int) $user->id, 0, 'explain');
        $repo->add_message($conversation, 'user', 'Hello?');
        $repo->add_message($conversation, 'assistant', 'Hi there.');

        $response = (new dispatcher())->dispatch([
            'jsonrpc' => '2.0', 'id' => 7, 'method' => 'tools/call',
            'params' => ['name' => 'tutor_get_history', 'arguments' => [
                'system_url' => $CFG->wwwroot, 'moodle_token' => $token,
                'conversation_id' => $conversation->convkey,
            ]],
        ]);

        $this->assertSame(7, $response['id']);
        $messages = $response['result']['structuredContent']['messages'];
        $this->assertCount(2, $messages);
        $this->assertSame('user', $messages[0]['role']);
    }

    /**
     * Recluster rejects a learner token (must be pinned to the maintenance account).
     */
    public function test_recluster_rejects_learner_token(): void {
        global $CFG;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(); // Not 'elediaai_tutor_service'.
        $token = $this->mint_token((int) $user->id);

        $response = (new dispatcher())->dispatch([
            'jsonrpc' => '2.0', 'id' => 9, 'method' => 'tools/call',
            'params' => ['name' => 'tutor_recluster_questions', 'arguments' => [
                'system_url' => $CFG->wwwroot, 'moodle_token' => $token, 'course_id' => '1',
                'existing_labels' => ['Photosynthesis'],
                'questions' => [['id' => 1, 'text' => 'how does photosynthesis work']],
            ]],
        ]);

        $this->assertArrayHasKey('error', $response);
        $this->assertSame(-32002, $response['error']['code']);
    }
}

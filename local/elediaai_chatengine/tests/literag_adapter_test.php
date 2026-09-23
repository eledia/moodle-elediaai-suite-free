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

use local_elediaai_chatengine\adapter\chat_request;
use local_elediaai_chatengine\adapter\literag_adapter;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * What the LiteRAG adapter actually puts on the wire.
 *
 * The interesting layer is the one between the request object and the tool
 * arguments: a placement can forbid callbacks all it likes, it only means
 * something once the ban leaves the building.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(literag_adapter::class)]
final class literag_adapter_test extends \advanced_testcase {
    /**
     * A dispatcher that records the arguments and answers with a fixed result.
     *
     * @return object The recording dispatcher.
     */
    private function recording_dispatcher(): object {
        return new class {
            /** @var array The arguments of the last tools/call. */
            public array $arguments = [];

            /**
             * Record the call and return a minimal valid envelope.
             *
             * @param array $request The JSON-RPC request.
             * @return array The JSON-RPC response envelope.
             */
            public function dispatch(array $request): array {
                $this->arguments = $request['params']['arguments'] ?? [];
                return ['jsonrpc' => '2.0', 'id' => 1, 'result' => [
                    'content' => [['type' => 'text', 'text' => 'Antwort.']],
                    'structuredContent' => ['answer' => 'Antwort.', 'conversation_id' => 'c1'],
                    'isError' => false,
                ]];
            }
        };
    }

    /**
     * Configure an MCP service so token provisioning works in a test.
     *
     * @return void
     */
    private function configure_mcp_service(): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/webservice/lib.php');

        if (!class_exists('\\webservice_elediamcp\\api')) {
            $this->markTestSkipped('webservice_elediamcp connector plugin is not installed.');
        }
        if (!class_exists('\\local_literag\\local\\config')) {
            $this->markTestSkipped('local_literag is not installed.');
        }

        $service = (object) [
            'name' => 'MCP test service',
            'shortname' => 'mcptest',
            'enabled' => 1,
            'restrictedusers' => 0,
            'downloadfiles' => 0,
            'uploadfiles' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $service->id = $DB->insert_record('external_services', $service);

        set_config('services', (string) $service->id, 'webservice_elediamcp');
        set_config('mcpserviceid', $service->id, 'local_elediaai_chatengine');
    }

    /**
     * A placement that may not call back sends the ban, not just a hint.
     *
     * The `intent` degradation travels alongside it for servers that predate
     * the flag; both are asserted so that removing either one is a visible
     * decision rather than an accident.
     *
     * @return void
     */
    public function test_forbidden_tools_reach_the_wire(): void {
        $this->resetAfterTest();
        $this->configure_mcp_service();
        $user = $this->getDataGenerator()->create_user();

        $dispatcher = $this->recording_dispatcher();
        (new literag_adapter($dispatcher))->chat(new chat_request(
            usermessage: 'Frage',
            userid: (int) $user->id,
            allowtools: false,
        ));

        $this->assertArrayHasKey('moodle_tools_enabled', $dispatcher->arguments);
        $this->assertFalse($dispatcher->arguments['moodle_tools_enabled']);
        $this->assertSame('knowledge', $dispatcher->arguments['intent']);
    }

    /**
     * A placement that may call back says so explicitly.
     *
     * The flag is sent in both positions, so a reader of a request log can tell
     * "allowed" from "this client does not know the field yet".
     *
     * @return void
     */
    public function test_permitted_tools_reach_the_wire(): void {
        $this->resetAfterTest();
        $this->configure_mcp_service();
        $user = $this->getDataGenerator()->create_user();

        $dispatcher = $this->recording_dispatcher();
        (new literag_adapter($dispatcher))->chat(new chat_request(
            usermessage: 'Frage',
            userid: (int) $user->id,
            allowtools: true,
        ));

        $this->assertTrue($dispatcher->arguments['moodle_tools_enabled']);
        $this->assertArrayNotHasKey('intent', $dispatcher->arguments);
    }
}

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

namespace webservice_elediamcp;

use advanced_testcase;
use moodle_exception;
use ReflectionClass;
use webservice_elediamcp\local\ai\registry;
use webservice_elediamcp\local\request;
use webservice_elediamcp\local\server;
use webservice_elediamcp\local\token_manager;
use webservice_elediamcp\local\tool_provider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/lib.php');

/**
 * The tool catalogue follows the caller's rights, and refusals say why (M-07).
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \webservice_elediamcp\local\tool_provider
 * @covers      \webservice_elediamcp\local\server
 */
final class tool_catalogue_test extends advanced_testcase {
    /**
     * Names in tools/list for a fresh token of the given user.
     *
     * @param int $userid Token owner.
     * @return string[]
     */
    private function catalogue_for(int $userid): array {
        $serviceid = token_manager::ensure_default_service_configured();
        $token = token_manager::create_token($userid, $serviceid, 'Catalogue');
        return array_column(tool_provider::get_tools($token->token), 'name');
    }

    /**
     * A teacher does not see moodle_create_user; an administrator does.
     */
    public function test_catalogue_hides_tools_without_capability(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $teachertools = $this->catalogue_for((int) $teacher->id);
        $this->assertNotContains('moodle_create_user', $teachertools);
        $this->assertContains('moodle_me', $teachertools);
        $this->assertContains('moodle_my_courses', $teachertools);

        $this->assertNotContains('moodle_create_user', $this->catalogue_for((int) $student->id));

        $admin = get_admin();
        $this->assertContains('moodle_create_user', $this->catalogue_for((int) $admin->id));
    }

    /**
     * Course capabilities count when held in at least one course.
     */
    public function test_course_capabilities_count_anywhere(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->assertTrue(tool_provider::user_may_see('moodle_grading_queue', (int) $teacher->id));
        $this->assertFalse(tool_provider::user_may_see('moodle_grading_queue', (int) $student->id));
        $this->assertFalse(tool_provider::user_may_see('moodle_create_user', (int) $teacher->id));
        // Tools without requirements are visible to everyone who may use MCP.
        $this->assertTrue(tool_provider::user_may_see('moodle_me', (int) $student->id));
        // Nobody in particular gets only the unrestricted tools.
        $this->assertFalse(tool_provider::user_may_see('moodle_create_user', 0));
    }

    /**
     * Contributed tools count as free, not as premium.
     */
    public function test_contributed_tools_count_as_free(): void {
        global $CFG;
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/webservice/elediamcp/tests/fixtures/contributed_tool_fixture.php');

        registry::set_test_tools([\webservice_elediamcp\tests\fixtures\contributed_tool_fixture::class]);
        try {
            $this->assertContains('fixture_echo', tool_provider::free_ai_tool_names());
            $this->assertNotContains('fixture_echo', tool_provider::premium_ai_tool_names());
            $this->assertContains('moodle_create_user', tool_provider::free_ai_tool_names());
            $this->assertContains('moodle_create_course', tool_provider::premium_ai_tool_names());
            // Free and premium never overlap and together cover the registry.
            $this->assertSame([], array_intersect(
                tool_provider::free_ai_tool_names(),
                tool_provider::premium_ai_tool_names()
            ));
            $this->assertCount(
                count(registry::names()),
                array_merge(tool_provider::free_ai_tool_names(), tool_provider::premium_ai_tool_names())
            );
        } finally {
            registry::set_test_tools([]);
        }
    }

    /**
     * A server with a prepared tools/call request.
     *
     * @param string $name Tool name.
     * @return array{0: server, 1: ReflectionClass}
     */
    private function server_for_call(string $name): array {
        $server = new server(WEBSERVICE_AUTHMETHOD_PERMANENT_TOKEN);
        $reflection = new ReflectionClass($server);
        $reflection->getProperty('mcprequest')->setValue($server, new request([
            'jsonrpc' => '2.0', 'method' => 'tools/call', 'id' => 7,
            'params' => ['name' => $name, 'arguments' => []],
        ]));
        $reflection->getProperty('functionname')->setValue($server, $name);
        return [$server, $reflection];
    }

    /**
     * A locked premium tool is refused as a JSON-RPC error that says premium.
     */
    public function test_premium_tool_refused_clearly(): void {
        $this->resetAfterTest();
        if (\webservice_elediamcp\local\premium::has_mcp_tools()) {
            $this->markTestSkipped('Premium add-on active; the free refusal cannot be asserted.');
        }

        [$server, $reflection] = $this->server_for_call('moodle_create_course');
        ob_start();
        $reflection->getMethod('emit_tool_unavailable')->invoke($server, 'moodle_create_course');
        $response = json_decode(ob_get_clean(), true);

        $this->assertSame(-32602, $response['error']['code']);
        $this->assertSame(7, $response['id']);
        $this->assertSame(
            get_string('err_tool_premium', 'webservice_elediamcp', 'moodle_create_course'),
            $response['error']['message']
        );
        $this->assertArrayNotHasKey('result', $response);
    }

    /**
     * A raw function the token's service does not contain is refused by name.
     */
    public function test_raw_function_outside_service_refused_clearly(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $serviceid = token_manager::ensure_default_service_configured();
        [$server, $reflection] = $this->server_for_call('core_webservice_get_site_info');
        $reflection->getProperty('restricted_serviceid')->setValue($server, $serviceid);

        try {
            $reflection->getMethod('load_function_info')->invoke($server);
            $this->fail('A function outside the service must be refused.');
        } catch (moodle_exception $ex) {
            $this->assertSame('err_tool_unavailable', $ex->errorcode);
            $this->assertSame('webservice_elediamcp', $ex->module);
        }

        ob_start();
        $reflection->getMethod('send_error')->invoke($server, $ex);
        $response = json_decode(ob_get_clean(), true);
        $this->assertSame(-32602, $response['error']['code']);
        $this->assertSame(
            get_string('err_tool_unavailable', 'webservice_elediamcp', 'core_webservice_get_site_info'),
            $response['error']['message']
        );
    }

    /**
     * A name that is no function at all is refused the same way.
     */
    public function test_unknown_function_refused_clearly(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$server, $reflection] = $this->server_for_call('no_such_function_anywhere');
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('err_tool_unavailable', 'webservice_elediamcp', 'no_such_function_anywhere'));
        $reflection->getMethod('load_function_info')->invoke($server);
    }
}

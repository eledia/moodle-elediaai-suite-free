<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace webservice_elediamcp;

use advanced_testcase;
use ReflectionClass;
use webservice_elediamcp\event\tool_invoked;
use webservice_elediamcp\local\ai\registry;
use webservice_elediamcp\local\confirmation_gate;
use webservice_elediamcp\local\request;
use webservice_elediamcp\local\server;

/**
 * The server asks the confirmation gate before a write tool runs (#178).
 *
 * @package     webservice_elediamcp
 * @author      Immanuel Pasanec <immanuel.pasanec@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \webservice_elediamcp\local\server
 */
final class confirmation_server_test extends advanced_testcase {
    /**
     * Give a user a token of a component.
     *
     * @param int $userid The user.
     * @param string|null $component Who provisioned the token.
     * @return string The token value.
     */
    private function token_for(int $userid, ?string $component): string {
        global $DB;
        $serviceid = local\token_manager::ensure_default_service_configured();
        $value = bin2hex(random_bytes(16));
        $externalid = $DB->insert_record('external_tokens', (object) [
            'token' => $value, 'tokentype' => EXTERNAL_TOKEN_PERMANENT, 'userid' => $userid,
            'externalserviceid' => $serviceid, 'contextid' => \core\context\system::instance()->id,
            'creatorid' => $userid, 'timecreated' => time(),
        ]);
        $DB->insert_record('webservice_elediamcp_token', (object) [
            'externaltokenid' => $externalid, 'tokenhash' => hash('sha256', $value), 'userid' => $userid,
            'externalserviceid' => $serviceid, 'name' => 'Test', 'creatorid' => $userid, 'component' => $component,
            'timecreated' => time(), 'revoked' => 0,
        ]);
        return $value;
    }

    /**
     * Call the fixture write tool through the server.
     *
     * @param string $token The token of the call.
     * @param int $userid The user.
     * @param array $parameters The tool arguments.
     * @return array{0: array, 1: array} The tool's structured result and the `other` data of the event.
     */
    private function call(string $token, int $userid, array $parameters): array {
        global $CFG;
        require_once($CFG->dirroot . '/webservice/elediamcp/tests/fixtures/confirm_tool_fixture.php');
        $class = \webservice_elediamcp\tests\fixtures\confirm_tool_fixture::class;
        $server = new server(WEBSERVICE_AUTHMETHOD_PERMANENT_TOKEN);
        $reflection = new ReflectionClass($server);
        foreach (
            [
                'parameters' => $parameters,
                'token' => $token,
                'userid' => $userid,
                'mcprequest' => new request(['jsonrpc' => '2.0', 'method' => 'tools/call', 'id' => 1, 'params' => []]),
            ] as $name => $value
        ) {
            $property = $reflection->getProperty($name);
            $property->setAccessible(true);
            $property->setValue($server, $value);
        }
        registry::set_test_tools([$class]);
        $sink = $this->redirectEvents();
        try {
            $method = $reflection->getMethod('dispatch_ai_tool');
            $method->setAccessible(true);
            ob_start();
            $method->invoke($server, $class);
            $answer = json_decode(ob_get_clean(), true);
        } finally {
            registry::set_test_tools([]);
        }
        $events = array_values(array_filter($sink->get_events(), fn($event) => $event instanceof tool_invoked));
        $this->assertCount(1, $events);
        $structured = $answer['result']['structuredContent'];
        return [$structured['result'] ?? $structured, $events[0]->other];
    }

    /**
     * The chat engine's agent cannot write on its own say-so; the attempt is logged.
     */
    public function test_confirm_without_approval_gets_the_preview_and_a_log_entry(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $token = $this->token_for((int) $user->id, confirmation_gate::GATED_COMPONENT);

        [$result, $other] = $this->call($token, (int) $user->id, ['name' => 'A', 'confirm' => true]);

        $this->assertFalse($result['created']);
        $this->assertTrue($result['requires_confirmation']);
        $this->assertArrayHasKey('confirmation_withheld', $result);
        $this->assertTrue($other['withheld']);
        $this->assertFalse($other['iserror']);
    }

    /**
     * Preview, the person's agreement to the card, then the confirmed call goes through, once.
     */
    public function test_the_approved_call_goes_through_once(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $token = $this->token_for((int) $user->id, confirmation_gate::GATED_COMPONENT);

        confirmation_gate::user_turn((int) $user->id, 'Schreibe A');
        [$preview, $other] = $this->call($token, (int) $user->id, ['name' => 'A']);
        $this->assertTrue($preview['requires_confirmation']);
        $this->assertArrayNotHasKey('withheld', $other);
        confirmation_gate::answer_shown((int) $user->id, ['required' => true, 'tooltitle' => 'fixture_write']);
        $this->waitForSecond();
        confirmation_gate::user_turn((int) $user->id, 'Ja, ausfuehren');

        [$done, $other] = $this->call($token, (int) $user->id, ['name' => 'A', 'confirm' => true]);
        $this->assertTrue($done['created']);
        $this->assertArrayNotHasKey('confirmation_withheld', $done);
        $this->assertArrayNotHasKey('withheld', $other);

        [$again, $other] = $this->call($token, (int) $user->id, ['name' => 'A', 'confirm' => true]);
        $this->assertFalse($again['created'], 'the approval is used up');
        $this->assertTrue($other['withheld']);
    }

    /**
     * Tokens of other components, and tokens without one, are not gated.
     */
    public function test_other_tokens_are_not_gated(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        foreach (['core', null] as $component) {
            $token = $this->token_for((int) $user->id, $component);
            [$result, $other] = $this->call($token, (int) $user->id, ['name' => 'A', 'confirm' => true]);
            $this->assertTrue($result['created'], (string) $component);
            $this->assertArrayNotHasKey('withheld', $other);
        }
    }
}

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
use ReflectionClass;
use webservice_elediamcp\event\tool_invoked;
use webservice_elediamcp\local\ai\registry;
use webservice_elediamcp\local\request;
use webservice_elediamcp\local\server;

/**
 * A failed tool call tells why in the event log and in the answer (#182).
 *
 * @package     webservice_elediamcp
 * @author      Immanuel Pasanec <immanuel.pasanec@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \webservice_elediamcp\local\server
 */
final class tool_error_event_test extends advanced_testcase {
    /**
     * Run the fixture tool through the server and collect the answer and the event.
     *
     * @param string $mode What the fixture does.
     * @param string $lang The user's language.
     * @return array{0: array, 1: array} The decoded answer and the `other` data of the tool_invoked event.
     */
    private function call(string $mode, string $lang = 'en'): array {
        global $CFG;
        require_once($CFG->dirroot . '/webservice/elediamcp/tests/fixtures/failing_tool_fixture.php');
        $user = $this->getDataGenerator()->create_user(['lang' => $lang]);
        $this->setUser($user);

        $server = new server(WEBSERVICE_AUTHMETHOD_PERMANENT_TOKEN);
        $reflection = new ReflectionClass($server);
        foreach (
            [
                'parameters' => ['mode' => $mode],
                'token' => '',
                'userid' => (int) $user->id,
                'mcprequest' => new request(['jsonrpc' => '2.0', 'method' => 'tools/call', 'id' => 1, 'params' => []]),
            ] as $name => $value
        ) {
            $property = $reflection->getProperty($name);
            $property->setAccessible(true);
            $property->setValue($server, $value);
        }

        registry::set_test_tools([\webservice_elediamcp\tests\fixtures\failing_tool_fixture::class]);
        $sink = $this->redirectEvents();
        try {
            $method = $reflection->getMethod('dispatch_ai_tool');
            $method->setAccessible(true);
            ob_start();
            $method->invoke($server, \webservice_elediamcp\tests\fixtures\failing_tool_fixture::class);
            $answer = json_decode(ob_get_clean(), true);
        } finally {
            registry::set_test_tools([]);
        }
        $events = array_values(array_filter($sink->get_events(), fn($event) => $event instanceof tool_invoked));
        $this->assertCount(1, $events);
        return [$answer, $events[0]->other];
    }

    /**
     * A business error: code and message reach the event, the answer is unchanged.
     */
    public function test_a_business_error_is_logged_with_code_and_message(): void {
        $this->resetAfterTest();
        [$answer, $other] = $this->call('business');

        $this->assertTrue($other['iserror']);
        $this->assertSame('toolerror', $other['errorcode']);
        $this->assertSame('No quiz with cmid 7 is visible to you.', $other['errormessage']);
        $this->assertArrayNotHasKey('exceptionclass', $other);
        $this->assertTrue($answer['result']['isError']);
    }

    /**
     * A long message is cut to 200 characters.
     */
    public function test_a_long_message_is_cut(): void {
        $this->resetAfterTest();
        [, $other] = $this->call('long');

        $this->assertSame(200, \core_text::strlen($other['errormessage']));
    }

    /**
     * An unexpected exception names its class, and neither message nor file.
     */
    public function test_an_unexpected_exception_logs_only_its_class(): void {
        $this->resetAfterTest();
        [$answer, $other] = $this->call('php');
        // The server writes the full exception to the debug log; that stays as it was.
        $this->assertDebuggingCalled();

        $this->assertSame('Error', $other['exceptionclass']);
        $this->assertSame('internal', $other['errorcode']);
        $this->assertArrayNotHasKey('errormessage', $other);
        $this->assertStringNotContainsString('secret_function', json_encode($other));
        $this->assertStringNotContainsString('/var/www', json_encode($other));

        $text = json_encode($answer);
        $structured = $answer['result']['structuredContent'];
        $structured = $structured['result'] ?? $structured;
        $this->assertSame('Error', $structured['exception_class']);
        $this->assertStringNotContainsString('secret_function', $text);
        $this->assertStringNotContainsString('/var/www', $text);
    }

    /**
     * A call that works adds nothing about errors.
     */
    public function test_a_successful_call_has_no_error_data(): void {
        $this->resetAfterTest();
        [, $other] = $this->call('ok');

        $this->assertFalse($other['iserror']);
        $this->assertArrayNotHasKey('errorcode', $other);
        $this->assertArrayNotHasKey('errormessage', $other);
        $this->assertArrayNotHasKey('exceptionclass', $other);
    }

    /**
     * The structured result of an error answer.
     *
     * @param array $answer The decoded answer.
     * @return array
     */
    private function structured(array $answer): array {
        $structured = $answer['result']['structuredContent'];
        return $structured['result'] ?? $structured;
    }

    /**
     * An error meant for the person carries its own text and is not retryable (#193).
     */
    public function test_an_error_for_the_person_carries_its_text(): void {
        $this->resetAfterTest();
        [$answer, $other] = $this->call('finished');
        $result = $this->structured($answer);

        $this->assertSame(
            'Question 3 of this attempt has already been checked. Its answer can no longer be changed.',
            $result['user_message']
        );
        $this->assertFalse($result['retryable']);
        // What the model reads, and the event, stay as they were (#182).
        $this->assertSame('The question in slot 3 was already checked; its answer can no longer be changed.', $result['error']);
        $this->assertSame('question_finished', $other['errorcode']);
        $this->assertTrue($answer['result']['isError']);
    }

    /**
     * Errors without a text for the person are unchanged, so the model can still correct its arguments (#193).
     */
    public function test_other_errors_are_unchanged(): void {
        $this->resetAfterTest();
        foreach (['badargument', 'business'] as $mode) {
            [$answer] = $this->call($mode);
            $result = $this->structured($answer);
            $this->assertArrayNotHasKey('user_message', $result, $mode);
            $this->assertArrayNotHasKey('retryable', $result, $mode);
        }
    }

    /**
     * A tool can mark an error as worth repeating with corrected arguments: the flag moves out of the
     * context, and there is still no text for the person, so the agent lets the model correct it (#195).
     */
    public function test_a_retryable_error_has_the_flag_beside_the_error(): void {
        $this->resetAfterTest();
        [$answer] = $this->call('retryable');
        $result = $this->structured($answer);

        $this->assertTrue($result['retryable']);
        $this->assertArrayNotHasKey('user_message', $result);
        $this->assertArrayNotHasKey('retryable', $result['context']);
        $this->assertSame('invalid_answer', $result['context']['errorcode']);
        $this->assertSame([['value' => '1', 'label' => 'True']], $result['context']['options']);
    }

    /**
     * Every text for the person exists in English and German with the same placeholders (#193).
     *
     * The German pack is not installed in the test site, so the files are read directly.
     */
    public function test_every_user_message_exists_in_both_languages(): void {
        global $CFG;
        $strings = [];
        foreach (['en', 'de'] as $lang) {
            $string = [];
            require($CFG->dirroot . '/webservice/elediamcp/lang/' . $lang . '/webservice_elediamcp.php');
            $strings[$lang] = array_filter(
                $string,
                fn($key) => str_starts_with($key, 'usermessage_') || str_starts_with($key, 'cardsummary_'),
                ARRAY_FILTER_USE_KEY
            );
        }
        $this->assertNotEmpty($strings['en']);
        $this->assertSame(array_keys($strings['en']), array_keys($strings['de']));
        foreach ($strings['en'] as $key => $english) {
            preg_match_all('/\{\$a->(\w+)\}/', $english, $en);
            preg_match_all('/\{\$a->(\w+)\}/', $strings['de'][$key], $de);
            $this->assertSame($en[1], $de[1], $key);
        }
        foreach (['attempt_not_finished', 'not_checkable', 'question_finished'] as $code) {
            $this->assertArrayHasKey('usermessage_' . $code, $strings['en']);
        }
    }
}

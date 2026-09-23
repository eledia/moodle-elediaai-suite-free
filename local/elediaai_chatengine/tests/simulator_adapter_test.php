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
use local_elediaai_chatengine\adapter\chat_response;
use local_elediaai_chatengine\adapter\simulator_adapter;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The backend that answers without a language model.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(simulator_adapter::class)]
final class simulator_adapter_test extends \advanced_testcase {
    /**
     * Off unless an administrator switched it on.
     *
     * The default matters more than it looks: this backend is reachable from
     * the browser, and a site that grew one by accident would answer learners
     * with invented text.
     *
     * @return void
     */
    public function test_it_is_off_by_default(): void {
        $this->resetAfterTest();

        $this->assertFalse(simulator_adapter::is_enabled());
        $this->assertFalse((new simulator_adapter())->is_available());
    }

    /**
     * Switched on, it takes over from the destination's backend.
     *
     * @return void
     */
    public function test_switching_it_on_makes_it_the_active_backend(): void {
        $this->resetAfterTest();

        $this->assertNotSame('simulator', backend_resolver::active_id());

        set_config('simulator', 1, 'local_elediaai_chatengine');

        $this->assertSame('simulator', backend_resolver::active_id());
        $this->assertInstanceOf(simulator_adapter::class, backend_resolver::active());
    }

    /**
     * Every answer says that it is not a real one.
     *
     * Checked on the plain answer and on each scripted variant, because the
     * marker is the whole reason this backend is allowed to be reachable from
     * a browser at all.
     *
     * @return void
     */
    public function test_every_answer_is_marked_as_simulated(): void {
        $this->resetAfterTest();
        set_config('simulator', 1, 'local_elediaai_chatengine');
        $adapter = new simulator_adapter();
        $marker = get_string('simulator_marker', 'local_elediaai_chatengine');

        foreach (['', '/help', '/sources', '/error', '/long', '/confirm', '/tokens'] as $command) {
            $answer = $adapter->chat(new chat_request(usermessage: 'Frage ' . $command))->answer;
            $this->assertStringContainsString(
                $marker,
                $answer,
                "The '{$command}' variant produced an answer without the simulated marker."
            );
        }
    }

    /**
     * The keywords reach the states someone wants to look at.
     *
     * @return void
     */
    public function test_the_keywords_reach_their_states(): void {
        $this->resetAfterTest();
        set_config('simulator', 1, 'local_elediaai_chatengine');
        $adapter = new simulator_adapter();

        $sources = $adapter->chat(new chat_request(usermessage: 'zeig mir /sources bitte'));
        $this->assertCount(3, $sources->sources);
        $this->assertSame(chat_response::ORIGIN_GROUNDED, $sources->origin);
        $this->assertFalse($sources->iserror);

        $error = $adapter->chat(new chat_request(usermessage: '/error'));
        $this->assertTrue($error->iserror);

        $confirm = $adapter->chat(new chat_request(usermessage: '/confirm'));
        $this->assertIsArray($confirm->confirmation);

        $tokens = $adapter->chat(new chat_request(usermessage: '/tokens'));
        $this->assertSame(1234, $tokens->prompttokens);
        $this->assertSame(567, $tokens->completiontokens);

        $plain = $adapter->chat(new chat_request(usermessage: 'Guten Tag'));
        $this->assertSame([], $plain->sources);
        $this->assertNull($plain->confirmation);
        $this->assertFalse($plain->iserror);
    }

    /**
     * The German keywords do the same as the English ones.
     *
     * @return void
     */
    public function test_the_german_keywords_work_too(): void {
        $this->resetAfterTest();
        set_config('simulator', 1, 'local_elediaai_chatengine');
        $adapter = new simulator_adapter();

        $this->assertCount(3, $adapter->chat(new chat_request(usermessage: '/quellen'))->sources);
        $this->assertTrue($adapter->chat(new chat_request(usermessage: '/fehler'))->iserror);
    }

    /**
     * A word that only looks like a keyword is answered plainly.
     *
     * Otherwise a learner writing about "/etc" or a path would trip a state
     * they did not ask for.
     *
     * @return void
     */
    public function test_an_unknown_slash_word_is_not_a_keyword(): void {
        $this->resetAfterTest();
        set_config('simulator', 1, 'local_elediaai_chatengine');

        $response = (new simulator_adapter())->chat(new chat_request(usermessage: 'schau in /etc nach'));

        $this->assertSame([], $response->sources);
        $this->assertFalse($response->iserror);
    }

    /**
     * The answer is handed out in fragments, and they add up to it.
     *
     * @return void
     */
    public function test_the_answer_is_streamed_in_fragments(): void {
        $this->resetAfterTest();
        set_config('simulator', 1, 'local_elediaai_chatengine');

        $fragments = [];
        $response = (new simulator_adapter())->chat(new chat_request(
            usermessage: 'Guten Tag',
            ondelta: static function (string $text, bool $reset) use (&$fragments): void {
                $fragments[] = $text;
            },
        ));

        $this->assertGreaterThan(1, count($fragments));
        $this->assertSame($response->answer, implode('', $fragments));
    }

    /**
     * The pairing canary does not count the simulator.
     *
     * It reads from nothing, so it is neither half of the sink/adapter pair
     * the canary watches. Pinned because an exception nobody can see is an
     * exception somebody will remove.
     *
     * @return void
     */
    public function test_the_pairing_canary_ignores_the_simulator(): void {
        $this->resetAfterTest();
        set_config('simulator', 1, 'local_elediaai_chatengine');

        $this->assertArrayNotHasKey('simulator', backend_resolver::paired_ids());
    }
}

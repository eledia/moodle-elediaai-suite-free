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

use local_elediaai_chatengine\external\send_message;
use local_elediaai_chatengine\placement\registry;
use local_elediaai_chatengine\tests\fixtures\fake_adapter;
use local_elediaai_chatengine\tests\fixtures\fake_placement;

/**
 * The shared endpoint, and what a client may ask a turn to carry.
 *
 * The panel every placement renders sends more than the message: the answer
 * style a learner picked, the intent behind a starter question, the button
 * pressed on a confirmation card. None of it was declared here, so Moodle
 * refused the whole call for an unexpected key - and because the streamed path
 * read its parameters from its own list, the same turn succeeded there and
 * failed here depending on a site setting.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_chatengine\external\send_message
 */
final class send_message_test extends \advanced_testcase {
    /** @var string The component the fake placement registers as. */
    private const COMPONENT = 'local_elediaai_chatengine';

    /** @var fake_adapter The backend standing in for a real one. */
    private fake_adapter $adapter;

    #[\Override]
    public static function setUpBeforeClass(): void {
        global $CFG;

        require_once($CFG->dirroot . '/local/elediaai_chatengine/tests/fixtures/fake_adapter.php');
        require_once($CFG->dirroot . '/local/elediaai_chatengine/tests/fixtures/fake_placement.php');
        parent::setUpBeforeClass();
    }

    #[\Override]
    protected function tearDown(): void {
        backend_resolver::override_for_testing(null);
        registry::override_for_testing(self::COMPONENT, null);
        parent::tearDown();
    }

    /**
     * Install a backend and a placement, and log somebody in.
     *
     * @param array $placementoptions The hints the placement adds of its own.
     * @return fake_placement The installed placement.
     */
    private function install(array $placementoptions = []): fake_placement {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->adapter = new fake_adapter();
        $placement = new fake_placement(
            \context_system::instance(),
            options: $placementoptions
        );
        backend_resolver::override_for_testing($this->adapter);
        registry::override_for_testing(self::COMPONENT, $placement);

        return $placement;
    }

    /**
     * A placement that sends no hints reaches the backend with none.
     *
     * The whole point of the defaults: `mod_elli` and `mod_aichat` never ask
     * for a style or an intent, and the arguments their turns produce must be
     * the ones they produced before hints existed.
     *
     * @return void
     */
    public function test_a_placement_without_hints_is_unchanged(): void {
        $this->install();

        $result = send_message::execute(self::COMPONENT, 0, 'Was ist Photosynthese?');

        $this->assertNotSame('', $result['answerhtml']);
        $this->assertSame([], $this->adapter->lastrequest->options);
    }

    /**
     * What the client asked for arrives at the backend.
     *
     * The regression itself: before this, the call was refused outright for
     * naming keys the endpoint did not declare.
     *
     * @return void
     */
    public function test_the_hints_a_client_asks_for_travel_with_the_turn(): void {
        $this->install();

        send_message::execute(
            self::COMPONENT,
            0,
            'Wie rechne ich das?',
            answerstyle: 'hint',
            intent: 'action',
            pendingdecision: 'confirm'
        );

        $request = $this->adapter->lastrequest;
        $this->assertSame('hint', $request->option('answerstyle'));
        $this->assertSame('action', $request->option('intent'));
        // A confirmation card belongs to the panel that showed it, so its
        // answer travels for every placement, claimed by none of them.
        $this->assertSame('confirm', $request->option('pendingdecision'));
    }

    /**
     * The placement is offered the wish and has the last word on it.
     *
     * @return void
     */
    public function test_the_placement_overrules_the_hint_it_answers_for(): void {
        $placement = $this->install(['answerstyle' => 'quiz']);

        send_message::execute(self::COMPONENT, 0, 'Frag mich ab', answerstyle: 'explain', intent: 'auto');

        // It was asked...
        $this->assertSame('explain', $placement->clienthints['answerstyle']);
        // ...and it decided otherwise, which is how an instance keeps a locked
        // setting locked against a request somebody edited by hand.
        $this->assertSame('quiz', $this->adapter->lastrequest->option('answerstyle'));
        // A hint it does not answer for is left as the client asked.
        $this->assertSame('auto', $this->adapter->lastrequest->option('intent'));
    }

    /**
     * A value outside the vocabulary never reaches the backend.
     *
     * The parameter type admits any short identifier, so the check that
     * matters is the vocabulary behind it.
     *
     * @return void
     */
    public function test_an_invented_value_is_dropped_not_forwarded(): void {
        $this->install();

        send_message::execute(self::COMPONENT, 0, 'Frage', answerstyle: 'root', intent: 'sudo');

        $this->assertSame([], $this->adapter->lastrequest->options);
    }
}

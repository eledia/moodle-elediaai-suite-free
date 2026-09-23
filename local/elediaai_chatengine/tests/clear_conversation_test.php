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

use local_elediaai_chatengine\external\clear_conversation;
use local_elediaai_chatengine\local\thread_store;
use local_elediaai_chatengine\placement\registry;
use local_elediaai_chatengine\tests\fixtures\fake_placement;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Who may empty a conversation, and who may not any more.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(clear_conversation::class)]
final class clear_conversation_test extends \advanced_testcase {
    /** @var string The component the fake placement answers for. */
    private const COMPONENT = 'local_elediaai_chatengine';

    /**
     * Load the fixture and hand back a user with one conversation.
     *
     * @param bool $mayclear What the placement answers.
     * @return array{0: \stdClass, 1: \stdClass} The user and their thread.
     */
    private function conversation(bool $mayclear): array {
        global $CFG;
        require_once($CFG->dirroot . '/local/elediaai_chatengine/tests/fixtures/fake_placement.php');

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        registry::override_for_testing(self::COMPONENT, new fake_placement(
            \context_system::instance(),
            mayclear: $mayclear
        ));

        $thread = thread_store::create(
            self::COMPONENT,
            0,
            (int) \context_system::instance()->id,
            0,
            (int) $user->id,
            null,
            'literag',
            'grounded',
            'hash'
        );
        thread_store::add_message((int) $thread->id, 'user', 'Meine Abgabe.');

        return [$user, $thread];
    }

    /**
     * An ordinary conversation is the reader's own to throw away.
     *
     * @return void
     */
    public function test_a_conversation_can_be_cleared(): void {
        $this->resetAfterTest();
        [, $thread] = $this->conversation(true);

        $result = clear_conversation::execute(self::COMPONENT, 0, (int) $thread->id);

        $this->assertTrue($result['cleared']);
        $this->assertSame([], thread_store::messages((int) $thread->id));
    }

    /**
     * A placement that says no is obeyed, and nothing is lost.
     *
     * The endpoint is reachable without the panel, so refusing here is the
     * rule; hiding the button is only a courtesy to the reader.
     *
     * @return void
     */
    public function test_a_placement_can_refuse_and_the_turns_survive(): void {
        $this->resetAfterTest();
        [, $thread] = $this->conversation(false);

        try {
            clear_conversation::execute(self::COMPONENT, 0, (int) $thread->id);
            $this->fail('Clearing was permitted although the placement refused it.');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString('error_clearnotallowed', $e->errorcode);
        }

        $this->assertCount(1, thread_store::messages((int) $thread->id));
    }
}

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

use local_elediaai_chatengine\adapter\chat_response;
use local_elediaai_chatengine\external\load_history;
use local_elediaai_chatengine\external\send_message;
use local_elediaai_chatengine\local\message;
use local_elediaai_chatengine\local\mode;
use local_elediaai_chatengine\local\thread_store;
use local_elediaai_chatengine\placement\registry;
use local_elediaai_chatengine\tests\fixtures\fake_adapter;
use local_elediaai_chatengine\tests\fixtures\fake_placement;

/**
 * What a reloaded conversation still knows about its answers.
 *
 * A grounding badge and a source list are the suite's claim that an answer came
 * out of the course material. The turns were stored with their citations from
 * the start, but this endpoint never handed them back, so the claim survived
 * exactly as long as the page was not reloaded - after F5 a cited answer looked
 * indistinguishable from one the model made up.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_chatengine\external\load_history
 */
final class load_history_test extends \advanced_testcase {
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
     * Install a backend that answers with the given response, and log somebody in.
     *
     * @param chat_response|null $response What the backend should answer.
     * @return void
     */
    private function install(?chat_response $response = null): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->adapter = new fake_adapter($response ?? new chat_response('geantwortet'));
        backend_resolver::override_for_testing($this->adapter);
        registry::override_for_testing(self::COMPONENT, new fake_placement(\context_system::instance()));
    }

    /**
     * A citation given when the answer was fresh is still there after a reload.
     *
     * The regression itself: the turn kept its sources in the database while
     * this endpoint declared only role, html and timecreated, so they never
     * reached the panel that had to render them.
     *
     * @return void
     */
    public function test_a_restored_answer_keeps_its_sources(): void {
        $sources = [
            ['title' => 'Kapitel 3', 'url' => 'https://example.org/3', 'snippet' => 'Ein Auszug'],
            ['title' => 'Kapitel 4', 'url' => 'https://example.org/4', 'snippet' => 'Noch einer'],
        ];
        $this->install(new chat_response(
            'Steht in Ihrem Kurs.',
            sources: $sources,
            origin: chat_response::ORIGIN_GROUNDED
        ));

        $fresh = send_message::execute(self::COMPONENT, 0, 'Was steht in Abschnitt 2?');
        $restored = load_history::execute(self::COMPONENT, 0);

        $answer = $this->assistant_turn($restored);
        $this->assertCount(2, $answer['sources'], 'The restored turn lost the citations it was sent with.');
        $this->assertSame('Kapitel 3', $answer['sources'][0]['title']);
        $this->assertSame('https://example.org/3', $answer['sources'][0]['url']);
        // What the panel showed fresh and what it shows restored is the same
        // claim, so the two endpoints have to agree on it.
        $this->assertSame(
            count($fresh['sources']),
            count($answer['sources']),
            'The buffered turn and the restored turn disagree on how many sources there are.'
        );
    }

    /**
     * The origin badge survives a reload too.
     *
     * @return void
     */
    public function test_a_restored_answer_keeps_its_origin(): void {
        $this->install(new chat_response(
            'Steht in Ihrem Kurs.',
            sources: [['title' => 'Kapitel 3', 'url' => 'https://example.org/3', 'snippet' => '…']],
            origin: chat_response::ORIGIN_GROUNDED
        ));

        send_message::execute(self::COMPONENT, 0, 'Was steht in Abschnitt 2?');

        $answer = $this->assistant_turn(load_history::execute(self::COMPONENT, 0));
        $this->assertSame(chat_response::ORIGIN_GROUNDED, $answer['origin']);
    }

    /**
     * An answer read out of Moodle keeps its own origin, though it cites nothing.
     *
     * This is why the origin is stored rather than derived from the presence of
     * citations: an MCP answer deliberately carries none, so deriving it would
     * relabel it as the model's own knowledge on every reload.
     *
     * @return void
     */
    public function test_an_mcp_answer_is_not_mistaken_for_general_knowledge(): void {
        $this->install(new chat_response(
            'Ihre nächste Abgabe ist am Freitag.',
            origin: chat_response::ORIGIN_MCP
        ));

        send_message::execute(self::COMPONENT, 0, 'Wann ist meine nächste Abgabe?');

        $answer = $this->assistant_turn(load_history::execute(self::COMPONENT, 0));
        $this->assertSame([], $answer['sources']);
        $this->assertSame(chat_response::ORIGIN_MCP, $answer['origin']);
    }

    /**
     * An ungrounded answer stays ungrounded, and cites nothing.
     *
     * @return void
     */
    public function test_a_general_answer_carries_no_sources(): void {
        $this->install(new chat_response('Allgemein gesprochen …', origin: chat_response::ORIGIN_GENERAL));

        send_message::execute(self::COMPONENT, 0, 'Was ist Photosynthese?');

        $answer = $this->assistant_turn(load_history::execute(self::COMPONENT, 0));
        $this->assertSame([], $answer['sources']);
        $this->assertSame(chat_response::ORIGIN_GENERAL, $answer['origin']);
    }

    /**
     * A turn stored before the origin was kept comes back without one.
     *
     * Those turns must not be guessed at after the fact: the panel shows no
     * badge for them rather than a wrong one.
     *
     * @return void
     */
    public function test_a_turn_from_before_the_upgrade_has_no_origin(): void {
        global $USER;

        $this->install();

        // The argument order matters here: the thread has to belong to the
        // logged-in user and the fake placement's context, or load_history
        // looks for a current conversation and correctly finds none.
        $thread = thread_store::open(
            self::COMPONENT,
            0,
            (int) \context_system::instance()->id,
            0,
            (int) $USER->id,
            null,
            'fake',
            mode::GROUNDED,
            'h'
        );
        thread_store::add_message((int) $thread->id, message::ROLE_USER, 'Eine alte Frage');
        thread_store::add_message((int) $thread->id, message::ROLE_ASSISTANT, 'Eine alte Antwort');

        $answer = $this->assistant_turn(load_history::execute(self::COMPONENT, 0));
        $this->assertSame('', $answer['origin']);
        $this->assertSame([], $answer['sources']);
    }

    /**
     * A user turn carries neither, and says so rather than omitting the keys.
     *
     * @return void
     */
    public function test_a_user_turn_carries_neither(): void {
        $this->install(new chat_response('Antwort', origin: chat_response::ORIGIN_GENERAL));

        send_message::execute(self::COMPONENT, 0, 'Eine Frage');

        $history = load_history::execute(self::COMPONENT, 0);
        $question = $history['messages'][0];
        $this->assertSame(message::ROLE_USER, $question['role']);
        $this->assertArrayHasKey('sources', $question);
        $this->assertSame([], $question['sources']);
        $this->assertSame('', $question['origin']);
    }

    /**
     * The declared return structure matches what execute() actually returns.
     *
     * The mismatch this guards against is exactly the one that caused the bug:
     * a field produced but not declared is stripped before it reaches a client,
     * silently and without an error anywhere.
     *
     * @return void
     */
    public function test_the_declared_structure_carries_the_grounding_fields(): void {
        $this->install(new chat_response(
            'Steht in Ihrem Kurs.',
            sources: [['title' => 'Kapitel 3', 'url' => 'https://example.org/3', 'snippet' => '…']],
            origin: chat_response::ORIGIN_GROUNDED
        ));

        send_message::execute(self::COMPONENT, 0, 'Was steht in Abschnitt 2?');

        $returned = \core_external\external_api::clean_returnvalue(
            load_history::execute_returns(),
            load_history::execute(self::COMPONENT, 0)
        );

        $answer = $this->assistant_turn($returned);
        $this->assertSame(chat_response::ORIGIN_GROUNDED, $answer['origin']);
        $this->assertCount(1, $answer['sources']);
        $this->assertSame('Kapitel 3', $answer['sources'][0]['title']);
    }

    /**
     * The first assistant turn of a returned conversation.
     *
     * @param array $history What load_history returned.
     * @return array The assistant turn.
     */
    private function assistant_turn(array $history): array {
        foreach ($history['messages'] as $item) {
            if ($item['role'] === message::ROLE_ASSISTANT) {
                return $item;
            }
        }

        $this->fail('The restored conversation contains no assistant turn.');
    }
}

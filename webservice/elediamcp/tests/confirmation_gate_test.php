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
use webservice_elediamcp\local\confirmation_gate;

/**
 * Server-side confirmation of write tools (#168).
 *
 * @package     webservice_elediamcp
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class confirmation_gate_test extends advanced_testcase {
    /** @var array A preview as the write tools return it. */
    private const PREVIEW = ['requires_confirmation' => true, 'created' => false, 'summary' => 'Aufgabe anlegen'];

    /** @var array Arguments of the previewed call. */
    private const ARGS = ['course_id' => 49, 'type' => 'assign', 'name' => 'Uebungsblatt 2'];

    /**
     * Preview handed out on the previous answer, then the person answers.
     *
     * @param int $userid The person.
     * @param string $message What they send next.
     * @param string $decision Card decision, or ''.
     * @param string $tool The tool.
     * @param array $args The previewed arguments.
     * @param array|null $card The answer's confirmation payload; by default a card for the tool.
     */
    private function preview_then(
        int $userid,
        string $message,
        string $decision = '',
        string $tool = 'moodle_create_activity',
        array $args = self::ARGS,
        ?array $card = ['required' => true, 'tooltitle' => '']
    ): void {
        if ($card !== null && ($card['tooltitle'] ?? '') === '') {
            $card['tooltitle'] = $tool;
        }
        confirmation_gate::user_turn($userid, 'Lege Uebungsblatt 2 an');
        confirmation_gate::observe($tool, $args, self::PREVIEW, $userid);
        confirmation_gate::answer_shown($userid, $card);
        $this->waitForSecond();
        confirmation_gate::user_turn($userid, $message, $decision);
    }

    /**
     * Ask the gate whether confirm=true would pass.
     *
     * @param int $userid The person.
     * @param array $args The confirming call's arguments (confirm is added).
     * @param string $tool The tool.
     * @return bool
     */
    private function passes(int $userid, array $args = self::ARGS, string $tool = 'moodle_create_activity'): bool {
        [$arguments, $withheld] = confirmation_gate::guard($tool, $args + ['confirm' => true], $userid);
        $this->assertSame(!$withheld, (bool) $arguments['confirm']);
        return !$withheld;
    }

    public function test_confirm_without_any_preview_is_withheld(): void {
        $this->resetAfterTest();
        $this->assertFalse($this->passes(2), 'the 22.10. case: confirm=true on the first call');
    }

    public function test_calls_without_confirm_pass_untouched(): void {
        $this->resetAfterTest();
        $this->assertSame([self::ARGS, false], confirmation_gate::guard('moodle_create_activity', self::ARGS, 2));
    }

    public function test_typed_agreement_approves_exactly_once(): void {
        $this->resetAfterTest();
        $this->preview_then(2, 'Ja, fortfahren.');
        $this->assertTrue($this->passes(2));
        $this->assertFalse($this->passes(2), 'an agreement is used up');
    }

    public function test_changed_arguments_fall_back_to_the_preview(): void {
        $this->resetAfterTest();
        $this->preview_then(2, 'ja');
        $this->assertFalse($this->passes(2, ['name' => 'Uebungsblatt 3'] + self::ARGS));
    }

    public function test_key_order_and_preview_tokens_do_not_matter(): void {
        $this->resetAfterTest();
        $this->preview_then(2, 'ok');
        $this->assertTrue($this->passes(2, array_reverse(self::ARGS, true) + ['draft_token' => 'abc']));
    }

    public function test_a_new_request_is_no_agreement(): void {
        $this->resetAfterTest();
        $this->preview_then(2, 'Ja, aber nimm drei Fragen statt fuenf');
        $this->assertFalse($this->passes(2));
    }

    public function test_a_typed_yes_without_a_card_approves_nothing(): void {
        $this->resetAfterTest();
        // The model fetched a preview but asked something else in plain text.
        $this->preview_then(2, 'ja', '', 'moodle_create_activity', self::ARGS, null);
        $this->assertFalse($this->passes(2));
    }

    public function test_an_answer_without_card_resets_an_earlier_card(): void {
        $this->resetAfterTest();
        confirmation_gate::user_turn(2, 'Lege Uebungsblatt 2 an');
        confirmation_gate::observe('moodle_create_activity', self::ARGS, self::PREVIEW, 2);
        confirmation_gate::answer_shown(2, ['required' => true, 'tooltitle' => 'moodle_create_activity']);
        confirmation_gate::answer_shown(2, null);
        $this->waitForSecond();
        confirmation_gate::user_turn(2, 'ja');
        $this->assertFalse($this->passes(2));
    }

    public function test_a_card_for_another_tool_approves_nothing(): void {
        $this->resetAfterTest();
        $card = ['required' => true, 'tooltitle' => 'moodle_update_course'];
        $this->preview_then(2, 'Ausfuehren', 'confirm', 'moodle_create_activity', self::ARGS, $card);
        $this->assertFalse($this->passes(2), 'click on a card of another tool');

        $this->preview_then(2, 'ja', '', 'moodle_create_activity', self::ARGS, $card);
        $this->assertFalse($this->passes(2), 'typed yes to a card of another tool');
    }

    public function test_a_card_without_title_still_approves_the_only_preview(): void {
        $this->resetAfterTest();
        $this->preview_then(2, 'Ausfuehren', 'confirm', 'moodle_create_activity', self::ARGS, ['required' => true]);
        $this->assertTrue($this->passes(2));
    }

    public function test_a_confirming_call_without_the_lock_is_withheld_and_keeps_the_approval(): void {
        global $CFG;
        $this->resetAfterTest();
        // The database lock of PostgreSQL is re-entrant within one connection; a file lock is not.
        $CFG->lock_factory = '\core\lock\file_lock_factory';
        $this->preview_then(2, 'ja');
        $factory = \core\lock\lock_config::get_lock_factory('webservice_elediamcp_confirmations');
        $held = $factory->get_lock('person2', 1);
        $this->assertNotFalse($held);
        try {
            $this->assertFalse($this->passes(2), 'someone else is using this person\'s state');
        } finally {
            $held->release();
        }
        $this->assertDebuggingCalled();
        $this->assertTrue($this->passes(2), 'the approval was not used up');
    }

    public function test_decline_on_the_card_withholds(): void {
        $this->resetAfterTest();
        $this->preview_then(2, 'Abbrechen', 'decline');
        $this->assertFalse($this->passes(2));
    }

    public function test_an_unused_agreement_lapses_with_the_next_message(): void {
        $this->resetAfterTest();
        $this->preview_then(2, 'ja');
        $this->waitForSecond();
        confirmation_gate::user_turn(2, 'Wie viele Abschnitte hat der Kurs?');
        $this->assertFalse($this->passes(2));
    }

    public function test_messages_need_the_card(): void {
        $this->resetAfterTest();
        $args = ['to_user_id' => 7, 'message' => 'Hallo'];
        $this->preview_then(2, 'ja', '', 'moodle_send_message', $args);
        $this->assertFalse($this->passes(2, $args, 'moodle_send_message'), 'typed yes is not enough for a message');

        $this->preview_then(2, 'Ja, senden', 'confirm', 'moodle_send_message', $args);
        $this->assertTrue($this->passes(2, $args, 'moodle_send_message'));
    }

    public function test_literag_replays_a_message_by_resolved_recipient(): void {
        $this->resetAfterTest();
        confirmation_gate::user_turn(2, 'Schreib Anna Muster');
        confirmation_gate::observe(
            'moodle_send_message',
            ['to_user' => 'Anna Muster', 'message' => 'Hallo'],
            self::PREVIEW + ['recipient' => ['id' => 7]],
            2
        );
        confirmation_gate::answer_shown(2, ['required' => true, 'tooltitle' => 'moodle_send_message']);
        $this->waitForSecond();
        confirmation_gate::user_turn(2, 'Senden', 'confirm');
        $this->assertTrue($this->passes(2, ['to_user_id' => 7, 'message' => 'Hallo'], 'moodle_send_message'));
    }

    public function test_one_person_cannot_agree_for_another(): void {
        $this->resetAfterTest();
        $this->preview_then(2, 'ja');
        $this->assertFalse($this->passes(3));
    }

    public function test_an_old_approval_expires(): void {
        $this->resetAfterTest();
        $this->preview_then(2, 'ja');
        $cache = \cache::make('webservice_elediamcp', 'confirmations');
        $state = $cache->get(2);
        $state['entries'][0]['time'] -= confirmation_gate::WINDOW + 1;
        $cache->set(2, $state);
        $this->assertFalse($this->passes(2));
    }

    /**
     * Two writes previewed in one answer, card for the first (#168, tightened).
     *
     * The agent shows a card only for the first write; a click on it must not
     * also carry the second, which the person never saw on a card.
     *
     * @param array|null $card The confirmation payload of the answer.
     * @param string $decision The person's reaction.
     * @param string $message What they send.
     */
    private function two_previews(?array $card, string $decision = 'confirm', string $message = 'Ja, ausfuehren'): void {
        confirmation_gate::user_turn(2, 'Lege die Aufgabe an und schreib dem Kurs');
        confirmation_gate::observe('moodle_create_activity', self::ARGS, self::PREVIEW, 2);
        confirmation_gate::observe('moodle_update_course', ['course_id' => 49, 'visible' => 0], self::PREVIEW, 2);
        confirmation_gate::answer_shown(2, $card);
        $this->waitForSecond();
        confirmation_gate::user_turn(2, $message, $decision);
    }

    public function test_a_click_approves_only_the_preview_on_the_card(): void {
        $this->resetAfterTest();
        $title = \webservice_elediamcp\local\ai\tools\moodle_create_activity::title();
        $this->two_previews(['required' => true, 'tooltitle' => $title]);
        $this->assertTrue($this->passes(2));
        $this->assertFalse($this->passes(2, ['course_id' => 49, 'visible' => 0], 'moodle_update_course'));
    }

    public function test_the_card_may_name_the_tool_by_its_name(): void {
        $this->resetAfterTest();
        $this->two_previews(['required' => true, 'tooltitle' => 'moodle_update_course']);
        $this->assertTrue($this->passes(2, ['course_id' => 49, 'visible' => 0], 'moodle_update_course'));
        $this->assertFalse($this->passes(2));
    }

    public function test_several_previews_without_a_named_card_approve_nothing(): void {
        $this->resetAfterTest();
        $this->two_previews(null);
        $this->assertFalse($this->passes(2));
        $this->assertFalse($this->passes(2, ['course_id' => 49, 'visible' => 0], 'moodle_update_course'));
    }

    public function test_a_typed_yes_to_several_previews_approves_nothing(): void {
        $this->resetAfterTest();
        $this->two_previews(null, '', 'ja');
        $this->assertFalse($this->passes(2));
    }

    public function test_two_previews_of_the_same_tool_approve_nothing(): void {
        $this->resetAfterTest();
        confirmation_gate::user_turn(2, 'Lege zwei Aufgaben an');
        confirmation_gate::observe('moodle_create_activity', self::ARGS, self::PREVIEW, 2);
        confirmation_gate::observe('moodle_create_activity', ['name' => 'Uebungsblatt 3'] + self::ARGS, self::PREVIEW, 2);
        confirmation_gate::answer_shown(2, ['required' => true, 'tooltitle' => 'moodle_create_activity']);
        $this->waitForSecond();
        confirmation_gate::user_turn(2, 'Ja, ausfuehren', 'confirm');
        $this->assertFalse($this->passes(2), 'which of the two is not clear');
    }

    /**
     * The card shows the tool in the person's language, whether it is named by title or name.
     */
    public function test_display_title_translates_the_card(): void {
        $this->resetAfterTest();
        $expected = get_string('cardtitle_moodle_send_message', 'webservice_elediamcp');
        $title = \webservice_elediamcp\local\ai\tools\moodle_send_message::title();
        $this->assertSame($expected, confirmation_gate::display_title($title));
        $this->assertSame($expected, confirmation_gate::display_title('moodle_send_message'));
        $this->assertNull(confirmation_gate::display_title('Something else entirely'));
        $this->assertNull(confirmation_gate::display_title(''));
    }

    /**
     * The course author lives in local_elediaai_coursegen, not in the registry; its card is translated too (#201).
     */
    public function test_display_title_translates_the_course_author_card(): void {
        $this->resetAfterTest();
        $class = '\\local_elediaai_coursegen\\mcp\\moodle_generate_course';
        if (!class_exists($class)) {
            $this->markTestSkipped('local_elediaai_coursegen is not installed.');
        }
        $expected = get_string('cardtitle_moodle_generate_course', 'webservice_elediamcp');
        $this->assertNotSame('', $expected);
        $this->assertSame($expected, confirmation_gate::display_title($class::title()));
        $this->assertSame($expected, confirmation_gate::display_title('moodle_generate_course'));
        $this->assertSame($expected, confirmation_gate::display_title(' MOODLE_GENERATE_COURSE '));
    }

    /**
     * Only the chat engine's tokens are gated; other MCP clients keep working.
     */
    public function test_token_component_decides_the_gate(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        $serviceid = \webservice_elediamcp\local\token_manager::ensure_default_service_configured();

        $engine = \webservice_elediamcp\api::create_token('local_elediaai_chatengine', (int) $user->id, $serviceid, 'Tutor');
        $other = \webservice_elediamcp\api::create_token('core', (int) $user->id, $serviceid, 'Desktop');

        $this->assertSame(
            confirmation_gate::GATED_COMPONENT,
            \webservice_elediamcp\local\token_manager::get_component_for_token((string) $engine->token)
        );
        $this->assertNotSame(
            confirmation_gate::GATED_COMPONENT,
            \webservice_elediamcp\local\token_manager::get_component_for_token((string) $other->token)
        );
        $this->assertNull(\webservice_elediamcp\local\token_manager::get_component_for_token('nosuchtoken'));
    }

    /**
     * Pure agreements, and what is not one.
     */
    public function test_is_agreement(): void {
        foreach (['ja', 'Ja!', 'Ja, fortfahren.', 'ok, mach das', 'Passt so', 'yes please', 'go ahead', 'Bestätigen'] as $yes) {
            $this->assertTrue(confirmation_gate::is_agreement($yes), $yes);
        }
        foreach (['nein', 'bitte', 'Ja, aber drei Fragen', 'Wie viele Abschnitte?', '', 'ja ja ja ja ja ja'] as $no) {
            $this->assertFalse(confirmation_gate::is_agreement($no), $no);
        }
    }

    /**
     * A question is no agreement, and three words that answer questions as often are not either (#178 F7).
     */
    public function test_questions_and_ambiguous_words_are_no_agreement(): void {
        foreach (['Ja?', 'Ok?', 'Klar?', 'Genau?', 'Ja, machen wir das?', 'Genau', 'Klar', 'Go', 'Genau so'] as $no) {
            $this->assertFalse(confirmation_gate::is_agreement($no), $no);
        }
        foreach (['Ja, genau', 'Ok, klar', 'Ja klar!'] as $yes) {
            $this->assertTrue(confirmation_gate::is_agreement($yes), $yes);
        }
    }

    /**
     * The preview and `confirm=true` in the same turn, as the 22.10. case happened (#178 F6.1).
     */
    public function test_preview_and_confirm_in_the_same_turn_is_withheld(): void {
        $this->resetAfterTest();
        confirmation_gate::user_turn(2, 'Lege Uebungsblatt 2 an');
        confirmation_gate::observe('moodle_create_activity', self::ARGS, self::PREVIEW, 2);
        $this->assertFalse($this->passes(2), 'no answer of the person lies between preview and confirm');
    }

    /**
     * A second "ja" does not renew an approval that is still unused (#178 F6.3).
     */
    public function test_a_second_yes_does_not_renew_an_unused_approval(): void {
        $this->resetAfterTest();
        $this->preview_then(2, 'ja');
        $this->waitForSecond();
        confirmation_gate::user_turn(2, 'ja');
        $this->assertFalse($this->passes(2), 'typed');

        // The same with the click: a second click on a card is not a second approval.
        $this->preview_then(2, 'Ausfuehren', 'confirm');
        $this->waitForSecond();
        confirmation_gate::user_turn(2, 'Ausfuehren', 'confirm');
        $this->assertFalse($this->passes(2), 'click');
    }

    /**
     * Tools that reach other people need the click (#178 F6.4).
     *
     * @dataProvider card_only_tools
     * @param string $tool The tool.
     * @param array $args Its arguments.
     */
    public function test_card_only_tools_need_the_click(string $tool, array $args): void {
        $this->resetAfterTest();
        $this->preview_then(2, 'ja', '', $tool, $args);
        $this->assertFalse($this->passes(2, $args, $tool), 'typed yes');

        $this->preview_then(2, 'Senden', 'confirm', $tool, $args);
        $this->assertTrue($this->passes(2, $args, $tool), 'click');
    }

    /**
     * The tools a typed agreement does not approve.
     *
     * @return array
     */
    public static function card_only_tools(): array {
        return [
            'send_message' => ['moodle_send_message', ['to_user_id' => 7, 'message' => 'Hallo']],
            'message_course_students' => ['moodle_message_course_students', ['course_id' => 49, 'message' => 'Hallo']],
            'grade_submission' => ['moodle_grade_submission', ['assignment_id' => 5, 'user_id' => 7, 'grade' => 80]],
        ];
    }

    /**
     * A preview older than the window cannot be approved any more (#178 F6.5).
     */
    public function test_an_expired_preview_cannot_be_approved(): void {
        $this->resetAfterTest();
        confirmation_gate::user_turn(2, 'Lege Uebungsblatt 2 an');
        confirmation_gate::observe('moodle_create_activity', self::ARGS, self::PREVIEW, 2);
        confirmation_gate::answer_shown(2, ['required' => true, 'tooltitle' => 'moodle_create_activity']);
        $cache = \cache::make('webservice_elediamcp', 'confirmations');
        $state = $cache->get(2);
        $state['entries'][0]['time'] -= confirmation_gate::WINDOW + 1;
        $cache->set(2, $state);
        $this->waitForSecond();
        confirmation_gate::user_turn(2, 'ja');
        $this->assertFalse($this->passes(2));
    }

    /**
     * Without a person nothing is remembered and nothing passes (#178 F6.5).
     */
    public function test_no_person_no_approval(): void {
        $this->resetAfterTest();
        confirmation_gate::user_turn(0, 'Lege Uebungsblatt 2 an');
        confirmation_gate::observe('moodle_create_activity', self::ARGS, self::PREVIEW, 0);
        confirmation_gate::answer_shown(0, ['required' => true, 'tooltitle' => 'moodle_create_activity']);
        confirmation_gate::user_turn(0, 'ja');
        $this->assertFalse($this->passes(0));
    }
}

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
use webservice_elediamcp\local\ai\registry;
use webservice_elediamcp\local\ai\tools;
use webservice_elediamcp\local\confirmation_gate;

/**
 * Previews announce the arguments of the confirmed call in `confirm_args` (#179).
 *
 * @package     webservice_elediamcp
 * @author      Immanuel Pasanec <immanuel.pasanec@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class confirm_args_test extends advanced_testcase {
    /** @var array Arguments of the previewed call. */
    private const ARGS = ['topic' => 'Photosynthese', 'count' => 3];

    /**
     * A preview, then the person's agreement.
     *
     * @param array $confirmargs What the preview announces, or null for no field.
     * @param int $userid The person.
     */
    private function preview_then_agree(?array $confirmargs, int $userid = 2): void {
        $output = ['requires_confirmation' => true, 'created' => false, 'summary' => 'Entwurf'];
        if ($confirmargs !== null) {
            $output['confirm_args'] = $confirmargs;
        }
        confirmation_gate::user_turn($userid, 'Erzeuge den Entwurf');
        confirmation_gate::observe('moodle_future_tool', self::ARGS, $output, $userid);
        confirmation_gate::answer_shown($userid, ['required' => true, 'tooltitle' => 'moodle_future_tool']);
        $this->waitForSecond();
        confirmation_gate::user_turn($userid, 'ja', '');
    }

    /**
     * Whether the confirmed call goes through.
     *
     * @param array $arguments The arguments the agent sends with confirm=true.
     * @param int $userid The person.
     * @return bool
     */
    private function passes(array $arguments, int $userid = 2): bool {
        [, $withheld] = confirmation_gate::guard('moodle_future_tool', $arguments + ['confirm' => true], $userid);
        return !$withheld;
    }

    /**
     * A token name the gate has never heard of passes when the preview announced it.
     */
    public function test_an_announced_argument_stays_out_of_the_fingerprint(): void {
        $this->resetAfterTest();
        $this->preview_then_agree(['render_token' => 'abc123']);
        $this->assertTrue($this->passes(self::ARGS + ['render_token' => 'abc123']));
        $this->assertFalse($this->passes(self::ARGS + ['render_token' => 'abc123']), 'the approval is used up');
    }

    /**
     * The same argument without an announcement changes the fingerprint.
     */
    public function test_an_argument_that_was_not_announced_is_withheld(): void {
        $this->resetAfterTest();
        $this->preview_then_agree(null);
        $this->assertFalse($this->passes(self::ARGS + ['render_token' => 'abc123']));
    }

    /**
     * An announcement does not let other arguments change.
     */
    public function test_an_announcement_does_not_cover_other_arguments(): void {
        $this->resetAfterTest();
        $this->preview_then_agree(['render_token' => 'abc123']);
        $this->assertFalse($this->passes(['topic' => 'Mitose', 'count' => 3, 'render_token' => 'abc123']));
    }

    /**
     * Only the names in confirm_args are left out, not any other extra argument.
     */
    public function test_an_unannounced_extra_argument_is_withheld(): void {
        $this->resetAfterTest();
        $this->preview_then_agree(['render_token' => 'abc123']);
        $this->assertFalse($this->passes(self::ARGS + ['render_token' => 'abc123', 'other' => 'x']));
    }

    /**
     * The fingerprint is the same with and without the announced names.
     */
    public function test_fingerprint_leaves_announced_names_out(): void {
        $with = confirmation_gate::fingerprint(self::ARGS + ['render_token' => 'x'], ['render_token']);
        $this->assertSame(confirmation_gate::fingerprint(self::ARGS), $with);
    }

    /**
     * The H5P author's preview announces its draft token.
     *
     * The tool is only registered with the H5P plugin installed, but its schema can be read
     * without it, so this check does not depend on the test site.
     */
    public function test_h5p_declares_its_draft_token_in_confirm_args(): void {
        $schema = tools\moodle_generate_h5p::output_schema();
        $declared = (array) ($schema['properties']['confirm_args']['properties'] ?? []);
        $this->assertArrayHasKey('draft_token', $declared);
        $this->assertArrayHasKey('draft_token', (array) tools\moodle_generate_h5p::input_schema()['properties']);
    }

    /**
     * Every registered tool that takes a token from its preview announces it in confirm_args.
     *
     * A static check over the catalogue: a tool with a `confirm` argument and an input
     * argument ending in `_token` must declare the same name in the `confirm_args`
     * of its output schema, so a new tool cannot be added without the field.
     */
    public function test_every_tool_with_a_preview_token_declares_confirm_args(): void {
        $this->assertNotEmpty(registry::all());
        foreach (registry::all() as $class) {
            $input = (array) ($class::input_schema()['properties'] ?? []);
            if (!isset($input['confirm'])) {
                continue;
            }
            $tokens = array_filter(array_keys($input), fn($name) => str_ends_with((string) $name, '_token'));
            $declared = (array) ($class::output_schema()['properties']['confirm_args']['properties'] ?? []);
            foreach ($tokens as $token) {
                $this->assertArrayHasKey($token, $declared, $class::name() . " must announce $token in confirm_args");
            }
        }
    }
}

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

namespace block_elediaai_tutor;

use PHPUnit\Framework\Attributes\CoversClass;
use block_elediaai_tutor\local\branding;
use block_elediaai_tutor\local\registry;
use block_elediaai_tutor\local\text;

/**
 * Tests for the multilingual configurable texts (ai_suite#209).
 *
 * @package     block_elediaai_tutor
 * @category    test
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\block_elediaai_tutor\local\text::class)]
final class text_test extends \advanced_testcase {
    /** @var string A greeting in German and English, in the core filter's markup. */
    private const GREETING = '<span lang="de" class="multilang">Hallo!</span>'
        . '<span lang="en" class="multilang">Hello!</span>';

    /**
     * Switch the core language filter on and pick a language for the user.
     *
     * @param string $lang Language code.
     * @return void
     */
    private function use_language(string $lang): void {
        global $SESSION;
        filter_set_global_state('multilang', TEXTFILTER_ON);
        // The core filter only reads the old syntax unless converted.
        set_config('filter_multilang_converted', 1);
        $SESSION->forcelang = $lang;
        \core_filters\filter_manager::reset_caches();
    }

    /**
     * With the filter on, a text resolves to the user's language.
     */
    public function test_localise_picks_the_user_language(): void {
        $this->resetAfterTest();
        $this->use_language('en');
        $this->assertSame('Hello!', text::localise(self::GREETING));
    }

    /**
     * Without a language filter the text is returned untouched, as before.
     */
    public function test_localise_without_filter_leaves_text_alone(): void {
        $this->resetAfterTest();
        filter_set_global_state('multilang', TEXTFILTER_DISABLED);
        $this->assertSame(self::GREETING, text::localise(self::GREETING));
        $this->assertFalse(text::language_filter_enabled());
    }

    /**
     * Plain text never reaches the filter manager and stays as it is.
     */
    public function test_plain_text_is_unchanged(): void {
        $this->resetAfterTest();
        $this->use_language('en');
        $this->assertFalse(text::has_markup('Hallo, {firstname}!'));
        $this->assertSame('Hallo, {firstname}!', text::localise('Hallo, {firstname}!'));
        $this->assertTrue(text::has_markup('{mlang de}Hallo{mlang}'));
    }

    /**
     * Only the language filters run: an active autolinking filter does not
     * reach a tutor text.
     */
    public function test_only_language_filters_apply(): void {
        $this->resetAfterTest();
        $this->use_language('en');
        filter_set_global_state('urltolink', TEXTFILTER_ON);
        $raw = self::GREETING . ' https://example.org';
        $this->assertSame('Hello! https://example.org', text::localise($raw));
    }

    /**
     * The registry, the persona sent to the model and the launcher label read
     * the text in the user's language; the stored value keeps every language.
     */
    public function test_texts_are_resolved_where_they_are_used(): void {
        $this->resetAfterTest();
        $this->use_language('en');
        set_config('persona_instructions', self::GREETING, 'block_elediaai_tutor');
        set_config('brandlaunchlabel', self::GREETING, 'block_elediaai_tutor');

        $this->assertSame('Hello!', registry::effective_text('persona_instructions'));
        $this->assertSame('Hello!', branding::persona([])['instructions']);
        $this->assertSame('Hello!', branding::resolve([])['launchlabel']);
        $this->assertSame(self::GREETING, registry::effective('persona_instructions'));

        // An instance override is localised as well.
        $this->assertSame('Hello!', registry::effective_text('welcomemessage', ['welcomemessage' => self::GREETING]));
    }

    /**
     * A line list keeps one entry per line: each line is resolved on its own,
     * so blocks of two lines are not joined into one group.
     */
    public function test_line_lists_are_resolved_per_line(): void {
        $this->resetAfterTest();
        $this->use_language('en');
        $raw = '<span lang="de" class="multilang">Kurs | Fasse zusammen</span>'
            . '<span lang="en" class="multilang">Course | Summarise</span>' . "\n"
            . 'fa-lightbulb | <span lang="de" class="multilang">Ideen</span><span lang="en" class="multilang">Ideas</span>' . "\n"
            . 'Plain | Unchanged';
        $this->assertSame("Course | Summarise\nfa-lightbulb | Ideas\nPlain | Unchanged", text::localise($raw));
    }

    /**
     * A block that runs over several lines is resolved as a whole.
     */
    public function test_blocks_over_several_lines_are_resolved_whole(): void {
        $this->resetAfterTest();
        $this->use_language('en');
        $raw = "<span lang=\"de\" class=\"multilang\">Hallo!\nWie geht es?</span>"
            . "<span lang=\"en\" class=\"multilang\">Hello!\nHow are you?</span>";
        $this->assertSame("Hello!\nHow are you?", text::localise($raw));
    }

    /**
     * Saving a text keeps the multilang markup, so the other languages are
     * not lost on the way into the configuration.
     */
    public function test_sanitise_keeps_the_markup(): void {
        $this->assertSame(self::GREETING, registry::sanitise('welcomemessage', self::GREETING));
        $this->assertSame(self::GREETING, registry::sanitise('persona', self::GREETING));
    }
}

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

use local_elediaai_chatengine\local\markdown_renderer;

/**
 * An address the assistant writes must be one the reader can click.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_chatengine\local\markdown_renderer
 */
final class markdown_renderer_test extends \advanced_testcase {
    /**
     * Render one piece of assistant Markdown.
     *
     * @param string $markdown The model's output.
     * @return string
     */
    private function render(string $markdown): string {
        return markdown_renderer::render($markdown, \core\context\system::instance());
    }

    /**
     * The case that was reported: an address written plainly.
     *
     * @return void
     */
    public function test_a_bare_address_becomes_a_link(): void {
        $this->resetAfterTest();

        $html = $this->render('Link: https://moodle.example.com/mod/assign/view.php?id=17');

        $this->assertStringContainsString(
            '<a href="https://moodle.example.com/mod/assign/view.php?id=17">',
            $html
        );
    }

    /**
     * A full stop belongs to the sentence, not to the address.
     *
     * @return void
     */
    public function test_trailing_punctuation_stays_outside_the_link(): void {
        $this->resetAfterTest();

        $html = $this->render('Siehe https://example.org/a/b.');

        $this->assertStringContainsString('<a href="https://example.org/a/b">', $html);
        $this->assertStringContainsString('</a>.', $html);
    }

    /**
     * Two addresses in one line are two links.
     *
     * @return void
     */
    public function test_every_address_in_a_line_is_linked(): void {
        $this->resetAfterTest();

        $html = $this->render('a https://example.org/1 und https://example.org/2 b');

        $this->assertSame(2, substr_count($html, '<a href="https://example.org/'));
    }

    /**
     * What was already a link is left as it was.
     *
     * @return void
     */
    public function test_an_existing_link_is_not_touched_twice(): void {
        $this->resetAfterTest();

        $angeklammert = $this->render('[Projektarbeit](https://example.org/x)');
        $spitz = $this->render('Link: <https://example.org/x>');

        $this->assertStringContainsString('>Projektarbeit</a>', $angeklammert);
        $this->assertSame(1, substr_count($angeklammert, '<a '));
        $this->assertSame(1, substr_count($spitz, '<a '));
    }

    /**
     * Code stays code, character for character.
     *
     * @return void
     */
    public function test_addresses_in_code_are_left_alone(): void {
        $this->resetAfterTest();

        $inline = $this->render('Nicht anfassen: `https://example.org/x`');
        $block = $this->render("Vorher\n```\nhttps://example.org/code\n```\nNachher https://example.org/d");

        $this->assertStringNotContainsString('<a ', $inline);
        $this->assertStringContainsString('<code>https://example.org/x</code>', $inline);
        // Im Block nicht, hinter dem Block schon.
        $this->assertSame(1, substr_count($block, '<a '));
        $this->assertStringContainsString('<a href="https://example.org/d">', $block);
    }

    /**
     * Only http and https, and nothing that could execute.
     *
     * Ein Modell darf keine Adresse anbieten, die beim Anklicken etwas
     * ausfuehrt -- deshalb fasst die Umschrift nur die beiden Schemata an,
     * und der Purifier raeumt danach ohnehin auf.
     *
     * @return void
     */
    public function test_no_other_scheme_is_linked(): void {
        $this->resetAfterTest();

        $html = $this->render('javascript:alert(1) und ftp://example.org/x und data:text/html,x');

        $this->assertStringNotContainsString('<a ', $html);
    }

    /**
     * Die Trennzeichen ueberleben den Markdown-Durchlauf.
     *
     * @covers \\local_elediaai_chatengine\\local\\markdown_renderer::render
     */
    public function test_inline_maths_keeps_its_delimiters(): void {
        $html = $this->render('Mit \\(R_\\text{ges} = 1000\\,\\Omega\\) ergibt sich der Strom.');

        $this->assertStringContainsString('\\(R_\\text{ges} = 1000\\,\\Omega\\)', $html);
    }

    /**
     * Auch die abgesetzte Schreibweise und die Dollar-Form.
     *
     * @covers \\local_elediaai_chatengine\\local\\markdown_renderer::render
     */
    public function test_display_maths_keeps_its_delimiters(): void {
        $eckig = $this->render('Es gilt \\[E = mc^2\\] seit 1905.');
        $this->assertStringContainsString('\\[E = mc^2\\]', $eckig);

        $dollar = $this->render('Es gilt $$a^2 + b^2 = c^2$$ im rechten Winkel.');
        $this->assertStringContainsString('$$a^2 + b^2 = c^2$$', $dollar);
    }

    /**
     * Ein einzelnes Dollarzeichen ist ein Geldbetrag, keine Formel.
     *
     * @covers \\local_elediaai_chatengine\\local\\markdown_renderer::render
     */
    public function test_single_dollars_are_not_treated_as_maths(): void {
        $html = $this->render('Das kostet $5, jenes $10.');

        $this->assertStringContainsString('$5', $html);
        $this->assertStringContainsString('$10', $html);
    }

    /**
     * Im Code bleibt die Formel Quelltext.
     *
     * @covers \\local_elediaai_chatengine\\local\\markdown_renderer::render
     */
    public function test_maths_inside_code_is_left_alone(): void {
        $html = $this->render("So schreibt man es:\n\n```\n\\(x^2\\)\n```");

        $this->assertStringContainsString('<code', $html);
        $this->assertStringNotContainsString('ELEDIAAIMATH', $html);
    }

    /**
     * Kleinerzeichen in einer Ungleichung darf kein Tag-Anfang werden.
     *
     * @covers \\local_elediaai_chatengine\\local\\markdown_renderer::render
     */
    public function test_maths_is_escaped_for_html(): void {
        $html = $this->render('Es gilt \\(a < b\\) in diesem Fall.');

        $this->assertStringContainsString('&lt;', $html);
        $this->assertStringNotContainsString('<b\\)', $html);
    }

    /**
     * Kein Platzhalter bleibt im Ergebnis stehen.
     *
     * @covers \\local_elediaai_chatengine\\local\\markdown_renderer::render
     */
    public function test_no_placeholder_survives(): void {
        $html = $this->render('Erst \\(a\\), dann \\[b\\], zuletzt $$c$$.');

        $this->assertStringNotContainsString('ELEDIAAIMATH', $html);
    }

    /**
     * Die Formel traegt die Klasse, nach der der MathJax-Loader sucht.
     *
     * @covers \\local_elediaai_chatengine\\local\\markdown_renderer::render
     */
    public function test_maths_carries_the_loader_class(): void {
        $html = $this->render('Es gilt \\(a + b\\) hier.');

        $this->assertStringContainsString('filter_mathjaxloader_equation', $html);
        $this->assertMatchesRegularExpression(
            '~<span class="filter_mathjaxloader_equation">\\\\\\(a \\+ b\\\\\\)</span>~',
            $html
        );
    }
    /**
     * Eine Liste direkt nach einem Absatz bleibt eine Liste.
     *
     * Der gemeldete Fall aus AI-79. Moodles FORMAT_MARKDOWN ist klassisches
     * Markdown und braucht davor eine Leerzeile; der Browser setzt beim
     * Streaming nach CommonMark, wo die Liste den Absatz unterbrechen darf.
     * Deshalb stimmte die Formatierung, bis die fertige Antwort vom Server
     * kam -- und kippte dann.
     */
    public function test_a_list_right_after_a_paragraph_stays_a_list(): void {
        $this->resetAfterTest();

        $html = markdown_renderer::render(
            "Bitte nenne mir:\n- **Kurstitel**\n- optional: **Kurzname**\n",
            \context_system::instance()
        );

        $this->assertStringContainsString('<ul>', $html);
        $this->assertSame(2, substr_count($html, '<li>'));
        $this->assertStringContainsString('<strong>Kurstitel</strong>', $html);
    }

    /**
     * Eine Nummerierung auch -- aber nur, wenn sie bei 1 beginnt.
     */
    public function test_an_ordered_list_interrupts_only_when_it_starts_at_one(): void {
        $this->resetAfterTest();
        $ctx = \context_system::instance();

        $liste = markdown_renderer::render("Schritte:\n1. eins\n2. zwei\n", $ctx);
        $this->assertSame(2, substr_count($liste, '<li>'));

        // „Das war am 3. Oktober" darf nicht zur Liste werden -- dieselbe
        // Regel, die CommonMark dafuer hat.
        $datum = markdown_renderer::render("Das war am\n3. Oktober\n", $ctx);
        $this->assertStringNotContainsString('<li>', $datum);
    }

    /**
     * Eine dichte Liste bleibt dicht.
     *
     * Eine eingerueckte Fortsetzungszeile gehoert noch zum Listenpunkt. Wer
     * dort eine Leerzeile einzoege, machte aus der dichten eine lockere Liste
     * -- jeder Punkt bekaeme ein eigenes Absatz-Tag.
     */
    public function test_a_tight_list_is_not_loosened(): void {
        $this->resetAfterTest();

        $html = markdown_renderer::render(
            "- eins\n  fortsetzung\n- zwei\n",
            \context_system::instance()
        );

        $this->assertSame(2, substr_count($html, '<li>'));
        $this->assertStringNotContainsString('<p>', $html);
    }

    /**
     * In Code wird nichts abgesetzt.
     */
    public function test_nothing_is_separated_inside_a_code_block(): void {
        $this->resetAfterTest();

        $html = markdown_renderer::render(
            "Text:\n```\n- kein Listenpunkt\n```\n",
            \context_system::instance()
        );

        $this->assertStringNotContainsString('<li>', $html);
        $this->assertStringContainsString('- kein Listenpunkt', $html);
    }
}

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

/**
 * Tests for the help document preparer.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_elediaai_core;

use local_elediaai_core\output\help_document;

/**
 * What a repository document may show a reader.
 *
 * @covers \local_elediaai_core\output\help_document
 */
final class help_document_test extends \advanced_testcase {
    /**
     * The leading Meta block is for the team, not for readers.
     *
     * @return void
     */
    public function test_the_meta_block_is_dropped(): void {
        $roh = "# Benutzer-Dokumentation\n\n## Meta\n\nQuelle der Wahrheit.\n\n---\n\n## Zielgruppen\n\nText.\n";
        $rein = help_document::clean($roh);

        $this->assertStringNotContainsString('## Meta', $rein);
        $this->assertStringNotContainsString('Quelle der Wahrheit', $rein);
        $this->assertStringContainsString('# Benutzer-Dokumentation', $rein);
        $this->assertStringContainsString('## Zielgruppen', $rein);
    }

    /**
     * "Meta" further down is an ordinary heading and stays.
     *
     * @return void
     */
    public function test_a_later_meta_heading_stays(): void {
        $roh = "# Titel\n\n## Erstes\n\nText.\n\n## Meta\n\nHier geht es um Metadaten im Produkt.\n";

        $this->assertStringContainsString('## Meta', help_document::clean($roh));
    }

    /**
     * A reference in brackets falls on its own; the sentence survives.
     *
     * @return void
     */
    public function test_a_bracketed_reference_falls_alone(): void {
        $roh = 'Der Launcher ist ruhig (siehe `00-master.md` adr03). Und der Rest bleibt.';

        $this->assertSame(
            "Der Launcher ist ruhig. Und der Rest bleibt.\n",
            help_document::clean($roh)
        );
    }

    /**
     * A whole sentence that points at a sibling document falls as a sentence.
     *
     * Half a sentence would be worse than the reference it removes.
     *
     * @return void
     */
    public function test_a_referring_sentence_falls_whole(): void {
        $roh = 'Die Funktion erscheint als „In Vorbereitung". Details stehen in `01-features.md`.';

        $this->assertSame("Die Funktion erscheint als „In Vorbereitung\".\n", help_document::clean($roh));
    }

    /**
     * Ordinary prose is not touched.
     *
     * @return void
     */
    public function test_prose_without_references_is_untouched(): void {
        $roh = "Ein Satz ohne Verweis bleibt. Ein zweiter auch.";

        $this->assertSame("Ein Satz ohne Verweis bleibt. Ein zweiter auch.\n", help_document::clean($roh));
    }

    /**
     * Die Seite hat schon eine Ueberschrift, also rutscht das Dokument.
     *
     * Gemessen am 06.09.2026 war die Dokumentueberschrift mit 40px groesser
     * als der Seitentitel mit 32px -- unter Boost wie unter theme_elediaai.
     * Zwei h1 sind ausserdem semantisch falsch.
     *
     * @return void
     */
    public function test_headings_move_down_one_level(): void {
        $roh = "# Titel\n\n## Abschnitt\n\n### Tiefer\n";
        $rein = help_document::clean($roh);

        $this->assertStringContainsString('## Titel', $rein);
        $this->assertStringContainsString('### Abschnitt', $rein);
        $this->assertStringContainsString('#### Tiefer', $rein);
        $this->assertDoesNotMatchRegularExpression('~^\# ~m', $rein);
    }

    /**
     * Tiefer als sechs geht Markdown nicht.
     *
     * @return void
     */
    public function test_the_sixth_level_stays(): void {
        $this->assertStringContainsString('###### Tief', help_document::clean("###### Tief\n"));
    }

    /**
     * Eine Raute mitten im Text ist keine Ueberschrift.
     *
     * @return void
     */
    public function test_a_hash_inside_a_line_is_not_a_heading(): void {
        $roh = 'Der Kanal #allgemein ist offen.';

        $this->assertSame("Der Kanal #allgemein ist offen.\n", help_document::clean($roh));
    }

    /**
     * A missing file is the empty string, not a warning.
     *
     * @return void
     */
    public function test_a_missing_file_is_empty(): void {
        $this->assertSame('', help_document::read('/gibt/es/nicht.md'));
    }

    /**
     * The suite's own document comes through clean.
     *
     * @return void
     */
    public function test_the_suite_document_has_no_internal_traces(): void {
        $pfad = \core_component::get_component_directory('local_elediaai_core') . '/docs/02-user-doc.md';
        $rein = help_document::read($pfad);

        $this->assertNotSame('', $rein);
        $this->assertStringNotContainsString('## Meta', $rein);
        $this->assertDoesNotMatchRegularExpression('~`\d\d-[a-z0-9-]+\.md`~', $rein);
    }

    /**
     * Ein Abschnitt endet an der naechsten Ueberschrift derselben Ebene.
     */
    public function test_a_section_ends_at_the_next_heading_of_its_level(): void {
        $datei = $this->write("## Eins\n\nText eins.\n\n## Zwei\n\nText zwei.\n");

        $treffer = help_document::section($datei, 'Eins', 2);

        $this->assertStringContainsString('Text eins.', $treffer);
        $this->assertStringNotContainsString('Text zwei.', $treffer);
    }

    /**
     * Und auch an einer *hoeheren* Ueberschrift.
     *
     * Der Fehler, aus dem dieser Test entstand: geprueft wurde nur auf dieselbe
     * Ebene, weshalb der letzte Unterabschnitt einer Datei ueber die naechste
     * `##` hinweglief bis zur uebernaechsten `###` -- gemessen 75 Zeilen statt
     * der 36, die dort stehen.
     */
    public function test_a_subsection_ends_at_a_higher_heading(): void {
        $datei = $this->write(
            "## Kapitel\n\n### Letzter\n\nGehoert dazu.\n\n## Naechstes\n\nGehoert nicht dazu.\n"
            . "\n### Tiefer\n\nAuch nicht.\n"
        );

        $treffer = help_document::section($datei, 'Letzter', 3);

        $this->assertStringContainsString('Gehoert dazu.', $treffer);
        $this->assertStringNotContainsString('Gehoert nicht dazu.', $treffer);
        $this->assertStringNotContainsString('Auch nicht.', $treffer);
    }

    /**
     * Der Vorspann laesst die Unterabschnitte weg.
     */
    public function test_the_intro_stops_before_the_first_subsection(): void {
        $datei = $this->write("## Kapitel\n\nEinleitung.\n\n### Erster\n\nDetail.\n");

        $treffer = help_document::section($datei, 'Kapitel', 2, true);

        $this->assertStringContainsString('Einleitung.', $treffer);
        $this->assertStringNotContainsString('Detail.', $treffer);
    }

    /**
     * Eine Ueberschrift, die es nicht gibt, gibt nichts zurueck.
     *
     * Wichtig, weil die Kapitel der Vertraege daran haengen: lieber kein
     * Kapitel als eines, das leer aussieht wie ein Fehler der Website.
     */
    public function test_a_missing_section_is_empty(): void {
        $datei = $this->write("## Eins\n\nText.\n");

        $this->assertSame('', help_document::section($datei, 'Gibt es nicht', 2));
    }

    /**
     * Schreibt ein Dokument in den Testbereich und gibt seinen Pfad zurueck.
     *
     * @param string $inhalt
     * @return string
     */
    private function write(string $inhalt): string {
        $pfad = make_request_directory() . '/doc.md';
        file_put_contents($pfad, $inhalt);
        return $pfad;
    }
}

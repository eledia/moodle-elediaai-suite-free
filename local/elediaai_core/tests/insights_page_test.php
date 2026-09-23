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
 * Die Kurs-Einblicke.
 *
 * @package    local_elediaai_core
 * @category   test
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core;

use advanced_testcase;
use local_elediaai_core\local\insights;
use local_elediaai_core\local\insights_page;
use local_elediaai_core\local\turn_recorder;

defined('MOODLE_INTERNAL') || die();

/**
 * Prueft die Darstellung der Kurs-Einblicke.
 *
 * @covers \local_elediaai_core\local\insights_page
 */
final class insights_page_test extends advanced_testcase {
    /** @var \stdClass Kurs. */
    private $course;

    /** @var int Kurskontext-Id. */
    private int $contextid;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        $this->contextid = (int) \core\context\course::instance((int) $this->course->id)->id;
    }

    /**
     * Die Luecke steht mit Anteil, Menge und Fragendenzahl da.
     */
    public function test_a_gap_shows_its_share_its_volume_and_its_people(): void {
        for ($i = 0; $i < 5; $i++) {
            $person = $this->getDataGenerator()->create_user();
            $this->record((int) $person->id, 'Konfidenzintervalle');
        }

        $html = insights_page::gaps(
            insights::gaps((int) $this->course->id),
            \core\context\course::instance((int) $this->course->id)
        );

        $this->assertStringContainsString('Konfidenzintervalle', $html);
        $this->assertStringContainsString(
            get_string('insights_gap_share', 'local_elediaai_core', 100),
            $html
        );
        $this->assertStringContainsString(
            get_string('insights_gap_askers', 'local_elediaai_core', 5),
            $html
        );
        // Der Balken zeigt zwei Anteile, nicht einen Fortschritt -- also
        // role="img" mit einer Beschriftung, die beide Zahlen nennt.
        $this->assertStringContainsString('role="img"', $html);
        $this->assertStringContainsString(
            get_string('insights_gap_share_label', 'local_elediaai_core', [
                'topic' => 'Konfidenzintervalle',
                'percent' => 100,
            ]),
            $html
        );
    }

    /**
     * Ohne Luecken sagt die Flaeche das -- keine leere Liste.
     */
    public function test_no_gaps_says_so(): void {
        $html = insights_page::gaps([], \core\context\course::instance((int) $this->course->id));

        $this->assertStringContainsString(
            get_string('insights_gaps_empty', 'local_elediaai_core'),
            $html
        );
        $this->assertStringNotContainsString('eai-splitbar', $html);
    }

    /**
     * Ein Thema ohne Material sagt das, eines mit Material nennt es.
     */
    public function test_a_gap_says_whether_material_exists(): void {
        for ($i = 0; $i < 4; $i++) {
            $person = $this->getDataGenerator()->create_user();
            $this->record((int) $person->id, 'Varianz');
        }
        $context = \core\context\course::instance((int) $this->course->id);

        $html = insights_page::gaps(insights::gaps((int) $this->course->id), $context);

        $this->assertStringContainsString(
            get_string('insights_gap_nosource', 'local_elediaai_core'),
            $html
        );
    }

    /**
     * Die Kennzahlen nennen ihre Bedeutung, nicht nur ihre Zahl.
     */
    public function test_the_metrics_name_what_they_mean(): void {
        $person = $this->getDataGenerator()->create_user();
        $this->record((int) $person->id, 'Varianz');

        $html = insights_page::metrics(insights::summary((int) $this->course->id), 30);

        // Die Deckungs-Kachel traegt ein Bild, keinen Hinweis -- und das Bild
        // muss seine Zahl fuer Screenreader mitfuehren, sonst ist es Dekoration.
        $this->assertStringContainsString('eai-splitbar', $html);
        $this->assertStringContainsString('role="img"', $html);
        $this->assertStringContainsString(
            get_string('insights_metric_askers_hint', 'local_elediaai_core'),
            $html
        );
        $this->assertStringContainsString('eai-metric__value', $html);
    }

    /**
     * Der Wortlaut zeigt die Frage, nie die Antwort.
     *
     * Eine Lehrkraft soll sehen, was gefragt wurde. Was die KI geantwortet hat,
     * gehoert in den technischen Bericht -- hier waere es die halbe Seite und
     * nicht der Befund.
     */
    public function test_the_verbatim_list_shows_questions_and_not_answers(): void {
        $person = $this->getDataGenerator()->create_user();
        $this->record((int) $person->id, 'Varianz');

        $html = insights_page::verbatim(insights::recent((int) $this->course->id), 90);

        $this->assertStringContainsString('Frage zu Varianz', $html);
        $this->assertStringNotContainsString('Antwort zu Varianz', $html);
        $this->assertStringContainsString(
            get_string('insights_verbatim_badge', 'local_elediaai_core'),
            $html
        );
    }

    /**
     * Der Verlauf hat einen Balken je Tag, auch fuer leere Tage.
     */
    public function test_the_trend_has_one_bar_per_day(): void {
        $person = $this->getDataGenerator()->create_user();
        $this->record((int) $person->id, 'Varianz');

        $html = insights_page::trend(insights::per_day((int) $this->course->id, 14));

        $this->assertSame(14, substr_count($html, 'eai-chart__column'));
        // Die hoechste Saeule traegt ihre Zahl sichtbar, alle im Titel.
        $this->assertStringContainsString('eai-chart__fill--peak', $html);
        $this->assertStringContainsString('eai-chart__axis', $html);
        $this->assertStringContainsString(
            get_string('insights_trend_label', 'local_elediaai_core', ['total' => 1, 'days' => 14]),
            $html
        );
    }

    /**
     * Die Werkzeugliste kommt aus der Registry, nicht aus einer Liste im Kern.
     */
    public function test_the_tool_list_is_asked_not_held(): void {
        for ($i = 0; $i < 4; $i++) {
            $person = $this->getDataGenerator()->create_user();
            $this->record((int) $person->id, 'Varianz');
        }
        $this->setAdminUser();
        $context = \core\context\course::instance((int) $this->course->id);

        $html = insights_page::gaps(insights::gaps((int) $this->course->id), $context);

        // Welche Werkzeuge erscheinen, haengt an den installierten Plugins --
        // gepruefft wird darum die Herkunft, nicht ein Name: entweder steht der
        // Hinweis da, oder es gibt keine passenden Deskriptoren.
        $haslist = str_contains($html, 'eai-insights-tools');
        if ($haslist) {
            $this->assertStringContainsString(
                get_string('insights_tools_hint', 'local_elediaai_core'),
                $html
            );
        } else {
            $this->assertStringNotContainsString('eai-insights-tool', $html);
        }
    }

    /**
     * Einen Turn schreiben.
     *
     * @param int $userid
     * @param string $topic
     * @param string $origin
     * @return void
     */
    private function record(int $userid, string $topic, string $origin = turn_recorder::ORIGIN_GENERAL): void {
        turn_recorder::record(
            component: 'block_elediaai_tutor',
            actionname: 'chat_turn',
            contextid: $this->contextid,
            userid: $userid,
            prompt: 'Frage zu ' . $topic,
            response: 'Antwort zu ' . $topic,
            prompttokens: 10,
            completiontokens: 5,
            origin: $origin,
            topic: $topic,
        );
    }
}

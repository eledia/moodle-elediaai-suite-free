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
 * Ein Ziel, ein Name.
 *
 * @package    local_elediaai_core
 * @category   test
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core;

use advanced_testcase;
use local_elediaai_core\output\section_nav;

defined('MOODLE_INTERNAL') || die();

/**
 * Haelt die Benennung der vier Audit-Flaechen fest.
 *
 * Jede Flaeche hat genau einen Namen, und der steht im Reiter, auf der
 * Einstiegskarte und im Seitentitel wortgleich. Vorher hiess dieselbe Seite
 * "Kurs-Einblicke" im Menue, "Was die Lernenden gefragt haben" auf der Karte
 * und "Welcher Kurs?" in der Titelzeile -- drei Namen, ein Ziel, und niemand
 * konnte wissen, dass es dasselbe ist.
 *
 * @covers \local_elediaai_core\output\section_nav::render_for_feature
 */
final class audit_naming_test extends advanced_testcase {
    /** @var array<string, string> Seitendatei => Sprachschluessel des Namens. */
    private const FLAECHEN = [
        'audit_didactic.php' => 'surface_insights_title',
        'course_insights.php' => 'surface_insights_title',
        'audit_actions.php' => 'surface_actions_title',
        'audit_technical.php' => 'surface_requests_title',
        'audit_settings.php' => 'surface_settings_title',
    ];

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Der Reiter nennt jede Flaeche mit ihrem Namen.
     */
    public function test_the_navigation_uses_the_canonical_name(): void {
        $this->setAdminUser();

        $nav = section_nav::render_for_feature('audit', section_nav::FEATURE_AUDIT);

        foreach (array_unique(array_values(self::FLAECHEN)) as $schluessel) {
            $this->assertStringContainsString(
                s(get_string($schluessel, 'local_elediaai_core')),
                $nav,
                "Der Reiter nennt {$schluessel} nicht."
            );
        }
    }

    /**
     * Kein Ziel steht zweimal im Reiter.
     *
     * Der Fund, der diesen Test geschrieben hat: „Uebersicht" und „Audit"
     * zeigten beide auf audit.php -- zwei Reiter, zwei Namen, eine Seite.
     */
    public function test_no_destination_appears_twice(): void {
        $this->setAdminUser();

        $nav = section_nav::render_for_feature('audit', section_nav::FEATURE_AUDIT);
        preg_match_all('/lh-plugin-section-nav__item" href="([^"]+)"/', $nav, $treffer);
        $ziele = array_map(static function (string $url): string {
            return (string) parse_url(html_entity_decode($url), PHP_URL_PATH);
        }, $treffer[1]);

        $this->assertSame(
            array_values(array_unique($ziele)),
            $ziele,
            'Zwei Reiter zeigen auf dieselbe Seite: ' . implode(', ', $ziele)
        );
    }

    /**
     * Die Seite selbst traegt denselben Namen wie ihr Reiter.
     *
     * Der Titel einer Seite steht in ihrem Skript, nicht in einer Klasse --
     * deshalb prueft dieser Test die Datei. Das ist die einzige Stelle, an der
     * sich ein zweiter Name einschleichen kann, ohne dass ein Test es merkt.
     */
    public function test_every_page_sets_the_same_name(): void {
        global $CFG;

        foreach (self::FLAECHEN as $datei => $schluessel) {
            $pfad = $CFG->dirroot . '/local/elediaai_core/' . $datei;
            $this->assertFileExists($pfad);
            $inhalt = (string) file_get_contents($pfad);

            $this->assertStringContainsString(
                "\$PAGE->set_title(get_string('{$schluessel}', 'local_elediaai_core'));",
                $inhalt,
                "{$datei} setzt einen anderen Seitentitel als {$schluessel}."
            );
            // audit_settings.php baut seine Huelle weiter unten; der Name
            // steht dort in derselben Zeile wie ueberall.
            $this->assertStringContainsString(
                "'tagline' => get_string('{$schluessel}', 'local_elediaai_core'),",
                $inhalt,
                "{$datei} beschriftet seine Kopfzeile anders als {$schluessel}."
            );
        }
    }

    /**
     * Die Einstiegskarten tragen die Namen der Flaechen, nicht eigene.
     */
    public function test_the_entry_cards_use_the_canonical_names(): void {
        global $CFG;

        $inhalt = (string) file_get_contents($CFG->dirroot . '/local/elediaai_core/audit.php');
        preg_match_all("/'title' => '([a-z0-9_]+)'/", $inhalt, $treffer);

        $this->assertNotEmpty($treffer[1]);
        foreach ($treffer[1] as $schluessel) {
            $this->assertStringStartsWith(
                'surface_',
                $schluessel,
                "Die Karte {$schluessel} traegt einen eigenen Namen statt den der Flaeche."
            );
        }
    }

    /**
     * „Technisches" und „didaktisches Audit" kommen nicht wieder.
     *
     * Beides waren Begriffe, die man erklaeren musste, bevor jemand wusste,
     * wo er klicken soll.
     */
    public function test_the_retired_jargon_stays_retired(): void {
        global $CFG;

        foreach (['en', 'de'] as $sprache) {
            $pfad = $CFG->dirroot . '/local/elediaai_core/lang/' . $sprache
                . '/local_elediaai_core.php';
            $inhalt = (string) file_get_contents($pfad);

            foreach (['technical audit', 'didactic audit', 'echnisches Audit', 'idaktisches Audit'] as $begriff) {
                $this->assertStringNotContainsStringIgnoringCase(
                    $begriff,
                    $inhalt,
                    "„{$begriff}" . '" steht wieder in ' . $sprache . '.'
                );
            }
        }
    }
}

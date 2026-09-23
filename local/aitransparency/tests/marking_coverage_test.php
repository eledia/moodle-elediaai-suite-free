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
 * Wer KI-Text ausgibt, kennzeichnet ihn.
 *
 * @package    local_aitransparency
 * @category   test
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aitransparency;

use advanced_testcase;
use core_plugin_manager;

/**
 * Meldet jede Komponente, die KI aufruft und ihre Ausgabe nicht kennzeichnet.
 *
 * Art. 50 Abs. 1 verlangt, dass ein Mensch erfaehrt, wenn er mit einer KI
 * spricht. Der Marker ist dafuer da; er hilft aber nur an den Stellen, an denen
 * ihn jemand ruft, und ob eine Stelle ihn ruft, sieht man dem Bildschirm nicht
 * an -- nur dem Quelltext.
 *
 * Dieser Test liest deshalb den Quelltext. Er findet jede installierte
 * Komponente, die den Engpass der Suite benutzt (und damit KI-Text erzeugt),
 * und prueft, ob irgendwo in derselben Komponente der Marker vorkommt. Was
 * fehlt, steht unten in {@see OHNE_KENNZEICHNUNG} -- mit dem Stand, zu dem es
 * dort landete.
 *
 * Die Liste ist eine Schuld, keine Erlaubnis. Sie wird kuerzer, wenn der
 * Betreiber entscheidet, wo der Hinweis stehen soll; sie darf nie laenger
 * werden, ohne dass jemand diese Datei anfasst.
 *
 * @covers \local_aitransparency\marker
 */
final class marking_coverage_test extends advanced_testcase {
    /**
     * @var string[] Komponenten, die KI aufrufen und (noch) nicht kennzeichnen.
     *
     * Stand 19.09.2026. Die Ausrollung auf diese Flaechen ist eine
     * Produktentscheidung: wo genau der Hinweis steht -- ueber der Ausgabe, im
     * Vorschaudialog, in der erzeugten Aktivitaet -- haengt davon ab, wer die
     * Ausgabe zu sehen bekommt, und das ist je Flaeche verschieden.
     */
    private const OHNE_KENNZEICHNUNG = [
        // Werkzeuge fuer Lehrkraefte. Die handelnde Person sitzt in einem
        // Werkzeug, das die KI im Namen fuehrt, und prueft das Ergebnis, bevor
        // es jemand anders sieht -- Art. 50 Abs. 1 nimmt genau das aus
        // („offensichtlich aus dem Kontext"). Den maschinenlesbaren Nachweis
        // nach Abs. 2 schreibt seit dem 20.09.2026 der Engpass der Suite fuer
        // alle mit; hier fehlt nur die sichtbare Zeile, und die waere hier
        // eine Belehrung.
        'block_elediaai_path',
        'format_elediaai',
        'local_activityfilter',
        'local_elediaai_coursegen',
        'local_elediaai_h5pauthor',
        'local_elediaai_questiongen',
        'local_elediaai_strategy',
        'local_elediaai_tactics',
        'local_elediaai_teachertools',
        // Eine Lehrkraft bearbeitet den Entwurf und gibt ihn frei; was die
        // lernende Person liest, steht in `teacherfeedback` und hat einen
        // Menschen als Urheber. Der Entwurfsbildschirm der Lehrkraft ist ein
        // Werkzeugfall wie die darueber.
        'mod_aifeedback',
    ];

    /**
     * Keine neue Komponente ohne Kennzeichnung.
     */
    public function test_no_new_component_generates_ai_text_without_marking_it(): void {
        $neu = array_values(array_diff($this->unmarked_generators(), self::OHNE_KENNZEICHNUNG));

        $this->assertSame(
            [],
            $neu,
            "Diese Komponenten erzeugen KI-Text und kennzeichnen ihn nicht:\n  "
                . implode("\n  ", $neu)
                . "\n\nEntweder den Marker rufen (\\local_aitransparency\\marker::notice())"
                . " oder die Komponente in marking_coverage_test::OHNE_KENNZEICHNUNG"
                . " eintragen -- mit dem Grund, warum sie dort steht."
        );
    }

    /**
     * Wer kennzeichnet, bleibt dabei.
     *
     * Die Gegenrichtung: eine Komponente, die den Marker einmal hatte und ihn
     * verliert, faellt sonst still in den Zustand von vorher zurueck.
     */
    public function test_a_component_that_marks_keeps_marking(): void {
        $generatoren = $this->generators();
        $ohne = $this->unmarked_generators();

        foreach ($generatoren as $component) {
            if (in_array($component, self::OHNE_KENNZEICHNUNG, true)) {
                continue;
            }
            $this->assertNotContains(
                $component,
                $ohne,
                "{$component} hat die Kennzeichnung verloren."
            );
        }
    }

    /**
     * Die Schuldenliste enthaelt nichts Erledigtes.
     */
    public function test_the_debt_list_has_no_stale_entries(): void {
        $ohne = $this->unmarked_generators();
        $installiert = array_keys($this->suite_components());

        foreach (self::OHNE_KENNZEICHNUNG as $component) {
            if (!in_array($component, $installiert, true)) {
                // In der CI ist meist nur ein Teil der Suite installiert.
                continue;
            }
            $this->assertContains(
                $component,
                $ohne,
                "{$component} kennzeichnet inzwischen und gehoert aus"
                    . ' marking_coverage_test::OHNE_KENNZEICHNUNG entfernt.'
            );
        }
    }

    /**
     * Komponenten, die den Engpass der Suite benutzen.
     *
     * @return string[]
     */
    private function generators(): array {
        $out = [];
        foreach ($this->suite_components() as $component => $verzeichnis) {
            if ($component === 'local_elediaai_core') {
                // Der Engpass selbst. Er erzeugt keinen Text, er zaehlt ihn.
                continue;
            }
            $engpass = [
                'quota_aware_ai_manager::process_action',
                'quota_aware_ai_manager::process_callback',
                'turn_recorder::record',
            ];
            if ($this->contains($verzeichnis, $engpass)) {
                $out[] = $component;
            }
        }
        sort($out);
        return $out;
    }

    /**
     * Davon die, in denen der Marker nirgends vorkommt.
     *
     * @return string[]
     */
    private function unmarked_generators(): array {
        $verzeichnisse = $this->suite_components();
        $out = [];
        foreach ($this->generators() as $component) {
            // Der Marker wird bewusst ueber einen Klassennamen als Zeichenkette
            // gerufen -- local_aitransparency ist eine weiche Abhaengigkeit.
            // Deshalb wird hier auf den Aufruf gesucht, nicht auf ein use.
            $aufrufe = [
                'marker::notice',
                'marker::wrap_text',
                "'notice'",
                "'wrap_text'",
            ];
            if (!$this->contains($verzeichnisse[$component], $aufrufe, 'aitransparency')) {
                $out[] = $component;
            }
        }
        return $out;
    }

    /**
     * Installierte Plugins, die local_elediaai_core hart voraussetzen.
     *
     * Nur die koennen den Engpass ueberhaupt rufen (siehe 03-dev-doc.md,
     * Abschnitt 6), und nur die werden gelesen -- ein Vollscan ueber Moodle
     * waere um Groessenordnungen teurer und faende dasselbe.
     *
     * @return array<string, string> Komponente => absolutes Verzeichnis.
     */
    private function suite_components(): array {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $cache = [];
        $manager = core_plugin_manager::instance();
        foreach ($manager->get_plugins() as $plugins) {
            foreach ($plugins as $info) {
                $component = (string) $info->component;
                $verzeichnis = (string) $info->rootdir;
                if ($verzeichnis === '' || !is_dir($verzeichnis)) {
                    continue;
                }
                if ($component === 'local_elediaai_core') {
                    $cache[$component] = $verzeichnis;
                    continue;
                }
                $benoetigt = $info->get_other_required_plugins();
                if (array_key_exists('local_elediaai_core', $benoetigt)) {
                    $cache[$component] = $verzeichnis;
                }
            }
        }
        ksort($cache);
        return $cache;
    }

    /**
     * Kommt eine der Zeichenketten irgendwo unter dem Verzeichnis vor?
     *
     * Tests bleiben draussen: ein Test, der den Marker prueft, ist kein
     * Aufrufer, und ein Test, der einen KI-Aufruf nachstellt, erzeugt keinen
     * Text fuer einen Menschen.
     *
     * @param string $verzeichnis
     * @param string[] $nadeln
     * @param string|null $auch Zusaetzliche Zeichenkette, die in derselben
     *                          Datei stehen muss (gegen Zufallstreffer).
     * @return bool
     */
    private function contains(string $verzeichnis, array $nadeln, ?string $auch = null): bool {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($verzeichnis, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $datei) {
            if (!$datei instanceof \SplFileInfo || $datei->getExtension() !== 'php') {
                continue;
            }
            $pfad = $datei->getPathname();
            if (str_contains($pfad, '/tests/') || str_contains($pfad, '/node_modules/')) {
                continue;
            }
            $inhalt = (string) file_get_contents($pfad);
            foreach ($nadeln as $nadel) {
                if (!str_contains($inhalt, $nadel)) {
                    continue;
                }
                if ($auch === null || str_contains($inhalt, $auch)) {
                    return true;
                }
            }
        }
        return false;
    }
}

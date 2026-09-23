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
 * Datentabellen schieben nicht seitlich.
 *
 * @package    local_elediaai_core
 * @category   test
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core;

use advanced_testcase;
use local_elediaai_core\local\audit_page;

/**
 * Haelt fest, was den seitlichen Bildlauf der Berichtstabellen verursacht hat.
 *
 * Die Wirkung selbst ist CSS und wurde am Bildschirm nachgemessen: der
 * Suite-Bericht mit zehn Spalten brauchte 1093 Pixel in einer 718 Pixel
 * breiten Spalte. Zwei Ursachen lagen im PHP-Stilblock dieser Seite, und
 * genau die kann ein Test festhalten -- eine feste Mindestbreite und
 * Spaltenbreiten nach Position.
 *
 * @covers \local_elediaai_core\local\audit_page::css
 */
final class audit_table_layout_test extends advanced_testcase {
    /**
     * Der Stilblock ohne seine Kommentare.
     *
     * Die Kommentare erklaeren, welche Regeln hier einmal standen und warum
     * sie weg sind -- sie nennen die Werte also, ohne sie zu setzen. Ein Test,
     * der den Text durchsucht, faende sie und meldete den erklaerten Zustand
     * als den beklagten.
     *
     * @return string
     */
    private function declarations(): string {
        return (string) preg_replace('#/\*.*?\*/#s', '', audit_page::css());
    }

    /**
     * Keine feste Mindestbreite auf der Tabelle.
     *
     * `min-width: 48rem` war der Grund, warum die Tabelle auch dann seitlich
     * geschoben werden musste, wenn ihr Inhalt laengst schmaler war.
     */
    public function test_the_table_has_no_fixed_minimum_width(): void {
        $css = $this->declarations();

        $this->assertStringNotContainsString('min-width: 48rem', $css);
        $this->assertDoesNotMatchRegularExpression(
            '/\.lh-ai-audit table\s*\{[^}]*min-width/s',
            $css,
            'Die Tabelle hat wieder eine feste Mindestbreite.'
        );
    }

    /**
     * Keine Spaltenbreiten nach Position.
     *
     * Der Bericht hat keine feste Spaltenfolge: Frage, Antwort und Tokens
     * haengen an den Einstellungen der Website. `nth-child(3)` traf deshalb
     * je nach Website eine andere Spalte als gemeint.
     */
    public function test_no_column_is_sized_by_its_position(): void {
        $css = $this->declarations();

        $this->assertDoesNotMatchRegularExpression(
            '/nth-child\(\d+\)[^{]*\{[^}]*(?:max-)?width\s*:/s',
            $css,
            'Es gibt wieder Spaltenbreiten nach Position.'
        );
    }

    /**
     * Und der Behaelter schiebt nicht mehr seitlich.
     */
    public function test_the_report_wrapper_does_not_scroll_sideways(): void {
        $css = $this->declarations();

        $this->assertDoesNotMatchRegularExpression(
            '/\.reportbuilder-wrapper\s*\{[^}]*overflow-x\s*:\s*auto/s',
            $css,
            'Der Berichtsbehaelter schiebt wieder seitlich.'
        );
    }
}

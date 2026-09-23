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
 * Tests for standalone Core output primitives.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core;

defined('MOODLE_INTERNAL') || die();

use advanced_testcase;
use local_elediaai_core\output\icon_kit;
use local_elediaai_core\output\plugin_page;
use local_elediaai_core\output\plugin_shell;
use moodle_url;

/**
 * Verifies output without loading an external UI plugin.
 */
final class output_test extends advanced_testcase {
    /**
     * The page frame and actions are rendered by Core itself.
     */
    public function test_page_shell_renders_standalone_markup(): void {
        ob_start();
        plugin_page::open([
            'name' => 'AI Suite',
            'tagline' => 'Overview',
            'sectionnav' => '<nav>Sections</nav>',
            'headeractionicons' => [[
                'url' => '/blocks/elediaai_tutor/home.php',
                'label' => 'AI Tutor',
                'faicon' => 'fa-comments',
            ]],
        ] + plugin_shell::action_slots('local_elediaai_core'), plugin_page::MODIFIER_WIDE);
        plugin_shell::content_open();
        echo 'Content';
        plugin_shell::content_close();
        plugin_page::close();
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('elediaai-core-shell--wide', $html);
        $this->assertStringContainsString('lucide-comments', $html);
        // Die Hilfe der Suite ist das Handbuch des Wegweisers; die frühere
        // Seite im Kern gab das Entwicklerdokument an Endnutzende aus.
        //
        // Der Wegweiser ist dabei eine weiche Abhaengigkeit: `help_url()` gibt
        // die leere Zeichenkette zurueck, wenn er fehlt, und beide Huellen
        // lassen den Knopf dann weg -- ein Hilfeknopf, der auf 404 fuehrt,
        // waere schlimmer als keiner. Die CI haengt nur das gepruefte Plugin
        // ein, dort ist genau diese Lage der Normalfall. Geprueft werden
        // deshalb beide Lagen.
        if (\core_component::get_component_directory('local_elediaai_guide') !== null) {
            $this->assertStringContainsString('local/elediaai_guide/index.php', $html);
        } else {
            $this->assertStringNotContainsString('local/elediaai_guide/', $html);
        }
        $this->assertStringNotContainsString('local/lernhive', $html);
    }

    /**
     * Icon actions retain an accessible label.
     */
    public function test_icon_action_is_accessible(): void {
        $html = icon_kit::button('edit', 'Edit scenario', new moodle_url('/course/modedit.php'));
        $this->assertStringContainsString('lh-icon-action', $html);
        $this->assertStringContainsString('aria-label="Edit scenario"', $html);
        $this->assertStringContainsString('lucide-pencil', $html);
    }
    /**
     * Ein Knopf in der Kopfzeile traegt immer eine Aufschrift.
     *
     * Der Fund: drei Seiten uebergaben '' als Beschriftung, und `??` faengt
     * nur null. Oben rechts stand dann eine leere Pille, die in die
     * Dokumentation fuehrte -- und ein Link ohne zugaenglichen Namen ist
     * nicht nur haesslich, sondern ein Barrierefreiheitsfehler.
     */
    public function test_an_empty_label_falls_back_to_the_default(): void {
        $this->resetAfterTest();

        $slots = \local_elediaai_core\output\plugin_shell::action_slots(
            'local_elediaai_core',
            false,
            null,
            '',
            ''
        );

        $this->assertSame(get_string('help'), $slots['helplabel']);
        $this->assertSame(get_string('settings'), $slots['settingslabel']);
    }

    /**
     * Und auch dann, wenn jemand die Kopfdaten von Hand baut.
     */
    public function test_a_hand_built_header_gets_a_named_button(): void {
        $this->resetAfterTest();

        ob_start();
        \local_elediaai_core\output\plugin_page::open([
            'name' => 'eLeDia.ai',
            'helpurl' => 'https://example.org/hilfe',
            'helplabel' => '',
        ]);
        \local_elediaai_core\output\plugin_page::close();
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('elediaai-core-shell__action--help', $html);
        $this->assertStringContainsString(s(get_string('help')), $html);
        // Kein Knopf, dessen Inhalt zwischen den Tags leer bleibt.
        $this->assertDoesNotMatchRegularExpression(
            '/elediaai-core-shell__action--help[^>]*>\s*</',
            $html
        );
    }
}

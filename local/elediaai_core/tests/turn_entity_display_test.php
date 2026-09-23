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
 * Wie eine Turn-Zeile gelesen aussieht.
 *
 * @package    local_elediaai_core
 * @category   test
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core;

use advanced_testcase;
use local_elediaai_core\reportbuilder\local\entities\turn;
use stdClass;

/**
 * Provider und Tokenzahl in der Anfragenliste.
 *
 * @covers \local_elediaai_core\reportbuilder\local\entities\turn::format_provider_model
 * @covers \local_elediaai_core\reportbuilder\local\entities\turn::format_tokens
 */
final class turn_entity_display_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Ein Chat-Backend erscheint mit seinem Namen, nicht mit seiner Kennung.
     *
     * „ingestionapi" in einer Spalte „Provider / Modell" ist das, worueber
     * jemand gestolpert ist. Die Kennung bleibt gespeichert -- sie ist stabil
     * und in jeder Sprache dieselbe --, aber gezeigt wird der Name.
     */
    public function test_a_chat_backend_is_shown_by_its_name(): void {
        if (!class_exists('\local_elediaai_chatengine\backend_resolver')) {
            $this->markTestSkipped('Die Chat-Engine ist in diesem Lauf nicht installiert.');
        }

        $html = turn::format_provider_model('ingestionapi', (object) ['turnmodel' => '']);

        $this->assertStringContainsString(
            s(\local_elediaai_chatengine\adapter\ingestionapi_adapter::name()),
            $html
        );
        $this->assertStringNotContainsString('ingestionapi', $html);
    }

    /**
     * Ein unbekannter Wert bleibt stehen, wie er ist.
     */
    public function test_an_unknown_provider_is_left_alone(): void {
        $html = turn::format_provider_model('litellm', (object) ['turnmodel' => 'gpt-4o-mini']);

        $this->assertStringContainsString('litellm', $html);
        $this->assertStringContainsString('gpt-4o-mini', $html);
    }

    /**
     * Eine geschaetzte Zahl traegt ihr Zeichen.
     */
    public function test_an_estimated_count_is_marked(): void {
        set_config('audit_show_tokens', 1, 'local_elediaai_core');

        $gemessen = turn::format_tokens(120, (object) [
            'turncompletion' => 60,
            'turnestimated' => 0,
        ]);
        $geschaetzt = turn::format_tokens(120, (object) [
            'turncompletion' => 60,
            'turnestimated' => 1,
        ]);

        $this->assertStringContainsString('120 / 60', $gemessen);
        $this->assertStringNotContainsString('≈', $gemessen);
        $this->assertStringContainsString('≈ 120 / 60', $geschaetzt);
        $this->assertStringContainsString(
            get_string('audit_col_tokens_help_estimated', 'local_elediaai_core'),
            $geschaetzt
        );
    }

    /**
     * Ohne Zahlen bleibt der Strich.
     */
    public function test_nothing_recorded_stays_a_dash(): void {
        set_config('audit_show_tokens', 1, 'local_elediaai_core');

        $this->assertSame('—', turn::format_tokens(0, (object) [
            'turncompletion' => 0,
            'turnestimated' => 1,
        ]));
    }
}

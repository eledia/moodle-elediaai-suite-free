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
 * Welches Feature wird benutzt.
 *
 * @package    local_elediaai_core
 * @category   test
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core;

use advanced_testcase;
use local_elediaai_core\local\audit_page;
use local_elediaai_core\local\insights;
use local_elediaai_core\local\quota_manager;
use local_elediaai_core\local\turn_recorder;

defined('MOODLE_INTERNAL') || die();

/**
 * Prueft die Auswertung nach Komponente.
 *
 * @covers \local_elediaai_core\local\insights::usage_by_component
 * @covers \local_elediaai_core\local\audit_page::component_usage
 * @covers \local_elediaai_core\local\audit_page::component_panel
 */
final class component_usage_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('quota_completion_buffer', 0, 'local_elediaai_core');
    }

    /**
     * Eine Zeile je Komponente, mit der richtigen Summe.
     */
    public function test_usage_is_grouped_per_component(): void {
        $this->record(100, 50, 'local_elediaai_questiongen');
        $this->record(20, 10, 'local_elediaai_questiongen');
        $this->record(400, 200, 'block_elediaai_tutor');

        $rows = $this->keyed(insights::usage_by_component(30));

        $this->assertCount(2, $rows);
        $this->assertSame(600, $rows['block_elediaai_tutor']['tokens']);
        $this->assertSame(1, $rows['block_elediaai_tutor']['requests']);
        $this->assertSame(180, $rows['local_elediaai_questiongen']['tokens']);
        $this->assertSame(2, $rows['local_elediaai_questiongen']['requests']);
    }

    /**
     * Warum nicht das Guthaben-Hauptbuch -- der Fund, der die Quelle umgestellt hat.
     *
     * Das Hauptbuch fuehrt eine component-Spalte, was es wie die naheliegende
     * Quelle aussehen laesst. Sein eindeutiger Index ist aber
     * (userid, rolebucket, windowtype, windowstart) OHNE Komponente: es gibt
     * eine Zeile je Person und Fenster, und die Spalte haelt nur fest, welches
     * Feature zuletzt gebucht hat. Eine Auswertung darauf sieht plausibel aus
     * und ist falsch.
     *
     * Dieser Test haelt die Eigenschaft fest, damit niemand sie erneut fuer
     * eine Zuordnung haelt.
     */
    public function test_the_credit_ledger_cannot_attribute_per_component(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        quota_manager::record_usage((int) $user->id, 100, 0, 'local_elediaai_questiongen');
        quota_manager::record_usage((int) $user->id, 100, 0, 'block_elediaai_tutor');

        $rows = $DB->get_records(quota_manager::TABLE, [
            'userid' => $user->id,
            'windowtype' => quota_manager::WINDOW_DAY,
        ]);

        // Eine Zeile fuer beide Buchungen ...
        $this->assertCount(1, $rows);
        $row = reset($rows);
        // ... mit der Komponente der letzten, und den Tokens von beiden.
        $this->assertSame('block_elediaai_tutor', $row->component);
        $this->assertSame(200, (int) $row->totaltokens);
    }

    /**
     * Die teuerste Komponente steht oben.
     */
    public function test_the_most_expensive_feature_comes_first(): void {
        $this->record(1, 1, 'local_elediaai_strategy');
        $this->record(5000, 1000, 'block_elediaai_tutor');
        $this->record(300, 100, 'local_elediaai_h5pauthor');

        $order = array_column(insights::usage_by_component(30), 'component');

        $this->assertSame([
            'block_elediaai_tutor',
            'local_elediaai_h5pauthor',
            'local_elediaai_strategy',
        ], $order);
    }

    /**
     * Turns ausserhalb des Zeitraums bleiben draussen.
     */
    public function test_turns_outside_the_period_are_left_out(): void {
        $this->record(10, 10, 'local_elediaai_teachertools', time() - (60 * DAYSECS));

        $this->assertSame([], insights::usage_by_component(30));
        $this->assertCount(1, insights::usage_by_component(90));
    }

    /**
     * Der Kern nennt keine Plugin-Namen, er fragt sie ab.
     */
    public function test_a_component_is_named_by_its_own_plugin(): void {
        $this->record(10, 10, 'local_elediaai_chatengine');

        $rows = audit_page::component_usage(30);

        $this->assertCount(1, $rows);
        $this->assertSame('local_elediaai_chatengine', $rows[0]['component']);
        $this->assertSame(
            get_string('pluginname', 'local_elediaai_chatengine'),
            $rows[0]['label']
        );
    }

    /**
     * Eine Komponente, deren Plugin es nicht gibt, behaelt ihren Namen.
     *
     * Nutzung, die stattgefunden hat, wird durch eine Deinstallation nicht
     * ungeschehen -- sie darf deshalb nicht aus der Liste verschwinden.
     */
    public function test_usage_of_a_vanished_plugin_keeps_its_frankenstyle_name(): void {
        $this->record(10, 10, 'local_gibtesnicht');

        $rows = audit_page::component_usage(30);

        $this->assertSame('local_gibtesnicht', $rows[0]['label']);
    }

    /**
     * Der Balken traegt seine Werte fuer Screenreader mit.
     */
    public function test_the_bar_carries_its_value_for_assistive_technology(): void {
        $this->record(400, 100, 'block_elediaai_tutor');

        $html = audit_page::component_panel(audit_page::component_usage(30), 30);

        $this->assertStringContainsString('role="progressbar"', $html);
        $this->assertStringContainsString('aria-valuenow="500"', $html);
        $this->assertStringContainsString('eai-audit-component__fill', $html);
    }

    /**
     * Ohne Turns sagt die Flaeche das, statt eine leere Liste zu zeigen.
     */
    public function test_an_empty_period_says_so(): void {
        $html = audit_page::component_panel(audit_page::component_usage(30), 30);

        $this->assertStringContainsString(
            get_string('audit_component_empty', 'local_elediaai_core'),
            $html
        );
        $this->assertStringNotContainsString('role="progressbar"', $html);
    }

    /**
     * Einen Turn schreiben.
     *
     * @param int $prompttokens
     * @param int $completiontokens
     * @param string $component
     * @param int|null $when
     * @return void
     */
    private function record(
        int $prompttokens,
        int $completiontokens,
        string $component,
        ?int $when = null
    ): void {
        $user = $this->getDataGenerator()->create_user();
        turn_recorder::record(
            component: $component,
            actionname: 'generate_text',
            contextid: (int) \core\context\system::instance()->id,
            userid: (int) $user->id,
            prompt: 'Frage',
            response: 'Antwort',
            prompttokens: $prompttokens,
            completiontokens: $completiontokens,
            timestarted: $when,
        );
    }

    /**
     * Ergebnisliste nach Komponente schluesseln.
     *
     * @param array $rows
     * @return array
     */
    private function keyed(array $rows): array {
        $out = [];
        foreach ($rows as $row) {
            $out[$row['component']] = $row;
        }
        return $out;
    }
}

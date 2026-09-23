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
 * Die beiden Anfragenlisten ueberschneiden sich nicht mehr.
 *
 * @package    local_elediaai_core
 * @category   test
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core;

use advanced_testcase;
use core_reportbuilder\system_report_factory;
use local_elediaai_core\local\audit_config;
use local_elediaai_core\local\turn_recorder;
use local_elediaai_core\reportbuilder\local\systemreports\audit as audit_report;

/**
 * Was die Suite selbst auflistet, fehlt in Moodles Registeransicht.
 *
 * @covers \local_elediaai_core\reportbuilder\local\systemreports\audit
 */
final class register_overlap_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        if (!audit_config::feature_available()) {
            $this->markTestSkipped('Ohne core_ai-Register gibt es die Ansicht nicht.');
        }
    }

    /**
     * Eine Registerzeile mit Turn erscheint nicht, eine ohne schon.
     */
    public function test_a_row_the_suite_already_lists_is_left_out(): void {
        $fremd = $this->register_row('Ein fremdes Plugin fragte.');
        $eigen = $this->register_row('Die Suite fragte.');

        turn_recorder::record(
            component: 'local_elediaai_questiongen',
            actionname: 'generate_text',
            contextid: (int) \core\context\system::instance()->id,
            userid: (int) $this->getDataGenerator()->create_user()->id,
            prompt: 'Die Suite fragte.',
            response: 'Antwort',
            registerid: $eigen,
        );

        $html = $this->rendered_report();

        $this->assertStringContainsString('Ein fremdes Plugin fragte.', $html, 'Die fremde Zeile fehlt.');
        $this->assertStringNotContainsString('Die Suite fragte.', $html, 'Die eigene Zeile steht doppelt.');
        unset($fremd, $eigen);
    }

    /**
     * Ein Chat-Turn traegt keine Registernummer und blendet nichts aus.
     *
     * Er erreicht sein Backend auf eigenem Weg; dort gibt es keine Zeile, die
     * er verdecken koennte. Ein `registerid = null` darf deshalb nicht als
     * „passt auf alles" wirken.
     */
    public function test_a_turn_without_a_register_row_hides_nothing(): void {
        $fremd = $this->register_row('Ein fremdes Plugin fragte.');

        turn_recorder::record(
            component: 'block_elediaai_tutor',
            actionname: 'chat_turn',
            contextid: (int) \core\context\system::instance()->id,
            userid: (int) $this->getDataGenerator()->create_user()->id,
            prompt: 'Eine Chatfrage.',
            response: 'Antwort',
        );

        $this->assertStringContainsString('Ein fremdes Plugin fragte.', $this->rendered_report());
        unset($fremd);
    }

    /**
     * Eine Zeile in Moodles Register anlegen, wie der Kern es tut.
     *
     * @param string $prompt
     * @return int Die Nummer der Registerzeile.
     */
    private function register_row(string $prompt): int {
        global $DB;

        $detailid = $DB->insert_record('ai_action_generate_text', (object) [
            'prompt' => $prompt,
            'responseid' => '',
            'fingerprint' => '',
            'generatedcontent' => 'Antwort',
            'finishreason' => 'stop',
            'prompttokens' => 10,
            'completiontoken' => 5,
        ]);

        return (int) $DB->insert_record('ai_action_register', (object) [
            'actionname' => 'generate_text',
            'actionid' => $detailid,
            'success' => 1,
            'userid' => (int) $this->getDataGenerator()->create_user()->id,
            'contextid' => (int) \core\context\system::instance()->id,
            'provider' => 'openai',
            'timecreated' => time(),
            'timecompleted' => time(),
            'model' => 'gpt-4o-mini',
        ]);
    }

    /**
     * Den Bericht so ausgeben, wie die Seite es tut.
     *
     * Die Zeilen einzeln abzufragen bietet Reportbuilder nicht an -- auch der
     * Kern prueft seine Systemberichte ueber die Ausgabe. Das ist hier sogar
     * die bessere Probe: geprueft wird, was jemand sieht.
     *
     * @return string
     */
    private function rendered_report(): string {
        global $PAGE;

        $PAGE->set_url(new \moodle_url('/local/elediaai_core/audit_technical.php'));

        return system_report_factory::create(
            audit_report::class,
            \core\context\system::instance()
        )->output();
    }
}

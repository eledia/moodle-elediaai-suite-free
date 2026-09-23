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
 * Die beiden MCP-Werkzeuge des Kerns.
 *
 * @package    local_elediaai_core
 * @category   test
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core;

use advanced_testcase;
use local_elediaai_core\local\action_recorder;
use local_elediaai_core\local\turn_recorder;
use local_elediaai_core\mcp\moodle_ai_course_insights;
use local_elediaai_core\mcp\moodle_my_ai_data;

defined('MOODLE_INTERNAL') || die();

/**
 * Prueft Katalog, Grenzen und Antworten der beiden Werkzeuge.
 *
 * @covers \local_elediaai_core\mcp\moodle_ai_course_insights
 * @covers \local_elediaai_core\mcp\moodle_my_ai_data
 */
final class mcp_tools_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        // Die Werkzeuge erfuellen einen Vertrag des MCP-Servers. Ohne das
        // Plugin laesst sich ihre Klasse nicht einmal laden -- und die
        // Kind-Pipeline installiert je Job nur das gepruefte Plugin und seine
        // deklarierten Abhaengigkeiten. Der Mount steht deshalb in .ci.env;
        // dieser Waechter ist fuer alles andere.
        if (!interface_exists('\webservice_elediamcp\local\ai\ai_tool')) {
            $this->markTestSkipped('webservice_elediamcp ist nicht installiert.');
        }
    }

    /**
     * Beide Werkzeuge sind lesend -- das steht in den Annotationen, nicht nur im Text.
     *
     * Der MCP-Server liest genau diese Einstufung, um eine Schreibhandlung ins
     * Handlungsprotokoll zu setzen. Ein Leser, der sich als Schreiber
     * deklariert, verfaelscht die Aufsicht.
     */
    public function test_both_tools_declare_themselves_read_only(): void {
        foreach ([moodle_ai_course_insights::class, moodle_my_ai_data::class] as $class) {
            $annotations = $class::annotations();
            $this->assertTrue($annotations['readOnlyHint'], $class);
            $this->assertFalse($annotations['destructiveHint'], $class);
        }
    }

    /**
     * Die Selbstauskunft nimmt gar kein Nutzer-Argument.
     *
     * Nicht ein ungepruefes und nicht ein gepruefes: es soll nichts geben,
     * worauf ein Agent gelenkt werden koennte.
     */
    public function test_the_self_disclosure_takes_no_user_argument(): void {
        $schema = moodle_my_ai_data::input_schema();

        $this->assertSame([], $schema['properties']);
        $this->assertFalse($schema['additionalProperties']);
    }

    /**
     * Die Selbstauskunft antwortet ueber die fragende Person.
     */
    public function test_the_self_disclosure_answers_about_the_caller(): void {
        $anke = $this->getDataGenerator()->create_user();
        $milan = $this->getDataGenerator()->create_user();
        $contextid = (int) \core\context\system::instance()->id;

        $this->record_turn($contextid, (int) $anke->id, 'Varianz');
        $this->record_turn($contextid, (int) $anke->id, 'Varianz');
        $this->record_turn($contextid, (int) $milan->id, 'Varianz');
        action_recorder::record(
            component: 'webservice_elediamcp',
            toolname: 'moodle_grade_submission',
            userid: (int) $anke->id,
            iswrite: true,
        );

        $result = moodle_my_ai_data::execute([], $anke);

        $this->assertSame(2, $result['my_questions_recorded']);
        $this->assertSame(1, $result['actions_on_my_behalf']);
        $this->assertTrue($result['my_questions_are_anonymous']);
        $this->assertStringContainsString('WITHOUT your name', $result['summary']);
    }

    /**
     * Die Kurs-Einblicke verlangen die Berechtigung, im Aufruf selbst.
     *
     * Ein Werkzeug, das im Katalog fehlt, kann ein Agent nicht raten; eines,
     * das trotzdem ausfuehrt, waere das Loch.
     */
    public function test_the_course_insights_tool_refuses_without_permission(): void {
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->expectException(\webservice_elediamcp\local\ai\tool_exception::class);
        moodle_ai_course_insights::execute(['courseid' => (int) $course->id], $student);
    }

    /**
     * Eine Lehrkraft bekommt Luecken, Zahlen und Beispiele -- ohne Namen.
     */
    public function test_a_teacher_gets_gaps_counts_and_examples_without_names(): void {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $contextid = (int) \core\context\course::instance((int) $course->id)->id;

        for ($i = 0; $i < 6; $i++) {
            $person = $this->getDataGenerator()->create_user();
            $this->record_turn($contextid, (int) $person->id, 'Konfidenzintervalle');
        }

        $result = moodle_ai_course_insights::execute(['courseid' => (int) $course->id], $teacher);

        $this->assertSame(6, $result['questions']);
        $this->assertSame(6, $result['askers']);
        $this->assertNotEmpty($result['gaps']);
        $this->assertSame('Konfidenzintervalle', $result['gaps'][0]['topic']);
        $this->assertSame(100, $result['gaps'][0]['uncovered_percent']);
        $this->assertNotEmpty($result['examples']);
        // Kein Feld, das eine Person benennt oder gruppiert.
        $this->assertArrayNotHasKey('askerkey', $result);
        $this->assertStringNotContainsString('askerkey', json_encode($result));
    }

    /**
     * Ohne Fragen sagt das Werkzeug das, statt leere Listen zu liefern.
     */
    public function test_an_empty_course_says_so(): void {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $result = moodle_ai_course_insights::execute(['courseid' => (int) $course->id], $teacher);

        $this->assertSame(0, $result['questions']);
        $this->assertStringContainsString('Nobody asked', $result['summary']);
    }

    /**
     * Ein unbekannter Kurs wird abgewiesen, nicht geraten.
     */
    public function test_an_unknown_course_is_refused(): void {
        $user = $this->getDataGenerator()->create_user();

        $this->expectException(\webservice_elediamcp\local\ai\tool_exception::class);
        moodle_ai_course_insights::execute(['courseid' => 999999], $user);
    }

    /**
     * Der Kern steuert genau diese zwei Werkzeuge bei -- und nicht das Handlungsprotokoll.
     */
    public function test_the_plugin_contributes_exactly_these_two_tools(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/elediaai_core/lib.php');

        $tools = local_elediaai_core_elediamcp_tools();

        $this->assertSame([
            moodle_ai_course_insights::class,
            moodle_my_ai_data::class,
        ], $tools);
        foreach ($tools as $class) {
            $this->assertStringNotContainsString('action', $class::name());
        }
    }

    /**
     * Einen Turn schreiben.
     *
     * @param int $contextid
     * @param int $userid
     * @param string $topic
     * @return void
     */
    private function record_turn(int $contextid, int $userid, string $topic): void {
        turn_recorder::record(
            component: 'block_elediaai_tutor',
            actionname: 'chat_turn',
            contextid: $contextid,
            userid: $userid,
            prompt: 'Frage zu ' . $topic,
            response: 'Antwort zu ' . $topic,
            origin: turn_recorder::ORIGIN_GENERAL,
            topic: $topic,
        );
    }
}

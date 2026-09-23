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
 * Schicht A: was die KI getan hat, in wessen Auftrag.
 *
 * @package    local_elediaai_core
 * @category   test
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core;

use advanced_testcase;
use local_elediaai_core\local\action_recorder;
use local_elediaai_core\local\actions;

defined('MOODLE_INTERNAL') || die();

/**
 * Prueft Schreiber, Lese-API und Aufbewahrung des Handlungsprotokolls.
 *
 * @covers \local_elediaai_core\local\action_recorder
 * @covers \local_elediaai_core\local\actions
 */
final class action_log_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Eine schreibende Handlung kommt mit Person und Einstufung an.
     */
    public function test_a_write_arrives_with_its_person_and_kind(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        action_recorder::record(
            component: 'webservice_elediamcp',
            toolname: 'moodle_grade_submission',
            userid: (int) $user->id,
            iswrite: true,
            durationms: 1800,
            contextid: (int) \core\context\course::instance((int) $course->id)->id,
            courseid: (int) $course->id,
        );

        $rows = $DB->get_records(action_recorder::TABLE);
        $this->assertCount(1, $rows);
        $row = reset($rows);
        $this->assertSame((int) $user->id, (int) $row->userid);
        $this->assertSame('moodle_grade_submission', $row->toolname);
        $this->assertSame(1, (int) $row->iswrite);
        $this->assertSame((int) $course->id, (int) $row->courseid);
        $this->assertSame(1800, (int) $row->durationms);
        $this->assertNull($row->turnid);
    }

    /**
     * Die Person bleibt -- das ist der Unterschied zum Turn-Speicher.
     *
     * Aufsicht ueber das, was eine KI GETAN hat, muss sagen koennen, in wessen
     * Auftrag. Aufsicht ueber das, was sie GESAGT hat, muss das nicht.
     */
    public function test_the_action_log_keeps_the_person_where_the_turn_log_does_not(): void {
        global $DB;

        $actioncolumns = array_keys($DB->get_columns(action_recorder::TABLE));
        $turncolumns = array_keys($DB->get_columns(\local_elediaai_core\local\turn_recorder::TABLE));

        $this->assertContains('userid', $actioncolumns);
        $this->assertNotContains('userid', $turncolumns);
        $this->assertNotContains('askerkey', $actioncolumns);
    }

    /**
     * Inhalte stehen nicht im Handlungsprotokoll.
     */
    public function test_the_action_log_holds_no_content(): void {
        global $DB;

        $columns = array_keys($DB->get_columns(action_recorder::TABLE));

        $this->assertNotContains('prompt', $columns);
        $this->assertNotContains('response', $columns);
    }

    /**
     * Die Kennzahlen zaehlen Schreibhandlungen, Fehlversuche und Personen.
     */
    public function test_the_summary_counts_writes_failures_and_people(): void {
        $anke = $this->getDataGenerator()->create_user();
        $milan = $this->getDataGenerator()->create_user();

        $this->record((int) $anke->id, 'moodle_my_courses', false, true);
        $this->record((int) $anke->id, 'moodle_grade_submission', true, true);
        $this->record((int) $milan->id, 'moodle_enrol_user', true, false);

        $summary = actions::summary();

        $this->assertSame(3, $summary->total);
        $this->assertSame(2, $summary->writes);
        $this->assertSame(1, $summary->failed);
        $this->assertSame(2, $summary->people);
    }

    /**
     * Die Frist entfernt den Bezug und behaelt die Zeile.
     *
     * Ein Aufsichtsnachweis, der verschwindet, weist nichts nach.
     */
    public function test_retention_removes_the_person_and_keeps_the_action(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->record((int) $user->id, 'moodle_grade_submission', true, true);
        $DB->execute(
            'UPDATE {' . action_recorder::TABLE . '} SET timecreated = timecreated - :shift',
            ['shift' => 800 * DAYSECS]
        );

        set_config('action_retentiondays', 730, 'local_elediaai_core');
        $this->assertSame(1, actions::anonymise());

        $this->assertSame(1, $DB->count_records(action_recorder::TABLE));
        $this->assertSame(0, (int) $DB->get_field(action_recorder::TABLE, 'userid', []));
        $this->assertSame(
            'moodle_grade_submission',
            $DB->get_field(action_recorder::TABLE, 'toolname', [])
        );
    }

    /**
     * 0 bewahrt den Bezug unbegrenzt auf.
     */
    public function test_zero_retention_keeps_the_person(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->record((int) $user->id, 'moodle_grade_submission', true, true);
        $DB->execute(
            'UPDATE {' . action_recorder::TABLE . '} SET timecreated = timecreated - :shift',
            ['shift' => 5000 * DAYSECS]
        );

        set_config('action_retentiondays', 0, 'local_elediaai_core');

        $this->assertSame(0, actions::anonymise());
        $this->assertSame((int) $user->id, (int) $DB->get_field(action_recorder::TABLE, 'userid', []));
    }

    /**
     * Eine Loeschanfrage anonymisiert, sie loescht nicht.
     *
     * Sonst koennte jede Person den Nachweis der Bewertung tilgen, die die KI
     * fuer sie geschrieben hat.
     */
    public function test_an_erasure_request_anonymises_instead_of_deleting(): void {
        global $DB;

        $anke = $this->getDataGenerator()->create_user();
        $milan = $this->getDataGenerator()->create_user();
        $this->record((int) $anke->id, 'moodle_grade_submission', true, true);
        $this->record((int) $milan->id, 'moodle_my_courses', false, true);

        $this->assertSame(1, actions::anonymise_for_user((int) $anke->id));

        $this->assertSame(2, $DB->count_records(action_recorder::TABLE));
        $this->assertSame(0, $DB->count_records(action_recorder::TABLE, ['userid' => $anke->id]));
        $this->assertSame(1, $DB->count_records(action_recorder::TABLE, ['userid' => $milan->id]));
    }

    /**
     * Die Selbstauskunft liefert nur die eigenen Handlungen.
     */
    public function test_the_self_disclosure_returns_only_your_own_actions(): void {
        $anke = $this->getDataGenerator()->create_user();
        $milan = $this->getDataGenerator()->create_user();
        $this->record((int) $anke->id, 'moodle_grade_submission', true, true);
        $this->record((int) $milan->id, 'moodle_my_courses', false, true);

        $mine = actions::for_user((int) $anke->id);

        $this->assertCount(1, $mine);
        $this->assertSame('moodle_grade_submission', $mine[0]->toolname);
    }

    /**
     * Ein kaputter Schreibvorgang erreicht den Aufrufer nicht.
     */
    public function test_a_broken_write_does_not_reach_the_caller(): void {
        global $DB;

        $DB->get_manager()->drop_table(new \xmldb_table(action_recorder::TABLE));

        action_recorder::record(
            component: 'webservice_elediamcp',
            toolname: 'moodle_my_courses',
            userid: 1,
        );

        $this->assertDebuggingCalled();
    }

    /**
     * Eine Handlung schreiben.
     *
     * @param int $userid
     * @param string $toolname
     * @param bool $iswrite
     * @param bool $success
     * @return void
     */
    private function record(int $userid, string $toolname, bool $iswrite, bool $success): void {
        action_recorder::record(
            component: 'webservice_elediamcp',
            toolname: $toolname,
            userid: $userid,
            iswrite: $iswrite,
            success: $success,
        );
    }
}

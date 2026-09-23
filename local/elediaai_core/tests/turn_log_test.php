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
 * Schicht B: der anonyme Turn-Speicher der Suite.
 *
 * @package    local_elediaai_core
 * @category   test
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core;

use advanced_testcase;
use local_elediaai_core\local\insights;
use local_elediaai_core\local\pseudonym;
use local_elediaai_core\local\turn_recorder;

defined('MOODLE_INTERNAL') || die();

/**
 * Prueft Schreiber, Pseudonym und Lese-API.
 *
 * @covers \local_elediaai_core\local\turn_recorder
 * @covers \local_elediaai_core\local\pseudonym
 * @covers \local_elediaai_core\local\insights
 */
final class turn_log_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Ein Turn kommt an, und er nennt sein Feature.
     *
     * Das ist der ganze Grund fuer eine eigene Tabelle: Moodles
     * ai_action_register hat keine component-Spalte.
     */
    public function test_a_turn_arrives_and_names_its_feature(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        turn_recorder::record(
            component: 'block_elediaai_tutor',
            actionname: 'chat_turn',
            contextid: (int) \core\context\system::instance()->id,
            userid: (int) $user->id,
            prompt: 'Was ist Varianz?',
            response: 'Die mittlere quadratische Abweichung.',
            prompttokens: 40,
            completiontokens: 12,
        );

        $rows = $DB->get_records(turn_recorder::TABLE);
        $this->assertCount(1, $rows);
        $row = reset($rows);
        $this->assertSame('block_elediaai_tutor', $row->component);
        $this->assertSame('chat_turn', $row->actionname);
        $this->assertSame('Was ist Varianz?', $row->prompt);
        $this->assertSame('Die mittlere quadratische Abweichung.', $row->response);
        $this->assertSame(40, (int) $row->prompttokens);
        $this->assertSame(12, (int) $row->completiontokens);
    }

    /**
     * Die Nutzerkennung erreicht die Tabelle nie.
     *
     * Betreiberentscheidung vom 05.09.2026. Beim Schreiben festgelegt, nicht
     * beim Anzeigen verborgen -- deshalb pruefen wir die Spalten, nicht die
     * Ausgabe.
     */
    public function test_the_user_id_never_enters_the_table(): void {
        global $DB;

        $columns = array_keys($DB->get_columns(turn_recorder::TABLE));
        $this->assertNotContains('userid', $columns);
        $this->assertContains('askerkey', $columns);
    }

    /**
     * Dieselbe Person ergibt denselben Schluessel, zwei Personen nie denselben.
     */
    public function test_the_pseudonym_is_stable_per_person_and_unique_between_them(): void {
        $anke = $this->getDataGenerator()->create_user();
        $milan = $this->getDataGenerator()->create_user();

        $first = pseudonym::for_user((int) $anke->id);
        $second = pseudonym::for_user((int) $anke->id);

        $this->assertSame($first, $second);
        $this->assertNotSame($first, pseudonym::for_user((int) $milan->id));
        $this->assertSame(64, strlen($first));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $first);
    }

    /**
     * Ohne Person gibt es keinen Schluessel -- und keinen Platzhalter, der
     * aussieht wie einer.
     */
    public function test_no_person_means_no_key(): void {
        $this->assertSame('', pseudonym::for_user(0));
        $this->assertSame('', pseudonym::for_user(-1));
    }

    /**
     * Der Schluessel ist nicht die Nutzerkennung, auch nicht gehasht ohne Salz.
     *
     * Ohne Salz waere er aus einer Nutzerliste in Sekunden rueckrechenbar und
     * das Pseudonym waere Theater.
     */
    public function test_the_key_is_salted(): void {
        $user = $this->getDataGenerator()->create_user();

        $key = pseudonym::for_user((int) $user->id);

        $this->assertNotSame(hash('sha256', (string) $user->id), $key);
        $this->assertNotSame(hash('sha256', $user->id . '|'), $key);
        $salt = get_config('local_elediaai_core', pseudonym::SALT_SETTING);
        $this->assertIsString($salt);
        $this->assertNotSame('', $salt);
    }

    /**
     * Das Salz wird nie ersetzt -- sonst waere jeder bereits gespeicherte
     * Schluessel verwaist.
     */
    public function test_the_salt_is_never_replaced(): void {
        $first = pseudonym::ensure_salt();
        $this->assertSame($first, pseudonym::ensure_salt());
    }

    /**
     * Fuenf Fragen von fuenf Personen sind nicht fuenf Fragen von einer.
     *
     * Genau das kann der Bericht ohne den Schluessel nicht unterscheiden -- und
     * es ist der Unterschied zwischen einem Kursproblem und einem Einzelfall.
     */
    public function test_five_askers_are_not_one_asker(): void {
        $course = $this->getDataGenerator()->create_course();
        $contextid = (int) \core\context\course::instance((int) $course->id)->id;

        $einzelperson = $this->getDataGenerator()->create_user();
        for ($i = 0; $i < 5; $i++) {
            $this->record_turn($contextid, (int) $einzelperson->id, 'Varianz');
        }
        $eine = insights::summary((int) $course->id);

        $this->assertSame(5, $eine->total);
        $this->assertSame(1, $eine->askers);

        // Jetzt fuenf verschiedene.
        $course2 = $this->getDataGenerator()->create_course();
        $contextid2 = (int) \core\context\course::instance((int) $course2->id)->id;
        for ($i = 0; $i < 5; $i++) {
            $person = $this->getDataGenerator()->create_user();
            $this->record_turn($contextid2, (int) $person->id, 'Varianz');
        }
        $fuenf = insights::summary((int) $course2->id);

        $this->assertSame(5, $fuenf->total);
        $this->assertSame(5, $fuenf->askers);
    }

    /**
     * Loeschen trifft genau die Zeilen einer Person.
     *
     * Der Schluessel wird dazu neu berechnet, nie aufgeloest.
     */
    public function test_erasure_hits_exactly_one_person(): void {
        global $DB;

        $contextid = (int) \core\context\system::instance()->id;
        $anke = $this->getDataGenerator()->create_user();
        $milan = $this->getDataGenerator()->create_user();

        $this->record_turn($contextid, (int) $anke->id, 'Varianz');
        $this->record_turn($contextid, (int) $anke->id, 'Varianz');
        $this->record_turn($contextid, (int) $milan->id, 'Varianz');

        $removed = insights::delete_for_user((int) $anke->id);

        $this->assertSame(2, $removed);
        $this->assertSame(1, $DB->count_records(turn_recorder::TABLE));
        $this->assertSame(
            pseudonym::for_user((int) $milan->id),
            $DB->get_field(turn_recorder::TABLE, 'askerkey', [])
        );
    }

    /**
     * Die Selbstauskunft liefert den ganzen Austausch, nicht die Haelfte.
     */
    public function test_a_data_subject_gets_question_and_answer(): void {
        $contextid = (int) \core\context\system::instance()->id;
        $user = $this->getDataGenerator()->create_user();
        $this->record_turn($contextid, (int) $user->id, 'Varianz');

        $turns = insights::turns_for_user((int) $user->id);

        $this->assertCount(1, $turns);
        $this->assertNotSame('', $turns[0]->prompt);
        $this->assertNotSame('', $turns[0]->response);
    }

    /**
     * Der Kurs wird beim Schreiben abgeleitet, nicht bei jeder Abfrage.
     */
    public function test_the_course_is_resolved_once_at_write_time(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);
        $contextid = (int) \core\context\module::instance((int) $forum->cmid)->id;
        $user = $this->getDataGenerator()->create_user();

        $this->record_turn($contextid, (int) $user->id, 'Varianz');

        $this->assertSame(
            (int) $course->id,
            (int) $DB->get_field(turn_recorder::TABLE, 'courseid', [])
        );
    }

    /**
     * Ein Kontext ausserhalb jedes Kurses ergibt 0, keinen Fehler.
     */
    public function test_a_context_outside_any_course_is_course_zero(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->record_turn((int) \core\context\system::instance()->id, (int) $user->id, 'Varianz');

        $this->assertSame(0, (int) $DB->get_field(turn_recorder::TABLE, 'courseid', []));
    }

    /**
     * Eine unbekannte Herkunft landet im ehrlichen Rueckfall.
     *
     * Ein freier Wert wuerde die Zeile aus jeder Gedeckt-Quote fallen lassen,
     * ohne dass es jemand meldet.
     */
    public function test_an_unknown_origin_falls_back_instead_of_being_stored(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        turn_recorder::record(
            component: 'local_test',
            actionname: 'chat_turn',
            contextid: (int) \core\context\system::instance()->id,
            userid: (int) $user->id,
            prompt: 'x',
            response: 'y',
            origin: 'irgendwas',
        );

        $this->assertSame(
            turn_recorder::ORIGIN_GENERAL,
            $DB->get_field(turn_recorder::TABLE, 'origin', [])
        );
    }

    /**
     * Ein kaputter Schreibvorgang erreicht den Aufrufer nicht.
     *
     * Ein Turn, der beantwortet wurde, darf nicht scheitern, weil die
     * Buchfuehrung scheiterte.
     */
    public function test_a_broken_write_does_not_reach_the_caller(): void {
        global $DB;

        $dbman = $DB->get_manager();
        $table = new \xmldb_table(turn_recorder::TABLE);
        $dbman->drop_table($table);

        turn_recorder::record(
            component: 'local_test',
            actionname: 'chat_turn',
            contextid: (int) \core\context\system::instance()->id,
            userid: 1,
            prompt: 'x',
            response: 'y',
        );

        // Die Meldung geht an die Entwickler-Diagnose, nicht an den Aufrufer.
        $this->assertDebuggingCalled();
    }

    /**
     * Die Luecken-Liste sortiert nach Anteil, nicht nach Menge.
     *
     * Das ist die inhaltliche Aenderung gegenueber dem alten Report: ein Thema,
     * das elfmal gefragt wird und nirgends im Material steht, ist wichtiger als
     * eines, das vierzigmal gefragt und jedes Mal beantwortet wird.
     */
    public function test_gaps_rank_by_share_not_by_volume(): void {
        $course = $this->getDataGenerator()->create_course();
        $contextid = (int) \core\context\course::instance((int) $course->id)->id;

        // Viel gefragt, gut gedeckt.
        for ($i = 0; $i < 40; $i++) {
            $person = $this->getDataGenerator()->create_user();
            $this->record_turn($contextid, (int) $person->id, 'Varianz', turn_recorder::ORIGIN_GROUNDED);
        }
        // Selten gefragt, nie gedeckt.
        for ($i = 0; $i < 11; $i++) {
            $person = $this->getDataGenerator()->create_user();
            $this->record_turn($contextid, (int) $person->id, 'Konfidenzintervalle');
        }

        $gaps = insights::gaps((int) $course->id);

        $this->assertNotEmpty($gaps);
        $this->assertSame('Konfidenzintervalle', $gaps[0]->label);
        $this->assertSame(100, $gaps[0]->ungroundedpct);
        $this->assertSame(11, $gaps[0]->total);
        $this->assertSame(11, $gaps[0]->askers);
    }

    /**
     * Ein einzelner ungedeckter Treffer ist keine Luecke.
     *
     * Er waere zu 100 % ungedeckt und stuende sonst ueber allem.
     */
    public function test_a_single_question_is_not_a_gap(): void {
        $course = $this->getDataGenerator()->create_course();
        $contextid = (int) \core\context\course::instance((int) $course->id)->id;
        $person = $this->getDataGenerator()->create_user();

        $this->record_turn($contextid, (int) $person->id, 'Einzelfall');

        $this->assertSame([], insights::gaps((int) $course->id));
    }

    /**
     * Material vorhanden und trotzdem nachgefasst -- der zweite Befund.
     */
    public function test_unclear_material_is_found_by_follow_up_questions(): void {
        $course = $this->getDataGenerator()->create_course();
        $contextid = (int) \core\context\course::instance((int) $course->id)->id;
        $now = time();

        for ($i = 0; $i < 3; $i++) {
            $person = $this->getDataGenerator()->create_user();
            // Zwei Fragen derselben Person zum selben Thema, zehn Minuten
            // auseinander: die erste Antwort hat nicht getragen.
            $this->record_turn(
                $contextid,
                (int) $person->id,
                'Varianz',
                turn_recorder::ORIGIN_GROUNDED,
                $now - 1200
            );
            $this->record_turn(
                $contextid,
                (int) $person->id,
                'Varianz',
                turn_recorder::ORIGIN_GROUNDED,
                $now - 600
            );
        }

        $unclear = insights::unclear((int) $course->id);

        $this->assertNotEmpty($unclear);
        $this->assertSame('Varianz', $unclear[0]->label);
        $this->assertSame(6, $unclear[0]->total);
        $this->assertSame(3, $unclear[0]->followups);
        $this->assertSame(50, $unclear[0]->followuppct);
    }

    /**
     * Der Verlauf fuellt Tage ohne Fragen mit Null und sortiert als Datum.
     *
     * userdate('%Y-%m-%d') streicht die fuehrende Null und sortiert
     * '2026-08-6' hinter '2026-08-29'.
     */
    public function test_the_daily_series_has_no_holes_and_sorts_as_a_date(): void {
        $course = $this->getDataGenerator()->create_course();
        $contextid = (int) \core\context\course::instance((int) $course->id)->id;
        $person = $this->getDataGenerator()->create_user();
        $this->record_turn($contextid, (int) $person->id, 'Varianz');

        $series = insights::per_day((int) $course->id, 14);

        $this->assertCount(14, $series);
        $this->assertSame(1, array_sum($series));
        $keys = array_keys($series);
        $sorted = $keys;
        sort($sorted);
        $this->assertSame($sorted, $keys);
        foreach ($keys as $key) {
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $key);
        }
    }

    /**
     * Mehrere Turns in derselben Sekunde brechen den Verlauf nicht.
     *
     * get_records_sql schluesselt nach der ersten Spalte und wirft, sobald sie
     * doppelt vorkommt. Der erste Anlauf las `SELECT timecreated` -- und eine
     * Klasse, die gleichzeitig fragt, liess die Seite fliegen. Der vorige Test
     * schrieb nur einen Turn und sah es nicht; gefunden hat es erst ein echter
     * Seitenaufruf.
     */
    public function test_several_turns_in_the_same_second_do_not_break_the_trend(): void {
        $course = $this->getDataGenerator()->create_course();
        $contextid = (int) \core\context\course::instance((int) $course->id)->id;
        $now = time();

        for ($i = 0; $i < 5; $i++) {
            $person = $this->getDataGenerator()->create_user();
            $this->record_turn($contextid, (int) $person->id, 'Varianz', turn_recorder::ORIGIN_GENERAL, $now);
        }

        $series = insights::per_day((int) $course->id, 14);

        $this->assertCount(14, $series);
        $this->assertSame(5, array_sum($series));
    }

    /**
     * Dasselbe Thema in zwei Kursen bricht die Unklar-Liste nicht.
     *
     * Site-weit gruppiert die Abfrage nach Thema UND Kurs; das Thema allein ist
     * als Schluessel dann nicht eindeutig.
     */
    public function test_the_same_topic_in_two_courses_does_not_break_the_unclear_list(): void {
        $now = time();
        foreach ([1, 2] as $nr) {
            $course = $this->getDataGenerator()->create_course();
            $contextid = (int) \core\context\course::instance((int) $course->id)->id;
            for ($i = 0; $i < 3; $i++) {
                $person = $this->getDataGenerator()->create_user();
                $this->record_turn($contextid, (int) $person->id, 'Varianz', turn_recorder::ORIGIN_GROUNDED, $now - 1200);
                $this->record_turn($contextid, (int) $person->id, 'Varianz', turn_recorder::ORIGIN_GROUNDED, $now - 600);
            }
        }

        // 0 heisst site-weit: beide Kurse, dasselbe Thema.
        $unclear = insights::unclear(0);

        $this->assertCount(2, $unclear);
        foreach ($unclear as $item) {
            $this->assertSame('Varianz', $item->label);
            $this->assertSame(6, $item->total);
        }
    }

    /**
     * Ein Thema ohne eine einzige Nachfrage steht nicht in der Liste.
     *
     * Dieselbe Regel wie bei den Luecken: die Liste heisst „Material da, traegt
     * aber nicht". Ein Thema mit 0 % Nachfragen ist der Gegenbeweis dazu, und
     * es dort zu zeigen laesst den Leser an der ganzen Liste zweifeln.
     */
    public function test_a_topic_nobody_asked_again_is_not_unclear(): void {
        $now = time();
        $course = $this->getDataGenerator()->create_course();
        $contextid = (int) \core\context\course::instance((int) $course->id)->id;

        // Vier Fragen, vier verschiedene Personen, weit auseinander: niemand
        // hat nachgefasst.
        for ($i = 0; $i < 4; $i++) {
            $person = $this->getDataGenerator()->create_user();
            $this->record_turn(
                $contextid,
                (int) $person->id,
                'Regression',
                turn_recorder::ORIGIN_GROUNDED,
                $now - ($i * DAYSECS)
            );
        }
        // Und ein Thema, bei dem jemand zweimal fragte.
        $nachfragerin = $this->getDataGenerator()->create_user();
        for ($i = 0; $i < 3; $i++) {
            $this->record_turn(
                $contextid,
                (int) $nachfragerin->id,
                'Varianz',
                turn_recorder::ORIGIN_GROUNDED,
                $now - 900 + ($i * 300)
            );
        }

        $unclear = insights::unclear((int) $course->id);

        $this->assertCount(1, $unclear);
        $this->assertSame('Varianz', $unclear[0]->label);
    }

    /**
     * Eine geschaetzte Tokenzahl ist als solche gekennzeichnet.
     *
     * Der Fund dahinter: meldet ein Chat-Backend keinen Verbrauch, bucht der
     * Engpass eine Schaetzung auf das Guthaben -- das Protokoll schrieb aber
     * die rohe Null der Antwort. Der Bericht sagte damit „hat nichts
     * gekostet" ueber eine Anfrage, die abgerechnet wurde.
     */
    public function test_an_estimated_token_count_says_so(): void {
        global $DB;

        $contextid = (int) \core\context\system::instance()->id;
        $person = $this->getDataGenerator()->create_user();

        $gemeldet = turn_recorder::record(
            component: 'block_elediaai_tutor',
            actionname: 'chat_turn',
            contextid: $contextid,
            userid: (int) $person->id,
            prompt: 'Gemessen',
            response: 'Antwort',
            prompttokens: 120,
            completiontokens: 60,
        );
        $geschaetzt = turn_recorder::record(
            component: 'block_elediaai_tutor',
            actionname: 'chat_turn',
            contextid: $contextid,
            userid: (int) $person->id,
            prompt: 'Geschaetzt',
            response: 'Antwort',
            prompttokens: 90,
            completiontokens: 40,
            tokensestimated: true,
        );

        $this->assertSame(0, (int) $DB->get_field(
            turn_recorder::TABLE,
            'tokensestimated',
            ['id' => $gemeldet]
        ));
        $this->assertSame(1, (int) $DB->get_field(
            turn_recorder::TABLE,
            'tokensestimated',
            ['id' => $geschaetzt]
        ));
        // Die Zahlen selbst stehen in beiden Faellen da -- eine Schaetzung ist
        // keine fehlende Angabe.
        $this->assertSame(90, (int) $DB->get_field(
            turn_recorder::TABLE,
            'prompttokens',
            ['id' => $geschaetzt]
        ));
    }

    /**
     * Ohne Angabe gilt eine Zahl als gemeldet.
     */
    public function test_a_recorded_count_is_reported_unless_stated(): void {
        global $DB;

        $id = turn_recorder::record(
            component: 'local_elediaai_questiongen',
            actionname: 'generate_text',
            contextid: (int) \core\context\system::instance()->id,
            userid: (int) $this->getDataGenerator()->create_user()->id,
            prompt: 'Frage',
            response: 'Antwort',
            prompttokens: 10,
            completiontokens: 5,
        );

        $this->assertSame(0, (int) $DB->get_field(
            turn_recorder::TABLE,
            'tokensestimated',
            ['id' => $id]
        ));
    }

    /**
     * Die Aufbewahrung ist einstellbar, und 0 bewahrt unbegrenzt auf.
     */
    public function test_retention_is_configurable_and_zero_keeps_everything(): void {
        global $DB;

        $contextid = (int) \core\context\system::instance()->id;
        $person = $this->getDataGenerator()->create_user();
        $this->record_turn($contextid, (int) $person->id, 'Varianz', turn_recorder::ORIGIN_GENERAL, time() - (100 * DAYSECS));

        set_config('turn_retentiondays', 0, 'local_elediaai_core');
        $this->assertSame(0, insights::prune());
        $this->assertSame(1, $DB->count_records(turn_recorder::TABLE));

        set_config('turn_retentiondays', 90, 'local_elediaai_core');
        $this->assertSame(90, insights::retention_days());
        $this->assertSame(1, insights::prune());
        $this->assertSame(0, $DB->count_records(turn_recorder::TABLE));
    }

    /**
     * Der Wortlaut fuer die Lehrkraft traegt kein Pseudonym mit.
     *
     * Eine Lehrkraft, die Fragen liest, braucht keinen Schluessel, der sie nach
     * Person gruppiert -- auch keinen unaufloesbaren.
     */
    public function test_the_verbatim_list_carries_no_key(): void {
        $course = $this->getDataGenerator()->create_course();
        $contextid = (int) \core\context\course::instance((int) $course->id)->id;
        $person = $this->getDataGenerator()->create_user();
        $this->record_turn($contextid, (int) $person->id, 'Varianz');

        $recent = insights::recent((int) $course->id);

        $this->assertCount(1, $recent);
        $this->assertObjectNotHasProperty('askerkey', $recent[0]);
        $this->assertObjectNotHasProperty('response', $recent[0]);
        $this->assertObjectHasProperty('prompt', $recent[0]);
    }

    /**
     * Der Bericht zeigt die Turns und nennt das Feature.
     */
    public function test_the_report_renders_the_turns_with_their_feature(): void {
        if (!\local_elediaai_core\local\audit_config::feature_available()) {
            $this->markTestSkipped('Der Audit-Zugang haengt an Moodles KI-Register (Moodle 5.0+).');
        }

        $this->setAdminUser();
        $contextid = (int) \core\context\system::instance()->id;
        $person = $this->getDataGenerator()->create_user();

        // Komponente ist dieses Plugin selbst. Die Kind-Pipeline installiert je
        // Job nur das geprueffte Plugin und seine deklarierten Abhaengigkeiten --
        // ein Test, der den Anzeigenamen eines Geschwister-Plugins behauptet,
        // faellt dort um, obwohl er lokal gruen ist. Genau das ist am
        // 19.09.2026 passiert.
        turn_recorder::record(
            component: 'local_elediaai_core',
            actionname: 'chat_turn',
            contextid: $contextid,
            userid: (int) $person->id,
            prompt: 'Frage zu Varianz',
            response: 'Antwort zu Varianz',
            topic: 'Varianz',
        );

        $report = \core_reportbuilder\system_report_factory::create(
            \local_elediaai_core\reportbuilder\local\systemreports\turns::class,
            \core\context\system::instance()
        );
        $output = $report->output();

        $this->assertStringContainsString(
            get_string('pluginname', 'local_elediaai_core'),
            $output
        );
        $this->assertStringContainsString('Frage zu Varianz', $output);
    }

    /**
     * Ohne Berechtigung zeigt der Bericht nichts.
     */
    public function test_the_report_refuses_without_permission(): void {
        if (!\local_elediaai_core\local\audit_config::feature_available()) {
            $this->markTestSkipped('Der Audit-Zugang haengt an Moodles KI-Register (Moodle 5.0+).');
        }

        $this->setUser($this->getDataGenerator()->create_user());

        $this->expectException(\moodle_exception::class);
        \core_reportbuilder\system_report_factory::create(
            \local_elediaai_core\reportbuilder\local\systemreports\turns::class,
            \core\context\system::instance()
        );
    }

    /**
     * Einen Turn schreiben, kurz.
     *
     * @param int $contextid
     * @param int $userid
     * @param string $topic
     * @param string $origin
     * @param int|null $when
     * @return void
     */
    private function record_turn(
        int $contextid,
        int $userid,
        string $topic,
        string $origin = turn_recorder::ORIGIN_GENERAL,
        ?int $when = null
    ): void {
        turn_recorder::record(
            component: 'block_elediaai_tutor',
            actionname: 'chat_turn',
            contextid: $contextid,
            userid: $userid,
            prompt: 'Frage zu ' . $topic,
            response: 'Antwort zu ' . $topic,
            prompttokens: 10,
            completiontokens: 5,
            origin: $origin,
            topic: $topic,
            timestarted: $when,
        );
    }
}

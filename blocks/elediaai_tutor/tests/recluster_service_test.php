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

declare(strict_types=1);

namespace block_elediaai_tutor;

use PHPUnit\Framework\Attributes\CoversClass;
use local_elediaai_core\local\turn_recorder;
use block_elediaai_tutor\local\recluster_service;
use local_elediaai_chatengine\local\service_user;
use local_elediaai_chatengine\tests\fixtures\fake_adapter;
use moodle_url;

/**
 * Unit tests for the batch topic reclustering service.
 *
 * Mostly connector-independent: the maintenance token is injected so these
 * tests run without webservice_elediamcp installed; only the end-to-end token
 * minting test requires the connector (and skips without it).
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\block_elediaai_tutor\local\recluster_service::class)]
#[CoversClass(\block_elediaai_tutor\task\recluster_questions::class)]
final class recluster_service_test extends \advanced_testcase {
    /**
     * Load the fake transport helper.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/elediaai_chatengine/tests/fixtures/fake_adapter.php');
        parent::setUpBeforeClass();
    }

    /**
     * Without configuration the run is a strict no-op.
     */
    public function test_unconfigured_is_noop(): void {
        $this->resetAfterTest();
        $this->assertFalse(recluster_service::is_configured());

        $stats = recluster_service::run();
        $this->assertSame(['courses' => 0, 'batches' => 0, 'updated' => 0, 'failed' => 0], $stats);
    }

    /**
     * The maintenance account is auto-created once and reused, with
     * webservice-only auth and no interactive login.
     */
    public function test_service_user_autocreated_and_reused(): void {
        global $DB;
        $this->resetAfterTest();

        $this->assertFalse($DB->record_exists('user', ['username' => service_user::USERNAME]));

        $first = service_user::get_or_create();
        $second = service_user::get_or_create();

        $this->assertSame((int) $first->id, (int) $second->id);
        $this->assertSame('webservice', $first->auth);
        $this->assertEquals(1, $first->confirmed);
        $this->assertEquals(0, $first->suspended);
        $this->assertSame(1, $DB->count_records('user', ['username' => service_user::USERNAME]));
    }

    /**
     * A configured run sends the batch (with the existing label registry and
     * the maintenance token) and applies the returned labels.
     */
    public function test_run_converges_labels(): void {
        global $DB;
        $this->resetAfterTest();
                set_config('reclustertoolname', 'tutor_recluster_questions', 'block_elediaai_tutor');
        set_config('ragserverurl', 'https://rag.example.com/mcp', 'block_elediaai_tutor');

        $user = $this->getDataGenerator()->create_user();
        $uid = (int) $user->id;
        // Two phrasings without a topic, one with a drifted label.
        $this->turn($uid, 7, 'When is the essay due?', null, 'Essay 2');
        $this->turn($uid, 7, 'essay deadline?');
        $this->turn($uid, 7, 'wann ist der essay fällig', 'Essay deadline (old)');
        $ids = array_map('intval', array_keys($DB->get_records(turn_recorder::TABLE, [], 'id ASC', 'id')));

        $adapter = new fake_adapter();
        $adapter->toolresult = fake_adapter::json_result(['topics' => [
            ['id' => $ids[0], 'topic' => 'Assignments & deadlines'],
            ['id' => $ids[1], 'topic' => 'Assignments & deadlines'],
            ['id' => $ids[2], 'topic' => 'Assignments & deadlines'],
        ]]);
        $maintainer = $this->getDataGenerator()->create_user();

        $stats = recluster_service::run($adapter, (int) $maintainer->id);

        $this->assertSame(1, $stats['courses']);
        $this->assertSame(1, $stats['batches']);
        $this->assertSame(3, $stats['updated']);
        $this->assertSame(0, $stats['failed']);

        // The call carried the label registry and acted as the maintenance
        // account, not as whoever triggered the task.
        [$tool, $arguments, $calleduserid] = $adapter->toolcalls[0];
        $this->assertSame('recluster', $tool);
        $this->assertSame((int) $maintainer->id, $calleduserid);
        $this->assertSame(['Essay deadline (old)'], $arguments['existing_labels']);
        $this->assertCount(3, $arguments['questions']);

        // Alle drei Zeilen tragen jetzt dasselbe Label -- in der Lueckenliste
        // ist das ein Eintrag mit drei Fragen.
        $luecken = \local_elediaai_core\local\insights::gaps(7, 30, 10);
        $this->assertCount(1, $luecken);
        $this->assertSame('Assignments & deadlines', $luecken[0]->label);
        $this->assertSame(3, $luecken[0]->total);
    }

    /**
     * Without an injected token, run() auto-provisions the maintenance account
     * and mints a real component token for it (requires the connector).
     */
    public function test_run_mints_maintenance_token(): void {
        global $DB, $CFG;
        $this->resetAfterTest();

        if (!class_exists('\\webservice_elediamcp\\api')) {
            $this->markTestSkipped('webservice_elediamcp connector plugin is not installed.');
        }
        require_once($CFG->dirroot . '/webservice/lib.php');
        $service = (object) [
            'name' => 'MCP test service', 'shortname' => 'mcptest', 'enabled' => 1,
            'restrictedusers' => 0, 'downloadfiles' => 0, 'uploadfiles' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ];
        $service->id = $DB->insert_record('external_services', $service);
        set_config('services', (string) $service->id, 'webservice_elediamcp');
        set_config('mcpserviceid', $service->id, 'block_elediaai_tutor');
        set_config('ragserverurl', 'https://rag.example.com/mcp', 'block_elediaai_tutor');
                set_config('reclustertoolname', 'tutor_recluster_questions', 'block_elediaai_tutor');

        $user = $this->getDataGenerator()->create_user();
        $this->turn((int) $user->id, 7, 'a question');
        $ids = array_keys($DB->get_records(turn_recorder::TABLE, [], 'id ASC', 'id'));

        $adapter = new fake_adapter();
        $adapter->toolresult = fake_adapter::json_result([
            'topics' => [['id' => (int) $ids[0], 'topic' => 'General']],
        ]);

        $stats = recluster_service::run($adapter);

        $this->assertSame(1, $stats['updated']);
        // With no account supplied the maintenance one is created and used, so
        // a nightly run is never attributed to a real person.
        $serviceuser = service_user::get_or_create();
        [, , $calleduserid] = $adapter->toolcalls[0];
        $this->assertSame((int) $serviceuser->id, $calleduserid);
    }

    /**
     * A failing batch is counted and skips the course without aborting the run.
     */
    public function test_failed_batch_is_contained(): void {
        $this->resetAfterTest();
                set_config('reclustertoolname', 'tutor_recluster_questions', 'block_elediaai_tutor');
        set_config('ragserverurl', 'https://rag.example.com/mcp', 'block_elediaai_tutor');

        $user = $this->getDataGenerator()->create_user();
        $this->turn((int) $user->id, 7, 'a question');

        $adapter = new fake_adapter();
        $adapter->toolqueue = [
            new \local_elediaai_chatengine\adapter\adapter_exception(
                'error_backend_unavailable',
                'http status 502',
                502
            ),
        ];
        $maintainer = $this->getDataGenerator()->create_user();

        $stats = recluster_service::run($adapter, (int) $maintainer->id);
        $this->assertDebuggingCalled(null, DEBUG_DEVELOPER);

        $this->assertSame(1, $stats['courses']);
        $this->assertSame(0, $stats['updated']);
        $this->assertSame(1, $stats['failed']);
    }

    /**
     * The scheduled task wires through and reports.
     */
    public function test_task_runs(): void {
        $this->resetAfterTest();
        $task = new \block_elediaai_tutor\task\recluster_questions();
        ob_start();
        $task->execute();
        $output = ob_get_clean();
        $this->assertStringContainsString('not configured', $output);
    }

    /**
     * Einen Turn schreiben, wie ihn die Engine schreiben wuerde.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $frage
     * @param string|null $thema
     * @param string|null $quelle
     * @return void
     */
    private function turn(
        int $userid,
        int $courseid,
        string $frage,
        ?string $thema = null,
        ?string $quelle = null
    ): void {
        global $DB;

        $id = turn_recorder::record(
            component: 'block_elediaai_tutor',
            actionname: 'chat_turn',
            contextid: (int) \core\context\system::instance()->id,
            userid: $userid,
            prompt: $frage,
            response: 'Antwort',
            topic: $thema,
            sourcetitle: $quelle,
        );

        // Der Schreiber leitet den Kurs aus dem Kontext ab. Diese Tests
        // arbeiten mit einer festen Kursnummer statt mit einem angelegten Kurs,
        // also wird sie hier gesetzt -- alles andere kommt aus dem echten Weg.
        $DB->set_field(turn_recorder::TABLE, 'courseid', $courseid, ['id' => $id]);
    }
}

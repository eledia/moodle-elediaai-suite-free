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
use block_elediaai_tutor\local\consent;
use block_elediaai_tutor\local\copilot_service;
use local_elediaai_core\local\turn_recorder;
use local_elediaai_chatengine\adapter\chat_response;
use local_elediaai_chatengine\tests\fixtures\fake_adapter;
use moodle_url;

/**
 * Tests for the Teacher-Copilot analysis service.
 *
 * @package     block_elediaai_tutor
 * @author      Johannes Moskaliuk
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\block_elediaai_tutor\local\copilot_service::class)]
final class copilot_service_test extends \advanced_testcase {
    /**
     * Load the fake transport.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/elediaai_chatengine/tests/fixtures/fake_adapter.php');
        parent::setUpBeforeClass();
    }

    /**
     * Configure an MCP service and point the block at it (token provisioning).
     *
     * @return void
     */
    private function configure_mcp_service(): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/webservice/lib.php');

        if (!class_exists('\\webservice_elediamcp\\api')) {
            $this->markTestSkipped('webservice_elediamcp connector plugin is not installed.');
        }

        $service = (object) [
            'name' => 'MCP test service',
            'shortname' => 'mcptest',
            'enabled' => 1,
            'restrictedusers' => 0,
            'downloadfiles' => 0,
            'uploadfiles' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $service->id = $DB->insert_record('external_services', $service);

        set_config('services', (string) $service->id, 'webservice_elediamcp');
        set_config('mcpserviceid', $service->id, 'block_elediaai_tutor');
        set_config('ragserverurl', 'https://rag.example.com/mcp', 'block_elediaai_tutor');
        set_config('chattoolname', 'tutor_chat', 'block_elediaai_tutor');
        set_config('enableanalytics', 1, 'block_elediaai_tutor');
    }

    /**
     * Course + consented teacher + a few logged questions.
     *
     * @return array{course: \stdClass, teacher: \stdClass, context: \core\context\course}
     */
    private function seed_course(): array {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);
        $context = \core\context\course::instance($course->id);
        consent::give((int) $teacher->id, $context);

        // Quelle des Copilots ist seit dem 19.09.2026 der Turn-Speicher der
        // Suite. Drei Fragen zu einem Thema, damit gaps() sie ueberhaupt
        // annimmt -- ein Einzeltreffer ist zu 100 % ungedeckt und waere keine
        // Aussage.
        foreach (['What is a normal distribution?', 'How do I compute the variance?', 'Variance again?'] as $frage) {
            $student = $this->getDataGenerator()->create_user();
            turn_recorder::record(
                component: 'block_elediaai_tutor',
                actionname: 'chat_turn',
                contextid: (int) $context->id,
                userid: (int) $student->id,
                prompt: $frage,
                response: 'Antwort',
                origin: turn_recorder::ORIGIN_GENERAL,
                topic: 'Statistics basics',
            );
        }

        return ['course' => $course, 'teacher' => $teacher, 'context' => $context];
    }

    /**
     * The analysis prompt carries the hotspots and sampled questions, the
     * answer comes back rendered, and nothing new is persisted in Moodle.
     */
    public function test_analyse_builds_prompt_and_persists_nothing(): void {
        global $DB;
        $this->resetAfterTest();
        $this->configure_mcp_service();
        ['course' => $course, 'teacher' => $teacher, 'context' => $context] = $this->seed_course();

        $turnsbefore = $DB->count_records(turn_recorder::TABLE);

        $adapter = new fake_adapter(new chat_response(
            "## Analyse\n\nDie Lernenden verwechseln Varianz und Standardabweichung.",
            'server-conv-999'
        ));

        $result = copilot_service::analyse((int) $course->id, (int) $teacher->id, $context, $adapter);

        // The prompt carries the hotspot label and a sampled question, framed as
        // untrusted input like any other turn.
        $request = $adapter->lastrequest;
        $this->assertStringContainsString('Statistics basics', $request->usermessage);
        $this->assertStringContainsString('What is a normal distribution?', $request->usermessage);
        // A grounded turn in the course scope, on the teacher's own behalf.
        $this->assertTrue($request->is_grounded());
        $this->assertSame((int) $course->id, $request->courseid);
        $this->assertSame((int) $teacher->id, $request->userid);

        // Markdown was rendered to HTML.
        $this->assertFalse($result['iserror']);
        $this->assertStringContainsString('<h2', $result['analysishtml']);
        $this->assertStringContainsString('Standardabweichung', $result['analysishtml']);

        // No new question-log rows, and no conversation: an analysis run must
        // not turn up in the teacher's list of conversations.
        $this->assertSame($turnsbefore, $DB->count_records(turn_recorder::TABLE));
        $this->assertSame(0, $DB->count_records(
            \local_elediaai_chatengine\local\thread_store::THREAD_TABLE,
            ['placement' => 'block_elediaai_tutor']
        ));
    }

    /**
     * Without a consent record the analysis never leaves Moodle.
     */
    public function test_analyse_requires_consent(): void {
        $this->resetAfterTest();
        $this->configure_mcp_service();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);
        $context = \core\context\course::instance($course->id);

        $adapter = new fake_adapter(new chat_response('never sent'));

        try {
            copilot_service::analyse((int) $course->id, (int) $teacher->id, $context, $adapter);
            $this->fail('Expected the consent gate to throw.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_consentrequired', $e->errorcode);
        }
        $this->assertSame(0, $adapter->calls);
    }

    /**
     * A course without logged questions yields the no-data error before any
     * RAG contact.
     */
    public function test_analyse_requires_data(): void {
        $this->resetAfterTest();
        $this->configure_mcp_service();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);
        $context = \core\context\course::instance($course->id);
        consent::give((int) $teacher->id, $context);

        $adapter = new fake_adapter(new chat_response('never sent'));

        try {
            copilot_service::analyse((int) $course->id, (int) $teacher->id, $context, $adapter);
            $this->fail('Expected the no-data gate to throw.');
        } catch (\moodle_exception $e) {
            $this->assertSame('copilot_nodata', $e->errorcode);
        }
        $this->assertSame(0, $adapter->calls);
    }
}

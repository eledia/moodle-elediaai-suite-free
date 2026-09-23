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
 * Quota-aware AI execution tests.
 *
 * @package    local_elediaai_core
 * @category   test
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core;

use advanced_testcase;
use core_ai\aiactions\generate_text;
use core_ai\aiactions\responses\response_generate_text;
use core_ai\manager;
use local_elediaai_core\local\quota_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Verifies dispatch and settlement outcomes.
 *
 * @covers \local_elediaai_core\quota_aware_ai_manager
 */
final class quota_aware_ai_manager_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('quota_completion_buffer', 0, 'local_elediaai_core');
        set_config('quota_student_day', 100, 'local_elediaai_core');
    }

    public function test_success_commits_measured_tokens(): void {
        [$user, $action] = $this->action();
        $response = new response_generate_text(true);
        $response->set_response_data([
            'generatedcontent' => 'Answer',
            'prompttokens' => 7,
            'completiontokens' => 3,
        ]);
        $manager = $this->createMock(manager::class);
        $manager->expects($this->once())->method('process_action')->with($action)->willReturn($response);

        $this->assertSame(
            $response,
            quota_aware_ai_manager::process_action($action, 'local_elediaai_strategy', 20, [], $manager),
        );
        $this->assertSame(10, $this->used_tokens($user->id));
        $this->assertSame(0, $this->reserved_tokens($user->id));
    }

    /**
     * Der Turn merkt sich die Registerzeile, die sein Aufruf erzeugt hat.
     *
     * Ohne diesen Verweis standen Anfragen der Suite in beiden Listen: einmal
     * im eigenen Protokoll und einmal in Moodles Register, das keine
     * Komponente fuehrt und sie deshalb nicht auslassen konnte.
     */
    public function test_the_turn_remembers_its_row_in_moodles_register(): void {
        global $DB;

        if (!$DB->get_manager()->table_exists('ai_action_register')) {
            $this->markTestSkipped('Ohne core_ai-Register gibt es nichts zu merken.');
        }

        [$user, $action] = $this->action();
        $response = new response_generate_text(true);
        $response->set_response_data([
            'generatedcontent' => 'Answer',
            'prompttokens' => 7,
            'completiontokens' => 3,
        ]);

        // Der echte Kern schreibt die Zeile mitten in process_action(). Die
        // Attrappe tut dasselbe, damit der Griff danach geprueft wird und
        // nicht nur seine Abwesenheit.
        $manager = $this->createMock(manager::class);
        $manager->method('process_action')->willReturnCallback(
            function () use ($response, $user): response_generate_text {
                global $DB;
                $DB->insert_record('ai_action_register', (object) [
                    'actionname' => 'generate_text',
                    'actionid' => 0,
                    'success' => 1,
                    'userid' => (int) $user->id,
                    'contextid' => (int) \context_system::instance()->id,
                    'provider' => 'openai',
                    'timecreated' => time(),
                    'timecompleted' => time(),
                    'model' => 'gpt-4o-mini',
                ]);
                return $response;
            }
        );

        quota_aware_ai_manager::process_action($action, 'local_elediaai_strategy', 20, [], $manager);

        $turn = $DB->get_record_sql(
            'SELECT * FROM {local_elediaai_core_turn} ORDER BY id DESC',
            null,
            IGNORE_MULTIPLE
        );
        $erwartet = (int) $DB->get_field_sql('SELECT MAX(id) FROM {ai_action_register}');
        $this->assertSame($erwartet, (int) $turn->registerid);
    }

    /**
     * Schreibt der Kern keine Zeile, bleibt der Verweis leer.
     */
    public function test_without_a_register_row_the_turn_points_nowhere(): void {
        global $DB;

        [, $action] = $this->action();
        $response = new response_generate_text(true);
        $response->set_response_data(['generatedcontent' => 'Answer', 'prompttokens' => 7, 'completiontokens' => 3]);
        $manager = $this->createMock(manager::class);
        $manager->method('process_action')->willReturn($response);

        quota_aware_ai_manager::process_action($action, 'local_elediaai_strategy', 20, [], $manager);

        $turn = $DB->get_record_sql(
            'SELECT * FROM {local_elediaai_core_turn} ORDER BY id DESC',
            null,
            IGNORE_MULTIPLE
        );
        $this->assertNull($turn->registerid);
    }

    /**
     * Eine Antwort, ein Herkunftsnachweis.
     *
     * Vorher schrieben mehrere Stellen: der Anbieter fuer alles, was er
     * erzeugt, und das Plugin noch einmal fuer sich. Eine ueber
     * aiprovider_eledia bewertete aitext-Frage sammelte so zwei Belege fuer
     * denselben Text.
     */
    public function test_one_answer_leaves_exactly_one_receipt(): void {
        global $DB;

        if (!class_exists('\\local_aitransparency\\provenance')) {
            $this->markTestSkipped('Ohne local_aitransparency gibt es keinen Nachweis.');
        }

        [, $action] = $this->action();
        $response = new response_generate_text(true);
        $response->set_response_data([
            'generatedcontent' => 'Eine erzeugte Antwort.',
            'prompttokens' => 7,
            'completiontokens' => 3,
            'model' => 'gpt-4o-mini',
        ]);
        $manager = $this->createMock(manager::class);
        $manager->method('process_action')->willReturn($response);

        $vorher = $DB->count_records('local_aitransparency_rec');
        $uuid = null;
        quota_aware_ai_manager::process_action(
            $action,
            'local_elediaai_strategy',
            20,
            [],
            $manager,
            null,
            $uuid
        );

        $this->assertSame($vorher + 1, $DB->count_records('local_aitransparency_rec'));
        $this->assertNotNull($uuid);
        $record = $DB->get_record('local_aitransparency_rec', ['uuid' => $uuid]);
        $this->assertSame('local_elediaai_strategy', $record->component);
        $turn = $DB->get_record_sql(
            'SELECT * FROM {local_elediaai_core_turn} ORDER BY id DESC',
            null,
            IGNORE_MULTIPLE
        );
        $this->assertSame((int) $turn->id, (int) $record->turnid);
    }

    /**
     * Der Anbieter erfaehrt, dass die Suite gerade ruft -- und nur solange.
     *
     * Ein Zaehler, kein Schalter: eine Aktion, die eine zweite ausloest, darf
     * die Marke beim Verlassen nicht fuer beide loeschen.
     */
    public function test_the_provider_is_told_only_while_the_suite_calls(): void {
        [, $action] = $this->action();
        $response = new response_generate_text(true);
        $response->set_response_data(['generatedcontent' => 'Antwort', 'prompttokens' => 1, 'completiontokens' => 1]);

        $this->assertFalse(quota_aware_ai_manager::in_suite_call());

        $drinnen = null;
        $manager = $this->createMock(manager::class);
        $manager->method('process_action')->willReturnCallback(
            function () use ($response, &$drinnen): response_generate_text {
                $drinnen = quota_aware_ai_manager::in_suite_call();
                return $response;
            }
        );

        quota_aware_ai_manager::process_action($action, 'local_elediaai_strategy', 20, [], $manager);

        $this->assertTrue($drinnen, 'Waehrend des Aufrufs weiss der Anbieter nichts davon.');
        $this->assertFalse(quota_aware_ai_manager::in_suite_call(), 'Die Marke bleibt haengen.');
    }

    /**
     * Auch ein Fehlschlag raeumt die Marke wieder ab.
     */
    public function test_the_mark_is_cleared_when_the_call_throws(): void {
        [, $action] = $this->action();
        $manager = $this->createMock(manager::class);
        $manager->method('process_action')->willThrowException(new \RuntimeException('kaputt'));

        try {
            quota_aware_ai_manager::process_action($action, 'local_elediaai_strategy', 20, [], $manager);
            $this->fail('Die Ausnahme haette durchkommen muessen.');
        } catch (\RuntimeException $e) {
            unset($e);
        }

        $this->assertFalse(quota_aware_ai_manager::in_suite_call());
    }

    public function test_quota_exhaustion_prevents_dispatch(): void {
        [$user, $action] = $this->action();
        quota_manager::record_usage($user->id, 90, 0, 'phpunit');
        $manager = $this->createMock(manager::class);
        $manager->expects($this->never())->method('process_action');

        $this->expectException(\moodle_exception::class);
        quota_aware_ai_manager::process_action($action, 'local_elediaai_strategy', 11, [], $manager);
    }

    public function test_failed_response_releases_reservation(): void {
        [$user, $action] = $this->action();
        $response = new response_generate_text(false, 500, 'provider_error', 'Unavailable');
        $manager = $this->createMock(manager::class);
        $manager->method('process_action')->willReturn($response);

        $this->assertSame(
            $response,
            quota_aware_ai_manager::process_action($action, 'local_elediaai_strategy', 60, [], $manager),
        );
        $this->assertSame(0, $this->used_tokens($user->id));
        $this->assertSame(0, $this->reserved_tokens($user->id));
    }

    public function test_exception_releases_reservation(): void {
        [$user, $action] = $this->action();
        $manager = $this->createMock(manager::class);
        $manager->method('process_action')->willThrowException(new \RuntimeException('Dispatch failed'));

        try {
            quota_aware_ai_manager::process_action($action, 'local_elediaai_strategy', 60, [], $manager);
            $this->fail('The dispatch exception should be propagated.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Dispatch failed', $exception->getMessage());
        }
        $this->assertSame(0, $this->used_tokens($user->id));
        $this->assertSame(0, $this->reserved_tokens($user->id));
    }

    public function test_callback_uses_same_quota_lifecycle(): void {
        $user = $this->getDataGenerator()->create_user();

        $result = quota_aware_ai_manager::process_callback(
            $user->id,
            'mod_elli',
            '12345678',
            static fn(): string => '1234',
        );

        $this->assertSame('1234', $result);
        $this->assertSame(3, $this->used_tokens($user->id));
        $this->assertSame(0, $this->reserved_tokens($user->id));
    }

    public function test_callback_commits_named_measured_usage(): void {
        $user = $this->getDataGenerator()->create_user();
        $callbackresult = [
            'answer' => 'Measured answer',
            'prompttokens' => 7,
            'completiontokens' => 3,
        ];

        $result = quota_aware_ai_manager::process_callback(
            $user->id,
            'block_elediaai_tutor',
            '12345678',
            static fn(): array => $callbackresult,
            static fn(array $response): array => [
                'prompttokens' => $response['prompttokens'],
                'completiontokens' => $response['completiontokens'],
            ],
        );

        $this->assertSame($callbackresult, $result);
        $this->assertSame(10, $this->used_tokens($user->id));
        $this->assertSame(0, $this->reserved_tokens($user->id));
    }

    public function test_callback_positional_usage_falls_back_per_field(): void {
        $user = $this->getDataGenerator()->create_user();

        quota_aware_ai_manager::process_callback(
            $user->id,
            'block_elediaai_tutor',
            '12345678',
            static fn(): string => '1234',
            static fn(string $response): array => [null, 4],
        );

        $this->assertSame(6, $this->used_tokens($user->id));
        $this->assertSame(0, $this->reserved_tokens($user->id));
    }

    public function test_callback_resolver_exception_records_estimates(): void {
        $user = $this->getDataGenerator()->create_user();

        $result = quota_aware_ai_manager::process_callback(
            $user->id,
            'block_elediaai_tutor',
            '12345678',
            static fn(): string => '1234',
            static function (): array {
                throw new \RuntimeException('Invalid usage');
            },
        );

        $this->assertSame('1234', $result);
        $this->assertSame(3, $this->used_tokens($user->id));
        $this->assertDebuggingCalled(
            'local_elediaai_core usage resolver failed; estimated usage was recorded: Invalid usage',
            DEBUG_DEVELOPER,
        );
        $this->assertSame(0, $this->reserved_tokens($user->id));
    }

    /**
     * Create a user and configured text action.
     *
     * @return array{0: \stdClass, 1: generate_text}
     */
    private function action(): array {
        $user = $this->getDataGenerator()->create_user();
        return [$user, new generate_text(\context_system::instance()->id, $user->id, 'Prompt')];
    }

    private function used_tokens(int $userid): int {
        return quota_manager::used_tokens(
            $userid,
            quota_manager::BUCKET_STUDENT,
            quota_manager::WINDOW_DAY,
        );
    }

    private function reserved_tokens(int $userid): int {
        global $DB;
        return (int) $DB->get_field(quota_manager::TABLE, 'reservedtokens', [
            'userid' => $userid,
            'rolebucket' => quota_manager::BUCKET_STUDENT,
            'windowtype' => quota_manager::WINDOW_DAY,
        ]);
    }
}

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
use block_elediaai_tutor\chatengine\placement;
use block_elediaai_tutor\external\send_message;
use block_elediaai_tutor\local\consent;
use local_elediaai_chatengine\backend_resolver;
use local_elediaai_chatengine\local\thread_store;
use local_elediaai_chatengine\tests\fixtures\fake_adapter;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../../local/elediaai_chatengine/tests/fixtures/fake_adapter.php');

/**
 * What a teacher configured on an instance actually reaching the turn.
 *
 * A conversation belongs to the course — `view.php?courseid=` answers for any
 * course, with no block instance to key a thread on — while the configuration
 * belongs to the block instance. One instance id cannot be both, so the turn
 * names its surface beside it. Before that, `helper::block_config()` was only
 * ever handed a course or system context and answered with an empty object:
 * persona, mode, budget and the answer-style lock were configured and then
 * silently ignored on every turn.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\block_elediaai_tutor\external\send_message::class)]
#[CoversClass(\block_elediaai_tutor\chatengine\placement::class)]
final class surface_test extends \advanced_testcase {
    /** @var fake_adapter The backend standing in for a real one. */
    private fake_adapter $adapter;

    #[\Override]
    protected function tearDown(): void {
        backend_resolver::override_for_testing(null);
        placement::forget_surface();
        parent::tearDown();
    }

    /**
     * A course with a configured tutor block and a consenting student.
     *
     * Model-only mode is permitted so the turn does not depend on a course
     * having indexed material; what is under test is which configuration the
     * turn runs with, not where its answer came from.
     *
     * @param array $config The block instance configuration to store.
     * @return array{courseid: int, contextid: int, userid: int}
     */
    private function setup_instance(array $config = []): array {
        global $DB;

        $this->resetAfterTest();
        set_config('allowllmonly', 1, 'block_elediaai_tutor');

        $this->adapter = new fake_adapter();
        backend_resolver::override_for_testing($this->adapter);

        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \core\context\course::instance($course->id);
        $block = $this->getDataGenerator()->create_block('elediaai_tutor', [
            'parentcontextid' => $coursecontext->id,
        ]);

        if ($config !== []) {
            // Written the way the block itself stores it, so the test proves the
            // real read path rather than a convenience one.
            $DB->set_field(
                'block_instances',
                'configdata',
                base64_encode(serialize((object) $config)),
                ['id' => $block->id]
            );
        }

        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        consent::give((int) $student->id, $coursecontext);
        $this->setUser($student);

        return [
            'courseid' => (int) $course->id,
            'contextid' => (int) \core\context\block::instance($block->id)->id,
            'userid' => (int) $student->id,
        ];
    }

    /**
     * The instance's default answer style decides the turn.
     *
     * @return void
     */
    public function test_the_instance_default_style_reaches_the_backend(): void {
        $scope = $this->setup_instance(['answerstyle' => 'quiz']);

        send_message::execute($scope['contextid'], 'Was ist Photosynthese?', $scope['courseid']);

        $this->assertSame('quiz', $this->adapter->lastrequest->option('answerstyle'));
    }

    /**
     * A locked instance keeps its style against a request that asks otherwise.
     *
     * The gate this whole change exists for: the lock lives in the instance
     * configuration, and until the surface travelled with the turn there was no
     * configuration to read it from.
     *
     * @return void
     */
    public function test_a_locked_style_survives_a_client_that_asks_otherwise(): void {
        $scope = $this->setup_instance([
            'answerstyle' => 'hint',
            'allowstylechange' => 0,
        ]);

        send_message::execute($scope['contextid'], 'Frage', $scope['courseid'], answerstyle: 'quiz');

        $this->assertSame('hint', $this->adapter->lastrequest->option('answerstyle'));
    }

    /**
     * An instance that permits the choice honours it.
     *
     * @return void
     */
    public function test_an_open_instance_honours_the_chosen_style(): void {
        $scope = $this->setup_instance([
            'answerstyle' => 'hint',
            'allowstylechange' => 1,
        ]);

        send_message::execute($scope['contextid'], 'Frage', $scope['courseid'], answerstyle: 'quiz');

        $this->assertSame('quiz', $this->adapter->lastrequest->option('answerstyle'));
    }

    /**
     * The instance's own daily budget is applied, not just the site's.
     *
     * @return void
     */
    public function test_the_instance_budget_is_enforced(): void {
        $scope = $this->setup_instance(['dailylimit' => 1]);

        send_message::execute($scope['contextid'], 'erste Frage', $scope['courseid']);

        $this->expectException(\moodle_exception::class);
        send_message::execute($scope['contextid'], 'zweite Frage', $scope['courseid']);
    }

    /**
     * Two turns in a row stay in one conversation.
     *
     * The counterpart to the test below, and the reason the wish has to be
     * stated: a turn that names no conversation carries on where the learner
     * left off.
     *
     * @return void
     */
    public function test_consecutive_turns_share_one_conversation(): void {
        $scope = $this->setup_instance([]);

        $first = send_message::execute($scope['contextid'], 'erste', $scope['courseid']);
        $second = send_message::execute($scope['contextid'], 'zweite', $scope['courseid']);

        $this->assertSame($first['threadid'], $second['threadid']);
    }

    /**
     * The plus opens a second conversation and keeps the first.
     *
     * The regression: the button dropped the panel's pointer and sent no
     * conversation id, which the engine reads as "carry on". The learner got
     * an empty log while the backend was handed the whole previous
     * conversation, and the history list never gained an entry.
     *
     * @return void
     */
    public function test_a_new_conversation_is_opened_when_the_turn_asks_for_one(): void {
        $scope = $this->setup_instance([]);

        $first = send_message::execute($scope['contextid'], 'erste', $scope['courseid']);
        $second = send_message::execute($scope['contextid'], 'zweite', $scope['courseid'], newthread: true);

        $this->assertNotEquals($first['threadid'], $second['threadid']);
        $this->assertSame([], $this->adapter->lastrequest->history);
        $this->assertCount(
            2,
            thread_store::threads_for(placement::component(), $scope['courseid'], $scope['userid'])
        );
    }

    /**
     * The instance's model-only setting decides the mode.
     *
     * @return void
     */
    public function test_the_instance_mode_reaches_the_backend(): void {
        $scope = $this->setup_instance(['ragmode' => \block_elediaai_tutor\local\chat_mode::MODE_LLMONLY]);

        send_message::execute($scope['contextid'], 'Frage', $scope['courseid']);

        $this->assertFalse($this->adapter->lastrequest->is_grounded());
    }

    /**
     * A context that does not belong to the named course is refused.
     *
     * The surface is client-named, so it is proved rather than believed: a
     * block from somebody else's course must not configure this turn.
     *
     * @return void
     */
    public function test_a_context_from_another_course_is_refused(): void {
        $scope = $this->setup_instance(['answerstyle' => 'quiz']);

        // Enrolled here too, and this course has its own tutor block: everything
        // except the pairing is in order, so a refusal can only come from the
        // context not belonging to the named course.
        $other = $this->getDataGenerator()->create_course();
        $othercontext = \core\context\course::instance($other->id);
        $this->getDataGenerator()->create_block('elediaai_tutor', [
            'parentcontextid' => $othercontext->id,
        ]);
        $this->getDataGenerator()->enrol_user($scope['userid'], (int) $other->id, 'student');

        try {
            send_message::execute($scope['contextid'], 'Frage', (int) $other->id);
            $this->fail('A block from another course should not configure this turn.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_invalid_context', $e->errorcode);
        }
    }

    /**
     * Without a surface the site defaults still apply.
     *
     * The shared endpoint keeps working for the tutor; it simply cannot resolve
     * an instance, which is exactly what it did before.
     *
     * @return void
     */
    public function test_the_shared_endpoint_still_answers_with_site_defaults(): void {
        $scope = $this->setup_instance(['answerstyle' => 'quiz']);

        \local_elediaai_chatengine\external\send_message::execute(
            'block_elediaai_tutor',
            $scope['courseid'],
            'Frage'
        );

        $this->assertSame('explain', $this->adapter->lastrequest->option('answerstyle'));
    }
}

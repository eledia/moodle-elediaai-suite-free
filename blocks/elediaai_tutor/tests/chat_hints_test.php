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
use local_elediaai_chatengine\backend_resolver;
use local_elediaai_chatengine\external\send_message;
use local_elediaai_chatengine\tests\fixtures\fake_adapter;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../../local/elediaai_chatengine/tests/fixtures/fake_adapter.php');

/**
 * A tutor turn, from the panel's arguments to the backend's.
 *
 * The tutor is the one placement that adds hints of its own: the answer style
 * a learner picked from the chips, and the routing intent a starter question
 * carries. Both are added in the browser and read in the engine, so neither
 * plugin's suite saw the two halves together - and they had come apart, with
 * the panel sending two arguments the shared endpoint refused to accept.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\block_elediaai_tutor\chatengine\placement::class)]
final class chat_hints_test extends \advanced_testcase {
    /** @var fake_adapter The backend standing in for a real one. */
    private fake_adapter $adapter;

    #[\Override]
    protected function tearDown(): void {
        backend_resolver::override_for_testing(null);
        parent::tearDown();
    }

    /**
     * A course with the tutor in it and a consenting student logged in.
     *
     * Model-only mode is permitted so the turn does not depend on a course
     * having indexed material; what is under test is what the turn carries,
     * not where its answer came from.
     *
     * @return int The course id, which is also the placement's instance id.
     */
    private function setup_course(): int {
        $this->resetAfterTest();
        set_config('allowllmonly', 1, 'block_elediaai_tutor');

        $this->adapter = new fake_adapter();
        backend_resolver::override_for_testing($this->adapter);

        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \core\context\course::instance($course->id);
        $this->getDataGenerator()->create_block('elediaai_tutor', [
            'parentcontextid' => $coursecontext->id,
        ]);

        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        consent::give((int) $student->id, $coursecontext);
        $this->setUser($student);

        return (int) $course->id;
    }

    /**
     * The style chip and the starter's intent reach the backend.
     *
     * @return void
     */
    public function test_a_tutor_turn_carries_its_style_and_intent(): void {
        $courseid = $this->setup_course();

        $result = send_message::execute(
            'block_elediaai_tutor',
            $courseid,
            'Wie rechne ich das?',
            answerstyle: 'hint',
            intent: 'action'
        );

        $this->assertNotSame('', $result['answerhtml']);
        $this->assertSame('hint', $this->adapter->lastrequest->option('answerstyle'));
        $this->assertSame('action', $this->adapter->lastrequest->option('intent'));
    }

    /**
     * A turn that asks for nothing gets the instance default and no intent.
     *
     * @return void
     */
    public function test_a_turn_without_hints_gets_the_instance_default(): void {
        $courseid = $this->setup_course();

        send_message::execute('block_elediaai_tutor', $courseid, 'Was ist Photosynthese?');

        $request = $this->adapter->lastrequest;
        $this->assertSame('explain', $request->option('answerstyle'));
        $this->assertNull($request->option('intent'));
    }

    /**
     * A style outside the vocabulary falls back to the instance default.
     *
     * @return void
     */
    public function test_an_unknown_style_falls_back_to_the_default(): void {
        $courseid = $this->setup_course();

        send_message::execute('block_elediaai_tutor', $courseid, 'Frage', answerstyle: 'root');

        $this->assertSame('explain', $this->adapter->lastrequest->option('answerstyle'));
    }
}

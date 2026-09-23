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

use block_elediaai_tutor\chatengine\placement;
use block_elediaai_tutor\local\consent;

/**
 * The gates a chat turn passes before anything leaves this site.
 *
 * These used to sit in the block's own chat endpoints. The endpoints are the
 * engine's now, so the gates live in the placement — and that is where they
 * have to keep being proved, because a placement is the only thing standing
 * between a shared endpoint and a course somebody may not read.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \block_elediaai_tutor\chatengine\placement
 */
final class placement_test extends \advanced_testcase {
    /**
     * The tutor asks for the base prompt it has always been answered inside.
     *
     * Also the value a server assumes when the argument is absent, so the
     * block reaches an older server exactly as it did before.
     *
     * @return void
     */
    public function test_the_tutor_names_its_base_prompt(): void {
        $this->resetAfterTest();

        $this->assertSame('tutor', (new placement())->system_prompt_id());
    }

    /**
     * The course scope resolves to the course, the site scope to the system.
     *
     * @return void
     */
    public function test_scope_resolves_to_its_context(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $placement = new placement();

        $this->assertSame(
            (int) \core\context\course::instance($course->id)->id,
            (int) $placement->context((int) $course->id)->id
        );
        $this->assertSame((int) \context_system::instance()->id, (int) $placement->context(0)->id);
        $this->assertSame((int) $course->id, $placement->courseid((int) $course->id));
        $this->assertSame(0, $placement->courseid(0));
    }

    /**
     * An enrolled student with the capability may chat in their course.
     *
     * @return void
     */
    public function test_enrolled_student_may_chat(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->create_course_tutor_block((int) $course->id);
        consent::give((int) $student->id, \core\context\course::instance($course->id));

        $this->setUser($student);
        $placement = new placement();

        $placement->require_access((int) $course->id, (int) $student->id);
        $this->assertTrue(true, 'Access was granted without an exception.');
    }

    /**
     * Someone not enrolled in the course cannot chat about it.
     *
     * @return void
     */
    public function test_unenrolled_user_is_refused(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $outsider = $this->getDataGenerator()->create_user();
        $this->create_course_tutor_block((int) $course->id);
        consent::give((int) $outsider->id, \context_system::instance());

        $this->setUser($outsider);
        $placement = new placement();

        $this->expectException(\moodle_exception::class);
        $placement->require_access((int) $course->id, (int) $outsider->id);
    }

    /**
     * A prohibition in the course is enforced.
     *
     * @return void
     */
    public function test_course_capability_prohibit_is_enforced(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->create_course_tutor_block((int) $course->id);
        consent::give((int) $student->id, \core\context\course::instance($course->id));

        $studentroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        assign_capability(
            'block/elediaai_tutor:use',
            CAP_PROHIBIT,
            $studentroleid,
            \core\context\course::instance($course->id)->id,
            true
        );
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($student);
        $placement = new placement();

        $this->expectException(\moodle_exception::class);
        $placement->require_access((int) $course->id, (int) $student->id);
    }

    /**
     * A scope the site has switched off is refused before anything else.
     *
     * @return void
     */
    public function test_disabled_scopes_are_refused(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enableglobalchat', 0, 'block_elediaai_tutor');
        $placement = new placement();

        $this->expectException(\moodle_exception::class);
        $placement->require_access(0, (int) $this->getDataGenerator()->create_user()->id);
    }

    /**
     * Nothing is sent before the privacy guidelines were acknowledged.
     *
     * The gate is on sending, not on access: the panel has to be able to load
     * an empty transcript in order to show the consent form at all.
     *
     * @return void
     */
    public function test_consent_gates_sending_not_looking(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->create_course_tutor_block((int) $course->id);

        $this->setUser($student);
        $placement = new placement();

        // Looking is fine without consent.
        $placement->require_access((int) $course->id, (int) $student->id);

        // Sending is not.
        try {
            $placement->require_send((int) $course->id, (int) $student->id);
            $this->fail('Sending without consent should have been refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_consentrequired', $e->errorcode);
        }

        consent::give((int) $student->id, \core\context\course::instance($course->id));
        $placement->require_send((int) $course->id, (int) $student->id);
    }

    /**
     * A locked instance decides the answer style, whatever the client asked.
     *
     * @return void
     */
    public function test_locked_instance_decides_the_answer_style(): void {
        $locked = (object) ['answerstyle' => 'hint', 'allowstylechange' => 0];
        $open = (object) ['answerstyle' => 'hint', 'allowstylechange' => 1];

        $this->assertSame('hint', placement::effective_answer_style($locked, 'quiz'));
        $this->assertSame('quiz', placement::effective_answer_style($open, 'quiz'));
        // An unknown style falls back to the instance default rather than through.
        $this->assertSame('hint', placement::effective_answer_style($open, 'root'));
        $this->assertSame('explain', placement::effective_answer_style((object) [], ''));
    }

    /**
     * Create a tutor block in a course and return its context.
     *
     * @param int $courseid Course id.
     * @return \core\context\block The block context.
     */
    private function create_course_tutor_block(int $courseid): \core\context\block {
        $block = $this->getDataGenerator()->create_block('elediaai_tutor', [
            'parentcontextid' => \core\context\course::instance($courseid)->id,
        ]);

        return \core\context\block::instance($block->id);
    }
}

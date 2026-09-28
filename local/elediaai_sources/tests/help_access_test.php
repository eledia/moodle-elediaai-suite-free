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

namespace local_elediaai_sources;

/**
 * Tests for who may read the help page (G-05).
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_sources\help_access
 */
final class help_access_test extends \advanced_testcase {
    /**
     * An editing teacher reads the help in their course.
     */
    public function test_teacher_reads_help_in_course(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $context = help_access::require_context($course);

        $this->assertSame(\core\context\course::instance((int) $course->id)->id, $context->id);
    }

    /**
     * A student does not: they do not operate the activity selection.
     */
    public function test_student_is_refused_in_course(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        help_access::require_context($course);
    }

    /**
     * Without a course the page stays an administrator page.
     */
    public function test_without_course_requires_site_config(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $this->setAdminUser();
        $this->assertSame(\core\context\system::instance()->id, help_access::require_context(null)->id);

        $this->setUser($teacher);
        $this->expectException(\required_capability_exception::class);
        help_access::require_context(null);
    }
}

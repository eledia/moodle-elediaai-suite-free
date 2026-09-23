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

namespace local_literag;

use local_literag\local\permission_filter;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Security tests: chunks must never leak content the user cannot see.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(permission_filter::class)]
final class permission_filter_test extends \advanced_testcase {
    /**
     * Make a fake chunk record for a course module.
     *
     * @param int $courseid
     * @param int $cmid
     * @return \stdClass
     */
    private function chunk($courseid, $cmid): \stdClass {
        return (object) ['courseid' => (int) $courseid, 'cmid' => (int) $cmid, 'chunktext' => 'x', 'sourcetitle' => 't'];
    }

    /**
     * A hidden activity's chunk is withheld from a student but visible to a teacher.
     */
    public function test_hidden_activity_is_withheld_from_student(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();

        $course = $gen->create_course();
        $visible = $gen->create_module('page', ['course' => $course->id]);
        $hidden = $gen->create_module('page', ['course' => $course->id, 'visible' => 0]);

        $student = $gen->create_and_enrol($course, 'student');
        $teacher = $gen->create_and_enrol($course, 'editingteacher');

        $chunks = [
            $this->chunk($course->id, $visible->cmid),
            $this->chunk($course->id, $hidden->cmid),
        ];

        $studentvisible = (new permission_filter($student))->filter($chunks);
        $this->assertCount(1, $studentvisible);
        $this->assertSame($visible->cmid, (int) $studentvisible[0]->cmid);

        $teachervisible = (new permission_filter($teacher))->filter($chunks);
        $this->assertCount(2, $teachervisible);
    }

    /**
     * A user not enrolled in the course sees nothing from it.
     */
    public function test_non_enrolled_user_sees_nothing(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();

        $course = $gen->create_course();
        $page = $gen->create_module('page', ['course' => $course->id]);
        $outsider = $gen->create_user();

        $filtered = (new permission_filter($outsider))->filter([$this->chunk($course->id, $page->cmid)]);
        $this->assertCount(0, $filtered);
    }

    /**
     * A chunk pointing at a deleted/unknown course module is dropped.
     */
    public function test_unknown_cmid_is_dropped(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();

        $course = $gen->create_course();
        $student = $gen->create_and_enrol($course, 'student');

        $filtered = (new permission_filter($student))->filter([$this->chunk($course->id, 99999)]);
        $this->assertCount(0, $filtered);
    }
}

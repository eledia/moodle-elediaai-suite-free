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
use block_elediaai_tutor\local\user_audience;

/**
 * Tests for the coarse audience resolution behind the dashboard starters.
 *
 * @package     block_elediaai_tutor
 * @author      Johannes Moskaliuk
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\block_elediaai_tutor\local\user_audience::class)]
final class user_audience_test extends \advanced_testcase {
    /**
     * A user with no role assignments is a student.
     */
    public function test_plain_user_is_student(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->assertSame(user_audience::STUDENT, user_audience::resolve((int) $user->id));
    }

    /**
     * A student enrolment stays a student (the student archetype is neither
     * teacher nor manager).
     */
    public function test_student_enrolment_is_student(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->assertSame(user_audience::STUDENT, user_audience::resolve((int) $user->id));
    }

    /**
     * Editing and non-editing teacher enrolments both resolve to teacher.
     */
    public function test_teacher_enrolments_are_teacher(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $editing = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($editing->id, $course->id, 'editingteacher');
        $this->assertSame(user_audience::TEACHER, user_audience::resolve((int) $editing->id));

        $nonediting = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($nonediting->id, $course->id, 'teacher');
        $this->assertSame(user_audience::TEACHER, user_audience::resolve((int) $nonediting->id));
    }

    /**
     * A manager-archetype role assignment (here: at category level) wins over
     * a teacher enrolment.
     */
    public function test_manager_assignment_is_manager(): void {
        $this->resetAfterTest();

        $category = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');

        $managerroleid = $this->getDataGenerator()->create_role(['archetype' => 'manager']);
        role_assign($managerroleid, $user->id, \core\context\coursecat::instance($category->id)->id);

        $this->assertSame(user_audience::MANAGER, user_audience::resolve((int) $user->id));
    }

    /**
     * A course-creator assignment also counts as manager.
     */
    public function test_coursecreator_assignment_is_manager(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $creatorroleid = $this->getDataGenerator()->create_role(['archetype' => 'coursecreator']);
        role_assign($creatorroleid, $user->id, \core\context\system::instance()->id);

        $this->assertSame(user_audience::MANAGER, user_audience::resolve((int) $user->id));
    }

    /**
     * Site admins are managers.
     */
    public function test_siteadmin_is_manager(): void {
        $this->resetAfterTest();
        $admin = get_admin();
        $this->assertSame(user_audience::MANAGER, user_audience::resolve((int) $admin->id));
    }

    /**
     * The result is cached: a role change surfaces only after a cache purge.
     */
    public function test_result_is_cached_until_purge(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        $this->assertSame(user_audience::STUDENT, user_audience::resolve((int) $user->id));

        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');

        // Still the cached value...
        $this->assertSame(user_audience::STUDENT, user_audience::resolve((int) $user->id));

        // ...until the cache is purged.
        \cache::make('block_elediaai_tutor', 'audience')->purge();
        $this->assertSame(user_audience::TEACHER, user_audience::resolve((int) $user->id));
    }
}

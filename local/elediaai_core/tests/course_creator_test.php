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

namespace local_elediaai_core;

use local_elediaai_core\local\course_creator;

/**
 * Category choice and creator enrolment around create_course() (#33, #34).
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_core\local\course_creator
 */
final class course_creator_test extends \advanced_testcase {
    /**
     * A user with the course creator role in one category only.
     *
     * @param int ...$categoryids Categories to grant the role in.
     * @return \stdClass The user.
     */
    private function creator_in(int ...$categoryids): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'coursecreator']);
        foreach ($categoryids as $id) {
            role_assign($roleid, $user->id, \core\context\coursecat::instance($id)->id);
        }
        return $user;
    }

    /**
     * One allowed category is used without asking.
     *
     * @return void
     */
    public function test_the_only_allowed_category_is_used(): void {
        $this->resetAfterTest();
        $category = $this->getDataGenerator()->create_category(['name' => 'FFHS']);
        $user = $this->creator_in((int) $category->id);

        $this->assertSame([(int) $category->id => 'FFHS'], course_creator::creatable_categories((int) $user->id));
        $this->assertSame((int) $category->id, course_creator::resolve_category(null, (int) $user->id));
    }

    /**
     * Several allowed categories without the default: the caller has to ask.
     *
     * @return void
     */
    public function test_several_categories_need_a_choice(): void {
        $this->resetAfterTest();
        $a = $this->getDataGenerator()->create_category();
        $b = $this->getDataGenerator()->create_category();
        $user = $this->creator_in((int) $a->id, (int) $b->id);

        $this->assertNull(course_creator::resolve_category(null, (int) $user->id));
        $this->assertSame((int) $b->id, course_creator::resolve_category((int) $b->id, (int) $user->id));
    }

    /**
     * Someone who may create anywhere keeps the site default.
     *
     * @return void
     */
    public function test_an_admin_keeps_the_default_category(): void {
        $this->resetAfterTest();
        $this->getDataGenerator()->create_category();

        $default = (int) \core_course_category::get_default()->id;
        $this->assertSame($default, course_creator::resolve_category(null, (int) get_admin()->id));
    }

    /**
     * Nobody without the capability gets a category.
     *
     * @return void
     */
    public function test_no_permission_anywhere_is_refused(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $this->expectException(\moodle_exception::class);
        course_creator::resolve_category(null, (int) $user->id);
    }

    /**
     * A category the user may not create in is refused.
     *
     * @return void
     */
    public function test_a_foreign_category_is_refused(): void {
        $this->resetAfterTest();
        $own = $this->getDataGenerator()->create_category();
        $foreign = $this->getDataGenerator()->create_category();
        $user = $this->creator_in((int) $own->id);

        $this->expectException(\required_capability_exception::class);
        course_creator::resolve_category((int) $foreign->id, (int) $user->id);
    }

    /**
     * The creator is enrolled and can then edit the course (#34).
     *
     * @return void
     */
    public function test_the_creator_is_enrolled(): void {
        global $CFG;
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/course/lib.php');
        $category = $this->getDataGenerator()->create_category();
        $user = $this->creator_in((int) $category->id);
        $course = create_course((object) [
            'fullname' => 'Testkurs Kursautor A',
            'shortname' => 'KA-A',
            'category' => $category->id,
        ]);
        $context = \core\context\course::instance((int) $course->id);
        $this->assertFalse(has_capability('moodle/course:update', $context, $user));

        $this->assertTrue(course_creator::enrol_creator($course, (int) $user->id));

        $this->assertTrue(is_enrolled($context, $user));
        $this->assertTrue(has_capability('moodle/course:update', $context, $user));
        // A second call changes nothing.
        $this->assertFalse(course_creator::enrol_creator($course, (int) $user->id));
    }
}

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

namespace local_aitransparency;

/**
 * The way back from the provenance check.
 *
 * @package    local_aitransparency
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_aitransparency\verify_back
 */
final class verify_back_test extends \advanced_testcase {
    public function test_the_page_one_came_from_wins(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        [$url, $label] = verify_back::resolve(\context_course::instance($course->id)->id, 'https://example.com/moodle/my/');

        $this->assertSame('https://example.com/moodle/my/', $url->out(false));
        $this->assertSame(get_string('verify_back', 'local_aitransparency'), $label);
    }

    public function test_a_referer_back_to_the_check_itself_is_ignored(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$url] = verify_back::resolve(null, 'https://example.com/local/aitransparency/verify.php?uuid=x');
        $this->assertStringContainsString('/my/', $url->out(false));
    }

    public function test_without_referer_it_names_the_course(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Statistik I']);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        [$url, $label] = verify_back::resolve(\context_course::instance($course->id)->id, '');

        $this->assertStringContainsString('/course/view.php?id=' . $course->id, $url->out(false));
        $this->assertStringContainsString('Statistik I', $label);
    }

    public function test_an_activity_is_named_by_its_name(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'name' => 'Lernziele']);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        [$url, $label] = verify_back::resolve(\context_module::instance($page->cmid)->id, '');

        $this->assertStringContainsString('/mod/page/view.php?id=' . $page->cmid, $url->out(false));
        $this->assertStringContainsString('Lernziele', $label);
    }

    public function test_a_course_one_cannot_see_is_not_named(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Kunde Fremd GmbH']);
        $this->setUser($this->getDataGenerator()->create_user());

        [$url, $label] = verify_back::resolve(\context_course::instance($course->id)->id, '');

        $this->assertStringContainsString('/my/', $url->out(false));
        $this->assertStringNotContainsString('Kunde Fremd', $label);
    }

    public function test_dashboard_for_site_level_content_and_missing_records(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $expected = get_string('verify_back_dashboard', 'local_aitransparency');
        $this->assertSame($expected, verify_back::resolve(\context_system::instance()->id, '')[1]);
        $this->assertSame($expected, verify_back::resolve(null, '')[1]);
        $this->assertSame($expected, verify_back::resolve(999999, '')[1]);
    }
}

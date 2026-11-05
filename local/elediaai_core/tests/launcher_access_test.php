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

namespace local_elediaai_core;

use advanced_testcase;
use core\hook\navigation\primary_extend;
use core\hook\output\before_standard_top_of_body_html_generation;
use local_elediaai_core\feature\descriptor;
use local_elediaai_core\feature\registry;
use local_elediaai_core\local\launcher_access;
use local_elediaai_core\output\plugin_page;
use local_elediaai_core\output\section_nav;

/**
 * Who gets the launcher button, the navigation entry and the overview.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_core\local\launcher_access
 * @covers     \local_elediaai_core\hook_callbacks
 */
final class launcher_access_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        registry::reset_cache();
        launcher_access::reset_cache();

        // One open feature, so "nothing to show" never hides the button.
        $open = new descriptor(
            id: 'openfixture',
            component: 'local_elediaai_core',
            name: 'Open fixture',
            description: 'A free feature that demands no capability',
            launchurl: null,
            icon: 'star',
            kind: descriptor::KIND_PAGE,
        );
        (new \ReflectionProperty(registry::class, 'descriptors'))->setValue(null, ['openfixture' => $open]);
    }

    /**
     * Enrol a new user in a new course with the given role.
     *
     * @param string $role Role short name.
     * @return array The user and the course.
     */
    private function user_with_course_role(string $role): array {
        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $course = $generator->create_course();
        $generator->enrol_user($user->id, $course->id, $role);
        return [$user, $course];
    }

    /**
     * The button markup the hook adds for the current user.
     *
     * @return string
     */
    private function button_html(): string {
        global $PAGE;
        $PAGE->set_url('/');
        $PAGE->set_pagelayout('standard');
        $hook = new before_standard_top_of_body_html_generation($PAGE->get_renderer('core'));
        hook_callbacks::inject_launcher($hook);
        return $hook->get_output();
    }

    /**
     * Whether the primary navigation gets the suite entry for the current user.
     *
     * @return bool
     */
    private function navigation_has_entry(): bool {
        global $PAGE;
        $PAGE->set_url('/');
        $primary = new \core\navigation\views\primary($PAGE);
        hook_callbacks::extend_primary_navigation(new primary_extend($primary));
        return $primary->get('aisuite') !== false;
    }

    /**
     * Teaching roles may use the launcher, learners may not.
     *
     * @dataProvider course_roles_provider
     * @param string $role Course role short name.
     * @param bool $expected Whether the role gets the launcher.
     */
    public function test_course_roles(string $role, bool $expected): void {
        [$user] = $this->user_with_course_role($role);
        $this->assertSame($expected, launcher_access::can_use((int) $user->id));
    }

    /**
     * Course roles and whether they get the launcher.
     *
     * @return array
     */
    public static function course_roles_provider(): array {
        return [
            'teacher' => ['editingteacher', true],
            'non-editing teacher' => ['teacher', true],
            'student' => ['student', false],
        ];
    }

    /**
     * A manager holds the role on the site, not in a course.
     */
    public function test_a_manager_in_the_system_context_may_use_it(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $managerid = $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerid, $user->id, \core\context\system::instance()->id);

        $this->assertTrue(launcher_access::can_use((int) $user->id));
    }

    /**
     * Someone with no role at all and nobody logged in get nothing.
     */
    public function test_a_plain_user_and_the_guest_may_not(): void {
        $plain = $this->getDataGenerator()->create_user();
        $this->assertFalse(launcher_access::can_use((int) $plain->id));
        $this->assertFalse(launcher_access::can_use(0));
    }

    /**
     * The check follows the capability, not the role name: a site that grants
     * it to students in a course gives them the launcher.
     */
    public function test_a_capability_granted_in_one_course_is_enough(): void {
        global $DB;
        [$user, $course] = $this->user_with_course_role('student');
        $this->assertFalse(launcher_access::can_use((int) $user->id));

        $studentid = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        assign_capability(
            launcher_access::CAPABILITY,
            CAP_ALLOW,
            $studentid,
            \core\context\course::instance($course->id)->id,
            true
        );
        launcher_access::reset_cache();

        $this->assertTrue(launcher_access::can_use((int) $user->id));
    }

    /**
     * Taking the right away from the teacher role takes the launcher away.
     */
    public function test_a_prohibited_capability_removes_the_launcher(): void {
        global $DB;
        [$user] = $this->user_with_course_role('editingteacher');
        $this->assertTrue(launcher_access::can_use((int) $user->id));

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability(
            launcher_access::CAPABILITY,
            CAP_PROHIBIT,
            $roleid,
            \core\context\system::instance()->id,
            true
        );
        accesslib_clear_all_caches_for_unit_testing();
        launcher_access::reset_cache();

        $this->assertFalse(launcher_access::can_use((int) $user->id));
    }

    /**
     * The administrator needs no role.
     */
    public function test_the_administrator_may_use_it(): void {
        $this->setAdminUser();
        $this->assertTrue(launcher_access::can_use());
    }

    /**
     * A teacher gets the button and the navigation entry.
     */
    public function test_a_teacher_sees_the_button_and_the_navigation_entry(): void {
        [$teacher] = $this->user_with_course_role('editingteacher');
        $this->setUser($teacher);

        $this->assertStringContainsString('lh-ai-launcher-host', $this->button_html());
        $this->assertTrue($this->navigation_has_entry());
    }

    /**
     * A learner gets neither: the button is not rendered at all.
     */
    public function test_a_student_sees_neither_the_button_nor_the_navigation_entry(): void {
        [$student] = $this->user_with_course_role('student');
        $this->setUser($student);

        $this->assertSame('', $this->button_html());
        $this->assertFalse($this->navigation_has_entry());
    }

    /**
     * The "back to the suite" link: teachers keep the overview, learners go to the dashboard.
     */
    public function test_the_home_link_leads_learners_to_the_dashboard(): void {
        $overview = (new \moodle_url('/local/elediaai_core/index.php'))->out(false);
        $dashboard = (new \moodle_url('/my/'))->out(false);

        [$teacher] = $this->user_with_course_role('editingteacher');
        $this->setUser($teacher);
        $this->assertSame([$overview, 'Suite'], launcher_access::home_link($overview, 'Suite'));

        launcher_access::reset_cache();
        [$student] = $this->user_with_course_role('student');
        $this->setUser($student);
        $this->assertSame([$dashboard, get_string('myhome')], launcher_access::home_link($overview, 'Suite'));
        // Another target is none of our business.
        $other = (new \moodle_url('/course/view.php', ['id' => 2]))->out(false);
        $this->assertSame([$other, 'Course'], launcher_access::home_link($other, 'Course'));
    }

    /**
     * The shared page header links a learner to the dashboard, not to the overview.
     */
    public function test_the_page_header_does_not_link_learners_to_the_overview(): void {
        $overview = (new \moodle_url('/local/elediaai_core/index.php'))->out(false);
        $header = ['name' => 'Suite', 'homeurl' => $overview];

        [$teacher] = $this->user_with_course_role('editingteacher');
        $this->setUser($teacher);
        $this->assertStringContainsString('local/elediaai_core/index.php', plugin_page::header_html($header));

        launcher_access::reset_cache();
        [$student] = $this->user_with_course_role('student');
        $this->setUser($student);
        $html = plugin_page::header_html($header);
        $this->assertStringNotContainsString('local/elediaai_core/index.php', $html);
        $this->assertStringContainsString('/my/', $html);
    }

    /**
     * The feature navigation has no entry for the overview when the user may not open it.
     */
    public function test_the_feature_navigation_hides_the_overview_entry_from_learners(): void {
        $fixture = new descriptor(
            id: 'navfixture',
            component: 'local_elediaai_core',
            name: 'Nav fixture',
            description: 'A feature with its own start page',
            launchurl: new \moodle_url('/local/elediaai_core/health.php'),
            icon: 'star',
            kind: descriptor::KIND_PAGE,
        );
        (new \ReflectionProperty(registry::class, 'descriptors'))->setValue(null, ['navfixture' => $fixture]);

        [$teacher] = $this->user_with_course_role('editingteacher');
        $this->setUser($teacher);
        $this->assertStringContainsString(
            'local/elediaai_core/index.php',
            section_nav::render_for_feature('navfixture', section_nav::FEATURE_OVERVIEW)
        );

        launcher_access::reset_cache();
        [$student] = $this->user_with_course_role('student');
        $this->setUser($student);
        $html = section_nav::render_for_feature('navfixture', section_nav::FEATURE_OVERVIEW);
        $this->assertStringNotContainsString('local/elediaai_core/index.php', $html);
        $this->assertStringContainsString('health.php', $html);
    }
}

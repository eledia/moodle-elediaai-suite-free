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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Tests for tutor block hook callbacks.
 *
 * @package     block_elediaai_tutor
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace block_elediaai_tutor;

use core_user\hook\extend_default_homepage;

/**
 * Verifies the tutor home start-page option.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(hook_callbacks::class)]
final class hook_callbacks_test extends \advanced_testcase {
    /**
     * Whether the option list contains the tutor home URL.
     *
     * @param extend_default_homepage $hook Dispatched hook.
     * @return bool True when the tutor home option is present.
     */
    private function has_tutor_home(extend_default_homepage $hook): bool {
        foreach (array_keys($hook->get_options()) as $url) {
            if (str_contains((string) $url, '/blocks/elediaai_tutor/home.php')) {
                return true;
            }
        }
        return false;
    }

    public function test_add_tutor_home_option_registers_local_homepage(): void {
        $hook = new extend_default_homepage(false);

        hook_callbacks::add_tutor_home_option($hook);

        $this->assertTrue($this->has_tutor_home($hook));
    }

    public function test_add_tutor_home_option_never_adds_a_non_local_url(): void {
        $hook = new extend_default_homepage(true);

        hook_callbacks::add_tutor_home_option($hook);

        foreach (array_keys($hook->get_options()) as $url) {
            $this->assertStringStartsNotWith('http', (string) $url);
        }
    }

    /**
     * Invoke the private page gate for the sitewide tutor.
     *
     * @return bool
     */
    private function sitewide_tutor_allowed(): bool {
        $method = new \ReflectionMethod(hook_callbacks::class, 'sitewide_tutor_allowed_on_page');
        return (bool) $method->invoke(null);
    }

    /**
     * Pages that never call set_url() (redirect interstitials) dispatch output
     * hooks too. Reading $PAGE->url there raises a debugging() notice, which
     * dev-mode error handlers escalate to a fatal error — the gate must answer
     * false without touching $PAGE->url.
     */
    public function test_sitewide_tutor_denied_without_page_url(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE = new \moodle_page();
        $PAGE->set_context(\core\context\system::instance());

        $this->assertFalse($this->sitewide_tutor_allowed());
        $this->assertDebuggingNotCalled();
    }

    public function test_sitewide_tutor_allowed_with_page_url(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE = new \moodle_page();
        $PAGE->set_context(\core\context\system::instance());
        $PAGE->set_url(new \moodle_url('/my/index.php'));

        $this->assertTrue($this->sitewide_tutor_allowed());
        $this->assertDebuggingNotCalled();
    }

    /**
     * On the front page with a tutor block in its region the block owns the
     * launcher; the site-wide one stays away (#207).
     */
    public function test_sitewide_tutor_steps_back_on_front_page_with_block(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE = $this->front_page();
        $this->assertTrue($this->sitewide_tutor_allowed(), 'Without a block the front page gets the site-wide tutor.');

        $PAGE->blocks->add_block('elediaai_tutor', $PAGE->blocks->get_default_region(), 0, false, 'site-index');
        $PAGE = $this->front_page();
        $this->assertFalse($this->sitewide_tutor_allowed());
        $this->assertDebuggingNotCalled();
    }

    /**
     * The block that opts a course in: the one on the course page wins over
     * one inside an activity, then the oldest.
     */
    public function test_unlocking_instance_prefers_the_course_page(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $this->assertNull(\block_elediaai_tutor\local\widget::unlocking_instance((int) $course->id));

        $page = $gen->create_module('page', ['course' => $course->id]);
        $inactivity = $this->add_tutor_block(\core\context\module::instance($page->cmid), 'mod-page-view');
        $this->assertSame($inactivity, (int) \block_elediaai_tutor\local\widget::unlocking_instance((int) $course->id)->id);

        $oncourse = $this->add_tutor_block(\core\context\course::instance($course->id), 'course-view-*');
        $this->assertSame($oncourse, (int) \block_elediaai_tutor\local\widget::unlocking_instance((int) $course->id)->id);
    }

    /**
     * On a page of the course without a block the site-wide launcher is the
     * tutor of the course's block: its name, not the site default (#207).
     */
    public function test_sitewide_tutor_uses_the_unlocking_instance(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('simulator', 1, 'local_elediaai_chatengine');
        set_config('allowllmonly', 1, 'block_elediaai_tutor');
        set_config('mcpserviceid', 0, 'local_elediaai_chatengine');
        set_config('persona', 'Site tutor', 'block_elediaai_tutor');

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $blockid = $this->add_tutor_block(\core\context\course::instance($course->id), 'course-view-*');
        global $DB;
        $configdata = base64_encode(serialize((object) ['persona' => 'Instance tutor', 'ragmode' => 'llmonly']));
        $DB->set_field('block_instances', 'configdata', $configdata, ['id' => $blockid]);

        $page = $gen->create_module('page', ['course' => $course->id]);
        $PAGE = new \moodle_page();
        $PAGE->set_cm(get_coursemodule_from_id('page', $page->cmid), $course);
        $PAGE->set_pagetype('mod-page-view');
        $PAGE->set_url(new \moodle_url('/mod/page/view.php', ['id' => $page->cmid]));

        $hook = new \core\hook\output\before_standard_top_of_body_html_generation($PAGE->get_renderer('core'));
        hook_callbacks::inject_sitewide_tutor($hook);
        $html = $hook->get_output();
        $this->assertStringContainsString('Instance tutor', $html);
        $this->assertStringNotContainsString('Site tutor', $html);
    }

    /**
     * Place a tutor block directly.
     *
     * @param \context $parent Parent context.
     * @param string $pagetypepattern Page type pattern.
     * @return int Block instance id.
     */
    private function add_tutor_block(\context $parent, string $pagetypepattern): int {
        global $DB;
        return (int) $DB->insert_record('block_instances', (object) [
            'blockname' => 'elediaai_tutor',
            'parentcontextid' => $parent->id,
            'showinsubcontexts' => 0,
            'pagetypepattern' => $pagetypepattern,
            'defaultregion' => 'side-pre',
            'defaultweight' => 0,
            'configdata' => '',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * A fresh front page, as the theme sets it up before the body is written.
     *
     * @return \moodle_page
     */
    private function front_page(): \moodle_page {
        $page = new \moodle_page();
        $page->set_course(get_site());
        $page->set_pagetype('site-index');
        $page->set_pagelayout('frontpage');
        $page->set_url(new \moodle_url('/'));
        $page->blocks->add_region('side-pre');
        $page->blocks->set_default_region('side-pre');
        return $page;
    }
}

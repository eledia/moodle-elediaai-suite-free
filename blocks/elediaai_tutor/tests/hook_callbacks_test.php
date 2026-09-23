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
}

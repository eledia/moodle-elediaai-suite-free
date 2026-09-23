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

/**
 * Section navigation tests.
 *
 * @package    local_elediaai_core
 * @category   test
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core;

use advanced_testcase;
use local_elediaai_core\output\section_nav;

defined('MOODLE_INTERNAL') || die();

/**
 * The suite's own pages must reach the suite's own reports.
 *
 * @covers \local_elediaai_core\output\section_nav
 */
final class section_nav_test extends advanced_testcase {
    /** @var string The report's address, as every bar must spell it. */
    private const HEALTH = '/local/elediaai_core/health.php';

    /**
     * The dashboard offers the health report.
     *
     * This is the regression this test exists for: the report is assembled by
     * this plugin, but for a while its only links lived in four *other*
     * plugins. On a site with none of them installed it was unreachable.
     */
    public function test_the_dashboard_bar_links_the_health_report(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $html = section_nav::render(section_nav::SECTION_OVERVIEW);

        $this->assertStringContainsString(self::HEALTH, $html);
    }

    /**
     * The page marks its own tab, so the reader knows where they are.
     */
    public function test_the_health_page_marks_its_own_tab(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $html = section_nav::render(section_nav::SECTION_HEALTH);

        $this->assertStringContainsString(self::HEALTH . '" aria-current="page"', $html);
    }

    /**
     * A tab nobody may follow is worse than no tab: the report requires
     * moodle/site:config, so a teacher must not be offered it.
     */
    public function test_a_user_without_site_config_is_not_offered_the_report(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $html = section_nav::render(section_nav::SECTION_OVERVIEW);

        $this->assertStringNotContainsString(self::HEALTH, $html);
    }

    /**
     * Both bars describe the entry from the same place.
     *
     * The infrastructure bar keeps the report as a cross-link -- whoever is
     * setting up the grounding chain is one click away from the report -- but
     * its address, icon and label come from `health_item()`, not from a copy.
     */
    public function test_both_bars_take_the_entry_from_one_place(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $item = section_nav::health_item();
        $suite = section_nav::render(section_nav::SECTION_OVERVIEW);
        $infra = section_nav::render_infrastructure(section_nav::INFRA_TUTOR);

        $this->assertSame(self::HEALTH, $item['url']->out_as_local_url(false));
        foreach ([$suite, $infra] as $html) {
            $this->assertStringContainsString($item['url']->out(false), $html);
            $this->assertStringContainsString(s($item['label']), $html);
        }
    }

    /**
     * The infrastructure bar never marks the report as current: it is a
     * cross-link there, and `health.php` shows the suite bar instead.
     */
    public function test_the_infrastructure_bar_does_not_claim_the_report(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $html = section_nav::render_infrastructure(section_nav::INFRA_SOURCES);

        $this->assertStringContainsString(self::HEALTH, $html);
        $this->assertStringNotContainsString(self::HEALTH . '" aria-current="page"', $html);
    }
}

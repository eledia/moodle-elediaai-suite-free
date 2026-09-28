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

use local_elediaai_core\feature\registry;
use local_elediaai_core\output\component_overview;

/**
 * The administrator's component inventory on the suite overview.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_core\output\component_overview
 */
final class component_overview_test extends \advanced_testcase {
    /**
     * Rows keyed by component.
     *
     * @return \stdClass[]
     */
    private function rows(): array {
        $out = [];
        foreach (component_overview::rows() as $row) {
            $out[$row->component] = $row;
        }
        return $out;
    }

    /**
     * Die Uebersicht nennt Release und Version, wie Moodle sie installiert hat.
     */
    public function test_core_is_listed_with_its_installed_version(): void {
        $this->resetAfterTest();

        $rows = $this->rows();
        $info = \core_plugin_manager::instance()->get_plugin_info('local_elediaai_core');

        $this->assertArrayHasKey('local_elediaai_core', $rows);
        $this->assertSame((string) $info->versiondb, $rows['local_elediaai_core']->version);
        $this->assertSame((string) $info->release, $rows['local_elediaai_core']->release);
    }

    /**
     * Die Chat-Engine fehlte: sie hat keine Kachel, weil sie keine Funktion
     * anbietet, ist aber Teil der Suite und hat eine Version.
     */
    public function test_a_component_without_tile_is_listed_without_switch(): void {
        $this->resetAfterTest();

        if (!\core_component::get_component_directory('local_elediaai_chatengine')) {
            $this->markTestSkipped('local_elediaai_chatengine is not installed.');
        }

        $rows = $this->rows();
        $this->assertArrayHasKey('local_elediaai_chatengine', $rows);
        $this->assertSame(component_overview::STATUS_NOSWITCH, $rows['local_elediaai_chatengine']->status);
    }

    /**
     * Der Status folgt den Schaltern der Registry.
     */
    public function test_status_follows_the_registry_switches(): void {
        $this->resetAfterTest();

        $own = array_filter(
            registry::all(),
            static fn($d): bool => $d->component === 'local_elediaai_core'
                && !registry::is_placeholder($d)
                && registry::is_switchable($d->id)
        );
        $this->assertNotEmpty($own, 'Der Kern bringt mindestens eine schaltbare Funktion mit (Audit).');

        $this->assertSame(component_overview::STATUS_ACTIVE, $this->rows()['local_elediaai_core']->status);

        foreach ($own as $descriptor) {
            registry::set_enabled($descriptor->id, false);
        }
        $row = $this->rows()['local_elediaai_core'];
        $this->assertSame(component_overview::STATUS_OFF, $row->status);
        $this->assertCount(count($own), $row->switchedoff);

        if (count($own) > 1) {
            registry::set_enabled(reset($own)->id, true);
            $this->assertSame(component_overview::STATUS_PARTIAL, $this->rows()['local_elediaai_core']->status);
        }
    }

    /**
     * Nur wer die Website konfigurieren darf, sieht die Tabelle.
     */
    public function test_only_administrators_see_the_inventory(): void {
        $this->resetAfterTest();

        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertSame('', component_overview::render());

        $this->setAdminUser();
        $html = component_overview::render();
        $this->assertStringContainsString('data-region="suite-components"', $html);
        $this->assertStringContainsString('local_elediaai_core', $html);
        $this->assertStringContainsString(get_string('components_heading', 'local_elediaai_core'), $html);
    }
}

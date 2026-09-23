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
 * Welcher Reifegrad auf einer Kachel steht.
 *
 * @package    local_elediaai_core
 * @category   test
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core;

use advanced_testcase;
use local_elediaai_core\feature\registry;

/**
 * Der Reifegrad kommt aus dem Plugin selbst.
 *
 * @covers \local_elediaai_core\feature\registry::maturity
 * @covers \local_elediaai_core\feature\registry::is_released
 */
final class feature_maturity_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        registry::reset_cache();
    }

    /**
     * Die vier freigegebenen Komponenten tragen keine Marke.
     *
     * Betreiberentscheidung 23.09.2026: die oeffentlich ausgelieferten Komponenten
     * sind freigegeben, auch der Kern und die Chat-Engine -- sie werden als
     * "eLeDia.ai Suite free" veroeffentlicht und duerfen sich nicht selbst als
     * unfertig ausweisen. Wer das aendert, aendert es in der version.php des
     * Plugins -- und dieser Test sagt, dass er es gemerkt hat.
     */
    public function test_the_released_components_are_the_agreed_four(): void {
        $freigegeben = [
            'block_elediaai_tutor',
            'webservice_elediamcp',
            'filter_eledia_translate',
            'local_elediaai_sources',
        ];

        foreach ($freigegeben as $component) {
            if (\core_component::get_component_directory($component) === null) {
                // In der CI ist meist nur ein Teil der Suite installiert.
                continue;
            }
            $this->assertTrue(
                registry::is_released($component),
                "{$component} gilt nicht mehr als freigegeben."
            );
        }
    }

    /**
     * Der Kern ist freigegeben und sagt es.
     *
     * Er wird mit dem freien Paket ausgeliefert; ein Beta-Vermerk auf seiner
     * Kachel wuerde der Veroeffentlichung widersprechen.
     */
    public function test_the_core_calls_itself_released(): void {
        $this->assertSame(MATURITY_STABLE, registry::maturity('local_elediaai_core'));
        $this->assertTrue(registry::is_released('local_elediaai_core'));
    }

    /**
     * Wer nichts sagt, bekommt keine Marke aufgedrueckt.
     *
     * Die Kachel eines fremden Plugins darf nicht als unfertig erscheinen,
     * nur weil es zu dieser Frage schweigt.
     */
    public function test_silence_counts_as_released(): void {
        $this->assertNull(registry::maturity('local_gibtesnicht'));
        $this->assertTrue(registry::is_released('local_gibtesnicht'));
    }

    /**
     * Gelesen wird einmal, nicht je Kachel.
     */
    public function test_the_answer_is_kept_for_the_request(): void {
        $erste = registry::maturity('local_elediaai_core');
        $zweite = registry::maturity('local_elediaai_core');

        $this->assertSame($erste, $zweite);
        registry::reset_cache();
        $this->assertSame($erste, registry::maturity('local_elediaai_core'));
    }
}

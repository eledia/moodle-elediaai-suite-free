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

namespace local_aitransparency;

use local_elediaai_core\feature\registry;

/**
 * The provenance report has a tile in the suite launcher (#30).
 *
 * @package    local_aitransparency
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_aitransparency\elediaai_core\feature_provider
 */
final class feature_provider_test extends \advanced_testcase {
    /**
     * Admins see the tile; somebody without the report capability does not.
     *
     * @return void
     */
    public function test_the_report_has_a_tile_for_those_who_may_read_it(): void {
        $this->resetAfterTest();
        registry::reset_cache();

        $this->assertArrayHasKey('aitransparency', registry::all());

        $this->setAdminUser();
        $this->assertArrayHasKey('aitransparency', registry::visible());

        $this->setUser($this->getDataGenerator()->create_user());
        registry::reset_cache();
        $this->assertArrayNotHasKey('aitransparency', registry::visible());
    }
}

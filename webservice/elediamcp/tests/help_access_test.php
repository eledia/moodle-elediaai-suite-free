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

namespace webservice_elediamcp;

use advanced_testcase;
use webservice_elediamcp\output\shell;

/**
 * The help page is readable for everyone who issues tokens (G-05).
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \webservice_elediamcp\output\shell
 */
final class help_access_test extends advanced_testcase {
    /**
     * Token issuers and administrators may read the help; others may not.
     */
    public function test_help_follows_token_capability(): void {
        $this->resetAfterTest();

        $issuer = $this->getDataGenerator()->create_user();
        $this->assertTrue(shell::can_view_help((int) $issuer->id));
        $this->assertTrue(shell::can_view_help((int) get_admin()->id));

        $blocked = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        $system = \core\context\system::instance();
        assign_capability('webservice/elediamcp:managetokens', CAP_PROHIBIT, $roleid, $system->id);
        role_assign($roleid, $blocked->id, $system->id);
        accesslib_clear_all_caches_for_unit_testing();

        $this->assertFalse(shell::can_view_help((int) $blocked->id));
    }
}

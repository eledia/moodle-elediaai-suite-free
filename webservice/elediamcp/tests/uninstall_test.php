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
use PHPUnit\Framework\Attributes\CoversFunction;
use webservice_elediamcp\local\token_manager;

/**
 * Tests for the uninstall hook.
 *
 * @package     webservice_elediamcp
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversFunction('xmldb_webservice_elediamcp_uninstall')]
final class uninstall_test extends advanced_testcase {
    /**
     * The default service goes with its tokens; a service the admin added stays.
     */
    public function test_uninstall_removes_only_the_default_service(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/webservice/elediamcp/db/uninstall.php');
        $this->resetAfterTest();

        $serviceid = token_manager::ensure_default_service_configured();
        $user = $this->getDataGenerator()->create_user();
        token_manager::create_token($user->id, $serviceid, 'Laptop');
        $ownid = $DB->insert_record('external_services', (object) [
            'name' => 'Own service', 'shortname' => 'ownservice', 'enabled' => 1,
            'restrictedusers' => 0, 'timecreated' => time(),
        ]);

        $this->assertTrue(xmldb_webservice_elediamcp_uninstall());

        $this->assertFalse($DB->record_exists('external_services', ['id' => $serviceid]));
        $this->assertEquals(0, $DB->count_records('external_tokens', ['externalserviceid' => $serviceid]));
        $this->assertTrue($DB->record_exists('external_services', ['id' => $ownid]));
    }
}

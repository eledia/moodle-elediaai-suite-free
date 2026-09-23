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
use webservice_elediamcp\local\premium;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests the MCP premium gate and its site grant.
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \webservice_elediamcp\local\premium
 */
final class premium_test extends advanced_testcase {
    /**
     * The MCP catalogue remains free until the dedicated grant is enabled.
     */
    public function test_mcp_tools_follow_premium_grant(): void {
        $this->resetAfterTest();

        if (!class_exists('\\local_elediaai_tutor_premium\\feature')) {
            $this->markTestSkipped('Premium add-on is not installed on this site.');
        }

        $this->assertFalse(premium::has_mcp_tools());
        set_config('grant_mcp_tools', 1, 'local_elediaai_tutor_premium');
        $this->assertTrue(premium::has_mcp_tools());
    }
}

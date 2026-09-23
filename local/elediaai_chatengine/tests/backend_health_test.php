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

namespace local_elediaai_chatengine;

use local_elediaai_chatengine\adapter\health;
use local_elediaai_chatengine\adapter\ingestionapi_adapter;
use local_elediaai_chatengine\adapter\literag_adapter;

/**
 * A backend answers for its own reachability.
 *
 * The operator dashboard used to reach into one particular backend's client to
 * test it, which could only ever test that one backend. These pin down that
 * asking works through the contract instead.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_chatengine\adapter\health
 */
final class backend_health_test extends \advanced_testcase {
    /**
     * A backend that is not set up says so, rather than reporting a fault.
     *
     * A site that has not configured anything yet has nothing broken.
     *
     * @return void
     */
    public function test_an_unconfigured_backend_is_not_a_fault(): void {
        $this->resetAfterTest();
        set_config('sink', '', 'local_elediaai_sources');

        foreach ([new literag_adapter(), new ingestionapi_adapter()] as $adapter) {
            $health = $adapter->health();
            $this->assertFalse($health->configured, get_class($adapter));
            $this->assertFalse($health->healthy, get_class($adapter));
        }
    }

    /**
     * Every adapter answers the question at all.
     *
     * @return void
     */
    public function test_the_contract_is_implemented_by_every_adapter(): void {
        $this->resetAfterTest();

        foreach ([new literag_adapter(), new ingestionapi_adapter()] as $adapter) {
            $this->assertInstanceOf(health::class, $adapter->health());
        }
    }

    /**
     * "Not set up" and "does not answer" are different answers.
     *
     * A dashboard needs to tell an operator who has not finished configuring
     * apart from one whose model is down.
     *
     * @return void
     */
    public function test_unconfigured_and_unhealthy_are_distinguishable(): void {
        $unconfigured = health::unconfigured();
        $broken = new health(false, 'connection refused');

        $this->assertFalse($unconfigured->configured);
        $this->assertTrue($broken->configured);
        $this->assertFalse($broken->healthy);
        $this->assertSame('connection refused', $broken->message);
    }
}

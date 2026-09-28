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

namespace block_elediaai_tutor;

use PHPUnit\Framework\Attributes\CoversFunction;
use block_elediaai_tutor\local\ltm;
use local_elediaai_chatengine\local\service_user;

/**
 * Tests for the uninstall hook.
 *
 * Moodle calls the hook before it drops the plugin's tables. A fatal error
 * there aborts the whole uninstall and, because the other suite plugins
 * depend on the tutor, blocks their removal too.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversFunction('xmldb_block_elediaai_tutor_uninstall')]
final class uninstall_test extends \advanced_testcase {
    /**
     * The hook removes the memory preference and the maintenance account.
     */
    public function test_uninstall_removes_preference_and_service_account(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/blocks/elediaai_tutor/db/uninstall.php');
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        ltm::set_enabled((int) $user->id, true);
        $serviceuser = service_user::get_or_create();

        $this->assertTrue(xmldb_block_elediaai_tutor_uninstall());

        $this->assertFalse($DB->record_exists('user_preferences', ['name' => ltm::PREF]));
        $this->assertEquals(1, $DB->get_field('user', 'deleted', ['id' => $serviceuser->id]));
    }

    /**
     * Without a maintenance account the hook still completes.
     */
    public function test_uninstall_without_service_account(): void {
        global $CFG;
        require_once($CFG->dirroot . '/blocks/elediaai_tutor/db/uninstall.php');
        $this->resetAfterTest();

        $this->assertTrue(xmldb_block_elediaai_tutor_uninstall());
    }
}

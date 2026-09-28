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

use local_elediaai_chatengine\local\token_provider;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the connector token provider.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(token_provider::class)]
final class token_provider_test extends \advanced_testcase {
    /**
     * Configure an MCP service the provider can mint tokens for.
     *
     * @return int The service id.
     */
    private function configure_service(): int {
        global $DB;
        if (!token_provider::is_connector_available()) {
            $this->markTestSkipped('webservice_elediamcp is not installed.');
        }
        $serviceid = (int) $DB->insert_record('external_services', (object) [
            'name' => 'MCP test service', 'shortname' => 'mcptest', 'enabled' => 1,
            'restrictedusers' => 0, 'downloadfiles' => 0, 'uploadfiles' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        set_config('services', (string) $serviceid, 'webservice_elediamcp');
        set_config('mcpserviceid', $serviceid, 'local_elediaai_chatengine');
        return $serviceid;
    }

    /**
     * Minting a token leaves the session as it was.
     *
     * A streamed turn has closed the session before the first token of a
     * person is minted; Moodle's note about the new token was then logged as a
     * session change after close.
     */
    public function test_minting_leaves_the_session_alone(): void {
        global $SESSION;
        $this->resetAfterTest();
        $this->configure_service();
        $user = $this->getDataGenerator()->create_user();
        unset($SESSION->webservicenewlycreatedtoken);

        $this->assertNotSame('', token_provider::get_token((int) $user->id));

        $this->assertFalse(isset($SESSION->webservicenewlycreatedtoken));
    }

    /**
     * Revoking removes the person's tokens.
     */
    public function test_revoke_for_user(): void {
        global $DB;
        $this->resetAfterTest();
        $this->configure_service();
        $user = $this->getDataGenerator()->create_user();
        token_provider::get_token((int) $user->id);
        $this->assertTrue($DB->record_exists('external_tokens', ['userid' => $user->id]));

        token_provider::revoke_for_user((int) $user->id);

        $this->assertFalse($DB->record_exists('external_tokens', ['userid' => $user->id]));
    }
}

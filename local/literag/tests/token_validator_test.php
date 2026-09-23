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

namespace local_literag;

use local_literag\local\token_validator;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for in-process Moodle MCP token validation.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(token_validator::class)]
final class token_validator_test extends \advanced_testcase {
    /**
     * Insert an external_tokens row directly and return the plain token.
     *
     * The validator never joins external_services (it only reads the elediamcp
     * services config CSV), so an arbitrary service id is sufficient here.
     *
     * @param int $userid
     * @param int $serviceid
     * @param int|null $validuntil
     * @param string|null $iprestriction
     * @return string
     */
    private function mint(
        int $userid,
        int $serviceid,
        ?int $validuntil = null,
        ?string $iprestriction = null
    ): string {
        global $DB;
        $token = 'lt' . random_string(40);
        $DB->insert_record('external_tokens', (object) [
            'token' => $token,
            'privatetoken' => null,
            'tokentype' => 0,
            'userid' => $userid,
            'externalserviceid' => $serviceid,
            'sid' => null,
            'contextid' => \context_system::instance()->id,
            'creatorid' => $userid,
            'iprestriction' => $iprestriction,
            'validuntil' => $validuntil,
            'timecreated' => time(),
            'lastaccess' => null,
            'name' => 'test',
        ]);
        return $token;
    }

    /**
     * A valid token resolves to its owner.
     */
    public function test_valid_token_resolves_user(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        set_config('services', '1', 'webservice_elediamcp');

        $token = $this->mint((int) $user->id, 1);
        $resolved = token_validator::resolve_user($token);

        $this->assertNotNull($resolved);
        $this->assertSame((int) $user->id, (int) $resolved->id);
    }

    /**
     * Unknown and empty tokens are rejected.
     */
    public function test_unknown_token_rejected(): void {
        $this->resetAfterTest();
        $this->assertNull(token_validator::resolve_user(''));
        $this->assertNull(token_validator::resolve_user('does-not-exist'));
    }

    /**
     * An expired token is rejected.
     */
    public function test_expired_token_rejected(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        set_config('services', '1', 'webservice_elediamcp');

        $token = $this->mint((int) $user->id, 1, time() - 100);
        $this->assertNull(token_validator::resolve_user($token));
    }

    /**
     * A token for a non-MCP service is rejected when MCP services are declared.
     */
    public function test_wrong_service_rejected(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        // Only service 2 is registered as an MCP service; the token is for service 1.
        set_config('services', '2', 'webservice_elediamcp');

        $token = $this->mint((int) $user->id, 1);
        $this->assertNull(token_validator::resolve_user($token));
    }

    /**
     * Tokens are rejected when no MCP service allowlist is configured.
     */
    public function test_missing_service_allowlist_rejected(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        unset_config('services', 'webservice_elediamcp');

        $token = $this->mint((int) $user->id, 1);
        $this->assertNull(token_validator::resolve_user($token));
    }

    /**
     * A suspended user's token is rejected.
     */
    public function test_suspended_user_rejected(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        set_config('services', '1', 'webservice_elediamcp');
        $token = $this->mint((int) $user->id, 1);

        $DB->set_field('user', 'suspended', 1, ['id' => $user->id]);
        $this->assertNull(token_validator::resolve_user($token));
    }
}

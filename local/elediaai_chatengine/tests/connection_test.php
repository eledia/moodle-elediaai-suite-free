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

use PHPUnit\Framework\Attributes\CoversClass;
use local_elediaai_chatengine\local\connection;
use local_elediaai_chatengine\local\guard;

/**
 * Unit tests for the security/configuration helper.
 *
 * @package    local_elediaai_chatengine
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_elediaai_chatengine\local\connection::class)]
#[CoversClass(\local_elediaai_chatengine\local\guard::class)]
final class connection_test extends \advanced_testcase {
    /**
     * A valid HTTPS RAG URL is accepted.
     */
    public function test_validated_rag_url_accepts_https(): void {
        $this->resetAfterTest();
        set_config('backend_ingestionapi_url', 'https://rag.example.com/mcp', 'local_elediaai_chatengine');
        $url = connection::validated_agent_url();
        $this->assertStringStartsWith('https://rag.example.com', $url->out(false));
    }


    /**
     * A missing RAG URL throws.
     */
    public function test_validated_rag_url_missing_throws(): void {
        $this->resetAfterTest();
        set_config('backend_ingestionapi_url', '', 'local_elediaai_chatengine');
        $this->expectException(\moodle_exception::class);
        connection::validated_agent_url();
    }

    /**
     * A plain HTTP URL is rejected unless insecure transport is allowed.
     */
    public function test_validated_rag_url_rejects_http_by_default(): void {
        $this->resetAfterTest();
        set_config('backend_ingestionapi_url', 'http://rag.example.com/mcp', 'local_elediaai_chatengine');
        $this->expectException(\moodle_exception::class);
        connection::validated_agent_url();
    }

    /**
     * HTTP is accepted only when the admin opts in.
     */
    public function test_validated_rag_url_allows_http_when_opted_in(): void {
        $this->resetAfterTest();
        set_config('backend_ingestionapi_url', 'http://localhost:8080/mcp', 'local_elediaai_chatengine');
        set_config('backend_ingestionapi_allowinsecure', 1, 'local_elediaai_chatengine');
        $url = connection::validated_agent_url();
        $this->assertStringStartsWith('http://localhost', $url->out(false));
    }

    /**
     * A non-HTTP scheme (SSRF vector) is rejected.
     */
    public function test_validated_rag_url_rejects_other_schemes(): void {
        $this->resetAfterTest();
        set_config('backend_ingestionapi_url', 'file:///etc/passwd', 'local_elediaai_chatengine');
        set_config('backend_ingestionapi_allowinsecure', 1, 'local_elediaai_chatengine');
        $this->expectException(\moodle_exception::class);
        connection::validated_agent_url();
    }

    /**
     * Empty messages are rejected.
     */
    public function test_validate_message_rejects_empty(): void {
        $this->resetAfterTest();
        $this->expectException(\moodle_exception::class);
        guard::validate_message('   ');
    }

    /**
     * Over-long messages are rejected.
     */
    public function test_validate_message_enforces_length(): void {
        $this->resetAfterTest();
        set_config('maxmessagelength', 10, 'local_elediaai_chatengine');
        $this->expectException(\moodle_exception::class);
        guard::validate_message(str_repeat('a', 11));
    }

    /**
     * The rate limiter trips after the configured number of messages.
     */
    public function test_rate_limit_trips(): void {
        $this->resetAfterTest();
        set_config('ratelimitperminute', 3, 'local_elediaai_chatengine');
        for ($i = 0; $i < 3; $i++) {
            guard::enforce_rate_limit(123);
        }
        $this->expectException(\moodle_exception::class);
        guard::enforce_rate_limit(123);
    }

    /**
     * A zero limit disables rate limiting.
     */
    public function test_rate_limit_disabled(): void {
        $this->resetAfterTest();
        set_config('ratelimitperminute', 0, 'local_elediaai_chatengine');
        for ($i = 0; $i < 50; $i++) {
            guard::enforce_rate_limit(456);
        }
        $this->assertTrue(true); // Reached without exception.
    }

    /**
     * An unset token reads back as an empty string (no auth configured).
     */
    public function test_rag_auth_token_empty_when_unset(): void {
        $this->resetAfterTest();
        $this->assertSame('', connection::agent_auth_token());
    }

    /**
     * A token written through the encrypted-password setting is stored as
     * ciphertext in config_plugins (not plaintext) yet reads back decrypted.
     */
    public function test_rag_auth_token_stored_encrypted_round_trip(): void {
        global $CFG;
        $this->resetAfterTest();
        require_once($CFG->libdir . '/adminlib.php');

        $setting = new \admin_setting_encryptedpassword(
            'local_elediaai_chatengine/backend_ingestionapi_authtoken',
            'RAG token',
            ''
        );
        $this->assertSame('', $setting->write_setting('SECRET-TOKEN'));

        // What actually lands in {config_plugins} must not be the plaintext.
        $stored = get_config('local_elediaai_chatengine', 'backend_ingestionapi_authtoken');
        $this->assertNotSame('SECRET-TOKEN', $stored);
        $this->assertStringNotContainsString('SECRET-TOKEN', (string) $stored);
        $this->assertStringStartsWith(\core\encryption::METHOD_SODIUM . ':', (string) $stored);

        // But the accessor hands the RAG client the real, decrypted value.
        $this->assertSame('SECRET-TOKEN', connection::agent_auth_token());
    }

    /**
     * Saving a replacement token rotates both the ciphertext and plaintext value.
     */
    public function test_rag_auth_token_rotation_replaces_encrypted_value(): void {
        global $CFG;
        $this->resetAfterTest();
        require_once($CFG->libdir . '/adminlib.php');

        $setting = new \admin_setting_encryptedpassword(
            'local_elediaai_chatengine/backend_ingestionapi_authtoken',
            'RAG token',
            ''
        );
        $this->assertSame('', $setting->write_setting('FIRST-TOKEN'));
        $firstciphertext = get_config('local_elediaai_chatengine', 'backend_ingestionapi_authtoken');

        $this->assertSame('', $setting->write_setting('ROTATED-TOKEN'));
        $rotatedciphertext = get_config('local_elediaai_chatengine', 'backend_ingestionapi_authtoken');

        $this->assertNotSame($firstciphertext, $rotatedciphertext);
        $this->assertStringNotContainsString('ROTATED-TOKEN', (string) $rotatedciphertext);
        $this->assertSame('ROTATED-TOKEN', connection::agent_auth_token());
    }

    /**
     * A multi-line "Header: value" token (custom header method) survives the
     * encrypt/decrypt round trip intact.
     */
    public function test_rag_auth_token_preserves_multiline_header(): void {
        $this->resetAfterTest();
        $header = "X-Api-Key: abc123\nX-Tenant: acme";
        set_config('backend_ingestionapi_authtoken', \core\encryption::encrypt($header), 'local_elediaai_chatengine');
        $this->assertSame($header, connection::agent_auth_token());
    }

    /**
     * A legacy plaintext value (pre-encryption, no method prefix) is returned
     * as-is so authentication keeps working until the upgrade migrates it.
     */
    public function test_rag_auth_token_reads_legacy_plaintext(): void {
        $this->resetAfterTest();
        set_config('backend_ingestionapi_authtoken', 'LEGACY-PLAINTEXT', 'local_elediaai_chatengine');
        $this->assertSame('LEGACY-PLAINTEXT', connection::agent_auth_token());
    }

    /**
     * An undecryptable encrypted value fails closed (empty) rather than leaking
     * ciphertext as a credential.
     */
    public function test_rag_auth_token_fails_closed_on_bad_ciphertext(): void {
        $this->resetAfterTest();
        // Carries the method prefix but is not valid ciphertext.
        set_config('backend_ingestionapi_authtoken', \core\encryption::METHOD_SODIUM . ':not-real', 'local_elediaai_chatengine');
        $this->assertSame('', connection::agent_auth_token());
        // The failure is reported to developers only; the token is not logged.
        $this->assertDebuggingCalled();
    }
}

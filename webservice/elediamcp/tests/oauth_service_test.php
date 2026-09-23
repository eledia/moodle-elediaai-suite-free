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
use webservice_elediamcp\local\oauth\oauth_exception;
use webservice_elediamcp\local\oauth\service;
use webservice_elediamcp\local\oauth\store;
use webservice_elediamcp\local\token_manager;

/**
 * Tests for the OAuth 2.1 Authorization Code + PKCE authorization server.
 *
 * @package     webservice_elediamcp
 * @author      Sven (eLeDia) <dev@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \webservice_elediamcp\local\oauth\service
 * @covers      \webservice_elediamcp\local\oauth\store
 * @covers      \webservice_elediamcp\local\oauth\oauth_exception
 */
final class oauth_service_test extends advanced_testcase {
    /** @var string A valid registered redirect URI used across tests. */
    private const REDIRECT = 'https://client.example/callback';

    /**
     * Enable OAuth and configure a default MCP service before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        set_config('oauth_enabled', 1, 'webservice_elediamcp');
        token_manager::ensure_default_service_configured();
    }

    /**
     * Create a user that satisfies the MCP service's required capability.
     *
     * @return \stdClass The user record.
     */
    private function mcp_user(): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $context = \context_system::instance();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('webservice/elediamcp:use', CAP_ALLOW, $roleid, $context->id, true);
        role_assign($roleid, $user->id, $context->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $user;
    }

    /**
     * Register a public client with a single redirect URI.
     *
     * @param array $overrides Registration request overrides.
     * @return array The registration response.
     */
    private function register(array $overrides = []): array {
        return service::register_client(array_merge([
            'client_name' => 'Test MCP Client',
            'redirect_uris' => [self::REDIRECT],
        ], $overrides));
    }

    /**
     * Build a matching PKCE verifier/challenge pair.
     *
     * @return array{0: string, 1: string} [verifier, challenge]
     */
    private function pkce_pair(): array {
        $verifier = service::base64url_encode(random_bytes(48));
        $challenge = service::base64url_encode(hash('sha256', $verifier, true));
        return [$verifier, $challenge];
    }

    /**
     * Build an authorization request parameter set.
     *
     * @param string $clientid Client id.
     * @param string $challenge PKCE challenge.
     * @param array $overrides Parameter overrides.
     * @return array
     */
    private function auth_params(string $clientid, string $challenge, array $overrides = []): array {
        return array_merge([
            'response_type' => 'code',
            'client_id' => $clientid,
            'redirect_uri' => self::REDIRECT,
            'scope' => 'mcp:tools.read mcp:tools.call',
            'state' => 'xyz-state',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ], $overrides);
    }

    /**
     * The happy path: register, authorize, exchange, receive a working MCP token.
     */
    public function test_full_authorization_code_pkce_flow(): void {
        global $DB;
        $user = $this->mcp_user();
        $client = $this->register();
        [$verifier, $challenge] = $this->pkce_pair();

        $ctx = service::validate_authorization_request($this->auth_params($client['client_id'], $challenge));
        $code = service::issue_code($ctx, (int) $user->id);
        $this->assertNotEmpty($code);
        $this->assertEquals(1, $DB->count_records(store::CODE_TABLE));

        $response = service::exchange_code([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => self::REDIRECT,
            'client_id' => $client['client_id'],
            'code_verifier' => $verifier,
        ]);

        $this->assertSame('Bearer', $response['token_type']);
        $this->assertNotEmpty($response['access_token']);
        $this->assertSame('mcp:tools.read mcp:tools.call', $response['scope']);

        // The code is single-use: it must be gone after exchange.
        $this->assertEquals(0, $DB->count_records(store::CODE_TABLE));

        // A real Moodle token was minted for the authorising user, on an MCP service.
        $token = $DB->get_record('external_tokens', ['token' => $response['access_token']]);
        $this->assertNotFalse($token);
        $this->assertEquals($user->id, $token->userid);
        $this->assertTrue(token_manager::is_mcp_service((int) $token->externalserviceid));

        // MCP metadata row exists and is owned by the user (revocable in self-service).
        $meta = $DB->get_record('webservice_elediamcp_token', ['externaltokenid' => $token->id]);
        $this->assertNotFalse($meta);
        $this->assertEquals($user->id, $meta->userid);
        $this->assertNull($meta->component);
    }

    /**
     * Replaying a consumed authorization code is rejected.
     */
    public function test_code_is_single_use(): void {
        $user = $this->mcp_user();
        $client = $this->register();
        [$verifier, $challenge] = $this->pkce_pair();
        $ctx = service::validate_authorization_request($this->auth_params($client['client_id'], $challenge));
        $code = service::issue_code($ctx, (int) $user->id);

        $request = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => self::REDIRECT,
            'client_id' => $client['client_id'],
            'code_verifier' => $verifier,
        ];
        service::exchange_code($request);

        $this->expectException(oauth_exception::class);
        try {
            service::exchange_code($request);
        } catch (oauth_exception $ex) {
            $this->assertSame('invalid_grant', $ex->get_error_code());
            throw $ex;
        }
    }

    /**
     * An expired authorization code is rejected and burned.
     */
    public function test_expired_code_rejected(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $client = $this->register();
        [$verifier, $challenge] = $this->pkce_pair();
        $ctx = service::validate_authorization_request($this->auth_params($client['client_id'], $challenge));
        $code = service::issue_code($ctx, (int) $user->id);

        // Force the stored code into the past.
        $row = $DB->get_record(store::CODE_TABLE, ['codehash' => store::hash($code)]);
        $DB->set_field(store::CODE_TABLE, 'expires', time() - 10, ['id' => $row->id]);

        try {
            service::exchange_code([
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => self::REDIRECT,
                'client_id' => $client['client_id'],
                'code_verifier' => $verifier,
            ]);
            $this->fail('Expected oauth_exception');
        } catch (oauth_exception $ex) {
            $this->assertSame('invalid_grant', $ex->get_error_code());
        }
        $this->assertEquals(0, $DB->count_records(store::CODE_TABLE));
    }

    /**
     * A wrong PKCE verifier is rejected.
     */
    public function test_pkce_mismatch_rejected(): void {
        $user = $this->getDataGenerator()->create_user();
        $client = $this->register();
        [, $challenge] = $this->pkce_pair();
        $ctx = service::validate_authorization_request($this->auth_params($client['client_id'], $challenge));
        $code = service::issue_code($ctx, (int) $user->id);

        [$otherverifier] = $this->pkce_pair();
        try {
            service::exchange_code([
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => self::REDIRECT,
                'client_id' => $client['client_id'],
                'code_verifier' => $otherverifier,
            ]);
            $this->fail('Expected oauth_exception');
        } catch (oauth_exception $ex) {
            $this->assertSame('invalid_grant', $ex->get_error_code());
        }
    }

    /**
     * The redirect URI presented at the token endpoint must match the code.
     */
    public function test_redirect_uri_mismatch_at_token_endpoint(): void {
        $user = $this->getDataGenerator()->create_user();
        $client = $this->register(['redirect_uris' => [self::REDIRECT, 'https://client.example/other']]);
        [$verifier, $challenge] = $this->pkce_pair();
        $ctx = service::validate_authorization_request($this->auth_params($client['client_id'], $challenge));
        $code = service::issue_code($ctx, (int) $user->id);

        try {
            service::exchange_code([
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => 'https://client.example/other',
                'client_id' => $client['client_id'],
                'code_verifier' => $verifier,
            ]);
            $this->fail('Expected oauth_exception');
        } catch (oauth_exception $ex) {
            $this->assertSame('invalid_grant', $ex->get_error_code());
        }
    }

    /**
     * An unsupported grant type is rejected before any lookup.
     */
    public function test_unsupported_grant_type(): void {
        $this->expectException(oauth_exception::class);
        try {
            service::exchange_code(['grant_type' => 'client_credentials']);
        } catch (oauth_exception $ex) {
            $this->assertSame('unsupported_grant_type', $ex->get_error_code());
            throw $ex;
        }
    }

    /**
     * A token request against an unknown client fails with invalid_client (401).
     */
    public function test_token_unknown_client(): void {
        try {
            service::exchange_code([
                'grant_type' => 'authorization_code',
                'code' => 'whatever',
                'redirect_uri' => self::REDIRECT,
                'client_id' => 'mcp_does_not_exist',
                'code_verifier' => str_repeat('a', 50),
            ]);
            $this->fail('Expected oauth_exception');
        } catch (oauth_exception $ex) {
            $this->assertSame('invalid_client', $ex->get_error_code());
            $this->assertSame(401, $ex->get_http_status());
        }
    }

    /**
     * Authorization request against an unknown client is a non-redirectable error.
     */
    public function test_authorize_unknown_client_not_redirectable(): void {
        [, $challenge] = $this->pkce_pair();
        try {
            service::validate_authorization_request($this->auth_params('mcp_nope', $challenge));
            $this->fail('Expected oauth_exception');
        } catch (oauth_exception $ex) {
            $this->assertSame('invalid_client', $ex->get_error_code());
            $this->assertFalse($ex->is_redirectable());
        }
    }

    /**
     * A redirect URI not registered for the client is rejected without redirecting.
     */
    public function test_authorize_redirect_uri_mismatch_not_redirectable(): void {
        $client = $this->register();
        [, $challenge] = $this->pkce_pair();
        try {
            service::validate_authorization_request(
                $this->auth_params($client['client_id'], $challenge, ['redirect_uri' => 'https://evil.example/cb'])
            );
            $this->fail('Expected oauth_exception');
        } catch (oauth_exception $ex) {
            $this->assertSame('invalid_request', $ex->get_error_code());
            $this->assertFalse($ex->is_redirectable());
        }
    }

    /**
     * With a trusted redirect URI, a bad response_type is a redirectable error.
     */
    public function test_authorize_bad_response_type_is_redirectable(): void {
        $client = $this->register();
        [, $challenge] = $this->pkce_pair();
        try {
            service::validate_authorization_request(
                $this->auth_params($client['client_id'], $challenge, ['response_type' => 'token'])
            );
            $this->fail('Expected oauth_exception');
        } catch (oauth_exception $ex) {
            $this->assertSame('unsupported_response_type', $ex->get_error_code());
            $this->assertTrue($ex->is_redirectable());
        }
    }

    /**
     * The plain PKCE method is refused; only S256 is accepted.
     */
    public function test_authorize_requires_s256(): void {
        $client = $this->register();
        [$verifier] = $this->pkce_pair();
        try {
            service::validate_authorization_request(
                $this->auth_params($client['client_id'], $verifier, ['code_challenge_method' => 'plain'])
            );
            $this->fail('Expected oauth_exception');
        } catch (oauth_exception $ex) {
            $this->assertSame('invalid_request', $ex->get_error_code());
            $this->assertTrue($ex->is_redirectable());
        }
    }

    /**
     * A malformed code challenge is rejected.
     */
    public function test_authorize_invalid_code_challenge(): void {
        $client = $this->register();
        try {
            service::validate_authorization_request(
                $this->auth_params($client['client_id'], 'too-short', [])
            );
            $this->fail('Expected oauth_exception');
        } catch (oauth_exception $ex) {
            $this->assertSame('invalid_request', $ex->get_error_code());
        }
    }

    /**
     * A missing redirect URI is allowed only when the client has exactly one.
     */
    public function test_authorize_missing_redirect_uri_single_client(): void {
        $client = $this->register();
        [, $challenge] = $this->pkce_pair();
        $ctx = service::validate_authorization_request(
            $this->auth_params($client['client_id'], $challenge, ['redirect_uri' => ''])
        );
        $this->assertSame(self::REDIRECT, $ctx->redirecturi);
    }

    /**
     * A missing redirect URI is rejected when the client registered several.
     */
    public function test_authorize_missing_redirect_uri_multi_client(): void {
        $client = $this->register(['redirect_uris' => [self::REDIRECT, 'https://client.example/other']]);
        [, $challenge] = $this->pkce_pair();
        try {
            service::validate_authorization_request(
                $this->auth_params($client['client_id'], $challenge, ['redirect_uri' => ''])
            );
            $this->fail('Expected oauth_exception');
        } catch (oauth_exception $ex) {
            $this->assertSame('invalid_request', $ex->get_error_code());
            $this->assertFalse($ex->is_redirectable());
        }
    }

    /**
     * Dynamic registration rejects invalid redirect URIs and honours the toggle.
     */
    public function test_dynamic_registration_validation(): void {
        // Plain http on a non-loopback host is rejected.
        try {
            $this->register(['redirect_uris' => ['http://client.example/cb']]);
            $this->fail('Expected oauth_exception');
        } catch (oauth_exception $ex) {
            $this->assertSame('invalid_redirect_uri', $ex->get_error_code());
        }

        // Loopback http and private-use schemes are accepted.
        $ok = $this->register(['redirect_uris' => ['http://127.0.0.1:1234/cb', 'com.example.app:/cb']]);
        $this->assertNotEmpty($ok['client_id']);
        $this->assertSame('none', $ok['token_endpoint_auth_method']);

        // Disabling dynamic registration blocks it.
        set_config('oauth_allow_dynamic_registration', 0, 'webservice_elediamcp');
        try {
            $this->register();
            $this->fail('Expected oauth_exception');
        } catch (oauth_exception $ex) {
            $this->assertSame('invalid_request', $ex->get_error_code());
            $this->assertSame(403, $ex->get_http_status());
        }
    }

    /**
     * PKCE S256 verification behaves per RFC 7636.
     */
    public function test_verify_pkce(): void {
        [$verifier, $challenge] = $this->pkce_pair();
        $this->assertTrue(service::verify_pkce($verifier, $challenge));
        $this->assertFalse(service::verify_pkce($verifier . 'x', $challenge));
        // Too-short verifier.
        $this->assertFalse(service::verify_pkce('short', $challenge));
        // Illegal character in verifier.
        $this->assertFalse(service::verify_pkce(str_repeat('a', 42) . ' ', $challenge));
    }

    /**
     * Redirect URI validation matches the OAuth 2.1 rules.
     */
    public function test_redirect_uri_validation(): void {
        $this->assertTrue(service::is_valid_redirect_uri('https://app.example/cb'));
        $this->assertTrue(service::is_valid_redirect_uri('http://localhost/cb'));
        $this->assertTrue(service::is_valid_redirect_uri('http://127.0.0.1:8080/cb'));
        $this->assertTrue(service::is_valid_redirect_uri('com.example.app:/callback'));
        $this->assertFalse(service::is_valid_redirect_uri('http://app.example/cb'));
        $this->assertFalse(service::is_valid_redirect_uri('https://app.example/cb#frag'));
        $this->assertFalse(service::is_valid_redirect_uri('not-a-uri'));
        $this->assertFalse(service::is_valid_redirect_uri(''));
    }

    /**
     * The advertised metadata documents reflect the enabled flow and scopes.
     */
    public function test_metadata_documents(): void {
        $meta = service::authorization_server_metadata();
        $this->assertSame(service::issuer(), $meta['issuer']);
        $this->assertSame(['code'], $meta['response_types_supported']);
        $this->assertSame(['authorization_code'], $meta['grant_types_supported']);
        $this->assertSame(['S256'], $meta['code_challenge_methods_supported']);
        $this->assertArrayHasKey('registration_endpoint', $meta);
        $this->assertContains('mcp:tools.call', $meta['scopes_supported']);

        // Without dynamic registration the endpoint is not advertised.
        set_config('oauth_allow_dynamic_registration', 0, 'webservice_elediamcp');
        $meta = service::authorization_server_metadata();
        $this->assertArrayNotHasKey('registration_endpoint', $meta);
    }

    /**
     * is_enabled tracks the admin setting.
     */
    public function test_is_enabled_toggle(): void {
        $this->assertTrue(service::is_enabled());
        set_config('oauth_enabled', 0, 'webservice_elediamcp');
        $this->assertFalse(service::is_enabled());
    }

    /**
     * Expired code garbage collection removes only expired rows.
     */
    public function test_gc_expired_codes(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $client = $this->register();
        [, $challenge] = $this->pkce_pair();
        $ctx = service::validate_authorization_request($this->auth_params($client['client_id'], $challenge));
        service::issue_code($ctx, (int) $user->id);
        // A second, already-expired code.
        store::create_code(
            store::hash('dead'),
            $client['client_id'],
            (int) $user->id,
            self::REDIRECT,
            $challenge,
            'S256',
            null,
            time() - 100
        );

        $this->assertEquals(2, $DB->count_records(store::CODE_TABLE));
        $removed = store::gc_expired_codes();
        $this->assertEquals(1, $removed);
        $this->assertEquals(1, $DB->count_records(store::CODE_TABLE));
    }

    /**
     * Token issuance fails cleanly when no MCP service is available.
     */
    public function test_token_issuance_without_service(): void {
        $user = $this->getDataGenerator()->create_user();
        $client = $this->register();
        [$verifier, $challenge] = $this->pkce_pair();
        $ctx = service::validate_authorization_request($this->auth_params($client['client_id'], $challenge));
        $code = service::issue_code($ctx, (int) $user->id);

        // Remove all configured MCP services.
        set_config('services', '', 'webservice_elediamcp');

        try {
            service::exchange_code([
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => self::REDIRECT,
                'client_id' => $client['client_id'],
                'code_verifier' => $verifier,
            ]);
            $this->fail('Expected oauth_exception');
        } catch (oauth_exception $ex) {
            $this->assertSame('server_error', $ex->get_error_code());
            $this->assertSame(500, $ex->get_http_status());
        }
    }
}

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

namespace webservice_elediamcp\local\oauth;

use stdClass;
use webservice_elediamcp\local\security;
use webservice_elediamcp\local\token_manager;

/**
 * OAuth 2.1 Authorization Code + PKCE authorization server for the MCP endpoint.
 *
 * The server issues Moodle external tokens (the same bearer credentials the MCP
 * endpoint already accepts) at the end of an Authorization Code + PKCE exchange,
 * so a compliant MCP client can onboard without a human manually minting a token.
 * Every credential the flow produces is a normal MCP service token subject to the
 * existing capability, service-binding and revocation controls.
 *
 * Design constraints enforced here:
 *  - PKCE is mandatory and only the S256 method is accepted (no downgrade to plain).
 *  - Clients are public: no client secret is ever issued or stored.
 *  - Redirect URIs must be pre-registered and are matched exactly.
 *  - Authorization codes are single-use, short-lived, and stored only as hashes.
 *
 * The class is deliberately transport-agnostic: it validates already-parsed input
 * and returns/echoes plain data, so the whole flow is unit-testable without HTTP.
 *
 * @package     webservice_elediamcp
 * @author      Sven (eLeDia) <dev@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class service {
    /** @var string Only PKCE method accepted. */
    public const PKCE_METHOD = 'S256';

    /** @var int Minimum PKCE code verifier/challenge length (RFC 7636). */
    public const PKCE_MIN_LENGTH = 43;

    /** @var int Maximum PKCE code verifier/challenge length (RFC 7636). */
    public const PKCE_MAX_LENGTH = 128;

    /**
     * Whether the OAuth authorization server is enabled by the administrator.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        return security::oauth_enabled();
    }

    /**
     * The OAuth issuer identifier for the MCP authorization server.
     *
     * @return string
     */
    public static function issuer(): string {
        global $CFG;
        return $CFG->wwwroot . '/webservice/elediamcp';
    }

    /**
     * URL of the authorization endpoint.
     *
     * @return string
     */
    public static function authorization_endpoint(): string {
        global $CFG;
        return $CFG->wwwroot . '/webservice/elediamcp/oauth/authorize.php';
    }

    /**
     * URL of the token endpoint.
     *
     * @return string
     */
    public static function token_endpoint(): string {
        global $CFG;
        return $CFG->wwwroot . '/webservice/elediamcp/oauth/token.php';
    }

    /**
     * URL of the dynamic client registration endpoint.
     *
     * @return string
     */
    public static function registration_endpoint(): string {
        global $CFG;
        return $CFG->wwwroot . '/webservice/elediamcp/oauth/register.php';
    }

    /**
     * The scopes advertised by the resource and authorization server.
     *
     * Scopes are advisory in this release: an issued token authenticates as the
     * user and is bound to the MCP service; effective authority is the user's
     * Moodle capabilities, not the requested scope string.
     *
     * @return string[]
     */
    public static function supported_scopes(): array {
        return [
            'mcp:tools.read',
            'mcp:tools.call',
            'mcp:resources.read',
            'mcp:prompts.read',
        ];
    }

    /**
     * Build the RFC 8414 authorization server metadata document.
     *
     * @return array<string, mixed>
     */
    public static function authorization_server_metadata(): array {
        $metadata = [
            'issuer' => self::issuer(),
            'authorization_endpoint' => self::authorization_endpoint(),
            'token_endpoint' => self::token_endpoint(),
            'scopes_supported' => self::supported_scopes(),
            'response_types_supported' => ['code'],
            'response_modes_supported' => ['query'],
            'grant_types_supported' => ['authorization_code'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'code_challenge_methods_supported' => [self::PKCE_METHOD],
            'service_documentation' => self::issuer() . '/configuration.php',
        ];
        if (security::oauth_allow_dynamic_registration()) {
            $metadata['registration_endpoint'] = self::registration_endpoint();
        }
        return $metadata;
    }

    // Dynamic client registration (RFC 7591).

    /**
     * Register a new public OAuth client from a dynamic registration request.
     *
     * @param array $request Decoded registration request body.
     * @return array<string, mixed> The client information response (RFC 7591 §3.2.1).
     * @throws oauth_exception On invalid metadata.
     */
    public static function register_client(array $request): array {
        if (!security::oauth_allow_dynamic_registration()) {
            throw new oauth_exception(
                'invalid_request',
                get_string('oauth_err_registration_disabled', 'webservice_elediamcp'),
                403,
                false
            );
        }

        $redirecturis = $request['redirect_uris'] ?? null;
        if (!is_array($redirecturis) || empty($redirecturis)) {
            throw new oauth_exception(
                'invalid_redirect_uri',
                get_string('oauth_err_redirect_uris_required', 'webservice_elediamcp'),
                400,
                false
            );
        }
        $clean = [];
        foreach ($redirecturis as $uri) {
            if (!is_string($uri) || !self::is_valid_redirect_uri($uri)) {
                throw new oauth_exception(
                    'invalid_redirect_uri',
                    get_string('oauth_err_redirect_uri_invalid', 'webservice_elediamcp'),
                    400,
                    false
                );
            }
            $clean[] = $uri;
        }
        $clean = array_values(array_unique($clean));

        // Only the authorization_code grant with the code response type and PKCE
        // (public client) is supported; reject anything else up front.
        $granttypes = $request['grant_types'] ?? ['authorization_code'];
        if (!is_array($granttypes)) {
            $granttypes = [];
        }
        foreach ($granttypes as $grant) {
            if ($grant !== 'authorization_code') {
                throw new oauth_exception(
                    'invalid_client_metadata',
                    get_string('oauth_err_unsupported_grant_registration', 'webservice_elediamcp'),
                    400,
                    false
                );
            }
        }
        $authmethod = $request['token_endpoint_auth_method'] ?? 'none';
        if ($authmethod !== 'none') {
            throw new oauth_exception(
                'invalid_client_metadata',
                get_string('oauth_err_public_client_only', 'webservice_elediamcp'),
                400,
                false
            );
        }

        $clientname = trim((string) ($request['client_name'] ?? ''));
        if ($clientname === '') {
            $clientname = get_string('oauth_default_client_name', 'webservice_elediamcp');
        }
        $clientname = \core_text::substr($clientname, 0, 255);

        $scope = isset($request['scope']) && is_string($request['scope']) ? trim($request['scope']) : null;

        $record = new stdClass();
        $record->clientid = self::generate_client_id();
        $record->clientname = $clientname;
        $record->redirecturis = implode("\n", $clean);
        $record->granttypes = 'authorization_code';
        $record->responsetypes = 'code';
        $record->tokenendpointauthmethod = 'none';
        $record->scope = ($scope === null || $scope === '') ? null : \core_text::substr($scope, 0, 255);

        $stored = store::create_client($record);

        return [
            'client_id' => $stored->clientid,
            'client_id_issued_at' => (int) $stored->timecreated,
            'client_name' => $stored->clientname,
            'redirect_uris' => $clean,
            'grant_types' => ['authorization_code'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
            'scope' => $stored->scope ?? implode(' ', self::supported_scopes()),
        ];
    }

    // Authorization endpoint.

    /**
     * Validate an authorization request.
     *
     * Errors that occur before the client and redirect URI are known-good are
     * thrown as non-redirectable {@see oauth_exception} (the endpoint MUST render
     * an error page rather than redirect, per RFC 6749 §4.1.2.1). Once the client
     * and redirect URI are trusted, protocol errors are thrown as redirectable so
     * the endpoint can bounce them back to the client.
     *
     * @param array $params The query parameters of the authorization request.
     * @return stdClass Validated context: {client, redirecturi, codechallenge, scope, state}.
     * @throws oauth_exception
     */
    public static function validate_authorization_request(array $params): stdClass {
        $clientid = isset($params['client_id']) ? (string) $params['client_id'] : '';
        if ($clientid === '') {
            throw new oauth_exception(
                'invalid_request',
                get_string('oauth_err_client_id_required', 'webservice_elediamcp'),
                400,
                false
            );
        }
        $client = store::get_client($clientid);
        if ($client === null) {
            throw new oauth_exception(
                'invalid_client',
                get_string('oauth_err_unknown_client', 'webservice_elediamcp'),
                400,
                false
            );
        }

        $registered = self::client_redirect_uris($client);
        $redirecturi = isset($params['redirect_uri']) ? (string) $params['redirect_uri'] : '';
        if ($redirecturi === '') {
            if (count($registered) === 1) {
                $redirecturi = $registered[0];
            } else {
                throw new oauth_exception(
                    'invalid_request',
                    get_string('oauth_err_redirect_uri_required', 'webservice_elediamcp'),
                    400,
                    false
                );
            }
        } else if (!in_array($redirecturi, $registered, true)) {
            throw new oauth_exception(
                'invalid_request',
                get_string('oauth_err_redirect_uri_mismatch', 'webservice_elediamcp'),
                400,
                false
            );
        }

        // From here the redirect URI is trusted: protocol errors are redirectable.
        $state = isset($params['state']) ? (string) $params['state'] : null;

        $responsetype = isset($params['response_type']) ? (string) $params['response_type'] : '';
        if ($responsetype !== 'code') {
            throw new oauth_exception(
                'unsupported_response_type',
                get_string('oauth_err_response_type', 'webservice_elediamcp'),
                400,
                true
            );
        }

        $method = isset($params['code_challenge_method']) ? (string) $params['code_challenge_method'] : '';
        if ($method !== self::PKCE_METHOD) {
            throw new oauth_exception(
                'invalid_request',
                get_string('oauth_err_pkce_method', 'webservice_elediamcp'),
                400,
                true
            );
        }

        $challenge = isset($params['code_challenge']) ? (string) $params['code_challenge'] : '';
        if (!self::is_valid_code_challenge($challenge)) {
            throw new oauth_exception(
                'invalid_request',
                get_string('oauth_err_code_challenge', 'webservice_elediamcp'),
                400,
                true
            );
        }

        $scope = self::filter_scope(isset($params['scope']) ? (string) $params['scope'] : null);

        $ctx = new stdClass();
        $ctx->client = $client;
        $ctx->redirecturi = $redirecturi;
        $ctx->codechallenge = $challenge;
        $ctx->scope = $scope;
        $ctx->state = $state;
        return $ctx;
    }

    /**
     * Issue an authorization code for a validated request and authorising user.
     *
     * @param stdClass $ctx Context returned by {@see self::validate_authorization_request()}.
     * @param int $userid The authenticated Moodle user granting access.
     * @return string The plaintext authorization code (only returned here; stored hashed).
     */
    public static function issue_code(stdClass $ctx, int $userid): string {
        $code = self::random_secret();
        store::create_code(
            store::hash($code),
            (string) $ctx->client->clientid,
            $userid,
            (string) $ctx->redirecturi,
            (string) $ctx->codechallenge,
            self::PKCE_METHOD,
            $ctx->scope,
            time() + security::oauth_code_ttl()
        );
        return $code;
    }

    /**
     * Build the success redirect URL carrying the authorization code.
     *
     * @param string $redirecturi Trusted redirect URI.
     * @param string $code Authorization code.
     * @param string|null $state Opaque client state to echo back.
     * @return string
     */
    public static function build_success_redirect(string $redirecturi, string $code, ?string $state): string {
        $query = ['code' => $code];
        if ($state !== null && $state !== '') {
            $query['state'] = $state;
        }
        return self::append_query($redirecturi, $query);
    }

    /**
     * Build an error redirect URL back to the client.
     *
     * @param string $redirecturi Trusted redirect URI.
     * @param string $error RFC error code.
     * @param string $description Human-readable description.
     * @param string|null $state Opaque client state to echo back.
     * @return string
     */
    public static function build_error_redirect(
        string $redirecturi,
        string $error,
        string $description,
        ?string $state
    ): string {
        $query = ['error' => $error];
        if ($description !== '') {
            $query['error_description'] = $description;
        }
        if ($state !== null && $state !== '') {
            $query['state'] = $state;
        }
        return self::append_query($redirecturi, $query);
    }

    // Token endpoint.

    /**
     * Exchange an authorization code for an access token.
     *
     * @param array $params The POSTed token request parameters.
     * @return array<string, mixed> The token response (RFC 6749 §5.1).
     * @throws oauth_exception On any validation failure.
     */
    public static function exchange_code(array $params): array {
        global $DB;

        $granttype = isset($params['grant_type']) ? (string) $params['grant_type'] : '';
        if ($granttype !== 'authorization_code') {
            throw new oauth_exception(
                'unsupported_grant_type',
                get_string('oauth_err_grant_type', 'webservice_elediamcp'),
                400
            );
        }

        $clientid = isset($params['client_id']) ? (string) $params['client_id'] : '';
        $code = isset($params['code']) ? (string) $params['code'] : '';
        $redirecturi = isset($params['redirect_uri']) ? (string) $params['redirect_uri'] : '';
        $verifier = isset($params['code_verifier']) ? (string) $params['code_verifier'] : '';

        if ($clientid === '' || $code === '' || $redirecturi === '' || $verifier === '') {
            throw new oauth_exception(
                'invalid_request',
                get_string('oauth_err_missing_token_params', 'webservice_elediamcp'),
                400
            );
        }

        $client = store::get_client($clientid);
        if ($client === null) {
            throw new oauth_exception(
                'invalid_client',
                get_string('oauth_err_unknown_client', 'webservice_elediamcp'),
                401
            );
        }

        // Consume the code atomically: read, delete, then validate the in-memory
        // copy. Deleting before validation makes the code single-use even against a
        // stolen code (a wrong verifier still burns it) and closes the replay window.
        $transaction = $DB->start_delegated_transaction();
        $record = store::get_code(store::hash($code));
        if ($record === null) {
            $transaction->allow_commit();
            throw new oauth_exception(
                'invalid_grant',
                get_string('oauth_err_invalid_code', 'webservice_elediamcp'),
                400
            );
        }
        store::delete_code((int) $record->id);
        $transaction->allow_commit();

        if ((int) $record->expires <= time()) {
            throw new oauth_exception(
                'invalid_grant',
                get_string('oauth_err_expired_code', 'webservice_elediamcp'),
                400
            );
        }
        if ((string) $record->clientid !== $clientid) {
            throw new oauth_exception(
                'invalid_grant',
                get_string('oauth_err_code_client_mismatch', 'webservice_elediamcp'),
                400
            );
        }
        if ((string) $record->redirecturi !== $redirecturi) {
            throw new oauth_exception(
                'invalid_grant',
                get_string('oauth_err_redirect_uri_mismatch', 'webservice_elediamcp'),
                400
            );
        }
        if (!self::verify_pkce($verifier, (string) $record->codechallenge)) {
            throw new oauth_exception(
                'invalid_grant',
                get_string('oauth_err_pkce_failed', 'webservice_elediamcp'),
                400
            );
        }

        return self::mint_token_response($record, $client);
    }

    /**
     * Mint an MCP token for a validated code and shape the token response.
     *
     * @param stdClass $record The consumed authorization code row.
     * @param stdClass $client The OAuth client.
     * @return array<string, mixed>
     * @throws oauth_exception When no MCP service is available to issue against.
     */
    protected static function mint_token_response(stdClass $record, stdClass $client): array {
        $serviceid = self::resolve_service_id();
        if ($serviceid === 0) {
            throw new oauth_exception(
                'server_error',
                get_string('oauth_err_no_service', 'webservice_elediamcp'),
                500
            );
        }

        $label = get_string('oauth_token_label', 'webservice_elediamcp', $client->clientname);
        $ttl = security::oauth_token_ttl();
        $validuntil = $ttl > 0 ? time() + $ttl : 0;

        try {
            $result = token_manager::create_token(
                (int) $record->userid,
                $serviceid,
                $label,
                $validuntil,
                null,
                (int) $record->userid
            );
        } catch (\moodle_exception $ex) {
            // The user no longer satisfies the service's required capability, etc.
            // Surface as invalid_grant without leaking Moodle internals.
            throw new oauth_exception(
                'invalid_grant',
                get_string('oauth_err_token_issue', 'webservice_elediamcp'),
                400
            );
        }

        $response = [
            'access_token' => $result->token,
            'token_type' => 'Bearer',
        ];
        if ($ttl > 0) {
            $response['expires_in'] = $ttl;
        }
        if (!empty($record->scope)) {
            $response['scope'] = $record->scope;
        }
        return $response;
    }

    /**
     * Resolve the MCP external service used to issue OAuth tokens.
     *
     * Prefers the dedicated default MCP service; otherwise the first configured,
     * enabled MCP service. Returns 0 when none is available.
     *
     * @return int External service id, or 0.
     */
    public static function resolve_service_id(): int {
        $services = token_manager::get_mcp_services();
        foreach ($services as $service) {
            if ($service->shortname === token_manager::DEFAULT_SERVICE_SHORTNAME) {
                return (int) $service->id;
            }
        }
        foreach ($services as $service) {
            return (int) $service->id;
        }
        return 0;
    }

    // PKCE and helpers.

    /**
     * Verify a PKCE code verifier against a stored S256 challenge.
     *
     * @param string $verifier The client-presented code verifier.
     * @param string $challenge The stored code challenge.
     * @return bool
     */
    public static function verify_pkce(string $verifier, string $challenge): bool {
        $len = strlen($verifier);
        if ($len < self::PKCE_MIN_LENGTH || $len > self::PKCE_MAX_LENGTH) {
            return false;
        }
        if (preg_match('/[^A-Za-z0-9\-._~]/', $verifier)) {
            return false;
        }
        $computed = self::base64url_encode(hash('sha256', $verifier, true));
        return hash_equals($challenge, $computed);
    }

    /**
     * Whether a PKCE code challenge is well-formed (base64url, correct length).
     *
     * @param string $challenge Challenge value.
     * @return bool
     */
    public static function is_valid_code_challenge(string $challenge): bool {
        $len = strlen($challenge);
        if ($len < self::PKCE_MIN_LENGTH || $len > self::PKCE_MAX_LENGTH) {
            return false;
        }
        return (bool) preg_match('/^[A-Za-z0-9\-_]+$/', $challenge);
    }

    /**
     * Validate a redirect URI for registration.
     *
     * Accepts absolute https URIs (any host), http URIs limited to loopback hosts,
     * and private-use / custom-scheme URIs for native clients. Rejects URIs that
     * carry a fragment. This mirrors the OAuth 2.1 redirect URI requirements.
     *
     * @param string $uri Candidate redirect URI.
     * @return bool
     */
    public static function is_valid_redirect_uri(string $uri): bool {
        $uri = trim($uri);
        if ($uri === '' || \core_text::strlen($uri) > 1333) {
            return false;
        }
        if (str_contains($uri, '#')) {
            return false;
        }
        $parts = parse_url($uri);
        if (!is_array($parts) || empty($parts['scheme'])) {
            return false;
        }
        $scheme = strtolower((string) $parts['scheme']);

        if ($scheme === 'https') {
            return !empty($parts['host']);
        }
        if ($scheme === 'http') {
            $host = strtolower((string) ($parts['host'] ?? ''));
            return in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true);
        }
        // Custom / private-use scheme for native apps (e.g. com.example.app:/cb).
        // Require a reverse-domain-style scheme to avoid ambiguous single tokens.
        return (bool) preg_match('/^[a-z][a-z0-9+.\-]*\.[a-z0-9+.\-]+$/', $scheme);
    }

    /**
     * The exact redirect URIs registered for a client.
     *
     * @param stdClass $client Client record.
     * @return string[]
     */
    public static function client_redirect_uris(stdClass $client): array {
        $uris = preg_split('/\R+/', (string) $client->redirecturis, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_values(array_map('trim', $uris));
    }

    /**
     * Reduce a requested scope string to the supported scopes.
     *
     * @param string|null $scope Requested space-separated scope.
     * @return string|null Filtered scope, or null when nothing recognised.
     */
    public static function filter_scope(?string $scope): ?string {
        if ($scope === null || trim($scope) === '') {
            return null;
        }
        $requested = preg_split('/\s+/', trim($scope), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $granted = array_values(array_intersect($requested, self::supported_scopes()));
        return empty($granted) ? null : implode(' ', $granted);
    }

    /**
     * Generate a unique public client identifier.
     *
     * @return string
     */
    public static function generate_client_id(): string {
        do {
            $candidate = 'mcp_' . bin2hex(random_bytes(16));
        } while (store::get_client($candidate) !== null);
        return $candidate;
    }

    /**
     * Generate a high-entropy secret (authorization code value).
     *
     * @return string 64 hex characters (256 bits of entropy).
     */
    public static function random_secret(): string {
        return bin2hex(random_bytes(32));
    }

    /**
     * URL-safe base64 encoding without padding.
     *
     * @param string $binary Raw bytes.
     * @return string
     */
    public static function base64url_encode(string $binary): string {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    /**
     * Append query parameters to a URL, preserving any existing query string.
     *
     * @param string $url Base URL.
     * @param array<string, string> $params Parameters to append.
     * @return string
     */
    protected static function append_query(string $url, array $params): string {
        $separator = str_contains($url, '?') ? '&' : '?';
        return $url . $separator . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
}

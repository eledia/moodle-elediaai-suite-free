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

namespace local_elediaai_chatengine\local;

use cache;
use moodle_exception;

/**
 * Provisions user-scoped Moodle MCP tokens via the webservice_elediamcp API.
 *
 * Strategy: a user keeps at most one connector-provisioned token per configured
 * MCP service. The plain token value (which the MCP plugin returns exactly once,
 * at creation) is held only in a short-lived application cache — never in the
 * database — for the configured token lifetime. While the cached value is valid
 * it is reused across chat turns; when it expires the cache entry is dropped, any
 * stale token rows are revoked, and a fresh token is minted. This keeps the
 * audit trail in the MCP plugin meaningful without persisting the secret.
 *
 * Every provisioned token is attributed to this component ('local_elediaai_chatengine')
 * so the connector's tokens are auditable and independently revocable.
 *
 * @package    local_elediaai_chatengine
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class token_provider {
    /** @var string This plugin's frankenstyle component name. */
    public const COMPONENT = 'local_elediaai_chatengine';

    /** @var string Fully-qualified name of the MCP plugin's internal API. */
    private const MCP_API = '\\webservice_elediamcp\\api';

    /**
     * Whether the required MCP connector plugin is installed and usable.
     *
     * @return bool
     */
    public static function is_connector_available(): bool {
        return class_exists(self::MCP_API);
    }

    /**
     * Assert that the connector is correctly configured, or throw.
     *
     * Used by the external functions to fail fast with an admin-actionable
     * message before any request is attempted.
     *
     * @return void
     * @throws moodle_exception When the connector plugin or service is unavailable.
     */
    public static function require_available(): void {
        if (!self::is_connector_available()) {
            throw new moodle_exception('error_connector_missing', 'local_elediaai_chatengine');
        }
        $serviceid = (int) connection::get('mcpserviceid', 0);
        if ($serviceid <= 0) {
            throw new moodle_exception('error_service_not_configured', 'local_elediaai_chatengine');
        }
        $services = call_user_func([self::MCP_API, 'get_services']);
        if (!isset($services[$serviceid])) {
            throw new moodle_exception('error_service_unavailable', 'local_elediaai_chatengine');
        }
    }

    /**
     * Return a usable plain Moodle MCP token for the given user.
     *
     * Reuses the cached token while it is valid, otherwise mints a fresh one.
     * The returned value is a secret: pass it straight to the RAG client and
     * never expose, log or persist it.
     *
     * @param int $userid The owner the token authenticates as.
     * @return string The plain token value.
     * @throws moodle_exception When provisioning fails.
     */
    public static function get_token(int $userid): string {
        self::require_available();
        $serviceid = (int) connection::get('mcpserviceid', 0);

        $cache = cache::make('local_elediaai_chatengine', 'usertoken');
        $cachekey = $userid . '_' . $serviceid;

        $cached = $cache->get($cachekey);
        if (is_array($cached) && !empty($cached['token']) && (int) $cached['expiry'] > time() + 30) {
            return (string) $cached['token'];
        }

        return self::mint_token($userid, $serviceid, $cache, $cachekey);
    }

    /**
     * Mint a fresh token, revoking any stale connector tokens first.
     *
     * @param int $userid The owner.
     * @param int $serviceid The MCP service id.
     * @param cache $cache The usertoken cache.
     * @param string $cachekey The cache key.
     * @return string The new plain token value.
     * @throws moodle_exception When provisioning fails.
     */
    private static function mint_token(int $userid, int $serviceid, cache $cache, string $cachekey): string {
        // Revoke any previous connector tokens for this user/service so we do not
        // accumulate orphaned credentials whose plain value we can no longer reach.
        try {
            call_user_func([self::MCP_API, 'revoke_user_service_tokens'], self::COMPONENT, $userid, $serviceid);
        } catch (moodle_exception $e) {
            // Non-fatal: continue to mint a fresh token.
            debugging('local_elediaai_chatengine: revoke of stale tokens failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        $lifetime = max(0, (int) connection::get('tokenlifetime', 3600));
        $validuntil = $lifetime > 0 ? time() + $lifetime : 0;
        $label = get_string('tokenlabel', 'local_elediaai_chatengine');

        $result = call_user_func(
            [self::MCP_API, 'create_token'],
            self::COMPONENT,
            $userid,
            $serviceid,
            $label,
            $validuntil
        );

        $token = (string) ($result->token ?? '');
        if ($token === '') {
            throw new moodle_exception('error_token_provision_failed', 'local_elediaai_chatengine');
        }

        // Cache the secret for (almost) its lifetime; bound by the cache TTL.
        $expiry = $validuntil > 0 ? $validuntil : time() + 3600;
        $cache->set($cachekey, ['token' => $token, 'expiry' => $expiry]);

        \local_elediaai_chatengine\event\token_provisioned::create([
            'context' => \core\context\system::instance(),
            'relateduserid' => $userid,
            'other' => ['serviceid' => $serviceid],
        ])->trigger();

        return $token;
    }

    /**
     * Drop a user's cached token (e.g. after the RAG server reports it invalid).
     *
     * Forces the next request to mint a fresh credential. Handles graceful
     * recovery from expiry/revocation on the MCP side.
     *
     * @param int $userid The owner.
     * @return void
     */
    public static function forget_cached_token(int $userid): void {
        $serviceid = (int) connection::get('mcpserviceid', 0);
        $cache = cache::make('local_elediaai_chatengine', 'usertoken');
        $cache->delete($userid . '_' . $serviceid);
    }
}

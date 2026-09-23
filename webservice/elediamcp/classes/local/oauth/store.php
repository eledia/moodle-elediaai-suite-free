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

/**
 * Persistence for OAuth client registrations and authorization codes.
 *
 * This class owns the {webservice_elediamcp_oauth_client} and
 * {webservice_elediamcp_oauth_code} tables. Authorization codes are stored only
 * as SHA-256 hashes; the plaintext value never touches the database.
 *
 * @package     webservice_elediamcp
 * @author      Sven (eLeDia) <dev@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class store {
    /** @var string Client registration table. */
    public const CLIENT_TABLE = 'webservice_elediamcp_oauth_client';

    /** @var string Authorization code table. */
    public const CODE_TABLE = 'webservice_elediamcp_oauth_code';

    /**
     * Hash a code or client secret value for storage/lookup.
     *
     * @param string $value Plaintext value.
     * @return string SHA-256 hex digest.
     */
    public static function hash(string $value): string {
        return hash('sha256', $value);
    }

    /**
     * Persist a new OAuth client registration.
     *
     * @param stdClass $client Client record (without id/timestamps).
     * @return stdClass The stored record, with id and timestamps set.
     */
    public static function create_client(stdClass $client): stdClass {
        global $DB;
        $now = time();
        $client->timecreated = $now;
        $client->timemodified = $now;
        $client->id = $DB->insert_record(self::CLIENT_TABLE, $client);
        return $client;
    }

    /**
     * Fetch a client by its issued client identifier.
     *
     * @param string $clientid Public client identifier.
     * @return stdClass|null
     */
    public static function get_client(string $clientid): ?stdClass {
        global $DB;
        $record = $DB->get_record(self::CLIENT_TABLE, ['clientid' => $clientid]);
        return $record ?: null;
    }

    /**
     * Store an authorization code binding.
     *
     * @param string $codehash SHA-256 hex digest of the code value.
     * @param string $clientid Client the code was issued to.
     * @param int $userid Authorising user.
     * @param string $redirecturi Exact redirect URI from the request.
     * @param string $codechallenge PKCE code challenge.
     * @param string $codechallengemethod PKCE method (S256).
     * @param string|null $scope Approved scope, or null.
     * @param int $expires Expiry timestamp.
     * @return int New row id.
     */
    public static function create_code(
        string $codehash,
        string $clientid,
        int $userid,
        string $redirecturi,
        string $codechallenge,
        string $codechallengemethod,
        ?string $scope,
        int $expires
    ): int {
        global $DB;
        return (int) $DB->insert_record(self::CODE_TABLE, (object) [
            'codehash' => $codehash,
            'clientid' => $clientid,
            'userid' => $userid,
            'redirecturi' => $redirecturi,
            'codechallenge' => $codechallenge,
            'codechallengemethod' => $codechallengemethod,
            'scope' => ($scope === null || $scope === '') ? null : $scope,
            'expires' => $expires,
            'timecreated' => time(),
        ]);
    }

    /**
     * Fetch an authorization code row by its hash.
     *
     * @param string $codehash SHA-256 hex digest of the presented code.
     * @return stdClass|null
     */
    public static function get_code(string $codehash): ?stdClass {
        global $DB;
        $record = $DB->get_record(self::CODE_TABLE, ['codehash' => $codehash]);
        return $record ?: null;
    }

    /**
     * Delete a single authorization code row.
     *
     * @param int $id Row id.
     * @return void
     */
    public static function delete_code(int $id): void {
        global $DB;
        $DB->delete_records(self::CODE_TABLE, ['id' => $id]);
    }

    /**
     * Delete expired authorization codes.
     *
     * @param int|null $now Reference timestamp, defaults to the current time.
     * @return int Number of rows deleted.
     */
    public static function gc_expired_codes(?int $now = null): int {
        global $DB;
        $now = $now ?? time();
        $count = $DB->count_records_select(self::CODE_TABLE, 'expires < :now', ['now' => $now]);
        if ($count > 0) {
            $DB->delete_records_select(self::CODE_TABLE, 'expires < :now', ['now' => $now]);
        }
        return $count;
    }
}

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
 * This class owns the {webservice_elediamcp_oauth_client}, {webservice_elediamcp_oauth_code}
 * and {webservice_elediamcp_refresh} tables. Authorization codes and refresh tokens are
 * stored only as SHA-256 hashes; the plaintext value never touches the database.
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

    /** @var string Refresh token table. */
    public const REFRESH_TABLE = 'webservice_elediamcp_refresh';

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
     * Note that a person allowed access for a client.
     *
     * @param string $clientid Public client identifier.
     * @param int|null $now Reference timestamp, defaults to the current time.
     * @return void
     */
    public static function touch_client(string $clientid, ?int $now = null): void {
        global $DB;
        $DB->set_field(self::CLIENT_TABLE, 'timelastused', $now ?? time(), ['clientid' => $clientid]);
    }

    /**
     * Delete clients nobody ever used that were registered before the cutoff.
     *
     * A client counts as used once a person has allowed access for it; those are kept.
     *
     * @param int $cutoff Registrations before this timestamp are deleted.
     * @return int Number of clients deleted.
     */
    public static function gc_unused_clients(int $cutoff): int {
        global $DB;
        $select = 'timelastused IS NULL AND timecreated < :cutoff';
        $count = $DB->count_records_select(self::CLIENT_TABLE, $select, ['cutoff' => $cutoff]);
        if ($count > 0) {
            $DB->delete_records_select(self::CLIENT_TABLE, $select, ['cutoff' => $cutoff]);
        }
        return $count;
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

    // Refresh tokens (#248).

    /**
     * Store a refresh token of a family.
     *
     * @param string $tokenhash SHA-256 hex digest of the token value.
     * @param string $familyid Family of the sign-in.
     * @param string $clientid Client the token is bound to.
     * @param int $userid Person the token is bound to.
     * @param string|null $scope Scope of the access token.
     * @param int $familyexpires Absolute end of the family.
     * @return int New row id.
     */
    public static function create_refresh(
        string $tokenhash,
        string $familyid,
        string $clientid,
        int $userid,
        ?string $scope,
        int $familyexpires
    ): int {
        global $DB;
        return (int) $DB->insert_record(self::REFRESH_TABLE, (object) [
            'tokenhash' => $tokenhash,
            'familyid' => $familyid,
            'clientid' => $clientid,
            'userid' => $userid,
            'scope' => ($scope === null || $scope === '') ? null : $scope,
            'familyexpires' => $familyexpires,
            'timecreated' => time(),
            'timeused' => null,
            'revoked' => 0,
        ]);
    }

    /**
     * Fetch a refresh token row by its hash.
     *
     * @param string $tokenhash SHA-256 hex digest of the presented value.
     * @return stdClass|null
     */
    public static function get_refresh(string $tokenhash): ?stdClass {
        global $DB;
        $record = $DB->get_record(self::REFRESH_TABLE, ['tokenhash' => $tokenhash]);
        return $record ?: null;
    }

    /**
     * The current refresh token of a family: the one not used yet, in a live family.
     *
     * @param string $familyid Family id.
     * @return stdClass|null
     */
    public static function get_current_refresh(string $familyid): ?stdClass {
        global $DB;
        $records = $DB->get_records_select(
            self::REFRESH_TABLE,
            'familyid = :familyid AND timeused IS NULL AND revoked = 0',
            ['familyid' => $familyid],
            'id DESC',
            '*',
            0,
            1
        );
        return $records ? reset($records) : null;
    }

    /**
     * Whether a used refresh token is the one of its family that was used last.
     *
     * @param stdClass $row A used refresh token row.
     * @return bool
     */
    public static function is_last_used_refresh(stdClass $row): bool {
        global $DB;
        $last = $DB->get_field_select(
            self::REFRESH_TABLE,
            'MAX(id)',
            'familyid = :familyid AND timeused IS NOT NULL',
            ['familyid' => $row->familyid]
        );
        return (int) $last === (int) $row->id;
    }

    /**
     * Note that a refresh token was exchanged.
     *
     * @param int $id Row id.
     * @param int|null $now Reference timestamp.
     * @return void
     */
    public static function mark_refresh_used(int $id, ?int $now = null): void {
        global $DB;
        $DB->set_field(self::REFRESH_TABLE, 'timeused', $now ?? time(), ['id' => $id]);
    }

    /**
     * End a family: none of its refresh tokens can be used any more.
     *
     * @param string $familyid Family id.
     * @return void
     */
    public static function end_family(string $familyid): void {
        global $DB;
        $DB->set_field(self::REFRESH_TABLE, 'revoked', 1, ['familyid' => $familyid]);
    }

    /**
     * Delete the refresh tokens of families that are over, and the tokens of ended families.
     *
     * @param int|null $now Reference timestamp, defaults to the current time.
     * @return int Number of rows deleted.
     */
    public static function gc_refresh(?int $now = null): int {
        global $DB;
        $now = $now ?? time();
        $select = 'familyexpires < :now1 OR (revoked = 1 AND timecreated < :cutoff)';
        $params = ['now1' => $now, 'cutoff' => $now - DAYSECS];
        $count = $DB->count_records_select(self::REFRESH_TABLE, $select, $params);
        if ($count > 0) {
            $DB->delete_records_select(self::REFRESH_TABLE, $select, $params);
        }
        return $count;
    }
}

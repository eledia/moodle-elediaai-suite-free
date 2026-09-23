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

/**
 * The tutor's auto-provisioned maintenance (service) account.
 *
 * Site-level RAG calls (currently only the nightly topic reclustering) carry a
 * Moodle MCP token like every other tool call, so the RAG server can verify
 * them by calling back into Moodle — no shared transport secret required. The
 * token belongs to this dedicated account, NOT to an administrator: it is
 * created on demand with webservice-only auth (no interactive login), no role
 * assignments and no enrolments, so the credential is deliberately near
 * powerless inside Moodle.
 *
 * @package    local_elediaai_chatengine
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class service_user {
    /** @var string Fixed username of the maintenance account. */
    public const USERNAME = 'elediaai_tutor_service';

    /**
     * Fetch the maintenance account, creating it on first use.
     *
     * @return \stdClass The user record.
     */
    public static function get_or_create(): \stdClass {
        global $CFG, $DB;

        $existing = $DB->get_record('user', [
            'username' => self::USERNAME,
            'mnethostid' => $CFG->mnet_localhost_id,
            'deleted' => 0,
        ]);
        if ($existing) {
            return $existing;
        }

        require_once($CFG->dirroot . '/user/lib.php');
        $host = parse_url($CFG->wwwroot, PHP_URL_HOST) ?: 'localhost';
        $record = (object) [
            'username' => self::USERNAME,
            // Webservice-only auth: the account can never log in interactively.
            'auth' => 'webservice',
            'firstname' => 'eLeDia.ai Tutor',
            'lastname' => 'Service',
            'email' => self::USERNAME . '@' . $host,
            'confirmed' => 1,
            'policyagreed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
        ];

        try {
            $userid = user_create_user($record, false, true);
        } catch (\Throwable $e) {
            // Lost a race against a concurrent run: use the winner's account.
            $existing = $DB->get_record('user', [
                'username' => self::USERNAME,
                'mnethostid' => $CFG->mnet_localhost_id,
                'deleted' => 0,
            ]);
            if ($existing) {
                return $existing;
            }
            throw $e;
        }

        return \core_user::get_user($userid, '*', MUST_EXIST);
    }
}

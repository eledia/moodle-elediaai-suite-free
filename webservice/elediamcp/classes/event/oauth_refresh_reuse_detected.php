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

namespace webservice_elediamcp\event;

use core\event\base;

/**
 * Event fired when a refresh token that was already used is presented again: the whole family is ended (#248).
 *
 * No token value is part of the event, only the ids that tie it to the sign-in.
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class oauth_refresh_reuse_detected extends base {
    /**
     * Initialise event data.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'webservice_elediamcp_token';
    }

    /**
     * Return the event name.
     *
     * @return string Localised event name.
     */
    public static function get_name(): string {
        return get_string('event_oauth_refresh_reuse_detected', 'webservice_elediamcp');
    }

    /**
     * Return a human-readable description of the event.
     *
     * @return string
     */
    public function get_description(): string {
        $a = (object) [
            'relateduserid' => $this->relateduserid,
            'clientid' => $this->other['clientid'] ?? '',
            'tokenid' => $this->objectid,
        ];
        return get_string('event_oauth_refresh_reuse_detected_desc', 'webservice_elediamcp', $a);
    }

    /**
     * Validate the event data before dispatch.
     *
     * @return void
     */
    protected function validate_data(): void {
        parent::validate_data();
        if (empty($this->objectid)) {
            throw new \coding_exception('oauth_refresh_reuse_detected event requires objectid (the token row)');
        }
        if (empty($this->relateduserid)) {
            throw new \coding_exception('oauth_refresh_reuse_detected event requires relateduserid (the token owner)');
        }
        if (!isset($this->other['clientid'])) {
            throw new \coding_exception('oauth_refresh_reuse_detected event requires other[clientid]');
        }
    }
}

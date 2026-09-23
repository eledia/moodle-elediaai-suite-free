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

/**
 * What this plugin reports about its own state.
 *
 * @package    webservice_elediamcp
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace webservice_elediamcp\elediaai_core;

use local_elediaai_core\health\check;
use local_elediaai_core\health\health_provider as health_provider_contract;
use moodle_url;

/**
 * The MCP service answers for its own wiring.
 */
final class health_provider implements health_provider_contract {
    /** @var string This component. */
    private const COMPONENT = 'webservice_elediamcp';

    #[\Override]
    public static function get_checks(): array {
        if (!class_exists(check::class)) {
            return [];
        }

        global $CFG;

        // Ohne Moodles Webservices geht gar nichts -- das ist die Frage vor
        // allen anderen, und sie liegt nicht in diesem Plugin.
        if (empty($CFG->enablewebservices)) {
            return [
                new check(
                    id: 'webservices',
                    component: self::COMPONENT,
                    label: get_string('health_webservices', self::COMPONENT),
                    status: check::STATUS_UNCONFIGURED,
                    detail: get_string('health_webservices_off', self::COMPONENT),
                    actionurl: new moodle_url('/admin/search.php', ['query' => 'enablewebservices']),
                    actionlabel: get_string('health_configure', self::COMPONENT),
                ),
            ];
        }

        $dienste = array_filter(array_map(
            'trim',
            explode(',', (string) get_config('webservice_elediamcp', 'services'))
        ));

        return [
            new check(
                id: 'services',
                component: self::COMPONENT,
                label: get_string('health_services', self::COMPONENT),
                status: $dienste !== [] ? check::STATUS_OK : check::STATUS_UNCONFIGURED,
                detail: $dienste !== []
                    ? get_string('health_services_ok', self::COMPONENT, count($dienste))
                    : get_string('health_services_none', self::COMPONENT),
                actionurl: new moodle_url('/webservice/elediamcp/configuration.php'),
                actionlabel: get_string('health_configure', self::COMPONENT),
            ),
        ];
    }
}

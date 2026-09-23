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
 * eLeDia.ai feature provider for webservice_elediamcp.
 *
 * @package    webservice_elediamcp
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace webservice_elediamcp\elediaai_core;

defined('MOODLE_INTERNAL') || die();

use local_elediaai_core\feature\descriptor;
use local_elediaai_core\feature\registry;
use local_elediaai_core\feature\feature_provider as feature_provider_contract;
use moodle_url;

/**
 * Announces this plugin to the AI Suite dashboard.
 *
 * The suite is only a soft dependency: without local_elediaai_core the
 * descriptor class is absent, the registry that would load this file does not
 * exist either, and get_descriptors() degrades to an empty result.
 */
final class feature_provider implements feature_provider_contract {
    #[\Override]
    public static function get_descriptors(): array {
        if (!class_exists(descriptor::class)) {
            return [];
        }

        return [
            new descriptor(
                id: 'mcp',
                kind: descriptor::KIND_PAGE,
                component: 'webservice_elediamcp',
                name: get_string('suite_feature_name', 'webservice_elediamcp'),
                description: get_string('suite_feature_desc', 'webservice_elediamcp'),
                launchurl: new moodle_url('/webservice/elediamcp/configuration.php'),
                icon: 'plug',
                audience: registry::AUDIENCE_ADMIN,
                imagename: 'feature_elediamcp',
                capability: null,
                configurl: new moodle_url('/admin/settings.php', ['section' => 'webservice_elediamcp']),
                detaildescription: get_string('suite_feature_detail', 'webservice_elediamcp'),
                keyfeatures: [
                    get_string('suite_feature_key_1', 'webservice_elediamcp'),
                    get_string('suite_feature_key_2', 'webservice_elediamcp'),
                    get_string('suite_feature_key_3', 'webservice_elediamcp'),
                ],
            ),
        ];
    }
}

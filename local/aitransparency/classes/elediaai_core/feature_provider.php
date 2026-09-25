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
 * eLeDia.ai feature provider for local_aitransparency.
 *
 * @package    local_aitransparency
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aitransparency\elediaai_core;

use local_elediaai_core\feature\descriptor;
use local_elediaai_core\feature\registry;
use local_elediaai_core\feature\feature_provider as feature_provider_contract;
use moodle_url;

/**
 * Announces the provenance report to the AI Suite launcher.
 *
 * Without it the plugin was installed and working but absent from the
 * launcher, and on a customer system that read as "not installed" (#30).
 * The tile is for the people who may read the report: the capability is the
 * report's own, so the launcher and report.php cannot disagree.
 */
final class feature_provider implements feature_provider_contract {
    #[\Override]
    public static function get_descriptors(): array {
        if (!class_exists(descriptor::class)) {
            return [];
        }

        return [
            new descriptor(
                id: 'aitransparency',
                kind: descriptor::KIND_PAGE,
                component: 'local_aitransparency',
                name: get_string('suite_feature_name', 'local_aitransparency'),
                description: get_string('suite_feature_desc', 'local_aitransparency'),
                launchurl: new moodle_url('/local/aitransparency/report.php'),
                icon: 'shield',
                audience: registry::AUDIENCE_ADMIN,
                imagename: 'feature_aitransparency',
                capability: 'local/aitransparency:viewreport',
                configurl: new moodle_url('/admin/settings.php', ['section' => 'local_aitransparency']),
                detaildescription: get_string('suite_feature_detail', 'local_aitransparency'),
                keyfeatures: [
                    get_string('suite_feature_key_1', 'local_aitransparency'),
                    get_string('suite_feature_key_2', 'local_aitransparency'),
                    get_string('suite_feature_key_3', 'local_aitransparency'),
                ],
            ),
        ];
    }
}

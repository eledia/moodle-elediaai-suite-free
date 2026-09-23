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
 * LernHive AI Suite feature provider for the eLeDia.ai Tutor block.
 *
 * @package     block_elediaai_tutor
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_elediaai_tutor\elediaai_core;

use local_elediaai_core\feature\descriptor;
use local_elediaai_core\feature\registry;
use local_elediaai_core\feature\feature_provider as feature_provider_contract;
use moodle_url;

/**
 * Registers the installed tutor block with the central AI Suite launcher.
 */
final class feature_provider implements feature_provider_contract {
    #[\Override]
    public static function get_descriptors(): array {
        return [
            new descriptor(
                id: 'tutor',
                kind: descriptor::KIND_PAGE,
                component: 'block_elediaai_tutor',
                name: get_string('suite_feature_name', 'block_elediaai_tutor'),
                description: get_string('suite_feature_desc', 'block_elediaai_tutor'),
                launchurl: new moodle_url('/blocks/elediaai_tutor/view.php'),
                icon: 'graduation-cap',
                audience: registry::AUDIENCE_STUDENT,
                imagename: 'feature_tutor',
                capability: null,
                configurl: new moodle_url('/local/elediaai_core/health.php'),
                comingsoon: false,
                detaildescription: get_string('suite_feature_detail', 'block_elediaai_tutor'),
                keyfeatures: [
                    get_string('suite_feature_key_1', 'block_elediaai_tutor'),
                    get_string('suite_feature_key_2', 'block_elediaai_tutor'),
                    get_string('suite_feature_key_3', 'block_elediaai_tutor'),
                ],
            ),
        ];
    }
}

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
 * Built-in "coming soon" descriptors so the launcher always shows the
 * full roadmap even when the implementing plugins do not exist yet.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core\elediaai_core;

defined('MOODLE_INTERNAL') || die();

use local_elediaai_core\feature\descriptor;
use local_elediaai_core\feature\registry;
use local_elediaai_core\feature\feature_provider as feature_provider_contract;
use local_elediaai_core\local\audit_config;
use core_component;
use moodle_url;

/**
 * Self-registers the three roadmap items the suite intends to ship
 * next (Course Generator, Translate, Tutor) as `comingsoon: true`
 * descriptors. Once the real plugins land they will provide their
 * own feature_provider classes with the same id; the launcher then
 * shows them as fully-functional tiles automatically.
 */
final class feature_provider implements feature_provider_contract {
    #[\Override]
    public static function get_descriptors(): array {
        $descriptors = [
            // Audit tile — only visible to users with the core_ai usage
            // capability. Reuses Moodle's own usage system report and
            // surfaces it from the AI Suite so admins don't have to dig
            // through Site administration → AI to find it.
            new descriptor(
                id: 'audit',
                kind: descriptor::KIND_PAGE,
                component: 'local_elediaai_core',
                name: get_string('feature_audit_name', 'local_elediaai_core'),
                description: get_string('feature_audit_desc', 'local_elediaai_core'),
                launchurl: new moodle_url('/local/elediaai_core/audit.php'),
                icon: 'clipboard-check',
                audience: registry::AUDIENCE_ADMIN,
                imagename: 'feature_audit',
                capability: 'moodle/ai:viewaiusagereport',
                configurl: new moodle_url('/local/elediaai_core/audit_settings.php'),
                detaildescription: get_string('feature_audit_detail', 'local_elediaai_core'),
                keyfeatures: [
                    get_string('feature_audit_key_1', 'local_elediaai_core'),
                    get_string('feature_audit_key_2', 'local_elediaai_core'),
                    get_string('feature_audit_key_3', 'local_elediaai_core'),
                ],
            ),
            new descriptor(
                id: 'coursegen',
                kind: descriptor::KIND_PAGE,
                component: 'local_elediaai_core',
                name: get_string('feature_coursegen_name', 'local_elediaai_core'),
                // The course author exists (local_elediaai_coursegen); when it
                // is missing here it is installable, not "coming soon" (#31).
                description: get_string('feature_coursegen_install_desc', 'local_elediaai_core'),
                launchurl: null,
                icon: 'book',
                audience: registry::AUDIENCE_DESIGNER,
                imagename: 'feature_coursegen',
                capability: 'moodle/site:config',
                configurl: null,
                comingsoon: true,
                installrequired: true,
                installurl: new moodle_url('/admin/tool/installaddon/index.php'),
                detaildescription: get_string('feature_coursegen_detail', 'local_elediaai_core'),
                keyfeatures: [
                    get_string('feature_coursegen_key_1', 'local_elediaai_core'),
                    get_string('feature_coursegen_key_2', 'local_elediaai_core'),
                    get_string('feature_coursegen_key_3', 'local_elediaai_core'),
                ],
            ),
            new descriptor(
                id: 'translate',
                kind: descriptor::KIND_PAGE,
                component: 'local_elediaai_core',
                name: get_string('feature_translate_name', 'local_elediaai_core'),
                description: self::translate_plugin_available()
                    ? get_string('feature_translate_desc', 'local_elediaai_core')
                    : get_string('feature_translate_install_desc', 'local_elediaai_core'),
                launchurl: null,
                icon: 'languages',
                audience: registry::AUDIENCE_ADMIN,
                imagename: 'feature_translate',
                capability: self::translate_plugin_available() ? null : 'moodle/site:config',
                configurl: null,
                comingsoon: true,
                detaildescription: get_string('feature_translate_detail', 'local_elediaai_core'),
                installrequired: !self::translate_plugin_available(),
                installurl: new moodle_url('/admin/tool/installaddon/index.php'),
                keyfeatures: [
                    get_string('feature_translate_key_1', 'local_elediaai_core'),
                    get_string('feature_translate_key_2', 'local_elediaai_core'),
                    get_string('feature_translate_key_3', 'local_elediaai_core'),
                ],
            ),
            new descriptor(
                id: 'tutor',
                kind: descriptor::KIND_PAGE,
                component: 'local_elediaai_core',
                name: get_string('feature_tutor_name', 'local_elediaai_core'),
                description: self::tutor_plugin_available()
                    ? get_string('feature_tutor_desc', 'local_elediaai_core')
                    : get_string('feature_tutor_install_desc', 'local_elediaai_core'),
                launchurl: null,
                icon: 'graduation-cap',
                audience: registry::AUDIENCE_STUDENT,
                imagename: 'feature_tutor',
                capability: self::tutor_plugin_available() ? null : 'moodle/site:config',
                configurl: null,
                comingsoon: true,
                detaildescription: get_string('feature_tutor_detail', 'local_elediaai_core'),
                installrequired: !self::tutor_plugin_available(),
                installurl: new moodle_url('/admin/tool/installaddon/index.php'),
                keyfeatures: [
                    get_string('feature_tutor_key_1', 'local_elediaai_core'),
                    get_string('feature_tutor_key_2', 'local_elediaai_core'),
                    get_string('feature_tutor_key_3', 'local_elediaai_core'),
                ],
            ),
        ];

        // Hide the audit tile on Moodle < 5.0, where core ships neither the
        // AI usage register the report reads nor its viewaiusagereport
        // capability. Everything else in the suite works there unchanged.
        if (!audit_config::feature_available()) {
            $descriptors = array_values(array_filter(
                $descriptors,
                static fn(descriptor $descriptor): bool => $descriptor->id !== 'audit'
            ));
        }

        return $descriptors;
    }

    /**
     * Whether a standalone translate plugin is present in this Moodle codebase.
     *
     * @return bool
     */
    private static function translate_plugin_available(): bool {
        return core_component::get_component_directory('filter_eledia_translate') !== null
            || core_component::get_component_directory('filter_eledia_translate') !== null;
    }

    /**
     * Whether the standalone eLeDia.ai Tutor block is present.
     *
     * @return bool
     */
    private static function tutor_plugin_available(): bool {
        return core_component::get_component_directory('block_elediaai_tutor') !== null;
    }
}

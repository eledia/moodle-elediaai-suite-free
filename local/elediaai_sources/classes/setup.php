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

namespace local_elediaai_sources;

use core_customfield\category_controller;
use core_customfield\field_controller;

/**
 * Install/upgrade setup helpers.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setup {
    /**
     * Create or update the per-course AI Sources override field.
     *
     * Idempotent: safe to call from both install and upgrade. The field is a
     * three-option select (Default / Include / Exclude) read by
     * {@see course_gate::override()}; the option tokens are stable identifiers,
     * not display strings, so they must not be translated.
     *
     * @return void
     */
    public static function ensure_course_field(): void {
        global $DB;

        $fieldid = $DB->get_field('customfield_field', 'id', ['shortname' => course_gate::FIELD]);
        if ($fieldid) {
            self::update_course_field_labels((int) $fieldid);
            return;
        }

        $handler = \core_course\customfield\course_handler::create();

        // Reuse our category if a previous partial run created it, else make it.
        $categoryid = 0;
        foreach ($handler->get_categories_with_fields() as $category) {
            if ($category->get('name') === self::category_name()) {
                $categoryid = $category->get('id');
                break;
            }
        }
        if ($categoryid === 0) {
            $categoryid = $handler->create_category(self::category_name());
        }

        $catcontroller = category_controller::create($categoryid);
        $field = field_controller::create(0, (object) ['type' => 'select'], $catcontroller);

        $record = (object) [
            'name' => get_string('cffieldname', 'local_elediaai_sources'),
            'shortname' => course_gate::FIELD,
            'type' => 'select',
            'description' => get_string('cffielddesc', 'local_elediaai_sources'),
            'descriptionformat' => FORMAT_HTML,
            'configdata' => [
                'options' => implode("\n", [
                    course_gate::OVERRIDE_DEFAULT,
                    course_gate::OVERRIDE_INCLUDE,
                    course_gate::OVERRIDE_EXCLUDE,
                ]),
                'defaultvalue' => course_gate::OVERRIDE_DEFAULT,
                'required' => 0,
                'uniquevalues' => 0,
                'locked' => 0,
                'visibility' => 2,
            ],
        ];

        $handler->save_field_configuration($field, $record);
    }

    /**
     * Update display labels for an existing course override field.
     *
     * @param int $fieldid Custom field ID.
     * @return void
     */
    private static function update_course_field_labels(int $fieldid): void {
        $field = field_controller::create($fieldid);
        $name = get_string('cffieldname', 'local_elediaai_sources');
        $description = get_string('cffielddesc', 'local_elediaai_sources');

        if ($field->get('name') === $name && $field->get('description') === $description) {
            return;
        }

        $rawconfig = $field->get('configdata');
        $configdata = is_array($rawconfig) ? $rawconfig : (json_decode((string) $rawconfig, true) ?: []);

        $record = (object) [
            'name' => $name,
            'shortname' => $field->get('shortname'),
            'type' => $field->get('type'),
            'description' => $description,
            'descriptionformat' => FORMAT_HTML,
            'configdata' => $configdata,
        ];

        $field->get_handler()->save_field_configuration($field, $record);
    }

    /**
     * Lock or unlock the per-course override field.
     *
     * When locked, editing the field requires
     * `moodle/course:changelockedcustomfields` (managers/admins only), so
     * teachers can no longer change a course's ingestion marking — while the
     * value stays visible (visibility is kept at "everyone"). Used for the
     * test-phase lock-down (see the `lockcoursemarking` admin setting).
     *
     * No-op when the field does not exist yet.
     *
     * @param bool $locked Whether the field should be locked.
     * @return void
     */
    public static function apply_field_state(bool $locked): void {
        global $DB;

        $fieldid = $DB->get_field('customfield_field', 'id', ['shortname' => course_gate::FIELD]);
        if (!$fieldid) {
            return;
        }

        $field = field_controller::create((int) $fieldid);
        $raw = $field->get('configdata');
        $configdata = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);

        if ((int) ($configdata['locked'] ?? 0) === ($locked ? 1 : 0)) {
            return; // Already in the desired state.
        }
        $configdata['locked'] = $locked ? 1 : 0;
        $configdata['visibility'] = $configdata['visibility'] ?? 2;

        $record = (object) [
            'name' => $field->get('name'),
            'shortname' => $field->get('shortname'),
            'type' => $field->get('type'),
            'description' => $field->get('description'),
            'descriptionformat' => FORMAT_HTML,
            'configdata' => $configdata,
        ];

        $field->get_handler()->save_field_configuration($field, $record);
    }

    /**
     * Lock the field if (and only if) the test-phase setting is enabled.
     *
     * @return void
     */
    public static function sync_field_lock(): void {
        self::apply_field_state((int) get_config('local_elediaai_sources', 'lockcoursemarking') === 1);
    }

    /**
     * The custom-field category name (also used to locate it on re-runs).
     *
     * @return string
     */
    private static function category_name(): string {
        return get_string('cfcategory', 'local_elediaai_sources');
    }
}

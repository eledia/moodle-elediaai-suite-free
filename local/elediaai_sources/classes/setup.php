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
     * Encrypt a plaintext Ingestion API key stored before ai_suite#252.
     *
     * The setting became an admin_setting_encryptedpassword in 1.6.1. A key
     * saved earlier sits in config_plugins as plaintext; this re-saves it as
     * ciphertext with the site key, the same way the setting itself would.
     *
     * Idempotent: an empty value stays empty, and a value that already carries
     * the encryption method prefix is left alone, so a second run does not
     * encrypt the ciphertext again.
     *
     * @return bool True when a plaintext value was encrypted.
     */
    public static function encrypt_stored_apikey(): bool {
        $stored = get_config('local_elediaai_sources', 'sink_ingestionapi_apikey');
        if ($stored === false) {
            return false;
        }
        $plain = trim((string) $stored);
        if ($plain === '') {
            return false;
        }
        if (str_starts_with($plain, \core\encryption::METHOD_SODIUM . ':')) {
            return false;
        }
        set_config('sink_ingestionapi_apikey', \core\encryption::encrypt($plain), 'local_elediaai_sources');
        return true;
    }

    /**
     * Create or update the per-course AI Sources override field.
     *
     * Idempotent: safe to call from both install and upgrade. The field is a
     * three-option select read by {@see course_gate::override()}. Moodle stores
     * the 1-based position of the chosen option (1 Default, 2 Include,
     * 3 Exclude), not its text, so the option texts are display labels and can
     * be written in the site language without touching any course's value.
     *
     * @return void
     */
    public static function ensure_course_field(): void {
        global $DB;

        $fieldid = $DB->get_field('customfield_field', 'id', ['shortname' => course_gate::FIELD]);
        if ($fieldid) {
            self::update_course_field_labels();
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

        $labels = self::labels(self::site_language());
        $record = (object) [
            'name' => $labels['name'],
            'shortname' => course_gate::FIELD,
            'type' => 'select',
            'description' => $labels['description'],
            'descriptionformat' => FORMAT_HTML,
            'configdata' => [
                'options' => implode("\n", $labels['options']),
                'defaultvalue' => $labels['options'][0],
                'required' => 0,
                'uniquevalues' => 0,
                'locked' => 0,
                'visibility' => 2,
            ],
        ];

        $handler->save_field_configuration($field, $record);
    }

    /**
     * Rewrite name, description and option labels of the existing field in the site language.
     *
     * Only texts the plugin wrote itself are replaced: the English option
     * tokens of earlier versions and the labels in any installed language, in
     * their original order. Options an administrator has renamed, reordered or
     * extended stay as they are, and so does the stored default. The value a
     * course holds is the option position and is never touched.
     *
     * Idempotent: a second run finds the texts already in place and saves nothing.
     *
     * @return bool True when the field configuration was saved.
     */
    public static function update_course_field_labels(): bool {
        global $DB;

        $fieldid = $DB->get_field('customfield_field', 'id', ['shortname' => course_gate::FIELD]);
        if (!$fieldid) {
            return false;
        }

        $field = field_controller::create((int) $fieldid);
        $labels = self::labels(self::site_language());
        $rawconfig = $field->get('configdata');
        $configdata = is_array($rawconfig) ? $rawconfig : (json_decode((string) $rawconfig, true) ?: []);

        $changed = $field->get('name') !== $labels['name'] || $field->get('description') !== $labels['description'];

        $options = self::option_lines((string) ($configdata['options'] ?? ''));
        if ($options !== $labels['options'] && self::is_own_options($options)) {
            $configdata['options'] = implode("\n", $labels['options']);
            $configdata['defaultvalue'] = $labels['options'][0];
            $changed = true;
        }

        if (!$changed) {
            return false;
        }

        $record = (object) [
            'name' => $labels['name'],
            'shortname' => $field->get('shortname'),
            'type' => $field->get('type'),
            'description' => $labels['description'],
            'descriptionformat' => FORMAT_HTML,
            'configdata' => $configdata,
        ];

        $field->get_handler()->save_field_configuration($field, $record);
        return true;
    }

    /**
     * Field name, description and the three option labels in one language.
     *
     * The options are ordered like {@see course_gate::OPTIONS}: Default,
     * Include, Exclude.
     *
     * @param string $lang Language code.
     * @return array{name: string, description: string, category: string, options: string[]}
     */
    public static function labels(string $lang): array {
        $sm = get_string_manager();
        $get = fn(string $key): string => $sm->get_string($key, 'local_elediaai_sources', null, $lang);
        return [
            'name' => $get('cffieldname'),
            'description' => $get('cffielddesc'),
            'category' => $get('cfcategory'),
            'options' => [$get('cfoption_default'), $get('cfoption_include'), $get('cfoption_exclude')],
        ];
    }

    /**
     * Whether a stored text is one the plugin wrote for the given label.
     *
     * @param string $label One of the keys of {@see labels()} except `options`.
     * @param string $text The stored text.
     * @return bool
     */
    public static function is_own_text(string $label, string $text): bool {
        foreach (self::known_languages() as $lang) {
            if (self::labels($lang)[$label] === $text) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether stored option lines are the plugin's own, in the original order.
     *
     * Accepts the English tokens of earlier versions and the labels in any
     * installed language.
     *
     * @param string[] $options Option lines as stored.
     * @return bool
     */
    public static function is_own_options(array $options): bool {
        if ($options === array_values(course_gate::OPTIONS)) {
            return true;
        }
        foreach (self::known_languages() as $lang) {
            if ($options === self::labels($lang)['options']) {
                return true;
            }
        }
        return false;
    }

    /**
     * Split the stored options text into lines the way the select field does.
     *
     * @param string $options The `options` value of the field configuration.
     * @return string[]
     */
    public static function option_lines(string $options): array {
        return preg_split("/\s*\n\s*/", trim($options), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * The site's default language, in which the stored texts are written.
     *
     * Not the language of whoever runs the upgrade: the stored texts are what
     * every place outside the course form shows (course listings, the custom
     * field administration, the course overview grouping).
     *
     * @return string
     */
    private static function site_language(): string {
        global $CFG;
        return !empty($CFG->lang) ? (string) $CFG->lang : 'en';
    }

    /**
     * Languages whose labels count as the plugin's own.
     *
     * @return string[]
     */
    private static function known_languages(): array {
        $langs = array_keys(get_string_manager()->get_list_of_translations(true));
        $langs[] = 'en';
        $langs[] = self::site_language();
        $langs[] = current_language();
        return array_values(array_unique($langs));
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
     * Remove the per-course override field, its values and its category.
     *
     * Called on uninstall. Without it the field outlives the plugin and keeps
     * showing up in every course form, where it no longer does anything (M-08).
     *
     * Goes through the course handler rather than raw SQL so that the
     * customfield API does what it does for any deleted field: the data rows,
     * the description files and the events. The category is looked up via the
     * field, not by name: the name is a translated string and may have been
     * renamed by an administrator. It is only removed when our field was the
     * only one in it — an administrator may have added fields of their own.
     *
     * @return void
     */
    public static function remove_course_field(): void {
        $handler = \core_course\customfield\course_handler::create();

        foreach ($handler->get_categories_with_fields() as $category) {
            $ours = [];
            $others = 0;
            foreach ($category->get_fields() as $field) {
                if ($field->get('shortname') === course_gate::FIELD) {
                    $ours[] = $field;
                } else {
                    $others++;
                }
            }
            if ($ours === []) {
                continue;
            }

            if ($others === 0) {
                $handler->delete_category($category);
                continue;
            }
            foreach ($ours as $field) {
                $handler->delete_field_configuration($field);
            }
        }
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

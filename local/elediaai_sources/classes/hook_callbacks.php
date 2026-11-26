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

/**
 * Hook callbacks.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Course edit form: while course marking is locked (test phase), remove the
     * AI Sources field for EVERYONE — including managers and admins, whom
     * Moodle's own field locking would still allow to edit. The per-course
     * value is inert during the lock anyway ({@see course_gate::should_ingest()});
     * removing the element keeps the UI honest. Stored values are untouched and
     * reappear when the lock is disabled.
     *
     * Otherwise the field is shown in the language of the person editing the
     * course ({@see self::relabel_course_marking()}).
     *
     * @param \core_course\hook\after_form_definition $hook The hook.
     */
    public static function after_course_form_definition(\core_course\hook\after_form_definition $hook): void {
        if (course_gate::marking_locked()) {
            self::strip_course_marking($hook->mform);
            return;
        }
        self::relabel_course_marking($hook->mform);
    }

    /**
     * Show the marking field in the current user's language.
     *
     * The custom field stores its name, description and option texts once, in
     * the site language (ai_suite#262). Moodle passes option texts through
     * format_string(), which only filters them when "Filter all strings" is on,
     * so multilang spans are no option. Instead the form gets the labels from
     * the language strings. The option values (positions) stay as they are.
     *
     * Only texts the plugin wrote itself are replaced; whatever an
     * administrator changed in the custom field settings is shown as stored.
     *
     * @param \MoodleQuickForm $mform The form.
     */
    public static function relabel_course_marking(\MoodleQuickForm $mform): void {
        global $DB;

        $element = 'customfield_' . course_gate::FIELD;
        if (!$mform->elementExists($element)) {
            return;
        }
        $field = $DB->get_record(
            'customfield_field',
            ['shortname' => course_gate::FIELD],
            'id, name, description, configdata, categoryid'
        );
        if (!$field) {
            return;
        }

        $labels = setup::labels(current_language());
        $select = $mform->getElement($element);

        $config = json_decode((string) $field->configdata, true) ?: [];
        $options = setup::option_lines((string) ($config['options'] ?? ''));
        if ($select instanceof \HTML_QuickForm_select && setup::is_own_options($options)) {
            $select->_options = [];
            // Position 0 is the empty choice customfield_select puts first.
            $select->loadArray(array_merge([''], $labels['options']));
        }
        if (setup::is_own_text('name', (string) $field->name)) {
            $select->setLabel($labels['name']);
        }

        $static = $element . '_static';
        if ($mform->elementExists($static) && setup::is_own_text('description', (string) $field->description)) {
            $mform->getElement($static)->setText(format_text(
                $labels['description'],
                FORMAT_HTML,
                ['context' => \core\context\system::instance()]
            ));
        }

        $header = 'category_' . $field->categoryid;
        $category = $DB->get_field('customfield_category', 'name', ['id' => $field->categoryid]);
        if ($category !== false && $mform->elementExists($header) && setup::is_own_text('category', (string) $category)) {
            $mform->getElement($header)->setText($labels['category']);
        }
    }

    /**
     * Remove the marking custom field (and its now-empty section header) from a
     * course edit form.
     *
     * @param \MoodleQuickForm $mform The form.
     */
    public static function strip_course_marking(\MoodleQuickForm $mform): void {
        global $DB;

        $element = 'customfield_' . course_gate::FIELD;
        if ($mform->elementExists($element)) {
            $mform->removeElement($element);
        }

        // Our custom-field category holds only this field; drop its header too
        // so no empty section remains.
        $categoryid = $DB->get_field(
            'customfield_field',
            'categoryid',
            ['shortname' => course_gate::FIELD]
        );
        if ($categoryid && $mform->elementExists('category_' . $categoryid)) {
            $mform->removeElement('category_' . $categoryid);
        }
    }
}

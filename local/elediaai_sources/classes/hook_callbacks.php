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
     * @param \core_course\hook\after_form_definition $hook The hook.
     */
    public static function after_course_form_definition(\core_course\hook\after_form_definition $hook): void {
        if (!course_gate::marking_locked()) {
            return;
        }
        self::strip_course_marking($hook->mform);
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

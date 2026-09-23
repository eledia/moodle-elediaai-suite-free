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
     * Course deletion: schedule removal of the course's documents.
     *
     * This has to happen *before* the course is torn down. Deleting a whole
     * course does not fire course_module_deleted per module — remove_course_contents()
     * calls each module's own delete_instance() and drops the course_modules
     * rows directly — so the module observer never sees these modules and their
     * documents would stay in the index for good. This hook runs while the
     * course still exists, which is the last point at which the module ids can
     * be read at all.
     *
     * Only courses the plugin believes to be indexed are cleared. A course that
     * was last written to a different destination is left alone, for the same
     * reason a target switch does not purge the old one: that backend is
     * usually unreachable by then and a failing purge must not break course
     * deletion.
     *
     * @param \core_course\hook\before_course_deleted $hook The hook.
     */
    public static function before_course_deleted(\core_course\hook\before_course_deleted $hook): void {
        $courseid = (int) $hook->course->id;
        if ($courseid <= 0 || $courseid == SITEID) {
            return;
        }

        if (!course_state::is_ingested($courseid)) {
            return;
        }

        try {
            $modinfo = get_fast_modinfo($courseid);
        } catch (\Throwable $e) {
            debugging(
                '[local_elediaai_sources] before_course_deleted could not read modinfo: ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            return;
        }

        foreach ($modinfo->get_cms() as $cm) {
            $task = new task\delete_module_task();
            $task->set_custom_data([
                'courseid' => $courseid,
                'cmid' => (int) $cm->id,
            ]);
            \core\task\manager::queue_adhoc_task($task, true);
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

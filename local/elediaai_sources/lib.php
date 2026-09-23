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
 * Course-module form and navigation callbacks for the AI Sources plugin.
 *
 * There are no activity-level custom fields in Moodle core (neither 4.5 nor
 * 5.2), so unlike the per-course marking — which lives in a course custom
 * field — the per-activity selection is wired in through these classic
 * callbacks and stored in the plugin's own table.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_elediaai_sources\activity_gate;
use local_elediaai_sources\course_gate;

/**
 * Add the ingestion selection to every activity's edit form.
 *
 * The element is omitted entirely — not frozen — when the user lacks the
 * capability or the test-phase lock is active: a frozen checkbox does not
 * submit its value reliably, and an absent element is what lets the save
 * handler distinguish "may not decide" from "decided".
 *
 * @param \moodleform_mod $formwrapper The module form wrapper.
 * @param \MoodleQuickForm $mform The form.
 */
function local_elediaai_sources_coursemodule_standard_elements($formwrapper, $mform): void {
    if (course_gate::marking_locked()) {
        return;
    }

    $current = $formwrapper->get_current();
    $cmid = (int) ($current->coursemodule ?? 0);
    $context = $cmid > 0
        ? \core\context\module::instance($cmid)
        : \core\context\course::instance((int) $current->course);

    if (!has_capability('local/elediaai_sources:selectactivities', $context)) {
        return;
    }

    $mform->addElement('header', 'elediaai_sources_header', get_string('pluginname', 'local_elediaai_sources'));
    $mform->addElement(
        'advcheckbox',
        'elediaai_sources_include',
        get_string('activityinclude', 'local_elediaai_sources')
    );
    $mform->addHelpButton('elediaai_sources_include', 'activityinclude', 'local_elediaai_sources');

    $default = activity_gate::mode() === activity_gate::MODE_OPTOUT;
    if ($cmid > 0) {
        $decisions = activity_gate::decisions((int) $current->course);
        if (array_key_exists($cmid, $decisions)) {
            $default = $decisions[$cmid];
        }
    }
    $mform->setDefault('elediaai_sources_include', $default ? 1 : 0);
}

/**
 * Persist the ingestion selection when the activity form is saved.
 *
 * Saving the form with the element present counts as an explicit decision —
 * also when the box was left at its default. That is deliberate: an explicit
 * row is what survives a later change of the site-wide default mode, so an
 * activity a teacher saw and saved keeps behaving as it did at that moment.
 *
 * The index itself is not touched here. The same save fires
 * course_module_updated, whose ad-hoc ingest task reads the flag at cron time
 * and either upserts or (on explicit exclusion) prefix-deletes the module —
 * event ordering differs between add and update, so nothing here may rely on
 * it.
 *
 * @param \stdClass $moduleinfo The saved module info.
 * @param \stdClass $course The course.
 * @return \stdClass The (unchanged) module info.
 */
function local_elediaai_sources_coursemodule_edit_post_actions($moduleinfo, $course) {
    // Absent property = the element was never in the form (no capability,
    // lock active, or a forged submission filtered out by get_data()).
    if (!property_exists($moduleinfo, 'elediaai_sources_include')) {
        return $moduleinfo;
    }

    activity_gate::set_included(
        (int) $course->id,
        (int) $moduleinfo->coursemodule,
        !empty($moduleinfo->elediaai_sources_include)
    );

    return $moduleinfo;
}

/**
 * Add the activity-selection page to the course navigation.
 *
 * @param \navigation_node $navigation The course navigation node.
 * @param \stdClass $course The course.
 * @param \core\context\course $context The course context.
 */
function local_elediaai_sources_extend_navigation_course(\navigation_node $navigation, \stdClass $course, $context): void {
    if (course_gate::marking_locked()) {
        return;
    }
    if (!has_capability('local/elediaai_sources:selectactivities', $context)) {
        return;
    }

    $navigation->add(
        get_string('activities_nav', 'local_elediaai_sources'),
        new moodle_url('/local/elediaai_sources/activities.php', ['id' => $course->id]),
        navigation_node::TYPE_SETTING,
        null,
        'elediaaisources_activities',
        new pix_icon('i/db', '')
    );
}

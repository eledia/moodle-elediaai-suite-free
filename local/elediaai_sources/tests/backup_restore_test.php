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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Tests that the per-activity decision survives backup, restore and duplication.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_local_elediaai_sources_plugin
 * @covers     \restore_local_elediaai_sources_plugin
 */
final class backup_restore_test extends \advanced_testcase {
    /**
     * Back a course up and restore it into a new one.
     *
     * @param \stdClass $course The source course.
     * @param int $userid The user performing backup and restore.
     * @return \stdClass The restored course.
     */
    private function backup_and_restore(\stdClass $course, int $userid): \stdClass {
        global $CFG;

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $userid
        );
        $bc->execute_plan();
        $results = $bc->get_results();
        $file = $results['backup_destination'];
        $backupid = $bc->get_backupid();
        $bc->destroy();

        $path = make_backup_temp_directory($backupid);
        $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), $path);

        $newcourseid = \restore_dbops::create_new_course(
            $course->fullname . ' (copy)',
            $course->shortname . '_copy',
            $course->category
        );
        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $userid,
            \backup::TARGET_NEW_COURSE
        );
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        return get_course($newcourseid);
    }

    /**
     * A decision travels with the activity into the restored course, but the
     * index state does not — state belongs to the site that sent the content.
     */
    public function test_decision_survives_backup_and_restore(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $excluded = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Excluded page',
            'content' => '<p>Out</p>',
        ]);
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Untouched page',
            'content' => '<p>In</p>',
        ]);
        activity_gate::set_included((int) $course->id, (int) $excluded->cmid, false);
        cm_state::record_success((int) $course->id, (int) $excluded->cmid, 'sid', 'h', 'recording');

        $restored = $this->backup_and_restore($course, get_admin()->id);

        $decisions = activity_gate::decisions((int) $restored->id);
        $this->assertCount(1, $decisions, 'Only the decided activity carries a decision.');
        $this->assertFalse(reset($decisions), 'The exclusion survived.');

        $newcmid = array_key_first($decisions);
        $this->assertNotSame((int) $excluded->cmid, $newcmid, 'The restored module is a different one.');
        $this->assertSame(
            0,
            (int) $DB->get_field('local_elediaai_sources_cm', 'usermodified', ['cmid' => $newcmid]),
            'A user id from the source site says nothing here.'
        );
        $this->assertSame(
            0,
            $DB->count_records('local_elediaai_sources_cmstate', ['courseid' => $restored->id]),
            'Index state must not be restored: nothing was ever sent for this course.'
        );
    }

    /**
     * An activity without a decision stays without one after a restore.
     */
    public function test_undecided_activity_stays_undecided(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Nobody decided</p>',
        ]);

        $restored = $this->backup_and_restore($course, get_admin()->id);

        $this->assertSame(
            0,
            $DB->count_records('local_elediaai_sources_cm', ['courseid' => $restored->id])
        );
    }

    /**
     * Duplicating an activity carries its decision along, because duplication
     * runs through backup and restore.
     */
    public function test_duplicated_activity_inherits_the_decision(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Original</p>',
        ]);
        activity_gate::set_included((int) $course->id, (int) $page->cmid, false);

        $newcmid = $this->duplicate((int) $course->id, (int) $page->cmid);

        $this->assertGreaterThan(0, $newcmid);
        $this->assertTrue(
            activity_gate::is_explicitly_excluded($newcmid),
            'A copy of an excluded activity must not silently start being ingested.'
        );
    }

    /**
     * Duplicate a module using whichever API this Moodle version offers.
     *
     * 5.2 deprecated duplicate_module() in favour of the format actions.
     * Probed by METHOD, not by class: 4.5 already has cmactions (visibility
     * changes go through it there too), it just cannot duplicate yet.
     *
     * @param int $courseid The course id.
     * @param int $cmid The module to duplicate.
     * @return int The new course module id.
     */
    private function duplicate(int $courseid, int $cmid): int {
        if (method_exists('\core_courseformat\local\cmactions', 'duplicate')) {
            $newcm = \core_courseformat\formatactions::cm($courseid)->duplicate($cmid);
            return $newcm !== null ? (int) $newcm->id : 0;
        }

        $course = get_course($courseid);
        $cm = get_fast_modinfo($course)->get_cm($cmid);
        $newcm = duplicate_module($course, $cm);
        return $newcm !== null ? (int) $newcm->id : 0;
    }
}

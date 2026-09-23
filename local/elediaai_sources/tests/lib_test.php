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
require_once($CFG->dirroot . '/local/elediaai_sources/lib.php');

/**
 * Unit tests for the module-form save callback.
 *
 * The form rendering side (coursemodule_standard_elements) is deliberately
 * not covered here: moodleform_mod cannot be instantiated standalone. What
 * matters behaviourally — what a save does and does not touch — is.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::local_elediaai_sources_coursemodule_edit_post_actions
 */
final class lib_test extends \advanced_testcase {
    /**
     * Saving with the box unticked records an explicit exclusion.
     */
    public function test_post_actions_unticked_records_exclusion(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $moduleinfo = (object) ['coursemodule' => 100, 'elediaai_sources_include' => 0];
        $course = (object) ['id' => 7];

        $returned = local_elediaai_sources_coursemodule_edit_post_actions($moduleinfo, $course);

        $this->assertSame($moduleinfo, $returned);
        $this->assertTrue(activity_gate::is_explicitly_excluded(100));
    }

    /**
     * Saving with the box ticked records an explicit inclusion — also when
     * that matches the mode default, so the decision survives a mode switch.
     */
    public function test_post_actions_ticked_records_inclusion(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $moduleinfo = (object) ['coursemodule' => 100, 'elediaai_sources_include' => 1];
        local_elediaai_sources_coursemodule_edit_post_actions($moduleinfo, (object) ['id' => 7]);

        $this->assertTrue($DB->record_exists('local_elediaai_sources_cm', ['cmid' => 100]));
        set_config('activitydefault', activity_gate::MODE_OPTIN, 'local_elediaai_sources');
        $this->assertTrue(activity_gate::should_ingest_cm(100));
    }

    /**
     * A save whose form never contained the element leaves the stored
     * decision untouched — that is how "may not decide" is distinguished
     * from "decided".
     */
    public function test_post_actions_without_element_leaves_row_alone(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        activity_gate::set_included(7, 100, false);

        $moduleinfo = (object) ['coursemodule' => 100];
        local_elediaai_sources_coursemodule_edit_post_actions($moduleinfo, (object) ['id' => 7]);

        $this->assertTrue(activity_gate::is_explicitly_excluded(100));
    }
}

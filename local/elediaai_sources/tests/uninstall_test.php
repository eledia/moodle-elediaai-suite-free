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
require_once($CFG->dirroot . '/local/elediaai_sources/db/uninstall.php');

/**
 * Tests for the uninstall cleanup of the course custom field (M-08).
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_sources\setup::remove_course_field
 * @covers     ::xmldb_local_elediaai_sources_uninstall
 */
final class uninstall_test extends \advanced_testcase {
    /**
     * The field installed by the plugin, as a controller.
     *
     * @return \core_customfield\field_controller
     */
    private function installed_field(): \core_customfield\field_controller {
        global $DB;
        setup::ensure_course_field();
        $fieldid = (int) $DB->get_field('customfield_field', 'id', ['shortname' => course_gate::FIELD], MUST_EXIST);
        return \core_customfield\field_controller::create($fieldid);
    }

    /**
     * Uninstalling removes field, category and every stored course value.
     */
    public function test_uninstall_removes_field_category_and_data(): void {
        global $DB;
        $this->resetAfterTest();

        $field = $this->installed_field();
        $categoryid = (int) $field->get('categoryid');
        $course = $this->getDataGenerator()->create_course();
        /** @var \core_customfield_generator $cfgen */
        $cfgen = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        $cfgen->add_instance_data($field, (int) $course->id, 2);
        $this->assertTrue($DB->record_exists('customfield_data', ['fieldid' => $field->get('id')]));

        $this->assertTrue(xmldb_local_elediaai_sources_uninstall());

        $this->assertFalse($DB->record_exists('customfield_field', ['shortname' => course_gate::FIELD]));
        $this->assertFalse($DB->record_exists('customfield_category', ['id' => $categoryid]));
        $this->assertFalse($DB->record_exists('customfield_data', ['fieldid' => $field->get('id')]));
    }

    /**
     * A category an administrator also put their own fields in stays, with those fields.
     */
    public function test_uninstall_keeps_shared_category_and_foreign_fields(): void {
        global $DB;
        $this->resetAfterTest();

        $field = $this->installed_field();
        $categoryid = (int) $field->get('categoryid');
        /** @var \core_customfield_generator $cfgen */
        $cfgen = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        $foreign = $cfgen->create_field(['categoryid' => $categoryid, 'shortname' => 'adminfield']);

        xmldb_local_elediaai_sources_uninstall();

        $this->assertFalse($DB->record_exists('customfield_field', ['shortname' => course_gate::FIELD]));
        $this->assertTrue($DB->record_exists('customfield_category', ['id' => $categoryid]));
        $this->assertTrue($DB->record_exists('customfield_field', ['id' => $foreign->get('id')]));
    }

    /**
     * Running the cleanup without a field is harmless.
     */
    public function test_uninstall_without_field_is_noop(): void {
        global $DB;
        $this->resetAfterTest();

        xmldb_local_elediaai_sources_uninstall();
        xmldb_local_elediaai_sources_uninstall();

        $this->assertFalse($DB->record_exists('customfield_field', ['shortname' => course_gate::FIELD]));
    }
}

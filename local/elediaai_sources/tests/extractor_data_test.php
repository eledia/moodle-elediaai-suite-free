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
 * Unit tests for the data (database activity) content extractor.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aisourcesextractor_data\extractor
 */
final class extractor_data_test extends \advanced_testcase {
    /**
     * Test that the data extractor supports data modules.
     */
    public function test_supports_data(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $data = $this->getDataGenerator()->create_module('data', [
            'course' => $course->id,
        ]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($data->cmid);

        $extractor = new \aisourcesextractor_data\extractor();
        $this->assertTrue($extractor->supports($cm));
    }

    /**
     * Test that the data extractor does not support page modules.
     */
    public function test_does_not_support_page(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Page</p>',
        ]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($page->cmid);

        $extractor = new \aisourcesextractor_data\extractor();
        $this->assertFalse($extractor->supports($cm));
    }

    /**
     * Test extracting content from a database activity with text entries.
     */
    public function test_extract_returns_html_with_entries(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $data = $this->getDataGenerator()->create_module('data', [
            'course' => $course->id,
            'name' => 'Knowledge Base',
            'intro' => '<p>Shared knowledge base.</p>',
        ]);

        // Create a text field.
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_data');
        $fieldrecord = new \stdClass();
        $fieldrecord->name = 'Topic';
        $fieldrecord->type = 'text';
        $field = $generator->create_field($fieldrecord, $data);

        // Create an approved record with content.
        $entry = $generator->create_entry(
            $data,
            [$field->field->id => 'Machine Learning Basics'],
        );

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($data->cmid);

        $extractor = new \aisourcesextractor_data\extractor();
        $result = $extractor->extract($cm);

        $this->assertNotNull($result);
        $this->assertEquals('text/html', $result['content_type']);
        $this->assertEquals('Knowledge Base', $result['title']);
        $this->assertStringContainsString('Shared knowledge base.', $result['content']);
        $this->assertStringContainsString('Topic', $result['content']);
        $this->assertStringContainsString('Machine Learning Basics', $result['content']);
    }

    /**
     * Test that extraction returns null for an empty database.
     */
    public function test_extract_returns_null_for_empty_database(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $data = $this->getDataGenerator()->create_module('data', [
            'course' => $course->id,
        ]);

        // Generator ignores empty intro — force-clear it.
        $DB->set_field('data', 'intro', '', ['id' => $data->id]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($data->cmid);

        $extractor = new \aisourcesextractor_data\extractor();
        $result = $extractor->extract($cm);

        $this->assertNull($result);
    }

    /**
     * Test that unapproved records are excluded.
     */
    public function test_extract_excludes_unapproved_records(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $data = $this->getDataGenerator()->create_module('data', [
            'course' => $course->id,
            'name' => 'Moderated DB',
            'approval' => 1,
        ]);

        // Generator ignores empty intro — force-clear it.
        $DB->set_field('data', 'intro', '', ['id' => $data->id]);

        // Create a text field.
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_data');
        $fieldrecord = new \stdClass();
        $fieldrecord->name = 'Note';
        $fieldrecord->type = 'text';
        $field = $generator->create_field($fieldrecord, $data);

        // Create a record.
        $entry = $generator->create_entry(
            $data,
            [$field->field->id => 'Unapproved content'],
        );

        // Mark the record as unapproved.
        $DB->set_field('data_records', 'approved', 0, ['dataid' => $data->id]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($data->cmid);

        $extractor = new \aisourcesextractor_data\extractor();
        $result = $extractor->extract($cm);

        // No intro, no approved records → null.
        $this->assertNull($result);
    }
}

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
 * Unit tests for the label content extractor.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aisourcesextractor_label\extractor
 */
final class extractor_label_test extends \advanced_testcase {
    /**
     * Test that the label extractor supports label modules.
     */
    public function test_supports_label(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $label = $this->getDataGenerator()->create_module('label', [
            'course' => $course->id,
            'intro' => '<p>A label</p>',
        ]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($label->cmid);

        $extractor = new \aisourcesextractor_label\extractor();
        $this->assertTrue($extractor->supports($cm));
    }

    /**
     * Test that the label extractor does not support page modules.
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

        $extractor = new \aisourcesextractor_label\extractor();
        $this->assertFalse($extractor->supports($cm));
    }

    /**
     * Test extracting content from a label activity.
     */
    public function test_extract_returns_content(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $label = $this->getDataGenerator()->create_module('label', [
            'course' => $course->id,
            'intro' => '<p>Important information here</p>',
        ]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($label->cmid);

        $extractor = new \aisourcesextractor_label\extractor();
        $result = $extractor->extract($cm);

        $this->assertNotNull($result);
        $this->assertEquals('text/html', $result['content_type']);
        $this->assertStringContainsString('Important information here', $result['content']);
    }

    /**
     * Test that extraction returns null for a label with empty intro.
     */
    public function test_extract_returns_null_for_empty_label(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $label = $this->getDataGenerator()->create_module('label', [
            'course' => $course->id,
            'intro' => '',
        ]);

        // The label generator auto-fills intro with the name, so force it to be empty.
        $DB->set_field('label', 'intro', '', ['id' => $label->id]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($label->cmid);

        $extractor = new \aisourcesextractor_label\extractor();
        $result = $extractor->extract($cm);

        $this->assertNull($result);
    }
}

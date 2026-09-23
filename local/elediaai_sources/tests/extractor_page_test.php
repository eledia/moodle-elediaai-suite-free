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
 * Unit tests for the page content extractor.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aisourcesextractor_page\extractor
 */
final class extractor_page_test extends \advanced_testcase {
    /**
     * Test that the page extractor supports page modules.
     */
    public function test_supports_page(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Content</p>',
        ]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($page->cmid);

        $extractor = new \aisourcesextractor_page\extractor();
        $this->assertTrue($extractor->supports($cm));
    }

    /**
     * Test that the page extractor does not support other module types.
     */
    public function test_does_not_support_forum(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('forum', [
            'course' => $course->id,
        ]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($forum->cmid);

        $extractor = new \aisourcesextractor_page\extractor();
        $this->assertFalse($extractor->supports($cm));
    }

    /**
     * Test extracting content from a page activity.
     */
    public function test_extract_returns_content(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'My Test Page',
            'content' => '<p>Hello World</p>',
        ]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($page->cmid);

        $extractor = new \aisourcesextractor_page\extractor();
        $result = $extractor->extract($cm);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('content_type', $result);
        $this->assertArrayHasKey('title', $result);
        $this->assertEquals('text/html', $result['content_type']);
        $this->assertEquals('My Test Page', $result['title']);
        $this->assertStringContainsString('Hello World', $result['content']);
    }

    /**
     * Test that extraction returns null for an empty page.
     */
    public function test_extract_returns_null_for_empty_page(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Empty Page',
            'content' => '',
        ]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($page->cmid);

        $extractor = new \aisourcesextractor_page\extractor();
        $result = $extractor->extract($cm);

        $this->assertNull($result);
    }
}

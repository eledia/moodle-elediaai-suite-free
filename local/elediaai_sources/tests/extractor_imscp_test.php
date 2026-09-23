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
 * Unit tests for the IMS content package extractor.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aisourcesextractor_imscp\extractor
 */
final class extractor_imscp_test extends \advanced_testcase {
    /**
     * Test that the imscp extractor supports imscp modules.
     */
    public function test_supports_imscp(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $imscp = $this->getDataGenerator()->create_module('imscp', [
            'course' => $course->id,
        ]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($imscp->cmid);

        $extractor = new \aisourcesextractor_imscp\extractor();
        $this->assertTrue($extractor->supports($cm));
    }

    /**
     * Test that the imscp extractor does not support page modules.
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

        $extractor = new \aisourcesextractor_imscp\extractor();
        $this->assertFalse($extractor->supports($cm));
    }

    /**
     * Test extracting content from an IMS CP with structure and HTML files.
     */
    public function test_extract_returns_html_from_structure(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $imscp = $this->getDataGenerator()->create_module('imscp', [
            'course' => $course->id,
            'name' => 'Learning Package',
        ]);

        $context = \core\context\module::instance($imscp->cmid);
        $revision = $DB->get_field('imscp', 'revision', ['id' => $imscp->id]);

        // Set the manifest structure.
        $structure = json_encode([
            (object) [
                'title' => 'Introduction',
                'href' => 'intro.html',
                'subitems' => [],
            ],
            (object) [
                'title' => 'Chapter One',
                'href' => 'chapter1.html',
                'subitems' => [
                    (object) [
                        'title' => 'Section 1.1',
                        'href' => 'section1_1.html',
                        'subitems' => [],
                    ],
                ],
            ],
        ]);
        $DB->set_field('imscp', 'structure', $structure, ['id' => $imscp->id]);

        // Create content files matching the structure.
        $fs = get_file_storage();
        $this->create_imscp_file(
            $fs,
            $context->id,
            $revision,
            'intro.html',
            '<html><body><p>Welcome to this learning package.</p></body></html>',
        );
        $this->create_imscp_file(
            $fs,
            $context->id,
            $revision,
            'chapter1.html',
            '<html><body><p>Chapter one main content goes here.</p></body></html>',
        );
        $this->create_imscp_file(
            $fs,
            $context->id,
            $revision,
            'section1_1.html',
            '<html><body><p>Section 1.1 detailed content.</p></body></html>',
        );

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($imscp->cmid);

        $extractor = new \aisourcesextractor_imscp\extractor();
        $result = $extractor->extract($cm);

        $this->assertNotNull($result);
        $this->assertEquals('text/html', $result['content_type']);
        $this->assertEquals('Learning Package', $result['title']);
        $this->assertStringContainsString('<h2>Introduction</h2>', $result['content']);
        $this->assertStringContainsString('Welcome to this learning package.', $result['content']);
        $this->assertStringContainsString('<h2>Chapter One</h2>', $result['content']);
        $this->assertStringContainsString('Chapter one main content goes here.', $result['content']);
        $this->assertStringContainsString('<h2>Section 1.1</h2>', $result['content']);
        $this->assertStringContainsString('Section 1.1 detailed content.', $result['content']);
    }

    /**
     * Test that extraction returns null when no content files exist.
     */
    public function test_extract_returns_null_for_empty_package(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $imscp = $this->getDataGenerator()->create_module('imscp', [
            'course' => $course->id,
        ]);

        // Generator ignores empty intro — force-clear it.
        $DB->set_field('imscp', 'intro', '', ['id' => $imscp->id]);

        // Clear the structure and remove any default files.
        $DB->set_field('imscp', 'structure', '', ['id' => $imscp->id]);
        $context = \core\context\module::instance($imscp->cmid);
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_imscp', 'content');

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($imscp->cmid);

        $extractor = new \aisourcesextractor_imscp\extractor();
        $result = $extractor->extract($cm);

        $this->assertNull($result);
    }

    /**
     * Helper to create a file in the IMS CP content area.
     *
     * @param \file_storage $fs The file storage instance.
     * @param int $contextid The context ID.
     * @param int $revision The IMS CP revision number.
     * @param string $filename The filename.
     * @param string $content The file content.
     */
    private function create_imscp_file(
        \file_storage $fs,
        int $contextid,
        int $revision,
        string $filename,
        string $content,
    ): void {
        $filerecord = [
            'contextid' => $contextid,
            'component' => 'mod_imscp',
            'filearea' => 'content',
            'itemid' => $revision,
            'filepath' => '/',
            'filename' => $filename,
        ];
        $fs->create_file_from_string($filerecord, $content);
    }
}

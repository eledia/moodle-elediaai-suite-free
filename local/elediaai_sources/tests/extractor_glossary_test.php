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
 * Unit tests for the glossary content extractor.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aisourcesextractor_glossary\extractor
 */
final class extractor_glossary_test extends \advanced_testcase {
    /**
     * Test that the glossary extractor supports glossary modules.
     */
    public function test_supports_glossary(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $glossary = $this->getDataGenerator()->create_module('glossary', [
            'course' => $course->id,
        ]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($glossary->cmid);

        $extractor = new \aisourcesextractor_glossary\extractor();
        $this->assertTrue($extractor->supports($cm));
    }

    /**
     * Test that the glossary extractor does not support page modules.
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

        $extractor = new \aisourcesextractor_glossary\extractor();
        $this->assertFalse($extractor->supports($cm));
    }

    /**
     * Test extracting content from a glossary with entries.
     */
    public function test_extract_returns_html_with_entries(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $glossary = $this->getDataGenerator()->create_module('glossary', [
            'course' => $course->id,
            'name' => 'Course Glossary',
        ]);

        // Create glossary entries via the data generator.
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_glossary');
        $generator->create_content($glossary, ['concept' => 'API', 'definition' => 'Application Programming Interface']);
        $generator->create_content($glossary, ['concept' => 'REST', 'definition' => 'Representational State Transfer']);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($glossary->cmid);

        $extractor = new \aisourcesextractor_glossary\extractor();
        $result = $extractor->extract($cm);

        $this->assertNotNull($result);
        $this->assertEquals('text/html', $result['content_type']);
        $this->assertEquals('Course Glossary', $result['title']);

        // Check that the HTML document contains entry terms.
        $this->assertStringContainsString('<dt>API</dt>', $result['content']);
        $this->assertStringContainsString('<dt>REST</dt>', $result['content']);
        $this->assertStringContainsString('Application Programming Interface', $result['content']);
        $this->assertStringContainsString('<dl>', $result['content']);
    }

    /**
     * Test that the glossary description (intro) and entry aliases (synonyms)
     * are included in the extracted content.
     */
    public function test_extract_includes_intro_and_aliases(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $glossary = $this->getDataGenerator()->create_module('glossary', [
            'course' => $course->id,
            'name' => 'Tech Terms',
            'intro' => '<p>Key terminology for the course.</p>',
            'introformat' => FORMAT_HTML,
        ]);

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_glossary');
        $entry = $generator->create_content($glossary, [
            'concept' => 'HTTP',
            'definition' => 'Hypertext Transfer Protocol',
        ]);

        // Attach alias synonyms to the entry.
        foreach (['HyperText Transfer Protocol', 'web protocol'] as $alias) {
            $DB->insert_record('glossary_alias', (object) ['entryid' => $entry->id, 'alias' => $alias]);
        }

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($glossary->cmid);

        $result = (new \aisourcesextractor_glossary\extractor())->extract($cm);

        $this->assertNotNull($result);
        // The course-level description is indexed.
        $this->assertStringContainsString('Key terminology for the course.', $result['content']);
        // The concept and both aliases are present for synonym retrieval.
        $this->assertStringContainsString('<dt>HTTP</dt>', $result['content']);
        $this->assertStringContainsString('HyperText Transfer Protocol', $result['content']);
        $this->assertStringContainsString('web protocol', $result['content']);
    }

    /**
     * Test that extraction returns null for an empty glossary.
     */
    public function test_extract_returns_null_for_empty_glossary(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $glossary = $this->getDataGenerator()->create_module('glossary', [
            'course' => $course->id,
        ]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($glossary->cmid);

        $extractor = new \aisourcesextractor_glossary\extractor();
        $result = $extractor->extract($cm);

        $this->assertNull($result);
    }

    /**
     * Test that unapproved entries are excluded from extraction.
     */
    public function test_extract_excludes_unapproved_entries(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $glossary = $this->getDataGenerator()->create_module('glossary', [
            'course' => $course->id,
            'name' => 'Reviewed Glossary',
            'defaultapproval' => 0,
        ]);

        // Create an entry and mark it as unapproved.
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_glossary');
        $entry = $generator->create_content($glossary, [
            'concept' => 'Unapproved',
            'definition' => 'This should not appear',
            'approved' => 0,
        ]);

        // Force the entry to be unapproved in DB.
        $DB->set_field('glossary_entries', 'approved', 0, ['id' => $entry->id]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($glossary->cmid);

        $extractor = new \aisourcesextractor_glossary\extractor();
        $result = $extractor->extract($cm);

        // With only unapproved entries, extraction should return null.
        $this->assertNull($result);
    }
}

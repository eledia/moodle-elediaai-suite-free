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
 * Unit tests for the book content extractor.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aisourcesextractor_book\extractor
 */
final class extractor_book_test extends \advanced_testcase {
    /**
     * Test that the book extractor supports book modules.
     */
    public function test_supports_book(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $book = $this->getDataGenerator()->create_module('book', [
            'course' => $course->id,
        ]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($book->cmid);

        $extractor = new \aisourcesextractor_book\extractor();
        $this->assertTrue($extractor->supports($cm));
    }

    /**
     * Test that the book extractor does not support page modules.
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

        $extractor = new \aisourcesextractor_book\extractor();
        $this->assertFalse($extractor->supports($cm));
    }

    /**
     * Test extracting content from a book with chapters.
     */
    public function test_extract_returns_html_with_chapters(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $book = $this->getDataGenerator()->create_module('book', [
            'course' => $course->id,
            'name' => 'My Course Book',
        ]);

        // Create chapters via data generator.
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_book');
        $generator->create_chapter([
            'bookid' => $book->id,
            'title' => 'Chapter One',
            'content' => '<p>First chapter content</p>',
        ]);
        $generator->create_chapter([
            'bookid' => $book->id,
            'title' => 'Chapter Two',
            'content' => '<p>Second chapter content</p>',
        ]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($book->cmid);

        $extractor = new \aisourcesextractor_book\extractor();
        $result = $extractor->extract($cm);

        $this->assertNotNull($result);
        $this->assertEquals('text/html', $result['content_type']);
        $this->assertEquals('My Course Book', $result['title']);

        // Check the HTML structure.
        $this->assertStringContainsString('<h1>My Course Book</h1>', $result['content']);
        $this->assertStringContainsString('<h2>Chapter One</h2>', $result['content']);
        $this->assertStringContainsString('<h2>Chapter Two</h2>', $result['content']);
        $this->assertStringContainsString('First chapter content', $result['content']);
        $this->assertStringContainsString('Second chapter content', $result['content']);
    }

    /**
     * Test that subchapters use <h3> tags.
     */
    public function test_extract_subchapters_use_h3(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $book = $this->getDataGenerator()->create_module('book', [
            'course' => $course->id,
            'name' => 'Subchapter Book',
        ]);

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_book');
        $generator->create_chapter([
            'bookid' => $book->id,
            'title' => 'Main Chapter',
            'content' => '<p>Main content</p>',
            'subchapter' => 0,
        ]);
        $generator->create_chapter([
            'bookid' => $book->id,
            'title' => 'Sub Section',
            'content' => '<p>Subsection content</p>',
            'subchapter' => 1,
        ]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($book->cmid);

        $extractor = new \aisourcesextractor_book\extractor();
        $result = $extractor->extract($cm);

        $this->assertNotNull($result);
        $this->assertStringContainsString('<h2>Main Chapter</h2>', $result['content']);
        $this->assertStringContainsString('<h3>Sub Section</h3>', $result['content']);
    }

    /**
     * Test that hidden chapters are excluded from extraction.
     */
    public function test_extract_excludes_hidden_chapters(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $book = $this->getDataGenerator()->create_module('book', [
            'course' => $course->id,
            'name' => 'Book With Hidden',
        ]);

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_book');
        $chapter = $generator->create_chapter([
            'bookid' => $book->id,
            'title' => 'Hidden Chapter',
            'content' => '<p>Secret content</p>',
        ]);

        // Hide the chapter.
        $DB->set_field('book_chapters', 'hidden', 1, ['id' => $chapter->id]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($book->cmid);

        $extractor = new \aisourcesextractor_book\extractor();
        $result = $extractor->extract($cm);

        // With only hidden chapters, extraction should return null.
        $this->assertNull($result);
    }

    /**
     * Test that extraction returns null for a book with no chapters.
     */
    public function test_extract_returns_null_for_empty_book(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $book = $this->getDataGenerator()->create_module('book', [
            'course' => $course->id,
        ]);

        // The book generator creates a default chapter — remove it.
        $DB->delete_records('book_chapters', ['bookid' => $book->id]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($book->cmid);

        $extractor = new \aisourcesextractor_book\extractor();
        $result = $extractor->extract($cm);

        $this->assertNull($result);
    }
}

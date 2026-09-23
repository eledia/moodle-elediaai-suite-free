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
 * Unit tests for the folder content extractor.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aisourcesextractor_folder\extractor
 */
final class extractor_folder_test extends \advanced_testcase {
    /**
     * Test that the folder extractor supports folder modules.
     */
    public function test_supports_folder(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $folder = $this->getDataGenerator()->create_module('folder', [
            'course' => $course->id,
        ]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($folder->cmid);

        $extractor = new \aisourcesextractor_folder\extractor();
        $this->assertTrue($extractor->supports($cm));
    }

    /**
     * Test that the folder extractor does not support page modules.
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

        $extractor = new \aisourcesextractor_folder\extractor();
        $this->assertFalse($extractor->supports($cm));
    }

    /**
     * Test extracting a single text file from a folder.
     */
    public function test_extract_single_text_file(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $folder = $this->getDataGenerator()->create_module('folder', [
            'course' => $course->id,
            'name' => 'Course Resources',
        ]);

        // Generator ignores empty intro — force-clear it so single-file native MIME is used.
        $DB->set_field('folder', 'intro', '', ['id' => $folder->id]);

        $this->add_file_to_folder($folder, 'readme.txt', 'Important course information.', 'text/plain');

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($folder->cmid);

        $extractor = new \aisourcesextractor_folder\extractor();
        $result = $extractor->extract($cm);

        $this->assertNotNull($result);
        // Single file without intro — native MIME type preserved.
        $this->assertEquals('text/plain', $result['content_type']);
        $this->assertEquals('Important course information.', $result['content']);
        $this->assertEquals('readme.txt', $result['title']);
    }

    /**
     * Test extracting multiple files wraps them in HTML.
     */
    public function test_extract_multiple_files_as_html(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $folder = $this->getDataGenerator()->create_module('folder', [
            'course' => $course->id,
            'name' => 'Multi File Folder',
        ]);

        $this->add_file_to_folder($folder, 'notes.txt', 'First file content.', 'text/plain');
        $this->add_file_to_folder($folder, 'guide.html', '<p>Second file guide.</p>', 'text/html');

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($folder->cmid);

        $extractor = new \aisourcesextractor_folder\extractor();
        $result = $extractor->extract($cm);

        $this->assertNotNull($result);
        $this->assertEquals('text/html', $result['content_type']);
        $this->assertEquals('Multi File Folder', $result['title']);
        $this->assertStringContainsString('First file content.', $result['content']);
        $this->assertStringContainsString('Second file guide.', $result['content']);
        $this->assertStringContainsString('<h2>notes.txt</h2>', $result['content']);
        $this->assertStringContainsString('<h2>guide.html</h2>', $result['content']);
    }

    /**
     * Test that unsupported file types are skipped.
     */
    public function test_extract_skips_unsupported_files(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $folder = $this->getDataGenerator()->create_module('folder', [
            'course' => $course->id,
            'name' => 'Image Folder',
        ]);

        // Generator ignores empty intro — force-clear it.
        $DB->set_field('folder', 'intro', '', ['id' => $folder->id]);

        $this->add_file_to_folder($folder, 'photo.png', 'fakepngdata', 'image/png');

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($folder->cmid);

        $extractor = new \aisourcesextractor_folder\extractor();
        $result = $extractor->extract($cm);

        $this->assertNull($result);
    }

    /**
     * Test that extraction returns null for an empty folder.
     */
    public function test_extract_returns_null_for_empty_folder(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $folder = $this->getDataGenerator()->create_module('folder', [
            'course' => $course->id,
        ]);

        // Generator ignores empty intro — force-clear it.
        $DB->set_field('folder', 'intro', '', ['id' => $folder->id]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($folder->cmid);

        $extractor = new \aisourcesextractor_folder\extractor();
        $result = $extractor->extract($cm);

        $this->assertNull($result);
    }

    /**
     * Files that cannot be exported leave a notice naming the file and the
     * reason — and they never take a suffix, so switching a format on does not
     * renumber (and therefore re-upsert) the files around them.
     */
    public function test_extract_documents_reports_skipped_files(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $folder = $this->getDataGenerator()->create_module('folder', [
            'course' => $course->id,
            'name' => 'Gemischt',
        ]);
        $DB->set_field('folder', 'intro', '', ['id' => $folder->id]);

        $this->add_file_to_folder($folder, 'a.pdf', '%PDF-1.7 first', 'application/pdf');
        $this->add_file_to_folder($folder, 'clip.mp4', 'not a video', 'video/mp4');
        $this->add_file_to_folder($folder, 'b.pdf', '%PDF-1.7 second', 'application/pdf');

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($folder->cmid);

        $documents = (new \aisourcesextractor_folder\extractor())->extract_documents($cm);

        $sent = array_values(array_filter($documents, static fn($d) => empty($d['skipped'])));
        $skipped = array_values(array_filter($documents, static fn($d) => !empty($d['skipped'])));

        $this->assertCount(2, $sent);
        $this->assertSame(['file1', 'file2'], array_column($sent, 'suffix'));
        $this->assertSame(['a.pdf', 'b.pdf'], array_column($sent, 'title'));

        $this->assertCount(1, $skipped);
        $this->assertSame('clip.mp4', $skipped[0]['title']);
        $this->assertArrayNotHasKey('suffix', $skipped[0]);
        $this->assertStringContainsString('clip.mp4', $skipped[0]['skipreason']);
    }

    /**
     * A document format the matrix knows is handed over with its own content
     * type; the destination question is the manager's, not the extractor's.
     */
    public function test_extract_documents_hands_over_negotiated_formats(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $folder = $this->getDataGenerator()->create_module('folder', [
            'course' => $course->id,
            'name' => 'Office',
        ]);
        $DB->set_field('folder', 'intro', '', ['id' => $folder->id]);

        $this->add_file_to_folder(
            $folder,
            'Handout.docx',
            'PK docx bytes',
            \local_elediaai_sources\format_matrix::DOCX
        );
        $this->add_file_to_folder(
            $folder,
            'Folien.pptx',
            'PK pptx bytes',
            \local_elediaai_sources\format_matrix::PPTX
        );
        $this->add_file_to_folder(
            $folder,
            'Noten.xlsx',
            'PK xlsx bytes',
            \local_elediaai_sources\format_matrix::XLSX
        );

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($folder->cmid);

        $documents = (new \aisourcesextractor_folder\extractor())->extract_documents($cm);

        // All three are document formats, so all three are handed over with
        // their own content type. Whether the destination takes them is the
        // manager's question, not this one's.
        $sent = array_values(array_filter($documents, static fn($d) => empty($d['skipped'])));
        $this->assertSame(
            [
                \local_elediaai_sources\format_matrix::DOCX,
                \local_elediaai_sources\format_matrix::PPTX,
                \local_elediaai_sources\format_matrix::XLSX,
            ],
            array_column($sent, 'content_type')
        );
        $this->assertSame(['file1', 'file2', 'file3'], array_column($sent, 'suffix'));
        $this->assertSame([], array_filter($documents, static fn($d) => !empty($d['skipped'])));
    }

    /**
     * Test that extract_documents emits one document per file, each with its
     * native content type and a distinct suffix, plus the description.
     */
    public function test_extract_documents_per_file(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $folder = $this->getDataGenerator()->create_module('folder', [
            'course' => $course->id,
            'name' => 'Readings',
            'intro' => '<p>Weekly readings.</p>',
            'introformat' => FORMAT_HTML,
        ]);

        $this->add_file_to_folder($folder, 'lecture1.pdf', '%PDF-1.7 first', 'application/pdf');
        $this->add_file_to_folder($folder, 'lecture2.pdf', '%PDF-1.7 second', 'application/pdf');
        $this->add_file_to_folder($folder, 'notes.txt', 'Plain notes.', 'text/plain');

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($folder->cmid);

        $docs = (new \aisourcesextractor_folder\extractor())->extract_documents($cm);

        // Description + three files.
        $this->assertCount(4, $docs);

        // Suffixes are unique.
        $suffixes = array_column($docs, 'suffix');
        $this->assertSame($suffixes, array_unique($suffixes));
        $this->assertContains('intro', $suffixes);

        // Both PDFs are present with their native content type (not flattened).
        $pdfs = array_filter($docs, static fn($d) => $d['content_type'] === 'application/pdf');
        $this->assertCount(2, $pdfs);

        // The plain-text file keeps text/plain.
        $texts = array_filter($docs, static fn($d) => $d['content_type'] === 'text/plain'
            && $d['title'] === 'notes.txt');
        $this->assertCount(1, $texts);
    }

    /**
     * Helper to add a file to a folder module's content area.
     *
     * @param \stdClass $folder The folder module record.
     * @param string $filename The filename.
     * @param string $content The file content.
     * @param string $mimetype The MIME type.
     */
    private function add_file_to_folder(
        \stdClass $folder,
        string $filename,
        string $content,
        string $mimetype,
    ): void {
        $context = \core\context\module::instance($folder->cmid);
        $fs = get_file_storage();

        $filerecord = [
            'contextid' => $context->id,
            'component' => 'mod_folder',
            'filearea' => 'content',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $filename,
            'mimetype' => $mimetype,
        ];
        $fs->create_file_from_string($filerecord, $content);
    }
}

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
 * Unit tests for the H5P embed helper.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_sources\h5p_embed_helper
 */
final class h5p_embed_helper_test extends \advanced_testcase {
    /**
     * Test that HTML without any H5P placeholders passes through unchanged.
     */
    public function test_no_placeholders_passthrough(): void {
        $html = '<p>Regular content without any H5P embedding at all.</p>';

        $result = h5p_embed_helper::resolve_h5p_placeholders($html);

        $this->assertEquals($html, $result);
    }

    /**
     * Test that a placeholder with an unresolvable URL is stripped.
     */
    public function test_unresolvable_placeholder_stripped(): void {
        $this->resetAfterTest();

        $html = '<p>Before the placeholder.</p>' .
                '<div class="h5p-placeholder" contenteditable="false">' .
                'https://example.com/pluginfile.php/999/contentbank/public/1/nonexistent.h5p' .
                '</div>' .
                '<p>After the placeholder.</p>';

        $result = h5p_embed_helper::resolve_h5p_placeholders($html);

        $this->assertStringContainsString('Before the placeholder.', $result);
        $this->assertStringContainsString('After the placeholder.', $result);
        $this->assertStringNotContainsString('h5p-placeholder', $result);
        $this->assertStringNotContainsString('nonexistent.h5p', $result);
    }

    /**
     * Test that a placeholder with an empty URL is stripped.
     */
    public function test_empty_placeholder_stripped(): void {
        $html = '<p>Content here.</p>' .
                '<div class="h5p-placeholder" contenteditable="false"></div>' .
                '<p>More content.</p>';

        $result = h5p_embed_helper::resolve_h5p_placeholders($html);

        $this->assertStringContainsString('Content here.', $result);
        $this->assertStringContainsString('More content.', $result);
        $this->assertStringNotContainsString('h5p-placeholder', $result);
    }

    /**
     * Test that non-.h5p URLs inside placeholders are stripped.
     */
    public function test_non_h5p_url_stripped(): void {
        $html = '<div class="h5p-placeholder">https://example.com/file.pdf</div>';

        $result = h5p_embed_helper::resolve_h5p_placeholders($html);

        $this->assertEquals('', $result);
    }

    /**
     * Test that a resolvable placeholder is replaced with extracted text.
     */
    public function test_resolvable_placeholder_replaced(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create an H5P file in the content bank and a deployed h5p record.
        $context = \core\context\system::instance();
        $fs = get_file_storage();

        $filerecord = [
            'contextid' => $context->id,
            'component' => 'contentbank',
            'filearea' => 'public',
            'itemid' => 42,
            'filepath' => '/',
            'filename' => 'quiz.h5p',
        ];
        $file = $fs->create_file_from_string($filerecord, 'fake-h5p-package-content');

        // Insert a deployed h5p record linked to this file.
        $h5precord = new \stdClass();
        $h5precord->jsoncontent = json_encode([
            'params' => [
                'question' => '<p>What colour is the sky on a clear sunny day?</p>',
                'answers' => [
                    ['text' => '<p>Blue is the correct answer here</p>'],
                    ['text' => '<p>Red is not quite right for a clear day</p>'],
                ],
            ],
        ]);
        $h5precord->mainlibraryid = 1;
        $h5precord->displayoptions = 0;
        $h5precord->pathnamehash = $file->get_pathnamehash();
        $h5precord->contenthash = $file->get_contenthash();
        $h5precord->filtered = '';
        $h5precord->timecreated = time();
        $h5precord->timemodified = time();
        $DB->insert_record('h5p', $h5precord);

        // Build a URL matching the file we just created.
        $url = "https://example.com/pluginfile.php/{$context->id}/contentbank/public/42/quiz.h5p";

        $html = '<p>Introduction text for this module.</p>' .
                '<div class="h5p-placeholder" contenteditable="false">' . $url . '</div>' .
                '<p>Conclusion of the label content here.</p>';

        $result = h5p_embed_helper::resolve_h5p_placeholders($html);

        $this->assertStringContainsString('Introduction text for this module.', $result);
        $this->assertStringContainsString('Conclusion of the label content here.', $result);
        $this->assertStringNotContainsString('h5p-placeholder', $result);
        $this->assertStringNotContainsString('quiz.h5p', $result);
        // The H5P text should now appear inline.
        $this->assertStringContainsString('What colour is the sky', $result);
        $this->assertStringContainsString('Blue is the correct answer here', $result);

        $course = $this->getDataGenerator()->create_course();
        $restricted = h5p_embed_helper::resolve_h5p_placeholders($html, (int) $course->id);
        $this->assertStringContainsString('Introduction text for this module.', $restricted);
        $this->assertStringNotContainsString('What colour is the sky', $restricted);
        $this->assertStringNotContainsString('quiz.h5p', $restricted);
    }

    /**
     * Test that multiple placeholders are each handled independently.
     */
    public function test_multiple_placeholders(): void {
        $this->resetAfterTest();

        $html = '<p>Text before.</p>' .
                '<div class="h5p-placeholder">https://example.com/pluginfile.php/1/contentbank/public/1/a.h5p</div>' .
                '<p>Text between the two placeholders here.</p>' .
                '<div class="h5p-placeholder">https://example.com/pluginfile.php/2/contentbank/public/2/b.h5p</div>' .
                '<p>Text after.</p>';

        $result = h5p_embed_helper::resolve_h5p_placeholders($html);

        // Both placeholders should be stripped (unresolvable).
        $this->assertStringContainsString('Text before.', $result);
        $this->assertStringContainsString('Text between the two placeholders here.', $result);
        $this->assertStringContainsString('Text after.', $result);
        $this->assertStringNotContainsString('h5p-placeholder', $result);
    }
}

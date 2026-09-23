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

/**
 * Upload source extractor tests.
 *
 * @package    local_elediaai_core
 * @category   test
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core;

use advanced_testcase;
use local_elediaai_core\content\upload_text_extractor;

defined('MOODLE_INTERNAL') || die();

/**
 * Reading text out of the files a teacher drops in.
 *
 * @covers \local_elediaai_core\content\upload_text_extractor
 */
final class upload_text_extractor_test extends advanced_testcase {
    /**
     * Plain text arrives unchanged apart from normalisation.
     */
    public function test_extract_keeps_plain_text(): void {
        $this->assertSame("Rain forms\nfrom condensation.", upload_text_extractor::extract(
            "Rain forms\nfrom condensation.",
            'source.txt'
        ));
    }

    /**
     * Extract keeps markdown text.
     */
    public function test_extract_keeps_markdown_text(): void {
        $text = upload_text_extractor::extract("# Rain\n\nWater **condenses** in clouds.", 'source.md');

        $this->assertStringContainsString('# Rain', $text);
        $this->assertStringContainsString('Water **condenses** in clouds.', $text);
    }

    /**
     * Extract converts html to text.
     */
    public function test_extract_converts_html_to_text(): void {
        $text = upload_text_extractor::extract('<h1>Rain</h1><p>Water vapour condenses.</p>', 'source.html');

        $this->assertStringContainsString('RAIN', $text);
        $this->assertStringContainsString('Water vapour condenses.', $text);
    }

    /**
     * Extract reads simple text pdf stream.
     */
    public function test_extract_reads_simple_text_pdf_stream(): void {
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Length 46 >>\nstream\nBT (Rain forms from condensation.) Tj ET\nendstream\nendobj\n%%EOF";

        $this->assertSame('Rain forms from condensation.', upload_text_extractor::extract($pdf, 'source.pdf'));
    }

    /**
     * Extract reads docx document xml.
     */
    public function test_extract_reads_docx_document_xml(): void {
        if (!class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive is required for DOCX extraction.');
        }

        $tempfile = tempnam(make_temp_directory('local_elediaai_core_tests'), 'docx');
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tempfile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        $zip->addFromString(
            'word/document.xml',
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body><w:p><w:r><w:t>Rain forms</w:t></w:r></w:p>'
            . '<w:p><w:r><w:t>from condensation.</w:t></w:r></w:p></w:body></w:document>'
        );
        $zip->close();

        $content = file_get_contents($tempfile);
        @unlink($tempfile);

        $this->assertSame("Rain forms\nfrom condensation.", upload_text_extractor::extract((string) $content, 'source.docx'));
    }

    /**
     * Extract reads legacy doc text runs.
     */
    public function test_extract_reads_legacy_doc_text_runs(): void {
        $doc = "binary\0junk\0R\0a\0i\0n\0 \0f\0o\0r\0m\0s\0.\0more-binary";

        $this->assertStringContainsString('Rain forms.', upload_text_extractor::extract($doc, 'source.doc'));
    }

    /**
     * Extract removes invalid utf8 from pdf stream.
     */
    public function test_extract_removes_invalid_utf8_from_pdf_stream(): void {
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Length 24 >>\nstream\nBT (Rain \xD2 forms.) Tj ET\nendstream\nendobj\n%%EOF";
        $text = upload_text_extractor::extract($pdf, 'source.pdf');

        $this->assertSame('Rain forms.', $text);
        $this->assertTrue(mb_check_encoding($text, 'UTF-8'));
    }
}

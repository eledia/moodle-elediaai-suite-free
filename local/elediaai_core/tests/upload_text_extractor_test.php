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

    /**
     * A PDF from Word or LibreOffice keeps its text in embedded fonts; the bundled parser reads it (#196).
     */
    public function test_extract_reads_a_pdf_with_embedded_fonts(): void {
        $this->resetAfterTest();
        set_config('pdftotext_path', '', 'local_elediaai_core');
        $pdf = (string) file_get_contents(__DIR__ . '/fixtures/word_embedded_fonts.pdf');

        $text = upload_text_extractor::extract($pdf, 'skript.pdf');

        $this->assertStringContainsString('Brüche kürzen und erweitern', $text);
        $this->assertStringContainsString('Zähler und einem Nenner', $text);
        $this->assertTrue(mb_check_encoding($text, 'UTF-8'));
    }

    /**
     * A broken PDF gives an empty text, never an exception to the person (#196).
     */
    public function test_extract_returns_empty_for_a_broken_pdf(): void {
        $this->resetAfterTest();
        set_config('pdftotext_path', '', 'local_elediaai_core');

        $this->assertSame('', upload_text_extractor::extract("%PDF-1.4\nnot really a pdf", 'kaputt.pdf'));
        $this->assertSame('', upload_text_extractor::extract('', 'leer.pdf'));
    }

    /**
     * A configured pdftotext binary is tried first (#196).
     */
    public function test_extract_prefers_a_configured_pdftotext_binary(): void {
        $this->resetAfterTest();
        $script = make_request_directory() . '/fake-pdftotext';
        file_put_contents($script, "#!/bin/sh\necho 'Text vom Programm'\n");
        chmod($script, 0755);
        set_config('pdftotext_path', $script, 'local_elediaai_core');
        $pdf = (string) file_get_contents(__DIR__ . '/fixtures/word_embedded_fonts.pdf');

        $this->assertSame('Text vom Programm', upload_text_extractor::extract($pdf, 'skript.pdf'));
    }

    /**
     * A binary that finds no text, or does not exist, falls through to the bundled parser (#196).
     */
    public function test_extract_falls_through_when_the_binary_finds_nothing(): void {
        $this->resetAfterTest();
        $pdf = (string) file_get_contents(__DIR__ . '/fixtures/word_embedded_fonts.pdf');

        $script = make_request_directory() . '/empty-pdftotext';
        file_put_contents($script, "#!/bin/sh\nexit 0\n");
        chmod($script, 0755);
        set_config('pdftotext_path', $script, 'local_elediaai_core');
        $this->assertStringContainsString('Brüche kürzen', upload_text_extractor::extract($pdf, 'a.pdf'));

        set_config('pdftotext_path', '/nicht/vorhanden/pdftotext', 'local_elediaai_core');
        $this->assertStringContainsString('Brüche kürzen', upload_text_extractor::extract($pdf, 'b.pdf'));
    }

    /**
     * `$CFG->pathtopdftotext` counts when the setting is empty (#196).
     */
    public function test_the_binary_path_falls_back_to_the_site_config(): void {
        global $CFG;
        $this->resetAfterTest();
        set_config('pdftotext_path', '', 'local_elediaai_core');
        $CFG->pathtopdftotext = '/usr/bin/pdftotext';
        $this->assertSame('/usr/bin/pdftotext', upload_text_extractor::pdftotext_path());

        set_config('pdftotext_path', '/opt/pdftotext', 'local_elediaai_core');
        $this->assertSame('/opt/pdftotext', upload_text_extractor::pdftotext_path());
    }

    /**
     * A binary that prints only a form feed per page, or that fails, finds no text; the parser takes over (#196).
     */
    public function test_extract_falls_through_when_the_binary_prints_only_form_feeds_or_fails(): void {
        $this->resetAfterTest();
        $pdf = (string) file_get_contents(__DIR__ . '/fixtures/word_embedded_fonts.pdf');

        $formfeed = make_request_directory() . '/formfeed-pdftotext';
        file_put_contents($formfeed, "#!/bin/sh\nprintf '\\f'\n");
        chmod($formfeed, 0755);
        set_config('pdftotext_path', $formfeed, 'local_elediaai_core');
        $this->assertStringContainsString('Brüche kürzen', upload_text_extractor::extract($pdf, 'a.pdf'));

        $failing = make_request_directory() . '/failing-pdftotext';
        file_put_contents($failing, "#!/bin/sh\necho 'halber Text'\nexit 1\n");
        chmod($failing, 0755);
        set_config('pdftotext_path', $failing, 'local_elediaai_core');
        $this->assertStringContainsString('Brüche kürzen', upload_text_extractor::extract($pdf, 'b.pdf'));
    }

    /**
     * A directory is no binary (#196).
     */
    public function test_a_directory_as_binary_path_is_ignored(): void {
        $this->resetAfterTest();
        set_config('pdftotext_path', '/usr/bin', 'local_elediaai_core');
        $pdf = (string) file_get_contents(__DIR__ . '/fixtures/word_embedded_fonts.pdf');

        $this->assertStringContainsString('Brüche kürzen', upload_text_extractor::extract($pdf, 'c.pdf'));
    }

    /**
     * An oversized PDF gives no text instead of a long request (#196).
     */
    public function test_an_oversized_pdf_gives_no_text(): void {
        $this->resetAfterTest();
        set_config('pdftotext_path', '', 'local_elediaai_core');
        $small = (string) file_get_contents(__DIR__ . '/fixtures/word_embedded_fonts.pdf');
        $this->assertNotSame('', upload_text_extractor::extract($small, 'klein.pdf'), 'The same PDF is readable at normal size.');
        $big = $small . str_repeat("\n", upload_text_extractor::MAX_PDF_BYTES);

        $this->assertSame('', upload_text_extractor::extract($big, 'gross.pdf'));
    }
}

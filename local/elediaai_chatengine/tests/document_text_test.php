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

declare(strict_types=1);

namespace local_elediaai_chatengine;

use local_elediaai_chatengine\local\document_text;

/**
 * Reading an uploaded document as text.
 *
 * The formats a placement is allowed to offer are the formats this can read,
 * so these tests use real files rather than mimetypes on empty strings.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_chatengine\local\document_text
 */
final class document_text_test extends \advanced_testcase {
    /**
     * Store a file somewhere it can be read back from.
     *
     * @param string $filename The name to store it under.
     * @param string $content The file content.
     * @param string $mimetype The mimetype to record.
     * @return \stored_file The stored file.
     */
    private function store(string $filename, string $content, string $mimetype = 'text/plain'): \stored_file {
        return get_file_storage()->create_file_from_string([
            'contextid' => \core\context\system::instance()->id,
            'component' => 'local_elediaai_chatengine',
            'filearea' => 'unittest',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $filename,
            'mimetype' => $mimetype,
        ], $content);
    }

    /**
     * A minimal but real PDF carrying one line of text.
     *
     * Built here rather than shipped as a binary fixture: the point is that a
     * PDF is read at all, and a generated one makes what is being read visible
     * in the test instead of hiding it in an opaque file.
     *
     * @param string $text The text to place on the page.
     * @return string The PDF bytes.
     */
    private function pdf_bytes(string $text): string {
        $stream = 'BT /F1 24 Tf 72 720 Td (' . $text . ') Tj ET';
        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] "
                . "/Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n",
            "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
            "5 0 obj\n<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream\nendobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }
        $startxref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf . "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n"
            . $startxref . "\n%%EOF\n";
    }

    /**
     * A minimal but real .docx carrying two paragraphs.
     *
     * @param string $first The first paragraph.
     * @param string $second The second paragraph.
     * @return string The .docx bytes.
     */
    private function docx_bytes(string $first, string $second): string {
        $path = make_request_directory() . '/reference.docx';
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString(
            '[Content_Types].xml',
            '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="xml" ContentType="application/xml"/></Types>'
        );
        $zip->addFromString(
            'word/document.xml',
            '<?xml version="1.0"?><w:document '
                . 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
                . '<w:p><w:r><w:t>' . $first . '</w:t></w:r></w:p>'
                . '<w:p><w:r><w:t>' . $second . '</w:t></w:r></w:p>'
                . '</w:body></w:document>'
        );
        $zip->close();

        return (string) file_get_contents($path);
    }

    /**
     * The two formats that need nothing installed are always read.
     *
     * @return void
     */
    public function test_docx_and_text_are_always_read(): void {
        $this->resetAfterTest();

        $docx = $this->store(
            'contract.docx',
            $this->docx_bytes('The contract runs for twelve months.', 'Notice period is four weeks.'),
            document_text::DOCX_MIMETYPE
        );
        $txt = $this->store('notes.txt', 'Delivered on the third of May.');

        $docxtext = (string) document_text::extract($docx);
        $this->assertStringContainsString('The contract runs for twelve months.', $docxtext);
        // Both paragraphs, not just the first, and not glued together.
        $this->assertStringContainsString('Notice period is four weeks.', $docxtext);
        $this->assertStringContainsString('Delivered on the third of May.', (string) document_text::extract($txt));
    }

    /**
     * A PDF is read where a reader is installed.
     *
     * PDF is the one optional format: the reader lives in local_literag, and a
     * site without it is not offered the upload in the first place. Asserting
     * it unconditionally would fail on a site that is behaving correctly.
     *
     * @return void
     */
    public function test_a_pdf_is_read_where_a_reader_exists(): void {
        $this->resetAfterTest();
        if (!document_text::pdf_supported()) {
            $this->markTestSkipped('No PDF reader installed on this site.');
        }

        $file = $this->store('invoice.pdf', $this->pdf_bytes('Invoice total is 240 euro'), 'application/pdf');

        $this->assertStringContainsString('Invoice total is 240 euro', (string) document_text::extract($file));
    }

    /**
     * A text file that is not UTF-8 keeps its characters.
     *
     * Windows editors routinely save .txt as Windows-1252. Passed on unchanged
     * those bytes survive only as replacement characters, so material reaches
     * the model spelled wrong and nobody is told.
     *
     * @return void
     */
    public function test_a_windows_encoded_text_file_keeps_its_characters(): void {
        $this->resetAfterTest();
        $file = $this->store(
            'terms.txt',
            (string) mb_convert_encoding('Rückgabe binnen 14 Tagen, Größe egal.', 'Windows-1252', 'UTF-8')
        );

        $text = (string) document_text::extract($file);

        $this->assertStringContainsString('Rückgabe binnen 14 Tagen, Größe egal.', $text);
        $this->assertTrue(mb_check_encoding($text, 'UTF-8'));
    }

    /**
     * A PDF without a text layer reads as nothing rather than as an error.
     *
     * What that means for the learner is the placement's call, not this one's.
     *
     * @return void
     */
    public function test_a_pdf_without_text_yields_nothing(): void {
        $this->resetAfterTest();
        $file = $this->store('scan.pdf', $this->pdf_bytes(''), 'application/pdf');

        $this->assertSame('', trim((string) document_text::extract($file)));
    }

    /**
     * A format we never claimed is not claimed.
     *
     * @return void
     */
    public function test_an_unknown_format_is_not_supported(): void {
        $this->resetAfterTest();
        $file = $this->store('sheet.ods', 'binary', 'application/vnd.oasis.opendocument.spreadsheet');

        $this->assertFalse(document_text::supports($file));
        $this->assertNull(document_text::extract($file));
    }

    /**
     * PDF is only offered where it can be read.
     *
     * @return void
     */
    public function test_accepted_extensions_follow_what_can_be_read(): void {
        $this->resetAfterTest();

        $accepted = document_text::accepted_extensions();

        $this->assertContains('.txt', $accepted);
        $this->assertContains('.docx', $accepted);
        $this->assertSame(document_text::pdf_supported(), in_array('.pdf', $accepted, true));
    }
}

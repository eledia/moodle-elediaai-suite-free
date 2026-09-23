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

namespace local_literag;

use local_literag\local\chunker;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for deterministic chunking and text extraction.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(chunker::class)]
final class chunker_test extends \advanced_testcase {
    /**
     * Same input must always yield identical chunks and hashes.
     */
    public function test_chunk_determinism(): void {
        $chunker = new chunker(200, 40);
        $text = str_repeat('The quick brown fox jumps over the lazy dog. ', 40);

        $a = $chunker->chunk($text);
        $b = $chunker->chunk($text);

        $this->assertSame($a, $b);
        $this->assertGreaterThan(1, count($a));
        foreach ($a as $i => $chunk) {
            $this->assertSame(chunker::hash($chunk), chunker::hash($b[$i]));
        }
    }

    /**
     * Chunks should respect the size bound (plus a small word-boundary slack).
     */
    public function test_chunk_size_bound(): void {
        $size = 300;
        $chunker = new chunker($size, 50);
        $text = str_repeat('alpha beta gamma delta ', 200);

        foreach ($chunker->chunk($text) as $chunk) {
            $this->assertLessThanOrEqual($size + 1, \core_text::strlen($chunk));
        }
    }

    /**
     * Short text returns a single chunk.
     */
    public function test_short_text_single_chunk(): void {
        $chunker = new chunker(1000, 100);
        $this->assertSame(['Hello world'], $chunker->chunk('Hello world'));
        $this->assertSame([], $chunker->chunk('   '));
    }

    /**
     * HTML extraction strips tags and keeps the text.
     */
    public function test_extract_html(): void {
        $chunker = new chunker(1000, 100);
        $text = $chunker->extract_text('<h1>Title</h1><p>Hello <strong>world</strong></p>', 'text/html');
        $this->assertNotNull($text);
        // HTML rendering upper-cases headings/strong, so compare case-insensitively.
        $this->assertStringContainsStringIgnoringCase('Title', $text);
        $this->assertStringContainsStringIgnoringCase('Hello', $text);
        $this->assertStringContainsStringIgnoringCase('world', $text);
        $this->assertStringNotContainsString('<', $text);
    }

    /**
     * Plain text passes through normalised.
     */
    public function test_extract_plain(): void {
        $chunker = new chunker(1000, 100);
        $text = $chunker->extract_text("Line one\r\n\r\n\r\nLine two", 'text/plain');
        $this->assertSame("Line one\n\nLine two", $text);
    }

    /**
     * A real PDF is extracted by the bundled pure-PHP parser, with no native
     * pdftotext binary configured.
     */
    public function test_extract_pdf_with_bundled_parser(): void {
        $this->resetAfterTest();
        set_config('pdftotext_path', '', 'local_literag');
        $chunker = new chunker(1000, 100);
        $bytes = file_get_contents(__DIR__ . '/fixtures/sample.pdf');

        $text = $chunker->extract_text($bytes, 'application/pdf');

        $this->assertNotNull($text);
        $this->assertStringContainsStringIgnoringCase('Photosynthesis', $text);
        $this->assertStringContainsStringIgnoringCase('glucose', $text);
    }

    /**
     * An unparseable PDF yields null so the caller records the source as skipped.
     */
    public function test_extract_pdf_invalid_is_null(): void {
        $this->resetAfterTest();
        set_config('pdftotext_path', '', 'local_literag');
        $chunker = new chunker(1000, 100);
        $this->assertNull($chunker->extract_text('%PDF-1.4 not a real pdf', 'application/pdf'));
        // The parser logs the failure at developer level; consume that message.
        $this->assertDebuggingCalled();
    }

    /**
     * Source type mapping.
     */
    public function test_source_type(): void {
        $this->assertSame('text', chunker::source_type('text/plain'));
        $this->assertSame('html', chunker::source_type('text/html'));
        $this->assertSame('pdf', chunker::source_type('application/pdf'));
        $this->assertSame('other', chunker::source_type('image/png'));
    }
}

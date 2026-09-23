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

namespace local_literag\local;

/**
 * Deterministic, embeddings-free text extraction and chunking.
 *
 * The same input always yields the same chunks and chunk hashes, so re-ingesting
 * unchanged content is a no-op. Text is extracted per content type (plain as-is,
 * HTML via {@see content_to_text()}, PDF via an optional external pdftotext), then
 * split into overlapping word-safe windows.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chunker {
    /** @var int Target chunk size in characters. */
    private int $size;

    /** @var int Overlap in characters between consecutive chunks. */
    private int $overlap;

    /**
     * Constructor.
     *
     * @param int|null $size Chunk size; defaults to the configured value.
     * @param int|null $overlap Overlap; defaults to the configured value.
     */
    public function __construct(?int $size = null, ?int $overlap = null) {
        $this->size = $size ?? config::chunk_size();
        $this->overlap = $overlap ?? config::chunk_overlap();
        if ($this->overlap >= $this->size) {
            $this->overlap = (int) floor($this->size / 4);
        }
    }

    /**
     * Map an ingestion content_type to a short source type tag.
     *
     * @param string $contenttype MIME type.
     * @return string text|html|pdf|other
     */
    public static function source_type(string $contenttype): string {
        switch (\core_text::strtolower(trim($contenttype))) {
            case 'text/plain':
                return 'text';
            case 'text/html':
                return 'html';
            case 'application/pdf':
                return 'pdf';
            default:
                return 'other';
        }
    }

    /**
     * Extract plain UTF-8 text from decoded document content.
     *
     * @param string $content Decoded (not base64) document body.
     * @param string $contenttype MIME type.
     * @return string|null Plain text, or null when extraction is not possible
     *                     (e.g. a PDF with no pdftotext configured).
     */
    public function extract_text(string $content, string $contenttype): ?string {
        switch (self::source_type($contenttype)) {
            case 'text':
                return $this->normalise($content);
            case 'html':
                return $this->normalise(content_to_text($content, FORMAT_HTML));
            case 'pdf':
                $text = $this->extract_pdf($content);
                return $text === null ? null : $this->normalise($text);
            default:
                // Unknown type: best effort as plain text.
                return $this->normalise($content);
        }
    }

    /**
     * Split text into deterministic, word-safe, overlapping chunks.
     *
     * @param string $text Plain text.
     * @return string[] Ordered chunk strings (empty when text is blank).
     */
    public function chunk(string $text): array {
        $text = $this->normalise($text);
        $len = \core_text::strlen($text);
        if ($len === 0) {
            return [];
        }
        if ($len <= $this->size) {
            return [$text];
        }

        $chunks = [];
        $pos = 0;
        while ($pos < $len) {
            $end = min($pos + $this->size, $len);

            // Back off to the last whitespace so we do not cut mid-word, but only
            // when it keeps at least half the window (avoids tiny fragments).
            if ($end < $len) {
                $window = \core_text::substr($text, $pos, $end - $pos);
                $lastspace = $this->last_whitespace($window);
                if ($lastspace !== null && $lastspace >= (int) floor(($end - $pos) / 2)) {
                    $end = $pos + $lastspace;
                }
            }

            $piece = trim(\core_text::substr($text, $pos, $end - $pos));
            if ($piece !== '') {
                $chunks[] = $piece;
            }

            if ($end >= $len) {
                break;
            }

            // Advance with overlap, always making forward progress.
            $next = $end - $this->overlap;
            $pos = $next > $pos ? $next : $end;
        }

        return $chunks;
    }

    /**
     * Stable hash for a chunk (deduplication / idempotency).
     *
     * @param string $chunk
     * @return string sha1 hash.
     */
    public static function hash(string $chunk): string {
        return sha1($chunk);
    }

    /**
     * Normalise whitespace deterministically (UTF-8 safe).
     *
     * @param string $text
     * @return string
     */
    private function normalise(string $text): string {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        // Collapse runs of spaces/tabs.
        $text = preg_replace('/[ \t]+/u', ' ', $text);
        // Collapse 3+ newlines to a paragraph break.
        $text = preg_replace('/\n{3,}/u', "\n\n", $text);
        // Trim trailing spaces on each line.
        $text = preg_replace('/ +\n/u', "\n", $text);
        return trim((string) $text);
    }

    /**
     * Index of the last whitespace char in a string, or null.
     *
     * @param string $s
     * @return int|null
     */
    private function last_whitespace(string $s): ?int {
        if (preg_match_all('/\s/u', $s, $m, PREG_OFFSET_CAPTURE)) {
            $last = end($m[0]);
            // PREG offsets are byte offsets; convert via substr length in chars.
            $byteoffset = (int) $last[1];
            return \core_text::strlen(substr($s, 0, $byteoffset));
        }
        return null;
    }

    /**
     * Extract text from PDF bytes.
     *
     * Core Moodle has no PDF text-extraction API, so extraction is done by the
     * bundled pure-PHP smalot/pdfparser library — PDFs are searchable out of the
     * box on any platform. A site that configures a native pdftotext binary gets
     * that higher-fidelity path first; the PHP parser is the fallback. Returns
     * null only when neither path can extract text (the caller then records the
     * source as skipped).
     *
     * @param string $pdfbytes Raw PDF content.
     * @return string|null Extracted text, or null when extraction is not possible.
     */
    private function extract_pdf(string $pdfbytes): ?string {
        $dir = make_request_directory();
        $tmp = $dir . '/in.pdf';
        if (file_put_contents($tmp, $pdfbytes) === false) {
            return null;
        }

        // Prefer a configured native pdftotext binary (best fidelity).
        $bin = config::pdftotext_path();
        if ($bin !== '' && is_executable($bin)) {
            $cmd = escapeshellarg($bin) . ' -enc UTF-8 -q ' . escapeshellarg($tmp) . ' -';
            $output = shell_exec($cmd);
            if (is_string($output) && trim($output) !== '') {
                return $output;
            }
        }

        // Pure-PHP fallback (bundled smalot/pdfparser), works everywhere.
        return $this->extract_pdf_php($tmp);
    }

    /**
     * Extract text from a PDF file using the bundled pure-PHP parser.
     *
     * @param string $path Path to the PDF file.
     * @return string|null Extracted text, or null on failure.
     */
    private function extract_pdf_php(string $path): ?string {
        require_once(__DIR__ . '/../../vendor/smalot/pdfparser/autoload.php');
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $text = $parser->parseFile($path)->getText();
        } catch (\Throwable $e) {
            debugging('local_literag: PDF text extraction failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }
        return trim($text) === '' ? null : $text;
    }
}

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
 * Extract prompt text from uploaded source files.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core\content;

defined('MOODLE_INTERNAL') || die();

/**
 * Lightweight extractor for teacher-uploaded source documents.
 *
 * It lives in the core plugin because more than one tool starts from a file
 * a teacher drops in: the question generator has always done it, and the
 * course author needs the same text to build a course from a script. The
 * sibling {@see content_extractor} does the same job for material that is
 * already an activity in a course.
 *
 * Deliberately dependency-free: docx and pdf are read with the means PHP
 * brings along. That is enough for text documents and fails honestly on
 * scanned pages -- an OCR stack is a different decision.
 */
final class upload_text_extractor {
    /**
     * Plain text from an uploaded file, chosen by its extension.
     *
     * @param string $content Raw file content.
     * @param string $filename Original file name; only its extension is read.
     * @return string Normalised plain text, empty when nothing could be read.
     */
    public static function extract(string $content, string $filename): string {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $text = match ($extension) {
            'docx' => self::extract_docx_text($content),
            'doc' => self::extract_legacy_doc_text($content),
            'html', 'htm' => html_to_text($content, 0, false),
            'md', 'markdown', 'txt' => $content,
            'pdf' => self::extract_pdf_text($content),
            default => $content,
        };
        return self::normalise_text($text);
    }

    /**
     * Extract text from modern Word documents by reading word/document.xml.
     *
     * @param string $content
     * @return string
     */
    private static function extract_docx_text(string $content): string {
        if (!class_exists(\ZipArchive::class)) {
            return '';
        }

        $tempfile = tempnam(make_temp_directory('local_elediaai_questiongen'), 'docx');
        if ($tempfile === false) {
            return '';
        }

        file_put_contents($tempfile, $content);
        $zip = new \ZipArchive();
        if ($zip->open($tempfile) !== true) {
            @unlink($tempfile);
            return '';
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($tempfile);
        if ($xml === false || trim((string) $xml) === '') {
            return '';
        }

        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML((string) $xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return '';
        }

        $xpath = new \DOMXPath($dom);
        $parts = [];
        foreach ($xpath->query('//*[local-name()="p"]') ?: [] as $paragraph) {
            $text = '';
            $inline = './/*[local-name()="t" or local-name()="tab" or local-name()="br"]';
            foreach ($xpath->query($inline, $paragraph) ?: [] as $node) {
                $name = $node->localName;
                if ($name === 'tab') {
                    $text .= "\t";
                } else if ($name === 'br') {
                    $text .= "\n";
                } else {
                    $text .= $node->textContent;
                }
            }
            $text = trim($text);
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return implode("\n", $parts);
    }

    /**
     * Best-effort text extraction for legacy binary .doc files.
     *
     * @param string $content
     * @return string
     */
    private static function extract_legacy_doc_text(string $content): string {
        $parts = [];
        if (preg_match_all('/(?:[\x20-\x7E]\x00){4,}/', $content, $matches)) {
            foreach ($matches[0] as $match) {
                $parts[] = str_replace("\0", '', $match);
            }
        }
        if (preg_match_all('/[\x20-\x7E]{4,}/', $content, $matches)) {
            foreach ($matches[0] as $match) {
                $parts[] = $match;
            }
        }
        return implode("\n", array_unique(array_filter(array_map('trim', $parts))));
    }

    /**
     * Best-effort text extraction for text-based PDFs.
     *
     * This intentionally does not OCR scanned PDFs. It decodes plain and
     * Flate-compressed content streams, then pulls literal text operands
     * used by common PDF text operators.
     *
     * @param string $content
     * @return string
     */
    private static function extract_pdf_text(string $content): string {
        $streams = [];
        if (preg_match_all('/(<<.*?>>)\s*stream\r?\n(.*?)\r?\nendstream/s', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $dict = (string) $match[1];
                $stream = (string) $match[2];
                if (str_contains($dict, '/FlateDecode')) {
                    $decoded = @gzuncompress($stream);
                    if ($decoded === false) {
                        $decoded = @gzdecode($stream);
                    }
                    if ($decoded !== false) {
                        $stream = $decoded;
                    }
                }
                $streams[] = $stream;
            }
        }
        if (empty($streams)) {
            $streams[] = $content;
        }

        $parts = [];
        foreach ($streams as $stream) {
            if (preg_match_all('/\((?:\\\\.|[^\\\\)])*\)\s*(?:Tj|TJ|\'|")/s', $stream, $matches)) {
                foreach ($matches[0] as $token) {
                    if (preg_match_all('/\((?:\\\\.|[^\\\\)])*\)/s', $token, $strings)) {
                        foreach ($strings[0] as $literal) {
                            $parts[] = self::decode_pdf_literal(substr($literal, 1, -1));
                        }
                    }
                }
            }
        }

        return implode("\n", array_filter(array_map('trim', $parts)));
    }

    /**
     * Decode the escape sequences of a PDF string literal.
     *
     * @param string $literal
     * @return string
     */
    private static function decode_pdf_literal(string $literal): string {
        $literal = preg_replace_callback('/\\\\([0-7]{1,3})/', static function (array $matches): string {
            return chr(octdec($matches[1]));
        }, $literal);
        $replacements = [
            '\\n' => "\n",
            '\\r' => "\n",
            '\\t' => "\t",
            '\\b' => '',
            '\\f' => '',
            '\\(' => '(',
            '\\)' => ')',
            '\\\\' => '\\',
        ];
        return strtr((string) $literal, $replacements);
    }

    /**
     * Reduce extracted text to something a prompt can carry.
     *
     * @param string $text Raw extracted text.
     * @return string
     */
    private static function normalise_text(string $text): string {
        $text = self::ensure_utf8($text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', ' ', $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\R{3,}/', "\n\n", (string) $text);
        return trim((string) $text);
    }

    /**
     * PostgreSQL rejects non-UTF-8 payloads. PDF content streams can contain
     * arbitrary bytes even when a few literal text operands are readable, so
     * strip invalid sequences before the text reaches job storage.
     *
     * @param string $text
     * @return string
     */
    private static function ensure_utf8(string $text): string {
        if (function_exists('mb_check_encoding') && mb_check_encoding($text, 'UTF-8')) {
            return $text;
        }

        $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
        if ($converted !== false) {
            return $converted;
        }

        return (string) preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '', $text);
    }
}

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

namespace local_elediaai_chatengine\local;

/**
 * Reads an uploaded document as plain text.
 *
 * Here rather than in a placement. A placement knows what it wants to say and
 * what material it stands on; which backend happens to be installed is not its
 * business. The PDF reader lives in local_literag, so an activity that called
 * it directly was coupled to one particular backend - and did not even declare
 * it. That knowledge belongs where the other backend knowledge already is.
 *
 * Plain text and .docx are read here and need nothing installed. A PDF needs a
 * reader; when none is present, PDF is simply not among the accepted types and
 * a teacher is never offered an upload that would fail later.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class document_text {
    /** @var string Mimetype Moodle assigns to .docx files. */
    public const DOCX_MIMETYPE = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    /**
     * Whether a PDF can be read on this site.
     *
     * @return bool
     */
    public static function pdf_supported(): bool {
        return class_exists(\local_literag\local\chunker::class);
    }

    /**
     * The file extensions a form may accept for reference material.
     *
     * @return string[] Extensions including the leading dot.
     */
    public static function accepted_extensions(): array {
        $types = ['.docx', '.txt'];
        if (self::pdf_supported()) {
            $types[] = '.pdf';
        }

        return $types;
    }

    /**
     * Whether this file is a format we claim to read.
     *
     * @param \stored_file $file The stored file.
     * @return bool
     */
    public static function supports(\stored_file $file): bool {
        $mimetype = (string) $file->get_mimetype();

        return $mimetype === 'text/plain'
            || $mimetype === self::DOCX_MIMETYPE
            || ($mimetype === 'application/pdf' && self::pdf_supported());
    }

    /**
     * The plain text of one document.
     *
     * Returns null rather than throwing: whether an unreadable document is
     * worth interrupting somebody over depends on what the document was for,
     * and that is the caller's judgement, not this one's.
     *
     * @param \stored_file $file The stored file.
     * @return string|null The text, or null when it could not be read.
     */
    public static function extract(\stored_file $file): ?string {
        $mimetype = (string) $file->get_mimetype();

        if ($mimetype === 'text/plain') {
            return self::as_utf8($file->get_content());
        }
        if ($mimetype === self::DOCX_MIMETYPE) {
            return self::docx_text($file->get_content());
        }
        if ($mimetype !== 'application/pdf' || !self::pdf_supported()) {
            return null;
        }

        try {
            $text = (new \local_literag\local\chunker())->extract_text($file->get_content(), $mimetype);
        } catch (\Throwable $e) {
            debugging(
                'Document extraction failed for stored file ' . $file->get_id()
                    . ' with ' . get_class($e) . '.',
                DEBUG_NORMAL
            );

            return null;
        }

        return $text;
    }

    /**
     * Bring file content into UTF-8.
     *
     * A .txt is bytes without a declared encoding, and on Windows it is
     * routinely saved as Windows-1252. Handing those bytes on unchanged does
     * not fail: the escaping downstream replaces every invalid byte, so the
     * material reaches the model with "Rueckgabe" spelled "R?ckgabe" and
     * nobody is told. Converting first keeps the text intact.
     *
     * @param string $content Raw file content.
     * @return string The content as UTF-8.
     */
    private static function as_utf8(string $content): string {
        if (mb_check_encoding($content, 'UTF-8')) {
            return $content;
        }

        // Windows-1252 rather than ISO-8859-1: it is what editors on Windows
        // actually write, and it covers ISO-8859-1 except for control slots.
        return (string) \core_text::convert($content, 'windows-1252', 'utf-8');
    }

    /**
     * Extract plain text from a .docx file.
     *
     * A .docx is a ZIP archive; the visible text lives in word/document.xml as
     * a series of <w:t> runs inside <w:p> paragraphs. This reads that entry
     * directly rather than pulling in a dependency, since Moodle core already
     * ships the zip extension.
     *
     * @param string $content Raw file content.
     * @return string|null Extracted text, or null if it could not be read.
     */
    private static function docx_text(string $content): ?string {
        if (!class_exists(\ZipArchive::class)) {
            return null;
        }

        $path = make_request_directory() . '/document.docx';
        file_put_contents($path, $content);

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return null;
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if ($xml === false) {
            return null;
        }

        // Turn paragraph and tab markup into whitespace before stripping tags,
        // so words from adjacent runs/paragraphs don't get glued together.
        $xml = str_replace(['</w:p>', '<w:tab/>', '<w:br/>'], ["\n", "\t", "\n"], $xml);
        $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');

        return trim(preg_replace('/[ \t]+\n/', "\n", $text));
    }
}

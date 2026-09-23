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

use local_elediaai_sources\sink\sink;
use local_elediaai_sources\sink\sink_manager;

/**
 * The versioned support matrix for document formats.
 *
 * Three places used to keep their own list of MIME types: the resource
 * extractor, the folder extractor and the ingestion manager. They agreed only
 * as long as someone remembered to edit all three, and nothing said what
 * should happen to a format that Moodle can export but the destination cannot
 * read yet. This class is that single list, and it answers both questions:
 * which formats exist at all, and which of them may leave the site right now.
 *
 * A format carries one of two states (AI-75, agreed with AI-48):
 *
 * - {@see STATUS_CORE}: every destination parses it; always offered.
 * - {@see STATUS_NEGOTIATED}: offered only while the *active* destination
 *   announces it (see {@see sink::supported_content_types()}). That is what
 *   keeps "DOCX as soon as the pipeline accepts it" from needing a Moodle
 *   release — and what stops a file being exported into a parser that would
 *   reject it.
 *
 * There is deliberately no third state for "we have not decided yet". Nothing
 * is parsed on this side — files are read from storage and base64-encoded —
 * so every open question about a format (does a spreadsheet survive as usable
 * structure? is a legacy container safe to open?) is a parser question and is
 * answered by the destination not announcing the type. A second gate here
 * would only be a second place to forget. A type nobody announces stays shut
 * either way; a type nobody should ever send simply has no entry below.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_matrix {
    /** @var string Matrix version, quoted in docs/format-support-matrix.md and the API spec. */
    public const VERSION = '1.1';

    /** @var string Parsed by every destination; always offered. */
    public const STATUS_CORE = 'core';

    /** @var string Offered while the active destination announces it. */
    public const STATUS_NEGOTIATED = 'negotiated';

    /** @var string MIME type of a Word document (OOXML). */
    public const DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    /** @var string MIME type of a PowerPoint presentation (OOXML). */
    public const PPTX = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';

    /** @var string MIME type of an Excel workbook (OOXML). */
    public const XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    /**
     * The matrix itself.
     *
     * 'binary' says whether the bytes must travel untouched — no heading may
     * be prepended and no truncation applied. 'extensions' exists because
     * Moodle does not know every MIME type it can store (see {@see resolve()}).
     *
     * @var array<string, array{status: string, binary: bool, extensions: string[]}>
     */
    private const FORMATS = [
        'text/plain' => [
            'status' => self::STATUS_CORE,
            'binary' => false,
            'extensions' => ['txt'],
        ],
        'text/html' => [
            'status' => self::STATUS_CORE,
            'binary' => false,
            'extensions' => ['html', 'htm'],
        ],
        'application/pdf' => [
            'status' => self::STATUS_CORE,
            'binary' => true,
            'extensions' => ['pdf'],
        ],
        'text/markdown' => [
            'status' => self::STATUS_NEGOTIATED,
            'binary' => false,
            'extensions' => ['md', 'markdown'],
        ],
        self::DOCX => [
            'status' => self::STATUS_NEGOTIATED,
            'binary' => true,
            'extensions' => ['docx'],
        ],
        self::PPTX => [
            'status' => self::STATUS_NEGOTIATED,
            'binary' => true,
            'extensions' => ['pptx'],
        ],
        self::XLSX => [
            'status' => self::STATUS_NEGOTIATED,
            'binary' => true,
            'extensions' => ['xlsx'],
        ],
        'text/csv' => [
            'status' => self::STATUS_NEGOTIATED,
            'binary' => false,
            'extensions' => ['csv'],
        ],
        'application/msword' => [
            'status' => self::STATUS_NEGOTIATED,
            'binary' => true,
            'extensions' => ['doc'],
        ],
        'application/vnd.oasis.opendocument.text' => [
            'status' => self::STATUS_NEGOTIATED,
            'binary' => true,
            'extensions' => ['odt'],
        ],
        'application/vnd.oasis.opendocument.spreadsheet' => [
            'status' => self::STATUS_NEGOTIATED,
            'binary' => true,
            'extensions' => ['ods'],
        ],
        'application/vnd.oasis.opendocument.presentation' => [
            'status' => self::STATUS_NEGOTIATED,
            'binary' => true,
            'extensions' => ['odp'],
        ],
    ];

    /**
     * MIME values Moodle hands out when it does not recognise a file.
     *
     * Reaching for the file name is only defensible for these — for anything
     * else Moodle's answer is an answer, and overriding it by extension would
     * be guessing.
     *
     * @var string[]
     */
    private const GENERIC_MIMETYPES = [
        '',
        'document/unknown',
        'application/octet-stream',
    ];

    /**
     * Strip parameters and casing from a MIME value.
     *
     * `text/plain; charset=utf-8` and `Text/Plain` are the same type; both
     * reach us from file storage and from other plugins. Normalising here
     * rather than at each comparison is what keeps the allowlist from
     * rejecting a type it actually supports — the symmetric rule to the one
     * the pipeline applies on its side (AI-48).
     *
     * @param string $contenttype A MIME value, possibly with parameters.
     * @return string The bare, lowercased type.
     */
    public static function normalise(string $contenttype): string {
        $type = trim($contenttype);
        $separator = strpos($type, ';');
        if ($separator !== false) {
            $type = substr($type, 0, $separator);
        }
        return \core_text::strtolower(trim($type));
    }

    /**
     * The MIME type a file should travel under.
     *
     * Moodle's file type list has no entry for Markdown, so a `.md` file is
     * stored as `document/unknown` — the type exists in the matrix but could
     * never be reached from a real file. Only for such generic answers is the
     * extension consulted, and only for extensions the matrix names itself.
     *
     * @param string $contenttype The MIME type Moodle reports.
     * @param string $filename The file name, used only as a fallback.
     * @return string The resolved MIME type, or the normalised input.
     */
    public static function resolve(string $contenttype, string $filename = ''): string {
        $type = self::normalise($contenttype);

        if (isset(self::FORMATS[$type])) {
            return $type;
        }
        if (!in_array($type, self::GENERIC_MIMETYPES, true) || $filename === '') {
            return $type;
        }

        $extension = \core_text::strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($extension === '') {
            return $type;
        }

        foreach (self::FORMATS as $mimetype => $format) {
            if (in_array($extension, $format['extensions'], true)) {
                return $mimetype;
            }
        }

        return $type;
    }

    /**
     * The full matrix, for documentation and tests.
     *
     * @return array<string, array{status: string, binary: bool, extensions: string[]}>
     */
    public static function entries(): array {
        return self::FORMATS;
    }

    /**
     * Whether the matrix knows this type at all.
     *
     * @param string $contenttype A MIME value.
     * @return bool True when the type has an entry.
     */
    public static function is_known(string $contenttype): bool {
        return isset(self::FORMATS[self::normalise($contenttype)]);
    }

    /**
     * Whether content of this type must travel byte-for-byte.
     *
     * An unknown type counts as binary: never modify bytes nobody has
     * described. Callers reject unknown types anyway, so the conservative
     * answer costs nothing and prevents a corrupted document if they stop.
     *
     * @param string $contenttype A MIME value.
     * @return bool True when the content is binary.
     */
    public static function is_binary(string $contenttype): bool {
        $type = self::normalise($contenttype);
        return self::FORMATS[$type]['binary'] ?? true;
    }

    /**
     * The state of one format.
     *
     * @param string $contenttype A MIME value.
     * @return string One of the STATUS_* constants, or '' for an unknown type.
     */
    public static function status(string $contenttype): string {
        return self::FORMATS[self::normalise($contenttype)]['status'] ?? '';
    }

    /**
     * Types every destination is required to parse.
     *
     * @return string[] MIME types.
     */
    public static function core_types(): array {
        return self::by_status(self::STATUS_CORE);
    }

    /**
     * Types a destination may announce to have them offered.
     *
     * @return string[] MIME types.
     */
    public static function negotiable_types(): array {
        return self::by_status(self::STATUS_NEGOTIATED);
    }

    /**
     * The types an extractor may hand over at all.
     *
     * Deliberately not narrowed by the destination: an extractor that asked
     * the destination would have to reach the network to answer "may I read
     * this file", and in a Folder the answer would decide the sub-document
     * numbering — so switching a format on at the service would renumber, and
     * therefore re-upsert, every file behind it. Extractors answer the stable
     * question ("is this a document format we ever export?"); whether the
     * destination can take it today is decided once, in
     * {@see ingestion_manager::prepare_document()}. The price is reading a
     * DOCX that is then skipped; the alternatives cost far more.
     *
     * @return string[] MIME types.
     */
    public static function extractable_types(): array {
        return array_merge(self::core_types(), self::negotiable_types());
    }

    /**
     * The types that may be exported right now.
     *
     * Core types plus every negotiable type the destination announces. Types
     * a destination announces but the matrix does not know are ignored: what
     * Moodle exports stays a decision taken here, not one the other side can
     * make on its own.
     *
     * @param sink|null $sink The destination to ask; the active one when null.
     * @return string[] MIME types, in matrix order.
     */
    public static function offered(?sink $sink = null): array {
        $sink = $sink ?? sink_manager::active();

        $announced = [];
        foreach ($sink->supported_content_types() as $type) {
            $announced[] = self::normalise((string) $type);
        }

        $offered = [];
        foreach (self::FORMATS as $mimetype => $format) {
            $iscore = $format['status'] === self::STATUS_CORE;
            if ($iscore || in_array($mimetype, $announced, true)) {
                $offered[] = $mimetype;
            }
        }

        return $offered;
    }

    /**
     * Whether a document of this type may be exported right now.
     *
     * @param string $contenttype A MIME value.
     * @param sink|null $sink The destination to ask; the active one when null.
     * @return bool True when the type is offered.
     */
    public static function is_offered(string $contenttype, ?sink $sink = null): bool {
        return in_array(self::normalise($contenttype), self::offered($sink), true);
    }

    /**
     * Why a file of this type is not being exported, in the reader's language.
     *
     * Every branch names the file, so a skipped file is traceable to an
     * answer rather than to an absence — the whole point of not dropping it
     * silently.
     *
     * @param string $contenttype A MIME value.
     * @param string $title The file name or document title.
     * @param sink|null $sink The destination to ask; the active one when null.
     * @return string The translated reason.
     */
    public static function skip_reason(string $contenttype, string $title, ?sink $sink = null): string {
        $type = self::normalise($contenttype);
        if (!isset(self::FORMATS[$type])) {
            $info = (object) ['title' => $title, 'type' => $type];
            return get_string('skipreason_unknowntype', 'local_elediaai_sources', $info);
        }

        // Only this branch needs to name the destination, so only this branch
        // resolves one: an extractor asking about a video should not construct
        // a sink to be told it is a video.
        $sink = $sink ?? sink_manager::active();
        $info = (object) ['title' => $title, 'type' => $type, 'sink' => $sink::name()];
        return get_string('skipreason_notaccepted', 'local_elediaai_sources', $info);
    }

    /**
     * MIME types carrying one status, in matrix order.
     *
     * @param string $status One of the STATUS_* constants.
     * @return string[] MIME types.
     */
    private static function by_status(string $status): array {
        $types = [];
        foreach (self::FORMATS as $mimetype => $format) {
            if ($format['status'] === $status) {
                $types[] = $mimetype;
            }
        }
        return $types;
    }
}

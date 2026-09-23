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

/**
 * Unit tests for the document format support matrix.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_sources\format_matrix
 */
final class format_matrix_test extends \advanced_testcase {
    /**
     * A destination announcing exactly what a test tells it to.
     *
     * @param string[] $types The MIME types it claims to parse.
     * @return sink The stub destination.
     */
    private function sink_announcing(array $types): sink {
        return new class ($types) implements sink {
            /**
             * Constructor.
             *
             * @param string[] $types The announced MIME types.
             */
            public function __construct(
                /** @var string[] The announced MIME types. */
                private array $types
            ) {
            }

            #[\Override]
            public static function id(): string {
                return 'stub';
            }

            #[\Override]
            public static function name(): string {
                return 'Stub';
            }

            #[\Override]
            public function is_configured(): bool {
                return true;
            }

            #[\Override]
            public function healthcheck(): array {
                return ['success' => true, 'http_code' => 200, 'response' => '', 'error' => ''];
            }

            #[\Override]
            public function embedding_model(): ?string {
                return null;
            }

            #[\Override]
            public function supported_content_types(): array {
                return $this->types;
            }

            /**
             * Never called in these tests.
             *
             * @param array $payload The document payload.
             * @return array Successful result row.
             */
            #[\Override]
            public function upsert(array $payload): array {
                return ['success' => true, 'http_code' => 200, 'response' => '', 'error' => ''];
            }

            /**
             * Never called in these tests.
             *
             * @param string $sourceid The source id.
             * @param string $scope Deletion scope.
             * @return array Successful result row.
             */
            #[\Override]
            public function delete(string $sourceid, string $scope = 'exact'): array {
                return ['success' => true, 'http_code' => 200, 'response' => '', 'error' => ''];
            }
        };
    }

    /**
     * Parameters and casing are noise, not a different type — the pipeline
     * applies the same rule on its side (AI-48).
     */
    public function test_normalise_strips_parameters_and_case(): void {
        $this->assertSame('text/plain', format_matrix::normalise('text/plain; charset=utf-8'));
        $this->assertSame('text/html', format_matrix::normalise('  TEXT/HTML  '));
        $this->assertSame('application/pdf', format_matrix::normalise('Application/PDF;version=1.7'));
    }

    /**
     * Moodle has no file type for Markdown, so a .md file arrives as
     * document/unknown. Only for such a generic answer is the name consulted.
     */
    public function test_resolve_uses_the_filename_only_for_generic_types(): void {
        $this->assertSame('text/markdown', format_matrix::resolve('document/unknown', 'readme.md'));
        $this->assertSame('text/markdown', format_matrix::resolve('application/octet-stream', 'Notes.MARKDOWN'));

        // A type Moodle *did* recognise is never overridden by the extension.
        $this->assertSame('image/png', format_matrix::resolve('image/png', 'trick.pdf'));

        // An unknown type with an extension nobody declared stays unknown.
        $this->assertSame('document/unknown', format_matrix::resolve('document/unknown', 'archive.7z'));
    }

    /**
     * Binary content must never be given a heading or truncated. An unknown
     * type counts as binary: bytes nobody described are not text.
     */
    public function test_binary_formats_are_recognised(): void {
        $this->assertTrue(format_matrix::is_binary('application/pdf'));
        $this->assertTrue(format_matrix::is_binary(format_matrix::DOCX));
        $this->assertTrue(format_matrix::is_binary(format_matrix::PPTX));
        $this->assertTrue(format_matrix::is_binary('application/x-unheard-of'));

        $this->assertFalse(format_matrix::is_binary('text/plain'));
        $this->assertFalse(format_matrix::is_binary('text/html'));
        $this->assertFalse(format_matrix::is_binary('text/markdown'));
    }

    /**
     * The core three are offered whatever the destination says — they are the
     * contractual minimum every destination has to parse.
     */
    public function test_core_types_are_always_offered(): void {
        $offered = format_matrix::offered($this->sink_announcing([]));

        $this->assertSame(['text/plain', 'text/html', 'application/pdf'], $offered);
    }

    /**
     * A negotiated format becomes exportable the moment the destination
     * announces it — that is what makes "DOCX once AI-48 ships" a
     * configuration change rather than a Moodle release.
     */
    public function test_announced_formats_become_offered(): void {
        $sink = $this->sink_announcing(array_merge(
            format_matrix::core_types(),
            [format_matrix::DOCX, 'text/markdown']
        ));

        $offered = format_matrix::offered($sink);

        $this->assertContains(format_matrix::DOCX, $offered);
        $this->assertContains('text/markdown', $offered);
        $this->assertNotContains(format_matrix::PPTX, $offered, 'Unannounced formats stay closed.');
        $this->assertTrue(format_matrix::is_offered('APPLICATION/VND.OPENXMLFORMATS-OFFICEDOCUMENT.'
            . 'WORDPROCESSINGML.DOCUMENT; x=1', $sink));
    }

    /**
     * Every document format the matrix knows is negotiable — including the
     * ones whose quality and safety are still open questions (spreadsheets,
     * legacy and OpenDocument containers). Nothing is parsed on this side, so
     * those questions belong to the parser, and the destination answers them
     * by announcing the type or not. A second gate here would only be a second
     * place to forget.
     */
    public function test_every_known_format_opens_when_announced(): void {
        $undecided = [
            format_matrix::XLSX,
            'text/csv',
            'application/msword',
            'application/vnd.oasis.opendocument.text',
            'application/vnd.oasis.opendocument.spreadsheet',
            'application/vnd.oasis.opendocument.presentation',
        ];

        $closed = format_matrix::offered($this->sink_announcing([]));
        $open = format_matrix::offered($this->sink_announcing($undecided));

        foreach ($undecided as $type) {
            $this->assertNotContains($type, $closed, "{$type} must stay shut while nobody announces it");
            $this->assertContains($type, $open, "{$type} must open once the destination announces it");
        }
    }

    /**
     * A type the matrix does not know is not exported however loudly the
     * destination offers to parse it.
     */
    public function test_unknown_announced_types_are_ignored(): void {
        $offered = format_matrix::offered($this->sink_announcing(['application/x-shockwave-flash']));

        $this->assertNotContains('application/x-shockwave-flash', $offered);
        $this->assertSame(format_matrix::core_types(), $offered);
    }

    /**
     * Extractors work from the stable list: everything that is a document
     * format at all, whether or not today's destination can read it.
     */
    public function test_extractable_types_cover_core_and_negotiated(): void {
        $extractable = format_matrix::extractable_types();

        foreach (format_matrix::core_types() as $type) {
            $this->assertContains($type, $extractable);
        }
        foreach (format_matrix::negotiable_types() as $type) {
            $this->assertContains($type, $extractable);
        }
        $this->assertSame(array_keys(format_matrix::entries()), $extractable);
    }

    /**
     * Every skip has a reason naming the file, and the three reasons are
     * genuinely different answers to "why is my file not in the index?".
     */
    public function test_skip_reasons_name_the_file_and_the_cause(): void {
        $this->resetAfterTest();

        $sink = $this->sink_announcing(format_matrix::core_types());

        $unknown = format_matrix::skip_reason('video/mp4', 'lecture.mp4', $sink);
        $this->assertStringContainsString('lecture.mp4', $unknown);
        $this->assertStringContainsString('video/mp4', $unknown);

        $notaccepted = format_matrix::skip_reason(format_matrix::DOCX, 'script.docx', $sink);
        $this->assertStringContainsString('script.docx', $notaccepted);
        $this->assertStringContainsString(format_matrix::DOCX, $notaccepted);
        $this->assertStringContainsString('Stub', $notaccepted, 'The destination is named.');

        // Not a document format at all, and this destination cannot read it
        // yet, are different answers and must not collapse into one sentence.
        $this->assertNotSame($unknown, $notaccepted);
    }

    /**
     * Every entry is complete and every declared status is one the code knows
     * — the matrix is documentation as much as it is code, and a typo in a
     * status would silently turn a core format into a negotiated one.
     */
    public function test_matrix_entries_are_wellformed(): void {
        $known = [format_matrix::STATUS_CORE, format_matrix::STATUS_NEGOTIATED];

        foreach (format_matrix::entries() as $mimetype => $entry) {
            $this->assertSame(format_matrix::normalise($mimetype), $mimetype, "{$mimetype} is not normalised");
            $this->assertContains($entry['status'], $known, "{$mimetype} carries an unknown status");
            $this->assertIsBool($entry['binary']);
            $this->assertNotEmpty($entry['extensions'], "{$mimetype} declares no extension");
        }
    }
}

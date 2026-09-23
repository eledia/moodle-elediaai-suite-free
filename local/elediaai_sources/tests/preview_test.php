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
 * Unit tests for the per-activity dry run.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_sources\ingestion_manager
 */
final class preview_test extends \advanced_testcase {
    /** @var \stdClass The course under test. */
    private \stdClass $course;

    /**
     * Released course, one page, recording destination.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course();
        set_config('enabledcategories', (string) $this->course->category, 'local_elediaai_sources');
        set_config('max_document_size_mb', '20', 'local_elediaai_sources');
    }

    /**
     * A destination that records everything and would fail the test if used.
     *
     * @return \local_elediaai_sources\sink\sink
     */
    private function recording_sink(): sink\sink {
        return new class implements sink\sink {
            /** @var string[] Formats this destination announces beyond the core three. */
            public array $extratypes = [];

            /** @var array<int, array> Every payload passed to upsert(). */
            public array $payloads = [];

            /** @var array<int, array> Every delete call. */
            public array $deletes = [];

            #[\Override]
            public static function id(): string {
                return 'recording';
            }

            #[\Override]
            public static function name(): string {
                return 'Recording';
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

            /**
             * The core formats, plus whatever a test wants this destination to
             * announce — the negotiated half of the support matrix is exactly
             * what the manager has to react to.
             *
             * @return string[] MIME types.
             */
            #[\Override]
            public function supported_content_types(): array {
                return array_merge(\local_elediaai_sources\format_matrix::core_types(), $this->extratypes);
            }

            /**
             * Record the payload.
             *
             * @param array $payload The document payload.
             * @return array Successful result row.
             */
            #[\Override]
            public function upsert(array $payload): array {
                $this->payloads[] = $payload;
                return ['success' => true, 'http_code' => 200, 'response' => '', 'error' => ''];
            }

            /**
             * Record the delete.
             *
             * @param string $sourceid The source_id to delete.
             * @param string $scope Deletion scope.
             * @return array Successful result row.
             */
            #[\Override]
            public function delete(string $sourceid, string $scope = 'exact'): array {
                $this->deletes[] = ['sourceid' => $sourceid, 'scope' => $scope];
                return ['success' => true, 'http_code' => 200, 'response' => '', 'error' => ''];
            }
        };
    }

    /**
     * Create a page in the course under test.
     *
     * @param string $content The page content.
     * @param array $extra Extra generator options.
     * @return \cm_info
     */
    private function page(string $content = '<p>Hello</p>', array $extra = []): \cm_info {
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $this->course->id,
            'content' => $content,
        ] + $extra);
        return get_fast_modinfo($this->course->id)->get_cm((int) $page->cmid);
    }

    /**
     * The headline property: a dry run changes nothing.
     *
     * Neither the destination nor the state table may be touched — a preview
     * that recorded state would corrupt the very truth it reports.
     */
    public function test_preview_writes_nothing(): void {
        global $DB;

        $cm = $this->page();
        $sink = $this->recording_sink();
        $manager = new ingestion_manager($sink);

        $before = $DB->count_records('local_elediaai_sources_cmstate');
        $report = $manager->preview_module($cm);

        $this->assertSame(ingestion_manager::VERDICT_INGEST, $report['verdict']);
        $this->assertCount(0, $sink->payloads, 'A dry run must not send.');
        $this->assertCount(0, $sink->deletes, 'A dry run must not delete.');
        $this->assertSame($before, $DB->count_records('local_elediaai_sources_cmstate'));
        $this->assertNull(cm_state::get((int) $cm->id));
    }

    /**
     * An existing state row survives a dry run field for field.
     */
    public function test_preview_leaves_existing_state_untouched(): void {
        $cm = $this->page();
        cm_state::record_success((int) $this->course->id, (int) $cm->id, 'sid', 'hash-a', 'recording');
        $before = cm_state::get((int) $cm->id);

        $sink = $this->recording_sink();
        (new ingestion_manager($sink))->preview_module($cm);

        $this->assertEquals($before, cm_state::get((int) $cm->id), 'The state row must be identical afterwards.');
    }

    /**
     * The preview and a real ingest agree on the content hash — proof that
     * the dry run reports what would actually be sent.
     */
    public function test_preview_hash_matches_a_real_ingest(): void {
        $cm = $this->page('<p>Exactly this</p>');
        $sink = $this->recording_sink();
        $manager = new ingestion_manager($sink);

        $report = $manager->preview_module($cm);
        $this->assertNotNull($report['aggregatehash']);

        ob_start();
        $manager->ingest_module((int) $this->course->id, (int) $cm->id);
        ob_end_clean();

        $this->assertSame(
            $report['aggregatehash'],
            cm_state::get((int) $cm->id)->contenthash,
            'Preview and ingest must not drift apart.'
        );
    }

    /**
     * Every gate branch produces its own verdict and reason.
     */
    public function test_gate_branches(): void {
        $sink = $this->recording_sink();
        $manager = new ingestion_manager($sink);

        // Course not released: needs its own category, since the one under
        // test is on the allow-list.
        $othercategory = $this->getDataGenerator()->create_category();
        $other = $this->getDataGenerator()->create_course(['category' => $othercategory->id]);
        // Create first, read modinfo after: the reverse builds the cache
        // before the module exists.
        $otherpage = $this->getDataGenerator()->create_module('page', [
            'course' => $other->id,
            'content' => '<p>x</p>',
        ]);
        $othercm = get_fast_modinfo($other->id)->get_cm((int) $otherpage->cmid);
        $report = $manager->preview_module($othercm);
        $this->assertSame(ingestion_manager::VERDICT_SKIP, $report['verdict']);
        $this->assertSame('coursenotmarked', $report['reason']);

        // Undecided in opt-in mode.
        set_config('activitydefault', activity_gate::MODE_OPTIN, 'local_elediaai_sources');
        $cm = $this->page();
        $report = $manager->preview_module($cm);
        $this->assertSame(ingestion_manager::VERDICT_SKIP, $report['verdict']);
        $this->assertSame('activitynotselected', $report['reason']);
        set_config('activitydefault', activity_gate::MODE_OPTOUT, 'local_elediaai_sources');

        // Explicitly excluded: the verdict is delete, but nothing is deleted.
        activity_gate::set_included((int) $this->course->id, (int) $cm->id, false);
        $report = $manager->preview_module($cm);
        $this->assertSame(ingestion_manager::VERDICT_DELETE, $report['verdict']);
        $this->assertSame('activityexcluded', $report['reason']);
        $this->assertCount(0, $sink->deletes, 'A verdict is not an action.');
        activity_gate::clear((int) $cm->id);

        // No extractor for this module type.
        $forummod = $this->getDataGenerator()->create_module('forum', ['course' => $this->course->id]);
        $forum = get_fast_modinfo($this->course->id)->get_cm((int) $forummod->cmid);
        $report = $manager->preview_module($forum);
        $this->assertSame(ingestion_manager::VERDICT_SKIP, $report['verdict']);
        $this->assertSame('noextractor', $report['reason']);
    }

    /**
     * A hidden activity yields delete when something is indexed, skip when not.
     */
    public function test_hidden_activity_verdict_depends_on_state(): void {
        $cm = $this->page('<p>Hidden</p>', ['visible' => 0]);
        $manager = new ingestion_manager($this->recording_sink());

        $report = $manager->preview_module($cm);
        $this->assertSame(ingestion_manager::VERDICT_SKIP, $report['verdict']);
        $this->assertSame('activityhidden', $report['reason']);

        cm_state::record_success((int) $this->course->id, (int) $cm->id, 'sid', 'h', 'recording');
        $report = $manager->preview_module($cm);
        $this->assertSame(ingestion_manager::VERDICT_DELETE, $report['verdict']);
        $this->assertSame('activityhidden', $report['reason']);
    }

    /**
     * The payload metadata shown is the contract's metadata.
     */
    public function test_payload_metadata_is_reported(): void {
        $cm = $this->page();
        $report = (new ingestion_manager($this->recording_sink()))->preview_module($cm);

        $this->assertEqualsCanonicalizing(
            ['title', 'tenant_id', 'site_url', 'course_id', 'cmid', 'module_url'],
            array_keys($report['payloadmeta'])
        );
        $this->assertSame((string) $cm->id, $report['payloadmeta']['cmid']);
    }

    /**
     * Oversized text is capped for display while the byte count stays honest,
     * and the truncation notice never leaks into the page.
     */
    public function test_long_content_is_capped_without_stray_output(): void {
        $long = '<p>' . str_repeat('Lorem ipsum dolor sit amet. ', 3000) . '</p>';
        $cm = $this->page($long);

        ob_start();
        $report = (new ingestion_manager($this->recording_sink()))->preview_module($cm);
        $stray = ob_get_clean();

        $this->assertSame('', $stray, 'Truncation logging must not print into the page.');

        $document = $report['documents'][0];
        $this->assertTrue($document['displaytruncated']);
        $this->assertSame(20000, \core_text::strlen($document['displaycontent']));
        $this->assertGreaterThan(20000, $document['bytes'], 'The byte count reports the real size.');
    }

    /**
     * Binary content is described, never carried.
     */
    public function test_binary_content_is_not_carried(): void {
        $resource = $this->getDataGenerator()->create_module('resource', ['course' => $this->course->id]);
        $context = \core\context\module::instance((int) $resource->cmid);
        get_file_storage()->delete_area_files($context->id, 'mod_resource', 'content');
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id, 'component' => 'mod_resource', 'filearea' => 'content',
            'itemid' => 0, 'filepath' => '/', 'filename' => 'handbook.pdf', 'mimetype' => 'application/pdf',
            'sortorder' => 1,
        ], '%PDF-1.4 binary payload');

        $cm = get_fast_modinfo($this->course->id)->get_cm((int) $resource->cmid);
        $report = (new ingestion_manager($this->recording_sink()))->preview_module($cm);

        $pdf = null;
        foreach ($report['documents'] as $document) {
            if ($document['content_type'] === 'application/pdf') {
                $pdf = $document;
            }
        }

        $this->assertNotNull($pdf);
        $this->assertTrue($pdf['isbinary']);
        $this->assertNull($pdf['displaycontent'], 'Binary bytes help nobody on screen.');
        $this->assertGreaterThan(0, $pdf['bytes']);
    }

    /**
     * A multi-document module lists one row per sub-document.
     */
    public function test_multidocument_lists_every_subdocument(): void {
        global $DB;

        $folder = $this->getDataGenerator()->create_module('folder', [
            'course' => $this->course->id,
            'name' => 'Readings',
        ]);
        $DB->set_field('folder', 'intro', '', ['id' => $folder->id]);
        $context = \core\context\module::instance((int) $folder->cmid);
        foreach (['a.txt' => 'First.', 'b.txt' => 'Second.'] as $filename => $content) {
            get_file_storage()->create_file_from_string([
                'contextid' => $context->id, 'component' => 'mod_folder', 'filearea' => 'content',
                'itemid' => 0, 'filepath' => '/', 'filename' => $filename, 'mimetype' => 'text/plain',
            ], $content);
        }

        $cm = get_fast_modinfo($this->course->id)->get_cm((int) $folder->cmid);
        $report = (new ingestion_manager($this->recording_sink()))->preview_module($cm);

        $this->assertGreaterThanOrEqual(2, count($report['documents']));
        $sourceids = array_column($report['documents'], 'sourceid');
        $this->assertSame(count($sourceids), count(array_unique($sourceids)), 'Every sub-document has its own id.');
    }

    /**
     * The comparison against the index distinguishes its honest states.
     */
    public function test_comparison_states(): void {
        $cm = $this->page();
        $manager = new ingestion_manager($this->recording_sink());

        $this->assertSame('absent', $manager->preview_module($cm)['comparison']);

        cm_state::record_success((int) $this->course->id, (int) $cm->id, 'sid', 'h', 'recording');
        $this->assertSame('indexed', $manager->preview_module($cm)['comparison']);

        cm_state::record_error((int) $this->course->id, (int) $cm->id, 'sid', 'boom', 'recording');
        $this->assertSame('lastattemptfailed', $manager->preview_module($cm)['comparison']);

        cm_state::forget((int) $cm->id);
        cm_state::record_success((int) $this->course->id, (int) $cm->id, 'sid', 'h', 'literag');
        $this->assertSame(
            'otherdestination',
            $manager->preview_module($cm)['comparison'],
            'Hashes are not comparable across destinations.'
        );
    }

    /**
     * After a real ingest the preview reports the content as unchanged — the
     * same judgement that makes a reindex skip it.
     */
    public function test_unchanged_flag_matches_the_skip_decision(): void {
        $cm = $this->page();
        $manager = new ingestion_manager($this->recording_sink());

        $this->assertFalse($manager->preview_module($cm)['unchanged']);

        ob_start();
        $manager->ingest_module((int) $this->course->id, (int) $cm->id);
        ob_end_clean();
        course_state::set_ingested((int) $this->course->id, true);

        $this->assertTrue($manager->preview_module($cm)['unchanged']);
    }
}

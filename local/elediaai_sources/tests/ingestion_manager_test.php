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
 * Unit tests for the ingestion_manager class.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_sources\ingestion_manager
 */
final class ingestion_manager_test extends \advanced_testcase {
    /**
     * Set up test configuration.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        // Configure the destination so the sink reports configured.
        set_config('sink', 'ingestionapi', 'local_elediaai_sources');
        set_config('sink_ingestionapi_baseurl', 'http://localhost:8001', 'local_elediaai_sources');
        set_config('sink_ingestionapi_apikey', 'test-key', 'local_elediaai_sources');
        set_config('max_document_size_mb', '20', 'local_elediaai_sources');

        // Mark the default course category for ingestion so generator courses
        // (created there) pass the opt-in gate.
        global $DB;
        $defaultcat = (int) $DB->get_field_select('course_categories', 'MIN(id)', 'parent = 0');
        set_config('enabledcategories', (string) $defaultcat, 'local_elediaai_sources');
    }

    /**
     * Test reindex_course returns error when the destination is not configured.
     */
    public function test_reindex_course_not_configured(): void {
        set_config('sink_ingestionapi_baseurl', '', 'local_elediaai_sources');
        set_config('sink_ingestionapi_apikey', '', 'local_elediaai_sources');

        $course = $this->getDataGenerator()->create_course();

        $manager = new ingestion_manager();
        $results = $manager->reindex_course($course->id);

        $this->assertCount(1, $results);
        $this->assertFalse($results[0]['success']);
        $this->assertEquals('error', $results[0]['status']);
        $this->assertStringContainsString('not configured', $results[0]['message']);
    }

    /**
     * Test reindex_course with a page activity and mocked API response.
     */
    public function test_reindex_course_with_page(): void {
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Test Page',
            'content' => '<p>Hello World</p>',
        ]);
        set_config('enabledcategories', (string) $course->category, 'local_elediaai_sources');

        // Two responses: the sink asks /health which formats the service can
        // parse before the first document goes out, then the upsert itself.
        \curl::mock_response('{"status": "ok"}');
        \curl::mock_response('{"status": "ok"}');

        $manager = new ingestion_manager();
        ob_start();
        $results = $manager->reindex_course($course->id);
        ob_end_clean();

        // Should have at least one result for the page.
        $pageresult = null;
        foreach ($results as $result) {
            if ($result['cmid'] == $page->cmid) {
                $pageresult = $result;
                break;
            }
        }

        $this->assertNotNull($pageresult, 'Page module result should be present.');
        $this->assertTrue($pageresult['success']);
        $this->assertEquals('success', $pageresult['status']);
    }

    /**
     * Test reindex_course skips modules without extractors.
     */
    public function test_reindex_course_skips_unsupported_module(): void {
        $course = $this->getDataGenerator()->create_course();
        // Create a forum — no extractor exists for it.
        $this->getDataGenerator()->create_module('forum', [
            'course' => $course->id,
            'name' => 'Test Forum',
        ]);

        $manager = new ingestion_manager();
        $results = $manager->reindex_course($course->id);

        // Forum should be skipped.
        $forumresult = null;
        foreach ($results as $result) {
            if ($result['status'] === 'skipped') {
                $forumresult = $result;
                break;
            }
        }

        $this->assertNotNull($forumresult, 'Forum module should be skipped.');
        $this->assertFalse($forumresult['success']);
        $this->assertEquals('skipped', $forumresult['status']);
        // Tried, nothing to send -- written down, so the reconcile does not
        // mistake it for a module that never got an attempt (#32).
        $state = cm_state::get((int) $forumresult['cmid']);
        $this->assertNotNull($state);
        $this->assertSame(cm_state::STATUS_EMPTY, $state->laststatus);
        $this->assertFalse(cm_state::may_hold_documents((int) $forumresult['cmid']));
    }

    /**
     * Test ingest_module returns error when API is not configured.
     */
    public function test_ingest_module_not_configured(): void {
        set_config('sink_ingestionapi_baseurl', '', 'local_elediaai_sources');
        set_config('sink_ingestionapi_apikey', '', 'local_elediaai_sources');

        $manager = new ingestion_manager();
        $result = $manager->ingest_module(1, 1);

        $this->assertFalse($result['success']);
        $this->assertEquals('error', $result['status']);
    }

    /**
     * Test delete_module returns error when API is not configured.
     */
    public function test_delete_module_not_configured(): void {
        set_config('sink_ingestionapi_baseurl', '', 'local_elediaai_sources');
        set_config('sink_ingestionapi_apikey', '', 'local_elediaai_sources');

        $manager = new ingestion_manager();
        $result = $manager->delete_module(1, 1);

        $this->assertFalse($result['success']);
        $this->assertEquals('error', $result['status']);
    }

    /**
     * Test delete_module with a mocked successful API response.
     */
    public function test_delete_module_success(): void {
        \curl::mock_response('{"status": "ok"}');

        $manager = new ingestion_manager();
        ob_start();
        $result = $manager->delete_module(42, 99);
        ob_end_clean();

        $this->assertTrue($result['success']);
        $this->assertEquals('success', $result['status']);
    }

    /**
     * Test that a multi-document module (a folder with several files) sends one
     * upsert per file after a prefix-scoped clear of the previous set.
     */
    public function test_ingest_multidocument_folder(): void {
        global $DB;
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $folder = $this->getDataGenerator()->create_module('folder', [
            'course' => $course->id,
            'name' => 'Readings',
        ]);
        // Force-clear the generator's default intro so the document count is
        // exactly the two files below.
        $DB->set_field('folder', 'intro', '', ['id' => $folder->id]);

        $context = \core\context\module::instance($folder->cmid);
        $fs = get_file_storage();
        foreach (['a.txt' => 'First.', 'b.txt' => 'Second.'] as $name => $body) {
            $fs->create_file_from_string([
                'contextid' => $context->id, 'component' => 'mod_folder', 'filearea' => 'content',
                'itemid' => 0, 'filepath' => '/', 'filename' => $name, 'mimetype' => 'text/plain',
            ], $body);
        }

        // One probe upsert + one prefix-delete + two upserts = four HTTP calls.
        // One for the /health format question, then probe, delete and the
        // documents themselves.
        \curl::mock_response('{"status": "ok"}');
        \curl::mock_response('{"status": "ok"}');
        \curl::mock_response('{"status": "ok"}');
        \curl::mock_response('{"status": "ok"}');
        \curl::mock_response('{"status": "ok"}');

        $manager = new ingestion_manager();
        ob_start();
        $result = $manager->ingest_module($course->id, $folder->cmid);
        ob_end_clean();

        $this->assertTrue($result['success']);
        $this->assertSame('success', $result['status']);
        $this->assertStringContainsString('2 document', $result['message']);
    }

    /**
     * Test that a failing upsert keeps the previous multi-document set: the
     * prefix-scoped delete must not run when the probe upsert is rejected,
     * otherwise the module disappears from the index (visibility hole).
     */
    public function test_ingest_multidocument_keeps_old_set_on_upsert_failure(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $folder = $this->getDataGenerator()->create_module('folder', [
            'course' => $course->id,
            'name' => 'Readings',
        ]);
        $DB->set_field('folder', 'intro', '', ['id' => $folder->id]);

        $context = \core\context\module::instance($folder->cmid);
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id, 'component' => 'mod_folder', 'filearea' => 'content',
            'itemid' => 0, 'filepath' => '/', 'filename' => 'a.txt', 'mimetype' => 'text/plain',
        ], 'First.');

        // Inject a destination whose upserts always fail and which records deletes.
        $sink = new class implements \local_elediaai_sources\sink\sink {
            /** @var string[] Formats this destination announces beyond the core three. */
            public array $extratypes = [];

            /** @var int Number of delete() calls received. */
            public int $deletecalls = 0;

            #[\Override]
            public static function id(): string {
                return 'test';
            }

            #[\Override]
            public static function name(): string {
                return 'Test';
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
             * Simulate a failing upsert.
             *
             * @param array $payload The document payload.
             * @return array Failed result row.
             */
            #[\Override]
            public function upsert(array $payload): array {
                return ['success' => false, 'http_code' => 500, 'response' => '', 'error' => 'boom'];
            }

            /**
             * Record delete calls instead of sending them.
             *
             * @param string $sourceid The source_id to delete.
             * @param string $scope Deletion scope.
             * @return array Successful result row.
             */
            #[\Override]
            public function delete(string $sourceid, string $scope = 'exact'): array {
                $this->deletecalls++;
                return ['success' => true, 'http_code' => 200, 'response' => '', 'error' => ''];
            }
        };

        $manager = new ingestion_manager($sink);

        ob_start();
        $result = $manager->ingest_module($course->id, $folder->cmid);
        ob_end_clean();

        $this->assertFalse($result['success']);
        $this->assertSame('error', $result['status']);
        $this->assertSame(0, $sink->deletecalls, 'Old document set must survive a failed upsert.');
    }

    /**
     * Test that the ingestion manager enforces file size limits.
     */
    public function test_reindex_course_enforces_size_limit(): void {
        // Set a tiny size limit of 0.001 MB (1 KB) for testing.
        set_config('max_document_size_mb', '0.001', 'local_elediaai_sources');

        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Big Page',
            'content' => '<p>This content exceeds 1 KB limit</p>' . str_repeat('x', 2000),
        ]);
        set_config('enabledcategories', (string) $course->category, 'local_elediaai_sources');

        $manager = new ingestion_manager();
        $results = $manager->reindex_course($course->id);

        // Find the page result — it should be skipped due to size.
        $pagefound = false;
        foreach ($results as $result) {
            if (str_contains($result['message'] ?? '', 'exceeds')) {
                $pagefound = true;
                $this->assertFalse($result['success']);
                $this->assertEquals('skipped', $result['status']);
            }
        }

        $this->assertTrue($pagefound, 'Page should have been skipped due to size limit.');
    }

    /**
     * Test that the upsert payload carries exactly the contract's metadata.
     *
     * AC-4 asks for tenant, source ids and embedding model. The first two are
     * checked field by field; the model is checked as *absent*, because neither
     * sink exposes one and v1.2 defines no field for it. Pinning the metadata
     * key set is the point of the test: an invented sixth field fails here
     * rather than at a service that would reject the request.
     */
    public function test_upsert_payload_carries_the_contract_metadata(): void {
        global $CFG;

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Contract Page',
            'content' => '<p>Hello World</p>',
        ]);
        set_config('enabledcategories', (string) $course->category, 'local_elediaai_sources');

        $sink = $this->recording_sink();
        $manager = new ingestion_manager($sink);

        ob_start();
        $result = $manager->ingest_module($course->id, $page->cmid);
        ob_end_clean();

        $this->assertTrue($result['success']);
        $this->assertCount(1, $sink->payloads);
        $payload = $sink->payloads[0];

        $expectedid = tenant::id() . ':course' . $course->id . ':cmid' . $page->cmid;
        $this->assertSame($expectedid, $payload['source_id']);
        $this->assertSame('text/html', $payload['content_type']);
        $this->assertNull($payload['parser_options']);
        $this->assertStringContainsString(
            'Hello World',
            (string) base64_decode($payload['content'], true),
            'Content must reach the destination base64-encoded.'
        );

        $meta = $payload['qdrant_metadata'];
        $this->assertEqualsCanonicalizing(
            ['title', 'tenant_id', 'site_url', 'course_id', 'cmid', 'module_url'],
            array_keys($meta),
            'The metadata key set is fixed by docs/api-specification.md v1.3.'
        );
        $this->assertSame('Contract Page', $meta['title'], 'The document title travels with the payload.');
        $this->assertSame(tenant::id(), $meta['tenant_id']);
        $this->assertSame((string) $CFG->wwwroot, $meta['site_url']);
        $this->assertSame((string) $course->id, $meta['course_id']);
        $this->assertSame((string) $page->cmid, $meta['cmid']);
        $this->assertStringContainsString('/mod/page/view.php', $meta['module_url']);
        $this->assertStringContainsString('id=' . $page->cmid, $meta['module_url']);
    }

    /**
     * Test that a sub-document keeps the module id as its prefix.
     *
     * The prefix-scoped delete depends on it: the module-level id must be a
     * ':'-separated prefix of every sub-document id, otherwise removing a
     * multi-file module leaves orphaned vectors behind. The metadata still
     * names the module, because a sub-document belongs to the same activity.
     */
    public function test_subdocument_payload_keeps_the_module_prefix(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $folder = $this->getDataGenerator()->create_module('folder', [
            'course' => $course->id,
            'name' => 'Readings',
        ]);
        $DB->set_field('folder', 'intro', '', ['id' => $folder->id]);
        set_config('enabledcategories', (string) $course->category, 'local_elediaai_sources');

        $context = \core\context\module::instance($folder->cmid);
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id, 'component' => 'mod_folder', 'filearea' => 'content',
            'itemid' => 0, 'filepath' => '/', 'filename' => 'a.txt', 'mimetype' => 'text/plain',
        ], 'First.');

        $sink = $this->recording_sink();
        $manager = new ingestion_manager($sink);

        ob_start();
        $manager->ingest_module($course->id, $folder->cmid);
        ob_end_clean();

        $this->assertNotEmpty($sink->payloads);
        $moduleid = tenant::id() . ':course' . $course->id . ':cmid' . $folder->cmid;

        foreach ($sink->payloads as $payload) {
            $this->assertStringStartsWith(
                $moduleid . ':',
                $payload['source_id'],
                'A sub-document id must extend the module id across a ":" boundary.'
            );
            $this->assertSame((string) $folder->cmid, $payload['qdrant_metadata']['cmid']);
            $this->assertSame(tenant::id(), $payload['qdrant_metadata']['tenant_id']);
        }
    }

    /**
     * Test that an explicitly excluded activity is removed from the index.
     *
     * The exclusion must converge the index — a prefix-scoped delete of the
     * module's documents — rather than merely skipping, otherwise unticking
     * an activity would leave its content retrievable forever.
     */
    public function test_excluded_activity_is_removed_from_the_index(): void {
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Secret</p>',
        ]);
        set_config('enabledcategories', (string) $course->category, 'local_elediaai_sources');
        activity_gate::set_included((int) $course->id, (int) $page->cmid, false);

        $sink = $this->recording_sink();
        $manager = new ingestion_manager($sink);

        ob_start();
        $result = $manager->ingest_module($course->id, $page->cmid);
        ob_end_clean();

        $this->assertSame('success', $result['status']);
        $this->assertNotEmpty($result['module_name']);
        $this->assertCount(0, $sink->payloads, 'An excluded activity must not be upserted.');
        $this->assertCount(1, $sink->deletes);
        $this->assertSame(
            source_id_helper::build_from_ids((int) $course->id, (int) $page->cmid),
            $sink->deletes[0]['sourceid']
        );
        $this->assertSame('prefix', $sink->deletes[0]['scope'], 'Sub-documents must be cleared too.');
    }

    /**
     * Test that an untouched activity in opt-in mode is skipped, not deleted.
     *
     * This is what makes flipping the site mode safe: existing content is
     * only ever removed on an explicit exclusion.
     */
    public function test_untouched_activity_in_optin_mode_is_skipped_not_deleted(): void {
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Existing</p>',
        ]);
        set_config('enabledcategories', (string) $course->category, 'local_elediaai_sources');
        set_config('activitydefault', activity_gate::MODE_OPTIN, 'local_elediaai_sources');

        $sink = $this->recording_sink();
        $manager = new ingestion_manager($sink);

        ob_start();
        $result = $manager->ingest_module($course->id, $page->cmid);
        ob_end_clean();

        $this->assertSame('skipped', $result['status']);
        $this->assertCount(0, $sink->payloads);
        $this->assertCount(0, $sink->deletes, 'Untouched in opt-in mode must never delete.');
    }

    /**
     * Test that an explicit inclusion beats the opt-in mode.
     */
    public function test_explicitly_included_activity_in_optin_mode_is_ingested(): void {
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Chosen</p>',
        ]);
        set_config('enabledcategories', (string) $course->category, 'local_elediaai_sources');
        set_config('activitydefault', activity_gate::MODE_OPTIN, 'local_elediaai_sources');
        activity_gate::set_included((int) $course->id, (int) $page->cmid, true);

        $sink = $this->recording_sink();
        $manager = new ingestion_manager($sink);

        ob_start();
        $result = $manager->ingest_module($course->id, $page->cmid);
        ob_end_clean();

        $this->assertTrue($result['success']);
        $this->assertCount(1, $sink->payloads);
        $this->assertCount(0, $sink->deletes);
    }

    /**
     * Test that a reindex deletes excluded modules but does not delete
     * modules that merely lack an extractor.
     */
    public function test_reindex_deletes_excluded_but_skips_unsupported(): void {
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Out</p>',
        ]);
        $forum = $this->getDataGenerator()->create_module('forum', [
            'course' => $course->id,
        ]);
        set_config('enabledcategories', (string) $course->category, 'local_elediaai_sources');
        activity_gate::set_included((int) $course->id, (int) $page->cmid, false);

        $sink = $this->recording_sink();
        $manager = new ingestion_manager($sink);

        ob_start();
        $results = $manager->reindex_course($course->id);
        ob_end_clean();

        $byid = [];
        foreach ($results as $result) {
            $byid[(int) $result['cmid']] = $result;
        }

        $this->assertSame('success', $byid[(int) $page->cmid]['status']);
        $this->assertSame('skipped', $byid[(int) $forum->cmid]['status']);
        $this->assertCount(1, $sink->deletes, 'Only the excluded module may be deleted.');
        $this->assertStringContainsString('cmid' . $page->cmid, $sink->deletes[0]['sourceid']);
    }

    /**
     * Test that unchanged content is not re-sent on the second ingest.
     */
    public function test_unchanged_content_is_skipped_on_second_ingest(): void {
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Stable</p>',
        ]);
        set_config('enabledcategories', (string) $course->category, 'local_elediaai_sources');

        $sink = $this->recording_sink();
        $manager = new ingestion_manager($sink);

        ob_start();
        $first = $manager->ingest_module($course->id, $page->cmid);
        ob_end_clean();
        $this->assertTrue($first['success']);
        $this->assertCount(1, $sink->payloads);
        $this->assertNotNull(cm_state::get((int) $page->cmid));

        // The course row carries the embedding model the comparison needs;
        // in production the reconcile writes it after a successful run.
        course_state::set_ingested((int) $course->id, true);

        ob_start();
        $second = $manager->ingest_module($course->id, $page->cmid);
        ob_end_clean();

        $this->assertSame('skipped', $second['status']);
        $this->assertStringContainsString('Unchanged', $second['message']);
        $this->assertCount(1, $sink->payloads, 'Unchanged content must not be re-sent.');

        // Changed content goes out again.
        global $DB;
        $DB->set_field('page', 'content', '<p>Edited</p>', ['id' => $page->id]);
        rebuild_course_cache((int) $course->id, true);

        ob_start();
        $third = $manager->ingest_module($course->id, $page->cmid);
        ob_end_clean();

        $this->assertTrue($third['success']);
        $this->assertCount(2, $sink->payloads);
    }

    /**
     * Test that a forced reindex re-sends even unchanged content.
     */
    public function test_forced_reindex_resends_unchanged_content(): void {
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Stable</p>',
        ]);
        set_config('enabledcategories', (string) $course->category, 'local_elediaai_sources');

        $sink = $this->recording_sink();
        $manager = new ingestion_manager($sink);

        ob_start();
        $manager->reindex_course($course->id);
        ob_end_clean();
        course_state::set_ingested((int) $course->id, true);
        $countafterfirst = count($sink->payloads);

        ob_start();
        $manager->reindex_course($course->id);
        ob_end_clean();
        $this->assertCount($countafterfirst, $sink->payloads, 'Plain reindex must skip unchanged content.');

        ob_start();
        $manager->reindex_course($course->id, true);
        ob_end_clean();
        $this->assertGreaterThan(
            $countafterfirst,
            count($sink->payloads),
            'Force must re-send — the recovery path when the destination lost data.'
        );
    }

    /**
     * Test that a module hidden from learners is removed from the index.
     *
     * Checked via the module path (not uservisible), because scheduled tasks
     * run as a user who sees hidden modules.
     */
    public function test_hidden_module_with_state_is_removed_from_index(): void {
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Was public once</p>',
            'visible' => 0,
        ]);
        set_config('enabledcategories', (string) $course->category, 'local_elediaai_sources');
        cm_state::record_success((int) $course->id, (int) $page->cmid, 'sid', 'h', 'recording');

        $sink = $this->recording_sink();
        $manager = new ingestion_manager($sink);

        ob_start();
        $result = $manager->ingest_module($course->id, $page->cmid);
        ob_end_clean();

        $this->assertSame('success', $result['status']);
        $this->assertStringContainsString('hidden', $result['message']);
        $this->assertCount(1, $sink->deletes);
        $this->assertSame('prefix', $sink->deletes[0]['scope']);
        $this->assertCount(0, $sink->payloads);
        $this->assertNull(cm_state::get((int) $page->cmid), 'Absence of a row means: not in the index.');
    }

    /**
     * Test that a hidden module without index state is only skipped.
     */
    public function test_hidden_module_without_state_is_skipped(): void {
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Never indexed</p>',
            'visible' => 0,
        ]);
        set_config('enabledcategories', (string) $course->category, 'local_elediaai_sources');

        $sink = $this->recording_sink();
        $manager = new ingestion_manager($sink);

        ob_start();
        $result = $manager->ingest_module($course->id, $page->cmid);
        ob_end_clean();

        $this->assertSame('skipped', $result['status']);
        $this->assertCount(0, $sink->deletes, 'Nothing to converge, nothing to delete.');
    }

    /**
     * Test that a module in a hidden section counts as hidden from learners.
     */
    public function test_module_in_hidden_section_is_removed_from_index(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['numsections' => 2]);
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Sectioned</p>',
            'section' => 1,
        ]);
        set_config('enabledcategories', (string) $course->category, 'local_elediaai_sources');
        cm_state::record_success((int) $course->id, (int) $page->cmid, 'sid', 'h', 'recording');

        $DB->set_field('course_sections', 'visible', 0, ['course' => $course->id, 'section' => 1]);
        rebuild_course_cache((int) $course->id, true);

        $sink = $this->recording_sink();
        $manager = new ingestion_manager($sink);

        ob_start();
        $result = $manager->ingest_module($course->id, $page->cmid);
        ob_end_clean();

        $this->assertSame('success', $result['status']);
        $this->assertCount(1, $sink->deletes);
        $this->assertNull(cm_state::get((int) $page->cmid));
    }

    /**
     * Test that a failed upsert leaves an error state behind.
     */
    public function test_failed_upsert_records_error_state(): void {
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Doomed</p>',
        ]);
        set_config('enabledcategories', (string) $course->category, 'local_elediaai_sources');

        $sink = new class implements \local_elediaai_sources\sink\sink {
            /** @var string[] Formats this destination announces beyond the core three. */
            public array $extratypes = [];

            #[\Override]
            public static function id(): string {
                return 'failing';
            }

            #[\Override]
            public static function name(): string {
                return 'Failing';
            }

            #[\Override]
            public function is_configured(): bool {
                return true;
            }

            #[\Override]
            public function healthcheck(): array {
                return ['success' => false, 'http_code' => 500, 'response' => '', 'error' => ''];
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
             * Simulate a failing upsert.
             *
             * @param array $payload The document payload.
             * @return array Failed result row.
             */
            #[\Override]
            public function upsert(array $payload): array {
                return ['success' => false, 'http_code' => 500, 'response' => '', 'error' => 'down'];
            }

            /**
             * Accept deletes.
             *
             * @param string $sourceid The source_id to delete.
             * @param string $scope Deletion scope.
             * @return array Successful result row.
             */
            #[\Override]
            public function delete(string $sourceid, string $scope = 'exact'): array {
                return ['success' => true, 'http_code' => 200, 'response' => '', 'error' => ''];
            }
        };

        $manager = new ingestion_manager($sink);

        ob_start();
        $result = $manager->ingest_module($course->id, $page->cmid);
        ob_end_clean();

        $this->assertSame('error', $result['status']);
        $row = cm_state::get((int) $page->cmid);
        $this->assertNotNull($row);
        $this->assertSame(cm_state::STATUS_ERROR, $row->laststatus);
        $this->assertSame('', $row->contenthash, 'Nothing ever reached the index.');
    }

    /**
     * The original file name reaches the destination.
     *
     * Without it the index can cite a document only by its source id, and a
     * parser log naming "…:cmid42:file3" tells nobody which upload failed.
     */
    public function test_payload_carries_the_original_filename(): void {
        $course = $this->getDataGenerator()->create_course();
        set_config('enabledcategories', (string) $course->category, 'local_elediaai_sources');
        $resource = $this->create_resource($course, 'Skript_Woche3.pdf', '%PDF-1.7 content', 'application/pdf');

        $sink = $this->recording_sink();
        $manager = new ingestion_manager($sink);

        ob_start();
        $result = $manager->ingest_module($course->id, $resource->cmid);
        ob_end_clean();

        $this->assertTrue($result['success']);
        $this->assertSame('Skript_Woche3.pdf', $sink->payloads[0]['qdrant_metadata']['title']);
    }

    /**
     * A negotiated format is exported exactly when the destination says it can
     * read it — and skipped with a reason naming the destination when it
     * cannot. This is the whole contract of AI-75 in one test.
     */
    public function test_negotiated_format_follows_the_destination(): void {
        $course = $this->getDataGenerator()->create_course();
        set_config('enabledcategories', (string) $course->category, 'local_elediaai_sources');
        $resource = $this->create_resource(
            $course,
            'Handout.docx',
            'PK binary docx bytes',
            \local_elediaai_sources\format_matrix::DOCX
        );

        // The pipeline has not announced DOCX: nothing is sent, and the reason
        // says why rather than claiming there was no content.
        $closed = $this->recording_sink();
        ob_start();
        $result = (new ingestion_manager($closed))->ingest_module($course->id, $resource->cmid);
        ob_end_clean();

        $this->assertFalse($result['success']);
        $this->assertSame('skipped', $result['status']);
        $this->assertSame([], $closed->payloads);
        $this->assertStringContainsString('Handout.docx', $result['message']);
        $this->assertStringNotContainsString(
            get_string('nocontent', 'local_elediaai_sources'),
            $result['message']
        );

        // The same site against a destination that announces DOCX.
        $open = $this->recording_sink();
        $open->extratypes = [\local_elediaai_sources\format_matrix::DOCX];

        ob_start();
        $result = (new ingestion_manager($open))->ingest_module($course->id, $resource->cmid);
        ob_end_clean();

        $this->assertTrue($result['success']);
        $this->assertCount(1, $open->payloads);
        $this->assertSame(
            \local_elediaai_sources\format_matrix::DOCX,
            $open->payloads[0]['content_type']
        );
        $this->assertSame(
            'PK binary docx bytes',
            base64_decode($open->payloads[0]['content'], true),
            'Binary content must travel untouched — no heading, no truncation.'
        );
    }

    /**
     * Resource and Folder answer the same question the same way.
     *
     * They used to keep one MIME list each, next to a third in this class;
     * the lists agreed only while someone remembered all three. This test is
     * the guard against them drifting apart again: the same file type, offered
     * through two activity types, must be treated identically.
     */
    public function test_resource_and_folder_agree_on_the_allowlist(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        set_config('enabledcategories', (string) $course->category, 'local_elediaai_sources');

        $resource = $this->create_resource(
            $course,
            'Handout.docx',
            'PK docx bytes',
            \local_elediaai_sources\format_matrix::DOCX
        );

        $folder = $this->getDataGenerator()->create_module('folder', [
            'course' => $course->id,
            'name' => 'Materialien',
        ]);
        $DB->set_field('folder', 'intro', '', ['id' => $folder->id]);
        $context = \core\context\module::instance($folder->cmid);
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id, 'component' => 'mod_folder', 'filearea' => 'content',
            'itemid' => 0, 'filepath' => '/', 'filename' => 'Handout.docx',
            'mimetype' => \local_elediaai_sources\format_matrix::DOCX,
        ], 'PK docx bytes');

        foreach ([[], [\local_elediaai_sources\format_matrix::DOCX]] as $announced) {
            $sink = $this->recording_sink();
            $sink->extratypes = $announced;
            $manager = new ingestion_manager($sink);

            ob_start();
            $fromresource = $manager->ingest_module($course->id, $resource->cmid);
            $fromfolder = $manager->ingest_module($course->id, $folder->cmid);
            ob_end_clean();

            $this->assertSame(
                $fromresource['success'],
                $fromfolder['success'],
                'Resource and Folder must reach the same verdict for the same file type.'
            );
            $this->assertSame(!empty($announced), $fromresource['success']);

            $types = array_values(array_unique(array_column($sink->payloads, 'content_type')));
            $this->assertSame(
                empty($announced) ? [] : [\local_elediaai_sources\format_matrix::DOCX],
                $types
            );
        }
    }

    /**
     * A file that cannot be sent is reported, not dropped.
     *
     * A Folder exporting four of its nine files while reporting success is
     * indistinguishable from a fully indexed one — which is how a course ends
     * up with an index nobody trusts.
     */
    public function test_unsupported_folder_files_are_reported(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        set_config('enabledcategories', (string) $course->category, 'local_elediaai_sources');

        $folder = $this->getDataGenerator()->create_module('folder', [
            'course' => $course->id,
            'name' => 'Gemischt',
        ]);
        $DB->set_field('folder', 'intro', '', ['id' => $folder->id]);

        $context = \core\context\module::instance($folder->cmid);
        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => $context->id, 'component' => 'mod_folder', 'filearea' => 'content',
            'itemid' => 0, 'filepath' => '/', 'filename' => 'skript.pdf', 'mimetype' => 'application/pdf',
        ], '%PDF-1.7 content');
        $fs->create_file_from_string([
            'contextid' => $context->id, 'component' => 'mod_folder', 'filearea' => 'content',
            'itemid' => 0, 'filepath' => '/', 'filename' => 'vorlesung.mp4', 'mimetype' => 'video/mp4',
        ], 'not really a video');

        $sink = $this->recording_sink();
        $manager = new ingestion_manager($sink);

        ob_start();
        $result = $manager->ingest_module($course->id, $folder->cmid);
        $log = ob_get_clean();

        $this->assertTrue($result['success'], 'The readable file is still indexed.');
        // Probe first, then the full set after the prefix delete — one document
        // therefore travels twice, which is the multi-document contract.
        $this->assertCount(2, $sink->payloads);
        $this->assertSame('skript.pdf', $sink->payloads[0]['qdrant_metadata']['title']);
        $this->assertStringContainsString('vorlesung.mp4', $log, 'The skipped file is named in the log.');
        $this->assertStringContainsString(
            get_string('ingestionmultiskipped', 'local_elediaai_sources', 1),
            $result['message']
        );

        $preview = $manager->preview_module(get_fast_modinfo($course->id)->get_cm($folder->cmid));
        $skipped = array_values(array_filter($preview['documents'], static fn($d) => !empty($d['skipped'])));
        $this->assertCount(1, $skipped);
        $this->assertSame('vorlesung.mp4', $skipped[0]['title']);
        $this->assertStringContainsString('video/mp4', $skipped[0]['skipreason']);
    }

    /**
     * Create a file resource with one file attached.
     *
     * @param \stdClass $course The course.
     * @param string $filename The file name.
     * @param string $content The file content.
     * @param string $mimetype The MIME type Moodle should record.
     * @return \stdClass The module record, carrying cmid.
     */
    private function create_resource(
        \stdClass $course,
        string $filename,
        string $content,
        string $mimetype
    ): \stdClass {
        $resource = $this->getDataGenerator()->create_module('resource', [
            'course' => $course->id,
            'name' => 'Datei',
        ]);

        $context = \core\context\module::instance($resource->cmid);
        $fs = get_file_storage();

        // The generator leaves a placeholder file behind; it would be the one
        // the extractor picks up.
        $fs->delete_area_files($context->id, 'mod_resource', 'content');

        $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_resource',
            'filearea' => 'content',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $filename,
            'mimetype' => $mimetype,
        ], $content);

        return $resource;
    }

    /**
     * A destination that records upsert payloads instead of sending them.
     *
     * @return \local_elediaai_sources\sink\sink Sink exposing a public $payloads array.
     */
    private function recording_sink(): \local_elediaai_sources\sink\sink {
        return new class implements \local_elediaai_sources\sink\sink {
            /** @var string[] Formats this destination announces beyond the core three. */
            public array $extratypes = [];

            /** @var array<int, array> Every payload passed to upsert(). */
            public array $payloads = [];

            /** @var array<int, array> Every delete call, as ['sourceid', 'scope']. */
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
             * Record the payload and report success.
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
             * Record the delete call and report success.
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
}

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

use local_literag\local\document_store;
use local_literag\local\ingest_exception;
use local_literag\local\tenant;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the ingestion document store (upsert/delete semantics).
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(document_store::class)]
final class document_store_test extends \advanced_testcase {
    /**
     * Build an upsert payload for the current site's tenant.
     *
     * @param string $content
     * @return array{0: string, 1: array}
     */
    private function fixture(string $content = 'photosynthesis converts light into energy'): array {
        global $CFG;
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $sourceid = tenant::id() . ':course' . $course->id . ':cmid' . $page->cmid;

        $payload = [
            'source_id' => $sourceid,
            'content' => base64_encode($content),
            'content_type' => 'text/plain',
            'qdrant_metadata' => [
                'tenant_id' => tenant::id(),
                'site_url' => $CFG->wwwroot,
                'course_id' => (string) $course->id,
                'cmid' => (string) $page->cmid,
                'module_url' => $CFG->wwwroot . '/mod/page/view.php?id=' . $page->cmid,
            ],
            'parser_options' => null,
        ];
        return [$sourceid, $payload];
    }

    /**
     * Upsert stores a source row and at least one chunk.
     */
    public function test_upsert_creates_source_and_chunks(): void {
        global $DB;
        $this->resetAfterTest();
        [$sid, $payload] = $this->fixture();

        (new document_store())->upsert($payload);

        $this->assertTrue($DB->record_exists('local_literag_sources', ['sourceid' => $sid]));
        $this->assertGreaterThanOrEqual(1, $DB->count_records('local_literag_chunks', ['sourceid' => $sid]));
    }

    /**
     * Re-upserting identical content does not duplicate chunks.
     */
    public function test_idempotent_upsert(): void {
        global $DB;
        $this->resetAfterTest();
        [$sid, $payload] = $this->fixture();
        $store = new document_store();

        $store->upsert($payload);
        $count1 = $DB->count_records('local_literag_chunks', ['sourceid' => $sid]);
        $store->upsert($payload);
        $count2 = $DB->count_records('local_literag_chunks', ['sourceid' => $sid]);

        $this->assertSame($count1, $count2);
    }

    /**
     * Prefix delete removes the module and its sub-documents, honouring the ':' boundary.
     */
    public function test_prefix_delete_boundary(): void {
        global $DB;
        $this->resetAfterTest();
        $store = new document_store();
        [$module, $payload] = $this->fixture();

        $sub = $module . ':file1';
        $sibling = $module . '0'; // Must NOT be matched by the prefix.

        $store->upsert($payload);
        $payload['source_id'] = $sub;
        $store->upsert($payload);
        $payload['source_id'] = $sibling;
        $store->upsert($payload);

        $store->delete($module, 'prefix');

        $this->assertFalse($DB->record_exists('local_literag_sources', ['sourceid' => $module]));
        $this->assertFalse($DB->record_exists('local_literag_sources', ['sourceid' => $sub]));
        $this->assertTrue($DB->record_exists('local_literag_sources', ['sourceid' => $sibling]));
        $this->assertFalse($DB->record_exists('local_literag_chunks', ['sourceid' => $module]));
        $this->assertFalse($DB->record_exists('local_literag_chunks', ['sourceid' => $sub]));
        $this->assertTrue($DB->record_exists('local_literag_chunks', ['sourceid' => $sibling]));
    }

    /**
     * Exact delete removes only the matching document.
     */
    public function test_exact_delete(): void {
        global $DB;
        $this->resetAfterTest();
        $store = new document_store();
        [$module, $payload] = $this->fixture();

        $sub = $module . ':file1';
        $store->upsert($payload);
        $payload['source_id'] = $sub;
        $store->upsert($payload);

        $store->delete($module, 'exact');

        $this->assertFalse($DB->record_exists('local_literag_sources', ['sourceid' => $module]));
        $this->assertTrue($DB->record_exists('local_literag_sources', ['sourceid' => $sub]));
    }

    /**
     * Deleting an unknown source_id is a no-op (idempotent), not an error.
     */
    public function test_delete_missing_is_noop(): void {
        $this->resetAfterTest();
        (new document_store())->delete('does:not:exist', 'prefix');
        $this->assertTrue(true);
    }

    /**
     * A cross-tenant payload is rejected with a 403.
     */
    public function test_tenant_mismatch_rejected(): void {
        $this->resetAfterTest();
        [, $payload] = $this->fixture();
        $payload['qdrant_metadata']['tenant_id'] = 'some-other-tenant';

        try {
            (new document_store())->upsert($payload);
            $this->fail('Expected ingest_exception');
        } catch (ingest_exception $e) {
            $this->assertSame(403, $e->httpstatus);
        }
    }

    /**
     * Invalid base64 content is rejected with a 400.
     */
    public function test_bad_base64_rejected(): void {
        $this->resetAfterTest();
        [, $payload] = $this->fixture();
        $payload['content'] = '@@@not base64@@@';

        try {
            (new document_store())->upsert($payload);
            $this->fail('Expected ingest_exception');
        } catch (ingest_exception $e) {
            $this->assertSame(400, $e->httpstatus);
        }
    }

    /**
     * Tenant identity is mandatory, not an optional mismatch check.
     */
    public function test_missing_tenant_rejected(): void {
        $this->resetAfterTest();
        [, $payload] = $this->fixture();
        unset($payload['qdrant_metadata']['tenant_id']);

        try {
            (new document_store())->upsert($payload);
            $this->fail('Expected ingest_exception');
        } catch (ingest_exception $e) {
            $this->assertSame(400, $e->httpstatus);
        }
    }

    /**
     * A course module cannot be attributed to another course.
     */
    public function test_cmid_course_mismatch_rejected(): void {
        $this->resetAfterTest();
        [, $payload] = $this->fixture();
        $othercourse = $this->getDataGenerator()->create_course();
        $payload['qdrant_metadata']['course_id'] = (string) $othercourse->id;

        try {
            (new document_store())->upsert($payload);
            $this->fail('Expected ingest_exception');
        } catch (ingest_exception $e) {
            $this->assertSame(400, $e->httpstatus);
        }
    }

    /**
     * Citation links must be the canonical URL for the claimed module.
     */
    public function test_external_module_url_rejected(): void {
        $this->resetAfterTest();
        [, $payload] = $this->fixture();
        $payload['qdrant_metadata']['module_url'] = 'https://example.net/phishing';

        try {
            (new document_store())->upsert($payload);
            $this->fail('Expected ingest_exception');
        } catch (ingest_exception $e) {
            $this->assertSame(400, $e->httpstatus);
        }
    }
}

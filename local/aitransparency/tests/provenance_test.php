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

namespace local_aitransparency;

use context_system;

/**
 * Tests for the provenance store API.
 *
 * @package    local_aitransparency
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_aitransparency\provenance
 * @covers     \local_aitransparency\record_request
 * @covers     \local_aitransparency\record
 */
final class provenance_test extends \advanced_testcase {
    /**
     * Build a request for a generated text output.
     *
     * @param string $content
     * @param int $contextid
     * @return record_request
     */
    private function request(string $content, int $contextid): record_request {
        return new record_request(
            component: 'aiprovider_eledia',
            actionname: 'generate_text',
            provider: 'aiprovider_eledia',
            model: 'test-model',
            userid: 7,
            contextid: $contextid,
            assettype: 'text',
            content: $content,
        );
    }

    public function test_record_creates_one_row_with_hash(): void {
        global $DB;
        $this->resetAfterTest();
        $ctx = context_system::instance()->id;

        $uuid = provenance::record($this->request('Hello AI world', $ctx));

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $uuid);
        $this->assertEquals(1, $DB->count_records('local_aitransparency_rec'));
        $row = $DB->get_record('local_aitransparency_rec', ['uuid' => $uuid]);
        $this->assertSame(hash('sha256', 'Hello AI world'), $row->contenthash);
        $this->assertSame('pending', $row->markstate);
        $this->assertSame('generate_text', $row->actionname);
    }

    public function test_double_call_is_idempotent(): void {
        global $DB;
        $this->resetAfterTest();
        $ctx = context_system::instance()->id;

        $first = provenance::record($this->request('same output', $ctx));
        $second = provenance::record($this->request('same output', $ctx));

        $this->assertSame($first, $second);
        $this->assertEquals(1, $DB->count_records('local_aitransparency_rec'));
    }

    public function test_same_content_different_context_is_distinct(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $coursectx = \context_course::instance($course->id)->id;

        $a = provenance::record($this->request('same output', context_system::instance()->id));
        $b = provenance::record($this->request('same output', $coursectx));

        $this->assertNotSame($a, $b);
        $this->assertEquals(2, $DB->count_records('local_aitransparency_rec'));
    }

    public function test_record_by_precomputed_hash(): void {
        $this->resetAfterTest();
        $hash = hash('sha256', 'precomputed');
        $uuid = provenance::record(new record_request(
            component: 'local_literag',
            actionname: 'generate_text',
            provider: 'local_literag',
            model: '',
            userid: 3,
            contextid: context_system::instance()->id,
            contenthash: $hash,
        ));
        $this->assertSame($hash, provenance::get($uuid)->contenthash);
    }

    public function test_request_without_content_or_hash_throws(): void {
        $this->expectException(\coding_exception::class);
        (new record_request(
            component: 'x',
            actionname: 'generate_text',
            provider: 'x',
            model: '',
            userid: 1,
            contextid: 1,
        ))->resolve_contenthash();
    }

    public function test_get_returns_record_object_or_null(): void {
        $this->resetAfterTest();
        $uuid = provenance::record($this->request('body', context_system::instance()->id));

        $record = provenance::get($uuid);
        $this->assertInstanceOf(record::class, $record);
        $this->assertSame($uuid, $record->uuid);
        $this->assertNull(provenance::get('00000000-0000-0000-0000-000000000000'));
    }

    public function test_get_by_contenthash(): void {
        $this->resetAfterTest();
        $ctx = context_system::instance()->id;
        $uuid = provenance::record($this->request('lookup me', $ctx));

        $found = provenance::get_by_contenthash(hash('sha256', 'lookup me'));
        $this->assertSame($uuid, $found->uuid);
        $this->assertNull(provenance::get_by_contenthash(hash('sha256', 'missing')));
    }

    public function test_set_mark_state_updates_and_validates(): void {
        global $DB;
        $this->resetAfterTest();
        $uuid = provenance::record($this->request('mark me', context_system::instance()->id));

        provenance::set_mark_state($uuid, 'embedded');
        $this->assertSame('embedded', $DB->get_field('local_aitransparency_rec', 'markstate', ['uuid' => $uuid]));

        $this->expectException(\coding_exception::class);
        provenance::set_mark_state($uuid, 'bogus');
    }
}

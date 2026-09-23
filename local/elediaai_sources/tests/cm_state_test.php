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
 * Unit tests for the per-module ingestion state.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_sources\cm_state
 */
final class cm_state_test extends \advanced_testcase {
    /**
     * Create the course-level state row is_current() compares the model against.
     *
     * @param int $courseid The course id.
     * @param string $model The recorded embedding model.
     */
    private function course_row(int $courseid, string $model = ''): void {
        global $DB;
        $DB->insert_record('local_elediaai_sources_course', (object) [
            'courseid' => $courseid,
            'ingested' => 1,
            'sink' => 'recording',
            'embeddingmodel' => $model,
            'tenant' => 'test',
            'timemodified' => time(),
        ]);
    }

    /**
     * Hashes are deterministic and sensitive to every part of the tuple.
     */
    public function test_hashes(): void {
        $a = cm_state::document_hash('s1', 'text/html', 'content');
        $this->assertSame($a, cm_state::document_hash('s1', 'text/html', 'content'));
        $this->assertNotSame($a, cm_state::document_hash('s1', 'text/html', 'other'));
        $this->assertNotSame($a, cm_state::document_hash('s2', 'text/html', 'content'));
        $this->assertNotSame($a, cm_state::document_hash('s1', 'text/plain', 'content'));

        // The title is part of the payload, so it is part of the hash: a file
        // renamed inside a Folder must be re-sent, or the index keeps citing
        // the old name forever.
        $titled = cm_state::document_hash('s1', 'text/html', 'content', 'Woche 3.pdf');
        $this->assertNotSame($a, $titled);
        $this->assertNotSame($titled, cm_state::document_hash('s1', 'text/html', 'content', 'Woche 4.pdf'));

        $agg = cm_state::aggregate_hash([$a]);
        $this->assertSame($agg, cm_state::aggregate_hash([$a]));
        $this->assertNotSame($agg, cm_state::aggregate_hash([$a, $a]));
    }

    /**
     * is_current() is true only when destination, source id, hash and the
     * course's embedding model all still match.
     */
    public function test_is_current_matches_exactly(): void {
        $this->resetAfterTest();
        $this->course_row(7);

        cm_state::record_success(7, 100, 'tenant:course7:cmid100', 'hash-a', 'recording');

        $this->assertTrue(cm_state::is_current(100, 'tenant:course7:cmid100', 'hash-a', 'recording', null));
        $this->assertFalse(cm_state::is_current(100, 'tenant:course7:cmid100', 'hash-B', 'recording', null));
        $this->assertFalse(cm_state::is_current(100, 'OTHER:course7:cmid100', 'hash-a', 'recording', null));
        $this->assertFalse(cm_state::is_current(100, 'tenant:course7:cmid100', 'hash-a', 'othersink', null));
        $this->assertFalse(
            cm_state::is_current(100, 'tenant:course7:cmid100', 'hash-a', 'recording', 'new-model'),
            'A changed embedding model must invalidate every module.'
        );
        $this->assertFalse(cm_state::is_current(999, 'x', 'hash-a', 'recording', null));
    }

    /**
     * Without a course-level row nothing counts as current — conservative
     * towards re-sending rather than towards skipping.
     */
    public function test_is_current_requires_course_row(): void {
        $this->resetAfterTest();

        cm_state::record_success(7, 100, 'sid', 'hash-a', 'recording');
        $this->assertFalse(cm_state::is_current(100, 'sid', 'hash-a', 'recording', null));
    }

    /**
     * A failed attempt keeps describing what the index actually holds.
     */
    public function test_record_error_preserves_index_truth(): void {
        $this->resetAfterTest();
        $this->course_row(7);

        cm_state::record_success(7, 100, 'sid', 'hash-a', 'recording');
        $before = cm_state::get(100);

        cm_state::record_error(7, 100, 'sid', 'boom', 'recording');
        $after = cm_state::get(100);

        $this->assertSame(cm_state::STATUS_ERROR, $after->laststatus);
        $this->assertSame('boom', $after->lasterror);
        $this->assertSame($before->contenthash, $after->contenthash, 'The index still holds the old content.');
        $this->assertEquals($before->timeingested, $after->timeingested);
        $this->assertFalse(cm_state::is_current(100, 'sid', 'hash-a', 'recording', null));
    }

    /**
     * An error without prior success records an empty hash.
     */
    public function test_record_error_without_prior_success(): void {
        $this->resetAfterTest();

        cm_state::record_error(7, 100, 'sid', 'boom', 'recording');
        $row = cm_state::get(100);

        $this->assertSame('', $row->contenthash);
        $this->assertEquals(0, $row->timeingested);
    }

    /**
     * Failures accumulate, a success wipes the slate.
     */
    public function test_attempts_count_up_and_reset(): void {
        $this->resetAfterTest();

        cm_state::record_error(7, 100, 'sid', 'boom', 'recording');
        $this->assertSame(1, (int) cm_state::get(100)->attempts);

        cm_state::record_error(7, 100, 'sid', 'boom', 'recording');
        $this->assertSame(2, (int) cm_state::get(100)->attempts);
        $this->assertFalse(cm_state::is_exhausted(100));

        cm_state::record_success(7, 100, 'sid', 'hash', 'recording');
        $this->assertSame(0, (int) cm_state::get(100)->attempts);
        $this->assertFalse(cm_state::is_exhausted(100));
    }

    /**
     * The budget runs out after the configured number of failures.
     */
    public function test_retry_budget_runs_out(): void {
        $this->resetAfterTest();

        for ($i = 1; $i < cm_state::MAX_ATTEMPTS; $i++) {
            cm_state::record_error(7, 100, 'sid', 'boom', 'recording');
            $this->assertFalse(cm_state::is_exhausted(100), "still retryable after {$i} attempt(s)");
        }

        cm_state::record_error(7, 100, 'sid', 'boom', 'recording');
        $this->assertTrue(cm_state::is_exhausted(100));

        // A forced reindex gives the module another chance.
        cm_state::reset_attempts(100);
        $this->assertFalse(cm_state::is_exhausted(100));
    }

    /**
     * The per-course summary separates what is in, what is worth retrying and
     * what was given up on — and only counts the destination asked about.
     */
    public function test_summary_by_course(): void {
        $this->resetAfterTest();

        cm_state::record_success(7, 100, 'a', 'h', 'recording');
        cm_state::record_error(7, 200, 'b', 'boom', 'recording');
        for ($i = 0; $i < cm_state::MAX_ATTEMPTS; $i++) {
            cm_state::record_error(7, 300, 'c', 'boom', 'recording');
        }
        cm_state::record_success(8, 400, 'd', 'h', 'othersink');

        $summary = cm_state::summary_by_course('recording');

        $this->assertSame(1, $summary[7]['success']);
        $this->assertSame(1, $summary[7]['retryable']);
        $this->assertSame(1, $summary[7]['exhausted']);
        $this->assertArrayNotHasKey(8, $summary, 'Rows of another destination are not counted.');
    }

    /**
     * forget() and forget_course() remove exactly their rows.
     */
    public function test_forget_scopes(): void {
        $this->resetAfterTest();

        cm_state::record_success(7, 100, 'a', 'h', 'recording');
        cm_state::record_success(7, 200, 'b', 'h', 'recording');
        cm_state::record_success(8, 300, 'c', 'h', 'recording');

        cm_state::forget(100);
        $this->assertNull(cm_state::get(100));
        $this->assertNotNull(cm_state::get(200));

        cm_state::forget_course(7);
        $this->assertNull(cm_state::get(200));
        $this->assertNotNull(cm_state::get(300));

        $this->assertSame([300], array_keys(cm_state::for_course(8)));
    }
}

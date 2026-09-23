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

use local_literag\local\retriever;
use local_literag\local\tenant;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for keyword retrieval (full-text or LIKE fallback) and scoping.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(retriever::class)]
final class retriever_test extends \advanced_testcase {
    /**
     * Insert a chunk row and return its source id.
     *
     * @param int $courseid
     * @param int $cmid
     * @param string $title
     * @param string $text
     * @param string|null $tenant
     * @return string
     */
    private function insert_chunk(int $courseid, int $cmid, string $title, string $text, ?string $tenant = null): string {
        global $DB, $CFG;
        $tenant = $tenant ?? tenant::id();
        $sourceid = "$tenant:course$courseid:cmid$cmid";
        $DB->insert_record('local_literag_chunks', (object) [
            'sourceid' => $sourceid,
            'tenant' => $tenant,
            'courseid' => $courseid,
            'contextid' => 0,
            'cmid' => $cmid,
            'sourcetype' => 'text',
            'sourcetitle' => $title,
            'moduleurl' => $CFG->wwwroot . "/mod/page/view.php?id=$cmid",
            'chunktext' => $text,
            'chunkhash' => sha1($text),
            'sortorder' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        return $sourceid;
    }

    /**
     * A chunk matching the query terms is retrieved.
     */
    public function test_matching_chunk_is_returned(): void {
        $this->resetAfterTest();
        $match = $this->insert_chunk(
            1,
            11,
            'Photosynthesis',
            'Photosynthesis converts sunlight carbon dioxide and water into glucose and oxygen.'
        );
        $this->insert_chunk(
            1,
            12,
            'Mitosis',
            'Mitosis is the process of nuclear division producing two identical daughter cells.'
        );

        $results = (new retriever())->candidates('How does photosynthesis work?', [1], 20);

        $ids = array_map(static fn($r) => $r->sourceid, $results);
        $this->assertContains($match, $ids);
    }

    /**
     * Retrieval is scoped to the requested courses.
     */
    public function test_course_scoping(): void {
        $this->resetAfterTest();
        $this->insert_chunk(1, 11, 'Photosynthesis', 'Photosynthesis converts sunlight into glucose energy.');
        $other = $this->insert_chunk(2, 21, 'Photosynthesis', 'Photosynthesis converts sunlight into glucose energy.');

        $results = (new retriever())->candidates('photosynthesis glucose energy', [1], 20);
        $ids = array_map(static fn($r) => $r->sourceid, $results);

        $this->assertNotContains($other, $ids);
    }

    /**
     * No accessible courses means no results.
     */
    public function test_empty_courses_returns_nothing(): void {
        $this->resetAfterTest();
        $this->insert_chunk(1, 11, 'Photosynthesis', 'Photosynthesis converts sunlight into glucose.');
        $this->assertSame([], (new retriever())->candidates('photosynthesis', [], 20));
    }

    /**
     * A different tenant's chunk is never returned.
     */
    public function test_tenant_scoping(): void {
        $this->resetAfterTest();
        $foreign = $this->insert_chunk(
            1,
            11,
            'Photosynthesis',
            'Photosynthesis converts sunlight into glucose energy.',
            'some-other-tenant'
        );

        $results = (new retriever())->candidates('photosynthesis glucose energy', [1], 20);
        $ids = array_map(static fn($r) => $r->sourceid, $results);
        $this->assertNotContains($foreign, $ids);
    }
}

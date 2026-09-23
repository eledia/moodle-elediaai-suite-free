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

use local_literag\local\conversation_repository;
use local_literag\local\tenant;
use local_literag\local\user_eraser;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for user-scoped erasure shared by privacy export and MCP tools.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(user_eraser::class)]
final class user_eraser_test extends \advanced_testcase {
    /**
     * Insert memory and query-log rows for a user.
     *
     * @param int $userid User id.
     * @return void
     */
    private function insert_user_scoped_rows(int $userid): void {
        global $DB;

        $now = time();
        $tenant = tenant::id();
        $DB->insert_record('local_literag_memory', (object) [
            'userid' => $userid,
            'tenant' => $tenant,
            'mkey' => 'preference',
            'mvalue' => 'Likes concise hints.',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $DB->insert_record('local_literag_query_log', (object) [
            'userid' => $userid,
            'courseid' => 42,
            'tenant' => $tenant,
            'querytext' => 'Explain photosynthesis',
            'numcandidates' => 4,
            'numreturned' => 2,
            'usedllm' => 1,
            'reranked' => 0,
            'latencyms' => 123,
            'timecreated' => $now,
        ]);
    }

    /**
     * Erasing one user removes all tutor data for that user only.
     */
    public function test_erase_removes_only_target_user_data(): void {
        global $DB;

        $this->resetAfterTest();
        $target = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $repo = new conversation_repository();

        $targetconversation = $repo->create((int) $target->id, 42, 'explain');
        $repo->add_message($targetconversation, 'user', 'Please explain photosynthesis.');
        $repo->add_message($targetconversation, 'assistant', 'Photosynthesis creates glucose.');
        $this->insert_user_scoped_rows((int) $target->id);

        $otherconversation = $repo->create((int) $other->id, 42, 'explain');
        $repo->add_message($otherconversation, 'user', 'What is mitosis?');
        $this->insert_user_scoped_rows((int) $other->id);

        $counts = user_eraser::erase((int) $target->id);

        $this->assertSame(['conversations' => 1, 'memories' => 1], $counts);
        $this->assertFalse($DB->record_exists('local_literag_conversations', ['userid' => $target->id]));
        $this->assertFalse($DB->record_exists('local_literag_messages', ['userid' => $target->id]));
        $this->assertFalse($DB->record_exists('local_literag_memory', ['userid' => $target->id]));
        $this->assertFalse($DB->record_exists('local_literag_query_log', ['userid' => $target->id]));

        $this->assertTrue($DB->record_exists('local_literag_conversations', ['userid' => $other->id]));
        $this->assertTrue($DB->record_exists('local_literag_messages', ['userid' => $other->id]));
        $this->assertTrue($DB->record_exists('local_literag_memory', ['userid' => $other->id]));
        $this->assertTrue($DB->record_exists('local_literag_query_log', ['userid' => $other->id]));
    }
}

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

use local_literag\local\tenant;
use local_literag\task\prune_logs;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the LiteRAG retention cleanup scheduled task.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(prune_logs::class)]
final class prune_logs_test extends \advanced_testcase {
    /**
     * Insert a query-log row with a specific timestamp.
     *
     * @param int $timecreated Row timestamp.
     * @return int Row id.
     */
    private function insert_query_log(int $timecreated): int {
        global $DB;

        return (int) $DB->insert_record('local_literag_query_log', (object) [
            'userid' => 7,
            'courseid' => 42,
            'tenant' => tenant::id(),
            'querytext' => 'How does retention work?',
            'numcandidates' => 3,
            'numreturned' => 2,
            'usedllm' => 1,
            'reranked' => 0,
            'latencyms' => 25,
            'timecreated' => $timecreated,
        ]);
    }

    /**
     * Insert a conversation plus one message with a specific modification time.
     *
     * @param int $timemodified Conversation modification timestamp.
     * @return array{conversationid:int,messageid:int}
     */
    private function insert_conversation(int $timemodified): array {
        global $DB;

        $conversationid = (int) $DB->insert_record('local_literag_conversations', (object) [
            'convkey' => sha1('conversation-' . $timemodified . '-' . random_string(8)),
            'userid' => 7,
            'courseid' => 42,
            'tenant' => tenant::id(),
            'lastanswerstyle' => 'concise',
            'pendingaction' => null,
            'timecreated' => $timemodified,
            'timemodified' => $timemodified,
        ]);

        $messageid = (int) $DB->insert_record('local_literag_messages', (object) [
            'conversationid' => $conversationid,
            'userid' => 7,
            'role' => 'user',
            'content' => 'Please explain the retention policy.',
            'sourcesjson' => null,
            'topic' => null,
            'primarycmid' => 0,
            'timecreated' => $timemodified,
        ]);

        return [
            'conversationid' => $conversationid,
            'messageid' => $messageid,
        ];
    }

    /**
     * Old query logs and expired conversations are pruned, fresh rows stay.
     */
    public function test_execute_prunes_rows_older_than_configured_retention(): void {
        global $DB;

        $this->resetAfterTest();
        set_config('query_log_retention_days', 10, 'local_literag');
        set_config('conversation_retention_days', 30, 'local_literag');

        $now = time();
        $oldqueryid = $this->insert_query_log($now - (11 * DAYSECS));
        $freshqueryid = $this->insert_query_log($now - (9 * DAYSECS));
        $oldconversation = $this->insert_conversation($now - (31 * DAYSECS));
        $freshconversation = $this->insert_conversation($now - (29 * DAYSECS));

        (new prune_logs())->execute();

        $this->assertFalse($DB->record_exists('local_literag_query_log', ['id' => $oldqueryid]));
        $this->assertTrue($DB->record_exists('local_literag_query_log', ['id' => $freshqueryid]));

        $this->assertFalse($DB->record_exists('local_literag_conversations', [
            'id' => $oldconversation['conversationid'],
        ]));
        $this->assertFalse($DB->record_exists('local_literag_messages', [
            'id' => $oldconversation['messageid'],
        ]));

        $this->assertTrue($DB->record_exists('local_literag_conversations', [
            'id' => $freshconversation['conversationid'],
        ]));
        $this->assertTrue($DB->record_exists('local_literag_messages', [
            'id' => $freshconversation['messageid'],
        ]));
    }

    /**
     * A retention value of zero keeps existing rows.
     */
    public function test_execute_keeps_rows_when_retention_is_disabled(): void {
        global $DB;

        $this->resetAfterTest();
        set_config('query_log_retention_days', 0, 'local_literag');
        set_config('conversation_retention_days', 0, 'local_literag');

        $oldtime = time() - (365 * DAYSECS);
        $queryid = $this->insert_query_log($oldtime);
        $conversation = $this->insert_conversation($oldtime);

        (new prune_logs())->execute();

        $this->assertTrue($DB->record_exists('local_literag_query_log', ['id' => $queryid]));
        $this->assertTrue($DB->record_exists('local_literag_conversations', [
            'id' => $conversation['conversationid'],
        ]));
        $this->assertTrue($DB->record_exists('local_literag_messages', [
            'id' => $conversation['messageid'],
        ]));
    }
}

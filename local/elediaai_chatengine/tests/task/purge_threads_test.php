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

namespace local_elediaai_chatengine\task;

use local_elediaai_chatengine\local\message;
use local_elediaai_chatengine\local\mode;
use local_elediaai_chatengine\local\thread_store;
use local_elediaai_chatengine\local\usage;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The nightly clean-up: daily message counters and expired conversations.
 *
 * @package     local_elediaai_chatengine
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(purge_threads::class)]
final class purge_threads_test extends \advanced_testcase {
    /**
     * Write a counter row for a day a number of days ago.
     *
     * @param int $userid The user id.
     * @param int $daysago How many days before today.
     * @return int The day key written.
     */
    private function counter(int $userid, int $daysago): int {
        global $DB;

        $daykey = (int) date('Ymd', time() - $daysago * DAYSECS);
        $DB->insert_record(usage::TABLE, (object) [
            'userid' => $userid,
            'daykey' => $daykey,
            'messagecount' => 3,
        ]);
        return $daykey;
    }

    /**
     * Run the task with its output swallowed.
     *
     * @return void
     */
    private function run_task(): void {
        ob_start();
        (new purge_threads())->execute();
        ob_end_clean();
    }

    /**
     * Counters past the fixed retention go, younger ones stay, with conversation retention off.
     *
     * @return void
     */
    public function test_prunes_counters_past_retention(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $expired = $this->counter((int) $user->id, usage::RETENTION_DAYS + 1);
        $kept = $this->counter((int) $user->id, usage::RETENTION_DAYS - 1);
        $today = $this->counter((int) $user->id, 0);

        $this->run_task();

        $this->assertFalse($DB->record_exists(usage::TABLE, ['daykey' => $expired]));
        $this->assertTrue($DB->record_exists(usage::TABLE, ['daykey' => $kept]));
        $this->assertTrue($DB->record_exists(usage::TABLE, ['daykey' => $today]));
    }

    /**
     * Conversation retention still applies alongside the counter clean-up.
     *
     * @return void
     */
    public function test_still_purges_expired_conversations(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('retentiondays', 30, 'local_elediaai_chatengine');
        $user = $this->getDataGenerator()->create_user();

        $old = thread_store::create(
            'block_elediaai_tutor',
            1,
            (int) SYSCONTEXTID,
            0,
            (int) $user->id,
            null,
            'literag',
            mode::GROUNDED,
            'hash'
        );
        thread_store::add_message((int) $old->id, message::ROLE_USER, 'Old question');
        $DB->set_field(thread_store::THREAD_TABLE, 'timemodified', time() - 31 * DAYSECS, ['id' => $old->id]);
        $fresh = thread_store::create(
            'block_elediaai_tutor',
            2,
            (int) SYSCONTEXTID,
            0,
            (int) $user->id,
            null,
            'literag',
            mode::GROUNDED,
            'hash'
        );

        $this->run_task();

        $this->assertFalse($DB->record_exists(thread_store::THREAD_TABLE, ['id' => $old->id]));
        $this->assertFalse($DB->record_exists(thread_store::MESSAGE_TABLE, ['threadid' => $old->id]));
        $this->assertTrue($DB->record_exists(thread_store::THREAD_TABLE, ['id' => $fresh->id]));
    }
}

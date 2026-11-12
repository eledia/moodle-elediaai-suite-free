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

/**
 * Concurrency test for the quota reservation (ai_suite#114).
 *
 * @package    local_elediaai_core
 * @category   test
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core;

use advanced_testcase;
use local_elediaai_core\local\quota_manager;

/**
 * Interleaves a reservation with a commit of another request of the same user.
 *
 * @covers \local_elediaai_core\local\quota_manager
 */
final class quota_manager_race_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('quota_completion_buffer', 0, 'local_elediaai_core');
        // Only the day window is limited, so the user has exactly one usage row.
        set_config('quota_student_day', 100000, 'local_elediaai_core');
    }

    /**
     * The one usage row of the user (day window).
     *
     * @param int $userid
     * @return \stdClass
     */
    private function usage_row(int $userid): \stdClass {
        global $DB;
        return $DB->get_record(quota_manager::TABLE, [
            'userid' => $userid,
            'rolebucket' => quota_manager::BUCKET_STUDENT,
            'windowtype' => quota_manager::WINDOW_DAY,
        ], '*', MUST_EXIST);
    }

    /**
     * Reference without any interleaving: reserve, commit, reserve again.
     */
    public function test_sequential_reserve_commit_reserve_keeps_all_counters(): void {
        $user = $this->getDataGenerator()->create_user();
        $userid = (int) $user->id;

        $first = quota_manager::reserve($userid, 1000, 'phpunit');
        quota_manager::commit($userid, $first, 2000, 1000, 'phpunit');
        quota_manager::reserve($userid, 500, 'phpunit');

        $row = $this->usage_row($userid);
        $this->assertSame(3000, (int) $row->totaltokens);
        $this->assertSame(2000, (int) $row->prompttokens);
        $this->assertSame(1000, (int) $row->completiontokens);
        $this->assertSame(1, (int) $row->requestcount);
        $this->assertSame(500, (int) $row->reservedtokens);
    }

    /**
     * A commit that lands between the read and the write-back of a reservation must survive.
     *
     * Request A (1000 reserved) is still running. Request B starts to reserve 500 and has read the
     * usage row. Now A is settled with 3000 tokens (without the quota lock, as in the product). B
     * then writes its reservation back. Expected: A's usage is still there and only B's 500 are reserved.
     */
    public function test_reservation_does_not_overwrite_usage_committed_in_between(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $userid = (int) $user->id;

        // Request A reserves first; this also creates the lock factory before $DB is replaced.
        $reservationa = quota_manager::reserve($userid, 1000, 'phpunit');

        $realdb = $DB;
        $commita = function () use ($userid, $reservationa): void {
            quota_manager::commit($userid, $reservationa, 2000, 1000, 'phpunit');
        };
        $DB = new class ($realdb, $commita) {
            /** @var callable|null Runs once, right after the usage row was read. */
            private $afterread;

            /** @var object The real database. */
            private $db;

            /**
             * Wrap the real database.
             *
             * @param object $db The real database.
             * @param callable $afterread Runs once after the usage row was read.
             */
            public function __construct($db, callable $afterread) {
                $this->db = $db;
                $this->afterread = $afterread;
            }

            /**
             * Forward the call; after the first read of the usage row run the callback.
             *
             * @param string $name Method name.
             * @param array $arguments Arguments.
             * @return mixed
             */
            public function __call(string $name, array $arguments) {
                $result = $this->db->$name(...$arguments);
                if ($name === 'get_record' && ($arguments[0] ?? '') === quota_manager::TABLE && $this->afterread) {
                    $callback = $this->afterread;
                    $this->afterread = null;
                    $callback();
                }
                return $result;
            }

            /**
             * Forward property reads (e.g. the table prefix).
             *
             * @param string $name Property name.
             * @return mixed
             */
            public function __get(string $name) {
                return $this->db->$name;
            }
        };

        try {
            quota_manager::reserve($userid, 500, 'phpunit');
        } finally {
            $DB = $realdb;
        }

        $row = $this->usage_row($userid);
        $this->assertSame(3000, (int) $row->totaltokens, 'The settled usage of request A was lost.');
        $this->assertSame(2000, (int) $row->prompttokens);
        $this->assertSame(1000, (int) $row->completiontokens);
        $this->assertSame(1, (int) $row->requestcount);
        $this->assertSame(500, (int) $row->reservedtokens, 'The released reservation of request A came back.');
    }
}

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
 * Token quota tests.
 *
 * @package    local_elediaai_core
 * @category   test
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core;

use advanced_testcase;
use local_elediaai_core\local\quota_manager;
use local_elediaai_core\local\quota_reservation;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests the component behavior and contracts.
 *
 * @covers \local_elediaai_core\local\quota_manager
 */
final class quota_manager_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        // Keep the hard-reservation tests deterministic: no completion buffer.
        set_config('quota_completion_buffer', 0, 'local_elediaai_core');
    }

    public function test_records_hourly_daily_and_monthly_token_windows(): void {
        $user = $this->getDataGenerator()->create_user();

        quota_manager::record_usage((int) $user->id, 10, 5, 'phpunit');
        quota_manager::record_usage((int) $user->id, 3, 2, 'phpunit');

        $this->assertSame(20, quota_manager::used_tokens(
            (int) $user->id,
            quota_manager::BUCKET_STUDENT,
            quota_manager::WINDOW_HOUR
        ));
        $this->assertSame(20, quota_manager::used_tokens(
            (int) $user->id,
            quota_manager::BUCKET_STUDENT,
            quota_manager::WINDOW_DAY
        ));
        $this->assertSame(20, quota_manager::used_tokens(
            (int) $user->id,
            quota_manager::BUCKET_STUDENT,
            quota_manager::WINDOW_MONTH
        ));
    }

    public function test_assert_can_request_enforces_student_limit(): void {
        $user = $this->getDataGenerator()->create_user();
        set_config('quota_student_day', 25, 'local_elediaai_core');

        quota_manager::record_usage((int) $user->id, 20, 0, 'phpunit');
        quota_manager::assert_can_request((int) $user->id, 5);

        $this->expectException(\moodle_exception::class);
        quota_manager::assert_can_request((int) $user->id, 6);
    }

    public function test_assert_can_request_enforces_student_month_limit(): void {
        $user = $this->getDataGenerator()->create_user();
        set_config('quota_student_month', 30, 'local_elediaai_core');

        quota_manager::record_usage((int) $user->id, 20, 5, 'phpunit');
        quota_manager::assert_can_request((int) $user->id, 5);

        $this->expectException(\moodle_exception::class);
        quota_manager::assert_can_request((int) $user->id, 6);
    }

    public function test_teacher_role_uses_teacher_bucket(): void {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user((int) $user->id, (int) $course->id, 'editingteacher');

        $this->assertSame(quota_manager::BUCKET_TEACHER, quota_manager::role_bucket((int) $user->id));

        set_config('quota_student_day', 1, 'local_elediaai_core');
        set_config('quota_teacher_day', 100, 'local_elediaai_core');

        quota_manager::record_usage((int) $user->id, 50, 0, 'phpunit');
        quota_manager::assert_can_request((int) $user->id, 49);
    }

    public function test_reserve_blocks_parallel_preflights(): void {
        $user = $this->getDataGenerator()->create_user();
        set_config('quota_student_hour', 100, 'local_elediaai_core');
        set_config('quota_student_day', 100, 'local_elediaai_core');

        // Two in-flight requests reserve against the same pre-state and pass.
        $first = quota_manager::reserve((int) $user->id, 40, 'phpunit');
        $second = quota_manager::reserve((int) $user->id, 40, 'phpunit');
        $this->assertSame(40, $first->amount);
        $this->assertSame(40, $second->amount);

        // A third parallel request cannot overdraw the limit anymore.
        $this->expectException(\moodle_exception::class);
        quota_manager::reserve((int) $user->id, 30, 'phpunit');
    }

    public function test_reserve_exact_limit_passes(): void {
        $user = $this->getDataGenerator()->create_user();
        set_config('quota_student_day', 50, 'local_elediaai_core');

        $reserved = quota_manager::reserve((int) $user->id, 50, 'phpunit');
        $this->assertSame(50, $reserved->amount);

        $this->expectException(\moodle_exception::class);
        quota_manager::reserve((int) $user->id, 1, 'phpunit');
    }

    public function test_commit_corrects_to_actual(): void {
        $user = $this->getDataGenerator()->create_user();
        set_config('quota_student_day', 200, 'local_elediaai_core');

        $reserved = quota_manager::reserve((int) $user->id, 100, 'phpunit');
        $this->assertSame(100, $reserved->amount);

        quota_manager::commit((int) $user->id, $reserved, 80, 10, 'phpunit');

        // Actual usage is charged and the unused reservation is released.
        $this->assertSame(90, quota_manager::used_tokens(
            (int) $user->id,
            quota_manager::BUCKET_STUDENT,
            quota_manager::WINDOW_DAY
        ));
        $this->assertSame(0, $this->reserved_tokens((int) $user->id, quota_manager::WINDOW_DAY));

        // The released headroom is usable by the next request.
        quota_manager::reserve((int) $user->id, 110, 'phpunit');
    }

    public function test_release_frees_reserved_tokens(): void {
        $user = $this->getDataGenerator()->create_user();
        set_config('quota_student_day', 100, 'local_elediaai_core');

        $reserved = quota_manager::reserve((int) $user->id, 70, 'phpunit');

        quota_manager::release((int) $user->id, $reserved);

        $this->assertSame(0, $this->reserved_tokens((int) $user->id, quota_manager::WINDOW_DAY));
        // The full limit is available again after the error path.
        quota_manager::reserve((int) $user->id, 100, 'phpunit');
    }

    public function test_release_uses_original_window_and_role_bucket(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        set_config('quota_student_hour', 100, 'local_elediaai_core');

        $reservation = quota_manager::reserve((int) $user->id, 40, 'phpunit');
        $currenthour = $reservation->window_start(quota_manager::WINDOW_HOUR);
        $originalhour = $currenthour - HOURSECS;
        $DB->set_field(quota_manager::TABLE, 'windowstart', $originalhour, [
            'userid' => $user->id,
            'rolebucket' => quota_manager::BUCKET_STUDENT,
            'windowtype' => quota_manager::WINDOW_HOUR,
            'windowstart' => $currenthour,
        ]);

        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user((int) $user->id, (int) $course->id, 'editingteacher');
        $originalreservation = new quota_reservation(
            (int) $user->id,
            quota_manager::BUCKET_STUDENT,
            $reservation->amount,
            [
                quota_manager::WINDOW_HOUR => $originalhour,
                quota_manager::WINDOW_DAY => $reservation->window_start(quota_manager::WINDOW_DAY),
                quota_manager::WINDOW_MONTH => $reservation->window_start(quota_manager::WINDOW_MONTH),
            ],
            [quota_manager::WINDOW_HOUR]
        );

        quota_manager::release((int) $user->id, $originalreservation);

        $this->assertSame(0, (int) $DB->get_field(quota_manager::TABLE, 'reservedtokens', [
            'userid' => $user->id,
            'rolebucket' => quota_manager::BUCKET_STUDENT,
            'windowtype' => quota_manager::WINDOW_HOUR,
            'windowstart' => $originalhour,
        ]));
    }

    public function test_commit_charges_original_window(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        set_config('quota_student_hour', 100, 'local_elediaai_core');

        $reservation = quota_manager::reserve((int) $user->id, 40, 'phpunit');
        $currenthour = $reservation->window_start(quota_manager::WINDOW_HOUR);
        $originalhour = $currenthour - HOURSECS;
        $DB->set_field(quota_manager::TABLE, 'windowstart', $originalhour, [
            'userid' => $user->id,
            'rolebucket' => quota_manager::BUCKET_STUDENT,
            'windowtype' => quota_manager::WINDOW_HOUR,
            'windowstart' => $currenthour,
        ]);
        $originalreservation = new quota_reservation(
            (int) $user->id,
            quota_manager::BUCKET_STUDENT,
            $reservation->amount,
            [
                quota_manager::WINDOW_HOUR => $originalhour,
                quota_manager::WINDOW_DAY => $reservation->window_start(quota_manager::WINDOW_DAY),
                quota_manager::WINDOW_MONTH => $reservation->window_start(quota_manager::WINDOW_MONTH),
            ],
            [quota_manager::WINDOW_HOUR]
        );

        quota_manager::commit((int) $user->id, $originalreservation, 25, 5, 'phpunit');

        $this->assertSame(30, (int) $DB->get_field(quota_manager::TABLE, 'totaltokens', [
            'userid' => $user->id,
            'rolebucket' => quota_manager::BUCKET_STUDENT,
            'windowtype' => quota_manager::WINDOW_HOUR,
            'windowstart' => $originalhour,
        ]));
        $this->assertFalse($DB->record_exists(quota_manager::TABLE, [
            'userid' => $user->id,
            'rolebucket' => quota_manager::BUCKET_STUDENT,
            'windowtype' => quota_manager::WINDOW_HOUR,
            'windowstart' => $currenthour,
        ]));
    }

    public function test_reserve_includes_completion_buffer(): void {
        $user = $this->getDataGenerator()->create_user();
        set_config('quota_student_day', 1000, 'local_elediaai_core');
        set_config('quota_completion_buffer', 150, 'local_elediaai_core');

        $reserved = quota_manager::reserve((int) $user->id, 25, 'phpunit');

        $this->assertSame(175, $reserved->amount);
        $this->assertSame(175, $this->reserved_tokens((int) $user->id, quota_manager::WINDOW_DAY));
    }

    public function test_reserve_partial_rollback_across_windows(): void {
        $user = $this->getDataGenerator()->create_user();
        set_config('quota_student_hour', 200, 'local_elediaai_core');
        set_config('quota_student_day', 50, 'local_elediaai_core');

        try {
            quota_manager::reserve((int) $user->id, 60, 'phpunit');
            $this->fail('reserve should have thrown');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_quota_token_exceeded', $e->errorcode);
        }

        // The already-reserved hour window must be rolled back.
        $this->assertSame(0, $this->reserved_tokens((int) $user->id, quota_manager::WINDOW_HOUR));
        $this->assertSame(0, $this->reserved_tokens((int) $user->id, quota_manager::WINDOW_DAY));
    }

    public function test_image_cost_uses_size_and_count_defaults(): void {
        $this->assertSame(1000, quota_manager::action_cost('generate_image', [
            'size' => '256x256',
            'numimages' => 1,
        ]));
        $this->assertSame(8000, quota_manager::action_cost('generate_image', [
            'size' => '1024x1024',
            'numimages' => 2,
        ]));
    }

    public function test_image_reservation_and_commit_use_image_credits(): void {
        $user = $this->getDataGenerator()->create_user();
        set_config('quota_student_day', 5000, 'local_elediaai_core');

        $reserved = quota_manager::reserve_for_action(
            (int) $user->id,
            'generate_image',
            ['size' => '512x512', 'numimages' => 2],
            'phpunit'
        );
        $this->assertSame(4000, $reserved->amount);

        quota_manager::commit_action(
            (int) $user->id,
            $reserved,
            'generate_image',
            ['size' => '512x512', 'numimages' => 1],
            0,
            0,
            'phpunit'
        );

        $this->assertSame(2000, quota_manager::used_tokens(
            (int) $user->id,
            quota_manager::BUCKET_STUDENT,
            quota_manager::WINDOW_DAY
        ));
    }

    /**
     * Reserved tokens in a window for the student bucket.
     *
     * @param int $userid
     * @param string $window
     * @return int
     */
    private function reserved_tokens(int $userid, string $window): int {
        global $DB;

        $row = $DB->get_record(quota_manager::TABLE, [
            'userid' => $userid,
            'rolebucket' => quota_manager::BUCKET_STUDENT,
            'windowtype' => $window,
        ]);
        return $row ? (int) $row->reservedtokens : 0;
    }
}

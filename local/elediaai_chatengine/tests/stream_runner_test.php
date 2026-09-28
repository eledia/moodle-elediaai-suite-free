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

namespace local_elediaai_chatengine;

use local_elediaai_chatengine\local\stream_runner;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the error message a streamed turn shows.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(stream_runner::class)]
final class stream_runner_test extends \advanced_testcase {
    /**
     * A spent quota is shown to a learner as it is, not as a backend failure.
     */
    public function test_learner_sees_refusals_written_for_them(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));
        $context = \context_course::instance($course->id);

        $quota = new \moodle_exception(
            'error_quota_token_exceeded',
            'local_elediaai_core',
            '',
            (object) ['limit' => 100, 'window' => 'day']
        );
        $this->assertSame($quota->getMessage(), stream_runner::error_message($quota, $context));

        $rate = new \moodle_exception('error_rate_limited', 'local_elediaai_chatengine', '', 12);
        $this->assertSame($rate->getMessage(), stream_runner::error_message($rate, $context));
    }

    /**
     * A technical failure stays generic for a learner and detailed for an admin.
     */
    public function test_technical_failures_stay_generic_for_learners(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $failure = new \moodle_exception('error_service_unavailable', 'local_elediaai_chatengine');
        $generic = get_string('error_backend_unavailable', 'local_elediaai_chatengine');

        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));
        $this->assertSame($generic, stream_runner::error_message($failure, $context));

        $this->setAdminUser();
        $this->assertSame($failure->getMessage(), stream_runner::error_message($failure, $context));
    }
}

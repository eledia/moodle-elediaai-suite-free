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

namespace block_elediaai_tutor;

use PHPUnit\Framework\Attributes\CoversClass;
use block_elediaai_tutor\external\delete_my_data;
use block_elediaai_tutor\external\generate_copilot_analysis;
use block_elediaai_tutor\external\set_ltm;
use block_elediaai_tutor\local\ltm;
use local_elediaai_chatengine\local\message;
use local_elediaai_chatengine\local\mode;
use local_elediaai_chatengine\local\thread_store;

/**
 * Tests for the block's own external functions.
 *
 * Chatting itself goes through the shared endpoints of the chat engine now, so
 * what is left here is what only the tutor offers: consent, long-term memory,
 * the teacher's copilot analysis and the user-initiated erase. The scope gates
 * the removed endpoints used to enforce moved into the placement and are
 * covered by placement_test.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\block_elediaai_tutor\external\generate_copilot_analysis::class)]
#[CoversClass(\block_elediaai_tutor\external\set_ltm::class)]
#[CoversClass(\block_elediaai_tutor\external\delete_my_data::class)]
#[CoversClass(\block_elediaai_tutor\external\helper::class)]
final class external_test extends \advanced_testcase {
    /**
     * The copilot analysis is reserved for report viewers: an ordinary
     * student in the course is rejected before any data is touched.
     */
    public function test_generate_copilot_analysis_requires_viewreports(): void {
        $this->resetAfterTest();
        set_config('enableanalytics', 1, 'block_elediaai_tutor');
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        generate_copilot_analysis::execute((int) $course->id);
    }

    /**
     * The copilot analysis refuses to run while question analytics is off.
     */
    public function test_generate_copilot_analysis_needs_no_analytics_switch(): void {
        $this->resetAfterTest();
        // Der Schalter des alten Frage-Logs stand vor der Analyse und ist
        // standardmaessig aus -- die Deutung war damit auf den meisten
        // Websites unerreichbar. Der Turn-Speicher laeuft immer, also
        // entscheidet nur noch die Berechtigung.
        set_config('enableanalytics', 0, 'block_elediaai_tutor');
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        try {
            generate_copilot_analysis::execute((int) $course->id);
            $this->fail('Ohne Daten oder Backend muss es scheitern, aber nicht am Schalter.');
        } catch (\moodle_exception $e) {
            $this->assertNotSame('report_disabled', $e->errorcode);
        }
    }

    /**
     * Ohne Berechtigung keine Deutung.
     */
    public function test_generate_copilot_analysis_requires_capability(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->expectException(\core\exception\required_capability_exception::class);
        generate_copilot_analysis::execute((int) $course->id);
    }














    /**
     * set_ltm stores the calling user's preference and fires the audit event.
     */
    public function test_set_ltm_roundtrip(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $contextid = \core\context\system::instance()->id;

        $sink = $this->redirectEvents();
        $result = set_ltm::execute($contextid, true);
        $result = \core_external\external_api::clean_returnvalue(set_ltm::execute_returns(), $result);
        $this->assertTrue($result['enabled']);
        $this->assertTrue(ltm::is_enabled((int) $user->id));

        $events = array_filter(
            $sink->get_events(),
            static fn($e) => $e instanceof \block_elediaai_tutor\event\ltm_preference_changed
        );
        $this->assertCount(1, $events);

        $result = set_ltm::execute($contextid, false);
        $result = \core_external\external_api::clean_returnvalue(set_ltm::execute_returns(), $result);
        $this->assertFalse($result['enabled']);
        $this->assertFalse(ltm::is_enabled((int) $user->id));
    }

    /**
     * delete_my_data erases only the calling user's conversations and reports
     * external deletion honestly when no delete tool is configured.
     */
    public function test_delete_my_data_scoped_to_caller(): void {
        $this->resetAfterTest();
        $alice = $this->getDataGenerator()->create_user();
        $bob = $this->getDataGenerator()->create_user();
        $this->open_conversation((int) $alice->id, 0, 'one');
        $this->open_conversation((int) $alice->id, 0, 'two');
        $this->open_conversation((int) $bob->id, 0, 'bob');

        $this->setUser($alice);
        $result = delete_my_data::execute(\core\context\system::instance()->id);
        $result = \core_external\external_api::clean_returnvalue(delete_my_data::execute_returns(), $result);

        $this->assertSame(2, $result['localdeleted']);
        $this->assertSame(2, $result['conversationsdeleted']);
        $this->assertSame(0, $result['questionsdeleted']);
        $this->assertSame(0, $result['consentsdeleted']);
        $this->assertSame(0, $result['usagedeleted']);
        $this->assertSame(0, $result['diagnosticsdeleted']);
        $this->assertSame(0, $result['ltmpreferencedeleted']);
        $this->assertFalse($result['externalsupported']);
        $this->assertSame(0, $this->count_conversations((int) $alice->id));
        $this->assertSame(1, $this->count_conversations((int) $bob->id));
    }









    /**
     * Create a Tutor block in one course and return its context.
     *
     * @param int $courseid Course id.
     * @return \core\context\block
     */
    private function create_course_tutor_block(int $courseid): \core\context\block {
        $block = $this->getDataGenerator()->create_block('elediaai_tutor', [
            'parentcontextid' => \core\context\course::instance($courseid)->id,
        ]);
        return \core\context\block::instance($block->id);
    }

    /**
     * Remove a user's manual enrolment from a course.
     *
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @return void
     */
    private function unenrol_user(int $courseid, int $userid): void {
        $manual = enrol_get_plugin('manual');
        $this->assertNotFalse($manual);
        foreach (enrol_get_instances($courseid, true) as $instance) {
            if ($instance->enrol === 'manual') {
                $manual->unenrol_user($instance, $userid);
                return;
            }
        }
        $this->fail('Manual enrolment instance not found.');
    }

    /**
     * Open a tutor conversation in the shared store.
     *
     * The block no longer owns a conversation table; the engine does. Tests
     * that need a conversation to exist create it the same way the placement
     * does at runtime.
     *
     * @param int $userid The owner.
     * @param int $courseid The course scope, 0 for the site-wide chat.
     * @param string $preview A first user message.
     * @return \stdClass The thread.
     */
    private function open_conversation(int $userid, int $courseid = 0, string $preview = 'hi'): \stdClass {
        // Deliberately create() and not open(): each call is a distinct
        // conversation. open() would resume the one already there and the
        // test would count a single thread while believing it made several.
        $thread = thread_store::create(
            'block_elediaai_tutor',
            $courseid,
            (int) \context_system::instance()->id,
            $courseid,
            $userid,
            null,
            'literag',
            mode::GROUNDED,
            'h'
        );
        thread_store::add_message((int) $thread->id, message::ROLE_USER, $preview);
        thread_store::touch((int) $thread->id, $preview);
        // A backend that keeps its own transcript issues an id; without one
        // there would be nothing for a per-conversation deletion to address.
        thread_store::set_convkey((int) $thread->id, 'conv-' . $thread->id);
        $thread->convkey = 'conv-' . $thread->id;

        return $thread;
    }

    /**
     * How many conversations this user holds in the tutor, across all scopes.
     *
     * @param int $userid The owner.
     * @return int The count.
     */
    private function count_conversations(int $userid): int {
        return count(thread_store::threads_for_user('block_elediaai_tutor', $userid));
    }
}

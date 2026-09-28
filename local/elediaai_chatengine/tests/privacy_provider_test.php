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

namespace local_elediaai_chatengine;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_elediaai_chatengine\local\message;
use local_elediaai_chatengine\local\mode;
use local_elediaai_chatengine\local\thread_store;
use local_elediaai_chatengine\local\usage;
use local_elediaai_chatengine\privacy\provider;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Privacy provider tests: conversations and daily message counters.
 *
 * @package     local_elediaai_chatengine
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(provider::class)]
final class privacy_provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Start a conversation with one user turn for a user in a context.
     *
     * @param \stdClass $user The owner.
     * @param \context $context The context the conversation is held in.
     * @param int $courseid The course, or 0.
     * @return \stdClass The thread record.
     */
    private function converse(\stdClass $user, \context $context, int $courseid = 0): \stdClass {
        $thread = thread_store::create(
            'block_elediaai_tutor',
            1,
            (int) $context->id,
            $courseid,
            (int) $user->id,
            null,
            'literag',
            mode::GROUNDED,
            'hash'
        );
        thread_store::add_message((int) $thread->id, message::ROLE_USER, 'What is photosynthesis?');
        return $thread;
    }

    /**
     * Metadata declares the counter table and the previously missing thread fields.
     *
     * @return void
     */
    public function test_get_metadata(): void {
        $collection = provider::get_metadata(new collection('local_elediaai_chatengine'));
        $items = [];
        foreach ($collection->get_collection() as $item) {
            $items[$item->get_name()] = $item;
        }

        $this->assertArrayHasKey(usage::TABLE, $items);
        $this->assertEqualsCanonicalizing(
            ['userid', 'daykey', 'messagecount'],
            array_keys($items[usage::TABLE]->get_privacy_fields())
        );
        $threadfields = $items[thread_store::THREAD_TABLE]->get_privacy_fields();
        foreach (['title', 'guestkey', 'contextid', 'convkey'] as $field) {
            $this->assertArrayHasKey($field, $threadfields);
        }
    }

    /**
     * Counters put the system context on the list, conversations their own context.
     *
     * @return void
     */
    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \core\context\course::instance($course->id);
        $user = $this->getDataGenerator()->create_user();
        $counteronly = $this->getDataGenerator()->create_user();
        $nobody = $this->getDataGenerator()->create_user();

        $this->converse($user, $coursecontext, (int) $course->id);
        usage::increment((int) $user->id);
        usage::increment((int) $counteronly->id);

        $this->assertEqualsCanonicalizing(
            [(int) $coursecontext->id, SYSCONTEXTID],
            array_map('intval', provider::get_contexts_for_userid((int) $user->id)->get_contextids())
        );
        $this->assertEquals(
            [SYSCONTEXTID],
            array_map('intval', provider::get_contexts_for_userid((int) $counteronly->id)->get_contextids())
        );
        $this->assertEmpty(provider::get_contexts_for_userid((int) $nobody->id)->get_contextids());
    }

    /**
     * Users with counters are found in the system context, and only there.
     *
     * @return void
     */
    public function test_get_users_in_context(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \core\context\course::instance($course->id);
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();

        $this->converse($user, $coursecontext, (int) $course->id);
        usage::increment((int) $other->id);

        $system = new userlist(\core\context\system::instance(), 'local_elediaai_chatengine');
        provider::get_users_in_context($system);
        $this->assertEquals([(int) $other->id], array_map('intval', $system->get_userids()));

        $incourse = new userlist($coursecontext, 'local_elediaai_chatengine');
        provider::get_users_in_context($incourse);
        $this->assertEquals([(int) $user->id], array_map('intval', $incourse->get_userids()));
    }

    /**
     * The export carries the counters and the conversation title.
     *
     * @return void
     */
    public function test_export_user_data(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $system = \core\context\system::instance();

        $thread = $this->converse($user, $system);
        $DB->set_field(thread_store::THREAD_TABLE, 'title', 'Biology revision', ['id' => $thread->id]);
        usage::increment((int) $user->id);
        usage::increment((int) $user->id);

        $this->export_context_data_for_user((int) $user->id, $system, 'local_elediaai_chatengine');
        $writer = writer::with_context($system);
        $pluginname = get_string('pluginname', 'local_elediaai_chatengine');

        $conversation = $writer->get_data([$pluginname, 'conversation-' . $thread->id]);
        $this->assertSame('Biology revision', $conversation->title);

        $counters = $writer->get_data([$pluginname, get_string('privacy:path:usage', 'local_elediaai_chatengine')]);
        $this->assertCount(1, $counters->days);
        $this->assertSame((string) usage::daykey(), $counters->days[0]->day);
        $this->assertSame(2, $counters->days[0]->messagecount);
    }

    /**
     * Erasing one user in the system context removes their counters only.
     *
     * @return void
     */
    public function test_delete_data_for_user(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        usage::increment((int) $user->id);
        usage::increment((int) $other->id);

        provider::delete_data_for_user(
            new approved_contextlist($user, 'local_elediaai_chatengine', [SYSCONTEXTID])
        );

        $this->assertFalse($DB->record_exists(usage::TABLE, ['userid' => $user->id]));
        $this->assertTrue($DB->record_exists(usage::TABLE, ['userid' => $other->id]));
    }

    /**
     * Erasing a course context leaves the counters alone; the system context clears them.
     *
     * @return void
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \core\context\course::instance($course->id);
        $user = $this->getDataGenerator()->create_user();
        $this->converse($user, $coursecontext, (int) $course->id);
        usage::increment((int) $user->id);

        provider::delete_data_for_all_users_in_context($coursecontext);
        $this->assertFalse($DB->record_exists(thread_store::THREAD_TABLE, ['userid' => $user->id]));
        $this->assertTrue($DB->record_exists(usage::TABLE, ['userid' => $user->id]));

        provider::delete_data_for_all_users_in_context(\core\context\system::instance());
        $this->assertFalse($DB->record_exists(usage::TABLE, []));
    }

    /**
     * Erasing a user list in the system context removes only the listed counters.
     *
     * @return void
     */
    public function test_delete_data_for_users(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        usage::increment((int) $user->id);
        usage::increment((int) $other->id);

        provider::delete_data_for_users(
            new approved_userlist(\core\context\system::instance(), 'local_elediaai_chatengine', [(int) $user->id])
        );

        $this->assertFalse($DB->record_exists(usage::TABLE, ['userid' => $user->id]));
        $this->assertTrue($DB->record_exists(usage::TABLE, ['userid' => $other->id]));
    }
}

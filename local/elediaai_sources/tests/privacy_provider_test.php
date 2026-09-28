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

namespace local_elediaai_sources;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_elediaai_sources\privacy\provider;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Privacy provider tests.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(provider::class)]
final class privacy_provider_test extends \core_privacy\tests\provider_testcase {
    /** @var string The decision table. */
    private const TABLE = 'local_elediaai_sources_cm';

    /**
     * Metadata declares the decision table and the external AI Sources service.
     */
    public function test_get_metadata(): void {
        $collection = provider::get_metadata(new collection('local_elediaai_sources'));
        $items = [];
        foreach ($collection->get_collection() as $item) {
            $items[$item->get_name()] = $item;
        }

        $this->assertArrayHasKey(self::TABLE, $items);
        $this->assertArrayHasKey('usermodified', $items[self::TABLE]->get_privacy_fields());
        $this->assertArrayHasKey('rag_service', $items);
        $this->assertArrayHasKey('usercontent', $items['rag_service']->get_privacy_fields());
    }

    /**
     * Make a page activity and record a decision on it as the given user.
     *
     * @param \stdClass $course The course.
     * @param \stdClass $user The deciding user.
     * @param bool $included The decision.
     * @return \core\context\module The activity's context.
     */
    private function decide(\stdClass $course, \stdClass $user, bool $included): \core\context\module {
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $this->setUser($user);
        activity_gate::set_included((int) $course->id, (int) $page->cmid, $included);
        $this->setUser(null);
        return \core\context\module::instance($page->cmid);
    }

    /**
     * The deciding user is found in the activity context, and only there.
     */
    public function test_contexts_and_users(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $context = $this->decide($course, $teacher, false);

        $this->assertEquals([$context->id], provider::get_contexts_for_userid((int) $teacher->id)->get_contextids());
        $this->assertEmpty(provider::get_contexts_for_userid((int) $other->id)->get_contextids());

        $userlist = new userlist($context, 'local_elediaai_sources');
        provider::get_users_in_context($userlist);
        $this->assertEquals([(int) $teacher->id], array_map('intval', $userlist->get_userids()));

        $courselist = new userlist(\core\context\course::instance($course->id), 'local_elediaai_sources');
        provider::get_users_in_context($courselist);
        $this->assertEmpty($courselist->get_userids());
    }

    /**
     * The export states which decision the user made.
     */
    public function test_export_user_data(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $context = $this->decide($course, $teacher, false);

        $this->export_context_data_for_user((int) $teacher->id, $context, 'local_elediaai_sources');
        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
        $data = $writer->get_data([get_string('pluginname', 'local_elediaai_sources')]);
        $this->assertEquals(get_string('no'), $data->included);
    }

    /**
     * Erasure removes the person from the decision and keeps the decision.
     */
    public function test_delete_data_for_user_keeps_decision(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $context = $this->decide($course, $teacher, false);
        $othercontext = $this->decide($course, $other, true);

        provider::delete_data_for_user(new approved_contextlist($teacher, 'local_elediaai_sources', [$context->id]));

        $row = $DB->get_record(self::TABLE, ['cmid' => $context->instanceid], '*', MUST_EXIST);
        $this->assertEquals(0, (int) $row->usermodified);
        $this->assertEquals(0, (int) $row->included);
        $this->assertTrue(activity_gate::is_explicitly_excluded((int) $context->instanceid));
        $this->assertEquals(
            (int) $other->id,
            (int) $DB->get_field(self::TABLE, 'usermodified', ['cmid' => $othercontext->instanceid])
        );
    }

    /**
     * Context-wide erasure anonymises the decision of that activity only.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $context = $this->decide($course, $teacher, true);
        $othercontext = $this->decide($course, $teacher, true);

        provider::delete_data_for_all_users_in_context($context);

        $this->assertEquals(0, (int) $DB->get_field(self::TABLE, 'usermodified', ['cmid' => $context->instanceid]));
        $this->assertEquals(
            (int) $teacher->id,
            (int) $DB->get_field(self::TABLE, 'usermodified', ['cmid' => $othercontext->instanceid])
        );
        $this->assertEquals(2, $DB->count_records(self::TABLE));
    }

    /**
     * Erasure for a user list anonymises only the listed users.
     */
    public function test_delete_data_for_users(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $context = $this->decide($course, $teacher, true);

        provider::delete_data_for_users(new approved_userlist($context, 'local_elediaai_sources', [(int) $teacher->id]));

        $this->assertEquals(0, (int) $DB->get_field(self::TABLE, 'usermodified', ['cmid' => $context->instanceid]));
        $this->assertEquals(1, $DB->count_records(self::TABLE));
    }
}

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

namespace local_elediaai_core;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\writer;
use local_elediaai_core\local\action_recorder;
use local_elediaai_core\local\turn_recorder;
use local_elediaai_core\privacy\provider;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Privacy provider tests: the turn log and the action log.
 *
 * @package     local_elediaai_core
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(provider::class)]
final class privacy_provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Every field the export carries is declared in the metadata.
     *
     * @return void
     */
    public function test_get_metadata_declares_place_fields(): void {
        $collection = provider::get_metadata(new collection('local_elediaai_core'));
        $fields = [];
        foreach ($collection->get_collection() as $item) {
            if ($item instanceof \core_privacy\local\metadata\types\database_table) {
                $fields[$item->get_name()] = array_keys($item->get_privacy_fields());
            }
        }

        foreach (['contextid', 'cmid', 'sourcetitle'] as $field) {
            $this->assertContains($field, $fields[turn_recorder::TABLE]);
        }
        foreach (['contextid', 'turnid'] as $field) {
            $this->assertContains($field, $fields[action_recorder::TABLE]);
        }
    }

    /**
     * The export carries context, cited source and module of a turn.
     *
     * @return void
     */
    public function test_export_turn_place_fields(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $coursecontext = \core\context\course::instance($course->id);
        $user = $this->getDataGenerator()->create_user();

        $turnid = turn_recorder::record(
            'block_elediaai_tutor',
            'chat_turn',
            (int) $coursecontext->id,
            (int) $user->id,
            'What is photosynthesis?',
            'Plants turn light into sugar.',
            sourcetitle: 'Chapter 3: Plants',
            cmid: (int) $page->cmid
        );
        $this->assertNotNull($turnid);

        $system = \core\context\system::instance();
        $this->export_context_data_for_user((int) $user->id, $system, 'local_elediaai_core');
        $data = writer::with_context($system)->get_data([get_string('privacy:path:turns', 'local_elediaai_core')]);

        $this->assertCount(1, $data->turns);
        $turn = $data->turns[0];
        $this->assertSame((int) $coursecontext->id, $turn->contextid);
        $this->assertSame('Chapter 3: Plants', $turn->sourcetitle);
        $this->assertSame((int) $page->cmid, $turn->cmid);
    }

    /**
     * The export carries context and turn of an action, and every action.
     *
     * @return void
     */
    public function test_export_action_place_fields(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \core\context\course::instance($course->id);
        $user = $this->getDataGenerator()->create_user();

        action_recorder::record(
            'webservice_elediamcp',
            'moodle_grade_submission',
            (int) $user->id,
            iswrite: true,
            contextid: (int) $coursecontext->id,
            courseid: (int) $course->id,
            turnid: 77
        );
        action_recorder::record('webservice_elediamcp', 'moodle_get_course', (int) $user->id);

        $system = \core\context\system::instance();
        $this->export_context_data_for_user((int) $user->id, $system, 'local_elediaai_core');
        $data = writer::with_context($system)->get_data([get_string('privacy:path:actions', 'local_elediaai_core')]);

        $this->assertCount(2, $data->actions);
        $byname = [];
        foreach ($data->actions as $action) {
            $byname[$action->toolname] = $action;
        }
        $this->assertSame((int) $coursecontext->id, $byname['moodle_grade_submission']->contextid);
        $this->assertSame(77, $byname['moodle_grade_submission']->turnid);
        $this->assertSame(0, $byname['moodle_get_course']->contextid);
        $this->assertNull($byname['moodle_get_course']->turnid);
    }
}

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

namespace local_elediaai_sources;

use local_elediaai_sources\task\ingest_module_task;

/**
 * Tests for the per-module ingest task (G-09).
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_sources\task\ingest_module_task
 */
final class ingest_module_task_test extends \advanced_testcase {
    /**
     * Run a task built for the given ids and return its output.
     *
     * @param int $courseid Course id.
     * @param int $cmid Course module id.
     * @return string
     */
    private function run_task(int $courseid, int $cmid): string {
        $task = new ingest_module_task();
        $task->set_custom_data(['courseid' => $courseid, 'cmid' => $cmid]);
        ob_start();
        $task->execute();
        return (string) ob_get_clean();
    }

    /**
     * A task for a course deleted before cron is discarded quietly.
     */
    public function test_deleted_course_is_discarded_without_error(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('sink', 'ingestionapi', 'local_elediaai_sources');
        set_config('sink_ingestionapi_baseurl', 'https://rag.invalid', 'local_elediaai_sources');
        set_config('sink_ingestionapi_apikey', 'test', 'local_elediaai_sources');

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        delete_course($course, false);

        $output = $this->run_task((int) $course->id, (int) $page->cmid);

        $this->assertStringContainsString('no longer exists, task discarded', $output);
        $this->assertStringNotContainsString("Can't find data record", $output);
        $this->assertStringNotContainsString('Ingesting cmid', $output);
        $this->assertFalse($DB->record_exists('local_elediaai_sources_cmstate', ['cmid' => $page->cmid]));
    }

    /**
     * A task for a module deleted from a course that still exists is discarded too.
     */
    public function test_deleted_module_is_discarded_without_error(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        // Only the row matters to the task; the deletion API differs across
        // supported Moodle versions (course_delete_module is deprecated in 5.2).
        $DB->delete_records('course_modules', ['id' => $page->cmid]);

        $output = $this->run_task((int) $course->id, (int) $page->cmid);

        $this->assertStringContainsString('no longer exists, task discarded', $output);
        $this->assertStringNotContainsString('Ingesting cmid', $output);
    }
}

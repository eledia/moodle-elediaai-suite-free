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

namespace webservice_elediamcp;

use advanced_testcase;
use webservice_elediamcp\local\ai\tools\moodle_calendar_upcoming;

/**
 * Links in moodle_calendar_upcoming.
 *
 * @package     webservice_elediamcp
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class calendar_upcoming_test extends advanced_testcase {
    /**
     * An activity link carries the course module id, not the instance id (#173).
     *
     * Pages first, so the assign instance id and the course module id drift
     * apart as they do on any real site.
     */
    public function test_activity_link_uses_the_course_module_id(): void {
        $this->resetAfterTest(true);
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        for ($i = 0; $i < 3; $i++) {
            $generator->create_module('page', ['course' => $course->id]);
        }
        $assign = $generator->create_module('assign', [
            'course' => $course->id,
            'name' => 'Uebungsblatt 1',
            'duedate' => time() + 3 * DAYSECS,
        ]);
        $this->assertNotEquals((int) $assign->id, (int) $assign->cmid, 'test needs differing ids');
        $student = $generator->create_and_enrol($course, 'student');
        $this->setUser($student);

        $result = moodle_calendar_upcoming::execute(['days_ahead' => 7], $student);

        $urls = array_column(array_filter(
            $result['events'],
            fn(array $event) => str_contains($event['name'], 'Uebungsblatt 1')
        ), 'url');
        $this->assertNotEmpty($urls, 'the due date shows up');
        foreach ($urls as $url) {
            $this->assertStringContainsString('/mod/assign/view.php?id=' . $assign->cmid, $url);
        }
    }
}

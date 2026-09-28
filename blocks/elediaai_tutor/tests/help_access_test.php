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

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Who may read the tutor help (G-05).
 *
 * Die Hilfe verlangte moodle/site:config; die Lehrkraft, die den Tutor im Kurs
 * einrichtet, konnte sie nicht lesen. help.php prueft im Kurs das Recht, mit
 * dem sie ihn dort einrichtet.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class help_access_test extends \advanced_testcase {
    /**
     * Editing teachers hold the capability the course help asks for; learners do not.
     */
    public function test_the_course_help_follows_the_setup_right(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $context = \core\context\course::instance($course->id);

        $this->assertTrue(has_capability('block/elediaai_tutor:manage', $context, $teacher));
        $this->assertFalse(has_capability('block/elediaai_tutor:manage', $context, $student));
        $this->assertFalse(has_capability('moodle/site:config', \core\context\system::instance(), $teacher));
    }
}

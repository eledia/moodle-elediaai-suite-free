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

/**
 * The block without a usable backend.
 *
 * Mit schwebendem Startknopf verschwand der Tutor kommentarlos: der Hinweis
 * stand im Block, und der Block sitzt in der meist geschlossenen Blockleiste.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\block_elediaai_tutor::class)]
final class block_unavailable_test extends \advanced_testcase {
    /**
     * Learners see a notice where the launcher would be.
     */
    public function test_a_missing_backend_is_explained_where_the_launcher_sits(): void {
        global $CFG;
        require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
        require_once($CFG->dirroot . '/blocks/elediaai_tutor/block_elediaai_tutor.php');
        $this->resetAfterTest();

        if (\local_elediaai_chatengine\backend_resolver::is_available()) {
            $this->markTestSkipped('A backend is available in this environment.');
        }

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);
        $context = \context_course::instance($course->id);
        $page = new \moodle_page();
        $page->set_course($course);
        $page->set_url('/course/view.php', ['id' => $course->id]);

        $record = $this->getDataGenerator()->create_block('elediaai_tutor', [
            'parentcontextid' => $context->id,
            'pagetypepattern' => 'course-view-*',
        ]);
        $block = block_instance('elediaai_tutor', $record, $page);
        $html = $block->get_content()->text;

        $this->assertStringContainsString(get_string('unavailable_user', 'block_elediaai_tutor'), $html);
        if ((\block_elediaai_tutor\local\branding::resolve([])['launcherstyle'] ?? '') === 'fab') {
            $this->assertStringContainsString('elediaai-chat-unavailable--floating', $html);
        }
    }
}

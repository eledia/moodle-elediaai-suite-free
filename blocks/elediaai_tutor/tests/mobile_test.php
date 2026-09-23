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
use block_elediaai_tutor\output\mobile;

/**
 * Unit tests for the Moodle App content callbacks.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\block_elediaai_tutor\output\mobile::class)]
final class mobile_test extends \advanced_testcase {
    /**
     * The course handler frames the page only for courses that opted in by
     * holding a tutor block; others get a notice.
     */
    public function test_mobile_course_view(): void {
        $this->resetAfterTest();
        $optedin = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_block('elediaai_tutor', [
            'parentcontextid' => \core\context\course::instance($optedin->id)->id,
        ]);
        $other = $this->getDataGenerator()->create_course();

        $result = mobile::mobile_course_view(['courseid' => (int) $optedin->id]);
        $this->assertSame('main', $result['templates'][0]['id']);
        $html = $result['templates'][0]['html'];
        $this->assertStringContainsString('<core-iframe', $html);
        $this->assertStringContainsString('/blocks/elediaai_tutor/view.php', $html);
        $this->assertStringContainsString('embedded=1', $html);
        $this->assertStringContainsString('courseid=' . $optedin->id, $html);

        // No tutor block in the course: a notice instead of the chat.
        $result = mobile::mobile_course_view(['courseid' => (int) $other->id]);
        $html = $result['templates'][0]['html'];
        $this->assertStringNotContainsString('<core-iframe', $html);
        $this->assertStringContainsString(
            get_string('notenabledincourse', 'block_elediaai_tutor'),
            $html
        );
    }

    /**
     * The main-menu handler frames the global page (no courseid).
     */
    public function test_mobile_global_view(): void {
        $this->resetAfterTest();

        $result = mobile::mobile_global_view([]);

        $html = $result['templates'][0]['html'];
        $this->assertStringContainsString('<core-iframe', $html);
        $this->assertStringContainsString('embedded=1', $html);
        $this->assertStringNotContainsString('courseid', $html);
    }
}

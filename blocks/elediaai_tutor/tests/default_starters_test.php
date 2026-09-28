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

use block_elediaai_tutor\local\user_audience;
use block_elediaai_tutor\local\widget;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The built-in dashboard starters match the tools the site has.
 *
 * Im freien Paket bot das Dashboard "Kurs entwerfen", "Offene Abgaben" und
 * "Offene Forenfragen" an - Werkzeuge, die es dort nicht gibt.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(widget::class)]
final class default_starters_test extends \advanced_testcase {
    /**
     * Without the premium MCP tools teachers and managers get the free lists.
     */
    public function test_free_lists_without_premium_tools(): void {
        $premium = '\\webservice_elediamcp\\local\\premium';
        if (class_exists($premium) && $premium::has_mcp_tools()) {
            $this->markTestSkipped('The premium MCP tools are installed here.');
        }

        $this->assertSame('default_promptstarters_teacher_free', widget::default_starters_key(user_audience::TEACHER));
        $this->assertSame('default_promptstarters_manager_free', widget::default_starters_key(user_audience::MANAGER));
        $this->assertSame('default_promptstarters_student', widget::default_starters_key(user_audience::STUDENT));

        $free = get_string('default_promptstarters_teacher_free', 'block_elediaai_tutor')
            . get_string('default_promptstarters_manager_free', 'block_elediaai_tutor');
        $this->assertStringNotContainsString('course author', $free);
        $this->assertStringNotContainsString('forum', $free);
        $this->assertStringNotContainsString('Create a course', $free);
    }
}

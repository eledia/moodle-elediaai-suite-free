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

/**
 * Tests for the shared course-module text extractor.
 *
 * @package    local_elediaai_core
 * @category   test
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core;

use advanced_testcase;
use local_elediaai_core\content\content_extractor;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests the component behavior and contracts.
 *
 * @covers \local_elediaai_core\content\content_extractor
 */
final class content_extractor_test extends advanced_testcase {
    public function test_extract_page_strips_html(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'intro' => 'Intro line.',
            'content' => '<p>Hello readable body text.</p>',
        ]);

        $text = content_extractor::extract((int) $page->cmid);

        $this->assertStringContainsString('Hello readable body text.', $text);
        $this->assertStringContainsString('Intro line.', $text);
        // HTML markup is stripped to plain text.
        $this->assertStringNotContainsString('<p>', $text);
    }

    public function test_list_supported_includes_page_excludes_forum(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);

        $list = content_extractor::list_supported((int) $course->id);

        $this->assertArrayHasKey((int) $page->cmid, $list);
    }

    public function test_unsupported_module_throws(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);

        $this->expectException(moodle_exception::class);
        content_extractor::extract((int) $forum->cmid);
    }
}

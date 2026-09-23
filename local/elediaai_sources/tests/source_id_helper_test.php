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

/**
 * Unit tests for the source_id_helper class.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_sources\source_id_helper
 */
final class source_id_helper_test extends \advanced_testcase {
    /**
     * Test that the tenant prefix is derived from wwwroot, not configured.
     */
    public function test_build_from_ids_derives_tenant_from_wwwroot(): void {
        $this->resetAfterTest();

        $expectedtenant = tenant::id();
        $this->assertNotSame('', $expectedtenant);

        $sourceid = source_id_helper::build_from_ids(42, 99);
        $this->assertEquals("{$expectedtenant}:course42:cmid99", $sourceid);
        // The tenant component never contains the ':' separator.
        $this->assertStringNotContainsString(':', $expectedtenant);
    }

    /**
     * Test that source IDs are deterministic — calling twice yields the same result.
     */
    public function test_build_from_ids_is_deterministic(): void {
        $this->resetAfterTest();

        $first = source_id_helper::build_from_ids(10, 20);
        $second = source_id_helper::build_from_ids(10, 20);
        $this->assertSame($first, $second);
    }

    /**
     * Test building source IDs from a cm_info object.
     */
    public function test_build_from_cm_info(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Test</p>',
        ]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($page->cmid);

        $sourceid = source_id_helper::build($cm);

        $expected = tenant::id() . ":course{$course->id}:cmid{$page->cmid}";
        $this->assertEquals($expected, $sourceid);
    }

    /**
     * Test that sub-document IDs append a sanitised, separator-free suffix to
     * the module-level id (so the module id stays a clean prefix).
     */
    public function test_build_sub(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module(
            'page',
            ['course' => $course->id, 'content' => '<p>x</p>']
        );
        $cm = get_fast_modinfo($course->id)->get_cm($page->cmid);

        $base = source_id_helper::build($cm);
        $sub = source_id_helper::build_sub($cm, 'file3');

        $this->assertSame($base . ':file3', $sub);
        // The module id is a clean, ':'-bounded prefix of the sub id.
        $this->assertStringStartsWith($base . ':', $sub);
    }

    /**
     * Test suffix sanitisation removes the ':' separator and other unsafe
     * characters so the prefix boundary can never be broken.
     */
    public function test_sanitise_suffix(): void {
        $this->assertSame('file-3', source_id_helper::sanitise_suffix('file:3'));
        $this->assertSame('a-b-c', source_id_helper::sanitise_suffix('a/b c'));
        $this->assertSame('lecture-pdf', source_id_helper::sanitise_suffix('Lecture.pdf'));
        // Empty / all-unsafe input falls back to a safe token.
        $this->assertSame('x', source_id_helper::sanitise_suffix(':::'));
        $this->assertSame('x', source_id_helper::sanitise_suffix(''));
    }
}

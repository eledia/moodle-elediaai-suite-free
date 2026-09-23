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

use local_elediaai_sources\output\activities_page;

/**
 * Unit tests for the activity selection page.
 *
 * The page's behaviour in a browser cannot be unit-tested, but two things
 * can: that the exported data says what the page claims, and that the
 * template renders at all — which catches Mustache errors and missing
 * language strings before they reach a teacher.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_sources\output\activities_page
 */
final class activities_page_test extends \advanced_testcase {
    /**
     * Export the page data for a course.
     *
     * @param \stdClass $course The course.
     * @return array The template context.
     */
    private function export(\stdClass $course): array {
        global $PAGE;
        return (new activities_page($course))->export_for_template($PAGE->get_renderer('core'));
    }

    /**
     * Find one activity row in the exported sections.
     *
     * @param array $data The exported context.
     * @param int $cmid The course module id.
     * @return array|null
     */
    private function row(array $data, int $cmid): ?array {
        foreach ($data['sections'] as $section) {
            foreach ($section['activities'] as $activity) {
                if ($activity['cmid'] === $cmid) {
                    return $activity;
                }
            }
        }
        return null;
    }

    /**
     * Without a state row the status is "unknown" — the page does not claim
     * an activity is indexed when the plugin has no record of it.
     */
    public function test_status_is_unknown_without_state(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Fresh</p>',
        ]);

        $row = $this->row($this->export($course), (int) $page->cmid);

        $this->assertNotNull($row);
        $this->assertSame('unknown', $row['status']);
        $this->assertTrue($row['supported']);
        $this->assertFalse($row['explicit'], 'Nobody decided yet.');
    }

    /**
     * A successful ingest shows as indexed, a failure as error.
     */
    public function test_status_reflects_state(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $good = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Good</p>',
        ]);
        $bad = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Bad</p>',
        ]);

        cm_state::record_success((int) $course->id, (int) $good->cmid, 'sid-a', 'h', 'recording');
        cm_state::record_error((int) $course->id, (int) $bad->cmid, 'sid-b', 'boom', 'recording');

        $data = $this->export($course);

        $this->assertSame('indexed', $this->row($data, (int) $good->cmid)['status']);
        $errorrow = $this->row($data, (int) $bad->cmid);
        $this->assertSame('error', $errorrow['status']);
        $this->assertSame('boom', $errorrow['statustitle'], 'The failure reason belongs on the page.');
    }

    /**
     * An explicit decision is flagged, so the reset affordance can appear.
     */
    public function test_explicit_decision_is_flagged(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Decided</p>',
        ]);
        activity_gate::set_included((int) $course->id, (int) $page->cmid, false);

        $row = $this->row($this->export($course), (int) $page->cmid);

        $this->assertTrue($row['explicit']);
        $this->assertTrue($row['excluded']);
        $this->assertFalse($row['included']);
    }

    /**
     * Bulk actions only ever address activities an extractor can read.
     */
    public function test_bulk_list_excludes_unsupported_activities(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Readable</p>',
        ]);
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);

        $data = $this->export($course);

        $bulk = [];
        foreach ($data['sections'] as $section) {
            if ($section['bulkcmids'] !== '') {
                $bulk = array_merge($bulk, array_map('intval', explode(',', $section['bulkcmids'])));
            }
        }

        $this->assertContains((int) $page->cmid, $bulk);
        $this->assertNotContains((int) $forum->cmid, $bulk);
        $this->assertFalse($this->row($data, (int) $forum->cmid)['supported']);
    }

    /**
     * Names containing an ampersand are escaped exactly once.
     *
     * get_section_name() and cm_info::get_formatted_name() already return
     * HTML-escaped output, so rendering them through {{name}} escaped a second
     * time and the page showed a literal "&amp;". The formatted name therefore
     * goes into {{{name}}}, while aria labels use the unescaped plainname and
     * let Mustache escape once.
     */
    public function test_names_with_ampersand_are_escaped_once(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Recht & Praxis',
            'content' => '<p>x</p>',
        ]);
        $DB->set_field(
            'course_sections',
            'name',
            'Kursmaterialien & Inhalte',
            ['course' => $course->id, 'section' => 0]
        );
        rebuild_course_cache((int) $course->id, true);
        $course = get_course((int) $course->id);

        $data = (new activities_page($course))->export_for_template($PAGE->get_renderer('core'));
        $section = $data['sections'][0];
        $this->assertSame('Kursmaterialien &amp; Inhalte', $section['name']);
        $this->assertSame('Kursmaterialien & Inhalte', $section['plainname']);

        $output = $PAGE->get_renderer('core')->render_from_template(
            'local_elediaai_sources/activities_page',
            $data
        );

        // Once escaped, never twice: "&amp;amp;" is the bug this guards.
        $this->assertStringContainsString('Kursmaterialien &amp; Inhalte', $output);
        $this->assertStringContainsString('Recht &amp; Praxis', $output);
        $this->assertStringNotContainsString('&amp;amp;', $output);
    }

    /**
     * The template renders — catching Mustache errors and missing strings.
     */
    public function test_template_renders(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Rendered</p>',
        ]);
        cm_state::record_success((int) $course->id, (int) $page->cmid, 'sid', 'h', 'recording');
        activity_gate::set_included((int) $course->id, (int) $page->cmid, false);

        $output = $PAGE->get_renderer('core')->render_from_template(
            'local_elediaai_sources/activities_page',
            $this->export($course)
        );

        $this->assertStringContainsString('data-region="elediaai-activities"', $output);
        $this->assertStringContainsString('data-region="toggle"', $output);
        $this->assertStringContainsString('data-region="bulk"', $output);
        $this->assertStringContainsString('data-region="reset"', $output);
        $this->assertStringContainsString('data-region="filter"', $output);
        // Unresolved language strings would render as [[key]].
        $this->assertStringNotContainsString('[[', $output);
    }
}

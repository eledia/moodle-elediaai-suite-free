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

use local_elediaai_sources\output\preview_page;

/**
 * Unit tests for the dry-run page.
 *
 * The browser behaviour cannot be unit-tested, but two things can: that the
 * page renders at all (catching Mustache errors and missing strings), and
 * that it never carries binary content into the template.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_sources\output\preview_page
 */
final class preview_page_test extends \advanced_testcase {
    /** @var \stdClass The course under test. */
    private \stdClass $course;

    /**
     * Released course with the ingestion API configured.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course();
        set_config('enabledcategories', (string) $this->course->category, 'local_elediaai_sources');
        set_config('sink', 'ingestionapi', 'local_elediaai_sources');
        set_config('sink_ingestionapi_baseurl', 'http://localhost:8001', 'local_elediaai_sources');
        set_config('sink_ingestionapi_apikey', 'test-key', 'local_elediaai_sources');
    }

    /**
     * Export the page data for one activity.
     *
     * @param \cm_info $cm The activity.
     * @return array The template context.
     */
    private function export(\cm_info $cm): array {
        global $PAGE;
        return (new preview_page($cm))->export_for_template($PAGE->get_renderer('core'));
    }

    /**
     * Render the page for one activity.
     *
     * @param \cm_info $cm The activity.
     * @return string The rendered HTML.
     */
    private function render(\cm_info $cm): string {
        global $PAGE;
        return $PAGE->get_renderer('core')->render_from_template(
            'local_elediaai_sources/preview_page',
            $this->export($cm)
        );
    }

    /**
     * Create a page in the course under test.
     *
     * @param string $content The page content.
     * @param array $extra Extra generator options.
     * @return \cm_info
     */
    private function page(string $content = '<p>Hello</p>', array $extra = []): \cm_info {
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $this->course->id,
            'content' => $content,
        ] + $extra);
        return get_fast_modinfo($this->course->id)->get_cm((int) $page->cmid);
    }

    /**
     * The page renders for an activity that would be sent.
     */
    public function test_renders_for_an_ingestible_activity(): void {
        $output = $this->render($this->page('<p>Findable sentence</p>'));

        $this->assertStringContainsString('les-preview', $output);
        $this->assertStringContainsString('Findable sentence', $output);
        // Unresolved language strings would render as [[key]].
        $this->assertStringNotContainsString('[[', $output);
    }

    /**
     * The content is shown as source, not rendered — the page must show what
     * travels, not how it would look.
     */
    public function test_content_is_shown_escaped(): void {
        $output = $this->render($this->page('<p>Escaped please</p>'));

        $this->assertStringContainsString('&lt;p&gt;Escaped please&lt;/p&gt;', $output);
        $this->assertStringNotContainsString('<p>Escaped please</p>', $output);
    }

    /**
     * Each verdict renders, including the ones a teacher opens the page for.
     */
    public function test_renders_for_every_verdict(): void {
        // Skipped: no extractor.
        $forummod = $this->getDataGenerator()->create_module('forum', ['course' => $this->course->id]);
        $forum = get_fast_modinfo($this->course->id)->get_cm((int) $forummod->cmid);
        $output = $this->render($forum);
        $this->assertStringNotContainsString('[[', $output);

        // Would be removed: explicitly excluded.
        $cm = $this->page();
        activity_gate::set_included((int) $this->course->id, (int) $cm->id, false);
        $export = $this->export($cm);
        $this->assertSame(ingestion_manager::VERDICT_DELETE, $export['verdict']);
        $this->assertStringNotContainsString('[[', $this->render($cm));
    }

    /**
     * Binary content never reaches the template context.
     */
    public function test_binary_content_never_reaches_the_template(): void {
        $resource = $this->getDataGenerator()->create_module('resource', ['course' => $this->course->id]);
        $context = \core\context\module::instance((int) $resource->cmid);
        get_file_storage()->delete_area_files($context->id, 'mod_resource', 'content');
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id, 'component' => 'mod_resource', 'filearea' => 'content',
            'itemid' => 0, 'filepath' => '/', 'filename' => 'handbook.pdf', 'mimetype' => 'application/pdf',
            'sortorder' => 1,
        ], '%PDF-1.4 SECRETBYTES');

        $cm = get_fast_modinfo($this->course->id)->get_cm((int) $resource->cmid);
        $export = $this->export($cm);

        foreach ($export['documents'] as $document) {
            if ($document['isbinary']) {
                $this->assertNull($document['displaycontent']);
            }
        }
        $this->assertStringNotContainsString('SECRETBYTES', $this->render($cm));
    }

    /**
     * The page must carry the module, not merely its context.
     *
     * With only the context set, the page keeps the site as its course while
     * claiming a module context, and building the navigation for that
     * combination dereferences the module the page never got. This bit the
     * administrator path only: a teacher arrives through
     * require_login($course, false, $cm), which sets both.
     */
    public function test_the_page_setup_survives_building_the_navigation(): void {
        global $PAGE;
        $cm = $this->page();

        $PAGE->set_cm($cm, $this->course);
        $PAGE->set_url(new \moodle_url('/local/elediaai_sources/preview.php', ['cmid' => $cm->id]));

        $this->assertNotNull($PAGE->settingsnav);
    }

    /**
     * The entry point announces the module, which is what the test above needs.
     *
     * Asserted against the file because the failure lives in page setup, which
     * no unit test can execute: reproducing the setup here would keep passing
     * while the real page regressed.
     */
    public function test_the_entry_point_sets_the_module_on_the_page(): void {
        $source = (string) file_get_contents(__DIR__ . '/../preview.php');

        $this->assertStringContainsString('$PAGE->set_cm(', $source);
    }

    /**
     * The file list is headed by the reason those files are on it.
     *
     * Audio left to a transcript is not the same as captions that could not be
     * paired, and a heading saying "could not be matched" above files that were
     * never matched to anything contradicts the sentence above it.
     */
    public function test_the_file_list_names_its_own_reason(): void {
        global $DB;
        $scorm = $this->getDataGenerator()->create_module('scorm', ['course' => $this->course->id]);
        $DB->set_field('scorm', 'scormtype', 'local', ['id' => $scorm->id]);

        $context = \core\context\module::instance((int) $scorm->cmid);
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_scorm', 'content');
        foreach (['/media/' => 'kapitel1.mp3', '/' => 'Transkript.html'] as $path => $name) {
            $fs->create_file_from_string([
                'contextid' => $context->id, 'component' => 'mod_scorm', 'filearea' => 'content',
                'itemid' => 0, 'filepath' => $path, 'filename' => $name,
            ], 'x');
        }

        $cm = get_fast_modinfo($this->course->id)->get_cm((int) $scorm->cmid);
        $export = $this->export($cm);

        $this->assertTrue($export['hasaccessibility']);
        $this->assertSame(
            get_string('a11y_files_transcript', 'local_elediaai_sources'),
            $export['a11yfileslabel']
        );
        $this->assertNotSame(
            get_string('a11y_files_undetermined', 'local_elediaai_sources'),
            $export['a11yfileslabel']
        );
    }

    /**
     * The index comparison is worded, not left blank, in every state.
     */
    public function test_comparison_is_always_worded(): void {
        $cm = $this->page();

        $this->assertNotSame('', $this->export($cm)['comparisontext']);

        cm_state::record_success((int) $this->course->id, (int) $cm->id, 'sid', 'stale-hash', 'ingestionapi');
        $older = $this->export($cm)['comparisontext'];
        $this->assertNotSame('', $older);

        cm_state::record_error((int) $this->course->id, (int) $cm->id, 'sid', 'boom', 'ingestionapi');
        $this->assertStringContainsString('boom', $this->export($cm)['comparisontext']);

        cm_state::forget((int) $cm->id);
        cm_state::record_success((int) $this->course->id, (int) $cm->id, 'sid', 'h', 'literag');
        $this->assertNotSame('', $this->export($cm)['comparisontext']);
    }
}

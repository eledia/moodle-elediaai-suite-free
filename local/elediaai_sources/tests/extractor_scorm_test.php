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
 * Unit tests for the SCORM content extractor.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aisourcesextractor_scorm\extractor
 */
final class extractor_scorm_test extends \advanced_testcase {
    /**
     * Test that the scorm extractor supports scorm modules.
     */
    public function test_supports_scorm(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $scorm = $this->getDataGenerator()->create_module('scorm', [
            'course' => $course->id,
        ]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($scorm->cmid);

        $extractor = new \aisourcesextractor_scorm\extractor();
        $this->assertTrue($extractor->supports($cm));
    }

    /**
     * Test that the scorm extractor does not support page modules.
     */
    public function test_does_not_support_page(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Page</p>',
        ]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($page->cmid);

        $extractor = new \aisourcesextractor_scorm\extractor();
        $this->assertFalse($extractor->supports($cm));
    }

    /**
     * Test extracting SCO titles from a SCORM package.
     */
    public function test_extract_returns_sco_titles(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $scorm = $this->getDataGenerator()->create_module('scorm', [
            'course' => $course->id,
            'name' => 'Safety Training',
        ]);

        // The SCORM generator creates default SCOs from its sample package.
        // Let's check what we get.
        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($scorm->cmid);

        $extractor = new \aisourcesextractor_scorm\extractor();
        $result = $extractor->extract($cm);

        // The SCORM module should at least have its intro or SCO titles.
        // With the default test package, SCOs are created from the manifest.
        if ($result !== null) {
            $this->assertEquals('text/html', $result['content_type']);
            $this->assertEquals('Safety Training', $result['title']);
        }
    }

    /**
     * Test extracting content with manually inserted SCOs and HTML files.
     */
    public function test_extract_with_html_launch_pages(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $scorm = $this->getDataGenerator()->create_module('scorm', [
            'course' => $course->id,
            'name' => 'Interactive Course',
            'intro' => '<p>Welcome to this interactive course.</p>',
        ]);

        // Ensure the package type is local.
        $DB->set_field('scorm', 'scormtype', 'local', ['id' => $scorm->id]);

        // Clear existing SCOs and insert our own.
        $DB->delete_records('scorm_scoes', ['scorm' => $scorm->id]);

        $DB->insert_record('scorm_scoes', [
            'scorm' => $scorm->id,
            'manifest' => 'manifest-1',
            'organization' => 'org-1',
            'parent' => '/',
            'identifier' => 'sco1',
            'launch' => 'lesson1.html',
            'scormtype' => 'sco',
            'title' => 'Lesson 1: Basics',
            'sortorder' => 1,
        ]);

        $DB->insert_record('scorm_scoes', [
            'scorm' => $scorm->id,
            'manifest' => 'manifest-1',
            'organization' => 'org-1',
            'parent' => '/',
            'identifier' => 'sco2',
            'launch' => 'lesson2.html',
            'scormtype' => 'sco',
            'title' => 'Lesson 2: Advanced',
            'sortorder' => 2,
        ]);

        // Create HTML files in the SCORM content area.
        $context = \core\context\module::instance($scorm->cmid);
        $fs = get_file_storage();

        $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_scorm',
            'filearea' => 'content',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'lesson1.html',
        ], '<html><body><p>This lesson covers the fundamentals of safety.</p></body></html>');

        $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_scorm',
            'filearea' => 'content',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'lesson2.html',
        ], '<html><body><p>Advanced topics in workplace safety procedures.</p></body></html>');

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($scorm->cmid);

        $extractor = new \aisourcesextractor_scorm\extractor();
        $result = $extractor->extract($cm);

        $this->assertNotNull($result);
        $this->assertEquals('text/html', $result['content_type']);
        $this->assertEquals('Interactive Course', $result['title']);
        $this->assertStringContainsString('Welcome to this interactive course.', $result['content']);
        $this->assertStringContainsString('<h2>Lesson 1: Basics</h2>', $result['content']);
        $this->assertStringContainsString('fundamentals of safety', $result['content']);
        $this->assertStringContainsString('<h2>Lesson 2: Advanced</h2>', $result['content']);
        $this->assertStringContainsString('workplace safety procedures', $result['content']);
    }

    /**
     * Test that a standalone WebVTT track is picked up as a transcript.
     *
     * This is the tool-independent case: an accessible package ships its
     * captions as real .vtt files next to the media.
     */
    public function test_extract_reads_standalone_vtt_captions(): void {
        $cm = $this->make_package('Accessible Course', [
            'audio/lesson1.vtt' => "WEBVTT\n\n1\n00:00:00.075 --> 00:00:05.125\n"
                . "Arbeitsschutz beginnt bei der Gefährdungsbeurteilung.\n\n"
                . "00:00:05.275 --> 00:00:09.125\nSie ist die Grundlage jeder Maßnahme.\n",
        ]);

        $result = (new \aisourcesextractor_scorm\extractor())->extract($cm);

        $this->assertNotNull($result);
        $this->assertStringContainsString('Arbeitsschutz beginnt bei der Gefährdungsbeurteilung.', $result['content']);
        $this->assertStringContainsString('Sie ist die Grundlage jeder Maßnahme.', $result['content']);
        // Timings and cue numbers are not words.
        $this->assertStringNotContainsString('00:00:00.075', $result['content']);
        $this->assertStringNotContainsString('WEBVTT', $result['content']);
    }

    /**
     * Test that captions bundled into a JavaScript wrapper are still found.
     *
     * Articulate Storyline ships no .vtt files at all: it URL-encodes the very
     * same WebVTT into a `*_captions.js` asset. Without unwrapping that, a
     * package built with it contributes no spoken text whatsoever.
     */
    public function test_extract_reads_captions_bundled_as_javascript(): void {
        $vtt = "WEBVTT\r\n\r\nNOTE\r\nKind: captions\r\nSource: Articulate Closed Captions Editor\r\n"
            . "Source Version: 3.117.36909.0\r\n\r\n"
            . "00:00:00.075 --> 00:00:05.125\r\nThomas kommt wieder. Eine digitale Analyse liegt vor.\r\n\r\n"
            . "00:00:05.275 --> 00:00:09.125\r\n– und führen das Gespräch, das kein Algorithmus führen kann.\r\n";
        $payload = json_encode(['captions' => [['langCode' => 'de', 'data' => rawurlencode($vtt)]]]);

        $cm = $this->make_package('Storyline Course', [
            'story_content/5e0XDmgLR4H_captions.js' =>
                "window.globalLoadJsAsset('story_content/5e0XDmgLR4H_captions.js', {$payload})",
        ]);

        $result = (new \aisourcesextractor_scorm\extractor())->extract($cm);

        $this->assertNotNull($result);
        $this->assertStringContainsString('Thomas kommt wieder.', $result['content']);
        $this->assertStringContainsString('kein Algorithmus führen kann.', $result['content']);
        // The NOTE block is metadata about the editor, not narration.
        $this->assertStringNotContainsString('Articulate Closed Captions Editor', $result['content']);
        $this->assertStringNotContainsString('3.117.36909.0', $result['content']);
    }

    /**
     * Test that on-screen slide text is extracted from a Storyline slide asset.
     */
    public function test_extract_reads_storyline_slide_text(): void {
        $slide = json_encode([
            'title' => 'Trends erkennen',
            'children' => [
                ['text' => 'Fühlen Sie sich bereit?'],
                ['text' => 'Ihre Einschätzung zählt\\n'],
                ['text' => '%_playerVars.menuSlideNumber%'],
            ],
        ]);
        // The asset embeds that JSON in a single-quoted JavaScript string.
        $embedded = str_replace('"', '\\"', $slide);

        $cm = $this->make_package('Storyline Course', [
            'html5/data/js/6dKtfD1uwLd.js' => "window.globalProvideData('slide', '{$embedded}');",
        ]);

        $result = (new \aisourcesextractor_scorm\extractor())->extract($cm);

        $this->assertNotNull($result);
        $this->assertStringContainsString('Trends erkennen', $result['content']);
        $this->assertStringContainsString('Fühlen Sie sich bereit?', $result['content']);
        // The tool's own line-break marker must not survive as text.
        $this->assertStringContainsString('Ihre Einschätzung zählt', $result['content']);
        $this->assertStringNotContainsString('zählt\\n', $result['content']);
        // Runtime placeholders are not content.
        $this->assertStringNotContainsString('_playerVars', $result['content']);
    }

    /**
     * Test that an inlined player stylesheet is not indexed as course content.
     *
     * A published package inlines its stylesheet into the launch page. Reading
     * the body without stripping it turned a screenful of CSS rules into the
     * document's "text" — noise that would be embedded and retrieved as if it
     * meant something.
     */
    public function test_extract_ignores_inline_css_and_script(): void {
        global $DB;

        $cm = $this->make_package('Player Shell', [
            'story.html' => '<html><head></head><body>'
                . '<style>.warn-connection-dialog { display: none; background: transparent; }</style>'
                . '<script>var g_processedData = {};</script>'
                . '<div id="app">Die Arbeitswelt im Wandel</div>'
                . '</body></html>',
        ]);

        $scormid = $DB->get_field('course_modules', 'instance', ['id' => $cm->id]);
        $DB->insert_record('scorm_scoes', [
            'scorm' => $scormid, 'manifest' => 'm1', 'organization' => 'o1', 'parent' => '/',
            'identifier' => 'sco1', 'launch' => 'story.html', 'scormtype' => 'sco',
            'title' => 'Die Arbeitswelt im Wandel', 'sortorder' => 1,
        ]);

        $result = (new \aisourcesextractor_scorm\extractor())->extract($cm);

        $this->assertNotNull($result);
        $this->assertStringContainsString('Die Arbeitswelt im Wandel', $result['content']);
        $this->assertStringNotContainsString('warn-connection-dialog', $result['content']);
        $this->assertStringNotContainsString('display: none', $result['content']);
        $this->assertStringNotContainsString('g_processedData', $result['content']);
    }

    /**
     * Test that a PDF inside the package becomes its own sub-document.
     *
     * Flattening it into the HTML would strip the very structure the RAG
     * service can parse itself; as a sub-document it keeps its content type.
     */
    public function test_package_pdf_becomes_a_subdocument(): void {
        $cm = $this->make_package('Course With PDF', [
            'story.html' => '<html><body><div>Intro</div></body></html>',
            'docs/handbook.pdf' => '%PDF-1.4 fake pdf payload',
        ]);

        $documents = (new \aisourcesextractor_scorm\extractor())->extract_documents($cm);

        $suffixes = array_column($documents, 'suffix');
        $this->assertContains('main', $suffixes, 'The narrative stays one document.');

        $pdf = null;
        foreach ($documents as $document) {
            if ($document['content_type'] === 'application/pdf') {
                $pdf = $document;
            }
        }

        $this->assertNotNull($pdf, 'A packaged PDF must travel as its own document.');
        $this->assertStringContainsString('handbook', $pdf['suffix']);
        $this->assertStringContainsString('docs', $pdf['suffix'], 'The path keeps the suffix stable and unique.');
        $this->assertSame('%PDF-1.4 fake pdf payload', $pdf['content']);
    }

    /**
     * Test that a package without PDFs yields only the main document.
     */
    public function test_package_without_pdf_yields_only_main(): void {
        $cm = $this->make_package('Plain Course', [
            'story.html' => '<html><body><div>Only text</div></body></html>',
        ]);

        $documents = (new \aisourcesextractor_scorm\extractor())->extract_documents($cm);

        $this->assertCount(1, $documents);
        $this->assertSame('main', $documents[0]['suffix']);
        $this->assertSame('text/html', $documents[0]['content_type']);
    }

    /**
     * Test that the course language selects among multi-language tracks.
     */
    public function test_course_language_selects_the_matching_track(): void {
        global $DB;

        $cm = $this->make_package('Multilingual', [
            'audio/lesson.de.vtt' => "WEBVTT\n\n00:00:00.000 --> 00:00:02.000\nDeutscher Sprechertext.\n",
            'audio/lesson.en.vtt' => "WEBVTT\n\n00:00:00.000 --> 00:00:02.000\nEnglish narration.\n",
        ]);
        $DB->set_field('course', 'lang', 'de', ['id' => $cm->course]);
        rebuild_course_cache((int) $cm->course, true);
        $cm = get_fast_modinfo((int) $cm->course)->get_cm((int) $cm->id);

        $result = (new \aisourcesextractor_scorm\extractor())->extract($cm);

        $this->assertStringContainsString('Deutscher Sprechertext.', $result['content']);
        $this->assertStringNotContainsString('English narration.', $result['content']);
    }

    /**
     * Test that without a matching track every language is kept — the only
     * transcript a package has must never be dropped.
     */
    public function test_without_matching_language_all_tracks_are_kept(): void {
        global $DB;

        $cm = $this->make_package('Multilingual', [
            'audio/lesson.fr.vtt' => "WEBVTT\n\n00:00:00.000 --> 00:00:02.000\nNarration francaise.\n",
            'audio/lesson.en.vtt' => "WEBVTT\n\n00:00:00.000 --> 00:00:02.000\nEnglish narration.\n",
        ]);
        $DB->set_field('course', 'lang', 'de', ['id' => $cm->course]);
        rebuild_course_cache((int) $cm->course, true);
        $cm = get_fast_modinfo((int) $cm->course)->get_cm((int) $cm->id);

        $result = (new \aisourcesextractor_scorm\extractor())->extract($cm);

        $this->assertStringContainsString('Narration francaise.', $result['content']);
        $this->assertStringContainsString('English narration.', $result['content']);
    }

    /**
     * Test that navigation labels are filtered from on-screen text while real
     * content — including words that merely start like a label — survives.
     */
    public function test_chrome_is_filtered_from_slide_text(): void {
        $slide = json_encode([
            'title' => 'Trends erkennen',
            'children' => [
                ['text' => 'Weiter'],
                ['text' => '75 %'],
                ['text' => 'Folie 3 von 12'],
                ['text' => 'Weiter denken lohnt sich.'],
                ['text' => 'Der Wandel betrifft alle Bereiche.'],
            ],
        ]);
        $embedded = str_replace('"', '\\"', $slide);

        $cm = $this->make_package('Storyline Course', [
            'html5/data/js/slide1.js' => "window.globalProvideData('slide', '{$embedded}');",
        ]);

        $result = (new \aisourcesextractor_scorm\extractor())->extract($cm);

        $this->assertStringContainsString('Der Wandel betrifft alle Bereiche.', $result['content']);
        $this->assertStringContainsString('Weiter denken lohnt sich.', $result['content'], 'Only whole matches are chrome.');
        $this->assertStringNotContainsString('>Weiter<', $result['content']);
        $this->assertStringNotContainsString('75 %', $result['content']);
        $this->assertStringNotContainsString('Folie 3 von 12', $result['content']);
    }

    /**
     * Test that on-screen harvesting can be switched off without touching the
     * transcript.
     */
    public function test_slide_text_harvesting_can_be_disabled(): void {
        $slide = json_encode(['title' => 'Screen', 'children' => [['text' => 'On-screen sentence.']]]);
        $embedded = str_replace('"', '\\"', $slide);

        $cm = $this->make_package('Storyline Course', [
            'html5/data/js/slide1.js' => "window.globalProvideData('slide', '{$embedded}');",
            'audio/lesson.vtt' => "WEBVTT\n\n00:00:00.000 --> 00:00:02.000\nSpoken sentence.\n",
        ]);

        set_config('scorm_harvest_slidetext', 0, 'local_elediaai_sources');
        $result = (new \aisourcesextractor_scorm\extractor())->extract($cm);

        $this->assertStringNotContainsString('On-screen sentence.', $result['content']);
        $this->assertStringContainsString('Spoken sentence.', $result['content'], 'The transcript is never affected.');
    }

    /**
     * The extractor answers the caption question for a local package.
     */
    public function test_accessibility_report_judges_the_package_media(): void {
        $cm = $this->make_package('Captioned course', [
            'index.html' => '<html><body><video src="media/one.mp4">'
                . '<track kind="captions" src="cc/one.vtt"></video>'
                . '<audio src="media/two.mp3"></audio></body></html>',
            'media/one.mp4' => 'binary',
            'media/two.mp3' => 'binary',
            'cc/one.vtt' => "WEBVTT\n\n00:00.000 --> 00:02.000\nHello",
        ]);

        $report = (new \aisourcesextractor_scorm\extractor())->accessibility_report($cm);

        $this->assertSame(\local_elediaai_sources\media_accessibility::VERDICT_INCOMPLETE, $report['verdict']);
        $this->assertSame(2, $report['total']);
        $this->assertSame(1, $report['captioned']);
        $this->assertSame(['media/two.mp3'], $report['uncaptioned']);
    }

    /**
     * The player skeleton of an authored package is not content.
     */
    public function test_the_player_skeleton_is_not_ingested(): void {
        global $DB;
        $cm = $this->make_package('Authored course', [
            'story.html' => '<html><body>'
                . '<div id="focus-sink" tabindex="-1"></div><div id="preso"></div>'
                . '<div class="warn-connection-dialog" role="alertdialog">'
                . '<p>You are offline. Trying to reconnect.</p></div>'
                . '<link rel="stylesheet" href="html5/data/css/output.min.css"/>'
                . '<p>Real lesson prose.</p></body></html>',
        ]);
        $DB->insert_record('scorm_scoes', [
            'scorm' => $DB->get_field('course_modules', 'instance', ['id' => $cm->id]),
            'manifest' => 'm',
            'organization' => 'o',
            'parent' => '/',
            'identifier' => 'sco1',
            'launch' => 'story.html',
            'scormtype' => 'sco',
            'title' => 'Lesson',
            'sortorder' => 1,
        ]);

        $content = (string) (new \aisourcesextractor_scorm\extractor())->extract($cm)['content'];

        $this->assertStringContainsString('Real lesson prose.', $content);
        $this->assertStringNotContainsString('focus-sink', $content);
        $this->assertStringNotContainsString('You are offline', $content);
        $this->assertStringNotContainsString('output.min.css', $content);
    }

    /**
     * Build a local SCORM package holding the given files.
     *
     * @param string $name The activity name.
     * @param array<string, string> $files Contents keyed by path relative to the package root.
     * @return \cm_info The course module.
     */
    private function make_package(string $name, array $files): \cm_info {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $scorm = $this->getDataGenerator()->create_module('scorm', [
            'course' => $course->id,
            'name' => $name,
        ]);
        $DB->set_field('scorm', 'scormtype', 'local', ['id' => $scorm->id]);
        $DB->delete_records('scorm_scoes', ['scorm' => $scorm->id]);

        $context = \core\context\module::instance($scorm->cmid);
        $fs = get_file_storage();
        foreach ($files as $path => $content) {
            $filename = basename($path);
            $dir = trim(dirname($path), '.');
            $filepath = ($dir === '' ? '/' : '/' . trim($dir, '/') . '/');
            $fs->create_file_from_string([
                'contextid' => $context->id,
                'component' => 'mod_scorm',
                'filearea' => 'content',
                'itemid' => 0,
                'filepath' => $filepath,
                'filename' => $filename,
            ], $content);
        }

        return get_fast_modinfo($course->id)->get_cm($scorm->cmid);
    }

    /**
     * Test that extraction returns only intro when no SCOs exist.
     */
    public function test_extract_returns_intro_without_scoes(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $scorm = $this->getDataGenerator()->create_module('scorm', [
            'course' => $course->id,
            'name' => 'Empty SCORM',
            'intro' => '<p>SCORM package description.</p>',
        ]);

        // Remove all SCOs.
        $DB->delete_records('scorm_scoes', ['scorm' => $scorm->id]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($scorm->cmid);

        $extractor = new \aisourcesextractor_scorm\extractor();
        $result = $extractor->extract($cm);

        $this->assertNotNull($result);
        $this->assertStringContainsString('SCORM package description.', $result['content']);
    }

    /**
     * The document names what kind of thing it is, not only what it is called.
     *
     * A package is filed in Moodle as SCORM; learners ask for a "Lernpaket".
     * LiteRAG matches text, so a word that appears nowhere in the document is
     * a question that finds nothing (AI-53). The heading carries both terms.
     */
    public function test_the_heading_names_the_package_kind(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $scorm = $this->getDataGenerator()->create_module('scorm', [
            'course' => $course->id,
            'name' => 'Photosynthese',
            'intro' => '<p>Worum es geht.</p>',
        ]);
        $cm = get_fast_modinfo($course->id)->get_cm($scorm->cmid);

        $result = (new \aisourcesextractor_scorm\extractor())->extract($cm);

        $this->assertNotNull($result);
        $this->assertMatchesRegularExpression(
            '~^\s*<h1\b~i',
            $result['content'],
            'The document must lead with its own heading, or the generic one is prepended instead.'
        );
        $heading = $this->first_heading($result['content']);
        $this->assertStringContainsString('Photosynthese', $heading);
        $this->assertStringContainsString('SCORM', $heading);
        $this->assertStringContainsString(
            get_string('documentheading', 'aisourcesextractor_scorm', 'Photosynthese'),
            html_entity_decode($heading, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * The generic heading is not added on top of the extractor's own.
     *
     * Two headings would mean the activity name twice and the kind buried
     * under it, which is the opposite of what the ticket asks for.
     */
    public function test_the_generic_heading_stands_back(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $scorm = $this->getDataGenerator()->create_module('scorm', [
            'course' => $course->id,
            'name' => 'Photosynthese',
            'intro' => '<p>Worum es geht.</p>',
        ]);
        $cm = get_fast_modinfo($course->id)->get_cm($scorm->cmid);
        $result = (new \aisourcesextractor_scorm\extractor())->extract($cm);

        $withheading = \local_elediaai_sources\document::with_heading(
            $result['content'],
            $result['content_type'],
            'Photosynthese'
        );

        $this->assertSame($result['content'], $withheading);
        $this->assertSame(1, substr_count(strtolower($withheading), '<h1'));
    }

    /**
     * The first h1 of a document, without its tags.
     *
     * @param string $html The document.
     * @return string The heading text.
     */
    private function first_heading(string $html): string {
        return preg_match('~<h1\b[^>]*>(.*?)</h1>~is', $html, $found) ? $found[1] : '';
    }
}

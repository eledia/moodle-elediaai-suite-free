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
 * Unit tests for the videotime transcript content extractor.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aisourcesextractor_videotime\extractor
 */
final class extractor_videotime_test extends \advanced_testcase {
    /**
     * Skip the current test if mod_videotime generator is not available.
     */
    private function require_videotime_generator(): void {
        $plugindir = \core_component::get_plugin_directory('mod', 'videotime');
        if (!$plugindir || !file_exists($plugindir . '/tests/generator/lib.php')) {
            $this->markTestSkipped('mod_videotime generator is not available.');
        }
    }

    /**
     * Test that the extractor supports videotime modules.
     */
    public function test_supports_videotime(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->require_videotime_generator();

        $course = $this->getDataGenerator()->create_course();
        $videotime = $this->getDataGenerator()->create_module('videotime', [
            'course' => $course->id,
            'name' => 'Lecture Recording',
        ]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($videotime->cmid);

        $extractor = new \aisourcesextractor_videotime\extractor();
        $this->assertTrue($extractor->supports($cm));
    }

    /**
     * Test that the extractor does not support other module types.
     */
    public function test_does_not_support_page(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Not a video</p>',
        ]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($page->cmid);

        $extractor = new \aisourcesextractor_videotime\extractor();
        $this->assertFalse($extractor->supports($cm));
    }

    /**
     * Test extracting transcript text from a videotime activity with captions.
     */
    public function test_extract_returns_transcript(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->require_videotime_generator();

        $course = $this->getDataGenerator()->create_course();
        $videotime = $this->getDataGenerator()->create_module('videotime', [
            'course' => $course->id,
            'name' => 'My Lecture',
        ]);

        /** @var \mod_videotime_generator $vtgen */
        $vtgen = $this->getDataGenerator()->get_plugin_generator('mod_videotime');
        $vtgen->create_texttrack($videotime->cmid, [
            'kind' => 'captions',
            'label' => 'English',
            'srclang' => 'en',
            'cues' => [
                ['00:00:00.000 --> 00:00:05.000', 'Welcome to the lecture.'],
                ['00:00:05.000 --> 00:00:10.000', 'Today we discuss algorithms.'],
            ],
        ]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($videotime->cmid);

        $extractor = new \aisourcesextractor_videotime\extractor();
        $result = $extractor->extract($cm);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('content_type', $result);
        $this->assertArrayHasKey('title', $result);
        $this->assertEquals('text/plain', $result['content_type']);
        $this->assertEquals('My Lecture', $result['title']);
        $this->assertStringContainsString('Welcome to the lecture.', $result['content']);
        $this->assertStringContainsString('Today we discuss algorithms.', $result['content']);
        // Timestamps must not appear in the extracted text.
        $this->assertStringNotContainsString('-->', $result['content']);
        $this->assertStringNotContainsString('WEBVTT', $result['content']);
    }

    /**
     * Test that extraction returns null when no tracks exist.
     */
    public function test_extract_returns_null_without_tracks(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->require_videotime_generator();

        $course = $this->getDataGenerator()->create_course();
        $videotime = $this->getDataGenerator()->create_module('videotime', [
            'course' => $course->id,
            'name' => 'Video Without Captions',
        ]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($videotime->cmid);

        $extractor = new \aisourcesextractor_videotime\extractor();
        $result = $extractor->extract($cm);

        $this->assertNull($result);
    }

    /**
     * Test that extraction returns null when a track record exists but has no VTT file.
     */
    public function test_extract_returns_null_for_track_without_file(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $this->require_videotime_generator();

        $course = $this->getDataGenerator()->create_course();
        $videotime = $this->getDataGenerator()->create_module('videotime', [
            'course' => $course->id,
            'name' => 'Video Missing File',
        ]);

        // Insert a track record without creating the associated file.
        $DB->insert_record('videotime_track', [
            'videotime' => $videotime->id,
            'isdefault' => 0,
            'kind' => 'captions',
            'label' => 'English',
            'srclang' => 'en',
            'visible' => 1,
        ]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($videotime->cmid);

        $extractor = new \aisourcesextractor_videotime\extractor();
        $result = $extractor->extract($cm);

        $this->assertNull($result);
    }

    /**
     * Test that multiple tracks are concatenated into a single transcript.
     */
    public function test_extract_combines_multiple_tracks(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->require_videotime_generator();

        $course = $this->getDataGenerator()->create_course();
        $videotime = $this->getDataGenerator()->create_module('videotime', [
            'course' => $course->id,
            'name' => 'Multi-Track Video',
        ]);

        /** @var \mod_videotime_generator $vtgen */
        $vtgen = $this->getDataGenerator()->get_plugin_generator('mod_videotime');

        // First track.
        $vtgen->create_texttrack($videotime->cmid, [
            'kind' => 'captions',
            'label' => 'English',
            'srclang' => 'en',
            'cues' => [
                ['00:00:00.000 --> 00:00:03.000', 'First track, first cue.'],
            ],
        ]);

        // Second track.
        $vtgen->create_texttrack($videotime->cmid, [
            'kind' => 'subtitles',
            'label' => 'German',
            'srclang' => 'de',
            'filename' => 'subtitles_de.vtt',
            'cues' => [
                ['00:00:00.000 --> 00:00:03.000', 'Zweiter Track, erster Cue.'],
            ],
        ]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($videotime->cmid);

        $extractor = new \aisourcesextractor_videotime\extractor();
        $result = $extractor->extract($cm);

        $this->assertNotNull($result);
        $this->assertStringContainsString('First track, first cue.', $result['content']);
        $this->assertStringContainsString('Zweiter Track, erster Cue.', $result['content']);
    }

    /**
     * Test VTT parsing strips headers, timestamps, and formatting tags.
     *
     * @dataProvider vtt_parsing_provider
     * @param string $vtt The raw VTT input.
     * @param string $expected The expected plain-text output.
     */
    public function test_parse_vtt_text(string $vtt, string $expected): void {
        $result = \aisourcesextractor_videotime\extractor::parse_vtt_text($vtt);
        $this->assertEquals($expected, $result);
    }

    /**
     * Data provider for VTT parsing tests.
     *
     * @return array[]
     */
    public static function vtt_parsing_provider(): array {
        return [
            'basic cues' => [
                "WEBVTT\n\n00:00:00.000 --> 00:00:05.000\nHello world\n\n00:00:05.000 --> 00:00:10.000\nSecond line",
                "Hello world\nSecond line",
            ],
            'cues with identifiers' => [
                "WEBVTT\n\n1\n00:00:00.000 --> 00:00:05.000\nFirst cue\n\n2\n00:00:05.000 --> 00:00:10.000\nSecond cue",
                "First cue\nSecond cue",
            ],
            'cues with formatting tags' => [
                "WEBVTT\n\n00:00:00.000 --> 00:00:05.000\n<b>Bold text</b> and <i>italic</i>",
                "Bold text and italic",
            ],
            'cues with voice tags' => [
                "WEBVTT\n\n00:00:00.000 --> 00:00:05.000\n<v Speaker>Hello everyone</v>",
                "Hello everyone",
            ],
            'empty VTT' => [
                "WEBVTT\n\n",
                "",
            ],
            'VTT with NOTE block' => [
                "WEBVTT\n\nNOTE\nThis is a comment\n\n00:00:00.000 --> 00:00:05.000\nActual content",
                "Actual content",
            ],
            'VTT with STYLE block' => [
                "WEBVTT\n\nSTYLE\n::cue { color: white; }\n\n00:00:00.000 --> 00:00:02.000\nStyled text",
                "Styled text",
            ],
            'multiline cue' => [
                "WEBVTT\n\n00:00:00.000 --> 00:00:05.000\nLine one\nLine two",
                "Line one Line two",
            ],
            'windows line endings' => [
                "WEBVTT\r\n\r\n00:00:00.000 --> 00:00:05.000\r\nHello from Windows",
                "Hello from Windows",
            ],
        ];
    }
}

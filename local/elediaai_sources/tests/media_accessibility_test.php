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
 * Unit tests for the caption coverage scan.
 *
 * The cases are the forms the media-to-caption link takes in the wild, not the
 * products that produce them: an authoring tool this code has never seen must
 * still be judged correctly, or the report becomes a Storyline feature.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_sources\media_accessibility
 */
final class media_accessibility_test extends \advanced_testcase {
    /**
     * A package without audio or video has nothing to answer for.
     */
    public function test_a_package_without_media(): void {
        $report = media_accessibility::scan(['index.html' => '<p>Text only</p>']);

        $this->assertSame(media_accessibility::VERDICT_NOMEDIA, $report['verdict']);
        $this->assertSame(0, $report['total']);
    }

    /**
     * An HTML track element is the tool-independent link.
     */
    public function test_an_html_track_counts_as_captions(): void {
        $report = media_accessibility::scan([
            'index.html' => '<video src="media/lesson.mp4">'
                . '<track kind="captions" srclang="de" src="cc/lesson.vtt"></video>',
            'media/lesson.mp4' => '',
            'cc/lesson.vtt' => 'WEBVTT',
        ]);

        $this->assertSame(media_accessibility::VERDICT_COMPLETE, $report['verdict']);
        $this->assertSame(1, $report['captioned']);
    }

    /**
     * A player element without a track proves the absence rather than hinting.
     */
    public function test_a_player_without_a_track_is_proof_of_absence(): void {
        $report = media_accessibility::scan([
            'index.html' => '<video src="a.mp4"><track kind="captions" src="a.vtt"></video>'
                . '<audio src="b.mp3"></audio>',
            'a.mp4' => '',
            'b.mp3' => '',
            'a.vtt' => 'WEBVTT',
        ]);

        $this->assertSame(media_accessibility::VERDICT_INCOMPLETE, $report['verdict']);
        $this->assertSame(['b.mp3'], $report['uncaptioned']);
    }

    /**
     * A caption file named after its media file counts, language infix included.
     */
    public function test_a_sidecar_track_counts(): void {
        $report = media_accessibility::scan([
            'media/lesson.mp4' => '',
            'media/lesson.de.vtt' => 'WEBVTT',
        ]);

        $this->assertSame(media_accessibility::VERDICT_COMPLETE, $report['verdict']);
    }

    /**
     * A media path and a caption path in one data object are a link, whatever
     * the keys are called — this is what carries unknown authoring tools.
     */
    public function test_a_reference_in_packaged_data_counts(): void {
        $report = media_accessibility::scan([
            'data/assets.json' => '{"kind":"asset","captions":"cc/track01.vtt","url":"audio/slide1.mp3"}',
            'audio/slide1.mp3' => '',
            'cc/track01.vtt' => 'WEBVTT',
        ]);

        $this->assertSame(media_accessibility::VERDICT_COMPLETE, $report['verdict']);
    }

    /**
     * A reference to a caption file that is not in the package is worth nothing.
     *
     * This is what a broken export leaves behind, and it is the difference
     * between a report and a rubber stamp.
     */
    public function test_a_dangling_reference_does_not_count(): void {
        $report = media_accessibility::scan([
            'data/assets.json' => '{"kind":"asset","captions":"cc/gone.vtt","url":"audio/slide1.mp3"}',
            'audio/slide1.mp3' => '',
        ]);

        $this->assertSame(media_accessibility::VERDICT_INCOMPLETE, $report['verdict']);
        $this->assertSame(['audio/slide1.mp3'], $report['uncaptioned']);
        // The broken export is named, because it is repaired differently from
        // captions that were never authored at all.
        $this->assertSame(['cc/gone.vtt'], $report['danglingcaptions']);
    }

    /**
     * A package with everything in place accuses nobody of a broken export.
     */
    public function test_a_sound_package_reports_no_broken_references(): void {
        $report = media_accessibility::scan([
            'data/assets.json' => '{"kind":"asset","captions":"cc/track01.vtt","url":"audio/slide1.mp3"}',
            'audio/slide1.mp3' => '',
            'cc/track01.vtt' => 'WEBVTT',
        ]);

        $this->assertSame([], $report['danglingcaptions']);
    }

    /**
     * Captions that cannot be paired are reported as such, not as a failure.
     */
    public function test_unpairable_captions_stay_undetermined(): void {
        $report = media_accessibility::scan([
            'audio/one.mp3' => '',
            'audio/two.mp3' => '',
            'subs/1e6f.vtt' => 'WEBVTT',
            'subs/9a2b.vtt' => 'WEBVTT',
        ]);

        $this->assertSame(media_accessibility::VERDICT_UNDETERMINED, $report['verdict']);
        $this->assertCount(2, $report['undetermined']);
        $this->assertSame([], $report['uncaptioned']);
    }

    /**
     * With no caption material at all, silence is the answer.
     */
    public function test_a_package_that_captions_nothing_is_incomplete(): void {
        $report = media_accessibility::scan(['audio/one.mp3' => '', 'audio/two.mp3' => '']);

        $this->assertSame(media_accessibility::VERDICT_INCOMPLETE, $report['verdict']);
        $this->assertCount(2, $report['uncaptioned']);
    }

    /**
     * Audio without a picture is satisfied by a transcript, not only by captions.
     *
     * WCAG 1.2.1 accepts a text alternative for audio-only media, so accusing
     * such a package of missing captions would be wrong. That the transcript
     * really covers these recordings cannot be read from the package, so the
     * answer is doubt rather than clearance.
     */
    public function test_audio_with_a_transcript_is_not_an_accusation(): void {
        $report = media_accessibility::scan([
            'audio/lesson1.mp3' => '',
            'audio/lesson2.mp3' => '',
            'Transkript.pdf' => '',
        ]);

        $this->assertSame(media_accessibility::VERDICT_UNDETERMINED, $report['verdict']);
        $this->assertTrue($report['hastranscript']);
        $this->assertSame([], $report['uncaptioned']);
        $this->assertSame(2, $report['audio']);
        $this->assertSame(0, $report['video']);
    }

    /**
     * A picture changes the requirement: captions, and a transcript is no
     * substitute for them (WCAG 1.2.2).
     */
    public function test_video_is_not_excused_by_a_transcript(): void {
        $report = media_accessibility::scan([
            'media/lesson.mp4' => '',
            'transcript.html' => '<p>Spoken words</p>',
        ]);

        $this->assertSame(media_accessibility::VERDICT_INCOMPLETE, $report['verdict']);
        $this->assertSame(['media/lesson.mp4'], $report['uncaptioned']);
        $this->assertSame(1, $report['video']);
    }

    /**
     * Audio in a package that ships no transcript at all remains a failure.
     */
    public function test_audio_without_any_alternative_is_a_failure(): void {
        $report = media_accessibility::scan(['audio/lesson.mp3' => '']);

        $this->assertSame(media_accessibility::VERDICT_INCOMPLETE, $report['verdict']);
        $this->assertFalse($report['hastranscript']);
        $this->assertSame(['audio/lesson.mp3'], $report['uncaptioned']);
    }

    /**
     * A player configured to show a transcript panel counts as evidence too.
     */
    public function test_a_transcript_panel_counts_as_evidence(): void {
        $report = media_accessibility::scan([
            'data/frame.js' => '{"tabs":{"sidebar":[{"name":"outline"},{"name":"transcript"}]},"transcript":true}',
            'audio/lesson.mp3' => '',
        ]);

        $this->assertTrue($report['hastranscript']);
        $this->assertSame(media_accessibility::VERDICT_UNDETERMINED, $report['verdict']);
    }

    /**
     * References are resolved both from the package root and from the file
     * holding them, because tools disagree on which they write.
     */
    public function test_references_resolve_relative_to_their_file(): void {
        $report = media_accessibility::scan([
            'html5/data/data.js' => '{"captions":"track.vtt","url":"lesson.mp3"}',
            'html5/data/lesson.mp3' => '',
            'html5/data/track.vtt' => 'WEBVTT',
        ]);

        $this->assertSame(media_accessibility::VERDICT_COMPLETE, $report['verdict']);
    }
}

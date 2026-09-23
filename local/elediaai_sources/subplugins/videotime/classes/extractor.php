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

namespace aisourcesextractor_videotime;

use local_elediaai_sources\content_extractor;

/**
 * Content extractor for mod_videotime activities.
 *
 * Extracts video transcript text from the VTT text-track files attached
 * to a Video Time activity. The raw VTT content is parsed to strip
 * headers and timestamps, producing plain-text transcript content
 * suitable for AI Sources.
 *
 * Reads directly from Moodle file storage (component 'mod_videotime',
 * filearea 'texttrack') via the {@see videotime_track} metadata table,
 * so it works independently of the videotimetab_texttrack subplugin.
 *
 * @package    aisourcesextractor_videotime
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class extractor implements content_extractor {
    /**
     * Check whether this extractor supports the given module.
     *
     * @param \cm_info $cm The course module info.
     * @return bool True if this is a videotime module.
     */
    public function supports(\cm_info $cm): bool {
        return $cm->modname === 'videotime';
    }

    /**
     * Extract transcript text from a videotime activity.
     *
     * Collects all VTT text-track files associated with the activity,
     * parses them to extract only the spoken-text cues (stripping the
     * WEBVTT header, cue identifiers and timestamps), and returns the
     * combined transcript as plain text.
     *
     * @param \cm_info $cm The course module info.
     * @return array|null Extracted document data, or null if no transcript content found.
     */
    public function extract(\cm_info $cm): ?array {
        global $DB;

        $videotime = $DB->get_record('videotime', ['id' => $cm->instance], 'id, name, intro', MUST_EXIST);

        // Get all tracks for this videotime instance.
        $tracks = $DB->get_records('videotime_track', ['videotime' => $videotime->id], 'id ASC');

        if (empty($tracks)) {
            return null;
        }

        $context = \core\context\module::instance($cm->id);
        $fs = get_file_storage();
        $transcriptparts = [];

        foreach ($tracks as $track) {
            $files = $fs->get_area_files(
                $context->id,
                'mod_videotime',
                'texttrack',
                $track->id,
                'id ASC',
                false,
            );

            foreach ($files as $file) {
                $vttcontent = $file->get_content();
                if (empty($vttcontent)) {
                    continue;
                }

                $text = self::parse_vtt_text($vttcontent);
                if ($text !== '') {
                    $transcriptparts[] = $text;
                }
            }
        }

        if (empty($transcriptparts)) {
            return null;
        }

        // Lead with the video description (intro) when present, then the
        // transcript. The activity name is added centrally by the manager.
        $intro = trim(html_entity_decode(
            strip_tags((string) ($videotime->intro ?? '')),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        ));
        $body = implode("\n\n", $transcriptparts);
        $content = $intro !== '' ? $intro . "\n\n" . $body : $body;

        return [
            'content' => $content,
            'content_type' => 'text/plain',
            'title' => $videotime->name,
        ];
    }

    /**
     * Parse a WebVTT string and return only the cue text.
     *
     * Strips the WEBVTT header, NOTE blocks, cue identifiers, and
     * timestamp lines, returning just the spoken-text content with
     * one line per cue, separated by newlines.
     *
     * @param string $vtt Raw WebVTT file content.
     * @return string Plain text transcript (may be empty).
     */
    public static function parse_vtt_text(string $vtt): string {
        // Normalise line endings.
        $vtt = str_replace(["\r\n", "\r"], "\n", $vtt);
        $blocks = preg_split('/\n{2,}/', $vtt);

        $lines = [];
        foreach ($blocks as $block) {
            $block = trim($block);

            // Skip the WEBVTT header block.
            if (str_starts_with($block, 'WEBVTT')) {
                continue;
            }

            // Skip NOTE blocks.
            if (str_starts_with($block, 'NOTE')) {
                continue;
            }

            // Skip STYLE blocks.
            if (str_starts_with($block, 'STYLE')) {
                continue;
            }

            // Extract cue text: remove optional cue id and the timestamp line.
            $cuetext = self::extract_cue_text($block);
            if ($cuetext !== '') {
                $lines[] = $cuetext;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Extract the text payload from a single VTT cue block.
     *
     * A cue block may optionally start with a cue identifier line,
     * followed by the timestamp line (containing '-->'), followed by
     * one or more lines of cue text.
     *
     * @param string $block A single cue block (already trimmed).
     * @return string The cue text with VTT tags stripped, or empty string.
     */
    private static function extract_cue_text(string $block): string {
        $blocklines = explode("\n", $block);
        $textlines = [];
        $foundtimestamp = false;

        foreach ($blocklines as $line) {
            if ($foundtimestamp) {
                $textlines[] = $line;
            } else if (str_contains($line, '-->')) {
                $foundtimestamp = true;
            }
        }

        if (empty($textlines)) {
            return '';
        }

        $text = implode(' ', $textlines);

        // Strip VTT formatting tags like <b>, <i>, <v Name>, etc.
        $text = preg_replace('/<[^>]+>/', '', $text);

        return trim($text);
    }
}

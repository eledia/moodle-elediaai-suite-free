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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace webservice_elediamcp\local\ai;

/**
 * Build the citation object that content-returning tools attach to their result.
 *
 * The RAG server spec (block_elediaai_tutor/docs/rag_server_spec.md, A.1)
 * defines a source entry as an object carrying a title, a url and a snippet.
 * A tool that returns readable course content is exactly such a citable
 * artefact, so it ships one ready-made instead of leaving every consumer to
 * guess which of its fields are quotable. The agent (and local_literag) can
 * lift the object straight into its own `sources` array.
 *
 * The field names deliberately match the spec's primary aliases (`title`,
 * `url`, `snippet`), so no mapping is needed on either side.
 *
 * @package     webservice_elediamcp
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class source {
    /** @var int Maximum snippet length in characters. */
    private const SNIPPET_MAX = 300;

    /**
     * Build a source entry.
     *
     * @param string $title Human-readable name of the cited item.
     * @param string $url Link a learner can follow, absolute.
     * @param string $snippet Plain-text excerpt; trimmed to a sensible length.
     * @param int|null $cmid Course module id when the item is an activity.
     * @param int|null $courseid Course the item belongs to.
     * @return array{title: string, url: string, snippet: string, cmid?: int, course_id?: int}
     */
    public static function build(
        string $title,
        string $url,
        string $snippet = '',
        ?int $cmid = null,
        ?int $courseid = null
    ): array {
        $entry = [
            'title' => trim($title),
            'url' => $url,
            'snippet' => self::snippet($snippet),
        ];
        // Lets the tutor block resolve the citation to a Moodle activity for
        // its teacher analytics without re-parsing the URL.
        if ($cmid !== null && $cmid > 0) {
            $entry['cmid'] = $cmid;
        }
        if ($courseid !== null && $courseid > 0) {
            $entry['course_id'] = $courseid;
        }
        return $entry;
    }

    /**
     * Reduce arbitrary content to a short plain-text excerpt.
     *
     * @param string $text Content, possibly HTML or very long.
     * @param int $max Maximum length.
     * @return string
     */
    public static function snippet(string $text, int $max = self::SNIPPET_MAX): string {
        $plain = trim(preg_replace('/\s+/u', ' ', html_to_text($text, 0, false)) ?? '');
        if ($plain === '' || \core_text::strlen($plain) <= $max) {
            return $plain;
        }
        return \core_text::substr($plain, 0, $max - 1) . '…';
    }

    /**
     * JSON schema fragment describing a source entry.
     *
     * @return array
     */
    public static function schema(): array {
        return [
            'type' => 'object',
            'description' => 'Citation for this item, ready to be used as a source entry.',
            'required' => ['title', 'url', 'snippet'],
            'properties' => [
                'title' => ['type' => 'string'],
                'url' => ['type' => 'string'],
                'snippet' => ['type' => 'string'],
                'cmid' => ['type' => 'integer'],
                'course_id' => ['type' => 'integer'],
            ],
        ];
    }
}

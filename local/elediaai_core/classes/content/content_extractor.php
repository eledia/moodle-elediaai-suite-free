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
 * Pull readable text out of a course-module so AI generators can ground
 * their output in it. Shared across the AI suite (question generator,
 * H5P author, …) — these plugins are not meant to run independently of
 * one another, so the extractor lives in the common AI umbrella.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core\content;

defined('MOODLE_INTERNAL') || die();

use coding_exception;
use context_module;
use moodle_exception;
use stored_file;

/**
 * Best-effort text extraction across the activity types where it is
 * cheap and side-effect-free to grab a body of prose.
 *
 * Strategy per type:
 *  - `page`  → `page.content` (raw HTML stripped to text).
 *  - `book`  → concatenated `book_chapters.content` ordered by sortorder.
 *  - `label` → `label.intro`.
 *  - `resource` (File) → text of attached files (plain-text MIME or
 *                       inline HTML). Binary formats (PDF, doc) skipped
 *                       in Phase 1 — Phase 2 plugs in an image-to-text
 *                       core_ai action.
 *  - `folder`  → concatenated text of inline-text files in the folder.
 *  - `lesson`  → page contents, ordered by lesson page id.
 *
 * Anything else throws so the caller can show
 * `error_extract_unsupported` instead of silently sending an empty
 * prompt to the AI.
 */
final class content_extractor {
    /** @var string[] Course-module types we can extract from in Phase 1. */
    public const SUPPORTED = ['page', 'book', 'label', 'resource', 'folder', 'lesson'];

    /**
     * Extract the readable text content of a single course-module.
     *
     * @param int $cmid Course-module id.
     * @param \core\context\course|null $authorisedcontext Course context when
     *     the caller already completed course login and authorisation.
     * @return string Plain text, trimmed.
     * @throws moodle_exception when the module type is not supported.
     */
    public static function extract(
        int $cmid,
        ?\core\context\course $authorisedcontext = null
    ): string {
        $cmrecord = get_coursemodule_from_id(null, $cmid, 0, false, MUST_EXIST);
        $cm = get_fast_modinfo((int) $cmrecord->course)->get_cm($cmid);
        if ($authorisedcontext === null) {
            require_login((int) $cmrecord->course, false, $cm);
        } else {
            if ((int) $authorisedcontext->instanceid !== (int) $cmrecord->course) {
                throw new \invalid_parameter_exception(
                    'The authorised course context does not match the source activity.'
                );
            }
            require_capability('moodle/course:manageactivities', $authorisedcontext);
        }
        if (empty($cm->uservisible)) {
            throw new moodle_exception('error_extract_unsupported', 'local_elediaai_core');
        }
        if (!in_array($cm->modname, self::SUPPORTED, true)) {
            throw new moodle_exception('error_extract_unsupported', 'local_elediaai_core', '', $cm->modname);
        }

        return match ($cm->modname) {
            'page' => self::extract_page((int) $cm->instance),
            'book' => self::extract_book((int) $cm->instance),
            'label' => self::extract_label((int) $cm->instance),
            'resource' => self::extract_resource($cm),
            'folder' => self::extract_folder($cm),
            'lesson' => self::extract_lesson((int) $cm->instance),
            default => throw new coding_exception("unhandled modname: {$cm->modname}"),
        };
    }

    /**
     * List the course-modules in a course that we know how to extract.
     *
     * @param int $courseid
     * @return array<int, string> cmid => display label.
     */
    public static function list_supported(int $courseid): array {
        require_login($courseid);
        $modinfo = get_fast_modinfo($courseid);
        $out = [];
        foreach ($modinfo->get_cms() as $cm) {
            if (empty($cm->uservisible)) {
                continue;
            }
            if (!in_array($cm->modname, self::SUPPORTED, true)) {
                continue;
            }
            $out[(int) $cm->id] = $cm->modplural . ': ' . format_string($cm->name);
        }
        return $out;
    }

    /**
     * Extract text from a page activity.
     *
     * @param int $instanceid mod_page row id.
     * @return string
     */
    private static function extract_page(int $instanceid): string {
        global $DB;
        $row = $DB->get_record('page', ['id' => $instanceid], 'intro, content', MUST_EXIST);
        return self::html_to_text((string) $row->intro . "\n\n" . (string) $row->content);
    }

    /**
     * Extract text from a book activity.
     *
     * @param int $instanceid mod_book row id.
     * @return string
     */
    private static function extract_book(int $instanceid): string {
        global $DB;
        $chapters = $DB->get_records(
            'book_chapters',
            ['bookid' => $instanceid, 'hidden' => 0],
            'pagenum ASC, id ASC',
            'id, title, content'
        );
        $parts = [];
        foreach ($chapters as $chapter) {
            $parts[] = '# ' . trim((string) $chapter->title) . "\n\n"
                . self::html_to_text((string) $chapter->content);
        }
        return trim(implode("\n\n", $parts));
    }

    /**
     * Extract text from a label activity.
     *
     * @param int $instanceid mod_label row id.
     * @return string
     */
    private static function extract_label(int $instanceid): string {
        global $DB;
        $row = $DB->get_record('label', ['id' => $instanceid], 'intro', MUST_EXIST);
        return self::html_to_text((string) $row->intro);
    }

    /**
     * Extract text from a file-based activity.
     *
     * @param \cm_info|\stdClass $cm
     * @return string
     */
    private static function extract_resource($cm): string {
        $context = context_module::instance((int) $cm->id);
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_resource', 'content', 0, 'sortorder ASC, id ASC', false);
        return self::files_to_text(array_values($files));
    }

    /**
     * Extract text from a file-based activity.
     *
     * @param \cm_info|\stdClass $cm
     * @return string
     */
    private static function extract_folder($cm): string {
        $context = context_module::instance((int) $cm->id);
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_folder', 'content', 0, 'sortorder ASC, id ASC', false);
        return self::files_to_text(array_values($files));
    }

    /**
     * Extract text from a lesson activity.
     *
     * @param int $instanceid mod_lesson row id.
     * @return string
     */
    private static function extract_lesson(int $instanceid): string {
        global $DB;
        $pages = $DB->get_records(
            'lesson_pages',
            ['lessonid' => $instanceid],
            'id ASC',
            'id, title, contents'
        );
        $parts = [];
        foreach ($pages as $page) {
            $parts[] = '# ' . trim((string) $page->title) . "\n\n"
                . self::html_to_text((string) $page->contents);
        }
        return trim(implode("\n\n", $parts));
    }

    /**
     * Reduce a list of stored_files to concatenated text for the prompt.
     *
     * Plain text and HTML are read inline. Other MIME types are skipped
     * — Phase 2 hooks in image-to-text via a future core_ai action.
     *
     * @param stored_file[] $files
     * @return string
     */
    private static function files_to_text(array $files): string {
        $parts = [];
        foreach ($files as $file) {
            $mimetype = (string) $file->get_mimetype();
            if (str_starts_with($mimetype, 'text/html')) {
                $parts[] = self::html_to_text((string) $file->get_content());
            } else if (str_starts_with($mimetype, 'text/')) {
                $parts[] = trim((string) $file->get_content());
            }
            // Binary formats (PDF, docx, images) intentionally skipped
            // until Phase 2 wires in an image-to-text core_ai action.
        }
        return trim(implode("\n\n", $parts));
    }

    /**
     * Strip HTML and collapse whitespace for prompt economy.
     *
     * @param string $html
     * @return string
     */
    private static function html_to_text(string $html): string {
        $text = html_to_text($html, 0, false);
        // Collapse runs of blank lines.
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return trim((string) $text);
    }
}

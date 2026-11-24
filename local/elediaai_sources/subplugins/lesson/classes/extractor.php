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

namespace aisourcesextractor_lesson;

use local_elediaai_sources\content_extractor;

/**
 * Content extractor for mod_lesson activities.
 *
 * Extracts the lesson intro, all page contents in navigation order,
 * and answer/response texts for question pages. Structural pages
 * (cluster, end-of-branch, end-of-cluster) are skipped.
 *
 * In a graded lesson (not a practice lesson, grade other than none) the
 * question pages keep their question text but lose their answers and
 * responses (ingestionpipeline#23): the answers name the solution and the
 * responses ("Richtig! ...") say which one it is, and the index cannot tell
 * whether a learner is in the middle of the lesson. Content pages keep their
 * buttons, and practice or ungraded lessons stay learning material as before.
 *
 * @package    aisourcesextractor_lesson
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class extractor implements content_extractor {
    /** @var int[] Structural page types that carry no educational content. */
    private const STRUCTURAL_QTYPES = [21, 30, 31]; // End of branch, cluster, end of cluster.

    /** @var int Content page (branch table): its "answers" are navigation buttons. */
    private const CONTENT_QTYPE = 20;

    /**
     * Check whether this extractor supports the given module.
     *
     * @param \cm_info $cm The course module info.
     * @return bool True if this is a lesson module.
     */
    public function supports(\cm_info $cm): bool {
        return $cm->modname === 'lesson';
    }

    /**
     * Extract content from a lesson activity.
     *
     * Walks the doubly-linked list of lesson pages in navigation order
     * and combines all page contents, answer options, and feedback
     * responses into a single HTML document.
     *
     * @param \cm_info $cm The course module info.
     * @return array|null Extracted document data, or null if no content.
     */
    public function extract(\cm_info $cm): ?array {
        global $DB;

        $lesson = $DB->get_record('lesson', ['id' => $cm->instance], 'id, name, intro, practice, grade', MUST_EXIST);
        $graded = self::is_graded($lesson);
        $context = \core\context\module::instance($cm->id);

        // Get all pages for this lesson.
        $pages = $DB->get_records(
            'lesson_pages',
            ['lessonid' => $lesson->id],
            '',
            'id, prevpageid, nextpageid, qtype, title, contents',
        );

        if (empty($pages)) {
            // Lesson with only an intro is still worth sending.
            if (empty($lesson->intro)) {
                return null;
            }
            $introhtml = file_rewrite_pluginfile_urls(
                $lesson->intro,
                'pluginfile.php',
                $context->id,
                'mod_lesson',
                'intro',
                0,
            );
            return [
                'content' => $introhtml,
                'content_type' => 'text/html',
                'title' => $lesson->name,
            ];
        }

        // Walk the doubly-linked list from the first page (prevpageid == 0).
        $pagemap = [];
        foreach ($pages as $p) {
            $pagemap[$p->id] = $p;
        }

        $ordered = [];
        $first = null;
        foreach ($pagemap as $p) {
            if ((int) $p->prevpageid === 0) {
                $first = $p;
                break;
            }
        }

        if ($first) {
            $current = $first;
            $seen = [];
            while ($current && !isset($seen[$current->id])) {
                $seen[$current->id] = true;
                $ordered[] = $current;
                $nextid = (int) $current->nextpageid;
                $current = ($nextid > 0 && isset($pagemap[$nextid])) ? $pagemap[$nextid] : null;
            }
        } else {
            // Fallback: use all pages if linked-list is broken.
            $ordered = array_values($pagemap);
        }

        // Pre-fetch all answers for this lesson, indexed by pageid.
        $allanswers = $DB->get_records(
            'lesson_answers',
            ['lessonid' => $lesson->id],
            'pageid ASC, id ASC',
            'id, pageid, answer, response',
        );
        $answersbypage = [];
        foreach ($allanswers as $a) {
            $answersbypage[$a->pageid][] = $a;
        }

        // Build the HTML document.
        $html = '';

        if (!empty($lesson->intro)) {
            $html .= file_rewrite_pluginfile_urls(
                $lesson->intro,
                'pluginfile.php',
                $context->id,
                'mod_lesson',
                'intro',
                0,
            );
        }

        foreach ($ordered as $page) {
            // Skip structural pages.
            if (in_array((int) $page->qtype, self::STRUCTURAL_QTYPES, true)) {
                continue;
            }

            $html .= '<h2>' . htmlspecialchars($page->title, ENT_QUOTES, 'UTF-8') . '</h2>' . "\n";

            if (!empty($page->contents)) {
                $html .= file_rewrite_pluginfile_urls(
                    $page->contents,
                    'pluginfile.php',
                    $context->id,
                    'mod_lesson',
                    'page_contents',
                    $page->id,
                ) . "\n";
            }

            // A graded lesson's question page keeps its question, never its
            // answers or responses: those are the solution.
            if ($graded && (int) $page->qtype !== self::CONTENT_QTYPE) {
                continue;
            }

            // Include answer and response texts for question pages.
            if (!empty($answersbypage[$page->id])) {
                foreach ($answersbypage[$page->id] as $answer) {
                    if (!empty($answer->answer)) {
                        $html .= '<p>' . file_rewrite_pluginfile_urls(
                            $answer->answer,
                            'pluginfile.php',
                            $context->id,
                            'mod_lesson',
                            'page_answers',
                            $page->id,
                        ) . '</p>' . "\n";
                    }
                    if (!empty($answer->response)) {
                        $html .= '<p>' . file_rewrite_pluginfile_urls(
                            $answer->response,
                            'pluginfile.php',
                            $context->id,
                            'mod_lesson',
                            'page_responses',
                            $page->id,
                        ) . '</p>' . "\n";
                    }
                }
            }
        }

        if (empty($html)) {
            return null;
        }

        return [
            'content' => $html,
            'content_type' => 'text/html',
            'title' => $lesson->name,
        ];
    }

    /**
     * Whether the lesson counts towards the grade.
     *
     * Mirrors mod_lesson: a practice lesson is never graded, and a grade of 0
     * means "none". A negative grade is a scale and graded as well.
     *
     * @param \stdClass $lesson Lesson record with practice and grade.
     * @return bool True for a graded lesson.
     */
    public static function is_graded(\stdClass $lesson): bool {
        return (int) $lesson->practice === 0 && (int) $lesson->grade !== 0;
    }
}

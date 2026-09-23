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

namespace aisourcesextractor_quiz;

use local_elediaai_sources\content_extractor;

/**
 * Content extractor for mod_quiz activities.
 *
 * Extracts the quiz intro, all question texts with their answer options
 * and general feedback, and overall quiz feedback bands. Random question
 * slots are skipped because they have no fixed question content.
 *
 * @package    aisourcesextractor_quiz
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class extractor implements content_extractor {
    /**
     * Check whether this extractor supports the given module.
     *
     * @param \cm_info $cm The course module info.
     * @return bool True if this is a quiz module.
     */
    public function supports(\cm_info $cm): bool {
        return $cm->modname === 'quiz';
    }

    /**
     * Extract content from a quiz activity.
     *
     * Resolves quiz slots through the question bank reference chain to
     * retrieve the current (latest non-draft) version of each question,
     * its answer options, and feedback. Random slots are excluded.
     *
     * @param \cm_info $cm The course module info.
     * @return array|null Extracted document data, or null if no content.
     */
    public function extract(\cm_info $cm): ?array {
        global $DB;

        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], 'id, name, intro', MUST_EXIST);
        $context = \core\context\module::instance($cm->id);

        $html = '';

        // Quiz intro.
        if (!empty($quiz->intro)) {
            $html .= file_rewrite_pluginfile_urls(
                $quiz->intro,
                'pluginfile.php',
                $context->id,
                'mod_quiz',
                'intro',
                0,
            );
        }

        // Resolve quiz slots → question references → question bank entries → versions → questions.
        // Random question slots use question_set_references rather than question_references,
        // so they are naturally excluded from this join.
        $sql = "SELECT slot.slot,
                       slot.id AS slotid,
                       q.id AS questionid,
                       q.name AS questionname,
                       q.questiontext,
                       q.generalfeedback,
                       q.qtype
                  FROM {quiz_slots} slot
                  JOIN {question_references} qr
                       ON qr.usingcontextid = :contextid
                      AND qr.component = 'mod_quiz'
                      AND qr.questionarea = 'slot'
                      AND qr.itemid = slot.id
                  JOIN {question_bank_entries} qbe
                       ON qbe.id = qr.questionbankentryid
                  JOIN {question_versions} qv
                       ON qv.questionbankentryid = qbe.id
                      AND qv.version = COALESCE(
                          qr.version,
                          (SELECT MAX(v.version)
                             FROM {question_versions} v
                            WHERE v.questionbankentryid = qbe.id
                              AND v.status <> 'draft')
                      )
                  JOIN {question} q
                       ON q.id = qv.questionid
                 WHERE slot.quizid = :quizid
              ORDER BY slot.slot";

        $questions = $DB->get_records_sql($sql, [
            'contextid' => $context->id,
            'quizid' => $quiz->id,
        ]);

        if (!empty($questions)) {
            // Pre-fetch all answers for resolved questions.
            $questionids = array_column((array) $questions, 'questionid');
            [$insql, $inparams] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);
            $allanswers = $DB->get_records_select(
                'question_answers',
                "question {$insql}",
                $inparams,
                'question ASC, id ASC',
            );
            $answersbyq = [];
            foreach ($allanswers as $a) {
                $answersbyq[$a->question][] = $a;
            }

            // Pre-fetch question hints (pedagogical scaffolding shown on retries).
            $hintsbyq = [];
            $allhints = $DB->get_records_select(
                'question_hints',
                "questionid {$insql}",
                $inparams,
                'questionid ASC, id ASC',
                'id, questionid, hint',
            );
            foreach ($allhints as $h) {
                if (trim((string) $h->hint) !== '') {
                    $hintsbyq[$h->questionid][] = $h->hint;
                }
            }

            foreach ($questions as $q) {
                $html .= '<h3>' . htmlspecialchars($q->questionname, ENT_QUOTES, 'UTF-8') . '</h3>' . "\n";

                if (!empty($q->questiontext)) {
                    $html .= self::safe_html((string) $q->questiontext, $context) . "\n";
                }

                // Include answer options.
                if (!empty($answersbyq[$q->questionid])) {
                    $html .= '<ul>' . "\n";
                    foreach ($answersbyq[$q->questionid] as $answer) {
                        if (!empty($answer->answer)) {
                            $html .= '<li>' . self::safe_html((string) $answer->answer, $context) . '</li>' . "\n";
                        }
                        if (!empty($answer->feedback)) {
                            $html .= '<p><em>' . self::plain_text((string) $answer->feedback) . '</em></p>' . "\n";
                        }
                    }
                    $html .= '</ul>' . "\n";
                }

                // General feedback.
                if (!empty($q->generalfeedback)) {
                    $html .= '<p>' . self::safe_html((string) $q->generalfeedback, $context) . '</p>' . "\n";
                }

                // Question hints.
                if (!empty($hintsbyq[$q->questionid])) {
                    $hintlabel = get_string('questionhint', 'local_elediaai_sources');
                    foreach ($hintsbyq[$q->questionid] as $hint) {
                        $html .= '<p><em>' . htmlspecialchars($hintlabel, ENT_QUOTES, 'UTF-8')
                            . ' ' . self::plain_text((string) $hint) . '</em></p>' . "\n";
                    }
                }
            }
        }

        // Quiz overall feedback bands.
        $feedbacks = $DB->get_records('quiz_feedback', ['quizid' => $quiz->id], 'mingrade ASC', 'id, feedbacktext');
        foreach ($feedbacks as $fb) {
            if (!empty($fb->feedbacktext)) {
                $html .= file_rewrite_pluginfile_urls(
                    $fb->feedbacktext,
                    'pluginfile.php',
                    $context->id,
                    'mod_quiz',
                    'feedback',
                    $fb->id,
                ) . "\n";
            }
        }

        if (empty($html)) {
            return null;
        }

        return [
            'content' => $html,
            'content_type' => 'text/html',
            'title' => $quiz->name,
        ];
    }

    /**
     * Clean editor HTML before sending it to the external index.
     *
     * @param string $html Stored HTML.
     * @param \context $context Formatting context.
     * @return string Clean HTML.
     */
    private static function safe_html(string $html, \context $context): string {
        return format_text($html, FORMAT_HTML, ['context' => $context, 'filter' => false, 'noclean' => false]);
    }

    /**
     * Convert an editor field to escaped plain text for inline contexts.
     *
     * @param string $html Stored HTML or text.
     * @return string HTML-safe text.
     */
    private static function plain_text(string $html): string {
        $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

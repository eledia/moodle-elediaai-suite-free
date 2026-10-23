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
 * Unit tests for the quiz content extractor.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aisourcesextractor_quiz\extractor
 */
final class extractor_quiz_test extends \advanced_testcase {
    /**
     * A quiz with one multiple-choice question whose answers carry feedback.
     *
     * @return \cm_info
     */
    private function quiz_with_question(): \cm_info {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'name' => 'Statistik-Quiz',
        ]);

        /** @var \core_question_generator $questions */
        $questions = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questions->create_question_category([
            'contextid' => \context_module::instance($quiz->cmid)->id,
        ]);
        $question = $questions->create_question('multichoice', 'one_of_four', ['category' => $category->id]);
        quiz_add_quiz_question($question->id, $quiz);

        return get_fast_modinfo($course->id)->get_cm($quiz->cmid);
    }

    /**
     * The extractor answers for quizzes.
     */
    public function test_supports_quiz(): void {
        $this->resetAfterTest();
        $cm = $this->quiz_with_question();
        $this->assertTrue((new \aisourcesextractor_quiz\extractor())->supports($cm));
    }

    /**
     * Answer feedback sits inside its answer's list item, not loose in the list (#14).
     */
    public function test_answer_feedback_stays_inside_its_list_item(): void {
        $this->resetAfterTest();
        $cm = $this->quiz_with_question();

        $result = (new \aisourcesextractor_quiz\extractor())->extract($cm);
        $this->assertNotNull($result);

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><body>' . $result['content'] . '</body>');
        libxml_clear_errors();

        $lists = $dom->getElementsByTagName('ul');
        $this->assertGreaterThan(0, $lists->length, 'The answers are listed.');
        $feedbackinitem = 0;
        foreach ($lists as $list) {
            foreach ($list->childNodes as $child) {
                if ($child instanceof \DOMElement) {
                    $this->assertSame('li', $child->tagName, 'Only list items belong directly in a <ul>.');
                    if ($child->getElementsByTagName('em')->length > 0) {
                        $feedbackinitem++;
                    }
                }
            }
        }
        $this->assertGreaterThan(0, $feedbackinitem, 'The answer feedback travels with its answer.');
    }
}

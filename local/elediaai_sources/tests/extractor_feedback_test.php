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
 * Unit tests for the feedback content extractor.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aisourcesextractor_feedback\extractor
 */
final class extractor_feedback_test extends \advanced_testcase {
    /**
     * Test that the feedback extractor supports feedback modules.
     */
    public function test_supports_feedback(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $feedback = $this->getDataGenerator()->create_module('feedback', [
            'course' => $course->id,
        ]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($feedback->cmid);

        $extractor = new \aisourcesextractor_feedback\extractor();
        $this->assertTrue($extractor->supports($cm));
    }

    /**
     * Test that the feedback extractor does not support page modules.
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

        $extractor = new \aisourcesextractor_feedback\extractor();
        $this->assertFalse($extractor->supports($cm));
    }

    /**
     * Test extracting content from a feedback with questions.
     */
    public function test_extract_returns_html_with_items(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $feedback = $this->getDataGenerator()->create_module('feedback', [
            'course' => $course->id,
            'name' => 'Course Evaluation',
            'intro' => '<p>Please complete this evaluation.</p>',
        ]);

        // Create feedback items directly in the DB.
        $DB->insert_record('feedback_item', [
            'feedback' => $feedback->id,
            'template' => 0,
            'name' => 'How would you rate the course?',
            'label' => '',
            'presentation' => 'r>Excellent|Good|Average|Poor',
            'typ' => 'multichoice',
            'hasvalue' => 1,
            'position' => 1,
            'required' => 0,
            'dependitem' => 0,
            'dependvalue' => '',
            'options' => '',
        ]);

        $DB->insert_record('feedback_item', [
            'feedback' => $feedback->id,
            'template' => 0,
            'name' => 'Any additional comments?',
            'label' => '',
            'presentation' => '30|5',
            'typ' => 'textarea',
            'hasvalue' => 1,
            'position' => 2,
            'required' => 0,
            'dependitem' => 0,
            'dependvalue' => '',
            'options' => '',
        ]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($feedback->cmid);

        $extractor = new \aisourcesextractor_feedback\extractor();
        $result = $extractor->extract($cm);

        $this->assertNotNull($result);
        $this->assertEquals('text/html', $result['content_type']);
        $this->assertEquals('Course Evaluation', $result['title']);
        $this->assertStringContainsString('Please complete this evaluation.', $result['content']);
        $this->assertStringContainsString('How would you rate the course?', $result['content']);
        $this->assertStringContainsString('Excellent', $result['content']);
        $this->assertStringContainsString('Good', $result['content']);
        $this->assertStringContainsString('Any additional comments?', $result['content']);
    }

    /**
     * Test that pagebreak items are skipped.
     */
    public function test_extract_skips_pagebreaks(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $feedback = $this->getDataGenerator()->create_module('feedback', [
            'course' => $course->id,
            'name' => 'Only Pagebreak',
        ]);

        // Generator ignores empty intro — force-clear it and page_after_submit.
        $DB->set_field('feedback', 'intro', '', ['id' => $feedback->id]);
        $DB->set_field('feedback', 'page_after_submit', '', ['id' => $feedback->id]);

        $DB->insert_record('feedback_item', [
            'feedback' => $feedback->id,
            'template' => 0,
            'name' => 'pagebreak',
            'label' => '',
            'presentation' => '',
            'typ' => 'pagebreak',
            'hasvalue' => 0,
            'position' => 1,
            'required' => 0,
            'dependitem' => 0,
            'dependvalue' => '',
            'options' => '',
        ]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($feedback->cmid);

        $extractor = new \aisourcesextractor_feedback\extractor();
        $result = $extractor->extract($cm);

        // Only a pagebreak and no intro → null.
        $this->assertNull($result);
    }

    /**
     * Test that extraction returns null for an empty feedback.
     */
    public function test_extract_returns_null_for_empty_feedback(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $feedback = $this->getDataGenerator()->create_module('feedback', [
            'course' => $course->id,
        ]);

        // Generator ignores empty intro — force-clear it and page_after_submit.
        $DB->set_field('feedback', 'intro', '', ['id' => $feedback->id]);
        $DB->set_field('feedback', 'page_after_submit', '', ['id' => $feedback->id]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($feedback->cmid);

        $extractor = new \aisourcesextractor_feedback\extractor();
        $result = $extractor->extract($cm);

        $this->assertNull($result);
    }

    /**
     * Test that label items output their presentation as HTML.
     */
    public function test_extract_includes_label_items(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $feedback = $this->getDataGenerator()->create_module('feedback', [
            'course' => $course->id,
            'name' => 'Feedback With Label',
            'intro' => '',
        ]);

        $DB->insert_record('feedback_item', [
            'feedback' => $feedback->id,
            'template' => 0,
            'name' => 'label',
            'label' => '',
            'presentation' => '<p>Please read the instructions carefully before answering.</p>',
            'typ' => 'label',
            'hasvalue' => 0,
            'position' => 1,
            'required' => 0,
            'dependitem' => 0,
            'dependvalue' => '',
            'options' => '',
        ]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($feedback->cmid);

        $extractor = new \aisourcesextractor_feedback\extractor();
        $result = $extractor->extract($cm);

        $this->assertNotNull($result);
        $this->assertStringContainsString('Please read the instructions carefully', $result['content']);
    }
}

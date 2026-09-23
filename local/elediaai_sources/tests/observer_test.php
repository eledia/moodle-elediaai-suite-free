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
 * Unit tests for the event observer class.
 *
 * Verifies that course module lifecycle events correctly queue ad-hoc tasks.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_sources\observer
 */
final class observer_test extends \advanced_testcase {
    /**
     * Test that creating a course module queues an ingestion task.
     */
    public function test_course_module_created_queues_task(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();

        // Count adhoc tasks before.
        $countbefore = $DB->count_records('task_adhoc', [
            'classname' => '\\local_elediaai_sources\\task\\ingest_module_task',
        ]);

        // Create a page — this triggers course_module_created event.
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Test</p>',
        ]);

        $countafter = $DB->count_records('task_adhoc', [
            'classname' => '\\local_elediaai_sources\\task\\ingest_module_task',
        ]);

        $this->assertGreaterThan(
            $countbefore,
            $countafter,
            'An ingestion ad-hoc task should have been queued.'
        );
    }

    /**
     * Test that updating a course module queues an ingestion task.
     */
    public function test_course_module_updated_queues_task(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Original</p>',
        ]);

        // Clear any tasks from creation.
        $DB->delete_records('task_adhoc', [
            'classname' => '\\local_elediaai_sources\\task\\ingest_module_task',
        ]);

        // Trigger update event.
        $event = \core\event\course_module_updated::create([
            'objectid' => $page->cmid,
            'courseid' => $course->id,
            'context' => \core\context\module::instance($page->cmid),
            'other' => [
                'modulename' => 'page',
                'instanceid' => $page->id,
                'name' => 'Test Page',
            ],
        ]);
        $event->trigger();

        $count = $DB->count_records('task_adhoc', [
            'classname' => '\\local_elediaai_sources\\task\\ingest_module_task',
        ]);

        $this->assertGreaterThanOrEqual(
            1,
            $count,
            'An ingestion ad-hoc task should have been queued on update.'
        );
    }

    /**
     * Test that deleting a course module queues a deletion task.
     */
    public function test_course_module_deleted_queues_deletion_task(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Will be deleted</p>',
        ]);

        // Trigger delete event.
        $event = \core\event\course_module_deleted::create([
            'objectid' => $page->cmid,
            'courseid' => $course->id,
            'context' => \core\context\course::instance($course->id),
            'other' => [
                'modulename' => 'page',
                'instanceid' => $page->id,
            ],
        ]);
        $event->trigger();

        $count = $DB->count_records('task_adhoc', [
            'classname' => '\\local_elediaai_sources\\task\\delete_module_task',
        ]);

        $this->assertGreaterThanOrEqual(
            1,
            $count,
            'A deletion ad-hoc task should have been queued.'
        );
    }

    /**
     * Test that updating a book chapter queues an ingestion task for the book.
     */
    public function test_book_chapter_updated_queues_task(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $book = $this->getDataGenerator()->create_module('book', [
            'course' => $course->id,
        ]);

        // Create a chapter via the generator.
        $bookgenerator = $this->getDataGenerator()->get_plugin_generator('mod_book');
        $chapter = $bookgenerator->create_chapter([
            'bookid' => $book->id,
            'title' => 'Test Chapter',
            'content' => '<p>Original content</p>',
        ]);

        // Clear any tasks from creation.
        $DB->delete_records('task_adhoc', [
            'classname' => '\\local_elediaai_sources\\task\\ingest_module_task',
        ]);

        // Trigger chapter_updated event.
        $context = \core\context\module::instance($book->cmid);
        $bookrecord = $DB->get_record('book', ['id' => $book->id]);
        $event = \mod_book\event\chapter_updated::create_from_chapter($bookrecord, $context, $chapter);
        $event->trigger();

        $count = $DB->count_records('task_adhoc', [
            'classname' => '\\local_elediaai_sources\\task\\ingest_module_task',
        ]);

        $this->assertGreaterThanOrEqual(
            1,
            $count,
            'An ingestion ad-hoc task should have been queued when a book chapter is updated.'
        );

        // Verify the task has the book's cmid, not the chapter id.
        $tasks = $DB->get_records('task_adhoc', [
            'classname' => '\\local_elediaai_sources\\task\\ingest_module_task',
        ]);
        $task = reset($tasks);
        $data = json_decode($task->customdata);
        $this->assertEquals($book->cmid, $data->cmid);
    }

    /**
     * Test that creating a book chapter queues an ingestion task.
     */
    public function test_book_chapter_created_queues_task(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $book = $this->getDataGenerator()->create_module('book', [
            'course' => $course->id,
        ]);

        // Clear any tasks from book creation.
        $DB->delete_records('task_adhoc', [
            'classname' => '\\local_elediaai_sources\\task\\ingest_module_task',
        ]);

        // Create a real chapter so the event's record snapshot carries the
        // full book_chapters schema (a hand-built partial record would trip
        // the snapshot-field integrity check in debug developer mode).
        $bookgenerator = $this->getDataGenerator()->get_plugin_generator('mod_book');
        $chapter = $bookgenerator->create_chapter([
            'bookid' => $book->id,
            'title' => 'New Chapter',
        ]);

        // Trigger chapter_created event.
        $context = \core\context\module::instance($book->cmid);
        $bookrecord = $DB->get_record('book', ['id' => $book->id]);
        $event = \mod_book\event\chapter_created::create_from_chapter($bookrecord, $context, $chapter);
        $event->trigger();

        $count = $DB->count_records('task_adhoc', [
            'classname' => '\\local_elediaai_sources\\task\\ingest_module_task',
        ]);

        $this->assertGreaterThanOrEqual(
            1,
            $count,
            'An ingestion ad-hoc task should have been queued when a book chapter is created.'
        );
    }

    /**
     * Test that updating a glossary entry queues an ingestion task for the glossary.
     */
    public function test_glossary_entry_updated_queues_task(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $glossary = $this->getDataGenerator()->create_module('glossary', [
            'course' => $course->id,
        ]);

        // Clear any tasks from creation.
        $DB->delete_records('task_adhoc', [
            'classname' => '\\local_elediaai_sources\\task\\ingest_module_task',
        ]);

        // Trigger entry_updated event.
        $context = \core\context\module::instance($glossary->cmid);
        $event = \mod_glossary\event\entry_updated::create([
            'context' => $context,
            'objectid' => 123,
            'other' => ['concept' => 'Test Term'],
        ]);
        $event->trigger();

        $count = $DB->count_records('task_adhoc', [
            'classname' => '\\local_elediaai_sources\\task\\ingest_module_task',
        ]);

        $this->assertGreaterThanOrEqual(
            1,
            $count,
            'An ingestion ad-hoc task should have been queued when a glossary entry is updated.'
        );

        // Verify the task has the glossary's cmid.
        $tasks = $DB->get_records('task_adhoc', [
            'classname' => '\\local_elediaai_sources\\task\\ingest_module_task',
        ]);
        $task = reset($tasks);
        $data = json_decode($task->customdata);
        $this->assertEquals($glossary->cmid, $data->cmid);
    }

    /**
     * Test that creating a database record queues a re-ingest for the activity.
     */
    public function test_data_record_created_queues_task(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $data = $this->getDataGenerator()->create_module('data', ['course' => $course->id]);

        $DB->delete_records('task_adhoc', [
            'classname' => '\\local_elediaai_sources\\task\\ingest_module_task',
        ]);

        // Record_created fires in the module context.
        $recordid = $DB->insert_record('data_records', (object) [
            'dataid' => $data->id, 'userid' => $USER->id,
            'timecreated' => time(), 'timemodified' => time(), 'approved' => 1,
        ]);
        \mod_data\event\record_created::create([
            'objectid' => $recordid,
            'context' => \core\context\module::instance($data->cmid),
            'courseid' => $course->id,
            'other' => ['dataid' => $data->id],
        ])->trigger();

        $tasks = $DB->get_records('task_adhoc', [
            'classname' => '\\local_elediaai_sources\\task\\ingest_module_task',
        ]);
        $this->assertGreaterThanOrEqual(1, count($tasks));
        $this->assertEquals($data->cmid, json_decode(reset($tasks)->customdata)->cmid);
    }

    /**
     * Test that adding a question to a quiz (slot_created) re-ingests the quiz.
     */
    public function test_quiz_slot_created_queues_task(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        $DB->delete_records('task_adhoc', [
            'classname' => '\\local_elediaai_sources\\task\\ingest_module_task',
        ]);

        \mod_quiz\event\slot_created::create([
            'objectid' => 1,
            'context' => \core\context\module::instance($quiz->cmid),
            'courseid' => $course->id,
            'other' => [
                'quizid' => $quiz->id,
                'slotnumber' => 1,
                'page' => 1,
                'questionbankentryid' => 1,
                'version' => null,
            ],
        ])->trigger();

        $tasks = $DB->get_records('task_adhoc', [
            'classname' => '\\local_elediaai_sources\\task\\ingest_module_task',
        ]);
        $this->assertGreaterThanOrEqual(1, count($tasks));
        $this->assertEquals($quiz->cmid, json_decode(reset($tasks)->customdata)->cmid);
    }

    /**
     * Test that editing a question re-ingests every quiz that references it.
     */
    public function test_question_updated_reingest_referencing_quiz(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        // Build a question in the quiz's context and add it to the quiz.
        $qgen = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $qgen->create_question_category([
            'contextid' => \core\context\module::instance($quiz->cmid)->id,
        ]);
        $question = $qgen->create_question('truefalse', null, ['category' => $cat->id]);
        quiz_add_quiz_question($question->id, $quiz);

        $DB->delete_records('task_adhoc', [
            'classname' => '\\local_elediaai_sources\\task\\ingest_module_task',
        ]);

        // Editing the question fires question_updated; the bank entry maps back
        // to the quiz slot that references it.
        \core\event\question_updated::create_from_question_instance(
            \question_bank::load_question_data($question->id),
            \core\context\module::instance($quiz->cmid),
        )->trigger();

        $tasks = $DB->get_records('task_adhoc', [
            'classname' => '\\local_elediaai_sources\\task\\ingest_module_task',
        ]);
        $cmids = array_map(static fn($t) => json_decode($t->customdata)->cmid, $tasks);
        $this->assertContains(
            (int) $quiz->cmid,
            array_map('intval', $cmids),
            'Editing a question should re-ingest the quiz that references it.'
        );
    }

    /**
     * Test that marking a course queues its reconcile straight away.
     *
     * The plan lists "ad-hoc task when a course is marked" as missing, assuming
     * a freshly marked course waits for the next scheduled reconcile. It does
     * not: saving the course fires course_updated, which this observer already
     * turns into a reconcile_course_task. The test pins that down, because the
     * failure it guards against is silent — drop the registration and marking
     * degrades to "indexed some time tonight" without any error.
     */
    public function test_marking_a_course_queues_its_reconcile(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // The per-course override only counts while marking is unlocked.
        set_config('lockcoursemarking', 0, 'local_elediaai_sources');
        setup::ensure_course_field();

        $course = $this->getDataGenerator()->create_course();

        // Ignore whatever course creation itself queued.
        $DB->delete_records('task_adhoc', [
            'classname' => '\\local_elediaai_sources\\task\\reconcile_course_task',
        ]);

        // Mark the course the way saving the course edit form does.
        update_course((object) [
            'id' => $course->id,
            'customfield_' . course_gate::FIELD => $this->include_option_index(),
        ]);

        $this->assertTrue(
            course_gate::should_ingest((int) $course->id),
            'The marking must have been stored, otherwise the queue check proves nothing.'
        );
        $this->assertGreaterThan(
            0,
            $DB->count_records('task_adhoc', [
                'classname' => '\\local_elediaai_sources\\task\\reconcile_course_task',
            ]),
            'Marking a course must queue its reconcile immediately, not wait for the nightly run.'
        );
    }

    /**
     * Test that deleting a course clears its documents and its state row.
     *
     * AC-3 asks that deleting a course empties the index, but no observer does
     * that wholesale: course_deleted only forgets the state row, and the
     * documents are expected to leave through the per-module
     * course_module_deleted events that fire while the course is torn down.
     * That expectation carries AC-3 and was never asserted — if those
     * per-module events stop arriving, the course disappears from Moodle while
     * its content stays in the index, and nothing reports it.
     */
    public function test_deleting_a_course_clears_documents_and_state(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Test</p>',
        ]);
        course_state::set_ingested((int) $course->id, true);

        $DB->delete_records('task_adhoc', [
            'classname' => '\\local_elediaai_sources\\task\\delete_module_task',
        ]);

        delete_course($course->id, false);

        $this->assertFalse(
            $DB->record_exists('local_elediaai_sources_course', ['courseid' => $course->id]),
            'Deleting a course must forget its state row.'
        );

        $tasks = $DB->get_records('task_adhoc', [
            'classname' => '\\local_elediaai_sources\\task\\delete_module_task',
        ]);
        $cmids = array_map(static fn($t) => (int) json_decode($t->customdata)->cmid, $tasks);
        $this->assertContains(
            (int) $page->cmid,
            $cmids,
            'Deleting a course must queue removal of its modules from the index.'
        );
    }

    /**
     * Test that deleting a module forgets its activity decision.
     *
     * The event is triggered directly (like the queueing test above does):
     * the 5.2 deletion API (cmactions::delete) does not exist in 4.5, and the
     * observer reacts to the event either way.
     */
    public function test_deleting_a_module_forgets_its_decision(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Test</p>',
        ]);
        activity_gate::set_included((int) $course->id, (int) $page->cmid, false);

        \core\event\course_module_deleted::create([
            'objectid' => $page->cmid,
            'courseid' => $course->id,
            'context' => \core\context\course::instance($course->id),
            'other' => [
                'modulename' => 'page',
                'instanceid' => $page->id,
            ],
        ])->trigger();

        $this->assertFalse(
            $DB->record_exists('local_elediaai_sources_cm', ['cmid' => $page->cmid]),
            'Deleting a module must remove its decision row.'
        );
    }

    /**
     * Test that deleting a course forgets all its activity decisions.
     */
    public function test_deleting_a_course_forgets_its_decisions(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Test</p>',
        ]);
        activity_gate::set_included((int) $course->id, (int) $page->cmid, false);
        cm_state::record_success((int) $course->id, (int) $page->cmid, 'sid', 'h', 'recording');

        delete_course($course->id, false);

        $this->assertFalse(
            $DB->record_exists('local_elediaai_sources_cm', ['courseid' => $course->id]),
            'Deleting a course must remove all its decision rows.'
        );
        $this->assertFalse(
            $DB->record_exists('local_elediaai_sources_cmstate', ['courseid' => $course->id]),
            'Deleting a course must remove all its state rows.'
        );
    }

    /**
     * The select index that stores "Include" in the course override field.
     *
     * A customfield_select persists the 1-based position of the chosen option,
     * not its text, so the position is read back from the field configuration
     * instead of being hard-coded to the current option order.
     *
     * @return int The 1-based option index.
     */
    private function include_option_index(): int {
        $handler = \core_course\customfield\course_handler::create();

        foreach ($handler->get_fields() as $field) {
            if ($field->get('shortname') !== course_gate::FIELD) {
                continue;
            }
            $options = explode("\n", (string) $field->get_configdata_property('options'));
            $index = array_search(course_gate::OVERRIDE_INCLUDE, array_map('trim', $options), true);
            if ($index !== false) {
                return (int) $index + 1;
            }
        }

        $this->fail('The course override custom field is missing its Include option.');
    }
}

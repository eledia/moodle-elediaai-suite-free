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
 * Event observer for course module lifecycle events.
 *
 * Queues ad-hoc tasks to ingest or delete content asynchronously via cron,
 * ensuring that module create/update/delete operations are never blocked
 * by RAG API calls.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Handle course module created event.
     *
     * Queues an ad-hoc ingestion task for the new module.
     *
     * @param \core\event\course_module_created $event The event.
     */
    public static function course_module_created(\core\event\course_module_created $event): void {
        self::queue_ingestion($event->courseid, $event->objectid);
    }

    /**
     * Handle course module updated event.
     *
     * Queues an ad-hoc ingestion task to re-ingest the updated module.
     *
     * @param \core\event\course_module_updated $event The event.
     */
    public static function course_module_updated(\core\event\course_module_updated $event): void {
        self::queue_ingestion($event->courseid, $event->objectid);
    }

    /**
     * Handle course module deleted event.
     *
     * Queues an ad-hoc deletion task to remove the module from the RAG index.
     *
     * @param \core\event\course_module_deleted $event The event.
     */
    public static function course_module_deleted(\core\event\course_module_deleted $event): void {
        activity_gate::forget_cm((int) $event->objectid);

        $task = new task\delete_module_task();
        $task->set_custom_data([
            'courseid' => $event->courseid,
            'cmid' => $event->objectid,
        ]);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Handle book chapter created/updated/deleted events.
     *
     * Book chapters are sub-content of the book activity. When any
     * chapter changes, we re-ingest the entire book. The event's
     * context is the book's course module context.
     *
     * @param \core\event\base $event The chapter event.
     */
    public static function book_chapter_changed(\core\event\base $event): void {
        self::queue_ingestion($event->courseid, $event->contextinstanceid);
    }

    /**
     * Handle glossary entry created/updated/deleted events.
     *
     * Glossary entries are sub-content of the glossary activity. When
     * any entry changes, we re-ingest the entire glossary. The event's
     * context is the glossary's course module context.
     *
     * @param \core\event\base $event The entry event.
     */
    public static function glossary_entry_changed(\core\event\base $event): void {
        self::queue_ingestion($event->courseid, $event->contextinstanceid);
    }

    /**
     * Handle lesson page created/updated/deleted events.
     *
     * Lesson pages are sub-content of the lesson activity. When any
     * page changes, we re-ingest the entire lesson. The event's
     * context is the lesson's course module context.
     *
     * @param \core\event\base $event The page event.
     */
    public static function lesson_page_changed(\core\event\base $event): void {
        self::queue_ingestion($event->courseid, $event->contextinstanceid);
    }

    /**
     * Handle wiki page created/updated/deleted events.
     *
     * Wiki pages are sub-content of the wiki activity. When any page
     * changes, we re-ingest the entire wiki. The event's context is
     * the wiki's course module context.
     *
     * @param \core\event\base $event The page event.
     */
    public static function wiki_page_changed(\core\event\base $event): void {
        self::queue_ingestion($event->courseid, $event->contextinstanceid);
    }

    /**
     * Handle database (mod_data) record created/updated/deleted events.
     *
     * Approved records are sub-content of the database activity. When any
     * record changes, re-ingest the whole activity. The event context is the
     * database's course module context.
     *
     * @param \core\event\base $event The record event.
     */
    public static function data_record_changed(\core\event\base $event): void {
        self::queue_ingestion((int) $event->courseid, (int) $event->contextinstanceid);
    }

    /**
     * Handle quiz structure (slot) changes: a question added to, removed from,
     * reordered in, or re-versioned within a quiz.
     *
     * The event context is the quiz's course module context, so the quiz is
     * re-ingested directly.
     *
     * @param \core\event\base $event The slot event.
     */
    public static function quiz_structure_changed(\core\event\base $event): void {
        self::queue_ingestion((int) $event->courseid, (int) $event->contextinstanceid);
    }

    /**
     * Handle question-bank edits (a question's text/answers changed).
     *
     * A question may be shared by several quizzes, so this reverse-maps the
     * question's bank entry to every quiz that references it and re-ingests
     * each. Random-slot (category) references are intentionally not resolved —
     * the quiz extractor does not ingest random-slot question content.
     *
     * @param \core\event\base $event The question event.
     */
    public static function question_changed(\core\event\base $event): void {
        try {
            global $DB;

            $questionid = (int) $event->objectid;
            if ($questionid <= 0) {
                return;
            }

            // Resolve the bank entry this question version belongs to.
            $entryid = $DB->get_field(
                'question_versions',
                'questionbankentryid',
                ['questionid' => $questionid]
            );
            if (!$entryid) {
                return;
            }

            // Find every quiz course-module that references the entry through a slot.
            $sql = "SELECT DISTINCT cm.id AS cmid, cm.course AS courseid
                      FROM {question_references} qr
                      JOIN {quiz_slots} qs ON qs.id = qr.itemid
                      JOIN {quiz} q ON q.id = qs.quizid
                      JOIN {course_modules} cm ON cm.instance = q.id
                      JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                     WHERE qr.component = 'mod_quiz'
                       AND qr.questionarea = 'slot'
                       AND qr.questionbankentryid = :entryid";
            $rows = $DB->get_records_sql($sql, ['entryid' => $entryid]);

            foreach ($rows as $row) {
                self::queue_ingestion((int) $row->courseid, (int) $row->cmid);
            }
        } catch (\Throwable $e) {
            debugging('[local_elediaai_sources] question_changed observer error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Handle course created/updated: re-evaluate the course's ingestion marking
     * and reconcile the index (re-index on enable, purge on disable).
     *
     * @param \core\event\base $event The course event.
     */
    public static function course_changed(\core\event\base $event): void {
        $task = new task\reconcile_course_task();
        $task->set_custom_data(['courseid' => (int) $event->objectid]);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Handle course deleted: forget our per-course state row.
     *
     * The documents themselves are already scheduled for removal by
     * {@see hook_callbacks::before_course_deleted()}, which runs while the
     * course still exists. It has to: deleting a whole course does not fire
     * course_module_deleted per module, so {@see self::course_module_deleted()}
     * never sees them.
     *
     * @param \core\event\course_deleted $event The event.
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        course_state::forget((int) $event->objectid);
        activity_gate::forget_course((int) $event->objectid);
        cm_state::forget_course((int) $event->objectid);
    }

    /**
     * Queue an ingestion task for a course module.
     *
     * @param int $courseid The course ID.
     * @param int $cmid The course module ID.
     */
    private static function queue_ingestion(int $courseid, int $cmid): void {
        $task = new task\ingest_module_task();
        $task->set_custom_data([
            'courseid' => $courseid,
            'cmid' => $cmid,
        ]);
        \core\task\manager::queue_adhoc_task($task, true);
    }
}

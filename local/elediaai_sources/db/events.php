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
 * Event observer definitions for the AI Sources plugin.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\course_module_created',
        'callback' => '\local_elediaai_sources\observer::course_module_created',
    ],
    [
        'eventname' => '\core\event\course_module_updated',
        'callback' => '\local_elediaai_sources\observer::course_module_updated',
    ],
    [
        'eventname' => '\core\event\course_module_deleted',
        'callback' => '\local_elediaai_sources\observer::course_module_deleted',
    ],

    // Sub-content events: book chapters.
    [
        'eventname' => '\mod_book\event\chapter_created',
        'callback' => '\local_elediaai_sources\observer::book_chapter_changed',
    ],
    [
        'eventname' => '\mod_book\event\chapter_updated',
        'callback' => '\local_elediaai_sources\observer::book_chapter_changed',
    ],
    [
        'eventname' => '\mod_book\event\chapter_deleted',
        'callback' => '\local_elediaai_sources\observer::book_chapter_changed',
    ],

    // Sub-content events: glossary entries.
    [
        'eventname' => '\mod_glossary\event\entry_created',
        'callback' => '\local_elediaai_sources\observer::glossary_entry_changed',
    ],
    [
        'eventname' => '\mod_glossary\event\entry_updated',
        'callback' => '\local_elediaai_sources\observer::glossary_entry_changed',
    ],
    [
        'eventname' => '\mod_glossary\event\entry_deleted',
        'callback' => '\local_elediaai_sources\observer::glossary_entry_changed',
    ],

    // Sub-content events: lesson pages.
    [
        'eventname' => '\mod_lesson\event\page_created',
        'callback' => '\local_elediaai_sources\observer::lesson_page_changed',
    ],
    [
        'eventname' => '\mod_lesson\event\page_updated',
        'callback' => '\local_elediaai_sources\observer::lesson_page_changed',
    ],
    [
        'eventname' => '\mod_lesson\event\page_deleted',
        'callback' => '\local_elediaai_sources\observer::lesson_page_changed',
    ],

    // Sub-content events: wiki pages.
    [
        'eventname' => '\mod_wiki\event\page_created',
        'callback' => '\local_elediaai_sources\observer::wiki_page_changed',
    ],
    [
        'eventname' => '\mod_wiki\event\page_updated',
        'callback' => '\local_elediaai_sources\observer::wiki_page_changed',
    ],
    [
        'eventname' => '\mod_wiki\event\page_deleted',
        'callback' => '\local_elediaai_sources\observer::wiki_page_changed',
    ],

    // Sub-content events: database records (approved entries are indexed).
    [
        'eventname' => '\mod_data\event\record_created',
        'callback' => '\local_elediaai_sources\observer::data_record_changed',
    ],
    [
        'eventname' => '\mod_data\event\record_updated',
        'callback' => '\local_elediaai_sources\observer::data_record_changed',
    ],
    [
        'eventname' => '\mod_data\event\record_deleted',
        'callback' => '\local_elediaai_sources\observer::data_record_changed',
    ],

    // Sub-content events: quiz structure (question added/removed/reordered/re-versioned).
    [
        'eventname' => '\mod_quiz\event\slot_created',
        'callback' => '\local_elediaai_sources\observer::quiz_structure_changed',
    ],
    [
        'eventname' => '\mod_quiz\event\slot_deleted',
        'callback' => '\local_elediaai_sources\observer::quiz_structure_changed',
    ],
    [
        'eventname' => '\mod_quiz\event\slot_moved',
        'callback' => '\local_elediaai_sources\observer::quiz_structure_changed',
    ],
    [
        'eventname' => '\mod_quiz\event\slot_version_updated',
        'callback' => '\local_elediaai_sources\observer::quiz_structure_changed',
    ],

    // Question-bank edits: re-ingest every quiz that references the question.
    [
        'eventname' => '\core\event\question_updated',
        'callback' => '\local_elediaai_sources\observer::question_changed',
    ],

    // Course marking changes: reconcile (re-index when enabled, purge when
    // disabled) and forget state on deletion.
    [
        'eventname' => '\core\event\course_created',
        'callback' => '\local_elediaai_sources\observer::course_changed',
    ],
    [
        'eventname' => '\core\event\course_updated',
        'callback' => '\local_elediaai_sources\observer::course_changed',
    ],
    [
        'eventname' => '\core\event\course_deleted',
        'callback' => '\local_elediaai_sources\observer::course_deleted',
    ],
];

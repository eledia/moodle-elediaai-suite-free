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

declare(strict_types=1);

namespace block_elediaai_tutor\local;

use local_elediaai_chatengine\adapter\adapter;
use local_elediaai_chatengine\adapter\adapter_exception;
use local_elediaai_chatengine\backend_resolver;
use local_elediaai_chatengine\local\connection;

/**
 * Long-term memory (LTM) opt-in handling.
 *
 * LTM lets the tutor remember helpful facts across conversations. It is an
 * explicit per-user opt-in stored as a Moodle user preference and is DISABLED
 * by default. Moodle itself never stores or transmits memory CONTENT — only
 * the consent boolean is communicated to the RAG server, and only when the
 * admin has configured the memory opt-in tool (declaring the server
 * memory-capable). Consent reaches the server through two complementary
 * channels: {@see self::sync_to_rag()} pushes toggle changes immediately (the
 * server erases stored memories on opt-out), and every chat call carries the
 * current state as the ltm_enabled argument so the server's behaviour is
 * correct per-request even if a sync was missed.
 *
 * {@see self::fetch()} reads the stored memory back from the server for the
 * learner's own view (#238). It is shown and discarded, never kept in Moodle.
 *
 * A user preference (rather than a DB table) is used because this is a single
 * boolean per user with no relational or audit requirements of its own; the
 * preference is declared and exported through the Privacy API, and changes are
 * recorded via the ltm_preference_changed event.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ltm {
    /** @var string Name of the user preference holding the opt-in flag. */
    public const PREF = 'block_elediaai_tutor_ltm_enabled';

    /**
     * Whether the user has opted in to long-term memory.
     *
     * @param int $userid The user id.
     * @return bool False unless the user has explicitly opted in.
     */
    public static function is_enabled(int $userid): bool {
        return (bool) get_user_preferences(self::PREF, 0, $userid);
    }

    /**
     * Store the user's long-term memory opt-in choice.
     *
     * @param int $userid The user id.
     * @param bool $enabled True to opt in, false to opt out.
     * @return void
     */
    public static function set_enabled(int $userid, bool $enabled): void {
        set_user_preference(self::PREF, $enabled ? 1 : 0, $userid);
    }

    /**
     * Erase a user's long-term memory preference.
     *
     * @param int $userid The user id.
     * @return int One when a stored preference was deleted, otherwise zero.
     */
    public static function delete_for_user(int $userid): int {
        if (get_user_preferences(self::PREF, null, $userid) === null) {
            return 0;
        }
        unset_user_preference(self::PREF, $userid);
        return 1;
    }

    /**
     * Synchronise the user's consent state to the RAG server.
     *
     * Only acts when the admin has configured a memory opt-in tool (declaring
     * the RAG server memory-capable); otherwise it is a no-op and no consent
     * data leaves Moodle. The sync is best-effort: a failure is recorded via a
     * rag_request_failed event but never blocks the preference change — every
     * chat call independently carries the current consent as ltm_enabled, so
     * the server's behaviour stays correct even when this call is missed. The
     * server side is contractually required to erase stored memories when it
     * receives enabled=false.
     *
     * Note: only the consent BOOLEAN is transmitted, never memory content —
     * Moodle holds none.
     *
     * @param int $userid The user id whose consent state is synced.
     * @param \context $context Context for the failure audit event.
     * @param adapter|null $adapter Optional injected backend (tests).
     * @return bool True when the server acknowledged the sync.
     */
    public static function sync_to_rag(int $userid, \context $context, ?adapter $adapter = null): bool {
        if (!consent::is_satisfied($userid)) {
            return false;
        }

        $adapter ??= backend_resolver::active();
        if ($adapter === null) {
            // No backend to tell, so there is nothing to communicate. Whether
            // it supports memory at all is its own answer, given when asked.
            return false;
        }

        try {
            $adapter->call_tool('memoryoptin', ['enabled' => self::is_enabled($userid)], $userid);
            return true;
        } catch (\moodle_exception $e) {
            \block_elediaai_tutor\event\rag_request_failed::create([
                'context' => $context,
                'userid' => $userid,
                'other' => ['reason' => 'memory_optin'],
            ])->trigger();
            debugging(
                'block_elediaai_tutor: memory opt-in sync failed: ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            return false;
        }
    }

    /** @var string The memory view loaded; the lists may be empty. */
    public const VIEW_OK = 'ok';

    /** @var string The backend offers no memory view, or the user has not consented to use it. */
    public const VIEW_UNAVAILABLE = 'unavailable';

    /** @var string The backend was asked and failed. */
    public const VIEW_ERROR = 'error';

    /** @var string[] Memory categories of rag_server_spec A.7, in display order. */
    public const CATEGORIES = ['background', 'preference', 'knowledge', 'other'];

    /**
     * Line prefixes of a session note and the strings that replace them.
     *
     * The agent writes session notes for the model, in English, with these
     * fixed line openings; the text after them stays as written (#238).
     *
     * @var array<string, string>
     */
    public const NOTE_PREFIXES = [
        'Worked on:' => 'memory_note_workedon',
        'Open:' => 'memory_note_open',
        'Moodle:' => 'memory_note_moodle',
    ];

    /** @var int Session notes shown at first; "Show more" reveals the next as many. */
    public const SESSION_PAGE = 10;

    /**
     * Whether the backend is asked to show the stored memory at all.
     *
     * The tool follows the engine's memory switch, so this is true exactly
     * when the opt-in is offered to a memory-capable server.
     *
     * @return bool
     */
    public static function is_viewable(): bool {
        return connection::tool_name('memory') !== '';
    }

    /**
     * What the tutor has remembered about a user, ready for display.
     *
     * Asks the backend's tutor_get_memory (rag_server_spec A.7), which answers
     * for the token's owner only and independently of the current opt-in:
     * after an opt-out the lists are simply empty. Variant A of #238: all
     * memories, and the session notes as delivered (at most 200), of which the
     * view first shows {@see self::SESSION_PAGE}.
     *
     * Nothing is stored in Moodle; the answer is shaped and handed on.
     *
     * @param int $userid The user whose memory is shown; always the caller.
     * @param \context $context Context for the failure audit event.
     * @param adapter|null $adapter Optional injected backend (tests).
     * @return array{status: string, categories: array, sessions: array, hasmemories: bool,
     *         hassessions: bool, sessioncount: int, hasmore: bool, sessionpage: int}
     */
    public static function fetch(int $userid, \context $context, ?adapter $adapter = null): array {
        $view = [
            'status' => self::VIEW_UNAVAILABLE,
            'categories' => [],
            'sessions' => [],
            'hasmemories' => false,
            'hassessions' => false,
            'sessioncount' => 0,
            'hasmore' => false,
            'sessionpage' => self::SESSION_PAGE,
        ];

        // Without the first-use acknowledgement nothing goes to the backend,
        // and without the switch it has nothing to show.
        if (!consent::is_satisfied($userid) || !self::is_viewable()) {
            return $view;
        }
        $adapter ??= backend_resolver::active();
        if ($adapter === null) {
            return $view;
        }

        try {
            $result = $adapter->call_tool('memory', [], $userid);
        } catch (adapter_exception $e) {
            if ($e->errorcode === 'error_backend_tool_missing') {
                return $view;
            }
            return self::view_failed($view, $userid, $context, $e);
        } catch (\moodle_exception $e) {
            return self::view_failed($view, $userid, $context, $e);
        }
        if (!empty($result['iserror'])) {
            return self::view_failed($view, $userid, $context, null);
        }

        // The JSON arrives as the text content (like A.6); an answer without
        // any is read as "nothing stored", which is what the spec sends then.
        $payload = json_decode((string) ($result['answer'] ?? ''), true);
        $payload = is_array($payload) ? $payload : [];

        $view['status'] = self::VIEW_OK;
        $view['categories'] = self::group_memories(is_array($payload['memories'] ?? null) ? $payload['memories'] : []);
        $view['sessions'] = self::sessions(is_array($payload['sessions'] ?? null) ? $payload['sessions'] : []);
        $view['hasmemories'] = $view['categories'] !== [];
        $view['hassessions'] = $view['sessions'] !== [];
        $view['sessioncount'] = count($view['sessions']);
        $view['hasmore'] = $view['sessioncount'] > self::SESSION_PAGE;
        return $view;
    }

    /**
     * Record a failed memory view and say so.
     *
     * @param array $view The view so far.
     * @param int $userid The user.
     * @param \context $context Context for the audit event.
     * @param \Throwable|null $e What went wrong, if anything was thrown.
     * @return array The view with the error status.
     */
    private static function view_failed(array $view, int $userid, \context $context, ?\Throwable $e): array {
        \block_elediaai_tutor\event\rag_request_failed::create([
            'context' => $context,
            'userid' => $userid,
            'other' => ['reason' => 'memory_view'],
        ])->trigger();
        if ($e !== null) {
            debugging('block_elediaai_tutor: memory view failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        $view['status'] = self::VIEW_ERROR;
        return $view;
    }

    /**
     * Group memories by category, in the fixed category order.
     *
     * An unknown category is shown under "other" rather than dropped: the
     * learner should see everything that is kept about them.
     *
     * @param array $memories The backend's memories list.
     * @return array List of {category, label, items: [{content, updated, updatedlabel}]}.
     */
    private static function group_memories(array $memories): array {
        $groups = array_fill_keys(self::CATEGORIES, []);
        foreach ($memories as $memory) {
            if (!is_array($memory)) {
                continue;
            }
            $content = trim(clean_param((string) ($memory['content'] ?? ''), PARAM_TEXT));
            if ($content === '') {
                continue;
            }
            $category = (string) ($memory['category'] ?? '');
            if (!isset($groups[$category])) {
                $category = 'other';
            }
            $updated = self::timestamp($memory['updated_at'] ?? null);
            $groups[$category][] = [
                'content' => $content,
                'updated' => $updated,
                'updatedlabel' => self::date_label($updated),
            ];
        }

        $out = [];
        foreach ($groups as $category => $items) {
            if ($items === []) {
                continue;
            }
            $out[] = [
                'category' => $category,
                'label' => get_string('memory_category_' . $category, 'block_elediaai_tutor'),
                'items' => $items,
            ];
        }
        return $out;
    }

    /**
     * Session notes with course name, date and translated line prefixes.
     *
     * @param array $sessions The backend's sessions list, newest first.
     * @return array List of {courseid, coursename, lines: [{label, text}], updated, updatedlabel, hidden}.
     */
    private static function sessions(array $sessions): array {
        global $DB;

        $out = [];
        $coursenames = [];
        foreach ($sessions as $session) {
            if (!is_array($session)) {
                continue;
            }
            $lines = self::note_lines((string) ($session['note'] ?? ''));
            if ($lines === []) {
                continue;
            }

            $courseid = (int) ($session['course_id'] ?? 0);
            if ($courseid > 0 && $courseid !== (int) SITEID) {
                if (!array_key_exists($courseid, $coursenames)) {
                    $fullname = $DB->get_field('course', 'fullname', ['id' => $courseid]);
                    $coursenames[$courseid] = $fullname === false
                        ? get_string('memory_session_coursegone', 'block_elediaai_tutor')
                        // Unescaped: the template escapes, and twice would
                        // show "&amp;" in a course called "A & B".
                        : format_string($fullname, true, [
                            'context' => \core\context\course::instance($courseid),
                            'escape' => false,
                        ]);
                }
                $coursename = $coursenames[$courseid];
            } else {
                $coursename = get_string('memory_session_nocourse', 'block_elediaai_tutor');
            }

            $updated = self::timestamp($session['updated_at'] ?? null);
            $out[] = [
                'courseid' => $courseid,
                'coursename' => $coursename,
                'lines' => $lines,
                'updated' => $updated,
                'updatedlabel' => self::date_label($updated),
                'hidden' => count($out) >= self::SESSION_PAGE,
            ];
        }
        return $out;
    }

    /**
     * Split a session note into lines and translate their fixed prefixes.
     *
     * @param string $note The note, lines separated by "\n".
     * @return array List of {label, text}; label is '' for a line without a known prefix.
     */
    public static function note_lines(string $note): array {
        $lines = [];
        foreach (preg_split('/\R/u', $note) ?: [] as $line) {
            $line = trim(clean_param($line, PARAM_TEXT));
            if ($line === '') {
                continue;
            }
            $label = '';
            foreach (self::NOTE_PREFIXES as $prefix => $identifier) {
                if (strncmp($line, $prefix, strlen($prefix)) === 0) {
                    $label = get_string($identifier, 'block_elediaai_tutor');
                    $line = trim(substr($line, strlen($prefix)));
                    break;
                }
            }
            if ($line === '') {
                continue;
            }
            $lines[] = ['label' => $label, 'text' => $line];
        }
        return $lines;
    }

    /**
     * A Unix timestamp from an ISO 8601 date, 0 when there is none.
     *
     * @param mixed $iso The backend's date.
     * @return int
     */
    private static function timestamp(mixed $iso): int {
        if (!is_string($iso) || $iso === '') {
            return 0;
        }
        $time = strtotime($iso);
        return $time === false ? 0 : $time;
    }

    /**
     * The date as the learner reads it, '' without one.
     *
     * @param int $time Unix timestamp.
     * @return string
     */
    private static function date_label(int $time): string {
        return $time > 0 ? userdate($time, get_string('strftimedatetimeshort', 'core_langconfig')) : '';
    }
}

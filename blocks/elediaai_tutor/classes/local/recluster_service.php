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
use local_elediaai_chatengine\local\service_user;
use local_elediaai_core\local\insights;

/**
 * Batch topic reclustering against the RAG server.
 *
 * Labels the questions of the question analytics: for every course with
 * recent activity, logged questions are sent to the recluster tool in
 * batches, together with the course (name, language) and its current labels
 * with counts and example questions, and the returned labels are written
 * back. The agent may answer {@see insights::TOPIC_NONE} for a turn that is
 * no question about the course; that is stored like any other label.
 *
 * Which turns are sent (ai_suite#210, decided 24.11.2026):
 *
 * - **Every night, only what is new** (variant B): turns whose topic is
 *   still NULL. Once labelled stays labelled. Before, the whole 30-day window
 *   was re-sent every night, borderline questions were decided anew each
 *   time, and teachers saw numbers jump without a single new question.
 * - **Every 14 days, everything** (variant C), only when the admin setting
 *   `reclusterfullrun` is on (default off): all turns of the window are
 *   re-labelled once, which repairs labels that drifted, at the price of the
 *   numbers moving on that night.
 *
 * This is a SITE-LEVEL service operation: requests carry the MCP token of the
 * auto-provisioned maintenance account ({@see service_user}) — never a real
 * person's token — so the RAG server can authenticate the call exactly like
 * every other tool, without any shared transport secret (see the spec,
 * section A.6). Question texts re-sent here already transited the same server
 * at chat time.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recluster_service {
    /** @var int Look-back window: matches the hotspot report window. */
    public const WINDOW_DAYS = 30;

    /** @var int Questions per tools/call batch (spec maximum: 200). */
    public const BATCH_SIZE = 200;

    /** @var int Safety cap of questions processed per course per run. */
    public const MAX_PER_COURSE = 1000;

    /** @var int Days between two full re-labelling runs (variant C). */
    public const FULL_RUN_INTERVAL_DAYS = 14;

    /** @var string Config key: full re-labelling every FULL_RUN_INTERVAL_DAYS. */
    public const CONFIG_FULL_RUN = 'reclusterfullrun';

    /** @var string Config key: when the last full run finished. */
    public const CONFIG_LAST_FULL_RUN = 'reclusterlastfullrun';

    /**
     * Whether reclustering is configured and may run.
     *
     * @return bool
     */
    public static function is_configured(): bool {
        // Kein Opt-in mehr davor: das Turn-Protokoll der Suite laeuft immer,
        // und ein Schalter, der eine Auswertung abschaltet, deren Daten
        // ohnehin da sind, verwirrt nur.
        return backend_resolver::is_available();
    }

    /**
     * Whether tonight's run re-labels everything (variant C).
     *
     * Only with the admin setting on, and then once every
     * {@see FULL_RUN_INTERVAL_DAYS} days. The first night after switching it
     * on is due at once: the admin asked for a repair, not for a two-week wait.
     *
     * @param int|null $now Point in time to judge, now by default.
     * @return bool
     */
    public static function full_run_due(?int $now = null): bool {
        if (!get_config('block_elediaai_tutor', self::CONFIG_FULL_RUN)) {
            return false;
        }
        $last = (int) get_config('block_elediaai_tutor', self::CONFIG_LAST_FULL_RUN);
        return ($now ?? time()) - $last >= self::FULL_RUN_INTERVAL_DAYS * DAYSECS;
    }

    /**
     * Recluster recent questions for every active course.
     *
     * Failures are per-course best-effort: a failing batch aborts that course
     * (the next nightly run retries) but never the whole run.
     *
     * @param adapter|null $adapter Optional injected backend (tests).
     * @param int|null $serviceuserid Optional maintenance account id (tests); by
     *                                default the auto-created service account.
     * @param bool|null $full True re-labels every turn of the window, false only
     *                        the turns without a topic; null decides by
     *                        {@see full_run_due()}.
     * @return array{courses: int, batches: int, updated: int, failed: int, full: bool}
     */
    public static function run(?adapter $adapter = null, ?int $serviceuserid = null, ?bool $full = null): array {
        $full ??= self::full_run_due();
        $stats = ['courses' => 0, 'batches' => 0, 'updated' => 0, 'failed' => 0, 'full' => $full];

        // Vorher stand hier der Opt-in-Schalter des Frage-Logs; er ist mit dem
        // Log entfallen. An seine Stelle tritt die Frage, ob ueberhaupt ein
        // Backend eingerichtet ist -- aber nur, wenn keiner uebergeben wurde:
        // wer einen Adapter mitbringt, hat die Frage schon beantwortet, und ihn
        // trotzdem abzuweisen hiesse, dieselbe Pruefung zweimal zu machen und
        // beim zweiten Mal falsch.
        if ($adapter === null && !self::is_configured()) {
            return $stats;
        }

        try {
            $adapter ??= backend_resolver::require_active();
            // Authenticated as the maintenance account rather than as whoever
            // happened to trigger the nightly task.
            $serviceuserid ??= (int) service_user::get_or_create()->id;
        } catch (\moodle_exception $e) {
            debugging(
                'block_elediaai_tutor: recluster aborted (backend/account): ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            $stats['failed']++;
            return $stats;
        }

        foreach (insights::active_courses(self::WINDOW_DAYS) as $courseid) {
            $stats['courses']++;
            $course = null;
            $labels = null;
            $afterid = 0;
            $seen = 0;

            while ($seen < self::MAX_PER_COURSE) {
                // Paged by id, not by offset: in variant B a labelled batch
                // leaves the "no topic yet" set, and an offset would then skip
                // the next batch's worth of rows.
                $rows = insights::fetch_for_recluster(
                    $courseid,
                    self::WINDOW_DAYS,
                    self::BATCH_SIZE,
                    0,
                    !$full,
                    $afterid
                );
                if (empty($rows)) {
                    break;
                }
                $seen += count($rows);
                $afterid = (int) end($rows)->id;

                $questions = [];
                $sentids = [];
                foreach ($rows as $row) {
                    // Eine leere Frage laesst der Agent den ganzen Stapel
                    // ablehnen (#206). Der Kern liefert seit dem Fix keine mehr;
                    // die Pruefung hier faengt Zeilen ab, die auf anderem Weg
                    // leer ins Protokoll kamen.
                    $text = trim((string) $row->prompt);
                    if ($text === '') {
                        continue;
                    }
                    $questions[] = ['id' => (int) $row->id, 'text' => $text];
                    $sentids[] = (int) $row->id;
                }
                if ($questions === []) {
                    if (count($rows) < self::BATCH_SIZE) {
                        break;
                    }
                    continue;
                }

                // Read only once there is something to send: in variant B most
                // courses have no new question on most nights.
                $course ??= self::course_info($courseid);
                $labels ??= insights::labels_with_examples($courseid);

                try {
                    // Contract of tutor_recluster_questions since the agent
                    // release of 23.11.2026 (rag_server_spec A.6): mode, the
                    // course as context and the label set with anchors instead
                    // of the bare existing_labels.
                    $result = $adapter->call_tool('recluster', [
                        'mode' => 'classify',
                        'course_id' => (string) $courseid,
                        'course' => $course,
                        'labels' => array_values($labels),
                        'questions' => array_values($questions),
                    ], $serviceuserid);
                    $map = self::topics_from($result);
                } catch (adapter_exception $e) {
                    $stats['failed']++;
                    debugging("block_elediaai_tutor: recluster batch failed (course $courseid): "
                        . $e->getMessage(), DEBUG_DEVELOPER);
                    break;
                }

                $stats['batches']++;
                $stats['updated'] += insights::apply_topics($map, $courseid, $sentids);

                // Newly minted labels join the label set for the next batch;
                // read again from the log so they come with count and examples.
                $known = array_column($labels, 'label');
                if (array_diff(array_values($map), $known, [insights::TOPIC_NONE]) !== []) {
                    $labels = null;
                }

                if (count($rows) < self::BATCH_SIZE) {
                    break;
                }
            }
        }

        if ($full) {
            set_config(self::CONFIG_LAST_FULL_RUN, time(), 'block_elediaai_tutor');
        }

        return $stats;
    }

    /**
     * The course as context for the agent: its name and its language.
     *
     * A course without a forced language is answered in the site language,
     * so that is what the agent gets. A course that no longer exists sends an
     * empty name; the agent treats the field as optional context.
     *
     * @param int $courseid
     * @return array{fullname: string, lang: string}
     */
    private static function course_info(int $courseid): array {
        global $CFG, $DB;

        $fullname = '';
        $lang = '';
        $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname, lang', IGNORE_MISSING);
        if ($course) {
            $fullname = strip_tags(format_string(
                (string) $course->fullname,
                true,
                ['context' => \core\context\course::instance($courseid), 'escape' => false]
            ));
            $lang = trim((string) $course->lang);
        }
        if ($lang === '') {
            $lang = (string) ($CFG->lang ?? 'en');
        }

        return [
            'fullname' => \core_text::substr(trim($fullname), 0, 255),
            'lang' => $lang,
        ];
    }

    /**
     * Read the topic map out of a recluster tool result.
     *
     * The typed wrapper that used to unwrap this lived in the block's own
     * client; the shape it expects is the backend's, described in
     * rag_server_spec A.6.
     *
     * @param array $result The normalised tool result.
     * @return array<int, string> Question id => topic label.
     * @throws adapter_exception When the backend answered without topics.
     */
    private static function topics_from(array $result): array {
        $payload = json_decode((string) ($result['answer'] ?? ''), true);
        $topics = is_array($payload) ? ($payload['topics'] ?? null) : null;
        if (!is_array($topics)) {
            throw new adapter_exception('error_backend_bad_response', 'recluster: no topics in result');
        }

        // A list of {id, topic} entries, per rag_server_spec A.6. Entries
        // without an id or with an empty label are skipped rather than stored:
        // a blank topic would replace a good one with nothing.
        $map = [];
        foreach ($topics as $entry) {
            if (!is_array($entry) || !isset($entry['id'])) {
                continue;
            }
            $topic = \core_text::substr(trim((string) ($entry['topic'] ?? '')), 0, 100);
            if ($topic !== '') {
                $map[(int) $entry['id']] = $topic;
            }
        }

        return $map;
    }
}

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
 * Converges the question-analytics hotspot labels: for every course with
 * recent activity, the last 30 days of logged questions are sent to the
 * configured recluster tool (in batches, together with the course's current
 * label registry) and the returned canonical labels are written back. The
 * server contract is idempotent, so repeated runs are safe and progressively
 * converge labels that drifted across model or prompt changes.
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
     * Recluster recent questions for every active course.
     *
     * Failures are per-course best-effort: a failing batch aborts that course
     * (the next nightly run retries) but never the whole run.
     *
     * @param adapter|null $adapter Optional injected backend (tests).
     * @param int|null $serviceuserid Optional maintenance account id (tests); by
     *                                default the auto-created service account.
     * @return array{courses: int, batches: int, updated: int, failed: int}
     */
    public static function run(?adapter $adapter = null, ?int $serviceuserid = null): array {
        $stats = ['courses' => 0, 'batches' => 0, 'updated' => 0, 'failed' => 0];

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
            $labels = insights::distinct_topics($courseid);
            $offset = 0;

            while ($offset < self::MAX_PER_COURSE) {
                $rows = insights::fetch_for_recluster(
                    $courseid,
                    self::WINDOW_DAYS,
                    self::BATCH_SIZE,
                    $offset
                );
                if (empty($rows)) {
                    break;
                }
                $offset += count($rows);

                $questions = [];
                $sentids = [];
                foreach ($rows as $row) {
                    $questions[] = ['id' => (int) $row->id, 'text' => (string) $row->prompt];
                    $sentids[] = (int) $row->id;
                }

                try {
                    $result = $adapter->call_tool('recluster', [
                        'course_id' => (string) $courseid,
                        'existing_labels' => array_values($labels),
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

                // Newly minted labels join the registry for the next batch.
                $labels = array_slice(array_values(array_unique(array_merge(
                    $labels,
                    array_values($map)
                ))), 0, 100);

                if (count($rows) < self::BATCH_SIZE) {
                    break;
                }
            }
        }

        return $stats;
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

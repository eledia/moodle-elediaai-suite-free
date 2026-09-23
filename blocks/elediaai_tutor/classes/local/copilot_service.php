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
use local_elediaai_chatengine\adapter\chat_request;
use local_elediaai_chatengine\adapter\chat_response;
use local_elediaai_chatengine\backend_resolver;
use local_elediaai_chatengine\local\guard;
use local_elediaai_chatengine\local\markdown_renderer;
use local_elediaai_chatengine\local\mode;
use local_elediaai_chatengine\local\prompt_safety;
use local_elediaai_chatengine\local\token_provider;
use local_elediaai_core\local\insights;

/**
 * Teacher-Copilot: AI analysis of a course's logged tutor questions.
 *
 * Builds one structured prompt from the question-analytics data (hotspots and
 * a sample of recent questions) and sends it through the regular chat tool,
 * grounded in the course knowledge base and authenticated with the teacher's
 * own user-scoped token. The result is a Markdown analysis ("what do learners
 * not understand + recommended actions") rendered for the report page.
 *
 * Deliberately bypasses the engine's chat service: a copilot run is not a
 * learner conversation, so it must not appear in the question log, in the
 * teacher's conversation list or in the learner-chat events. The consent gate
 * and the rate limit are enforced the same way; the daily message quota is
 * not consumed. The server-issued conversation id is discarded.
 *
 * @package     block_elediaai_tutor
 * @author      Johannes Moskaliuk
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class copilot_service {
    /** @var int Max recent questions sampled into the prompt. */
    private const SAMPLE_SIZE = 40;

    /** @var int Per-question character cap inside the prompt. */
    private const QUESTION_MAXLEN = 200;

    /** @var int Rough overall prompt budget in characters. */
    private const PROMPT_BUDGET = 6000;

    /**
     * Generate the analysis for a course.
     *
     * @param int $courseid The course id.
     * @param int $userid The acting teacher's user id.
     * @param \core\context\course $context Course context (for safe rendering).
     * @param adapter|null $adapter Optional injected backend (tests).
     * @return array{analysishtml: string, iserror: bool}
     * @throws \moodle_exception On consent, rate-limit, data or backend failure.
     */
    public static function analyse(
        int $courseid,
        int $userid,
        \core\context\course $context,
        ?adapter $adapter = null
    ): array {
        consent::require_consent($userid);
        guard::enforce_rate_limit($userid);

        // Quelle ist seit dem 19.09.2026 der Turn-Speicher der Suite. Der alte
        // Frage-Log wurde seit dem 27.08.2026 nicht mehr gefuellt, also
        // analysierte der Copilot eine leere Tabelle und meldete "keine Daten".
        $hotspots = insights::gaps($courseid, 30, 10);
        $recent = insights::recent($courseid, 30, self::SAMPLE_SIZE);
        if (empty($hotspots) && empty($recent)) {
            throw new \moodle_exception('copilot_nodata', 'block_elediaai_tutor');
        }

        $prompt = self::build_prompt($hotspots, $recent);

        try {
            $adapter ??= backend_resolver::require_active();
        } catch (\moodle_exception $e) {
            self::log_failure($userid, $context, $e, $courseid);
            throw $e;
        }

        try {
            $response = self::call($adapter, $prompt, $courseid, $userid);
        } catch (adapter_exception $e) {
            // The cached callback token may have been revoked or expired: drop
            // it, mint a fresh one and retry exactly once, as a chat turn does.
            token_provider::forget_cached_token($userid);
            try {
                $response = self::call($adapter, $prompt, $courseid, $userid);
            } catch (\moodle_exception $retry) {
                self::log_failure($userid, $context, $retry, $courseid);
                throw $retry;
            }
        } catch (\moodle_exception $e) {
            self::log_failure($userid, $context, $e, $courseid);
            throw $e;
        }

        return [
            'analysishtml' => markdown_renderer::render($response->answer, $context),
            'iserror' => $response->iserror,
        ];
    }

    /**
     * One grounded turn on the teacher's own behalf.
     *
     * The conversation id the backend returns is deliberately dropped: an
     * analysis run is not a conversation and must not turn up in the teacher's
     * list of them.
     *
     * @param adapter $adapter The backend.
     * @param string $prompt The assembled analysis prompt.
     * @param int $courseid The course id.
     * @param int $userid The teacher's user id.
     * @return chat_response The answer.
     */
    private static function call(adapter $adapter, string $prompt, int $courseid, int $userid): chat_response {
        return $adapter->chat(new chat_request(
            usermessage: prompt_safety::frame_user_input($prompt),
            mode: mode::GROUNDED,
            userid: $userid,
            contextid: (int) \context_course::instance($courseid)->id,
            courseid: $courseid,
            language: current_language(),
        ));
    }

    /**
     * Assemble the localised analysis prompt from hotspots + question sample.
     *
     * @param \stdClass[] $hotspots Hotspot buckets {label, count, cmid}.
     * @param \stdClass[] $recent Recent question rows {question, ...}.
     * @return string
     */
    private static function build_prompt(array $hotspots, array $recent): string {
        // Der ungedeckte Anteil reist mit: er ist das Signal, das die Analyse
        // braucht, um "fehlt im Material" von "wird nur oft gefragt" zu
        // unterscheiden.
        $hotspotlines = [];
        foreach ($hotspots as $hotspot) {
            $hotspotlines[] = $hotspot->label . ': ' . $hotspot->total
                . ' (' . $hotspot->ungroundedpct . '% ungedeckt)';
        }
        $hotspotstext = $hotspotlines !== [] ? implode('; ', $hotspotlines) : '-';

        // Sample questions until the rough prompt budget is spent, each one
        // truncated so a single essay-length question cannot eat the budget.
        $questionlines = [];
        $spent = 0;
        foreach ($recent as $index => $row) {
            $question = \core_text::substr(trim((string) $row->prompt), 0, self::QUESTION_MAXLEN);
            if ($question === '') {
                continue;
            }
            $line = ($index + 1) . '. ' . $question;
            if ($spent + \core_text::strlen($line) > self::PROMPT_BUDGET) {
                break;
            }
            $spent += \core_text::strlen($line);
            $questionlines[] = $line;
        }
        $questionstext = $questionlines !== [] ? implode(' | ', $questionlines) : '-';

        return get_string('copilot_prompt', 'block_elediaai_tutor', (object) [
            'hotspots' => $hotspotstext,
            'questions' => $questionstext,
        ]);
    }

    /**
     * Record a failed copilot run (event + admin diagnostics, no content).
     *
     * @param int $userid The acting user id.
     * @param \core\context\course $context The course context.
     * @param \Throwable $exception The failure.
     * @param int $courseid The course id.
     * @return void
     */
    private static function log_failure(
        int $userid,
        \core\context\course $context,
        \Throwable $exception,
        int $courseid
    ): void {
        \block_elediaai_tutor\event\rag_request_failed::create([
            'context' => $context,
            'userid' => $userid,
            'other' => ['reason' => 'copilot_error'],
        ])->trigger();

        diagnostics::record($userid, $context, 'copilot_error', $exception, $courseid);
    }
}

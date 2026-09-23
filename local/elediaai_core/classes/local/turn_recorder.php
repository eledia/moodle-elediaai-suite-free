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
 * Writes one row per AI turn of the suite.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_elediaai_core\local;

/**
 * The suite's own record of what was asked and answered.
 *
 * **Why this exists at all.** The audit used to be a view on Moodle's
 * `ai_action_register`, and inherited its limits: no component, so the question
 * „which feature is being used" was unanswerable, and nothing outside
 * `core_ai` appeared. Closing the chat gap meant writing into a foreign table
 * by rebuilding a private core method — defensible once, not as architecture.
 * This table is the suite's own, and the write happens where every AI call of
 * the suite already passes: {@see \local_elediaai_core\quota_aware_ai_manager}.
 *
 * **What is in it and what is not.** Prompt, answer, place, cost, which feature
 * asked and what the answer was grounded on. No user id — the operator decided
 * that on 05.09.2026 — but a salted pseudonym, so the didactic report can tell
 * five askers from one and an erasure request can still be answered. See
 * {@see pseudonym} for why that is the honest middle.
 *
 * **It never throws into the caller.** A turn that was answered must not fail
 * because the bookkeeping did; the failure goes to developer debugging and the
 * answer reaches the reader. Same contract as the quota lifecycle around it.
 */
final class turn_recorder {
    /** @var string Backing table. */
    public const TABLE = 'local_elediaai_core_turn';

    /** @var string The answer came from retrieved course material. */
    public const ORIGIN_GROUNDED = 'grounded';

    /** @var string The answer came from the model's own knowledge. */
    public const ORIGIN_GENERAL = 'general';

    /** @var string The answer was read out of Moodle through a tool. */
    public const ORIGIN_MCP = 'mcp';

    /** @var int Longest stored prompt/answer, in characters. */
    private const TEXT_LIMIT = 20000;

    /**
     * Static-only.
     */
    private function __construct() {
    }

    /**
     * Record one turn.
     *
     * @param string $component Frankenstyle of the feature that asked.
     * @param string $actionname Core action name, or 'chat_turn'.
     * @param int $contextid Where it happened.
     * @param int $userid Who asked; stored only as a pseudonym, never as an id.
     * @param string $prompt What was asked.
     * @param string $response What was answered.
     * @param bool $success Whether an answer was produced.
     * @param int|null $prompttokens Measured prompt tokens.
     * @param int|null $completiontokens Measured completion tokens.
     * @param int $instanceid Placement instance, 0 for a site-wide surface.
     * @param string $origin One of the ORIGIN_* constants.
     * @param string|null $topic Canonical topic label from the backend.
     * @param string|null $sourcetitle Title of the primary cited source.
     * @param int|null $cmid Course module of that source, when resolvable.
     * @param string|null $provider Who answered.
     * @param string|null $model The model, when the backend names one.
     * @param string|null $errorcode Error code on failure.
     * @param bool $tokensestimated Whether the two token counts are an estimate
     *                               rather than a figure the backend reported.
     * @param int|null $registerid The row this call produced in Moodle's own
     *                             register, when it went through core_ai.
     * @param int|null $timestarted When the turn was dispatched; now if omitted.
     * @return int|null The new row id, or null when the write failed. The
     *         provenance record uses it to point at the exchange it belongs to.
     */
    public static function record(
        string $component,
        string $actionname,
        int $contextid,
        int $userid,
        string $prompt,
        string $response,
        bool $success = true,
        ?int $prompttokens = null,
        ?int $completiontokens = null,
        int $instanceid = 0,
        string $origin = self::ORIGIN_GENERAL,
        ?string $topic = null,
        ?string $sourcetitle = null,
        ?int $cmid = null,
        ?string $provider = null,
        ?string $model = null,
        ?string $errorcode = null,
        ?int $timestarted = null,
        bool $tokensestimated = false,
        ?int $registerid = null
    ): ?int {
        global $DB;

        try {
            $now = time();
            $started = $timestarted !== null && $timestarted > 0 ? $timestarted : $now;

            return (int) $DB->insert_record(self::TABLE, (object) [
                'component' => self::clip($component, 100),
                'instanceid' => max(0, $instanceid),
                'actionname' => self::clip($actionname, 100),
                'contextid' => max(0, $contextid),
                'courseid' => self::course_of($contextid),
                // Deliberately a pseudonym, never the id. See the class comment.
                'askerkey' => pseudonym::for_user($userid),
                'origin' => self::normalise_origin($origin),
                'topic' => self::clip_or_null($topic, 255),
                'sourcetitle' => self::clip_or_null($sourcetitle, 255),
                'cmid' => $cmid !== null && $cmid > 0 ? $cmid : null,
                'prompt' => self::clip($prompt, self::TEXT_LIMIT),
                'response' => self::clip($response, self::TEXT_LIMIT),
                'prompttokens' => max(0, (int) $prompttokens),
                'completiontokens' => max(0, (int) $completiontokens),
                'success' => $success ? 1 : 0,
                'errorcode' => self::clip_or_null($errorcode, 100),
                'provider' => self::clip_or_null($provider, 100),
                'model' => self::clip_or_null($model, 100),
                'tokensestimated' => $tokensestimated ? 1 : 0,
                'registerid' => $registerid !== null && $registerid > 0 ? $registerid : null,
                'durationms' => max(0, ($now - $started) * 1000),
                'timecreated' => $started,
            ]);
        } catch (\Throwable $e) {
            debugging(
                'local_elediaai_core: could not record the AI turn: ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            return null;
        }
    }

    /**
     * Course a context belongs to, or 0 outside any course.
     *
     * Resolved once here rather than per row in the report: the didactic view
     * groups by course, and walking the context tree for every one of a few
     * thousand rows is the difference between a page and a wait.
     *
     * @param int $contextid
     * @return int
     */
    private static function course_of(int $contextid): int {
        if ($contextid <= 0) {
            return 0;
        }

        try {
            $context = \core\context::instance_by_id($contextid, IGNORE_MISSING);
        } catch (\Throwable $e) {
            return 0;
        }
        if (!$context) {
            return 0;
        }

        $coursecontext = $context->get_course_context(false);
        return $coursecontext ? (int) $coursecontext->instanceid : 0;
    }

    /**
     * Keep an unknown origin out of the table.
     *
     * A value the report does not know would show up as a missing string rather
     * than as a wrong number, but it would also silently drop the row out of
     * every grounded/ungrounded ratio. Better to land in the honest default.
     *
     * @param string $origin
     * @return string
     */
    private static function normalise_origin(string $origin): string {
        $known = [self::ORIGIN_GROUNDED, self::ORIGIN_GENERAL, self::ORIGIN_MCP];
        return in_array($origin, $known, true) ? $origin : self::ORIGIN_GENERAL;
    }

    /**
     * Truncate to the column width.
     *
     * @param string $value
     * @param int $limit
     * @return string
     */
    private static function clip(string $value, int $limit): string {
        return \core_text::strlen($value) > $limit
            ? \core_text::substr($value, 0, $limit)
            : $value;
    }

    /**
     * Truncate, or return null for an empty value.
     *
     * @param string|null $value
     * @param int $limit
     * @return string|null
     */
    private static function clip_or_null(?string $value, int $limit): ?string {
        $value = $value === null ? '' : trim($value);
        return $value === '' ? null : self::clip($value, $limit);
    }
}

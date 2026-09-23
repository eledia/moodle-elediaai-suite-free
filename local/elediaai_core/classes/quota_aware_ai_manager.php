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
 * Quota-aware execution boundary for eLeDia.ai consumers.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core;

use core_ai\aiactions\base;
use core_ai\aiactions\responses\response_base;
use core_ai\manager;
use local_elediaai_core\local\quota_manager;
use local_elediaai_core\local\turn_recorder;

defined('MOODLE_INTERNAL') || die();

/**
 * Owns reservation, Core AI dispatch, and quota settlement for one request.
 *
 * Consumers must not reserve or record local quota around this API. Moodle's
 * manager remains responsible for provider selection and the AI action audit.
 */
final class quota_aware_ai_manager {
    /** @var int How many suite calls are on the stack; see {@see in_suite_call()}. */
    private static int $insuitecall = 0;

    /**
     * Execute a legacy non-core callback under the same quota lifecycle.
     *
     * This compatibility entry point lets existing suite adapters migrate
     * their accounting before their transport is replaced with Core AI.
     *
     * @param int $userid User charged for the request.
     * @param string $component Calling component.
     * @param string $prompt Complete prompt material sent by the callback.
     * @param callable $callback External request callback.
     * @param callable|null $usageresolver Optional measured-usage resolver. It may return named
     *     `prompttokens`/`completiontokens` values or a positional prompt/completion tuple.
     * @return mixed Callback result.
     */
    public static function process_callback(
        int $userid,
        string $component,
        string $prompt,
        callable $callback,
        ?callable $usageresolver = null,
        ?array &$booked = null,
    ): mixed {
        $estimatedprompttokens = quota_manager::estimate_tokens($prompt);
        $reservation = quota_manager::reserve($userid, $estimatedprompttokens, $component);
        try {
            $result = $callback();
            $completion = is_string($result) ? $result : json_encode($result);
            [$prompttokens, $completiontokens, $estimated] = self::resolve_callback_usage(
                $result,
                $usageresolver,
                $estimatedprompttokens,
                quota_manager::estimate_tokens((string) $completion),
            );
            // Was gebucht wurde, geht an den Aufrufer zurueck. Sonst schreibt
            // er sein Protokoll aus den rohen Werten der Antwort, waehrend das
            // Guthaben eine Schaetzung traegt -- und das Audit behauptet, die
            // Anfrage habe nichts gekostet.
            $booked = [
                'prompttokens' => $prompttokens,
                'completiontokens' => $completiontokens,
                'estimated' => $estimated,
            ];
            quota_manager::commit(
                $userid,
                $reservation,
                $prompttokens,
                $completiontokens,
                $component,
            );
            return $result;
        } catch (\Throwable $exception) {
            quota_manager::release($userid, $reservation);
            throw $exception;
        }
    }

    /**
     * Resolve measured callback usage without making accounting dependent on an adapter helper.
     *
     * A resolver failure must not turn completed external work into an uncharged request or hide
     * its successful result. Invalid, negative, or missing values fall back independently.
     *
     * @param mixed $result Callback result passed unchanged to the resolver.
     * @param callable|null $usageresolver Optional usage resolver.
     * @param int $promptfallback Estimated prompt usage.
     * @param int $completionfallback Estimated completion usage.
     * @return array{0: int, 1: int, 2: bool}{0: int, 1: int} Normalized prompt and completion tokens.
     */
    private static function resolve_callback_usage(
        mixed $result,
        ?callable $usageresolver,
        int $promptfallback,
        int $completionfallback,
    ): array {
        if ($usageresolver === null) {
            return [max(0, $promptfallback), max(0, $completionfallback), true];
        }

        try {
            $usage = $usageresolver($result);
            if (!is_array($usage)) {
                throw new \UnexpectedValueException('The usage resolver must return an array.');
            }
        } catch (\Throwable $exception) {
            debugging(
                'local_elediaai_core usage resolver failed; estimated usage was recorded: '
                    . $exception->getMessage(),
                DEBUG_DEVELOPER,
            );
            return [max(0, $promptfallback), max(0, $completionfallback), true];
        }

        $promptvalue = array_key_exists('prompttokens', $usage) ? $usage['prompttokens'] : ($usage[0] ?? null);
        $completionvalue = array_key_exists('completiontokens', $usage)
            ? $usage['completiontokens']
            : ($usage[1] ?? null);

        // Geschaetzt ist es, sobald eine der beiden Zahlen aus dem Rueckfall
        // kommt: eine halb gemessene Summe ist keine gemessene.
        $estimated = !self::is_reported($promptvalue) || !self::is_reported($completionvalue);

        return [
            self::resolved_token_value($promptvalue, $promptfallback),
            self::resolved_token_value($completionvalue, $completionfallback),
            $estimated,
        ];
    }

    /**
     * Normalize one resolved token value with a deterministic fallback.
     *
     * @param mixed $value Resolved value.
     * @param int $fallback Estimated fallback.
     * @return int
     */
    /**
     * Whether the backend reported this number itself.
     *
     * Same condition as {@see resolved_token_value()}, named once so the two
     * cannot drift apart.
     *
     * @param mixed $value
     * @return bool
     */
    private static function is_reported(mixed $value): bool {
        return is_numeric($value) && (int) $value >= 0;
    }

    /**
     * The reported number, or the estimate when the backend gave none.
     *
     * @param mixed $value The value the resolver produced.
     * @param int $fallback The estimate to fall back to.
     * @return int
     */
    private static function resolved_token_value(mixed $value, int $fallback): int {
        if (!is_numeric($value) || (int) $value < 0) {
            return max(0, $fallback);
        }
        return (int) $value;
    }

    /**
     * Execute an AI action under the local quota lifecycle.
     *
     * @param base $action Configured Moodle AI action.
     * @param string $component Frankenstyle name of the calling component.
     * @param int $estimatedprompttokens Conservative prompt-token estimate.
     * @param array $actionparams Quota parameters such as image size/count.
     * @param manager|null $manager Core manager override, primarily for tests.
     * @param callable|null $successvalidator Validates a successful response before quota is committed.
     * @return response_base The unchanged Moodle AI response.
     */
    public static function process_action(
        base $action,
        string $component,
        int $estimatedprompttokens,
        array $actionparams = [],
        ?manager $manager = null,
        ?callable $successvalidator = null,
        ?string &$provenanceuuid = null,
    ): response_base {
        $userid = (int) $action->get_configuration('userid');
        $actionname = $action->get_basename();
        $reservation = quota_manager::reserve_for_action(
            $userid,
            $actionname,
            $actionparams,
            $component,
            $estimatedprompttokens,
        );

        try {
            $manager ??= \core\di::get(manager::class);
            // Solange dieser Aufruf laeuft, weiss der Anbieter, dass er den
            // Herkunftsnachweis nicht selbst schreiben muss -- der entsteht
            // hier, mit Komponente, Turn und Registerzeile daran. Ohne diese
            // Absprache schrieben beide, und eine Antwort bekam zwei Belege.
            self::$insuitecall++;
            // Der Kern schreibt seine Registerzeile mitten in process_action()
            // und gibt ihre Nummer nicht heraus -- store_action_result() liefert
            // sie, process_action() wirft sie weg. Also wird die Tabelle vorher
            // und nachher befragt.
            $registerfloor = self::register_high_water_mark();
            $response = $manager->process_action($action);
            if (!$response->get_success()) {
                quota_manager::release($userid, $reservation);
                return $response;
            }

            if ($successvalidator !== null) {
                $successvalidator($response);
            }

            $responsedata = $response->get_response_data();
            $prompttokens = self::token_value($responsedata, 'prompttokens', $estimatedprompttokens);
            $completiontokens = self::token_value(
                $responsedata,
                'completiontokens',
                quota_manager::estimate_tokens((string) ($responsedata['generatedcontent'] ?? '')),
            );
            quota_manager::commit_action(
                $userid,
                $reservation,
                $actionname,
                $actionparams,
                $prompttokens,
                $completiontokens,
                $component,
            );
            $registerid = self::register_row_since($registerfloor, $action, $actionname, $userid);
            $turnid = self::record_turn(
                $action,
                $component,
                $actionname,
                $userid,
                $responsedata,
                $prompttokens,
                $completiontokens,
                $registerid,
            );
            $provenanceuuid = self::record_provenance(
                $component,
                $actionname,
                $userid,
                $action,
                $responsedata,
                $registerid,
                $turnid,
            );
            return $response;
        } catch (\Throwable $exception) {
            quota_manager::release($userid, $reservation);
            throw $exception;
        } finally {
            self::$insuitecall = max(0, self::$insuitecall - 1);
        }
    }

    /**
     * Whether a suite call is on the stack right now.
     *
     * Asked by `aiprovider_eledia`, which records a provenance receipt for
     * everything it generates -- including calls that never came through this
     * suite. When the suite is the caller, the receipt is written here
     * instead, because only here are the component, the turn and the register
     * row known. A counter rather than a flag: an action that triggers another
     * must not clear the mark on its way out.
     *
     * @return bool
     */
    public static function in_suite_call(): bool {
        return self::$insuitecall > 0;
    }

    /**
     * The highest id Moodle's action register currently holds.
     *
     * Taken before the call so the row the call produces can be found
     * afterwards. Returns 0 when the register does not exist -- on Moodle 4.5
     * there is no such table, and the suite still runs there.
     *
     * @return int
     */
    private static function register_high_water_mark(): int {
        global $DB;

        try {
            if (!$DB->get_manager()->table_exists('ai_action_register')) {
                return 0;
            }

            return (int) $DB->get_field_sql(
                'SELECT COALESCE(MAX(id), 0) FROM {ai_action_register}'
            );
        } catch (\Throwable $e) {
            unset($e);
            return 0;
        }
    }

    /**
     * The register row this call just produced, if it can be identified.
     *
     * The core writes the row inside `process_action()` and keeps its id to
     * itself, so it is looked for afterwards: newer than the mark taken before
     * the call, and matching the person, the place and the action. Within one
     * call the core writes at most one row per provider attempt, and the last
     * of them is the one whose result was returned.
     *
     * This is a heuristic, and deliberately a narrow one. For it to pick the
     * wrong row, the same person would have to run the same action in the same
     * context from a second request in the same instant. The cost if it ever
     * happened is one row missing from the register view -- not a wrong
     * number, not a lost record.
     *
     * @param int $floor The highest id before the call.
     * @param base $action The action that was processed.
     * @param string $actionname Its basename.
     * @param int $userid The acting user.
     * @return int|null
     */
    private static function register_row_since(
        int $floor,
        base $action,
        string $actionname,
        int $userid
    ): ?int {
        global $DB;

        try {
            if (!$DB->get_manager()->table_exists('ai_action_register')) {
                return null;
            }

            $contextid = (int) ($action->get_configuration('contextid') ?? 0);
            $id = $DB->get_field_sql(
                "SELECT MAX(id)
                   FROM {ai_action_register}
                  WHERE id > :floor
                    AND actionname = :actionname
                    AND userid = :userid
                    AND contextid = :contextid",
                [
                    'floor' => $floor,
                    'actionname' => $actionname,
                    'userid' => $userid,
                    'contextid' => $contextid,
                ]
            );

            return $id ? (int) $id : null;
        } catch (\Throwable $e) {
            // Ein fehlender Verweis kostet eine Zeile doppelt in der Ansicht.
            // Eine Ausnahme hier koestete die KI-Antwort.
            unset($e);
            return null;
        }
    }

    /**
     * Record that this output came from a model, and return its receipt.
     *
     * Art. 50 Abs. 2 asks for generative output to be marked machine-readably.
     * The visible mark is applied where the output is rendered -- only that
     * surface knows what is HTML and what is a plain string -- but the record
     * it points at belongs here, at the one place every AI call of the suite
     * passes through, and the only place that knows the component, the turn
     * and the register row.
     *
     * Before this, four writers recorded receipts at three levels, and a
     * question graded through `aiprovider_eledia` collected two for one
     * answer. The provider now stands back while {@see in_suite_call()} is
     * true and keeps its own writing for calls that never came through here.
     *
     * Soft on purpose: without local_aitransparency there is no record and no
     * uuid, and every caller degrades to unmarked output rather than failing.
     *
     * @param string $component Frankenstyle of the calling feature.
     * @param string $actionname Core action name.
     * @param int $userid Acting user.
     * @param base $action The action, for its context.
     * @param array $responsedata The response payload.
     * @param int|null $registerid The row in Moodle's own register.
     * @param int|null $turnid The turn this belongs to.
     * @return string|null The record's uuid, or null when none was written.
     */
    private static function record_provenance(
        string $component,
        string $actionname,
        int $userid,
        base $action,
        array $responsedata,
        ?int $registerid,
        ?int $turnid
    ): ?string {
        if (!class_exists('\\local_aitransparency\\provenance')) {
            return null;
        }

        $content = (string) ($responsedata['generatedcontent'] ?? '');
        if (trim($content) === '') {
            // Nichts erzeugt, nichts nachzuweisen.
            return null;
        }

        try {
            return \local_aitransparency\provenance::record(
                new \local_aitransparency\record_request(
                    component: $component,
                    actionname: $actionname,
                    provider: (string) ($responsedata['provider'] ?? ''),
                    model: (string) ($responsedata['model'] ?? ''),
                    userid: $userid,
                    contextid: (int) ($action->get_configuration('contextid') ?? 0),
                    assettype: 'text',
                    content: $content,
                    registerid: $registerid,
                    turnid: $turnid,
                )
            );
        } catch (\Throwable $e) {
            debugging(
                'local_elediaai_core: provenance record failed: ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            return null;
        }
    }

    /**
     * Put a successful action into the suite's own turn log.
     *
     * This is the reason the log is cheap: every AI call of the suite already
     * passes here, a test keeps it that way, and at this point the component,
     * the context, the prompt, the answer and the measured tokens are all in
     * hand. Thirteen plugins are covered by one call.
     *
     * The chat engine is not covered here: it goes through
     * {@see process_callback()}, which has no context and knows nothing about
     * grounding or topic. It writes its own turn, with those fields filled in,
     * from `local_elediaai_chatengine\chat_service`.
     *
     * @param base $action The action that ran.
     * @param string $component Frankenstyle of the calling feature.
     * @param string $actionname Core action name.
     * @param int $userid Acting user.
     * @param array $responsedata Response payload from core.
     * @param int $prompttokens Measured prompt tokens.
     * @param int $completiontokens Measured completion tokens.
     * @param int|null $registerid The row in Moodle's own register, if known.
     * @return int|null The turn's id, when one was written.
     */
    private static function record_turn(
        base $action,
        string $component,
        string $actionname,
        int $userid,
        array $responsedata,
        int $prompttokens,
        int $completiontokens,
        ?int $registerid = null
    ): ?int {
        $prompt = '';
        $contextid = 0;
        try {
            $prompt = (string) ($action->get_configuration('prompttext') ?? '');
            $contextid = (int) ($action->get_configuration('contextid') ?? 0);
        } catch (\Throwable $e) {
            // An action type without those keys still gets a row: a turn
            // without its prompt is worth more than no turn at all.
            unset($e);
        }

        return turn_recorder::record(
            component: $component,
            actionname: $actionname,
            contextid: $contextid,
            userid: $userid,
            prompt: $prompt,
            response: (string) ($responsedata['generatedcontent'] ?? ''),
            success: true,
            prompttokens: $prompttokens,
            completiontokens: $completiontokens,
            origin: turn_recorder::ORIGIN_GENERAL,
            model: isset($responsedata['model']) ? (string) $responsedata['model'] : null,
            registerid: $registerid,
        );
    }

    /**
     * Read a non-negative provider token count or use its fallback.
     *
     * @param array $responsedata
     * @param string $key
     * @param int $fallback
     * @return int
     */
    private static function token_value(array $responsedata, string $key, int $fallback): int {
        if (!isset($responsedata[$key]) || !is_numeric($responsedata[$key])) {
            return max(0, $fallback);
        }
        return max(0, (int) $responsedata[$key]);
    }
}

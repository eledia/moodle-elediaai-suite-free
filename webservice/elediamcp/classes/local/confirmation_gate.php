<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Server-side confirmation of write tools.
 *
 * @package     webservice_elediamcp
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace webservice_elediamcp\local;

/**
 * Whether a person actually agreed to a write, decided by Moodle (#168).
 *
 * The write tools have a two-step contract: without `confirm` they show a
 * preview, with `confirm=true` they act. Until now the agent's model alone
 * decided when to send `confirm=true`, and on 22.10.2026 it did so without
 * asking (Moodle_Agent#35): an assignment was created straight away, and a
 * question bank after a "Ja" to a question asked in plain text.
 *
 * Now Moodle keeps its own record, without any token travelling through the
 * agent:
 *
 *  1. {@see observe()} remembers every preview a tool hands out: person, tool,
 *     fingerprint of the arguments, time.
 *  2. {@see answer_shown()} notes which tool the confirmation card of an
 *     answer was for. {@see user_turn()} is called by the chat engine for
 *     every message the person sends. A click on the card, or a message that
 *     is nothing but an agreement ("ja", "ok, mach das"), approves exactly one
 *     preview of the previous answer: the only one, or the one the card
 *     names. Anything else discards them -- "ja, aber nimm drei Fragen" is a
 *     new request, not an agreement.
 *  3. {@see guard()} runs before every tool call. `confirm=true` only passes
 *     with an approved preview for the same person, tool and arguments, at
 *     most {@see WINDOW} seconds old, and once. Otherwise `confirm` is turned
 *     off and the tool answers with its preview again.
 *
 * Tools that reach other people or cannot be taken back cleanly need the
 * click; a typed agreement does not approve them ({@see CARD_ONLY}).
 *
 * Only calls with a token the chat engine issued are gated
 * ({@see GATED_COMPONENT}): only there does Moodle see the person's answer.
 *
 * The state lives in the application cache. Losing it (purge, restart) can
 * only cost a second preview, never let a write through.
 */
final class confirmation_gate {
    /**
     * The token owner whose calls are gated.
     *
     * The chat engine hands its agent a token it issued itself, and it is the
     * chat engine that reports the person's messages to {@see user_turn()}.
     * Other MCP clients -- a personal token, an OAuth app like Claude Desktop
     * -- have no such channel; gating them would make every write impossible.
     * Their own client asks the person before it calls.
     */
    public const GATED_COMPONENT = 'local_elediaai_chatengine';

    /** @var int Seconds an approval or a preview stays valid. */
    public const WINDOW = 600;

    /** @var string[] Tools a typed agreement does not approve: they need the card. */
    public const CARD_ONLY = [
        'moodle_send_message',
        'moodle_message_course_students',
        'moodle_grade_submission',
    ];

    /**
     * Keys left out of the fingerprint.
     *
     * `confirm` is the very thing being decided. The draft and job tokens are
     * handed out by the preview and added to the confirming call (#43); the
     * tool checks them itself.
     */
    private const IGNORED_KEYS = ['confirm', 'draft_token', 'job_token'];

    /**
     * Words a pure agreement may consist of.
     *
     * Every word of the message has to be one of these, and at least one of
     * {@see CORE_WORDS}. That keeps "ja, aber nimm drei" and "nein" out.
     */
    private const AGREEMENT_WORDS = [
        'ja', 'jawohl', 'jo', 'jep', 'jup', 'genau', 'richtig', 'klar', 'gerne', 'gern', 'bitte',
        'ok', 'okay', 'okey', 'oki', 'passt', 'einverstanden', 'mach', 'mache', 'machen', 'das', 'es',
        'so', 'los', 'weiter', 'fortfahren', 'ausführen', 'ausfuehren', 'durchführen', 'durchfuehren',
        'bestätigen', 'bestaetigen', 'bestätige', 'bestaetige', 'bestätigt', 'bestaetigt',
        'anlegen', 'erstellen', 'erzeugen', 'speichern', 'gut', 'super', 'perfekt', 'natürlich', 'sicher',
        'yes', 'yep', 'yeah', 'sure', 'please', 'do', 'it', 'go', 'ahead', 'confirm', 'confirmed',
        'proceed', 'alright', 'fine', 'create', 'that', 'sounds', 'good', 'great',
    ];

    /** @var string[] At least one of these must be in the message. */
    private const CORE_WORDS = [
        'ja', 'jawohl', 'jo', 'jep', 'jup', 'genau', 'klar', 'gerne', 'gern', 'ok', 'okay', 'okey', 'oki',
        'passt', 'einverstanden', 'mach', 'mache', 'los', 'fortfahren', 'ausführen', 'ausfuehren',
        'durchführen', 'durchfuehren', 'bestätigen', 'bestaetigen', 'bestätige', 'bestaetige',
        'bestätigt', 'bestaetigt', 'yes', 'yep', 'yeah', 'sure', 'go', 'confirm', 'confirmed', 'proceed', 'alright',
    ];

    /** @var int More words than this are a new request, not an agreement. */
    private const MAX_WORDS = 5;

    /**
     * Gate a tool call before it runs.
     *
     * @param string $tool The tool name.
     * @param array $arguments The arguments as the agent sent them.
     * @param int $userid The person the call acts for.
     * @return array{0: array, 1: bool} The arguments to run with, and whether
     *         `confirm` was withheld for lack of an approval.
     */
    public static function guard(string $tool, array $arguments, int $userid): array {
        if (empty($arguments['confirm'])) {
            return [$arguments, false];
        }
        if (self::consume($userid, $tool, self::fingerprint($arguments))) {
            return [$arguments, false];
        }
        $arguments['confirm'] = false;
        return [$arguments, true];
    }

    /**
     * Remember a preview a tool has just handed out.
     *
     * @param string $tool The tool name.
     * @param array $arguments The arguments the preview was made with.
     * @param mixed $output The tool's structured output.
     * @param int $userid The person the call acted for.
     */
    public static function observe(string $tool, array $arguments, mixed $output, int $userid): void {
        if (!is_array($output) || empty($output['requires_confirmation']) || $userid <= 0) {
            return;
        }
        $fingerprints = [self::fingerprint($arguments)];
        // LiteRAG confirms a message by the resolved recipient, not by the
        // name it was asked for: the preview tells which person it found.
        $recipient = (int) ($output['recipient']['id'] ?? 0);
        if ($tool === 'moodle_send_message' && $recipient > 0 && isset($arguments['message'])) {
            $fingerprints[] = self::fingerprint(['to_user_id' => $recipient, 'message' => (string) $arguments['message']]);
        }

        $state = self::load($userid);
        $previewid = random_int(1, PHP_INT_MAX);
        foreach ($fingerprints as $fingerprint) {
            $state['entries'][] = [
                'tool' => $tool, 'fp' => $fingerprint, 'time' => time(), 'approved' => false, 'preview' => $previewid,
            ];
        }
        self::save($userid, $state);
    }

    /**
     * Take note of which tool an answer's confirmation card was for.
     *
     * Called by the chat engine after every answer. The card names the tool
     * by its title (or name); that is matched against the previews of this
     * answer, so a click later approves the preview the person actually saw
     * and not a second one the model produced alongside it.
     *
     * @param int $userid The person (the account the backend acts for).
     * @param array|null $confirmation The answer's confirmation payload, or null for none.
     */
    public static function answer_shown(int $userid, ?array $confirmation): void {
        if ($userid <= 0) {
            return;
        }
        $state = self::load($userid);
        $state['cardtool'] = null;
        $label = trim((string) ($confirmation['tooltitle'] ?? ''));
        if (!empty($confirmation['required']) && $label !== '') {
            $tools = [];
            foreach ($state['entries'] as $entry) {
                if ((int) $entry['time'] >= (int) $state['lastturn'] && self::tool_answers_to($entry['tool'], $label)) {
                    $tools[$entry['tool']] = true;
                }
            }
            $state['cardtool'] = count($tools) === 1 ? (string) array_key_first($tools) : null;
        }
        self::save($userid, $state);
    }

    /**
     * Take note of a message the person sent.
     *
     * Approves one preview of the previous answer when the message is an
     * agreement, discards all of them otherwise. Approvals that were not used
     * by the next message lapse as well: an agreement belongs to one answer.
     *
     * Which preview: the only one the answer produced; with several, the one
     * of the tool the card named, if that is exactly one preview. Anything
     * still ambiguous approves nothing, and the agent gets a preview back --
     * one click must never carry an action the person did not see on a card.
     *
     * @param int $userid The person (the account the backend acts for).
     * @param string $message The message as sent.
     * @param string $decision 'confirm' or 'decline' from the confirmation card, or ''.
     */
    public static function user_turn(int $userid, string $message, string $decision = ''): void {
        if ($userid <= 0) {
            return;
        }
        $state = self::load($userid);
        $now = time();
        $previous = (int) $state['lastturn'];
        $card = $decision === 'confirm';
        $typed = $decision === '' && self::is_agreement($message);

        // What the last answer previewed, still within the window; older
        // entries and unused approvals had their turn.
        $candidates = [];
        foreach ($state['entries'] as $entry) {
            if ((int) $entry['time'] >= $previous && (int) $entry['time'] >= $now - self::WINDOW && !$entry['approved']) {
                $candidates[] = $entry;
            }
        }
        if ($typed) {
            $candidates = array_values(array_filter(
                $candidates,
                fn(array $entry) => !in_array($entry['tool'], self::CARD_ONLY, true)
            ));
        }

        $kept = [];
        if ($card || $typed) {
            $chosen = self::single_preview($candidates, $state['cardtool'] ?? null);
            if ($chosen !== null) {
                $chosen['approved'] = true;
                $chosen['time'] = $now;
                $kept[] = $chosen;
            }
        }
        self::save($userid, ['lastturn' => $now, 'entries' => $kept, 'cardtool' => null]);
    }

    /**
     * The one preview an agreement stands for, or null when that is not clear.
     *
     * Two fingerprints of one preview (the send_message alias) count as one.
     *
     * @param array $candidates Previews of the previous answer.
     * @param string|null $cardtool The tool the card named, if known.
     * @return array|null
     */
    private static function single_preview(array $candidates, ?string $cardtool): ?array {
        $bytool = [];
        foreach ($candidates as $entry) {
            $bytool[$entry['tool']][(int) ($entry['preview'] ?? 0)][] = $entry;
        }
        if (count($bytool) > 1) {
            if ($cardtool === null || !isset($bytool[$cardtool])) {
                return null;
            }
            $bytool = [$cardtool => $bytool[$cardtool]];
        }
        $previews = $bytool ? reset($bytool) : [];
        if (count($previews) !== 1) {
            return null;
        }
        // One preview may have two fingerprints (the send_message alias); the
        // approval carries the others as aliases.
        $entries = reset($previews);
        $first = array_shift($entries);
        $first['aliases'] = array_column($entries, 'fp');
        return $first;
    }

    /**
     * The confirmation card's title in the person's language.
     *
     * The agents put the tool's title on the card, and that is the English
     * title of the MCP catalogue ("Send a personal Moodle message") -- on a
     * German page it stood out. The catalogue stays English for every MCP
     * client; only the card is translated, by the chat engine, just before it
     * reaches the browser.
     *
     * @param string $label The card's tooltitle: a tool title or name.
     * @return string|null The translated title, or null when the tool or a string for it is unknown.
     */
    public static function display_title(string $label): ?string {
        $label = trim($label);
        if ($label === '') {
            return null;
        }
        foreach (\webservice_elediamcp\local\ai\registry::all() as $class) {
            $name = (string) $class::name();
            if (!self::tool_answers_to($name, $label)) {
                continue;
            }
            $key = 'cardtitle_' . $name;
            return get_string_manager()->string_exists($key, 'webservice_elediamcp')
                ? get_string($key, 'webservice_elediamcp') : null;
        }
        return null;
    }

    /**
     * Whether a card label names this tool.
     *
     * @param string $tool The tool name.
     * @param string $label The card's tooltitle.
     * @return bool
     */
    private static function tool_answers_to(string $tool, string $label): bool {
        if (strcasecmp($tool, $label) === 0) {
            return true;
        }
        // The registry, not tool_provider: naming a card has nothing to do with
        // whether this edition offers the tool.
        $class = \webservice_elediamcp\local\ai\registry::find($tool);
        if ($class === null) {
            return false;
        }
        $titles = [(string) $class::title(), (string) ($class::annotations()['title'] ?? '')];
        foreach ($titles as $title) {
            if ($title !== '' && \core_text::strtolower($title) === \core_text::strtolower($label)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether a message is nothing but an agreement.
     *
     * @param string $message The message as typed.
     * @return bool
     */
    public static function is_agreement(string $message): bool {
        $words = preg_split('/[^\p{L}\p{N}]+/u', \core_text::strtolower(trim($message)), -1, PREG_SPLIT_NO_EMPTY);
        if (!$words || count($words) > self::MAX_WORDS) {
            return false;
        }
        foreach ($words as $word) {
            if (!in_array($word, self::AGREEMENT_WORDS, true)) {
                return false;
            }
        }
        return array_intersect($words, self::CORE_WORDS) !== [];
    }

    /**
     * Fingerprint of a call's arguments, independent of key order.
     *
     * @param array $arguments The arguments.
     * @return string
     */
    public static function fingerprint(array $arguments): string {
        foreach (self::IGNORED_KEYS as $key) {
            unset($arguments[$key]);
        }
        return hash('sha256', json_encode(self::normalise($arguments), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Use up an approval for this exact call.
     *
     * @param int $userid The person.
     * @param string $tool The tool name.
     * @param string $fingerprint Fingerprint of the arguments.
     * @return bool Whether one was there.
     */
    private static function consume(int $userid, string $tool, string $fingerprint): bool {
        if ($userid <= 0) {
            return false;
        }
        $state = self::load($userid);
        foreach ($state['entries'] as $index => $entry) {
            $matches = $entry['fp'] === $fingerprint || in_array($fingerprint, $entry['aliases'] ?? [], true);
            if (
                $entry['approved'] && $entry['tool'] === $tool && $matches
                && (int) $entry['time'] >= time() - self::WINDOW
            ) {
                unset($state['entries'][$index]);
                $state['entries'] = array_values($state['entries']);
                self::save($userid, $state);
                return true;
            }
        }
        return false;
    }

    /**
     * Sort maps by key, recursively; lists keep their order.
     *
     * @param mixed $value Any argument value.
     * @return mixed
     */
    private static function normalise(mixed $value): mixed {
        if (!is_array($value)) {
            return $value;
        }
        if (!array_is_list($value)) {
            ksort($value);
        }
        return array_map([self::class, 'normalise'], $value);
    }

    /**
     * The person's state.
     *
     * @param int $userid The person.
     * @return array{lastturn: int, entries: array, cardtool: ?string}
     */
    private static function load(int $userid): array {
        $state = \cache::make('webservice_elediamcp', 'confirmations')->get($userid);
        $empty = ['lastturn' => 0, 'entries' => [], 'cardtool' => null];
        return is_array($state) ? $state + $empty : $empty;
    }

    /**
     * Store the person's state.
     *
     * @param int $userid The person.
     * @param array $state The state.
     */
    private static function save(int $userid, array $state): void {
        \cache::make('webservice_elediamcp', 'confirmations')->set($userid, $state);
    }
}

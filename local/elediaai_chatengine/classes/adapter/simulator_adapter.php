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

namespace local_elediaai_chatengine\adapter;

/**
 * A backend that answers without a language model.
 *
 * For working on the chat surfaces: layout, streaming, source cards, error
 * states, the confirmation card. All of those are hard to reach reliably
 * through a real backend, which answers differently every time and needs a
 * key, a network and a bill.
 *
 * It is **not** a test double in the PHPUnit sense. The fixtures under
 * `tests/fixtures/` stay where they are; this one is reachable from the
 * browser, and everything about it is built so that nobody can mistake its
 * answers for real ones: every reply carries a marker, and the setting that
 * switches it on says plainly what it does.
 *
 * What it answers is steered by keywords in the message, so a person working
 * on the interface can walk through the states one by one instead of hoping
 * one turns up. `/hilfe` (or `/help`) lists them.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class simulator_adapter implements adapter {
    /** @var string The setting that switches this backend on. */
    public const SETTING = 'simulator';

    /** @var int Milliseconds between fragments in the slow variant. */
    private const SLOW_DELAY_MS = 220;

    /**
     * Whether an administrator has switched the simulator on.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        return (bool) get_config('local_elediaai_chatengine', self::SETTING);
    }

    #[\Override]
    public static function id(): string {
        return 'simulator';
    }

    #[\Override]
    public static function name(): string {
        return get_string('backend_simulator', 'local_elediaai_chatengine');
    }

    #[\Override]
    public function is_available(): bool {
        return self::is_enabled();
    }

    /**
     * Everything, because there is nothing to be unable to do.
     *
     * Reporting the full set is the point: the surfaces under test decide what
     * they offer from these flags, and a simulator that reported less would
     * hide exactly the controls someone wants to look at.
     *
     * @return capabilities The capability report.
     */
    #[\Override]
    public function capabilities(): capabilities {
        return new capabilities(
            supportsungrounded: true,
            supportstools: true,
            supportsstreaming: true,
            stateful: true,
        );
    }

    /**
     * Healthy, and saying loudly that it is not real.
     *
     * @return health The health report.
     */
    #[\Override]
    public function health(): health {
        return new health(true, get_string('status_simulator_active', 'local_elediaai_chatengine'));
    }

    /**
     * The other tools of the contract, answered plausibly and without state.
     *
     * @param string $tool The logical tool key.
     * @param array $arguments Tool arguments.
     * @param int $userid The acting user id.
     * @return array The normalised tool result.
     */
    #[\Override]
    public function call_tool(string $tool, array $arguments, int $userid): array {
        return [
            'answer' => '',
            'conversation_id' => (string) ($arguments['conversation_id'] ?? ''),
            'sources' => [],
            'topic' => null,
            'answer_origin' => 'general',
            'confirmation' => null,
            'iserror' => false,
            'prompttokens' => null,
            'completiontokens' => null,
            'messages' => [],
            'structured' => [],
        ];
    }

    #[\Override]
    public function chat(chat_request $request): chat_response {
        $command = self::command($request->usermessage);
        $convkey = ($request->convkey !== null && $request->convkey !== '')
            ? $request->convkey
            : 'sim-' . substr(sha1((string) $request->userid . '|' . time()), 0, 12);

        if ($command === 'error') {
            // Returned as an ordinary error response rather than thrown: this
            // is how a backend reports that it could not answer, and the error
            // state of the panel is what someone wants to look at.
            return new chat_response(
                answer: $this->mark(get_string('simulator_error_body', 'local_elediaai_chatengine')),
                convkey: $convkey,
                iserror: true,
            );
        }

        $body = $this->body($command, $request);
        $answer = $this->mark($body);
        $this->stream($answer, $request, $command === 'slow');

        return new chat_response(
            answer: $answer,
            convkey: $convkey,
            sources: $command === 'sources' ? $this->sources() : [],
            origin: $command === 'sources' ? chat_response::ORIGIN_GROUNDED : chat_response::ORIGIN_GENERAL,
            topic: get_string('simulator_topic', 'local_elediaai_chatengine'),
            confirmation: $command === 'confirm' ? $this->confirmation() : null,
            prompttokens: $command === 'tokens' ? 1234 : null,
            completiontokens: $command === 'tokens' ? 567 : null,
        );
    }

    /**
     * Put the marker in front of every answer.
     *
     * Not a nicety. A simulated answer that looks like a real one is a trap for
     * whoever sees the screen next, and screenshots outlive the session they
     * were taken in.
     *
     * @param string $body The answer body in Markdown.
     * @return string The marked answer.
     */
    private function mark(string $body): string {
        return '> ⚠️ ' . get_string('simulator_marker', 'local_elediaai_chatengine') . "\n\n" . $body;
    }

    /**
     * Which variant a message asks for.
     *
     * @param string $message The learner's message.
     * @return string One of help|sources|error|slow|long|confirm|tokens|default.
     */
    private static function command(string $message): string {
        $known = ['help', 'hilfe', 'sources', 'quellen', 'error', 'fehler',
            'slow', 'langsam', 'long', 'lang', 'confirm', 'bestaetigen', 'tokens'];
        if (!preg_match('~(?:^|\s)/([a-zA-Z]+)~', $message, $found)) {
            return 'default';
        }
        $word = \core_text::strtolower($found[1]);
        if (!in_array($word, $known, true)) {
            return 'default';
        }

        return match ($word) {
            'hilfe' => 'help',
            'quellen' => 'sources',
            'fehler' => 'error',
            'langsam' => 'slow',
            'lang' => 'long',
            'bestaetigen' => 'confirm',
            default => $word,
        };
    }

    /**
     * The answer body for one variant.
     *
     * @param string $command The variant.
     * @param chat_request $request The turn, so the answer can quote it back.
     * @return string Markdown.
     */
    private function body(string $command, chat_request $request): string {
        $echo = \core_text::substr(trim($request->usermessage), 0, 200);

        return match ($command) {
            'help' => get_string('simulator_help', 'local_elediaai_chatengine'),
            'long' => get_string('simulator_long', 'local_elediaai_chatengine')
                . "\n\n" . str_repeat(get_string('simulator_long_para', 'local_elediaai_chatengine') . "\n\n", 8),
            'sources' => get_string('simulator_sources_body', 'local_elediaai_chatengine'),
            'confirm' => get_string('simulator_confirm_body', 'local_elediaai_chatengine'),
            'tokens' => get_string('simulator_tokens_body', 'local_elediaai_chatengine'),
            'slow' => get_string('simulator_slow_body', 'local_elediaai_chatengine'),
            default => get_string('simulator_default', 'local_elediaai_chatengine', (object) [
                'message' => s($echo),
                'mode' => $request->mode,
                'prompt' => $request->systempromptid,
                'tools' => $request->allowtools
                    ? get_string('yes')
                    : get_string('no'),
            ]),
        };
    }

    /**
     * Hand the answer out fragment by fragment, the way a streaming backend does.
     *
     * Word by word rather than character by character: that is roughly the
     * granularity a real token stream arrives in, and it is what the typing
     * behaviour of the panel should be judged against.
     *
     * @param string $answer The complete answer.
     * @param chat_request $request The turn, carrying the callback.
     * @param bool $slow Whether to pause between fragments.
     * @return void
     */
    private function stream(string $answer, chat_request $request, bool $slow): void {
        $ondelta = $request->ondelta;
        if (!is_callable($ondelta)) {
            return;
        }

        foreach (preg_split('~(?<=\s)~u', $answer) ?: [] as $fragment) {
            if ($fragment === '') {
                continue;
            }
            $ondelta($fragment, false);
            if ($slow) {
                usleep(self::SLOW_DELAY_MS * 1000);
            }
        }
    }

    /**
     * Three source cards, shaped like the contract's.
     *
     * @return array<int, array<string, string>>
     */
    private function sources(): array {
        global $CFG;

        $titles = [
            get_string('simulator_source_one', 'local_elediaai_chatengine'),
            get_string('simulator_source_two', 'local_elediaai_chatengine'),
            get_string('simulator_source_three', 'local_elediaai_chatengine'),
        ];

        $sources = [];
        foreach ($titles as $index => $title) {
            $sources[] = [
                'title' => $title,
                'url' => $CFG->wwwroot . '/#simulated-source-' . ($index + 1),
                'snippet' => get_string('simulator_source_snippet', 'local_elediaai_chatengine'),
            ];
        }

        return $sources;
    }

    /**
     * A pending write, so the confirmation card can be looked at.
     *
     * Nothing is ever carried out: the simulator has no Moodle tools behind it,
     * and a confirmation that did something would make this backend dangerous
     * rather than merely fake.
     *
     * @return array<string, mixed>
     */
    private function confirmation(): array {
        return [
            'tool' => 'moodle_send_message',
            'summary' => get_string('simulator_confirm_summary', 'local_elediaai_chatengine'),
            'arguments' => [],
        ];
    }
}

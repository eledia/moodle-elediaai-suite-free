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

namespace local_elediaai_chatengine\local;

use local_elediaai_chatengine\chat_service;

/**
 * One streamed turn, from the headers to the terminating frame.
 *
 * Moodle's external API is request and response, so an answer can only be shown
 * while it is produced through an endpoint that holds the connection open. What
 * such an endpoint has to get right is not the chat — that is
 * {@see chat_service::send()} — but the transport around it: the headers that
 * stop a proxy from buffering, releasing the session lock, flushing every
 * fragment, and the frame names the backend contract uses
 * (`rag_server_spec` B.2.1).
 *
 * That is collected here so a placement keeping its own endpoints — which the
 * contract invites (docs/contract.md §6) — inherits the protocol instead of
 * reimplementing it. Two implementations of the same frame names would drift,
 * and the browser would only find out mid-answer.
 *
 * The caller stays responsible for what only it knows: reading its parameters,
 * requiring login and the session key, resolving its placement, and setting the
 * page context.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stream_runner {
    /**
     * Run one turn and emit it as server-sent events.
     *
     * Never throws once the first frame is out: after the headers there is no
     * status code left to fail with, so a failure is reported as an `error`
     * frame and the stream is terminated properly. A refusal *before* that —
     * streaming switched off — is thrown, so the browser sees an ordinary
     * error and falls back to the buffered call.
     *
     * @param string $component The placement's frankenstyle component.
     * @param int $instanceid The placement's own instance id.
     * @param int $userid The acting user id.
     * @param string $message The message as typed.
     * @param \context $context The context the turn happens in; decides who is
     *        told why a turn failed.
     * @param int|null $threadid Continue this conversation, or null for the current one.
     * @param array $clienthints Hints the client asked for; checked by the engine.
     * @param array $options Hints the calling code supplies server-side.
     * @param bool $newthread Start a fresh conversation instead of continuing the current one.
     * @return void
     * @throws \moodle_exception When streaming is not available at all.
     */
    public static function run(
        string $component,
        int $instanceid,
        int $userid,
        string $message,
        \context $context,
        ?int $threadid = null,
        array $clienthints = [],
        array $options = [],
        bool $newthread = false
    ): void {
        if (!connection::streaming_enabled()) {
            throw new \moodle_exception('error_backend_unavailable', 'local_elediaai_chatengine');
        }

        self::open();

        try {
            $result = chat_service::send(
                $component,
                $instanceid,
                $userid,
                $message,
                $threadid,
                null,
                $options,
                static function (string $delta, bool $reset = false): void {
                    if ($reset) {
                        // The turn is being retried; discard what was rendered so far.
                        self::frame('reset', ['delta' => '']);
                        return;
                    }
                    self::frame('token', ['delta' => $delta]);
                },
                null,
                $clienthints,
                $newthread
            );

            self::frame('final', [
                'answerhtml' => $result['answerhtml'],
                'threadid' => $result['threadid'],
                'iserror' => $result['iserror'],
                'origin' => $result['origin'],
                'sources' => array_map(static fn(array $source): array => [
                    'title' => (string) ($source['title'] ?? ''),
                    'url' => clean_param((string) ($source['url'] ?? ''), PARAM_URL),
                    'snippet' => (string) ($source['snippet'] ?? ''),
                ], $result['sources']),
                'confirmation' => is_array($result['confirmation'] ?? null) ? $result['confirmation'] : [],
            ]);
        } catch (\moodle_exception $e) {
            // A detailed reason only for people who can act on it; everybody else
            // gets the generic one, because a backend URL or a tool name in a
            // learner's chat window helps nobody and tells an attacker something.
            $candetail = has_capability('moodle/site:config', $context);
            self::frame('error', [
                'message' => $candetail
                    ? $e->getMessage()
                    : get_string('error_backend_unavailable', 'local_elediaai_chatengine'),
            ]);
        }

        self::frame('done', '[DONE]');
    }

    /**
     * Put the request into a state where fragments actually reach the browser.
     *
     * @return void
     */
    private static function open(): void {
        // The session is not written from here, and holding its lock would block
        // every other request this user makes for the whole generation.
        \core\session\manager::write_close();

        @set_time_limit(0);
        \core_php_time_limit::raise(0);

        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('X-Accel-Buffering: no');
        header('Connection: keep-alive');
        // Nothing here is cacheable or embeddable.
        header('X-Content-Type-Options: nosniff');
        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        // Stop generating once the learner has navigated away.
        ignore_user_abort(false);
    }

    /**
     * Write one server-sent frame and push it to the browser immediately.
     *
     * @param string $event Frame name.
     * @param mixed $data Payload; JSON-encoded unless it already is a string.
     * @return void
     */
    private static function frame(string $event, mixed $data): void {
        $payload = is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo 'event: ' . $event . "\n" . 'data: ' . $payload . "\n\n";
        // Without flushing, PHP and the web server hold the frame back and the
        // whole point of streaming is lost.
        if (ob_get_level() > 0) {
            @ob_flush();
        }
        flush();
    }
}

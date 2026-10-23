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

namespace local_elediaai_chatengine;

use local_elediaai_chatengine\adapter\adapter;
use local_elediaai_chatengine\adapter\adapter_exception;
use local_elediaai_chatengine\adapter\backend_refusal;
use local_elediaai_chatengine\adapter\capabilities;
use local_elediaai_chatengine\adapter\chat_request;
use local_elediaai_chatengine\adapter\chat_response;
use local_elediaai_chatengine\local\connection;
use local_elediaai_chatengine\local\course_scope;
use local_elediaai_chatengine\local\guard;
use local_elediaai_chatengine\local\hints;
use local_elediaai_chatengine\local\markdown_renderer;
use local_elediaai_chatengine\local\message;
use local_elediaai_chatengine\local\mode;
use local_elediaai_chatengine\local\prompt_safety;
use local_elediaai_chatengine\local\system_prompt;
use local_elediaai_chatengine\local\thread_store;
use local_elediaai_chatengine\local\token_provider;
use local_elediaai_chatengine\local\usage;
use local_elediaai_chatengine\placement\placement;
use local_elediaai_chatengine\placement\registry;
use local_elediaai_chatengine\placement\user_request_framing;

/**
 * Runs one chat turn, from the placement's request to a render-ready answer.
 *
 * The single path every surface takes. What is decided here — whether the mode
 * is permitted, what the backend is told, what is recorded, what is billed —
 * is decided once, which is the whole reason for extracting the engine. A
 * placement supplies context and asks; it does not orchestrate.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chat_service {
    /** @var string The MCP server's confirmation gate, present when webservice_elediamcp is installed (#168). */
    private const CONFIRMATION_GATE = '\\webservice_elediamcp\\local\\confirmation_gate';

    /**
     * Process one turn.
     *
     * @param string $component The placement's frankenstyle component.
     * @param int $instanceid The placement's own instance id, or 0 for site-wide.
     * @param int $userid The acting user id, or 0 for an unauthenticated visitor.
     * @param string $rawmessage The message as typed.
     * @param int|null $threadid Continue this conversation, or null for the current one.
     * @param string|null $guestkey Guest identifier when userid is 0.
     * @param array $options Extra backend hints from the caller, merged over the placement's.
     *        Server-side and trusted, unlike $clienthints.
     * @param callable|null $ondelta Called per streamed fragment as (string $text, bool $reset).
     * @param int|null $backenduserid The account the backend acts for - the one a callback token
     *        is issued for and the one the quota is booked against. Defaults to $userid. A guest
     *        surface supplies a nominated account here: the visitor owns the conversation but has
     *        no account to authenticate or to bill.
     * @param array $clienthints Hints the client asked the turn to carry, as a name => value map.
     *        Checked against {@see \local_elediaai_chatengine\local\hints} and then offered to
     *        the placement, which has the final word on every hint it answers for. Nothing here
     *        can widen what the turn is allowed to do; the mode, the backend and the tool
     *        permission are decided elsewhere and are not asked for.
     * @param bool $newthread Start a fresh conversation for this turn instead of continuing the
     *        caller's most recent one. Only meaningful when $threadid is null: a turn that names
     *        a conversation is asking for that one. The previous conversation is kept.
     * @return array{
     *     threadid: int, answerhtml: string, answermarkdown: string, sources: array,
     *     origin: string, confirmation: ?array, iserror: bool
     * }
     * @throws \moodle_exception On access, validation, quota or backend failure.
     */
    public static function send(
        string $component,
        int $instanceid,
        int $userid,
        string $rawmessage,
        ?int $threadid = null,
        ?string $guestkey = null,
        array $options = [],
        ?callable $ondelta = null,
        ?int $backenduserid = null,
        array $clienthints = [],
        bool $newthread = false
    ): array {
        $logged = false;
        try {
            return self::run_turn(
                $component,
                $instanceid,
                $userid,
                $rawmessage,
                $threadid,
                $guestkey,
                $options,
                $ondelta,
                $backenduserid,
                $clienthints,
                $newthread,
                $logged
            );
        } catch (\moodle_exception $e) {
            // A turn that never got an answer left no trace at all: no row
            // in the turn log, no error code, and the only evidence of a
            // customer's "Ungueltiger Kontext" was her screenshot (#37). It
            // is recorded now -- once, and not when the failure came after
            // the turn was already logged.
            if (!$logged) {
                self::record_rejection($component, $instanceid, $userid, $e);
            }
            throw $e;
        }
    }

    /**
     * Record a turn that was refused, in the suite's turn log.
     *
     * For the engine's own refusals and for a placement endpoint that refuses
     * before it hands the turn to the engine -- the tutor proves the surface
     * it was sent from first, and that is where #37 failed. The same fields as
     * an answered turn, with `success` off and the error code; neither the
     * question nor an answer is kept.
     *
     * Never allowed to mask the refusal it records: a failure to write the
     * log is reported to developers and swallowed.
     *
     * @param string $component The placement's frankenstyle component.
     * @param int $instanceid The placement instance, 0 for a site-wide surface.
     * @param int $userid Who asked; stored only as a pseudonym.
     * @param \moodle_exception $e Why it was refused.
     * @param \context|null $context Where it happened, when the caller knows; else
     *        the placement is asked, and failing that the system context is used.
     * @return void
     */
    public static function record_rejection(
        string $component,
        int $instanceid,
        int $userid,
        \moodle_exception $e,
        ?\context $context = null
    ): void {
        $recorder = '\\local_elediaai_core\\local\\turn_recorder';
        if (!class_exists($recorder)) {
            return;
        }

        try {
            if ($context === null) {
                try {
                    $context = registry::require_placement($component)->context($instanceid);
                } catch (\Throwable $ignored) {
                    $context = \context_system::instance();
                }
            }

            // The backend's own code says more than the engine's wrapper
            // around it: "tenant_not_found", not "error_backend_refused".
            $errorcode = $e instanceof backend_refusal
                ? 'backend:' . $e->backend_code()
                : $e->module . ':' . $e->errorcode;

            call_user_func([$recorder, 'record'], ...[
                'component' => $component,
                'actionname' => 'chat_turn',
                'contextid' => (int) $context->id,
                'userid' => $userid,
                // Not the question. A refused turn may have been refused
                // precisely because it must not be processed -- no consent,
                // no access -- and the code, place and time are what a
                // diagnosis needs.
                'prompt' => '',
                'response' => '',
                'success' => false,
                'errorcode' => $errorcode,
                'instanceid' => $instanceid,
            ]);
        } catch (\Throwable $failure) {
            debugging(
                'local_elediaai_chatengine: refused turn could not be logged: ' . $failure->getMessage(),
                DEBUG_DEVELOPER
            );
        }
    }

    /**
     * Run one turn; {@see send()} without the record of a refusal.
     *
     * @param string $component See {@see send()}.
     * @param int $instanceid See {@see send()}.
     * @param int $userid See {@see send()}.
     * @param string $rawmessage See {@see send()}.
     * @param int|null $threadid See {@see send()}.
     * @param string|null $guestkey See {@see send()}.
     * @param array $options See {@see send()}.
     * @param callable|null $ondelta See {@see send()}.
     * @param int|null $backenduserid See {@see send()}.
     * @param array $clienthints See {@see send()}.
     * @param bool $newthread See {@see send()}.
     * @param bool $logged Set once the turn is in the turn log.
     * @return array See {@see send()}.
     * @throws \moodle_exception On access, validation, quota or backend failure.
     */
    private static function run_turn(
        string $component,
        int $instanceid,
        int $userid,
        string $rawmessage,
        ?int $threadid,
        ?string $guestkey,
        array $options,
        ?callable $ondelta,
        ?int $backenduserid,
        array $clienthints,
        bool $newthread,
        bool &$logged
    ): array {
        // Who holds the conversation and who the backend acts for are two
        // different questions. They coincide for a logged-in learner and come
        // apart for a guest, who owns the thread but has no account to issue a
        // callback token for or to bill.
        $backenduserid ??= $userid;
        $placement = registry::require_placement($component);
        $placement->require_access($instanceid, $userid);
        $placement->require_send($instanceid, $userid);

        $message = guard::validate_message($rawmessage);
        guard::enforce_rate_limit($userid, $guestkey);
        $dailylimit = $placement->daily_limit($instanceid) ?? connection::daily_message_limit();
        usage::assert_within_limit($backenduserid, $dailylimit);

        $adapter = backend_resolver::require_active();
        $capabilities = $adapter->capabilities();
        $turnmode = self::resolve_mode($placement->mode($instanceid), $capabilities);

        $context = $placement->context($instanceid);
        $persona = $placement->persona($instanceid);

        // Which courses this person may be answered from. Resolved here rather
        // than left to the backend: the enrolment is a Moodle fact, and the
        // backend filters on what it is handed (operator decision 20.09.2026,
        // spec A.1 "Entitlement").
        //
        // It does not touch the mode. Nothing entitled means no course material
        // -- it does not mean no retrieval: the backend also holds a corpus that
        // has nothing to do with enrolments (the Moodle documentation), and
        // rag_enabled: false would shut that off too. It would also relabel an
        // answer read out of Moodle through a tool as the model's own. The
        // first version of this did exactly that, and the history tests said so.
        $scope = course_scope::for_turn(
            $placement->courseid($instanceid),
            $userid,
            $placement->knowledge_scope($instanceid)
        );
        if ($turnmode === mode::GROUNDED && $scope->is_exhausted()) {
            // A knowledge base was configured and this person may see none of
            // it. Sending no argument would tell the server "all the courses
            // this learner is in" -- the opposite of the setting. The one way
            // to say "nothing" is to stop asking for retrieval.
            $turnmode = mode::UNGROUNDED;
        }
        $thread = self::open_thread(
            $placement,
            $component,
            $instanceid,
            $userid,
            $guestkey,
            $threadid,
            $adapter,
            $turnmode,
            $persona->hash(),
            $context,
            $newthread
        );

        $history = thread_store::messages((int) $thread->id, connection::history_limit());
        $hints = self::resolve_hints($placement, $instanceid, $userid, $clienthints, $options);

        // Whether this message agrees to what the last answer previewed is
        // decided here, from what the person actually sent -- a click on the
        // confirmation card or a message that is nothing but an agreement --
        // and not by the agent's model (#168). The MCP server then lets a
        // write through only with that approval. Keyed by the account the
        // backend acts for, because that is who the tool calls run as.
        if (class_exists(self::CONFIRMATION_GATE)) {
            call_user_func(
                [self::CONFIRMATION_GATE, 'user_turn'],
                $backenduserid,
                $message,
                (string) ($hints['pendingdecision'] ?? '')
            );
        }

        $request = new chat_request(
            // Only placements that opt in frame the message as the person's request;
            // every other placement keeps the default frame word for word.
            usermessage: $placement instanceof user_request_framing
                ? prompt_safety::frame_user_request($message)
                : prompt_safety::frame_user_input($message),
            question: $message,
            history: $history,
            persona: $persona,
            // The placement says what kind of conversation this is; the backend
            // owns the prompt it answers inside. Normalised here so a placement
            // that returns something outside the contract degrades to the tutor
            // prompt rather than sending a value no server has to accept.
            systempromptid: system_prompt::normalise($placement->system_prompt_id()),
            mode: $turnmode,
            userid: $backenduserid,
            contextid: (int) $context->id,
            courseid: (int) $thread->courseid,
            coursescope: $scope,
            convkey: $thread->convkey !== null ? (string) $thread->convkey : null,
            allowtools: $placement->allow_tools($instanceid) && $capabilities->supportstools,
            language: current_language(),
            options: $hints,
            ondelta: $capabilities->supportsstreaming ? $ondelta : null,
        );

        $turnstarted = time();
        $booked = null;
        $response = self::dispatch(
            $adapter,
            $request,
            $component,
            $message,
            $backenduserid,
            $ondelta,
            $booked
        );

        // A backend that returned citations for an ungrounded turn must not
        // make the interface claim the answer was grounded, and the audit trail
        // must not record it as such either.
        if ($turnmode === mode::UNGROUNDED) {
            $response = $response->without_sources();
        }

        // Eine Fehlermeldung ist keine Antwort. Sie landete bisher als
        // Assistentenbeitrag im Verlauf, reiste mit der naechsten Frage als
        // Kontext zum Modell, bekam eine Kennzeichnung als KI-Ausgabe und einen
        // Nachweis im Transparenzregister -- fuer einen Satz, den kein Modell
        // geschrieben hat. Gespeichert wird nur die Frage; das Turn-Protokoll
        // haelt den Fehlschlag mit Code fest.
        self::record_turn($thread, $message, $response->iserror ? null : $response);
        $turnid = self::record_in_turn_log(
            $component,
            $instanceid,
            $userid,
            $adapter,
            $message,
            $response,
            (int) $context->id,
            $turnstarted,
            $booked
        );
        $logged = true;
        // Der Simulator erzeugt keinen Text mit einem Modell; seine Antworten
        // tragen schon den Hinweis "Simulation" und sind keine KI-Ausgabe.
        $generated = !$response->iserror && $adapter::id() !== 'simulator';
        $provenanceuuid = !$generated ? null : self::audit(
            $component,
            $adapter,
            $response,
            $backenduserid,
            (int) $context->id,
            $turnid
        );
        usage::increment($backenduserid);

        // Which preview the card stands for (#168): a click on it later must
        // approve that one and not a second write the model previewed alongside.
        $confirmation = $response->confirmation;
        if (class_exists(self::CONFIRMATION_GATE)) {
            call_user_func([self::CONFIRMATION_GATE, 'answer_shown'], $backenduserid, $confirmation);
            // The card names the tool by its English catalogue title; the
            // person reads it in their own language. After answer_shown(),
            // which matches on the original.
            $label = is_array($confirmation) ? (string) ($confirmation['tooltitle'] ?? '') : '';
            $translated = $label !== '' ? call_user_func([self::CONFIRMATION_GATE, 'display_title'], $label) : null;
            if ($translated !== null) {
                $confirmation['tooltitle'] = $translated;
            }
        }

        return [
            'threadid' => (int) $thread->id,
            'answerhtml' => self::render_marked($response->answer, $context, $provenanceuuid),
            'answermarkdown' => $response->answer,
            'sources' => $response->sources,
            'origin' => $response->origin,
            'confirmation' => $confirmation,
            'iserror' => $response->iserror,
            // Art. 50 Abs. 2: the panel marks the turn for machines as well as
            // for readers (#52). The same rule as the provenance record above.
            'aigenerated' => $generated,
        ];
    }

    /**
     * The answer, rendered and marked as AI output.
     *
     * Art. 50 has two halves and they are different duties: the reader has to be
     * able to tell (Abs. 1 -- a visible sentence), and a machine has to be able
     * to detect it (Abs. 2 -- the IPTC vocabulary term plus a resolvable
     * record). Both come from `local_aitransparency`, and both are skipped when
     * there is no provenance record: a marker that points nowhere is worse than
     * none, because it looks like evidence.
     *
     * @param string $answer The answer as Markdown.
     * @param \context $context Rendering context.
     * @param string|null $uuid Provenance UUID, or null when unrecorded.
     * @param adapter $adapter The backend that answered.
     * @return string
     */
    private static function render_marked(
        string $answer,
        \context $context,
        ?string $uuid
    ): string {
        $html = markdown_renderer::render($answer, $context);
        $markerclass = '\\local_aitransparency\\marker';
        if ($uuid === null || !class_exists($markerclass)) {
            return $html;
        }

        $wrapped = call_user_func([$markerclass, 'wrap_text'], $html, $uuid);
        // Der Eintrag blieb auf "pending", und die Pruefseite meldete "noch nicht
        // gekennzeichnet" fuer eine Antwort, die es war.
        \local_aitransparency\provenance::set_mark_state($uuid, 'marked');
        // Ohne Namen in Klammern. Hier stand die Kennung des Backends, und das
        // war zweimal falsch: einmal als rohe Kennung („ingestionapi"), und
        // einmal in der Sache -- dieser Dienst fuellt die Vektordatenbank und
        // reicht die Frage weiter, erzeugt den Text aber nicht. Welches Modell
        // ihn geschrieben hat, erfaehrt die Suite nicht. Einen Dienst zu
        // nennen, der es nicht war, ist schlechter als keinen zu nennen.
        // How it looks is the site's choice for chats (#42); "once" shows the
        // sentence at the panel and the compact mark under each answer.
        $style = null;
        if (method_exists($markerclass, 'style')) {
            $style = call_user_func([$markerclass, 'style'], 'chat');
            if ($style === 'once') {
                $style = 'compact';
            }
        }
        return $wrapped . call_user_func([$markerclass, 'notice'], $uuid, null, $style);
    }

    /**
     * The hints this turn actually carries.
     *
     * Three layers, and the order is the point. What the client asked for is
     * checked against the engine's vocabulary first, so an unknown name or an
     * invented value never reaches a backend. The placement is then offered
     * what survived and answers with the hints it stands behind - an instance
     * that locked its answer style overrules the wish here, which is why the
     * lock cannot be lifted by editing a request. Whatever the placement does
     * not answer for stays as the client asked: the decision on a confirmation
     * card belongs to the panel that showed it, and no placement should have to
     * repeat it to keep it. The caller's own hints come last: code on this side
     * of the wire has already decided.
     *
     * @param placement $placement The placement being addressed.
     * @param int $instanceid The instance id.
     * @param int $userid The acting user id.
     * @param array $clienthints Hints the client asked for, unchecked.
     * @param array $options Hints the calling code supplied server-side.
     * @return array Hint name => value.
     */
    private static function resolve_hints(
        placement $placement,
        int $instanceid,
        int $userid,
        array $clienthints,
        array $options
    ): array {
        $wished = hints::sanitise($clienthints);

        return array_merge($wished, $placement->options($instanceid, $userid, $wished), $options);
    }

    /**
     * The mode this turn actually runs in.
     *
     * A mode the backend does not report is refused rather than downgraded.
     * Downgrading would answer the question in a way the placement did not ask
     * for and nobody would be told; refusing is visible and can be fixed.
     *
     * @param string $requested The placement's configured mode.
     * @param capabilities $capabilities The backend's report.
     * @return string The mode to use.
     * @throws \moodle_exception When the backend cannot answer in that mode.
     */
    private static function resolve_mode(string $requested, capabilities $capabilities): string {
        $requested = mode::normalise($requested);
        if ($requested === mode::UNGROUNDED && !$capabilities->supportsungrounded) {
            throw new \moodle_exception('error_mode_unsupported', 'local_elediaai_chatengine');
        }

        return $requested;
    }

    /**
     * Resume the requested conversation, open the current one, or start a new one.
     *
     * Three answers, because "no conversation id" carries two different
     * questions. A panel that was simply reopened wants to carry on where it
     * left off, and gets the current thread. A panel whose user pressed "new
     * conversation" wants the opposite, and would silently be handed the old
     * thread back if that wish were not stated: the log looks empty, the
     * backend is told the whole previous history, and no new conversation ever
     * appears in the list.
     *
     * @param placement $placement The placement.
     * @param string $component The placement component.
     * @param int $instanceid The instance id.
     * @param int $userid The acting user id.
     * @param string|null $guestkey Guest identifier when userid is 0.
     * @param int|null $threadid An explicitly requested conversation, or null.
     * @param adapter $adapter The active backend.
     * @param string $turnmode The resolved mode.
     * @param string $personahash Fingerprint of the effective persona.
     * @param \context $context The placement context.
     * @param bool $newthread Start a fresh conversation instead of resuming the current one.
     * @return \stdClass The thread record.
     * @throws \moodle_exception When a requested conversation is not the caller's.
     */
    private static function open_thread(
        placement $placement,
        string $component,
        int $instanceid,
        int $userid,
        ?string $guestkey,
        ?int $threadid,
        adapter $adapter,
        string $turnmode,
        string $personahash,
        \context $context,
        bool $newthread = false
    ): \stdClass {
        if ($threadid !== null) {
            $thread = thread_store::owned($threadid, $userid, $guestkey);
            if ($thread === null) {
                throw new \moodle_exception('error_thread_not_found', 'local_elediaai_chatengine');
            }
            return $thread;
        }

        if ($newthread) {
            return thread_store::create(
                $component,
                $instanceid,
                (int) $context->id,
                $placement->courseid($instanceid),
                $userid,
                $guestkey,
                $adapter::id(),
                $turnmode,
                $personahash
            );
        }

        return thread_store::open(
            $component,
            $instanceid,
            (int) $context->id,
            $placement->courseid($instanceid),
            $userid,
            $guestkey,
            $adapter::id(),
            $turnmode,
            $personahash
        );
    }

    /**
     * Ask the backend, once, with a single retry for a rejected credential.
     *
     * The other single retry: a backend that answers `conversation_not_found`
     * is asked again without the conversation id, as its spec prescribes.
     *
     * The retry exists only for 401/403: a cached callback token may have been
     * revoked, and a fresh one cures it. Every other failure — a timeout, a
     * 5xx, an unreadable answer — is not cured by retrying, and retrying would
     * send the same turn to the backend a second time.
     *
     * The whole call including the retry is wrapped in the shared quota
     * manager, so a retried turn stays one billable answer. The backend's own
     * measured token counts are handed over where it reports them: no local
     * estimate can see the backend's system prompt, its tool loop or its
     * reranking, so an estimate is wrong in one direction only.
     *
     * @param adapter $adapter The active backend.
     * @param chat_request $request The assembled request.
     * @param string $component The placement component, for the quota ledger.
     * @param string $message The plain user message, for the quota estimate.
     * @param int $userid The acting user id.
     * @param callable|null $ondelta Streaming callback, to reset on retry.
     * @param array|null $booked Filled with the token counts the credit ledger
     *                           actually booked, and whether they are estimated.
     * @return chat_response The answer.
     * @throws \moodle_exception When the backend fails.
     */
    private static function dispatch(
        adapter $adapter,
        chat_request $request,
        string $component,
        string $message,
        int $userid,
        ?callable $ondelta,
        ?array &$booked = null
    ): chat_response {
        $call = static function () use ($adapter, $request, $userid, $ondelta): chat_response {
            try {
                return $adapter->chat($request);
            } catch (backend_refusal $e) {
                // The backend has forgotten the conversation (expired, or
                // deleted on its side). Its spec asks for a new one; the
                // history kept here still travels with the turn, so the learner
                // loses nothing and does not have to start over by hand. The
                // new conversation id replaces the old one with the answer.
                if ($e->backend_code() !== backend_refusal::CONVERSATION_NOT_FOUND || $request->convkey === null) {
                    throw $e;
                }
                if ($ondelta !== null) {
                    $ondelta('', true);
                }
                return $adapter->chat($request->without_convkey());
            } catch (adapter_exception $e) {
                if (!$e->is_auth_failure() || $userid <= 0) {
                    throw $e;
                }
                token_provider::forget_cached_token($userid);
                // The first attempt may already have streamed fragments; tell
                // the caller to discard them so the retry does not append its
                // answer to a half-rendered one.
                if ($ondelta !== null) {
                    $ondelta('', true);
                }
                return $adapter->chat($request);
            }
        };

        $manager = '\\local_elediaai_core\\quota_aware_ai_manager';
        if (!class_exists($manager)) {
            return $call();
        }

        return $manager::process_callback(
            $userid,
            $component,
            $message,
            $call,
            static fn(chat_response $response): array => [
                'prompttokens' => $response->prompttokens,
                'completiontokens' => $response->completiontokens,
            ],
            $booked,
        );
    }

    /**
     * Write the turn to the shared store.
     *
     * @param \stdClass $thread The thread record.
     * @param string $message The plain user message.
     * @param chat_response|null $response The backend's answer, null when the turn failed.
     * @return void
     */
    private static function record_turn(\stdClass $thread, string $message, ?chat_response $response): void {
        $threadid = (int) $thread->id;

        thread_store::add_message($threadid, message::ROLE_USER, $message);
        if ($response !== null) {
            thread_store::add_message(
                $threadid,
                message::ROLE_ASSISTANT,
                $response->answer,
                $response->sources,
                $response->origin
            );
        }
        if ($response !== null && $response->convkey !== null && $response->convkey !== (string) $thread->convkey) {
            thread_store::set_convkey($threadid, $response->convkey);
        }
        thread_store::touch($threadid, $message);

        // Twice the history limit, so the store keeps a little more than travels
        // with a request and a reader still sees the turn before the one that
        // was quoted.
        thread_store::trim($threadid, connection::history_limit() * 2);
    }

    /**
     * Put the turn into the suite's turn log -- what was asked and answered,
     * on what it was grounded, and not by whom.
     *
     * The engine does not go through `core_ai`, so nothing records its turns on
     * its behalf. It used to write into Moodle's own `ai_action_register` by
     * rebuilding a private core method; since the suite keeps its own turn log
     * it writes there instead, and three fields the engine has always carried
     * finally arrive somewhere: `origin` (grounded, general or read out of
     * Moodle through a tool), `topic`, and the primary citation. Those are
     * exactly what the course report needs to say which questions the material
     * failed to answer -- the analytics that consumed them fell silent on
     * 27.08.2026 when the block became a placement, and nobody noticed.
     *
     * The prompt, the answer and the place are recorded; the person is not.
     * That is the operator's decision of 05.09.2026, enforced where the row is
     * written rather than where it is displayed. What the log does carry is a
     * salted pseudonym, so the report can tell five askers from one and an
     * erasure request stays answerable -- see
     * `local_elediaai_core\local\pseudonym`.
     *
     * Separate from {@see audit()}, which feeds `local_aitransparency`. That
     * register is the mirror image -- it keeps the person and hashes the
     * content -- because it answers the Art. 50 question about one output.
     *
     * @param string $component The placement's frankenstyle component.
     * @param int $instanceid The placement instance, 0 for a site-wide surface.
     * @param int $userid Who asked; stored only as a pseudonym.
     * @param adapter $adapter The backend that answered.
     * @param string $message What was asked.
     * @param chat_response $response What came back.
     * @param int $contextid Where the turn happened.
     * @param int $turnstarted When it was dispatched.
     * @return void
     */
    private static function record_in_turn_log(
        string $component,
        int $instanceid,
        int $userid,
        adapter $adapter,
        string $message,
        chat_response $response,
        int $contextid,
        int $turnstarted,
        ?array $booked = null
    ): ?int {
        $recorder = '\\local_elediaai_core\\local\\turn_recorder';
        if (!class_exists($recorder)) {
            return null;
        }

        // The first entry, sources[0], is the primary citation; the rest are ordered behind it.
        $primary = $response->sources[0] ?? null;
        $sourcetitle = null;
        $cmid = null;
        if (is_array($primary)) {
            $sourcetitle = isset($primary['title']) ? (string) $primary['title'] : null;
            $cmid = isset($primary['cmid']) ? (int) $primary['cmid'] : null;
        }

        return call_user_func([$recorder, 'record'], ...[
            'component' => $component,
            'actionname' => 'chat_turn',
            'contextid' => $contextid,
            'userid' => $userid,
            'prompt' => $message,
            'response' => $response->answer,
            'success' => !$response->iserror,
            'errorcode' => $response->iserror ? 'backend_error' : null,
            // Nicht die rohen Werte der Antwort: die sind leer, sobald das
            // Backend kein usage-Feld schickt, waehrend der Engpass eine
            // Schaetzung auf das Guthaben gebucht hat. Das Protokoll haelt
            // dieselbe Zahl fest wie die Abrechnung, und die Kennzeichnung
            // sagt, welche der beiden es ist.
            'prompttokens' => (int) ($booked['prompttokens'] ?? $response->prompttokens),
            'completiontokens' => (int) ($booked['completiontokens'] ?? $response->completiontokens),
            'tokensestimated' => (bool) ($booked['estimated'] ?? false),
            'instanceid' => $instanceid,
            'origin' => $response->origin,
            'topic' => $response->topic,
            'sourcetitle' => $sourcetitle,
            'cmid' => $cmid,
            'provider' => $adapter::id(),
            'timestarted' => $turnstarted,
        ]);
    }

    /**
     * Record the answer in the AI transparency trail, when it is installed.
     *
     * Never allowed to break a chat turn: the trail documents what happened,
     * and a documentation failure that swallowed the answer would be worse
     * than the gap it was trying to avoid.
     *
     * @param string $component The placement component.
     * @param adapter $adapter The backend that answered.
     * @param chat_response $response The answer.
     * @param int $userid The acting user id.
     * @param int $contextid The context id.
     * @return void
     */
    private static function audit(
        string $component,
        adapter $adapter,
        chat_response $response,
        int $userid,
        int $contextid,
        ?int $turnid = null
    ): ?string {
        if (!class_exists('\\local_aitransparency\\provenance')) {
            return null;
        }

        try {
            return \local_aitransparency\provenance::record(new \local_aitransparency\record_request(
                component: $component,
                actionname: 'generate_text',
                // The backend id, which is what actually answered. For LiteRAG
                // that is a plugin; for the RAG agent it is a service outside
                // this site, so the id is the only name it has here.
                provider: $adapter::id(),
                model: '',
                userid: $userid,
                contextid: $contextid,
                assettype: 'text',
                content: $response->answer,
                turnid: $turnid,
            ));
        } catch (\Throwable $e) {
            debugging(
                'local_elediaai_chatengine: provenance record failed: ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            return null;
        }
    }
}

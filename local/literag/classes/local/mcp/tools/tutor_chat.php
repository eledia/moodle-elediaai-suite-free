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

namespace local_literag\local\mcp\tools;

use local_literag\local\agent;
use local_literag\local\config;
use local_literag\local\confirmation;
use local_literag\local\conversation_repository;
use local_literag\local\llm\client;
use local_literag\local\llm\llm_exception;
use local_literag\local\mcp\moodle_client;
use local_literag\local\mcp\result;
use local_literag\local\mcp\tool;
use local_literag\local\mcp\tool_exception;
use local_literag\local\permission_filter;
use local_literag\local\prompt_builder;
use local_literag\local\reranker;
use local_literag\local\retriever;
use local_literag\local\token_validator;
use local_literag\local\topic_registry;

/**
 * The required tutor_chat tool: answer a learner message with grounded citations.
 *
 * Validates the user-scoped token in-process, retrieves permission-filtered
 * chunks, composes a final Markdown answer via the LLM, and returns it with a
 * conversation id, ordered sources (sources[0] = primary), and a topic label.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tutor_chat implements tool {
    /** @var int Maximum accepted learner message length in characters. */
    private const MAX_MESSAGE_CHARS = 4000;

    /** @var client|null Injected LLM client (tests), or null to build on demand. */
    private ?client $llm;

    /** @var conversation_repository Conversation persistence. */
    private conversation_repository $repo;

    /** @var moodle_client|null Injected elediamcp client (tests), or null to build on demand. */
    private ?moodle_client $mcp;

    /**
     * Constructor.
     *
     * @param client|null $llm Optional LLM client override for testing.
     * @param conversation_repository|null $repo Optional repository override.
     * @param moodle_client|null $mcp Optional elediamcp client override for testing.
     */
    public function __construct(?client $llm = null, ?conversation_repository $repo = null, ?moodle_client $mcp = null) {
        $this->llm = $llm;
        $this->mcp = $mcp;
        $this->repo = $repo ?? new conversation_repository();
    }

    /**
     * Answer a learner message with grounded, cited content.
     *
     * @param array $arguments
     * @return array
     * @throws tool_exception
     */
    public function handle(array $arguments): array {
        global $CFG, $DB;

        $user = token_validator::resolve_user((string) ($arguments['moodle_token'] ?? ''));
        if ($user === null) {
            // Invalid/expired token: surface as a JSON-RPC error so the block
            // drops its cached token, mints a fresh one and retries the turn once.
            throw new tool_exception('invalid moodle_token', -32001);
        }
        $userid = (int) $user->id;

        $message = trim((string) ($arguments['user_message'] ?? ''));
        if ($message === '') {
            throw new tool_exception('missing user_message');
        }
        if (\core_text::strlen($message) > self::MAX_MESSAGE_CHARS) {
            throw new tool_exception('user_message too long');
        }
        \local_literag\local\rate_limiter::check($userid);
        $starttime = microtime(true);

        $courseid = (int) ($arguments['course_id'] ?? 0);
        $answerstyle = in_array(($arguments['answer_style'] ?? ''), ['explain', 'hint', 'quiz'], true)
            ? (string) $arguments['answer_style'] : null;
        $userlang = isset($arguments['user_lang']) ? clean_param((string) $arguments['user_lang'], PARAM_LANG) : null;
        $userlang = $userlang !== '' ? $userlang : null;
        $ragenabled = array_key_exists('rag_enabled', $arguments) ? (bool) $arguments['rag_enabled'] : true;
        $persona = (isset($arguments['persona']) && is_array($arguments['persona'])) ? $arguments['persona'] : null;
        // Which base prompt to answer inside. Not validated against a list here:
        // prompt_builder owns the vocabulary and falls back to the tutor for
        // anything it does not know, so an id from a newer client is answered
        // rather than refused.
        $systempromptid = isset($arguments['system_prompt_id'])
            ? clean_param((string) $arguments['system_prompt_id'], PARAM_ALPHANUMEXT) : null;
        // Optional routing hint from the client (e.g. dashboard action pills):
        // 'action' skips knowledge-base retrieval entirely (Moodle tools + LLM
        // only, so the answer is never labelled 'rag'); 'knowledge' skips the
        // Moodle tools (retrieval only); 'auto' (default) keeps both available.
        $intent = in_array(($arguments['intent'] ?? ''), ['auto', 'action', 'knowledge'], true)
            ? (string) $arguments['intent'] : 'auto';
        // Per-request ban on the live Moodle tools (RAG server spec 0.28.0). The
        // site setting `enable_mcp_tools` says whether this installation offers
        // them at all; this argument says whether the surface asking may use
        // them for this turn -- a role play, for instance, never acts in Moodle
        // on the learner's behalf. Both must agree, and neither overrules the
        // other: absent means the caller has no opinion, which is how every
        // client before that spec version asked.
        $moodletoolsenabled = array_key_exists('moodle_tools_enabled', $arguments)
            ? (bool) $arguments['moodle_tools_enabled'] : true;

        // Resolve / create the conversation (owner-scoped; unknown ids start fresh).
        $conversation = null;
        $convid = trim((string) ($arguments['conversation_id'] ?? ''));
        if ($convid !== '') {
            $conversation = $this->repo->find_owned($convid, $userid);
        }
        if ($conversation === null) {
            $conversation = $this->repo->create($userid, $courseid, (string) $answerstyle);
        }

        $history = $this->repo->recent_messages($conversation);

        $systemurl = $CFG->wwwroot;
        $moodletoken = (string) ($arguments['moodle_token'] ?? '');

        // Confirmation turn: a write previewed on the previous turn is sent now.
        // The confirmation-card buttons send an explicit pending_decision
        // ('confirm'/'decline'); typed affirmatives keep working as fallback via
        // confirmation::is_yes(). The pending action lives for exactly one turn —
        // it is consumed (cleared) either way.
        $decision = in_array(($arguments['pending_decision'] ?? ''), ['confirm', 'decline'], true)
            ? (string) $arguments['pending_decision'] : '';
        $pending = $this->repo->get_pending_action($conversation);
        if ($pending !== null) {
            $this->repo->set_pending_action($conversation, null);
            if ($decision === 'decline') {
                $this->repo->add_message($conversation, 'user', $message);
                $declined = get_string('confirm_declined', 'local_literag');
                $this->repo->add_message($conversation, 'assistant', $declined);
                return result::tool($declined, [
                    'answer' => $declined,
                    'conversation_id' => $conversation->convkey,
                ]);
            }
            // $moodletoolsenabled gates this too. A pending write can only have
            // been previewed on a turn where the tools were allowed, but the
            // surface may have been reconfigured in between, and the pending
            // action outlives that change by one turn. Carrying it out now would
            // execute in Moodle exactly what the caller has since forbidden.
            if (
                $moodletoolsenabled && config::enable_write_tools() && $systemurl !== ''
                && ($decision === 'confirm' || confirmation::is_yes($message))
            ) {
                $this->repo->add_message($conversation, 'user', $message);
                return $this->confirm_pending(
                    $conversation,
                    $systemurl,
                    $moodletoken,
                    $pending,
                    $history,
                    $answerstyle,
                    $userlang,
                    $persona,
                    $systempromptid
                );
            }
        }

        // Retrieval (skipped entirely in LLM-only mode and for action intents).
        $contextchunks = [];
        $numcandidates = 0;
        if ($ragenabled && $intent !== 'action') {
            $courseids = $courseid > 0 ? [$courseid] : array_keys(enrol_get_users_courses($userid, true));
            $candidates = (new retriever())->candidates($message, $courseids, config::retrieval_candidates());
            $numcandidates = count($candidates);

            $candidates = (new permission_filter($user))->filter($candidates);

            if (config::enable_rerank() && count($candidates) > config::context_chunks()) {
                $reranker = new reranker($this->client());
                $candidates = $reranker->rerank($message, $candidates, config::context_chunks());
                $turnusage = self::add_usage($turnusage, $reranker->get_usage());
            } else {
                $candidates = array_slice($candidates, 0, config::context_chunks());
            }
            $contextchunks = $candidates;
        }

        // Deduplicate the retrieved chunks into unique source documents and number
        // them, so the [S#] citation markers the model emits map 1:1 to the source
        // cards (several passages of one document share one number / one card).
        $sources = $this->number_sources($contextchunks);

        // Persist the learner's message before calling the model.
        $this->repo->add_message($conversation, 'user', $message);

        // Live Moodle tools: when grounded and enabled, let the LLM call
        // elediamcp's tools as the learner (spec Part C). Read-only tools are always
        // offered; write tools (e.g. moodle_send_message) only when opted in, and
        // they are forced preview-only by the agent (sent only after confirmation).
        $tools = [];
        $writetools = [];
        $destructivetools = [];
        $tooltitles = [];
        $mcp = null;
        $usersummary = null;
        if (
            $ragenabled && $intent !== 'knowledge' && $moodletoolsenabled
            && config::enable_mcp_tools() && $systemurl !== ''
        ) {
            $mcp = $this->mcp ?? $this->moodle_client($systemurl, $moodletoken);
            // Bootstrap with a verified, LLM-ready summary of the learner.
            $verify = $mcp->call_tool('moodle_verify_user_context', $courseid > 0 ? ['course_id' => $courseid] : []);
            if (!$verify['iserror'] && is_array($verify['structured'])) {
                $usersummary = (string) ($verify['structured']['summary'] ?? '');
            }
            $allowwrites = config::enable_write_tools();
            foreach ($mcp->list_tools() as $tool) {
                if ($tool['name'] === 'moodle_verify_user_context') {
                    continue; // Already called above.
                }
                if (!$tool['readonly'] && !$allowwrites) {
                    continue; // Write tools only when explicitly enabled.
                }
                if (!$tool['readonly']) {
                    $writetools[] = $tool['name'];
                }
                if (!empty($tool['destructive'])) {
                    $destructivetools[] = $tool['name'];
                }
                if (!empty($tool['title'])) {
                    $tooltitles[$tool['name']] = (string) $tool['title'];
                }
                $tools[] = [
                    'type' => 'function',
                    'function' => [
                        'name' => $tool['name'],
                        'description' => $tool['description'],
                        'parameters' => $tool['parameters'],
                    ],
                ];
            }
        }

        $messages = prompt_builder::build(
            $message,
            $contextchunks,
            $history,
            $answerstyle,
            $userlang,
            $persona,
            $systempromptid,
            $ragenabled && $intent !== 'action',
            $usersummary,
            !empty($tools),
            !empty($writetools)
        );

        $confirmation = null;
        $answerorigin = !empty($sources) ? 'rag' : 'general';
        // Every completion of this turn — reranking, each agent iteration, the
        // final answer, the confirmation — is billed separately. The tutor books
        // the turn on the Moodle side, so it needs the total, not the last call
        // (see the chatengine's rag_server_spec, A.1 "Reporting token usage").
        $turnusage = ['prompttokens' => null, 'completiontokens' => null];

        try {
            if (!empty($tools) && $mcp !== null) {
                $agentrunner = new agent($this->client(), $mcp, null, null, $writetools, $destructivetools);
                // Usage is collected after run() below: one turn may involve
                // several completions inside the loop.
                $answer = $agentrunner->run($messages, $tools);
                $turnusage = self::add_usage($turnusage, $agentrunner->get_usage());
                if (!empty($agentrunner->usedtools)) {
                    $answerorigin = 'mcp';
                }
                // Answers built from live Moodle data carry citations too: the
                // retrieved chunks stay primary, tool citations follow. Without
                // this an answer sourced purely from tools showed no sources.
                foreach ($agentrunner->toolsources as $toolsource) {
                    $known = false;
                    foreach ($sources as $existing) {
                        if (($existing['url'] ?? '') === $toolsource['url']) {
                            $known = true;
                            break;
                        }
                    }
                    if (!$known) {
                        $sources[] = $toolsource;
                    }
                }
                // A write previewed this turn awaits the learner's confirmation next turn.
                if ($agentrunner->pendingaction !== null) {
                    $this->repo->set_pending_action($conversation, $agentrunner->pendingaction);
                    $confirmation = $this->confirmation_payload($agentrunner->pendingaction, $tooltitles);
                    $answerorigin = 'mcp';
                }
            } else {
                $plain = $this->client()->chat_with_usage($messages);
                $answer = $plain['content'];
                $turnusage = self::add_usage($turnusage, $plain['usage']);
            }
        } catch (llm_exception $e) {
            debugging('local_literag tutor_chat LLM failure: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $this->repo->touch($conversation, (string) $answerstyle);
            $friendly = get_string('error_llm', 'local_literag');
            // Handled error: still return a conversation id so the block can retry.
            return result::tool($friendly, [
                'answer' => $friendly,
                'conversation_id' => $conversation->convkey,
            ], true);
        }

        // The sources list (deduplicated, ordered, sources[0] = primary) was built above.
        // The url MUST be the module_url so the block can resolve the analytics cmid.
        $primarycmid = !empty($contextchunks) ? (int) $contextchunks[0]->cmid : 0;
        $primarytitle = !empty($contextchunks) ? (string) $contextchunks[0]->sourcetitle : null;
        $topic = (new topic_registry())->classify($courseid, $primarytitle);

        // Persist the clean answer plus its structured sources, so tutor_get_history
        // can return the same citations and the block renders identical source cards
        // on resume (see rag_server_spec.md A.2).
        $this->repo->add_message($conversation, 'assistant', $answer, $topic, $primarycmid, $sources);
        $this->repo->touch($conversation, (string) $answerstyle);

        $latencyms = (int) round((microtime(true) - $starttime) * 1000);
        $this->log_query($userid, $courseid, $message, $numcandidates, count($contextchunks), $latencyms);

        $this->record_provenance($userid, $courseid, $answer);

        $structured = [
            'answer' => $answer,
            'conversation_id' => $conversation->convkey,
            'answer_origin' => $answerorigin,
        ];
        if (!empty($sources)) {
            $structured['sources'] = $sources;
        }
        if ($topic !== null && $topic !== '') {
            $structured['topic'] = $topic;
        }
        if ($confirmation !== null) {
            $structured['confirmation'] = $confirmation;
        }
        if ($turnusage['prompttokens'] !== null || $turnusage['completiontokens'] !== null) {
            $structured['usage'] = [
                'prompt_tokens' => $turnusage['prompttokens'],
                'completion_tokens' => $turnusage['completiontokens'],
            ];
        }

        return result::tool($answer, $structured, false);
    }

    /**
     * Record an Art. 50 provenance entry for the generated tutor answer.
     *
     * This is the second AI chokepoint: literag runs its own LLM client, not
     * core_ai, so the answer is otherwise unrecorded. Guarded by class_exists so
     * literag keeps working without local_aitransparency, and never lets a
     * provenance failure break the tutor response.
     *
     * @param int $userid Triggering user id
     * @param int $courseid Course id, 0 outside a course
     * @param string $answer The generated answer delivered to the user
     */
    private function record_provenance(int $userid, int $courseid, string $answer): void {
        if ($answer === '' || !class_exists(\local_aitransparency\provenance::class)) {
            return;
        }
        try {
            $context = $courseid > 0
                ? \context_course::instance($courseid)
                : \context_system::instance();
            \local_aitransparency\provenance::record(new \local_aitransparency\record_request(
                component: 'local_literag',
                actionname: 'generate_text',
                provider: 'local_literag',
                model: '',
                userid: $userid,
                contextid: (int) $context->id,
                assettype: 'text',
                content: $answer,
            ));
        } catch (\Throwable $e) {
            debugging('local_literag provenance recording failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Deduplicate context chunks into unique source documents and number them.
     *
     * Several retrieved passages often come from the same module; they must
     * collapse to one numbered source card. Each chunk is tagged with its source
     * number (`->sourcenum`) so the prompt's [S#] markers and the returned source
     * cards share the same numbering. Order follows first (best-relevance)
     * appearance, so sources[0] stays the primary source.
     *
     * @param array $contextchunks Chunk records (mutated: each gains ->sourcenum).
     * @return array<int, array{title: string, url: string, snippet: string}> Unique sources.
     */
    private function number_sources(array $contextchunks): array {
        $sources = [];
        $index = [];
        foreach ($contextchunks as $chunk) {
            if ((int) $chunk->cmid > 0) {
                $key = 'cm:' . (int) $chunk->cmid;
            } else if ((string) $chunk->moduleurl !== '') {
                $key = 'url:' . $chunk->moduleurl;
            } else {
                $key = 'title:' . $chunk->sourcetitle;
            }
            if (!isset($index[$key])) {
                $index[$key] = count($sources) + 1;
                $sources[] = [
                    'title' => (string) $chunk->sourcetitle,
                    'url' => (string) $chunk->moduleurl,
                    'snippet' => trim(\core_text::substr((string) $chunk->chunktext, 0, 240)),
                ];
            }
            $chunk->sourcenum = $index[$key];
        }
        return $sources;
    }

    /**
     * Execute a learner-confirmed pending write and return a brief confirmation.
     *
     * @param \stdClass $conversation
     * @param string $systemurl
     * @param string $moodletoken
     * @param array $pending The stored pending action ({tool, arguments}).
     * @param array $history Prior conversation turns for context.
     * @param string|null $answerstyle
     * @param string|null $userlang
     * @param array|null $persona
     * @param string|null $systempromptid Which base prompt to answer inside.
     * @return array MCP tool result.
     */
    private function confirm_pending(
        \stdClass $conversation,
        string $systemurl,
        string $moodletoken,
        array $pending,
        array $history,
        ?string $answerstyle,
        ?string $userlang,
        ?array $persona,
        ?string $systempromptid = null
    ): array {
        $mcp = $this->mcp ?? $this->moodle_client($systemurl, $moodletoken);
        // This path answers a confirmation and bills its own completion, so it
        // reports usage just like the main path does.
        $turnusage = ['prompttokens' => null, 'completiontokens' => null];

        $args = is_array($pending['arguments'] ?? null) ? $pending['arguments'] : [];
        $args['confirm'] = true;
        $result = $mcp->call_tool((string) ($pending['tool'] ?? ''), $args);

        $summary = (is_array($result['structured']) && !empty($result['structured']['summary']))
            ? (string) $result['structured']['summary'] : '';
        $completed = !$result['iserror'] && $this->confirmed_action_completed($result['structured']);
        if ($completed) {
            $note = 'You have just completed the learner\'s confirmed request: '
                . ($summary !== '' ? $summary : 'done') . '. Confirm this to the learner in one short sentence.';
        } else {
            $note = 'The action could not be completed' . ($summary !== '' ? ' (' . $summary . ')' : '')
                . '. Apologise briefly and suggest trying again.';
        }

        try {
            // A send/confirmation reply is a plain short sentence — never quizzed or turned into
            // a hint — so the answer style is deliberately dropped (null) on this path only.
            $confirmreply = $this->client()->chat_with_usage(
                prompt_builder::build($note, [], $history, null, $userlang, $persona, $systempromptid, false)
            );
            $answer = $confirmreply['content'];
            $turnusage = self::add_usage($turnusage, $confirmreply['usage']);
        } catch (llm_exception $e) {
            $answer = $completed ? get_string('confirm_sent', 'local_literag') : get_string('error_llm', 'local_literag');
        }
        if ($completed) {
            $answer = $this->append_action_link($answer, $result['structured']);
        }

        $this->repo->add_message($conversation, 'assistant', $answer, null, 0, []);
        $this->repo->touch($conversation, (string) $answerstyle);
        $structured = [
            'answer' => $answer,
            'conversation_id' => $conversation->convkey,
            'answer_origin' => 'mcp',
        ];
        if ($turnusage['prompttokens'] !== null || $turnusage['completiontokens'] !== null) {
            $structured['usage'] = [
                'prompt_tokens' => $turnusage['prompttokens'],
                'completion_tokens' => $turnusage['completiontokens'],
            ];
        }
        return result::tool($answer, $structured, false);
    }

    /**
     * Structured confirmation controls for chat UIs that can render buttons.
     *
     * @return array{required: bool, yeslabel: string, nolabel: string, yesmessage: string, nomessage: string}
     */
    private function confirmation_payload(array $pending = [], array $tooltitles = []): array {
        $toolname = (string) ($pending['tool'] ?? '');
        return [
            'required' => true,
            'yeslabel' => get_string('confirm_yes', 'local_literag'),
            'nolabel' => get_string('confirm_no', 'local_literag'),
            'yesmessage' => get_string('confirm_yes', 'local_literag'),
            'nomessage' => get_string('confirm_no', 'local_literag'),
            // Generic-card payload: what would run, its preview summary, and
            // whether the tool is annotated destructive (danger styling).
            'summary' => self::user_facing_summary((string) ($pending['summary'] ?? '')),
            'destructive' => !empty($pending['destructive']),
            'tooltitle' => $tooltitles[$toolname] ?? $toolname,
        ];
    }

    /**
     * Strip agent-facing protocol phrases from a tool preview summary so it
     * can be shown on the learner's confirmation card.
     *
     * @param string $summary Raw preview summary.
     * @return string
     */
    private static function user_facing_summary(string $summary): string {
        $summary = (string) preg_replace('/[^.!?]*confirm\s*=\s*true[^.!?]*[.!?]/i', '', $summary);
        $summary = (string) preg_replace('/^\s*Preview only\.?\s*/i', '', $summary);
        return trim($summary);
    }

    /**
     * Append deterministic action links that should not depend on LLM wording.
     *
     * @param string $answer Renderable Markdown answer.
     * @param mixed $structured Structured Moodle tool result.
     * @return string Answer with any missing action link appended.
     */
    private function append_action_link(string $answer, $structured): string {
        if (!is_array($structured)) {
            return $answer;
        }
        $course = is_array($structured['course'] ?? null) ? $structured['course'] : null;
        if ($course === null || empty($course['url'])) {
            return $answer;
        }
        $url = clean_param((string) $course['url'], PARAM_URL);
        if ($url === '' || strpos($answer, $url) !== false) {
            return $answer;
        }
        return trim($answer) . "\n\n" . '[Kurs öffnen](' . $url . ')';
    }

    /**
     * Whether a confirmed Moodle write tool result represents a completed action.
     *
     * Different elediamcp write tools use domain-specific flags (`sent`,
     * `created`, `updated`, `enrolled`) but share `requires_confirmation=false`
     * after the second call. Treat any of these success shapes as completed.
     *
     * @param mixed $structured Structured tool result.
     * @return bool
     */
    private function confirmed_action_completed($structured): bool {
        if (!is_array($structured)) {
            return false;
        }
        foreach (['sent', 'created', 'updated', 'enrolled'] as $flag) {
            if (!empty($structured[$flag])) {
                return true;
            }
        }
        return array_key_exists('requires_confirmation', $structured)
            && empty($structured['requires_confirmation']);
    }

    /**
     * Get the LLM client, lazily constructing the default one.
     *
     * @return client
     */
    private function client(): client {
        if ($this->llm === null) {
            $this->llm = new client();
        }
        return $this->llm;
    }

    /**
     * Build the Moodle MCP client.
     *
     * Kept overridable for tests: the system URL must be the local Moodle wwwroot,
     * never a request-supplied host.
     *
     * @param string $systemurl Canonical Moodle wwwroot.
     * @param string $moodletoken User-scoped MCP token.
     * @return moodle_client
     */
    protected function moodle_client(string $systemurl, string $moodletoken): moodle_client {
        return new moodle_client($systemurl, $moodletoken);
    }

    /**
     * Record an operational query-log row (query text only at full verbosity).
     *
     * @param int $userid
     * @param int $courseid
     * @param string $message
     * @param int $numcandidates
     * @param int $numreturned
     * @param int $latencyms Wall-clock latency for the handled turn.
     * @return void
     */
    private function log_query(
        int $userid,
        int $courseid,
        string $message,
        int $numcandidates,
        int $numreturned,
        int $latencyms
    ): void {
        global $DB;
        $DB->insert_record('local_literag_query_log', (object) [
            'userid' => $userid,
            'courseid' => $courseid,
            'tenant' => \local_literag\local\tenant::id(),
            'querytext' => config::log_verbosity() >= 2 ? \core_text::substr($message, 0, 1000) : null,
            'numcandidates' => $numcandidates,
            'numreturned' => $numreturned,
            'usedllm' => 1,
            'reranked' => config::enable_rerank() ? 1 : 0,
            'latencyms' => max(0, $latencyms),
            'timecreated' => time(),
        ]);
    }

    /**
     * Add one completion's usage to a running turn total.
     *
     * A null on either side stays null only while nothing has been reported at
     * all; once any completion reports a value, the total is a real number and
     * further nulls simply add nothing. That way a backend that reports for some
     * calls but not others still yields a usable — if low — figure, and one that
     * reports nothing leaves nulls so the tutor falls back to estimating.
     *
     * @param array $total The running total.
     * @param array $add The usage of one completion.
     * @return array{prompttokens: ?int, completiontokens: ?int}
     */
    private static function add_usage(array $total, array $add): array {
        foreach (['prompttokens', 'completiontokens'] as $key) {
            $value = $add[$key] ?? null;
            if (!is_int($value) || $value < 0) {
                continue;
            }
            $total[$key] = (int) ($total[$key] ?? 0) + $value;
        }
        return $total;
    }
}

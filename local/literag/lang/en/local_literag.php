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
 * Language strings for local_literag.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['confirm_declined'] = 'Okay, I have discarded the action. Nothing was changed.';
$string['confirm_no'] = 'No, cancel';
$string['confirm_yes'] = 'Yes, proceed';
$string['chattoolname'] = 'Chat tool name';
$string['chattoolname_desc'] = 'Name of the required chat tool.';
$string['chunk_overlap'] = 'Chunk overlap (characters)';
$string['chunk_overlap_desc'] = 'Overlap between consecutive chunks to preserve context across boundaries.';
$string['chunk_size'] = 'Chunk size (characters)';
$string['chunk_size_desc'] = 'Target size of each stored text chunk.';
$string['confirm_sent'] = 'Done — your message was sent.';
$string['context_chunks'] = 'Context chunks';
$string['context_chunks_desc'] = 'How many chunks to feed the LLM as grounding context (also the citation count).';
$string['conversation_retention_days'] = 'Conversation retention (days)';
$string['conversation_retention_days_desc'] = 'Delete conversations not modified for this many days (0 = keep forever).';
$string['deletetoolname'] = 'Delete-conversation tool name';
$string['deletetoolname_desc'] = 'Name of the per-conversation delete tool.';
$string['deleteusertoolname'] = 'Delete-user-data tool name';
$string['deleteusertoolname_desc'] = 'Name of the complete user-data erasure tool.';
$string['emergency_disable'] = 'Disable endpoints';
$string['emergency_disable_desc'] = 'When ticked, both the ingestion and tutor endpoints return a service-unavailable response.';
$string['enable_mcp_tools'] = 'Enable live Moodle tools';
$string['enable_mcp_tools_desc'] = 'When on (and retrieval is enabled), the tutor may call elediamcp\'s read-only tools as the learner to fetch live data. Requires webservice_elediamcp.';
$string['enable_memory'] = 'Enable long-term memory';
$string['enable_memory_desc'] = 'Allow opt-in long-term memory. Off by default; every read/write is gated on per-request consent.';
$string['enable_rerank'] = 'Enable LLM reranking';
$string['enable_rerank_desc'] = 'Optionally ask the LLM to reorder candidates before answering (extra latency and cost).';
$string['enable_write_tools'] = 'Allow sending messages';
$string['enable_write_tools_desc'] = 'Off by default. When on (and live tools are enabled), the tutor may send Moodle messages on the learner\'s behalf — but only after previewing the message and getting an explicit in-chat confirmation. Sends honour the learner\'s own permissions.';
$string['endpointinfo'] = 'Endpoint URLs';
$string['endpointinfo_desc'] = 'Configure the existing plugins to point here:<ul>'
    . '<li><strong>local_elediaai_sources</strong> &rarr; select <em>LiteRAG</em> as the destination. '
    . 'Nothing else to enter: the route (<code>{$a->upsert}</code>) is derived from this site\'s address, '
    . 'and the ingestion key below is read straight from here.</li>'
    . '<li><strong>block_elediaai_tutor</strong> &rarr; RAG server URL: <code>{$a->mcp}</code></li></ul>';
$string['error_llm'] = 'Sorry, the tutor could not generate an answer right now. Please try again.';
$string['guide_body'] = 'When the tutor quotes the course instead of answering freely, it asked an ingestion destination. Which one is decided by a setting in AI Sources - and **exactly one is active at a time**:

- **The external ingestion API** is the default. It talks over HTTP to a service outside Moodle; the administrator enters a base URL and a tenant key.
- **LiteRAG** runs on this Moodle site. There is neither a URL nor a second key to enter: the route is derived from `wwwroot`, and the key used is LiteRAG\'s own ingestion key. Anyone looking for a setting here is looking in the wrong plugin — it belongs to LiteRAG.

What you configure here concerns only the second case: access to and operation of LiteRAG, plus the view showing whether ingestion is running and what has arrived.

What to watch for: **switching the destination makes every course diverge.** What sits in the old destination does not move along; courses have to be ingested again. Two destinations in parallel are not supported, which is why the setting is a single choice and not a multi-select.

And regardless of the choice: the destination holds course content. With the external API it sits outside Moodle, with LiteRAG inside. Which of the two your privacy assessment requires is the decision that actually matters.';
$string['guide_summary'] = 'AI Sources send selected material to exactly one destination. LiteRAG is one of them: it runs on this Moodle site.';
$string['guide_title'] = 'LiteRAG as an ingestion destination';
$string['head_connection'] = 'Connection';
$string['head_livetools'] = 'Live Moodle tools';
$string['head_livetools_desc'] = 'Let the tutor call webservice_elediamcp\'s read-only moodle_* tools to answer with the learner\'s real-time data (courses, assignments, grades, deadlines, …).';
$string['head_llm'] = 'LLM (OpenAI-compatible)';
$string['head_privacy'] = 'Memory, logging & retention';
$string['head_retrieval'] = 'Retrieval & chunking';
$string['head_tools'] = 'Tool names';
$string['head_tools_desc'] = 'These MUST match the tool names configured in the tutor block.';
$string['historytoolname'] = 'History tool name';
$string['historytoolname_desc'] = 'Name of the conversation-history tool.';
$string['ingest_api_key'] = 'Ingestion API key';
$string['ingest_api_key_desc'] = 'Shared secret the ingester must present in the <code>X-API-Key</code> header. '
    . 'local_elediaai_sources reads this value from here; there is no second place to enter it.';
$string['literag:manage'] = 'Manage the eLeDia.ai LiteRAG backend settings';
$string['llm_allow_private'] = 'Allow private/loopback LLM host';
$string['llm_allow_private_desc'] = 'Bypass Moodle cURL security to reach a self-hosted LiteLLM on a private or loopback address. Enable only for trusted local/Docker LLM endpoints; this disables Moodle\'s SSRF protection for the LLM URL.';
$string['llm_api_key'] = 'API key';
$string['llm_api_key_desc'] = 'Bearer API key for the LLM endpoint.';
$string['llm_base_url'] = 'API base URL';
$string['llm_base_url_desc'] = 'OpenAI-compatible base URL, e.g. <code>https://api.openai.com/v1</code> or a LiteLLM proxy.';
$string['llm_max_tokens'] = 'Max output tokens';
$string['llm_max_tokens_desc'] = 'Maximum tokens to generate per answer.';
$string['llm_model'] = 'Model';
$string['llm_model_desc'] = 'Chat completion model id.';
$string['llm_temperature'] = 'Temperature';
$string['llm_temperature_desc'] = 'Sampling temperature (e.g. 0.2).';
$string['llm_timeout'] = 'Request timeout (seconds)';
$string['llm_timeout_desc'] = 'Keep below the tutor block timeout (default 30s) so answers return in time.';
$string['log_verbosity'] = 'Logging verbosity';
$string['log_verbosity_desc'] = 'How much retrieval detail to record in the query log.';
$string['loglevel_counts'] = 'Counts (no query text)';
$string['loglevel_errors'] = 'Errors only';
$string['loglevel_full'] = 'Full (store query text)';
$string['max_tool_iterations'] = 'Max tool rounds';
$string['max_tool_iterations_desc'] = 'Maximum tool-call rounds the tutor may take per answer before it must respond.';
$string['mcp_timeout'] = 'Tool call timeout (seconds)';
$string['mcp_timeout_desc'] = 'Per-request timeout when calling an elediamcp tool.';
$string['memoryoptintoolname'] = 'Memory opt-in tool name';
$string['memoryoptintoolname_desc'] = 'Name of the long-term-memory consent tool.';
$string['pdftotext_path'] = 'pdftotext path (optional)';
$string['pdftotext_path_desc'] = 'Optional. PDFs are already searchable via the bundled PHP parser; set an absolute path to a native <code>pdftotext</code> (Poppler) binary for higher-fidelity extraction, or leave empty to use the bundled parser.';
$string['pluginname'] = 'eLeDia.ai LiteRAG';
$string['nav_label'] = 'eLeDia.ai LiteRAG sections';
$string['nav_settings'] = 'Settings';
$string['privacy:conversations'] = 'Conversations';
$string['privacy:memory'] = 'Long-term memory';
$string['privacy:metadata:llm_provider'] = 'Questions and retrieved context are sent to an external OpenAI-compatible LLM to compose answers.';
$string['privacy:metadata:llm_provider:context'] = 'Retrieved course-content excerpts sent to the LLM as grounding context.';
$string['privacy:metadata:llm_provider:toolresults'] = 'Live Moodle tool results, such as grades, calendar entries, progress information and messages, may be sent to the LLM when live tools are enabled.';
$string['privacy:metadata:llm_provider:usermessage'] = 'The learner question sent to the LLM.';
$string['privacy:metadata:llm_provider:usersummary'] = 'A learner summary or opt-in memory facts may be sent to the LLM when those features are enabled.';
$string['privacy:metadata:local_literag_conversations'] = 'Tutor conversations owned by this backend.';
$string['privacy:metadata:local_literag_conversations:courseid'] = 'The course the conversation is attached to.';
$string['privacy:metadata:local_literag_conversations:timecreated'] = 'When the conversation was created.';
$string['privacy:metadata:local_literag_conversations:userid'] = 'The user who owns the conversation.';
$string['privacy:metadata:local_literag_memory'] = 'Opt-in long-term memory facts about the user.';
$string['privacy:metadata:local_literag_memory:mvalue'] = 'The stored memory fact.';
$string['privacy:metadata:local_literag_memory:timecreated'] = 'When the memory was stored.';
$string['privacy:metadata:local_literag_memory:userid'] = 'The user the memory belongs to.';
$string['privacy:metadata:local_literag_messages'] = 'Messages within tutor conversations.';
$string['privacy:metadata:local_literag_messages:content'] = 'The message text.';
$string['privacy:metadata:local_literag_messages:role'] = 'Whether the message is from the user or the assistant.';
$string['privacy:metadata:local_literag_messages:timecreated'] = 'When the message was created.';
$string['privacy:metadata:local_literag_messages:userid'] = 'The user who owns the message.';
$string['privacy:metadata:local_literag_query_log'] = 'Operational log of retrieval queries.';
$string['privacy:metadata:local_literag_query_log:querytext'] = 'The question text (only at full logging verbosity).';
$string['privacy:metadata:local_literag_query_log:timecreated'] = 'When the query was made.';
$string['privacy:metadata:local_literag_query_log:userid'] = 'The user who asked the question.';
$string['privacy:querylogs'] = 'Query logs';
$string['query_log_retention_days'] = 'Query-log retention (days)';
$string['query_log_retention_days_desc'] = 'Delete query-log rows older than this many days (0 = keep forever).';
$string['reclustertoolname'] = 'Recluster tool name';
$string['reclustertoolname_desc'] = 'Name of the nightly question-reclustering tool.';
$string['rerank_model'] = 'Rerank model';
$string['rerank_model_desc'] = 'Model used for reranking; leave empty to reuse the answer model.';
$string['rate_limit_per_day'] = 'Tutor turns per user and day';
$string['rate_limit_per_day_desc'] = 'Hard daily ceiling for paid tutor turns by one user. The value must be at least 1.';
$string['rate_limit_per_minute'] = 'Tutor turns per user and minute';
$string['rate_limit_per_minute_desc'] = 'Burst limit for paid tutor turns by one user. The value must be at least 1.';
$string['retrieval_candidates'] = 'Candidate chunks';
$string['retrieval_candidates_desc'] = 'How many chunks the full-text search returns before permission filtering.';
$string['suite_feature_desc'] = 'One of the destinations course material can be ingested into - this one runs inside Moodle itself, rather than at an external service.';
$string['suite_feature_detail'] = 'When the tutor cites the course rather than inventing an answer, this is what it asked. LiteRAG receives the material that AI Sources selected, keeps it searchable and returns the passages that fit a question. Administrators configure the backend, check its health and see what has been ingested.';
$string['suite_feature_key_1'] = 'Receives and stores the material selected in AI Sources.';
$string['suite_feature_key_2'] = 'Finds the passages that fit a question, so answers can cite the course.';
$string['suite_feature_key_3'] = 'Administrators check the backend connection and what has been ingested.';
$string['suite_feature_name'] = 'LiteRAG';
$string['task_prune_logs'] = 'Prune eLeDia.ai LiteRAG logs and expired conversations';
$string['settings_hub_desc'] = 'Choose one eLeDia.ai LiteRAG settings area.';
$string['settings_section_connection_desc'] = 'Endpoint URLs, shared secrets and emergency stop.';
$string['settings_section_livetools_desc'] = 'Live Moodle tool access through eLeDia MCP.';
$string['settings_section_llm_desc'] = 'OpenAI-compatible model endpoint and generation limits.';
$string['settings_section_privacy_desc'] = 'Long-term memory, logging and retention.';
$string['settings_section_retrieval_desc'] = 'Chunking, retrieval breadth and reranking.';
$string['settings_section_tools_desc'] = 'MCP tool names exposed to the tutor block.';
$string['shell_help_label'] = 'Help for eLeDia.ai LiteRAG';
$string['shell_subtitle'] = 'RAG backend settings for ingestion, retrieval and tutor answers.';
$string['transport_auth_token'] = 'Tutor transport token (optional)';
$string['transport_auth_token_desc'] = 'Optional bearer token the tutor block must present (defence in depth). '
    . 'Leave empty to accept any request; every chat already carries a verifiable per-user token.';
$string['untitledsource'] = 'Untitled source';

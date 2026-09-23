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
 * English strings for local_elediaai_chatengine.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['health_backend'] = 'Chat backend';
$string['health_backend_unconfigured'] = 'No backend is selected, so no chat can answer.';
$string['pluginname'] = 'AI Chat Engine';
$string['privacy:metadata:thread'] = 'Conversations held with an AI assistant in any of the chat placements.';
$string['privacy:metadata:thread:userid'] = 'The user who held the conversation.';
$string['privacy:metadata:thread:placement'] = 'Which chat placement the conversation was started from.';
$string['privacy:metadata:thread:courseid'] = 'The course the conversation belonged to, when it was not site-wide.';
$string['privacy:metadata:thread:mode'] = 'Whether the conversation was answered from course material or from the model alone.';
$string['privacy:metadata:thread:lastpreview'] = 'A short preview of the most recent message, shown in conversation lists.';
$string['privacy:metadata:thread:timecreated'] = 'When the conversation was started.';
$string['privacy:metadata:thread:timemodified'] = 'When the conversation was last used.';
$string['privacy:metadata:msg'] = 'The individual turns of a conversation.';
$string['privacy:metadata:msg:role'] = 'Whether the turn was written by the user or produced by the assistant.';
$string['privacy:metadata:msg:content'] = 'The text of the turn.';
$string['privacy:metadata:msg:sources'] = 'The course material an assistant turn cited.';
$string['privacy:metadata:msg:origin'] = 'Whether an assistant turn came from course material, from Moodle\'s own data, or from the model\'s general knowledge.';
$string['privacy:metadata:msg:timecreated'] = 'When the turn was recorded.';
$string['privacy:metadata:backend'] = 'Messages are sent to the configured AI backend so that it can produce an answer.';
$string['privacy:metadata:backend:usermessage'] = 'The message the user typed.';
$string['privacy:metadata:backend:history'] = 'Earlier turns of the same conversation, so the answer follows the thread.';
$string['privacy:metadata:backend:userid'] = 'A user-scoped token identifying who is asking, so the backend can apply that user\'s permissions.';
$string['privacy:metadata:backend:courseid'] = 'The course whose material may be retrieved.';
$string['privacy:metadata:backend:language'] = 'The user\'s language, so the answer is given in it.';

// Backends.
$string['backend_ingestionapi'] = 'RAG agent';
$string['backend_literag'] = 'LiteRAG (in this site)';
$string['backend_simulator'] = 'Simulator (no language model)';
$string['setting_simulator'] = 'Simulated backend (no language model)';
$string['setting_simulator_desc'] = 'Answers every chat turn from a fixed script instead of asking a language model. For working on the chat surfaces: layout, streaming, source cards, error states, the confirmation card.

<strong>While this is on, no chat on this site reaches a real backend.</strong> Every answer is marked as simulated, and the health report shows a warning, but nothing here is suitable for teaching or for judging answer quality.

Keywords steer what comes back — send <code>/help</code> in any chat to see them.';
$string['status_simulator_active'] = 'Simulator active — answers are invented, no language model is involved.';
$string['simulator_marker'] = '**Simulated answer.** No language model was involved.';
$string['simulator_topic'] = 'Simulation';
$string['simulator_default'] = 'You wrote: *{$a->message}*

This is the simulated backend. It answers from a script, so the chat surface can be worked on without a key, a network or a bill.

**What this turn carried**

| Field | Value |
|---|---|
| Answer mode | `{$a->mode}` |
| Base prompt | `{$a->prompt}` |
| Moodle tools permitted | {$a->tools} |

Send `/help` for the other variants.';
$string['simulator_help'] = 'These keywords change what comes back. Put one anywhere in your message.

- `/help` — this list
- `/sources` — an answer with three source cards, reported as grounded
- `/error` — the backend reports a failure, so the error state can be seen
- `/slow` — streamed with a pause between fragments, for the typing behaviour
- `/long` — a long answer, for scrolling and layout
- `/confirm` — an answer with a confirmation card (nothing is ever carried out)
- `/tokens` — an answer that reports token usage, for the quota ledger

The German words work too: `/hilfe`, `/quellen`, `/fehler`, `/langsam`, `/lang`, `/bestaetigen`.';
$string['simulator_error_body'] = 'The simulated backend was asked to fail, and did. This is what the panel shows when a backend cannot answer.';
$string['simulator_sources_body'] = 'This answer claims to rest on course material [S1], and on two further documents [S2] [S3]. The cards below are invented — they exist so the citation markers have somewhere to point.';
$string['simulator_source_one'] = 'Simulated document: introduction';
$string['simulator_source_two'] = 'Simulated document: exercise sheet';
$string['simulator_source_three'] = 'Simulated document: summary';
$string['simulator_source_snippet'] = 'An invented passage, long enough to see how a source card wraps when the text does not stop after four words.';
$string['simulator_confirm_body'] = 'This answer carries a confirmation card. Confirming does nothing: the simulator has no Moodle tools behind it, and a confirmation that acted would make this backend dangerous rather than merely fake.';
$string['simulator_confirm_summary'] = 'Send a message to Erika Mustermann (simulated — nothing will be sent).';
$string['simulator_tokens_body'] = 'This answer reports a token usage of 1234 prompt tokens and 567 completion tokens, so the quota ledger has something to record.';
$string['simulator_slow_body'] = 'This answer arrives fragment by fragment with a pause between them, so the typing behaviour of the panel can be watched at a speed the eye can follow.';
$string['simulator_long'] = '## A long answer

The paragraphs below repeat. The point is the length, not the content: scrolling, the position of the input field, and whether anything jumps while it grows.';
$string['simulator_long_para'] = 'An invented paragraph of the simulated backend. It says nothing, at some length, so that the surface has something to render and a person looking at it can judge line spacing, measure and the rhythm of the text.';

// Answer modes.
$string['mode_grounded'] = 'Grounded (answers from the knowledge base)';
$string['mode_ungrounded'] = 'Model only (no knowledge base)';

// Cache definitions.
$string['cachedef_usertoken'] = 'User-scoped backend callback tokens';
$string['cachedef_ratelimit'] = 'Per-user chat request counters';

// Scheduled tasks.
$string['task_purge_threads'] = 'Delete expired AI chat conversations';

// Settings.
$string['settings_head_backend'] = 'Backend';
$string['settings_head_backend_desc'] = 'Which backend answers is not configured here. It follows the destination that course material is written to, set in AI Sources, so a question is never put to a service the material was never sent to. What is configured here is how to reach that backend, and the limits that apply whichever one answers.';
$string['settings_head_ingestionapi'] = 'RAG agent (ingestion pipeline)';
$string['settings_head_ingestionapi_desc'] = 'The agent is a separate service from the ingestion pipeline: the pipeline receives documents, the agent answers questions. Its address is therefore configured separately and cannot be derived from the pipeline\'s base URL.';
$string['settings_head_literag'] = 'LiteRAG';
$string['settings_head_literag_desc'] = 'LiteRAG runs inside this site and is called in process. It has no address and no credential to configure here; its model and retrieval settings live in the LiteRAG plugin itself.';
$string['settings_head_limits'] = 'Limits';
$string['setting_backend_ingestionapi_url'] = 'Agent MCP endpoint';
$string['setting_backend_ingestionapi_url_desc'] = 'Full URL of the agent\'s MCP endpoint, for example https://agent.example.com/mcp.';
$string['setting_backend_ingestionapi_authmethod'] = 'Authentication method';
$string['setting_backend_ingestionapi_authmethod_desc'] = 'How this site authenticates to the agent.';
$string['setting_authmethod_none'] = 'None';
$string['setting_authmethod_bearer'] = 'Bearer token';
$string['setting_authmethod_header'] = 'Custom header lines';
$string['setting_backend_ingestionapi_authtoken'] = 'Authentication token';
$string['setting_backend_ingestionapi_authtoken_desc'] = 'The bearer token, or one complete "Name: value" header per line when custom headers are used. Stored encrypted.';
$string['setting_backend_ingestionapi_allowinsecure'] = 'Allow plain HTTP';
$string['setting_backend_ingestionapi_allowinsecure_desc'] = 'Permit an http:// endpoint. Intended for development only; a token sent over plain HTTP is readable in transit.';
$string['setting_backend_ingestionapi_allowprivate'] = 'Allow private network addresses';
$string['setting_backend_ingestionapi_allowprivate_desc'] = 'Bypass the site\'s blocked-hosts policy for the agent host. Needed only when the agent lives on an internal network or a development host.';
$string['setting_tool_chat'] = 'Chat tool name';
$string['setting_tool_chat_desc'] = 'Name of the agent\'s chat tool. Only applies to the RAG agent; LiteRAG resolves tool names from its own settings.';
$string['setting_tool_history'] = 'History tool name';
$string['setting_tool_history_desc'] = 'Optional. Leave empty when the agent does not serve a transcript.';
$string['setting_tool_delete'] = 'Delete-conversation tool name';
$string['setting_tool_delete_desc'] = 'Optional. Leave empty when the agent does not support deleting a single conversation.';
$string['setting_tool_deleteuser'] = 'Delete-user-data tool name';
$string['setting_tool_deleteuser_desc'] = 'Optional, but recommended: without it, a deleted user\'s data is removed from this site only.';
$string['setting_tool_memoryoptin'] = 'Memory opt-in tool name';
$string['setting_tool_memoryoptin_desc'] = 'Optional. Leave empty when the agent has no long-term memory.';
$string['setting_tool_recluster'] = 'Question reclustering tool name';
$string['setting_tool_recluster_desc'] = 'Optional. Used by the tutor placement\'s nightly question analytics.';
$string['setting_requesttimeout'] = 'Request timeout';
$string['setting_requesttimeout_desc'] = 'Seconds to wait for an answer before giving up.';
$string['setting_maxmessagelength'] = 'Maximum message length';
$string['setting_maxmessagelength_desc'] = 'Characters a single message may have.';
$string['setting_historylimit'] = 'Conversation history limit';
$string['setting_historylimit_desc'] = 'How many earlier turns travel with a request. Bounds the request size of a long conversation.';
$string['setting_ratelimitperminute'] = 'Requests per minute';
$string['setting_ratelimitperminute_desc'] = 'Chat requests one user may send per minute. 0 disables the limit.';
$string['setting_dailymessagelimit'] = 'Messages per day';
$string['setting_dailymessagelimit_desc'] = 'Messages one user may send per day. 0 means unlimited.';
$string['setting_streamingenabled'] = 'Stream answers';
$string['setting_streamingenabled_desc'] = 'Show an answer as it is produced, where the backend supports it.';
$string['setting_retentiondays'] = 'Conversation retention';
$string['setting_retentiondays_desc'] = 'Days a conversation is kept after its last turn. 0 keeps conversations until a user or an administrator deletes them.';
$string['setting_mcpserviceid'] = 'Callback web service';
$string['setting_mcpserviceid_desc'] = 'The external service the backend uses to call back into Moodle on behalf of the asking user.';
$string['setting_tokenlifetime'] = 'Callback token lifetime';
$string['setting_tokenlifetime_desc'] = 'Seconds a callback token stays valid. 0 issues tokens that do not expire.';

// Status shown in placements.
$string['cachedef_backendhealth'] = 'Last health answer from the active chat backend';
$string['status_health_notprobed'] = 'Configured. This backend offers no health endpoint, so reachability is reported by the source indexing instead.';
$string['status_llm_missing_key'] = 'No API key is configured for the language model.';
$string['status_nobackend'] = 'No AI backend is available';
$string['status_nobackend_help'] = 'Chat is unavailable until an administrator configures a destination in AI Sources and the matching backend connection.';
$string['status_nobackend_admin'] = 'Configure a destination in AI Sources, then set up the connection to the matching backend in the AI Chat Engine settings.';

// Errors.
$string['error_no_backend'] = 'No AI backend is configured. Chat is unavailable.';
$string['error_backend_unavailable'] = 'The AI backend could not be reached. Please try again later.';
$string['error_backend_bad_response'] = 'The AI backend returned an answer that could not be read.';
$string['error_tool_error'] = 'The assistant could not complete that request.';
$string['error_agent_url_missing'] = 'No agent endpoint is configured.';
$string['error_agent_url_invalid'] = 'The configured agent endpoint is not a valid URL.';
$string['error_agent_url_insecure'] = 'The configured agent endpoint uses plain HTTP, which is not permitted.';
$string['error_connector_missing'] = 'The Moodle MCP connector is not installed, so the backend cannot call back into this site.';
$string['error_service_not_configured'] = 'No callback web service has been selected.';
$string['error_service_unavailable'] = 'The callback web service is not available.';
$string['error_token_provision_failed'] = 'A callback token for this user could not be issued.';
$string['error_message_empty'] = 'Please enter a message.';
$string['error_message_too_long'] = 'That message is too long. The maximum is {$a} characters.';
$string['error_rate_limited'] = 'Too many messages just now. Please wait {$a} seconds.';
$string['error_daily_limit'] = 'You have reached the daily message limit.';
$string['error_mode_unsupported'] = 'The configured backend cannot answer in that mode.';
$string['error_thread_not_found'] = 'That conversation could not be found.';

// Shared interface strings.
$string['source'] = 'Source';
$string['sources'] = 'Sources';
$string['tokenlabel'] = 'AI chat engine callback token';
$string['send'] = 'Send';
$string['messageplaceholder'] = 'Ask a question…';
$string['clearconversation'] = 'Clear conversation';
$string['clearconfirm'] = 'Clearing the chat permanently deletes the entire conversation. This action cannot be undone.';
$string['cleared'] = 'The chat has been cleared.';
$string['error_clearfailed'] = 'The chat could not be cleared. The displayed conversation has not been changed. Please try again.';
$string['error_clearnotallowed'] = 'This conversation has been handed in and can no longer be cleared.';
$string['online'] = 'Online';
$string['assistantname'] = 'AI assistant';
$string['thinking'] = 'Thinking…';
$string['event_token_provisioned'] = 'AI chat callback token provisioned';

// Message bubble.
$string['groundedbadge'] = 'Source based';
$string['groundedbadge_title'] = 'This answer cites sources from the knowledge base.';
$string['ungroundedbadge'] = 'General answer';
$string['ungroundedbadge_title'] = 'This answer is not based on the knowledge base — double-check important facts.';
$string['mcpbadge_title'] = 'This answer used Moodle tools.';
$string['senderyou'] = 'You';
$string['copy'] = 'Copy answer';
$string['copied'] = 'Copied';
$string['retry'] = 'Retry';
$string['failed'] = 'The answer could not be delivered.';
$string['toolong'] = 'That message is too long.';
$string['newstarted'] = 'New conversation started.';
$string['resumed'] = 'Conversation resumed.';
$string['transcriptlabel'] = 'Conversation transcript';
$string['expandlabel'] = 'Enlarge the chat';
$string['collapselabel'] = 'Shrink the chat';
$string['error_backend_tool_missing'] = 'The configured backend does not offer that tool.';

// Chat designs.
$string['settings_head_design'] = 'Design';
$string['settings_head_design_desc'] = 'How the chat looks. A design is a named bundle of values that every chat surface can be pointed at, so the tutor block, the chat activity and the learning scenario read as one product. A surface that picks no design uses the built-in look.';
$string['setting_sitedesign'] = 'Site design';
$string['setting_sitedesign_desc'] = 'The design used wherever an instance does not pick its own.';
$string['design_none'] = 'Built-in look';
$string['design_inherit'] = 'Site design';
$string['design'] = 'Chat design';
$string['design_help'] = 'The look this chat is rendered in. "Site design" follows whatever the site is set to, so a later change reaches this instance too.';
$string['contract_language_note_en'] = 'This contract text is maintained in English and is shown here as written.';
$string['topic_contract_ragserver_summary'] = 'The wire protocol a retrieval backend must speak to answer for this engine: tools, '
    . 'transport, authentication and the conversation lifecycle.';
$string['topic_contract_ragserver_title'] = 'RAG server: the integration specification';

// Knowledge base -- which courses a surface may be answered from.
$string['scope_category'] = 'Category: {$a}';
$string['scope_course'] = '{$a->name} ({$a->shortname})';
$string['scope_notallowed'] = 'You may only choose courses you teach yourself. Not allowed: {$a}';
$string['scope_summary'] = 'Of the courses chosen here, {$a->indexed} of {$a->total} have been indexed and can be searched.';
$string['scope_summary_plain'] = 'This selection covers {$a} courses.';
$string['scope_summary_none'] = 'This selection currently covers no courses.';
$string['setting_maxscopecourses'] = 'Course ids per request';
$string['setting_maxscopecourses_desc'] = 'How many courses may be named in one request to the backend. <b>This is the backend\'s number.</b> Its retrieval tool refuses a list longer than it accepts, so raise this only once the backend has raised its own limit — a higher value widens no search, it makes requests fail. When a knowledge base resolves to more courses than this, a course surface falls back to the course it sits in, and a surface without a course leaves the enrolments for the backend to resolve.';

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
 * English language strings for the MCP web service plugin.
 *
 * @package     webservice_elediamcp
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2025 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Audit events.
$string['event_context_verified'] = 'MCP user context verified';
$string['event_context_verified_desc'] = 'The user with id \'{$a->userid}\' verified their MCP context (course filter: {$a->coursefilter}).';
$string['event_token_created'] = 'MCP token created';
$string['event_token_created_desc'] = 'The user with id \'{$a->userid}\' created MCP token \'{$a->label}\' for the user with id \'{$a->relateduserid}\' on service \'{$a->service}\' (via {$a->component}).';
$string['event_token_revoked'] = 'MCP token revoked';
$string['event_token_revoked_desc'] = 'The user with id \'{$a->userid}\' revoked MCP token \'{$a->label}\' belonging to the user with id \'{$a->relateduserid}\' on service \'{$a->service}\'.';
$string['event_tool_invoked'] = 'MCP tool invoked';
$string['event_tool_invoked_desc'] = 'The user with id \'{$a->userid}\' invoked MCP tool \'{$a->toolname}\' (isError: {$a->iserror}, duration: {$a->durationms} ms).';
$string['event_write_performed'] = 'MCP write action performed';
$string['event_write_performed_desc'] = 'The user with id \'{$a->userid}\' performed a write action through MCP tool \'{$a->toolname}\'.';

// Errors.
$string['error_expiry_in_past'] = 'The expiry date must be in the future.';
$string['error_invalid_component'] = 'Unknown component \'{$a}\'. MCP tokens can only be provisioned on behalf of an installed Moodle component.';
$string['error_label_required'] = 'A token label is required.';
$string['error_service_disabled'] = 'The selected web service is disabled.';
$string['error_service_not_mcp'] = 'The selected web service is not configured as an MCP service. Tokens can only be created for configured MCP services.';
$string['error_token_not_found'] = 'The requested MCP token does not exist.';
$string['error_token_not_owned_by_component'] = 'This MCP token was not provisioned by the calling component and cannot be revoked through the internal API.';
$string['err_emergency_disabled'] = 'The MCP web service is temporarily disabled by the site administrator.';
$string['err_empty_request'] = 'Request body is empty';
$string['err_forbidden_origin'] = 'Origin not allowed by site policy';
$string['err_invalid_json'] = 'Invalid JSON';
$string['err_invalid_jsonrpc'] = 'Invalid JSON-RPC version';
$string['err_invalid_protocol_version'] = 'Unsupported MCP protocol version';
$string['err_internal_tool_error'] = 'Internal tool error. Please contact the site administrator if the problem persists.';
$string['err_missing_method'] = 'Missing method';
$string['err_missing_tool_name'] = 'Missing tool name';
$string['err_not_mcp_service'] = 'This token is not authorised for the MCP service.';
$string['err_rate_limit_exceeded'] = 'Rate limit exceeded. Retry after {$a} seconds.';
$string['err_request_too_large'] = 'Request body exceeds the maximum allowed size';
$string['err_token_in_query_disabled'] = 'Token in query string is disabled by site policy. Use the Authorization header instead.';

// Capabilities.
$string['elediamcp:managetokens'] = 'Create and revoke own MCP tokens';
$string['elediamcp:use'] = 'Use MCP web service';
$string['elediamcp:viewcaps'] = 'View own capability tree through MCP';

// Generic.
$string['disabled'] = 'disabled';

// Plugin metadata.
$string['guide_body'] = 'Model Context Protocol is the open protocol AI assistants use to reach outside services. This plugin exposes selected Moodle capabilities as such tools: look up courses, read content, prepare material.

What you decide as an administrator:

- **Which tools are exposed.** That selection is the security boundary.
- **Who gets a token.** Every call runs under the token\'s identity.

What to watch for: the person\'s rights apply, not the assistant\'s. A token for an account with far-reaching rights gives the assistant the same. For assistants it is worth a dedicated account with exactly the rights the task needs.';
$string['guide_summary'] = 'MCP makes Moodle a tool an assistant can use - always as an authenticated person and never beyond that person\'s rights.';
$string['guide_title'] = 'Connecting external AI assistants';
$string['health_configure'] = 'Configuration';
$string['health_services'] = 'Exposed services';
$string['health_services_none'] = 'No service is exposed, so an assistant reaches nothing.';
$string['health_services_ok'] = '{$a} service(s) exposed over MCP.';
$string['health_webservices'] = 'Moodle web services';
$string['health_webservices_off'] = 'Web services are switched off site-wide. Nothing MCP offers can be reached until they are on.';
$string['pluginname'] = 'Model Context Protocol';
$string['shell_help_label'] = 'Help for Model Context Protocol';
$string['shell_settings_label'] = 'Model Context Protocol settings';
$string['privacy:metadata:webservice_elediamcp_token'] = 'Metadata about MCP web service tokens issued to or on behalf of a user. The token secret itself is never stored here.';
$string['privacy:metadata:webservice_elediamcp_token:component'] = 'The first-party component that provisioned the token, if any.';
$string['privacy:metadata:webservice_elediamcp_token:creatorid'] = 'The user who created the token.';
$string['privacy:metadata:webservice_elediamcp_token:externalserviceid'] = 'The external service the token is scoped to.';
$string['privacy:metadata:webservice_elediamcp_token:lastaccess'] = 'The time the token was last used.';
$string['privacy:metadata:webservice_elediamcp_token:name'] = 'The token label.';
$string['privacy:metadata:webservice_elediamcp_token:revoked'] = 'Whether the token has been revoked.';
$string['privacy:metadata:webservice_elediamcp_token:revokedby'] = 'The user who revoked the token.';
$string['privacy:metadata:webservice_elediamcp_token:timecreated'] = 'The time the token was created.';
$string['privacy:metadata:webservice_elediamcp_token:userid'] = 'The user the token authenticates as.';
$string['privacy:metadata:webservice_elediamcp_token:validuntil'] = 'The token expiry time.';
$string['privacy:metadata:webservice_elediamcp_oauth_code'] = 'Short-lived OAuth authorization codes issued during the Authorization Code + PKCE flow. Only a hash of the code is stored; rows are deleted on token exchange or expiry.';
$string['privacy:metadata:webservice_elediamcp_oauth_code:clientid'] = 'The OAuth client the authorization code was issued to.';
$string['privacy:metadata:webservice_elediamcp_oauth_code:expires'] = 'The time the authorization code expires.';
$string['privacy:metadata:webservice_elediamcp_oauth_code:scope'] = 'The scopes approved for the authorization code.';
$string['privacy:metadata:webservice_elediamcp_oauth_code:timecreated'] = 'The time the authorization code was issued.';
$string['privacy:metadata:webservice_elediamcp_oauth_code:userid'] = 'The user who authorised the request.';

// Token management UI.
$string['claude_config_file'] = 'Configuration file location';
$string['claude_config_file_help'] = 'On macOS: <code>~/Library/Application Support/Claude/claude_desktop_config.json</code>. On Windows: <code>%APPDATA%\\Claude\\claude_desktop_config.json</code>. Create the file if it does not exist.';
$string['claude_connect_heading'] = 'Connect an MCP client (Claude Desktop)';
$string['claude_connect_intro'] = 'Claude Desktop and other stdio-only MCP clients connect to this remote server through the <a href="https://www.npmjs.com/package/mcp-remote" target="_blank" rel="noopener">mcp-remote</a> bridge, which requires <a href="https://nodejs.org/" target="_blank" rel="noopener">Node.js</a> (for <code>npx</code>) on the client machine. Add the snippet below to your <code>claude_desktop_config.json</code>, replace the token, then fully restart Claude Desktop.';
$string['claude_connect_serverurl'] = 'MCP server URL';
$string['claude_connect_snippet'] = 'Claude Desktop configuration';
$string['claude_connect_snippet_withtoken'] = 'Ready-to-use Claude Desktop configuration (your new token is already filled in — copy it now)';
$string['claude_connect_tokenhint'] = 'Replace <code>{$a}</code> with a token you created above.';
$string['suite_feature_desc'] = 'Lets an external AI assistant work with this Moodle — under Moodle roles and rights.';
$string['suite_feature_detail'] = 'MCP is the open protocol AI assistants use to reach outside tools. This plugin makes Moodle one of them: an assistant can look up courses, read content and prepare material, always as an authenticated user and never beyond what that user may do. Administrators define which tools are exposed and issue the tokens.';
$string['suite_feature_key_1'] = 'Exposes selected Moodle capabilities as MCP tools.';
$string['suite_feature_key_2'] = 'Every call runs as an authenticated user, under that person\'s rights.';
$string['suite_feature_key_3'] = 'Administrators choose which tools are exposed and issue tokens.';
$string['suite_feature_name'] = 'Model Context Protocol';
$string['token_actions'] = 'Actions';
$string['token_create'] = 'Create token';
$string['token_created'] = 'Created';
$string['token_created_once'] = 'Your new token has been created. Copy it now — for security it will not be shown again.';
$string['token_label'] = 'Label';
$string['token_label_help'] = 'A name to help you recognise this token later, for example the device or application it is used by.';
$string['token_lastused'] = 'Last used';
$string['token_never'] = 'Never';
$string['token_revoke'] = 'Revoke';
$string['token_revoke_confirm'] = 'Are you sure you want to revoke the token "{$a}"? Any application using it will immediately lose access. This cannot be undone.';
$string['token_revoked_notice'] = 'The token has been revoked.';
$string['token_service'] = 'Service';
$string['token_service_help'] = 'The MCP service this token grants access to. Only services your administrator has enabled for MCP and that you are permitted to use are listed.';
$string['token_status'] = 'Status';
$string['token_status_active'] = 'Active';
$string['token_status_expired'] = 'Expired';
$string['token_status_revoked'] = 'Revoked';
$string['token_validuntil'] = 'Expires';
$string['token_validuntil_help'] = 'An optional date after which the token stops working. Leave disabled for a token that never expires.';
$string['tokens_heading'] = 'MCP tokens';
$string['tokens_existing_heading'] = 'Existing tokens';
$string['tokens_intro'] = 'Tokens let MCP clients and AI agents access Moodle on your behalf. Treat each token like a password.';
$string['tokens_navlabel'] = 'MCP tokens';
$string['tokens_activate_service_button'] = 'Activate MCP service';
$string['tokens_activate_service_success'] = 'The MCP service has been activated. You can now create tokens.';
$string['tokens_no_services_configured'] = 'No MCP service has been configured on this site yet. Activate the default MCP service to enable token creation.';
$string['tokens_no_services_permitted'] = 'MCP services are configured on this site, but you are not currently permitted to use any of them. This usually means the service is restricted to authorised users; contact your administrator to be granted access.';
$string['tokens_none'] = 'You have not created any MCP tokens yet.';

// Settings.
$string['configuration_error_invalid_origin'] = 'Each CORS origin must be an absolute http(s) origin, for example https://app.example.com.';
$string['configuration_error_nonnegative'] = 'Enter a value of zero or higher.';
$string['configuration_error_wildcard_origin'] = 'Wildcard CORS origin (*) is not allowed here. Add explicit trusted origins instead.';
$string['configuration_heading'] = 'MCP configuration';
$string['configuration_hint'] = 'Configure external services, token policy, security limits and the MCP tool catalogue.';
$string['configuration_saved'] = 'MCP configuration saved.';
$string['configuration_security_heading'] = 'Security and limits';
$string['configuration_services_heading'] = 'Services and tokens';
$string['configuration_shell_link'] = 'Open MCP Plugin Shell';
$string['configuration_shell_link_desc'] = 'Open the plugin-owned MCP configuration page.';
$string['configuration_tag_mcp'] = 'MCP';
$string['configuration_tag_security'] = 'Security';
$string['configuration_tagline'] = 'Configuration';
$string['configuration_oauth_heading'] = 'OAuth 2.1 authorization server';
$string['configuration_tools_heading'] = 'Tool catalogue';
$string['setting_oauth_allow_dynamic_registration'] = 'Allow dynamic client registration';
$string['setting_oauth_allow_dynamic_registration_desc'] = 'When enabled, MCP clients may register themselves as public PKCE clients (RFC 7591) and obtain a client_id automatically. Required for zero-configuration clients such as Claude Desktop. Disable to only permit clients you provision yourself.';
$string['setting_oauth_allow_dynamic_registration_help'] = $string['setting_oauth_allow_dynamic_registration_desc'];
$string['setting_oauth_code_ttl'] = 'Authorization code lifetime (seconds)';
$string['setting_oauth_code_ttl_desc'] = 'How long an issued authorization code remains valid before it must be exchanged for a token. Clamped to 30–600 seconds. Default 300.';
$string['setting_oauth_code_ttl_help'] = $string['setting_oauth_code_ttl_desc'];
$string['setting_oauth_enabled'] = 'Enable OAuth 2.1 (Authorization Code + PKCE)';
$string['setting_oauth_enabled_desc'] = 'When enabled, the plugin publishes an OAuth 2.1 authorization server so that compliant MCP clients can complete an Authorization Code + PKCE flow and receive an MCP token without a manually created token. Existing bearer tokens keep working regardless of this setting. Disabled by default.';
$string['setting_oauth_enabled_help'] = $string['setting_oauth_enabled_desc'];
$string['setting_oauth_token_ttl'] = 'OAuth access token lifetime (seconds)';
$string['setting_oauth_token_ttl_desc'] = 'Expiry applied to tokens issued through the OAuth flow. Set to 0 for tokens that do not expire (the default, matching manually created tokens).';
$string['setting_oauth_token_ttl_help'] = $string['setting_oauth_token_ttl_desc'];
$string['setting_oauth_warning'] = 'The OAuth flow lets any user who can sign in and holds the MCP capability mint a token for a client that completes the flow. Keep the allowed CORS origins and the MCP service list tight, and only enable dynamic client registration if you understand that clients can self-register.';
$string['premium_status_active'] = 'Premium active';
$string['premium_status_active_notice'] = 'The premium add-on unlocks the complete curated MCP tool catalogue. Raw Moodle Web Service functions still require the separate setting below.';
$string['premium_status_free'] = 'Free version';
$string['premium_status_free_notice'] = 'The free version exposes the baseline MCP tools only. Install and enable the eLeDia.ai Tutor Premium add-on with feature mcp_tools to unlock the full catalogue.';
$string['premium_status_free_tools'] = '{$a} free tools';
$string['premium_status_heading'] = 'Edition and tool access';
$string['premium_status_intro'] = '{$a->edition}: {$a->freecount} free tools are available. Premium adds {$a->premiumcount} more curated tools.';
$string['premium_status_premium_tool_list'] = 'Premium tools';
$string['premium_status_premium_tools'] = '{$a} premium tools';
$string['setting_allow_token_in_query'] = 'Allow token in query string';
$string['setting_allow_token_in_query_desc'] = 'When enabled, the MCP endpoint accepts the token through the <code>?wstoken=</code> query parameter. Disabled by default because tokens in URLs leak into web server logs, browser history, and HTTP referer headers. Clients should use the <code>Authorization: Bearer</code> header.';
$string['setting_allow_token_in_query_help'] = $string['setting_allow_token_in_query_desc'];
$string['setting_allowed_origins'] = 'Allowed CORS origins';
$string['setting_allowed_origins_desc'] = 'One origin per line (e.g. <code>https://app.example.com</code>). Use <code>*</code> to allow any origin (not recommended). When empty, only same-origin requests are accepted. The MCP server validates the <code>Origin</code> header on all requests and returns HTTP 403 for any origin not on this list.';
$string['setting_allowed_origins_help'] = $string['setting_allowed_origins_desc'];
$string['setting_enforce_mcp_service'] = 'Restrict endpoint to MCP services';
$string['setting_enforce_mcp_service_desc'] = 'When enabled (default), the MCP endpoint only accepts tokens that belong to a configured MCP external service. This makes the MCP service list the access boundary for the endpoint, not just for token issuance. Disable only if you must present tokens minted for other web services.';
$string['setting_enforce_mcp_service_help'] = $string['setting_enforce_mcp_service_desc'];
$string['setting_emergency_disable'] = 'Emergency disable';
$string['setting_emergency_disable_desc'] = 'When enabled, every request to the MCP endpoint returns HTTP 503 Service Unavailable. Use this as a temporary kill switch during incident response.';
$string['setting_emergency_disable_help'] = $string['setting_emergency_disable_desc'];
$string['setting_expose_raw_functions'] = 'Expose raw Moodle Web Service functions';
$string['setting_expose_raw_functions_desc'] = 'When enabled, every external function assigned to the authenticated service is exposed as an MCP tool, in addition to the curated AI-native tools. Disabling this restricts the surface to the AI-native tool set only, which is recommended for production AI agents.';
$string['setting_expose_raw_functions_help'] = $string['setting_expose_raw_functions_desc'];
$string['setting_expose_raw_functions_warning'] = 'Security warning: enabling raw Web Service functions greatly expands the tool surface exposed to AI clients. Use this only for controlled administrator testing, not as the default for production agents. The read-only/destructive annotations derived for raw functions are best effort and must not be treated as a security boundary.';
$string['setting_max_request_size'] = 'Maximum request body size (bytes)';
$string['setting_max_request_size_desc'] = 'Requests with a body larger than this value are rejected with HTTP 413. Default 1 MiB.';
$string['setting_max_request_size_help'] = $string['setting_max_request_size_desc'];
$string['setting_rate_limit_per_hour'] = 'Rate limit per hour (per token)';
$string['setting_rate_limit_per_hour_desc'] = 'Maximum number of requests per hour for a single token. Default 600.';
$string['setting_rate_limit_per_hour_help'] = $string['setting_rate_limit_per_hour_desc'];
$string['setting_rate_limit_per_minute'] = 'Rate limit per minute (per token)';
$string['setting_rate_limit_per_minute_desc'] = 'Maximum number of requests per minute for a single token. Default 60.';
$string['setting_rate_limit_per_minute_help'] = $string['setting_rate_limit_per_minute_desc'];
$string['setting_token_retention_days'] = 'Revoked token retention (days)';
$string['setting_token_retention_days_desc'] = 'How many days to keep the audit record of a revoked MCP token before the scheduled cleanup task deletes it. Connector-provisioned tokens are re-minted regularly, so their revoked records can accumulate. Set to 0 to keep every revoked token record indefinitely.';
$string['setting_token_retention_days_help'] = $string['setting_token_retention_days_desc'];
$string['setting_services'] = 'MCP external services';
$string['setting_services_desc'] = 'The external services that may issue MCP tokens. Only services selected here can be chosen in the self-service token UI or targeted through the internal token API. Create the services under <em>Site administration → Server → Web services → External services</em> first, then enable them here.';
$string['setting_services_help'] = $string['setting_services_desc'];
$string['setting_tools_page_size'] = 'Default page size for tools/list';
$string['setting_tools_page_size_desc'] = 'Maximum number of tools returned per <code>tools/list</code> response. Larger sets are paginated through the <code>nextCursor</code> field.';
$string['setting_tools_page_size_help'] = $string['setting_tools_page_size_desc'];

// OAuth 2.1 authorization server.
$string['oauth_consent_allow'] = 'Allow access';
$string['oauth_consent_deny'] = 'Deny';
$string['oauth_consent_intro'] = '<strong>{$a->client}</strong> is requesting access to Moodle on your behalf ({$a->user}). If you approve, it will be able to use the MCP tools with your permissions.';
$string['oauth_consent_note'] = 'Approving creates an MCP access token bound to your account. You can revoke it at any time under Preferences → MCP tokens.';
$string['oauth_consent_scopes'] = 'The application is requesting the following access:';
$string['oauth_consent_title'] = 'Authorise MCP client';
$string['oauth_default_client_name'] = 'MCP client';
$string['oauth_err_access_denied'] = 'You denied the authorization request.';
$string['oauth_err_client_id_required'] = 'A client_id is required.';
$string['oauth_err_code_challenge'] = 'A valid PKCE code_challenge is required (43–128 base64url characters).';
$string['oauth_err_code_client_mismatch'] = 'The authorization code was not issued to this client.';
$string['oauth_err_disabled'] = 'The OAuth authorization server is not enabled on this site.';
$string['oauth_err_expired_code'] = 'The authorization code has expired.';
$string['oauth_err_grant_type'] = 'Unsupported grant_type. Only authorization_code is supported.';
$string['oauth_err_guest'] = 'You must sign in with a full Moodle account to authorise an MCP client. Guest access is not permitted.';
$string['oauth_err_invalid_code'] = 'The authorization code is invalid.';
$string['oauth_err_invalid_registration_body'] = 'The registration request body must be a JSON object.';
$string['oauth_err_missing_token_params'] = 'The token request is missing one or more required parameters.';
$string['oauth_err_no_service'] = 'No MCP service is configured to issue tokens against.';
$string['oauth_err_pkce_failed'] = 'PKCE verification failed.';
$string['oauth_err_pkce_method'] = 'code_challenge_method must be S256.';
$string['oauth_err_public_client_only'] = 'Only public clients (token_endpoint_auth_method "none") are supported.';
$string['oauth_err_redirect_uri_invalid'] = 'One or more redirect URIs are invalid. Use an absolute https URI, an http URI on a loopback host, or a private-use scheme.';
$string['oauth_err_redirect_uri_mismatch'] = 'The redirect_uri does not match a registered redirect URI for this client.';
$string['oauth_err_redirect_uri_required'] = 'A redirect_uri is required because the client has more than one registered redirect URI.';
$string['oauth_err_redirect_uris_required'] = 'At least one redirect_uri is required.';
$string['oauth_err_registration_disabled'] = 'Dynamic client registration is disabled on this site.';
$string['oauth_err_response_type'] = 'Unsupported response_type. Only "code" is supported.';
$string['oauth_err_token_issue'] = 'The authorization could not be completed for your account.';
$string['oauth_err_unknown_client'] = 'Unknown OAuth client.';
$string['oauth_err_unsupported_grant_registration'] = 'Only the authorization_code grant type is supported.';
$string['oauth_privacy_codes_heading'] = 'MCP OAuth authorization codes';
$string['oauth_token_label'] = 'OAuth · {$a}';

// Server metadata.
$string['server_instructions'] = 'Moodle MCP server with curated AI-native tools.

Identity & context: call moodle_me first to confirm identity, then moodle_verify_user_context for the full roles/groups/capabilities probe.

People discovery: moodle_find_user resolves a free-form name fragment (e.g. "erika") to a list of messageable Moodle users with concrete ids. Use it BEFORE moodle_send_message whenever you do not already know the recipient\'s exact user id.

Course discovery: moodle_my_courses lists enrolled courses; moodle_search_courses searches the public catalogue; moodle_course_contents enumerates sections/activities of one course; moodle_get_resource returns the body of a page/book chapter/label/url/resource by cmid.

Feeds & progress: moodle_get_announcements for the latest news-forum posts; moodle_calendar_upcoming for deadlines; moodle_my_assignments for submission status; moodle_my_grades for course finals (or per-item with include_items + course_id).

Write tools: moodle_send_message accepts to_user_id (preferred), to_username (exact match) or to_query (fuzzy, single-match only). Two-step flow — call once without confirm to receive a preview, then call again with confirm=true and the user\'s explicit go-ahead to actually send. Hard limit 4000 chars.

Read-only tools are safe to call automatically; write tools require an explicit "confirm" argument.';
$string['servername'] = 'Moodle MCP Server';

// Scheduled tasks.
$string['err_prompt_not_found'] = 'Prompt "{$a}" was not found or is not available for this token.';
$string['err_prompt_missing_argument'] = 'Prompt argument "{$a}" is required.';
$string['err_prompt_unknown_argument'] = 'Prompt argument "{$a}" is not supported.';
$string['err_prompt_invalid_argument'] = 'Prompt argument "{$a}" has an invalid type.';
$string['err_prompt_arguments_object'] = 'Prompt arguments must be an object.';
$string['err_internal_prompt_error'] = 'The prompt could not be rendered due to an internal error.';
$string['prompt_arg_titel'] = 'Optional course title.';
$string['prompt_arg_kurs_id'] = 'Moodle course id.';
$string['prompt_arg_anzahl_sektionen'] = 'Optional number of course sections.';
$string['prompt_arg_aktivitaet'] = 'Optional activity name or id to focus on.';
$string['prompt_kurs_aus_dokument_title'] = 'Create a course from a document';
$string['prompt_kurs_aus_dokument_description'] = 'Derive a course outline from the document attached by the user and create it after explicit confirmation.';
$string['prompt_kurs_aus_dokument_text'] = 'Use the document attached by the user as the source for a Moodle course outline. ' .
    'Propose the title, sections and activities first and ask the user to confirm the complete plan. ' .
    'Then use moodle_create_course, moodle_manage_sections and moodle_create_activity with their two-step preview/confirm flow; never bypass confirmation.' .
    '{$a}';
$string['prompt_wochenueberblick_title'] = 'Weekly overview';
$string['prompt_wochenueberblick_description'] = 'Summarise the authenticated learner\'s upcoming work, grades and calendar events.';
$string['prompt_wochenueberblick_text'] = 'Create a concise weekly overview for the authenticated user. Call moodle_due_work, moodle_my_grades and moodle_calendar_upcoming, group results by urgency and course, and clearly distinguish missing data from no results.';
$string['prompt_kurs_health_check_title'] = 'Course health check';
$string['prompt_kurs_health_check_description'] = 'Review a course for participation, unanswered forum posts and grading backlog.';
$string['prompt_kurs_health_check_text'] = 'Inspect Moodle course {$a} with moodle_course_health, moodle_unanswered_forum_posts and moodle_grading_queue. Summarise actionable risks and suggest next steps without changing data.';
$string['prompt_bewertungs_session_title'] = 'Grading session';
$string['prompt_bewertungs_session_description'] = 'Guide a teacher through a focused grading session with explicit confirmation before every grade write.';
$string['prompt_bewertungs_session_text'] = 'Start a grading session for course {$a->courseid}. Use moodle_grading_queue and moodle_read_submission to select and inspect work{$a->activity}. Present feedback and proposed grades, ask for explicit confirmation, and only then call moodle_grade_submission with confirm=true.';
$string['prompt_kursmaterial_zusammenfassen_title'] = 'Summarise course materials';
$string['prompt_kursmaterial_zusammenfassen_description'] = 'Summarise the visible materials in a Moodle course.';
$string['prompt_kursmaterial_zusammenfassen_text'] = 'Use moodle_course_contents and moodle_get_resource to collect visible materials for course {$a}. Produce a structured summary with links or activity names, and do not invent content that was not returned by the tools.';
$string['task_prune_revoked_tokens'] = 'Prune old revoked MCP tokens';

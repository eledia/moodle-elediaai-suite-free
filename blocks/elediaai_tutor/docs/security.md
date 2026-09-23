# Security notes

## Threat model summary

The block brokers an untrusted external service (the RAG/Tutor server) and a
powerful credential (a user-scoped Moodle MCP token) on behalf of a logged-in
user. The controls below keep secrets server-side and constrain both inbound
(browser → Moodle) and outbound (Moodle → RAG) trust.

## Secret handling

- The Moodle MCP token, RAG authorization token and RAG URL are **never** sent
  to the browser. The browser only calls Moodle's authenticated AJAX endpoints.
- The RAG auth token is stored **encrypted at rest** with
  `admin_setting_encryptedpassword` (Moodle's site encryption key in
  moodledata) and decrypted server-side only, in `security::rag_auth_token()`,
  at the moment the RAG request is built. It never reaches the browser.
- Provisioned tokens are attributed to the component `block_elediaai_tutor`
  (auditable, independently revocable) and the plain value is cached only in a
  short-lived application cache — never written to the database.
- Token expiry/revocation is handled gracefully: on a RAG auth failure the
  connector drops the cached token, mints a fresh one and retries **once**.

## Inbound request hardening

- All external functions `require_login()`, resolve and `validate_context()` an
  allowed system, course or Tutor-block context, and `require_capability()` the
  appropriate capability.
- Course-scoped chat and history bind that context to the server-resolved
  course. System contexts are global-only; block contexts must belong to an
  actual `block_elediaai_tutor` instance whose configuration resolves to the
  same course. Stored conversations keep their stored course authoritative.
- Current course access is rechecked for conversation lists, transcripts,
  continuations and single-conversation deletion. Global history contains only
  global conversations.
- They run over Moodle core/ajax (session + sesskey protected) and are **not**
  attached to any external service, so they are unreachable via public Web
  Services.
- All input is validated through Moodle parameter APIs; message length and a
  per-user rate limit are enforced server-side.
- Conversation access is strictly owner-scoped — every read/delete is filtered
  by `userid`.

## Outbound (SSRF) hardening

- The destination is **only** the admin-configured RAG URL. Users can never
  supply or influence it.
- The URL is validated: scheme must be `https` (or `http` only when an admin
  explicitly opts in); malformed URLs are rejected.
- Requests go through Moodle's `curl` wrapper, so the site **cURL security
  helper** (blocked hosts, internal ranges, disallowed ports) applies as defence
  in depth. Redirects are disabled.

## Output safety

- Assistant Markdown is converted and **sanitised through Moodle's HTML
  purifier** (`format_text` + `FORMAT_MARKDOWN`) server-side before it reaches
  the DOM.
- User message text is escaped (rendered as text, never HTML).
- RAG errors surface a generic, localised message; detail goes only to
  developer-mode debug info and is scrubbed of secrets.

## Auditing

Security-relevant actions emit events (message sent, response received, request
failed, conversation created/cleared, token provisioned, configuration error)
without logging secrets or full message content.

## Reviewer checklist

- [ ] No secret appears in any `js_call_amd` argument or template.
- [ ] Every external `execute()` does login + context + capability checks.
- [ ] No code path lets a request target a non-configured URL.
- [ ] Assistant output is only ever inserted after `format_text` sanitisation.
- [ ] Conversation queries are always filtered by `userid`.

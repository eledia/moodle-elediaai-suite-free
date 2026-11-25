# Security notes

## Purpose

This document describes the security state of `block_elediaai_tutor`: assets,
trust boundaries, threats, controls, findings and incident handling. It
complements `privacy.md`. No secrets, keys or tokens belong here.

## Scope

- **Product:** eLeDia.ai Tutor (`block_elediaai_tutor`), release 1.0.22
- **Security responsibility:** maintainers of the eLeDia.ai suite; operation by
  the hosting institution
- **Systems:** Moodle with `local_elediaai_chatengine`, `webservice_elediamcp`,
  `local_elediaai_core`; the connected RAG/Tutor service
- **Protection need:** high (brokers a user-scoped Moodle credential)
- **State:** 2026-11-24
- **Last human review:** open

## Assets and trust boundaries

| Asset | Confidentiality | Integrity | Availability | Note |
|---|---|---|---|---|
| User-scoped Moodle MCP token | critical | high | normal | minted by `webservice_elediamcp`, cached briefly, never sent to the browser |
| Agent URL and authorization token | high | high | normal | configured and encrypted in `local_elediaai_chatengine` |
| Learner questions and answers | high | normal | normal | stored by the engine; see `privacy.md` |
| Tutor profiles, custom CSS, uploaded logo/avatar | normal | high | normal | admin- and teacher-controlled content rendered to all users |

Trust boundaries: browser → Moodle (authenticated AJAX), Moodle → RAG/Tutor
service (outbound HTTP through the chat engine), RAG/Tutor service → Moodle
(MCP callbacks with the user-scoped token).

## Threat model summary

The block brokers an untrusted external service (the RAG/Tutor server) and a
powerful credential (a user-scoped Moodle MCP token) on behalf of a logged-in
user. The controls below keep secrets server-side and constrain both inbound
(browser → Moodle) and outbound (Moodle → RAG) trust.

## Secret handling

- The Moodle MCP token, the agent's authorization token and its URL are
  **never** sent to the browser. The browser only calls Moodle's authenticated
  AJAX endpoints.
- Since the tutor became a placement on the chat engine, the agent's
  authorization token is a setting of `local_elediaai_chatengine`, stored
  **encrypted at rest** with `admin_setting_encryptedpassword` (Moodle's site
  encryption key in moodledata) and decrypted server-side only when the request
  is built. The block itself no longer holds a connection secret.
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
- All input is validated through Moodle parameter APIs. Message length and a
  per-user rate limit are enforced server-side by the chat engine; the block
  adds its per-instance daily limit and the per-instance quiz lock
  (`placement::quiz_lock()`, enforced by the engine before rate limit, quota
  and backend).
- Conversation access is strictly owner-scoped — every read/delete is filtered
  by `userid`.

## Outbound (SSRF) hardening

- These controls live in `local_elediaai_chatengine` (`local\connection`,
  `local\http\curl_transport`); the block makes no outbound HTTP calls of its
  own.
- The destination is **only** the admin-configured agent endpoint. Users can
  never supply or influence it.
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

## Threat model

| Threat / misuse | Components | Likelihood / impact | Controls | Residual risk | Status |
|---|---|---|---|---|---|
| Token leak to the browser or logs | placement, engine, templates | low / critical | server-side only, no secret in `js_call_amd` or templates, events without secrets | low | mitigated |
| Cross-course access through a forged context (IDOR) | external functions, placement | medium / high | server-bound course scope, stored course authoritative, owner-scoped queries | low | mitigated (`bug28`) |
| Stored XSS through SVG uploads | `lib.php`, `tutor_io` | medium / high | force-download, `tutor_io::is_safe_image()` | low | mitigated (`bug13`) |
| Stored XSS through custom CSS `</style>` breakout | settings, widget | low / high | admin-only; `security::custom_css()` strips angle brackets, `@import`, `expression()` and `javascript:` | low | mitigated (`bug14`) |
| SSRF through the agent URL | engine connection | low / high | admin-only URL, scheme check, cURL security helper, no redirects | low | mitigated |
| Script in greeting or other texts | text fields | medium / medium | script and style blocks removed with content | low | mitigated (1.0.2, G-01) |
| Abuse of the backend (cost, flooding) | engine | medium / medium | rate limit, daily limits, token quota of `local_elediaai_core` | medium | open (human assessment) |

## Security controls

| Area | Implementation | Evidence | Status |
|---|---|---|---|
| Authentication | `require_login()` in every endpoint and page | `tests/external_test.php`, `tests/help_access_test.php` | implemented |
| Authorisation | capabilities in `db/access.php`, `validate_context()`, server-bound scope | `tests/placement_test.php`, `tests/surface_test.php` | implemented |
| Secrets | encrypted setting in the engine; tokens cached, never persisted in plain text | engine tests | implemented |
| Encryption | HTTPS to the agent unless an admin opts in to HTTP | engine tests | implemented |
| Input / output | Moodle parameter APIs, `format_text` sanitisation, escaped user text | `tests/text_test.php`, `tests/widget_test.php` | implemented |
| Dependencies / SBOM | Lucide icons declared in `thirdpartylibs.xml` | `../thirdpartylibs.xml` | implemented |
| Logging / monitoring | events without secrets, redacted diagnostics (30 days) | `tests/error_message_test.php` | implemented |
| Backup / restore | not applicable: no data beyond Moodle's own tables | — | not applicable |
| CI / deploy | suite pipeline (PHPCS, PHPUnit, Behat, design-lint) | `.gitlab-ci.yml` | implemented |

## Findings

All findings from the reviews of June 2026 (`bug02`–`bug17`, `bug28`) are fixed;
details in `05-quality.md`. No open critical or high findings are known.

## Incident response

- **Reporting:** through the GitLab project of the suite.
- **Triage and escalation:** maintainers of the suite assess severity and inform
  the operating institutions.
- **Containment:** switch the tutor off in the suite (feature switch in
  `local_elediaai_core`) or revoke the callback web service in MCP.
- **Evidence:** Moodle logs, the tutor's diagnostics and the engine's turn log.
- **Recovery:** fix release through the usual tag and deploy process.
- **Privacy link:** check `privacy.md` for affected data and notification duties.
- **Lessons learned:** as `bugXX` or issue, with a regression test.

## Review on changes and releases

- [ ] New or changed attack surfaces are in the threat model.
- [ ] Authentication, authorisation and course boundaries are checked.
- [ ] No secrets or production data were committed or logged.
- [ ] Dependencies and known vulnerabilities are checked.
- [ ] Relevant automated and manual security tests are documented.
- [ ] Monitoring and the incident process still work.
- [ ] Open critical or high findings block the release or are accepted by a human.
- [ ] Relevant changes are in `CHANGES.md` and `releasenotes.md`.


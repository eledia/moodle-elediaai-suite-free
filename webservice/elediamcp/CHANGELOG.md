# Changelog

All notable changes to the **webservice_elediamcp** plugin are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.8.1] – 2026-09-25
### Added
- `moodle_list_course_categories` (read, free): the categories the user may
  create courses in, with id and path, so a named category becomes a
  `category_id` (#33).
### Fixed
- `moodle_create_course` no longer falls back blindly to the default
  category. Without `category_id` it takes the only category the user may
  create in, else the default if allowed, else it asks with the allowed
  categories by name (#33).
- A course creator is enrolled in the course they just created, like the
  course form does (`$CFG->creatornewroleid`); before, the course was
  unreachable for them (#34).
- A missing capability is reported as such, not as "internal tool error,
  try again later", and every failed tool call records an `errorcode` in
  `local_elediaai_core_action` (#33).

## [1.7.0] – 2026-08-06
### Added
- MCP prompts/list and prompts/get with five built-in Suite workflow prompts.
  Prompt visibility follows the availability of every orchestrated tool, and
  third-party plugins may contribute prompts through
  `<component>_elediamcp_prompts()`.
- **OAuth 2.1 Authorization Code + PKCE** authorization server (opt-in, off by
  default via the new `oauth_enabled` setting), so a compliant MCP client can
  obtain an MCP token without a human manually creating one (SUI-621, follows
  SUI-45):
  - Authorization Server Metadata document (`/.well-known/oauth-authorization-server`,
    RFC 8414); the Protected Resource Metadata now advertises the authorization
    server when the flow is enabled.
  - Dynamic Client Registration (`oauth/register.php`, RFC 7591) for public,
    PKCE-only clients (no client secret); toggle `oauth_allow_dynamic_registration`.
  - Authorization endpoint (`oauth/authorize.php`) requiring Moodle login and a
    sesskey-protected consent screen; issues a single-use, short-lived,
    hash-stored authorization code bound to the user, client, redirect URI and
    PKCE challenge (`oauth_code_ttl`, default 300 s).
  - Token endpoint (`oauth/token.php`) exchanging code + `code_verifier` (S256
    only) for a Moodle MCP token minted through `token_manager`
    (`oauth_token_ttl`, default 0 = no expiry). The token is visible and
    revocable under Preferences → MCP tokens.
  - New tables `webservice_elediamcp_oauth_client` and
    `webservice_elediamcp_oauth_code`; privacy provider and the cleanup task
    extended (expired codes are garbage-collected).
  - 23 PHPUnit tests covering the full flow and the negative cases (unknown
    client, redirect-URI/PKCE/expiry/replay/`response_type`/`grant_type`/S256).
- Existing bearer-token clients keep working regardless of the setting.

## [1.5.3] - 2026-08-04
### Fixed
- Advertised `serverInfo`/server-info version is now derived from
  `$plugin->release` at runtime (`server::get_server_version()`) instead of a
  hand-maintained `SERVER_VERSION` constant, which had silently gone stale twice
  (stuck at 1.1.0, then lagging the release again at 1.5.1 vs. 1.5.2). Single
  source of truth; a PHPUnit assertion now pins the advertised version to
  `$plugin->release` to guard future drift. README maturity badge realigned
  (SUI-43).

## [1.5.2] – 2026-08-03
### Geändert
- Referenzen auf die umbenannten Suite-Komponenten nachgezogen (`local_lernhive_ai` → `local_elediaai_core` usw.); keine Verhaltensänderung (SUI-560)

## [1.5.1] - 2026-07-31

### Changed
- `serverInfo`/`initialize` now advertise `SERVER_VERSION` `1.5.1`, aligned with
  the plugin release in `version.php` (previously lagged behind the release).

## [1.5.0] - 2026-07-23

### Added
- Third-party tool provider hook ("shared verbs", LernHive ADR-P15 stage 4):
  any installed plugin can contribute MCP tools by implementing
  `<component>_elediamcp_tools()` in its `lib.php`, returning class-strings
  that implement `webservice_elediamcp\local\ai\ai_tool`. Contributed
  tools flow through the existing dispatch, capability-check and audit
  pipeline; on tool-name collisions the built-in tool wins; invalid entries
  are skipped with a developer debugging message. Contributed tools bypass
  the premium gating (the site installed them itself).

## [1.4.2] - 2026-07-09

### Fixed
- Added the required `@webservice` Behat feature tag for Moodle Plugins
  Directory checks.
- Increased the Moodle plugin build number for the Marketplace resubmission.

## [1.4.1] - 2026-07-07

### Security
- `moodle_enrol_user` now verifies the requested role against
  `get_assignable_roles()` so a teacher token can no longer assign roles
  (e.g. manager) it may not assign in the Moodle UI.
- `moodle_generate_questions` enforces `local/elediaai_questiongen:use` and
  `moodle/question:add` on the create path too, and rejects the request when
  question banks exist but none is usable instead of silently creating a new
  bank.
- `moodle_read_submission` and `moodle_grade_submission` accept only real
  assignment participants (enrolment and separate-groups boundaries via
  `assign::get_participant()`) and refuse blind-marking assignments.
- `moodle_manage_sections` requires `moodle/course:sectionvisibility` for the
  visibility toggle, matching core course state actions.

### Fixed
- `serverInfo`/`initialize` now advertise the real plugin release
  (`SERVER_VERSION` was stuck at 1.1.0).
- Book chapter lookup in `moodle_get_resource` uses a targeted query instead
  of relying on array indexing by record id.
- The raw-functions admin setting documents that derived tool annotations are
  best effort only.

## [1.4.0] - 2026-07-02

### Added
- Teacher grading loop: `moodle_read_submission` returns a student's
  assignment submission content (online text, readable files inline) and
  `moodle_grade_submission` saves points plus a feedback comment with a
  preview/confirm flow. With marking workflow enabled the grade is stored
  as "In review" for teacher release; advanced grading forms are rejected.
- `moodle_course_health`: read-only course report with inactive students,
  per-assignment submission/grading coverage and course grade summary.
- `moodle_message_course_students`: targeted bulk message (all students,
  not-submitted for an assignment, or inactive for N days) with recipient
  preview, confirm flow and a hard recipient cap.
- `moodle_update_activity`: change name, visibility, description, page
  content, url target and assignment dates of existing activities via the
  canonical update path (events, calendar and gradebook stay consistent).
- `moodle_manage_sections`: create, rename and hide/show course sections.

## [1.3.0] - 2026-07-02

### Added
- Student-facing self-study tool family (only on sites with
  `local_elediaai_selfstudy` installed): `moodle_selfstudy_create_quiz`
  generates a personal practice quiz server-side via the site AI provider,
  `moodle_selfstudy_list_quizzes` and `moodle_selfstudy_get_quiz` drive the
  conversational quiz flow (questions are returned without solutions), and
  `moodle_selfstudy_submit_attempt` grades answers, stores the attempt and
  records Moodle competency evidence with teacher-review recommendations.
  Teacher per-course opt-in and per-student daily quotas are enforced by the
  wrapped plugin.

## [1.2.0] - 2026-07-02

### Added
- Premium `moodle_create_activity` tool that creates course activities (page,
  label, url, book including chapters, assign) with preview and confirmation
  flow.
- Premium `moodle_generate_h5p` tool (only on sites with `local_elediaai_h5pauthor`
  installed) that generates H5P dialog cards, fill-in-the-blanks or
  single-choice content server-side via the site AI provider and publishes it
  to the content bank or as a course activity.
- Premium `moodle_generate_questions` tool (only on sites with
  `local_elediaai_questiongen` installed) that generates quiz questions
  server-side via the site AI provider and imports them into a question bank
  of the course, creating a bank when the course has none.

### Changed
- The tool registry now registers the eledia.ai generation tools conditionally,
  so marketplace installations without the eledia.ai suite are unaffected.

## [1.1.0] - 2026-06-28

### Added
- Plugin-owned MCP help page that renders the plugin documentation inside the
  MCP plugin shell without a runtime dependency on LernHive.
- Optional Premium gating for the extended MCP tool surface through the
  `mcp_tools` feature of `local_elediaai_tutor_premium`.
- Premium `moodle_update_course` tool for course title, shortname, visibility,
  summary and date updates with preview and confirmation flow.
- Premium `moodle_enrol_user` tool for manual course enrolments with preview
  and confirmation flow.

### Changed
- MCP configuration, token management and Claude connection guidance now live on
  one MCP page in the shared plugin shell.
- The MCP configuration page now shows whether the site is using the Free or
  Premium MCP tool catalogue and lists the additional premium tools.
- Premium feature detection now uses only the canonical
  `local_elediaai_tutor_premium` add-on.
- Token creation uses a post/redirect/get flow so refreshes do not accidentally
  create another token.
- DevFlow documentation was consolidated into the root `Docs/` structure.

### Fixed
- The MCP shell help action now opens the plugin-owned MCP help page instead of
  the eLeDia.ai Tutor/LernHive help route.

## [1.0.1] - 2026-06-27

### Added
- `moodle_search_courses` gained a `scope` parameter (`catalogue` default |
  `enrolled`) and now treats `query` as optional: omitting it browses/lists all
  courses in the scope (catalogue browsing answers "how many courses are there"),
  while a keyword still searches. The `enrolled` scope is consolidated onto
  `moodle_my_courses` (single implementation for the user's own courses).
- `moodle_find_user` now finds users for callers with site-wide messaging rights
  (site admins / `moodle/site:sendmessage`) even without a shared course, so
  discovery matches what `moodle_send_message` can actually reach. Multi-word
  name fragments ("Paul Maier") are matched across first/last name. Non-messageable
  users stay filtered out, and non-privileged callers are unchanged.

### Hardened
- Rate limiting is now atomic: the per-bucket read-increment-write is serialised
  through the MUC lock, so limits hold even on cache stores that are not natively
  atomic (Redis still recommended for performance).
- `moodle_get_announcements` now only reads announcement forums whose course-module
  is visible to the user (hidden modules / availability restrictions are honoured)
  and scopes separate-groups announcements to the user's groups.

### Security
- Email visibility now actually works: `moodle_me` and `moodle_verify_user_context`
  read the correct user field `maildisplay` (previously the non-existent
  `emaildisplay`, so the guard never triggered and the address was returned
  regardless of the user's "hide my email" preference). Verified by PHPUnit.
- The MCP endpoint now restricts access to tokens that belong to a configured
  MCP external service (new admin setting **Restrict endpoint to MCP services**,
  enabled by default). Previously any valid Moodle web-service token could reach
  the AI tools; the MCP service list now governs endpoint access, not just token
  issuance. Can be disabled for transitional setups.
- `moodle_create_user` now accepts only **enabled** auth methods instead of any
  installed auth plugin, so callers cannot bypass the site's account policy.
- `moodle_search_content` now HTML-escapes result snippets, consistent with the
  other tool fields.
- `moodle_course_contents` no longer reveals hidden section names/summaries to
  users without `moodle/course:viewhiddenactivities`.
- `moodle_search_courses` and `moodle_calendar_upcoming` no longer surface
  internal exception messages to the client (logged via `debugging()` instead).

### Fixed
- `moodle_me` returned no email because of a misplaced assignment; the email is
  now resolved correctly (still gated by the user's `emaildisplay`).
- `moodle_forum_discussions` reported `discussion_count` from the current page
  only; it now reflects the full, visibility-filtered discussion count.
- Text truncation across several tools is now multibyte-safe (`core_text`),
  avoiding broken UTF-8 in excerpts/previews.
- Removed dead `request::from_raw_input()` / `request::is_raw_input_empty()`
  helpers that re-read `php://input`.

## [1.0.0] - 2026-06-12

### Added
- `moodle_search_content` — full-text search across the content the user can
  access via core global search (all engine access checks apply); graceful
  fallback to visible activity names/descriptions when global search is
  disabled, flagged via the `engine` response field.
- `moodle_my_submission_files` — the authenticated user's **own** latest
  submission for one assignment: attached files (name, size, mime type,
  download URL) and the plain text of online-text submissions. Strictly
  self-scoped by construction.
- Negative-case test sweep across the original twelve AI tools
  (visibility/enrolment/privacy boundaries), complementing the 0.9.0 tests.

### Security
- **`moodle_get_resource` no longer returns module content from courses the
  caller cannot access.** It previously gated only on `cm_info::uservisible`,
  which checks module visibility/availability and `mod/<x>:view` but **not**
  course enrolment or course visibility — and `mod/page:view` (etc.) is granted
  to the `user` archetype, so any authenticated user could read a page/book/URL
  in any course, including hidden ones. A `can_access_course()` gate is now
  applied first. The same gate was added defensively to
  `moodle_my_submission_files` and `moodle_forum_discussions` (both resolve a
  module by `cmid`). Found by the 1.0 negative-case test sweep.

### Changed
- Plugin maturity raised to **STABLE**.

## [0.9.0] - 2026-06-12

### Added
- **Three new AI tools** closing the learner-progress gaps for tutor agents:
  - `moodle_my_progress` — completion progress per enrolled course
    (percentage, completed/total tracked activities, course-completed flag),
    optionally per-activity completion states for a single course.
  - `moodle_quiz_info` — quizzes across enrolled courses with open/close
    windows, time limits, allowed attempts and grading method, plus the
    authenticated user's **own** attempt history and best grade for a single
    quiz (`cmid`). Never exposes other users' attempts.
  - `moodle_forum_discussions` — read course forums: visible forums and
    discussions per course or forum, and the posts of one discussion as plain
    text. All mod_forum visibility rules enforced through the forum API
    (group modes, Q&A first-post gating, timed posts, private replies).
    Read-only by design.

## [Unreleased]

### Changed

- Configuration uses the `report` page layout and the default 72rem shell
  width; help remains reading-width.

### Fixed
- Fatal parse error in `classes/local/server.php` (`$this->wsname` assignment) that
  prevented the server class from loading at all.
- Upgrade savepoint in `db/upgrade.php` used the wrong plugin name (`mcp` instead
  of `elediamcp`), which would have broken the upgrade path on existing sites.
- Capability language strings (`elediamcp:use`, `elediamcp:viewcaps`,
  `elediamcp:managetokens`) were keyed under the pre-rename `mcp:*` names and did
  not resolve in the role-definition UI.
- Stale unit-test assertion pinning the advertised server version to `0.5.0`.

### Changed
- Advertised MCP `serverInfo.version` aligned with the plugin release (`0.8.0`).
- Relocated the convenience HTTP client from `lib.php` to the autoloaded class
  `\webservice_elediamcp\client`, so `lib.php` now contains only Moodle callbacks.
- `lib.php` now declares the `MOODLE_INTERNAL` guard.

### Added
- Self-service token page now renders a ready-to-paste **Claude Desktop**
  (`mcp-remote`) configuration snippet, with the freshly created token pre-filled
  once at creation time and a generic placeholder example for existing tokens.
- GitLab CI/CD pipeline (`.gitlab-ci.yml`): PHPCS (Moodle standard), PHPStan,
  Semgrep, Trivy, PHPUnit (with plugin-only coverage), Behat, PHPDepend metrics
  and a consolidated summary report.
- Repository governance files: `CHANGELOG.md`, `LICENSE`, `SECURITY.md`,
  `CONTRIBUTING.md`, `.gitignore`.

## [0.8.0]

### Added
- Self-service token management UI (create / view metadata / revoke) and an
  internal PHP API (`\webservice_elediamcp\api`) for first-party plugins to
  provision and revoke user-scoped tokens, with component attribution.
- Companion `webservice_elediamcp_token` metadata table that survives revocation
  for a durable audit trail; full Privacy API provider.
- Curated AI-native tool catalogue (`moodle_me`, `moodle_verify_user_context`,
  `moodle_find_user`, `moodle_my_courses`, `moodle_search_courses`,
  `moodle_course_contents`, `moodle_get_resource`, `moodle_get_announcements`,
  `moodle_calendar_upcoming`, `moodle_my_assignments`, `moodle_my_grades`,
  `moodle_send_message`).
- Multi-version MCP protocol negotiation (`2025-11-25`, `2025-06-18`,
  `2025-03-26`), tool annotations, structured output, `tools/list` pagination.
- Security controls: Origin/CORS allow-list, per-token/per-IP rate limiting,
  request-size limits, emergency disable, optional (off-by-default) query-string
  token, audit events.
- OAuth Protected Resource discovery (`/.well-known/oauth-protected-resource`)
  and `WWW-Authenticate` hints on 401.

[Unreleased]: https://eledia.de
[0.8.0]: https://eledia.de

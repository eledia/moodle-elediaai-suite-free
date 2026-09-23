# Changelog

All notable changes to the **local_literag** plugin are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `tutor_chat` accepts the `moodle_tools_enabled` argument and honours it as a
  ban on the live Moodle tools for that turn. The site setting
  `enable_mcp_tools` says whether this installation offers the tools at all;
  the new argument says whether the surface that is asking may use them right
  now. Both must agree. Absent means the caller has no opinion, which is how
  every client before RAG server spec 0.28.0 asked, so nothing changes for
  them. A pending write action is gated the same way: it was previewed on a
  turn that still allowed the tools and outlives that turn by one, and carrying
  it out afterwards would do in Moodle exactly what the caller has since
  forbidden.
- `tutor_chat` accepts the `system_prompt_id` argument (`tutor`|`aichat`|`elli`)
  and composes a different opening instruction per surface, so a role-play
  scenario and a general assistant are no longer both answered as a tutor.
  Everything after that opening — answer mode, tools, grounding, language,
  safety — is unchanged and identical for all three, because those are this
  plugin's own promises and do not depend on who asked. An absent or
  unrecognised id is answered as a tutor and never refused, which is the same
  rule the RAG server specification puts on an external server.
### Behoben
- **Die Einstellungsseite meldete „Too much data passed as arguments to js_call_amd".** Der gerenderte Seitenkopf (~2 KB) reiste durch die JavaScript-Argumentliste; Moodle warnt dort ab 1024 Zeichen, und bei eingeschaltetem Entwickler-Debugging schlägt das bei jedem Aufruf der Seite als Fehler durch. Das Modul wird jetzt über ein Inline-`require()` aufgerufen, die Nutzlast reist also in einem `<script>` statt in der Argumentliste — dieselbe Lösung, die `blocks/elediaai_tutor/settings.php` bereits verwendet. Der `init()`-Vertrag bleibt unverändert
- Der Zweig lief unabhängig davon, ob die Suite-Grundlage installiert ist: Der Kopf wird hier bewusst „in both modes" gerendert, also auch über den eigenen Fallback. Der Fehler bestand damit seit jeher

## [0.6.7] – 2026-08-06
### Geändert
- CI-Matrix-Fix: `mod/elli` in eigene Gruppe `elli` ausgelagert, damit der RAG-Stack (`local/literag`, `local/ragingest`, `webservice/elediamcp`) wieder eine Forgejo-Zelle erzeugt; inkompatible Gruppen werden jetzt im Matrix-Log ausgewiesen statt still verworfen (task04)
- Neuer Nachtjob `crossdb` in `.forgejo/workflows/moodle-ci.yml`: PHPUnit-Suite gegen MariaDB 11.4 zusätzlich zu PostgreSQL 16 — 78 Tests / 278 Assertions grün auf beiden Datenbanken (task04)

## [0.6.6] – 2026-08-03
### Geändert
- Referenzen auf die umbenannten Suite-Komponenten nachgezogen (`local_lernhive_ai` → `local_elediaai_core` usw.); keine Verhaltensänderung (SUI-560)

## [0.6.4] - 2026-07-31

### Changed
- Stamped the shared `lh-core` shell with the Zone-A actionbar, visible keyboard
  focus rings and 44px icon-action hit areas. The Full Shell content-area rule
  now lets the outer shell modifier own the width cap.

## [0.6.2] - 2026-07-25

### Security
- Ingestion now requires the current tenant and site identity, verifies that
  each course module belongs to the claimed course, and accepts only the
  canonical local Moodle URL for that module. This prevents forged citation
  links and cross-course metadata.

### Tests
- Replaced synthetic ingestion metadata with real course-module fixtures and
  added missing-tenant, cross-course and external-URL rejection coverage.

## [0.6.1] - 2026-07-25

### Security
- Added atomic, tenant-scoped per-user limits for paid tutor turns: configurable
  minute bursts and a hard daily request ceiling, enforced after Moodle-token
  validation and before retrieval or LLM calls.

### Tests
- Added focused minute-window, daily-window and user-isolation coverage.

## [0.6.0] - 2026-07-23

### Added
- Generic confirmation payloads derived from MCP tool annotations, allowing the
  tutor UI to preview and explicitly confirm supported write tools.

## [0.5.3] - 2026-07-09

### Changed
- Declare Moodle 5.2 support after the Marketplace preflight PHPUnit run passed
  on Moodle 5.2.

## [0.5.2] - 2026-07-08

### Changed
- Release maturity is now `MATURITY_BETA`.
- The monorepo Forgejo CI matrix now contains a dedicated `local/literag` job
  with the required suite dependencies.

### Security
- RAG context blocks and elediamcp tool results are now explicitly marked as
  untrusted model input. Prompt instructions tell the model not to follow
  instructions embedded in retrieved course content or tool output.

### Tests
- Added assertions for prompt-context and tool-result delimiting.

## [0.5.1] - 2026-06-18

### Fixed
- **Answer-style modes (Hints only / Quiz me) now reliably change behavior.** The
  `answer_style` value the tutor block sends was already parsed, but in the system
  prompt it was a single weak line that the opening instruction and the grounding
  rules ("answer clearly", "ground your answer and cite the sources") overrode — so
  on weaker models hint/quiz looked ignored. The pedagogical mode is now a dominant,
  mode-aware directive the other rules defer to, with **strict** semantics: hint
  never reveals the final solution (even when asked), quiz always poses questions and
  waits for the learner's answers. Send/confirmation replies stay plain (no style).

## [0.5.0] - 2026-06-15

### Added
- **Send Moodle messages (opt-in, two-step confirm)**: when `enable_write_tools`
  is on (default **off**) the tutor may send a Moodle message on the learner's
  behalf via elediamcp's `moodle_send_message`, but only safely: a single chat
  turn can never send — the write tool is always forced to `confirm=false`, so it
  only ever returns a *preview* (resolved recipient + message text), which the
  tutor relays and asks the learner to confirm. The message is sent only after the
  learner's explicit affirmative on the following turn (new `confirmation::is_yes`,
  a conservative en/de yes-list), reusing the recipient id + text from the preview
  the learner saw. Sends honour the learner's own Moodle permissions and are
  audited server-side. New nullable `pendingaction` column on
  `local_literag_conversations`; setting `enable_write_tools`.

### Fixed
- **Live tool-calling now actually reaches the model**: a no-argument tool's empty
  `inputSchema` was serialised as a JSON array (`[]`) instead of an object, which
  OpenAI rejects with `invalid_function_parameters`. That 400'd the entire tools
  payload, so the agent silently degraded to RAG-only and *no* `moodle_*` tool was
  ever offered (the read-only tools added in 0.4.0 included). Tool schemas are now
  normalised to a valid JSON object (`{"type":"object","properties":{}}`).

## [0.4.0] - 2026-06-15

### Added
- **Live Moodle tools (agentic tool-calling)**: when grounded, the tutor can now
  call `webservice_elediamcp`'s read-only `moodle_*` tools as the learner (via the
  user-scoped `moodle_token`, spec Part C) to answer with real-time data —
  assignments, due dates, grades, calendar, progress, forum posts, etc. A new MCP
  client (`mcp\moodle_client`) drives elediamcp statelessly; an `agent` runs a
  bounded tool-calling loop (capped by `max_tool_iterations` and a wall-clock
  deadline) on top of OpenAI function calling. The conversation is bootstrapped
  with `moodle_verify_user_context`. Settings: `enable_mcp_tools` (default on),
  `max_tool_iterations`, `mcp_timeout`. Read-only tools only (never `moodle_send_message`);
  degrades gracefully to RAG-only when tools/endpoint are unavailable.

## [0.3.1] - 2026-06-15

### Fixed
- Source citations are now **deduplicated per document and numbered by source**:
  several retrieved passages from the same module collapse into one numbered
  source card, and the `[S#]` markers in the answer map 1:1 to those cards
  (previously every chunk produced its own card, so one document could appear
  multiple times with mismatched numbering).

## [0.3.0] - 2026-06-15

### Added
- **Structured sources on resumed conversations**: `tutor_get_history` now
  returns each assistant message's `sources` (`{title, url, snippet}`), so the
  tutor block renders the same citation cards on resume as for live answers
  (requires `block_elediaai_tutor` ≥ 0.14.0). New nullable `sourcesjson` column on
  `local_literag_messages`.

### Changed
- Dropped the markdown "Sources" footer that was baked into stored answers as a
  stopgap; sources now travel as structured data.

## [0.2.0] - 2026-06-15

### Added
- **PDF text extraction out of the box**: bundles the pure-PHP
  [`smalot/pdfparser`](https://github.com/smalot/pdfparser) library (LGPL-3.0,
  declared in `thirdpartylibs.xml`), so ingested PDF resources are chunked and
  searchable on every platform with no external binary and no install step.

### Changed
- The `pdftotext` path is now an **optional** higher-fidelity override: when a
  native Poppler `pdftotext` binary is configured it is used first, otherwise the
  bundled PHP parser handles extraction. A README install guide covers adding
  `poppler-utils` for sites that want the native path.

## [0.1.0] - 2026-06-15

### Added
- Initial release: a Moodle-local, embeddings-free RAG backend that drop-in
  replaces the external RAG service for `local_ragingest` (ingestion) and
  `block_elediaai_tutor` (tutor MCP), with no changes to either plugin.
- **Ingestion endpoint** (`ingest.php`) implementing the RAG ingestion API v1.2:
  `POST /documents/upsert` and `/documents/delete` authenticated by `X-API-Key`,
  with idempotent upserts and exact/prefix-scoped deletes (the `:` boundary
  prevents `cmid99` matching `cmid990`).
- **MCP / tutor endpoint** (`mcp.php`) speaking JSON-RPC 2.0 `tools/call`:
  `tutor_chat` (required) plus optional `tutor_get_history`,
  `tutor_delete_conversation`, `tutor_delete_user_data`,
  `tutor_set_memory_optin` and `tutor_recluster_questions`.
- **Embeddings-free retrieval**: database full-text search (PostgreSQL
  `to_tsvector`, MySQL/MariaDB `MATCH … AGAINST`, MSSQL `CONTAINS`) with a
  portable `LIKE` fallback, an optional LLM reranking pass, and strict per-user
  Moodle permission filtering (`get_fast_modinfo()` / `uservisible`).
- **In-process Moodle MCP token validation** against the core `external_tokens`
  table — no HTTP callback required.
- **OpenAI-compatible LLM client** (works against the OpenAI API or a LiteLLM
  proxy) that composes the final Markdown answer with citations.
- Conversations and messages, opt-in long-term memory, per-course topic-label
  registry, a GDPR privacy provider, a log/conversation pruning task, and a
  PHPUnit suite (chunking, prefix-delete, retrieval, permission filtering, token
  validation and an end-to-end `tutor_chat` test).
- GitLab CI pipeline (lint / SAST / dependency scan / test / report).

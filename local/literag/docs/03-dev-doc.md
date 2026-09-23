# eLeDia.ai LiteRAG — Dev Doc

> Wie es umgesetzt ist. Intent in `01-features.md`, Nutzerführung in
> `02-user-doc.md` (EN) / `02-user-doc.de.md` (DE).

## Architektur

```
local_ragingest ──POST /documents/upsert|delete (X-API-Key)──▶ ingest.php
                                                                   │
                                              local\document_store │ upsert/delete
                                                       │ chunker (deterministisch,
                                                       │  text|html|pdf; pdfparser
                                                       │  oder Poppler pdftotext)
                                                       ▼
                              local_literag_sources / local_literag_chunks
                              (FULLTEXT/GIN-Indizes via local\schema, Raw-SQL)

block_elediaai_tutor ──JSON-RPC 2.0 tools/call (Bearer optional)──▶ mcp.php
                                                                     │
                                                 mcp\dispatcher ─────┤ Tool-Name → Handler
                                                                     ▼
        mcp\tools\tutor_chat ── token_validator (moodle_token, in-process
             │                   gegen {external_tokens})
             │ query_normaliser ─▶ retriever (DB-FTS / LIKE-Fallback)
             │                        │ reranker (optional, LLM)
             │                        ▼
             │                permission_filter (uservisible — harte Grenze)
             │ prompt_builder (Grounding, answer_style, Persona, Sprache)
             │ agent ── mcp\moodle_client ──▶ webservice_elediamcp
             │            (Loopback $CFG->wwwroot, read-only; Schreiben nur
             │             Preview + confirmation::is_yes im Folge-Turn)
             ▼
        llm\client (OpenAI-kompatibel, http\curl_transport)
             │ Antwort + deduplizierte [S#]-Quellen
             ▼
   conversation_repository ─▶ local_literag_conversations/_messages
   memory_store (Opt-in) · topic_registry · Query-Log

   Weitere Tools: tutor_get_history · tutor_delete_conversation ·
   tutor_delete_user_data (user_eraser) · tutor_set_memory_optin ·
   tutor_recluster_questions

   settings.php (+ output\shell, amd settings_shell) · help.php (Handbuch)
   task\prune_logs (täglich 04:17) · privacy\provider (nutzt user_eraser)
```

## Datenmodell (`db/install.xml`)

- `local_literag_sources` — ein ingestiertes Dokument je `sourceid`
  (z. B. `tenant:course42:cmid99[:suffix]`): `tenant`, `courseid`, `cmid`,
  `contextid`, `moduleurl`, `sourcetitle`, `contenttype`, `contenthash`
  (sha1; unverändert → kein Re-Chunk), `parsestate` (ok|skipped|failed).
- `local_literag_chunks` — deterministische Text-Chunks fürs Keyword-Retrieval:
  `sourceid`, Scope-Spalten (tenant/courseid/contextid/cmid), `sourcetype`
  (text|html|pdf), `chunktext`, `chunkhash`, `sortorder`.
- `local_literag_conversations` — Tutor-Konversationen: `convkey` (öffentliche
  conversation_id, unique), `userid`, `courseid` (0 = global), `tenant`,
  `lastanswerstyle`, `pendingaction` (JSON einer bestätigungspflichtigen
  Schreibaktion, nullable; seit 0.5.0).
- `local_literag_messages` — Nachrichten: `conversationid` (FK), `userid`
  (denormalisiert für schnelles user-scoped Erase), `role`, `content`,
  `sourcesjson` (JSON `{title,url,snippet}`, nullable; seit 0.3.0), `topic`,
  `primarycmid`.
- `local_literag_query_log` — operatives Retrieval-Log: `querytext` (je nach
  `log_verbosity`), `numcandidates`, `numreturned`, `usedllm`, `reranked`,
  `latencyms`.
- `local_literag_memory` — Opt-in-Langzeit-Memory: `userid`, `tenant`, `mkey`,
  `mvalue`.
- `local_literag_topics` — kanonisches Topic-Label-Register je Kurs:
  `courseid`+`label` unique, `usecount`.

**Volltext-Indizes:** XMLDB kann FULLTEXT (MySQL/MariaDB) bzw. GIN (PostgreSQL)
nicht deklarieren; `local\schema::create_fulltext_indexes()` legt sie mit
dbfamily-geschütztem Raw-SQL aus `db/install.php` und `db/upgrade.php` an.
Ohne nativen Index greift der `LIKE`-Fallback des Retrievers.

## Klassen (`classes/`)

Pipeline (in `local\`):

- `document_store` — Upsert/Delete-Semantik der Ingestion-API v1.2:
  idempotenter Upsert keyed auf `source_id` (Re-Chunk = delete-then-insert),
  exakte und prefix-scoped Deletes.
- `chunker` — deterministische Extraktion + Chunking (Größe/Overlap aus
  Settings); PDF über gebündelten `smalot/pdfparser` oder konfiguriertes
  Poppler `pdftotext`.
- `query_normaliser` — Frage → Keyword-Terme (lowercase, Interpunktion raus,
  EN/DE-Stopwörter, dedupliziert).
- `retriever` — DB-FTS (pgsql/mysql/mssql) + `LIKE`-Fallback über die
  gespeicherten Chunks.
- `reranker` — optionales LLM-Reranking; jeder Fehler degradiert zur
  Eingabereihenfolge.
- `permission_filter` — Sicherheits-Invariante: Chunk eines für den Nutzer
  unsichtbaren Kursmoduls wird nie zurückgegeben (`get_fast_modinfo()` /
  `uservisible`).
- `prompt_builder` — OpenAI-Message-Liste je Tutor-Turn: Grounding-Kontrakt
  (`[S#]`-Zitate), `answer_style` explain|hint|quiz als dominante Direktive,
  Persona (nur Stimme), `user_lang`.
- `agent` — begrenzter Tool-Calling-Loop (`max_tool_iterations` +
  Wall-Clock-Deadline) über OpenAI Function Calling.

Infrastruktur (in `local\`):

- `config` — typisierte Accessoren für alle Settings inkl. Defaults;
  `is_disabled()` (Notaus `emergency_disable`).
- `tenant` — kanonische Tenant-Id aus `wwwroot`; muss byte-identisch zu
  `local_ragingest` bleiben.
- `token_validator` — validiert das user-scoped `moodle_token` in-process
  gegen `{external_tokens}` (provisioniert von
  `\webservice_elediamcp\api::create_token()`).
- `conversation_repository` — Konversationen/Nachrichten, immer owner-scoped.
- `memory_store` — Opt-in-Memory; jeder Read/Write gated auf `ltm_enabled`,
  Opt-out löscht den Bestand.
- `topic_registry` — klassifiziert in bestehende Kurs-Labels, mintet nur bei
  Bedarf neue.
- `user_eraser` — Single Source of Truth der Nutzer-Löschung (Tool + Privacy).
- `confirmation` — konservative en/de-Ja-Erkennung für pendente
  Schreibaktionen.
- `ingest_exception` — Fehler mit HTTP-Status für `ingest.php`.
- `http\transport` / `http\curl_transport` — HTTP-Abstraktion (Moodle-cURL)
  für ausgehende LLM-Calls.
- `llm\client` / `llm\llm_exception` — OpenAI-kompatibler Chat-Completions-
  Client (api.openai.com/v1 oder LiteLLM-Proxy), gepufferte Antwort.
- `rate_limiter` — atomare Per-Nutzer-/Per-Tenant-Kostenkontrolle für bezahlte
  Tutor-Chat-Aufrufe; Minute-Burst-Grenzen und tägliche Spend-Deckel via
  Cache-Locking serialisiert, konfigurierbar via `rate_limit_per_minute` und
  `rate_limit_per_day`.
- `schema` — Verwaltung von Volltext-Indizes (FULLTEXT/GIN); da XMLDB keine
  nativen Full-Text-Indizes deklariert, legt die Klasse sie mit geschütztem
  Raw-SQL für MySQL/MariaDB, PostgreSQL und MSSQL an; idempotent und defensiv
  (Fallback auf LIKE-Suche beim Fehlen des Index).

MCP (in `local\mcp\`):

- `dispatcher` — routet JSON-RPC `tools/call` auf den Handler; Tool-Namen sind
  admin-konfigurierbar und spiegeln die Block-Settings.
- `result` — JSON-RPC-Envelopes (Erfolg/Fehler).
- `tool` / `tool_exception` — Handler-Kontrakt.
- `moodle_client` — stateless MCP-Client für `webservice_elediamcp`
  (Bearer = `moodle_token`, Ziel immer lokales `$CFG->wwwroot`).
- `tools\tutor_chat`, `tools\tutor_get_history`,
  `tools\tutor_delete_conversation`, `tools\tutor_delete_user_data`,
  `tools\tutor_set_memory_optin`, `tools\tutor_recluster_questions`.

Sonstige:

- `output\shell` — LernHive-Plugin-Shell-Adapter (`is_available()`,
  `require_css()`, `context()`); Fallback auf Moodle-Standard.
- `task\prune_logs` — Scheduled Task (täglich 04:17, `db/tasks.php`):
  Query-Log + abgelaufene Konversationen gemäß Retention.
- `privacy\provider` — Privacy-API (Export/Löschung; löscht über
  `user_eraser`).

## Endpunkte und weitere Dateien

- `ingest.php` — Ingestion (siehe feat01); Routing über PATH_INFO
  (`$CFG->slasharguments` nötig) oder `?action=`; `WS_SERVER`,
  `NO_DEBUG_DISPLAY`, kein Login/CSRF.
- `mcp.php` — MCP über Streamable HTTP: ein JSON-RPC 2.0 `tools/call` pro
  Request, Antwort als `application/json` mit Header
  `MCP-Protocol-Version: 2025-06-18`; optionaler Bearer-Check
  (`transport_auth_token`, `hash_equals`); 503 bei `emergency_disable`.
- `help.php` — Handbuch-Seite (rendert `docs/02-user-doc(.de).md` je nach
  Sprache), `require_capability('local/literag:manage')`.
- `settings.php` — Admin-Settings in sechs Sektionen; injiziert bei
  verfügbarer Shell Header + Sektionkarten via
  `local_literag/settings_shell` (AMD).
- `amd/src/settings_shell.js` — Shell-UX der Settings-Seite (Build unter
  `amd/build/`).
- `bin/release.sh` — Release-ZIP-Paketierung (Komponente/Version aus
  `version.php`).
- `styles.css` enthält neben den Plugin-Regeln den aus `design/lh-core.css`
  gestempelten Suite-Shell-Block; die Shell nutzt auf Reading-Seiten einen
  konstanten Rahmen und begrenzt nur den Content-Bereich. `db/access.php` (nur
  `local/literag:manage`,
  CONTEXT_SYSTEM, Manager), `db/install.php` / `db/upgrade.php`
  (Volltext-Indizes; Savepoints 2026061700 `sourcesjson`, 2026061900
  `pendingaction`, 2026061901), `thirdpartylibs.xml`,
  `vendor/smalot/pdfparser` (2.12.5, LGPL-3.0).

## Sicherheit / Grenzen

- Bezahlte `tutor_chat`-Aufrufe passieren nach Token-/Eingabevalidierung und
  vor Retrieval oder Provider-Arbeit `rate_limiter::check()`. MUC-Zähler sind
  per Tenant und Nutzer getrennt, per Cache-Lock serialisiert und über
  `rate_limit_per_minute` sowie `rate_limit_per_day` konfigurierbar.
- Machine-to-Machine-Endpunkte ohne Session/CSRF; Authentifizierung:
  `X-API-Key` (Ingestion), optional Bearer (`transport_auth_token`, MCP),
  Nutzeridentität via in-process validiertem `moodle_token` (adr04).
- Harte Autorisierungsgrenze ist `permission_filter` (Moodle-Sichtbarkeit);
  Konversationen/Memory/Verlauf sind strikt owner-scoped.
- Live-Tools nur gegen das lokale `$CFG->wwwroot` (SSRF-/Exfiltrations-
  Schutz); Schreib-Tools opt-in mit Zwei-Schritt-Bestätigung (adr07).
- LLM-Keys, Transport-Tokens und Moodle-Tokens werden nicht geloggt;
  `log_verbosity` steuert das Query-Log; Notaus `emergency_disable`.
- `llm_allow_private` (Default aus) gate't private/interne LLM-Endpunkte.

## Verwendete Core-/Suite-APIs

- Moodle-DB-Schicht (`$DB`, XMLDB) + dbfamily-Raw-SQL für Volltext-Indizes;
  `get_fast_modinfo()` / `cm_info::uservisible`; `moodle_url`; Moodle-cURL.
- Core-Tabelle `{external_tokens}` (Token-Validierung in-process).
- Privacy API (`core_privacy`), Task API (`core\task\scheduled_task`),
  Admin-Settings-API, AMD/JS (`js_call_amd`).
- Suite: `webservice_elediamcp` (Live-Tools, Token-Provisionierung),
  `local_lernhive` Plugin-Shell (optional, Template
  `local_lernhive/plugin_shell_header`); Gegenstellen `local_ragingest`
  und `block_elediaai_tutor` via HTTP.
- Gebündelt: `smalot/pdfparser` 2.12.5 (LGPL-3.0, `thirdpartylibs.xml`).

## Reifegrad / Release-Gate

Der Schritt aus BETA heraus ist nicht offen, sondern an prüfbare Schwellen
gebunden. Die vollständige Checkliste (Gate 1 = interner Stable-Release, Gate 2
= optionale moodle.org-Einreichung) steht in `05-quality.md`
(„Reifegrad-/Release-Kriterien", q05). Kurz: PHPUnit + Cross-DB grün, phpcs
sauber, `moodle-plugin-ci`-Precheck grün, Privacy-Provider vollständig,
Upgrade-Pfad getestet, Behat-Entscheidung getroffen, Doku-Stand konsistent,
End-to-End-Nachweis — erst dann der `MATURITY_STABLE`-Bump auf SemVer `>= 1.0.0`.
Der tatsächliche BETA→stable-Sprung und eine moodle.org-Einreichung bleiben
Produktentscheidungen (Johannes).

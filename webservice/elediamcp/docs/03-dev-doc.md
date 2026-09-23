# Moodle MCP Web Service — Dev Doc

> Wie es umgesetzt ist. Intent in `01-features.md`, Nutzerführung in
> `02-user-doc.md` (EN) / `02-user-doc.de.md` (DE).

## Architektur

### Shell-Breite

Die Konfigurationsseite verwendet das Moodle-Pagelayout `report` und den
Default-Shell-Modifier mit 72rem. Die Help-Seite uebergibt explizit den
Reading-Modifier mit 58rem.

```
MCP-Client (Claude Desktop via mcp-remote, Cursor, Langflow, Tutor-Agent)
    │  Streamable HTTP · JSON-RPC 2.0 · Authorization: Bearer <token>
    ▼
server.php ── webservice_protocol_is_enabled('elediamcp')
    │
    ▼
local\server (extends webservice_base_server)
    │  set_headers (CORS) → emergency_disable? → Origin-Check → parse_request
    │  → Size-Limit → MCP-Protocol-Version-Header → Rate-Limit
    │  → authenticate_user (core external_tokens + enforce_mcp_service)
    │  → require_mcp_capability (webservice/elediamcp:use)
    │
    ├─ initialize / ping / tools/list / prompts/* / notifications ── local\protocol
    │       (Versionsverhandlung 2025-11-25 / 2025-06-18 / 2025-03-26)
    │
    └─ tools/call ── local\tool_provider
           │
           ├─ AI-native Tool?  ── local\ai\registry (statisch, class_exists-
           │      │                Guards für h5pauthor/questiongen/selfstudy)
           │      ▼
           │  local\ai\tools\* (implements ai_tool)
           │      execute($arguments, $USER) — Capability-Checks im Tool,
           │      tool_exception → isError:true-Result
           │      Gate: premium::has_mcp_tools() ∨ FREE_AI_TOOLS
           │
           └─ Raw External Function (nur premium ∧ expose_raw_functions,
                  service-gebunden) ── parent::run() → core_external
    │
    ▼
Events: tool_invoked / write_performed (+ context_verified aus Tools)

Token-Lebenszyklus:
  token/index.php (Self-Service-UI)  ┐
  configuration.php (Admin-Shell)    ├─▶ local\token_manager ─▶ core external_tokens
  \webservice_elediamcp\api (intern) ┘         └─▶ {webservice_elediamcp_token}
                                                    (Metadaten, überlebt Revocation)
  task\prune_revoked_tokens (nächtlich) ─ löscht widerrufene Metadaten > Retention
```

`local\mcp\prompt` definiert den Prompt-Vertrag; `prompt_registry` aggregiert
den Built-in-Katalog und `<component>_elediamcp_prompts()`-Beiträge. Die Registry
filtert Prompts anhand der tatsächlich für das Bearer-Token verfügbaren Toolnamen
und verwendet denselben opaken Cursor wie `tools/list`. `prompts/get` validiert
Pflicht- und unbekannte Argumente vor `render()` und meldet Protokollfehler als
JSON-RPC `-32602` bzw. `-32603`.

## Datenmodell (`db/install.xml`)

Drei Tabellen. `webservice_elediamcp_token` (Lifecycle-/Audit-Metadaten, das
Secret liegt nie hier):

- `externaltokenid` — Referenz auf `external_tokens.id`, solange der Token
  lebt; wird bei Revocation genullt (Core-Token wird gelöscht).
- `tokenhash` — SHA-256 des Tokenwerts, nur zur Korrelation (Index).
- `userid`, `externalserviceid` — Bindung an Nutzer **und** MCP-Service.
- `name` (Label), `creatorid`, `component` — Frankenstyle-Komponente bei
  programmatisch provisionierten Tokens, `null` bei Self-Service.
- `validuntil`, `timecreated`, `lastaccess` — Last-Access wird bei Revocation
  vom Core-Token gesnapshottet.
- `revoked`, `timerevoked`, `revokedby` — Revocations-Zustand.
- Indizes: `tokenhash`, `externaltokenid`, `userid,revoked`.

`webservice_elediamcp_oauth_client` (OAuth-Client-Registrierungen, seit 1.6.0):
`clientid` (öffentlich, unique), `clientname`, `redirecturis` (newline-Liste,
exakt registriert), `granttypes`/`responsetypes`/`tokenendpointauthmethod`
(immer `authorization_code`/`code`/`none` — öffentliche PKCE-Clients, kein
Secret), `scope`, `timecreated`/`timemodified`. Kein Personenbezug.

`webservice_elediamcp_oauth_code` (Autorisierungscodes, seit 1.6.0):
`codehash` (SHA-256 des Codes, unique — Klartext wird nie gespeichert),
`clientid`, `userid` (autorisierende Person), `redirecturi` (exakt),
`codechallenge`+`codechallengemethod` (`S256`), `scope`, `expires`,
`timecreated`. Single-use (bei Tausch gelöscht), kurzlebig (Default 300 s),
Index auf `codehash` und `expires` (GC).

Authentifizierung selbst läuft ausschließlich über Moodles `external_tokens`.
Kein `db/services.php` — das Plugin deklariert keine External Functions.

## Klassen (`classes/`)

- `local/server.php` — MCP-Server (`webservice_base_server`); Lifecycle
  (initialize/ping/tools-list/tools-call), Header-/Origin-/Size-/Rate-Checks,
  `authenticate_user()`-Override mit MCP-Service-Enforcement,
  `require_mcp_capability()`, `dispatch_ai_tool()`, `emit_tool_result()`
  (isError-Semantik, Legacy-Envelope), Parameter-Koerzierung für
  Raw-Funktionen, Audit (`record_tool_invocation`). Die advertised
  `serverInfo.version` wird über `server::get_server_version()` zur Laufzeit aus
  `$plugin->release` (version.php) abgeleitet — Single Source of Truth, kann
  nicht mehr veralten (siehe `04-tasks.md` q01).
- `local/protocol.php` — Versionskatalog + Negotiation; `uses_result_envelope()`
  (nur `2025-03-26`), `supports_annotations()`/`supports_tool_title()`.
- `local/request.php` — JSON-RPC-2.0-Parsing/-Validierung (`from_string`).
- `local/security.php` — Config-Zugriff mit Defaults, Origin-/CORS-Auflösung,
  Rate-Limit (MUC `rate_limit`-Cache, Read-Increment-Write unter MUC-Lock),
  Size-/Pagesize-Getter, `expose_raw_functions()`, `enforce_mcp_service()`
  (Default 1), `is_emergency_disabled()`, `allow_token_in_query()` (Default 0).
- `local/tool_provider.php` — aggregiert AI-Tools + Raw-Funktionen;
  `FREE_AI_TOOLS` (15 Namen), Premium-Gate je Tool, Pagination
  (base64-Cursor), JSON-Schema-Generierung aus `external_description`,
  Annotation-Inferenz aus Funktionsnamen.
- `local/premium.php` — `has_mcp_tools()` über
  `\local_elediaai_tutor_premium\feature::has_feature('mcp_tools')`
  (weiche Kopplung per `class_exists`).
- `local/token_manager.php` — Token-Lebenszyklus (create/revoke/list/prune,
  Status-Berechnung, Default-Service `elediamcp` via
  `ensure_default_service_configured()`); delegiert die Token-Erzeugung an
  `core_external\util::generate_token()` (erzwingt `requiredcapability`).
- `api.php` — interne PHP-Fassade für First-Party-Plugins;
  `validate_component()` als Trust-Boundary (Komponente muss installiert
  sein), komponenten-scopte Revocation.
- `client.php` — HTTP-Convenience-Client (JSON-RPC-Requests an den Endpoint).
- `local/ai/ai_tool.php` — Interface (name, title, description, input_schema,
  output_schema, annotations, execute).
- `local/ai/registry.php` — statische Tool-Liste: 31 feste Tools + bedingt
  `moodle_generate_h5p`, `moodle_generate_questions` und die 4
  `moodle_selfstudy_*`-Tools (+ `selfstudy_helper` als Nicht-Tool-Helfer).
- `local/ai/tool_exception.php` — Business-Fehler → `isError:true`.
- `local/ai/tools/*` — 37 Tool-Klassen (Read-, Lehrer-, Write-,
  Generierungs-Tools; Write-Tools mit Preview/Confirm-Flow).
- `event/` — `tool_invoked`, `write_performed`, `context_verified`,
  `token_created`, `token_revoked`.
- `form/configuration_form.php`, `form/create_token_form.php` — Admin-Config
  bzw. Token-Erstellung (Post/Redirect/Get).
- `output/shell.php` — gemeinsame Plugin-Shell (Konfigurations-/Token-Seite,
  Help-Aktion → `help.php`).
- `task/prune_revoked_tokens.php` — nächtlicher Scheduled Task (04:xx),
  Retention aus `token_retention_days` (Default 30).
- `privacy/provider.php` — Export/Erasure der Token-Metadaten inkl. Detach
  gelöschter Ersteller/Widerrufer.

### Design-Entscheidung: advertised Server-Version an `$plugin->release` gekoppelt (q01)

- **Warum Kopplung statt manuellem Mitziehen:** Die advertised `serverInfo.version`
  war früher eine handgepflegte Konstante `SERVER_VERSION`. Diese ist zweimal
  unbemerkt veraltet (`1.1.0` fixiert, dann `1.5.1` bei `release 1.5.2`) — ein
  zweiter Wert neben `$plugin->release`, den man beim Release-Bump vergisst. Genau
  darum kam q01 wiederholt zurück. Deshalb gibt es jetzt **nur noch eine Quelle**:
  `server::get_server_version()` liest `$plugin->release` aus `version.php` zur
  Laufzeit (einmal statisch gecacht). Ein Release-Bump in `version.php` ist damit
  ausreichend; es gibt keine Konstante mehr, die man synchron halten müsste.
- **Was manuell bleibt:** Nur das README-Maturity-Badge ist inhärent statisch
  (Shields-URL) und wird beim Release mitgezogen — siehe `CONTRIBUTING.md` →
  Versioning. Das ist bewusst außerhalb des Codepfades und daher unkritisch.
- **Test bewusst driftfest, keine Wartungsfalle:** `server_test.php`
  (`test_server_constants`) hardcodet **keine** Versionsnummer mehr (das war die
  frühere Falle). Es liest `$plugin->release` dynamisch aus `version.php` und
  prüft `assertSame($plugin->release, get_server_version())` plus SemVer-Form. Der
  Test bricht also nur, wenn Code und `version.php` real auseinanderlaufen, und
  muss bei einem Release-Bump nicht angefasst werden. Diese Bauart bitte
  beibehalten (nicht in eine feste Zeichenkette „vereinfachen").

## Tools aus anderen Plugins beisteuern (Provider-Hook)

Seit 1.5.0 können andere Plugins eigene MCP-Tools registrieren, ohne die
Registry anzufassen ("shared verbs", LernHive ADR-P15 Stufe 4):

1. In `lib.php` des Plugins die Funktion `<component>_elediamcp_tools()`
   implementieren, die ein Array von Klassennamen zurückgibt. Guard mit
   `interface_exists('\webservice_elediamcp\local\ai\ai_tool')`, damit das
   Plugin ohne installiertes elediamcp keine harte Abhängigkeit hat.
2. Jede Klasse implementiert `webservice_elediamcp\local\ai\ai_tool`
   (statische Methoden `name/title/description/input_schema/output_schema/
   annotations/execute`). Capability-Checks gehören IN `execute()` und laufen
   im Kontext des authentifizierten Users; Business-Fehler als
   `tool_exception` werfen.
3. Die Annotationen steuern Audit und UI: `destructiveHint`/`readOnlyHint`
   entscheiden über das `write_performed`-Event und darüber, ob Clients einen
   Confirm-Schritt erzwingen sollen. Write-Tools folgen dem Zwei-Schritt-
   Muster (`confirm=false` → Preview, `confirm=true` → Ausführung).

Regeln: Bei Namenskollision gewinnt das eingebaute Tool; ungültige Einträge
(Klasse fehlt, Interface nicht implementiert, Name nicht
`/^[a-z][a-z0-9_]*$/`) werden mit Developer-Debugging übersprungen.
Contributed Tools unterliegen nicht dem Premium-Gating — die Site hat sie
selbst installiert. Referenzimplementierung:
`local_lernhive_seminarmanagement` (lernhive-Repo, `classes/mcp/`).

## Weitere Einstiegspunkte

- `server.php` — Endpoint (`WS_SERVER`, `NO_DEBUG_DISPLAY`, Protokoll-Check).
- `configuration.php` — Admin-Seite (Capability `moodle/site:config`):
  MCP-Services, Origins, Token-/Limits-Settings, Free/Premium-Anzeige,
  Token-Verwaltung; `settings.php` leitet die Standard-Settings-Sektion
  hierher um.
- `token/index.php` — Self-Service-Token-UI (Preferences-Eintrag über
  `webservice_elediamcp_extend_navigation_user_settings()` in `lib.php`).
- `help.php` — plugin-eigene Hilfeseite (rendert `docs/02-user-doc*.md`).
- `.well-known/oauth-protected-resource.php` — OAuth-Protected-Resource-Metadaten
  (RFC 9728); listet den Authorization-Server, wenn `oauth_enabled` an ist.
- `.well-known/oauth-authorization-server.php` — Authorization-Server-Metadaten
  (RFC 8414); 404 wenn `oauth_enabled` aus ist.
- `oauth/authorize.php`, `oauth/token.php`, `oauth/register.php` — OAuth-2.1-
  Endpoints (Authorization + Consent, Token-Tausch, Dynamic Client Registration).
- `locallib.php` — `webservice_elediamcp_test_client` für Moodles
  WS-Test-Client-Interface.

## OAuth 2.1 Authorization Code + PKCE (seit 1.6.0)

Klassen unter `classes/local/oauth/`:

- `service.php` — die gesamte, transport-agnostische Flow-Logik: Metadaten-Bau,
  `register_client()` (RFC 7591), `validate_authorization_request()` (trennt
  nicht-redirectbare Pre-Redirect-Fehler von redirectbaren Protokollfehlern),
  `issue_code()`, `exchange_code()`, PKCE-`verify_pkce()` (S256, `hash_equals`),
  Redirect-URI-Validierung, `resolve_service_id()`. Reine, statische Methoden →
  ohne HTTP unit-testbar (`tests/oauth_service_test.php`).
- `store.php` — DB-Zugriff für Clients und Codes (Codes nur als SHA-256-Hash,
  `gc_expired_codes()`).
- `oauth_exception.php` — trägt RFC-Error-Code, sichere Beschreibung,
  HTTP-Status und `redirectable`-Flag; die Endpoints rendern daraus
  RFC-konforme Fehler.

Ablauf:

```
Client ─ GET /.well-known/oauth-protected-resource ─▶ authorization_servers:[issuer]
       ─ GET /.well-known/oauth-authorization-server ─▶ authorize/token/register-URLs
       ─ POST oauth/register.php (DCR) ─▶ client_id (public, PKCE-only)
       ─ GET  oauth/authorize.php ── require_login → Consent (sesskey) ──▶ code (single-use)
       ─ POST oauth/token.php  (code + code_verifier) ─▶ Bearer = MCP-Token
```

Sicherheitseigenschaften: PKCE Pflicht (nur S256), Redirect-URIs exakt
vorregistriert, Codes single-use (bei Tausch gelöscht — verbrennt auch bei
falschem Verifier) und kurzlebig, Token-Ausstellung über `token_manager`
(erzwingt die Service-`requiredcapability` gegen die autorisierende Person).
Der Token-Endpoint konsumiert den Code in einer DB-Transaktion (Read+Delete),
validiert erst danach die In-Memory-Kopie und wirft `oauth_exception` außerhalb
der Transaktion. Der ausgestellte Token hat `component=null` und ist damit im
Self-Service (*Preferences → MCP tokens*) sichtbar und widerrufbar.

Opt-in: `oauth_enabled` (Default 0). Aus → Discovery ohne Authorization-Server,
Endpoints 404, bestehende Bearer-Tokens unberührt. `oauth_allow_dynamic_
registration` (Default 1), `oauth_code_ttl` (30–600 s, Default 300),
`oauth_token_ttl` (0 = kein Ablauf). Der Scheduled Task `prune_revoked_tokens`
räumt zusätzlich abgelaufene Codes ab. Privacy-Provider deckt `oauth_code`
(Personenbezug via `userid`) ab; `oauth_client` ist ohne Personenbezug.

Deployment-Hinweis: Die extensionslosen `/.well-known/*`-URLs setzen — wie die
bereits bestehende Protected-Resource-Discovery — ein Web-Server-Rewrite auf die
`*.php`-Dateien voraus. Die funktionalen Endpoints werden mit `.php` beworben und
brauchen kein Rewrite.

## Konfiguration (Component `webservice_elediamcp`)

`services` (MCP-Service-IDs, CSV), `allowed_origins`, `allow_token_in_query`
(0), `enforce_mcp_service` (1), `expose_raw_functions` (0),
`rate_limit_per_minute` (60), `rate_limit_per_hour` (600), `max_request_size`
(1 MiB), `tools_page_size` (50), `token_retention_days` (30),
`emergency_disable` (0), `oauth_enabled` (0),
`oauth_allow_dynamic_registration` (1), `oauth_code_ttl` (300),
`oauth_token_ttl` (0). Gepflegt über `configuration.php`, gelesen über
`local\security`.

## Sicherheit / Grenzen

- **Jede** Operation läuft als der authentifizierte Token-Nutzer; Tools
  prüfen Capabilities/Enrolment/Sichtbarkeit selbst (Security-first-Regel in
  CONTRIBUTING.md). Details und Meldeweg: `SECURITY.md`.
- Endpoint-Gates in Reihenfolge: Emergency-Disable → Origin → Size →
  Protokollversion → Rate-Limit → Token-Auth (+ MCP-Service-Bindung) →
  `webservice/elediamcp:use`.
- Capabilities (`db/access.php`): `:use` (read, user-Archetyp), `:viewcaps`
  (read, manager — gated `include_capabilities` von
  `moodle_verify_user_context`), `:managetokens` (write, user-Archetyp).
- Secrets: nie im Klartext gespeichert (SHA-256-Hash), nie in Logs/Events;
  Fehlerantworten enthalten keine internen Exception-Details.
- Rate-Limit-Zähler in der MUC-Application-Cache; für harte Limits im Betrieb
  Redis o. ä. als Store empfohlen (README-Hinweis).

## Verwendete Core-/Suite-APIs

- `webservice_base_server` / `webservice/lib.php` (Protokoll-Plugin-Rahmen)
- `core_external` (`external_api::external_function_info`,
  `clean_returnvalue`, `util::generate_token`, `external_description`-Typen
  für die JSON-Schema-Generierung)
- Cache-API (MUC, `db/caches.php`: `rate_limit`, `verify_context` TTL 30 s,
  `responses` TTL 60 s), Events-API, Scheduled Tasks, Privacy-API,
  Navigations-Callback, `core_text` (multibyte-sichere Truncation)
- Suite (optional, per `class_exists`): `local_elediaai_tutor_premium\feature`
  (Premium-Gate), `local_elediaai_h5pauthor\local\authoring_service`,
  `local_elediaai_questiongen\local\generator`,
  `local_elediaai_selfstudy\local\quiz_service`

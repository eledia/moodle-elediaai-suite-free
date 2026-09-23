# Moodle MCP Web Service — Tasks

> Arbeitslog. Offene Fragen als `qXX`, Aufgaben als `taskXX`. Neueste oben.

## Offen

### task03 — Doku nachziehen: Helfer-Klasse `grading_guard`

- **Herkunft:** DevFlow-Sync 2026-07-31.
- **Befund:** `classes/local/ai/tools/grading_guard.php` (`final class
  grading_guard`) erzwingt die Teilnehmer-/Kurs-Grenzen für die Grading-Loop-
  Tools, ist aber weder in `01-features.md` noch in `03-dev-doc.md` namentlich
  aufgeführt (03-dev-doc nennt nur allgemein „Write-Tools mit Preview/Confirm-
  Flow").
- **To-do:** Die Klasse im Klassen-/Architektur-Abschnitt von `03-dev-doc.md`
  als geteilten Guard für die Grading-Tools ergänzen (nur Doku, kein Code).

## Offene Fragen (qXX)

### q02 — Monorepo-CI: Plugin nicht im Forgejo-Katalog

Der Plugin-Katalog in `.forgejo/workflows/moodle-ci.yml` (Monorepo-Root)
enthält `webservice/elediamcp` nicht; das Plugin bringt stattdessen eigenes CI
mit (`.github/workflows/moodle-ci.yml` und `.gitlab-ci.yml` im vendored Repo).
Ist das bewusst so (eigenes Repo = eigenes CI), oder soll das Plugin in den
Monorepo-Katalog aufgenommen werden?

## Erledigt

### task04 — OAuth 2.1 Authorization Code + PKCE umgesetzt (1.6.0)

- **Datum:** 2026-08-05 (SUI-621, Folge aus q04/SUI-45).
- **Umfang:** In-Moodle-OAuth-2.1-Autorisierungsserver (opt-in `oauth_enabled`,
  Default aus). Authorization-Server-Metadaten (RFC 8414), Dynamic Client
  Registration (RFC 7591), Authorization-Endpoint mit Login + Consent-Screen,
  Token-Endpoint (Code + PKCE-Verifier → MCP-Token via `token_manager`). Zwei
  neue Tabellen (`oauth_client`, `oauth_code`), Endpoints unter `oauth/` und
  `.well-known/oauth-authorization-server.php`, Settings, Privacy, Task-GC,
  EN/DE-Strings, 23 PHPUnit-Tests (`tests/oauth_service_test.php`).
- **Autonome Entscheidungen (im Freigabe-Kommentar dokumentiert):**
  - **Opt-in, Default aus:** Sicherste Migration; bestehende Bearer-Tokens und
    Installationen bleiben unberührt, bis ein Admin bewusst aktiviert.
  - **Öffentliche PKCE-only-Clients, kein Client-Secret;** nur `S256` (kein
    `plain`); Redirect-URIs exakt vorregistriert.
  - **Kein Refresh-Token:** Es werden langlebige Moodle-Tokens ausgegeben
    (`oauth_token_ttl` Default 0). Scopes advisory — die effektive Autorität ist
    das Moodle-Rechtemodell des Nutzers.
  - **DCR standardmäßig an** (`oauth_allow_dynamic_registration`), abschaltbar,
    für Zero-Config-Clients wie Claude Desktop.
  - **Token `component=null`** → im Self-Service sichtbar/widerrufbar.
- **Offener Deployment-Punkt:** extensionslose `/.well-known/*`-URLs brauchen ein
  Web-Server-Rewrite auf die `*.php`-Dateien (gilt bereits für die bestehende
  Protected-Resource-Discovery; nicht neu durch diese Aufgabe).
- **Irreversible Klasse:** DB-Schema + Security → vor Push Freigabe angefordert
  (Workspace-Policy v2).

### q04 — resources/prompts und OAuth 2.1 → entschieden

- **Datum:** 2026-08-04 (SUI-45).
- **Entscheidung (Johannes):** OAuth 2.1 mit Authorization Code + PKCE wird als
  nächstes Feature-Thema priorisiert; grober Horizont ist das nächste
  Minor-Release. Der Flow ist der relevante Client-Blocker, weil bisher pro
  Nutzer manuell erzeugte Moodle-Tokens verwendet werden.
- **Zurückgestellt:** `resources/list` und `prompts/list` bleiben mit leeren
  Listen („reserved for upcoming releases") bewusst unverändert, bis ein
  konkreter Use-Case vorliegt. Ohne Inhalte entsteht daraus kein Mehrwert.
- **Folge-Tasks:** OAuth-Flow als Umsetzungs-Task; `resources`/`prompts` als
  Backlog-Task für die spätere Use-Case-Klärung.

### q01 — Advertised Server-Version ist stale → behoben, an `$plugin->release` gekoppelt

- **Datum:** 2026-08-04 (SUI-43); Vorlauf: manuelle Angleichungen 1.4.1/1.5.1.
- **Befund:** Die advertised `serverInfo.version` war eine handgepflegte
  Konstante `SERVER_VERSION` und ist deshalb zweimal veraltet: erst bei `1.1.0`
  hängengeblieben, dann wieder hinterher (`1.5.1` bei `release 1.5.2`). Auch das
  README-Maturity-Badge lief mit (`v1.4.2`).
- **Entscheidung:** Kopplung statt manuellem Mitziehen. `SERVER_VERSION` wurde
  entfernt; `server::get_server_version()` liest `$plugin->release` zur Laufzeit
  aus `version.php` (Single Source of Truth). `serverInfo`/`initialize` und die
  GET-Server-Info nutzen diese Methode. Eine PHPUnit-Assertion pinnt die
  advertised Version an `$plugin->release`, sodass künftige Drift auffällt.
- **Rest:** Das README-Badge ist inhärent statisch und wird beim Release-Bump
  mitgezogen (jetzt `v1.5.3`, vgl. `CONTRIBUTING.md` → Versioning).

### q03 — Deutsche Sprachdatei? → ja, im Repo

- **Datum:** 2026-07-26
- **Entscheidung (Johannes):** Jedes Plugin führt eine deutsche Sprachdatei im
  Repository, unabhängig davon, ob es im Moodle-Plugins-Verzeichnis
  veröffentlicht ist und ob AMOS eine Übersetzung anbietet. Die Repo-Fassung
  ist verbindlich.
- **Umsetzung:** `lang/de/webservice_elediamcp.php` angelegt, alle Keys aus
  `lang/en` übersetzt (Token-Seite, Konfigurationsseite, Events,
  Fehlermeldungen, Admin-Einstellungen, privacy:metadata). Terminologie nach
  `eLeDia.OS/DevFlow/skills/moodle-glossar/GLOSSAR.md` und der bestehenden
  deutschen Benutzer-Doku (`docs/02-user-doc.de.md`).

### q02 — Monorepo-CI: Plugin nicht im Forgejo-Katalog → im Katalog (rag-Gruppe)

- **Datum:** 2026-08-04
- **Prämisse war stale:** Die ursprüngliche Frage bezog sich auf
  `.forgejo/workflows/moodle-ci.yml`, aber der Plugin-Katalog liegt tatsächlich
  in `scripts/ci-matrix.php` (Monorepo-Root).
- **Verifikation:** `webservice/elediamcp` ist bereits im Katalog `scripts/ci-matrix.php`
  enthalten — in der Gruppe `'rag'` (Zeilen 102–107), zusammen mit `local/literag`,
  `local/ragingest` und `mod/elli`. Dependency-Eintrag vorhanden (Zeile 147).
- **Entscheidung:** Das Plugin wird durch das Monorepo-CI abgedeckt. Das
  plugin-eigene CI (`.github/workflows/moodle-ci.yml` und `.gitlab-ci.yml` im
  vendored Repo) bleibt parallel bestehen — zwei unabhängige Gates (Forgejo +
  GitHub Actions/GitLab).

### task02 — DevFlow-Docs 00–05 nachgezogen

- **Datum:** 2026-07-06
- **Status:** erledigt
- **Umsetzung:** Ist-Zustand aus Code, README, CHANGELOG und SECURITY.md
  erhoben (Server-Lifecycle, Registry/tool_provider, Token-Bindung,
  Gates, Testinventar) und als Perspektiven `00`, `01`, `03`, `04`, `05`
  dokumentiert; sieben Leitentscheidungen (adr01–adr07) festgehalten. Die
  bestehende zweisprachige Benutzer-Doku (`02-user-doc.md` /
  `02-user-doc.de.md`) blieb unverändert.

### task01 — Ausbau zum Suite-MCP-Hub (Releases 1.1.0–1.4.0, Juni/Juli 2026)

- **Status:** erledigt (Herkunft: CHANGELOG)
- **Umfang:**
  - **1.1.0 (2026-06-28):** Premium-Gating über `local_elediaai_tutor_premium`
    (Feature `mcp_tools`); Premium-Write-Tools `moodle_update_course` und
    `moodle_enrol_user`; Konfiguration/Token/Hilfe in einer Plugin-Shell
    konsolidiert; Post/Redirect/Get bei Token-Erstellung.
  - **1.2.0 (2026-07-02):** Premium-Generierungs-Tools `moodle_create_activity`,
    `moodle_generate_h5p`, `moodle_generate_questions`; bedingte Registrierung
    der Suite-Tools (Marketplace-Installationen unberührt).
  - **1.3.0 (2026-07-02):** Self-Study-Tool-Familie (`moodle_selfstudy_*`) als
    Wrapper um `local_elediaai_selfstudy` (Kurs-Opt-in + Quota im gewrappten
    Plugin).
  - **1.4.0 (2026-07-02):** Lehrer-Workflows — Grading-Loop
    (`moodle_read_submission`, `moodle_grade_submission`),
    `moodle_course_health`, `moodle_message_course_students`,
    `moodle_update_activity`, `moodle_manage_sections`.

### task00 — MCP-Server-Fundament bis STABLE (Releases 0.8.0–1.0.1)

- **Status:** erledigt (Herkunft: CHANGELOG, vor der DevFlow-Einführung)
- **Umfang:**
  - **0.8.0:** Kuratierter AI-Tool-Katalog (zwölf Tools), Multi-Version-MCP
    (Negotiation, Annotationen, strukturierte Ausgabe, Pagination),
    Token-Self-Service + interne API + Metadaten-Tabelle, Security-Controls
    (Origin/CORS, Rate-Limit, Size-Limit, Emergency-Disable, Audit-Events),
    OAuth-Discovery; Governance-Dateien und GitLab-CI-Pipeline.
  - **0.9.0 (2026-06-12):** Lernstand-Tools `moodle_my_progress`,
    `moodle_quiz_info`, `moodle_forum_discussions`.
  - **1.0.0 (2026-06-12):** `moodle_search_content`,
    `moodle_my_submission_files`; Negative-Case-Test-Sweep; Security-Fix
    `can_access_course()`-Gate für `moodle_get_resource` u. a.; Maturity
    **STABLE**.
  - **1.0.1 (2026-06-27):** Hardening — atomares Rate-Limit (MUC-Lock),
    Endpoint-Bindung an MCP-Services (`enforce_mcp_service`),
    `maildisplay`-Fix, nur aktivierte Auth-Methoden bei `moodle_create_user`,
    Sichtbarkeits-Fixes (Announcements, versteckte Sektionen), keine internen
    Exception-Meldungen an Clients.

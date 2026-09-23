# Moodle MCP Web Service — Quality

> Nachweis, dass die Features funktionieren. Umsetzung in `03-dev-doc.md`.

## Automatisierte Tests

PHPUnit (19 Testdateien, zusammen 205 Testmethoden; Suite
`webservice_elediamcp_testsuite`):

- `tests/oauth_service_test.php` — 23 Tests (OAuth 2.1 Authorization Code +
  PKCE: voller Flow, Single-Use/Replay, Ablauf, PKCE-Mismatch, Redirect-URI-,
  Client-, `response_type`-, `grant_type`-, S256-Negativfälle, DCR-Validierung,
  Metadaten, GC)
- `tests/ai_tools_test.php` — 23 Tests (Kern-AI-Tools)
- `tests/server_test.php` — 20 Tests (Server-Lifecycle, Parameter-Koerzierung)
- `tests/negative_cases_test.php` — 15 Tests (Sichtbarkeits-/Enrolment-/
  Privacy-Grenzen über alle Tools)
- `tests/create_activity_test.php` — 12 Tests
- `tests/request_test.php` — 12 Tests (JSON-RPC-Parsing/-Validierung)
- `tests/token_manager_test.php` — 12 Tests (Erstellung, Widerruf, Ablauf,
  Listing, Pruning)
- `tests/teacher_tools_test.php` — 15 Tests (Grading-Loop, Course Health,
  Bulk-Message, Sektionen)
- `tests/generation_tools_test.php` — 12 Tests (H5P/Questiongen-Wrapper)
- `tests/tool_provider_test.php` — 10 Tests (Katalog, Annotationen, Pagination)
- `tests/client_test.php` — 9 Tests (HTTP-Convenience-Client)
- `tests/learner_tools_test.php` — 9 Tests
- `tests/api_test.php` — 6 Tests (interne API: Komponenten-Attribution,
  Ownership-Scoping)
- `tests/protocol_test.php` — 6 Tests (Versionsverhandlung)
- `tests/security_test.php` — 6 Tests (Origin, CORS, Rate-Limit)
- `tests/selfstudy_tools_test.php` — 5 Tests (Self-Study-Wrapper)
- `tests/tool_provider_extras_test.php` — 5 Tests
- `tests/content_tools_test.php` — 4 Tests
- `tests/privacy_provider_test.php` — 1 Test

Behat (`--tags @webservice_elediamcp`, eigener Step-Kontext
`tests/behat/behat_webservice_elediamcp.php`):

- `tests/behat/token_management.feature` — 4 Szenarien (Preferences-Link,
  Create → Reveal-once → Revoke, „kein nutzbarer Service"-Hinweis)
- `tests/behat/webservice_elediamcp.feature` — 1 Szenario

Fixtures: `tests/fixtures/local_elediaai_tutor_premium/` (Premium-Add-on-Stub
für Free/Premium-Tests).

## Checks

### test01 PHPUnit lokal grün

- **Wie:** `vendor/bin/phpunit --testsuite webservice_elediamcp_testsuite`
  aus dem Moodle-Root (Container `elediaai-moodle-1`).
- **Status (SUI-621, 2026-08-05):** ⚠️ DB-gestützte Suite lokal **nicht
  ausgeführt** — die PHPUnit-Neuinitialisierung (nötig wegen des Versions-Bumps)
  scheitert im Container `elediaai-moodle-1` an einem **fremden** Defekt
  (Block-Namenskonflikt `block_lernhive_ai_chat` ↔ `elediaai_chat` aus dem
  laufenden SUI-560-Rename), nicht an dieser Änderung. Ersatzweise verifiziert:
  `php -l` grün auf allen neuen/geänderten Dateien; XMLDB-Struktur via
  `xmldb_file::validateDefinition()` **valide**; ein Bootstrap-Smoke-Test der
  reinen Flow-Logik (PKCE, Redirect-URI-Regeln, Metadaten) **15/15 grün**.
  Die vollständige DB-Suite läuft im Remote-CI (test02–test04).

### test02 Plugin-eigenes CI (GitHub Actions)

- **Wie:** `.github/workflows/moodle-ci.yml` im vendored Plugin-Repo:
  moodle-plugin-ci-Matrix PHP 8.2/8.3/8.4 × pgsql/mariadb ×
  `MOODLE_501_STABLE` (phplint, phpunit, phpcs u. a.).
- **Status:** ✅ Workflow vorhanden und auf das genestete
  `public/webservice/elediamcp`-Layout eingerichtet; aktueller Laufstatus vom
  Monorepo aus nicht einsehbar.

### test03 Plugin-eigenes CI (GitLab)

- **Wie:** `.gitlab-ci.yml` im Plugin-Repo: PHPCS (Moodle-Standard), PHPStan,
  Semgrep, Trivy, PHPUnit (mit Plugin-Coverage), Behat, PHPDepend-Metriken,
  Summary-Report.
- **Status:** ✅ Pipeline-Definition vorhanden; Laufstatus vom Monorepo aus
  nicht einsehbar.

### test04 Monorepo-CI (Forgejo)

- **Wie:** `scripts/ci-matrix.php` (Monorepo-Root) definiert die Test-Matrix;
  `.forgejo/workflows/moodle-ci.yml` gibt die entsprechenden Jobs aus.
- **Status:** ✅ `webservice/elediamcp` ist im Plugin-Katalog enthalten
  (Gruppe `'rag'`, `scripts/ci-matrix.php` Zeilen 102–107); das Plugin wird
  durch die Monorepo-CI abgedeckt. Dependency-Eintrag unter DEPS vorhanden.

### test05 Behat Self-Service-UI

- **Wie:** `vendor/bin/behat --tags @webservice_elediamcp` (Preferences-Link,
  Create/Reveal/Revoke-Flow, Service-Hinweis).
- **Status:** ⏳ lokal nicht separat verifiziert; Teil der
  GitLab-CI-Pipeline-Definition.

### test06 PHP-Syntax + Codestyle

- **Wie:** `php -l`; `vendor/bin/phpcs --standard=moodle webservice/elediamcp`
  (CONTRIBUTING.md, spiegelt CI).
- **Status (SUI-621, 2026-08-05):** ✅ `php -l` grün; `phpcs` (Moodle-Standard,
  `moodlehq/moodle-cs`) auf allen neuen/geänderten PHP-Dateien **0 Errors /
  0 Warnings**. (Zwei bestehende Long-Line-Errors in `configuration.php` sind
  identisch mit `origin/main` und nicht Teil dieser Änderung.)

### test07 End-to-End mit echtem MCP-Client

- **Wie:** Token über Preferences → MCP tokens erzeugen, Claude Desktop via
  `mcp-remote`-Snippet verbinden; `initialize`/`tools/list`/`tools/call`
  gegen `server.php`; Gates prüfen (falsche Origin → 403, Rate-Limit → 429,
  Emergency-Disable → 503, Nicht-MCP-Service-Token abgewiesen).
- **Status:** ⏳ manuelle Verifikation nicht in dieser Session durchgeführt.

### test08 DevFlow-Dokumentation vorhanden

- **Wie:** `docs/00`–`05` vorhanden, code-konsistent (02 zweisprachig,
  bestand bereits).
- **Status:** ✅ erfüllt (2026-07-06)

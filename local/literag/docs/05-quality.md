# eLeDia.ai LiteRAG — Quality

> Nachweis, dass die Features funktionieren. Umsetzung in `03-dev-doc.md`.

## Automatisierte Tests

PHPUnit-Suite `local_literag_testsuite`, 13 Testdateien mit zusammen
78 Tests (plus Fixture `tests/fixtures/sample.pdf`):

- `tests/tutor_chat_test.php` — 13 Tests (End-to-End `tutor_chat`).
- `tests/chunker_test.php` — 8 Tests (Extraktion/Chunking, PDF).
- `tests/prompt_builder_test.php` — Grounding, answer_style,
  Kontext-Delimitierung und Injection-Hinweise.
- `tests/agent_test.php` — Tool-Calling-Loop und Delimitierung von
  Tool-Resultaten.
- `tests/document_store_test.php` — 10 Tests (Upsert/Delete, Prefix-Scope,
  Tenant-/Kurs-/URL-Validierung).
- `tests/dispatcher_test.php` — 6 Tests (JSON-RPC-Routing).
- `tests/token_validator_test.php` — 5 Tests (Token-Validierung).
- `tests/retriever_test.php` — 4 Tests (FTS/LIKE-Retrieval).
- `tests/permission_filter_test.php` — 3 Tests (Sichtbarkeits-Invariante).
- `tests/user_eraser_test.php` — 1 Test (nutzerbezogene Erasure ueber
  Conversations, Messages, Memory, Query-Logs).
- `tests/prune_logs_test.php` — 2 Tests (Retention-Cleanup fuer Query-Logs,
  Conversations und Messages, inklusive Retention `0`).
- `tests/privacy_provider_test.php` — 7 Tests (Metadata, Context-/Userlist-
  Discovery, Export und alle Privacy-Delete-Pfade).
- `tests/rate_limiter_test.php` — 3 Tests (Minuten-/Tagesfenster und
  Nutzerisolation der Kostenkontrolle).

Nicht abgedeckt (eigene Testdateien fehlen): `memory_store`, `topic_registry`,
`reranker`, `llm\client`, `conversation_repository` (teils indirekt ueber
`tutor_chat_test.php`).
Ausbau: task02 in `04-tasks.md`.

## Behat-Tests — bewusst nicht vorgesehen

LiteRAG ist ein reines Backend-Plugin ohne Lerner-UI; alle Lerner-Interaktionen
laufen über `block_elediaai_tutor` (JSON-RPC-Integrationen). Admin-seitige
Workflows oder Settings existieren nicht. Daher ist Behat-Abdeckung nicht erforderlich.

**Neubewertung:** Falls LiteRAG später direkte Lerner-UI oder Admin-Workflows
erhält, wird diese Entscheidung neu überprüft (q03, beantwortet 2026-08-04).

## Checks

### test01 PHPUnit lokal grün

- **Wie:** `vendor/bin/phpunit --testsuite local_literag_testsuite` im
  Moodle-Root (lokales Docker).
- **Status:** ✅ grün — README dokumentiert den Lauf vom 2026-06-27
  („Tests: 57, Assertions: 188"); devflow-Stand 2026-06-28 bestätigt lokale
  PHPUnit-Checks grün. Lauf vom 2026-07-09: 72 Tests / 272 Assertions gruen,
  keine PHPUnit-Deprecations. Aktueller Stand 2026-08-04 (Moodle 5.2.1,
  PHP 8.3): 78 Tests / 278 Assertions gruen, weiterhin ohne Deprecations.

### test02 Moodle-CS / phpcs

- **Wie:** `phpcs --standard=Moodle --ignore='*/vendor/*'
  public/local/literag`.
- **Status:** ✅ lokal grün laut devflow-Stand 2026-06-28.

### test03 Eigener Job in der Monorepo-CI (Forgejo)

- **Wie:** Gruppe `rag` im Katalog `scripts/ci-matrix.php`, gefahren von
  `.forgejo/workflows/moodle-ci.yml` (moodle-plugin-ci 4, php 8.3 / pgsql 16),
  zusammen mit `local/ragingest` und `webservice/elediamcp`; die Suite-
  Abhaengigkeiten `local/elediaai_core` und `local/elediaai_tutor_premium`
  werden als Extras gestaged.
- **Status:** ✅ grün (2026-08-04) — der Matrix-Eintrag vom 2026-07-08 hatte bis
  dahin nie eine Zelle erzeugt: `mod/elli` lag in derselben Gruppe und deckelt
  mit `supported = [405, 501]` bei Moodle 5.1, waehrend die Matrix nur
  `MOODLE_502_STABLE` faehrt — eine Gruppe laeuft nur auf Zweigen, die *alle*
  ihre Plugins deklarieren. `mod/elli` hat jetzt eine eigene Gruppe, die
  RAG-Zelle entsteht wieder (`scripts/ci-matrix-test.php` haelt das fest), und
  Gruppen ohne kompatiblen Zweig stehen ab sofort als
  `-> Ueberspringe …: inkompatibel (…)` im Matrix-Log, statt still zu
  verschwinden. Lokal in Containern nachgestellt (Moodle 5.2.1, PHP 8.3,
  PostgreSQL 16): Install, phplint und PHPUnit gruen fuer alle drei Plugins der
  Gruppe, `local_literag` 78 Tests / 278 Assertions.
- **Offen:** Der erste echte Forgejo-Lauf folgt mit dem Merge nach `main`.

### test04 Cross-DB-Nachweis (PostgreSQL + MariaDB)

- **Wie:** Nachtjob `crossdb` in `.forgejo/workflows/moodle-ci.yml`: zweiter
  Install derselben Gruppe gegen MariaDB 11.4, dann die PHPUnit-Suiten.
  Betroffene Gruppen stehen in `CROSS_DB_GROUPS` (`scripts/ci-matrix.php`),
  aktuell `rag`. PostgreSQL 16 deckt die regulaere Matrix ab (test03).
- **Status:** ✅ grün (2026-08-04) — Nachweisweg geklaert und umgesetzt: nicht
  die GitLab-Pipeline des Standalone-Repos (q04), sondern die Monorepo-CI.
  Lokal in Containern nachgestellt: `local_literag` 78 Tests / 278 Assertions
  gruen auf **MariaDB 11.4** und auf **PostgreSQL 16**, jeweils Moodle 5.2.1 /
  PHP 8.3 / moodle-plugin-ci 4.5.10; `local/ragingest` und
  `webservice/elediamcp` ebenfalls gruen auf beiden Datenbanken.
- **Warum diese Gruppe cross-DB laeuft:** `local\schema` legt den Volltext-Index
  datenbankabhaengig als GIN (PostgreSQL) oder FULLTEXT (MariaDB) an, und der
  Retrieval-Pfad haengt daran — ein reiner PostgreSQL-Lauf laesst genau diesen
  Zweig ungeprueft.
- **Offen:** Der erste echte Forgejo-Nachtlauf folgt mit dem Merge nach `main`.

### test05 PHPUnit-12-Tauglichkeit

- **Wie:** Docblock-Metadaten der Testklassen auf PHP-Attribute migrieren;
  Lauf ohne Test-Runner-Deprecations.
- **Status:** ✅ pass (2026-07-09) — alle LiteRAG-Testklassen verwenden
  `CoversClass`-Attribute statt `@covers`-Docblocks; lokaler Lauf
  `local_literag_testsuite`: 72 Tests / 272 Assertions, keine PHPUnit-
  Deprecations.

### test06 Privacy-/Retention-/Erasure-Abdeckung

- **Wie:** gezielte Unit-Tests für `privacy\provider`-Export,
  `user_eraser` (Tool- und Privacy-Pfad löschen identische Zeilen) und
  `task\prune_logs` (Retention-Grenzfälle).
- **Status:** ✅ pass (2026-07-09) — `tests/user_eraser_test.php` prueft
  zielnutzerbezogene Loeschung von Conversations, Messages, Memory und
  Query-Logs bei Erhalt anderer Nutzerdaten. `tests/prune_logs_test.php`
  deckt Retention-Grenzfaelle fuer Query-Logs, Conversations und Messages ab.
  `tests/privacy_provider_test.php` deckt Metadata, Context-/Userlist-
  Discovery, Export von Conversations/Memory/Query-Logs und alle drei Privacy-
  Delete-Pfade ab. Lokal gruen in `local_literag_testsuite` (72 Tests /
  272 Assertions, keine PHPUnit-Deprecations).

### test07 End-to-End über die Tutor-Landschaft

- **Wie:** Mit konfiguriertem LLM: Inhalt über `local_ragingest` ingestieren →
  Frage im `block_elediaai_tutor` stellen → fundierte Antwort mit
  `[S#]`-Quellen; Sichtbarkeits-Grenze (verstecktes Modul), Hint-/Quiz-Modus
  und Live-Tools (read-only, Schreib-Preview + Bestätigung) prüfen.
- **Status:** ⏳ manuelle Verifikation nicht dokumentiert (braucht laufende
  Gegenstellen + LLM-Endpoint).

### test08 DevFlow-Dokumentation vorhanden

- **Wie:** `docs/00`–`05` vorhanden, code-konsistent; `devflow.md` aufgelöst.
- **Status:** ✅ erfüllt (2026-07-06)

### test09 Prompt-Injection-Haertung

- **Wie:** Kontext und Tool-Resultate werden als untrusted Blocks mit klaren
  Delimitern in den Prompt gegeben; Tests pruefen, dass entsprechende
  Sicherheitsinstruktionen und Begrenzungen im Prompt enthalten sind.
- **Status:** ✅ umgesetzt (2026-07-08); Suite-Lauf gruen (2026-08-04:
  78 Tests / 278 Assertions auf PostgreSQL und MariaDB, test03/test04).

## Reifegrad-/Release-Kriterien (q05)

> Definiert am 2026-08-04 (q05). Prüfbare Kriterien für den Schritt aus BETA
> heraus. **Gate 1** ist das Muss für den internen Stable-Release im
> eLeDia.ai-Betrieb; **Gate 2** ergänzt Gate 1 nur, falls eine öffentliche
> moodle.org-/Directory-Einreichung tatsächlich verfolgt wird — das ist eine
> separate Produktentscheidung (siehe Hinweis am Ende). Jedes Kriterium ist
> gegen die oben stehenden `test0X`-Checks bzw. gegen `04-tasks.md` belegbar.

### Gate 1 — BETA → stable (interner Produktions-Release)

Harte Schwellen, keine Wunschliste: jede Zeile ist ein Pass/Fail-Kriterium und
muss grün in diesem Dokument belegt sein, **bevor** `version.php` von
`MATURITY_BETA` weggeht. Ein „unentschieden"/„teilweise" gilt als Fail.

1. **PHPUnit grün:** `local_literag_testsuite` läuft vollständig durch —
   0 Failures, 0 Errors, 0 Risky, **keine** PHPUnit-Test-Runner-Deprecations
   (test01/test05). Die sicherheitskritischen Pfade (`permission_filter`,
   `token_validator`, `document_store`, `rate_limiter`, `privacy\provider`,
   `user_eraser`, `prune_logs`) behalten eigene Tests; für die bisher nur
   indirekt getesteten Klassen (`memory_store`, `topic_registry`, `reranker`,
   `llm\client`, `conversation_repository`) ist die indirekte Abdeckung über
   `tutor_chat_test.php` bestätigt oder als bewusstes Nicht-Ziel notiert.
2. **Cross-DB grün:** dieselbe Suite grün auf PostgreSQL **und**
   MySQL/MariaDB (getrennte CI-Läufe). Zwingend, weil Retrieval
   dbfamily-spezifisches FTS-SQL nutzt (adr02); ein reiner Single-DB-Lauf zählt
   nicht (test04).
3. **phpcs sauber:** `phpcs --standard=Moodle --ignore='*/vendor/*'
   public/local/literag` → **0 Errors und 0 Warnings** (Vendor ausgenommen),
   in der CI erzwungen (test02).
4. **moodle-plugin-ci-Precheck grün:** der volle `moodle-plugin-ci`-Satz des
   Forgejo-Jobs (phplint, phpcpd/phpmd, phpdoc, `validate`, `savepoints`,
   `mustache`, `grunt`) läuft ohne Errors; der erste grüne Lauf auf `main` ist
   dokumentiert (test03/task04).
5. **Privacy-Provider vollständig:** `privacy\provider` deklariert alle
   gespeicherten Daten (Metadata), implementiert Export und **alle drei**
   Delete-Pfade (`delete_data_for_user`, `delete_data_for_users`,
   `delete_data_for_all_users_in_context`), und `user_eraser`/`prune_logs`
   löschen deckungsgleich; per Unit-Test belegt (test06).
6. **Upgrade-Pfad getestet:** Frisch-Install **und** Upgrade von der niedrigsten
   unterstützten Vorversion laufen fehlerfrei durch; die dbfamily-geschützten
   FULLTEXT/GIN-Statements in `install.php`/`upgrade.php` (adr02) sind
   idempotent/re-runnable und die Upgrade-Savepoints korrekt gesetzt.
7. **Behat-Entscheidung getroffen:** q03 ist entschieden — entweder existieren
   grüne Behat-Features (mind. Admin-Settings/Help) oder Behat ist als
   dokumentiertes Nicht-Ziel fixiert. „Offen" ist ein Fail.
8. **Doku-Stand konsistent:** DevFlow 00–05, `README.md`/`README.de.md` und
   `CHANGELOG.md` entsprechen der ausgelieferten `version.php`
   (version/release/maturity); keine stale Referenzen mehr — insbesondere die
   GitLab-Pipeline-Referenz aus q04 geklärt (test08).
9. **End-to-End-Nachweis:** test07 mindestens einmal gegen laufende
   Gegenstellen (`local_ragingest`, `block_elediaai_tutor`, optional
   `webservice_elediamcp`) plus LLM-Endpoint durchgeführt und dokumentiert.
10. **Maturity-Bump:** erst wenn 1–9 grün sind, `version.php` auf
    `MATURITY_STABLE` und SemVer `>= 1.0.0` heben, mit passendem
    `CHANGELOG.md`-Eintrag (Keep a Changelog / SemVer).

### Gate 2 — optionale öffentliche moodle.org-/Directory-Einreichung

Nur zusätzlich zu Gate 1 und nur, falls die Einreichung verfolgt wird:

1. **Prechecker grün:** Der moodle.org-Plugin-Prechecker
   (mdlcode/phpcs/phpdoc, Upgrade-Savepoints, Sprachstring-Prüfung) läuft ohne
   Errors.
2. **Standalone-Release:** Eigenständiges Repo/Zip mit eigenem Version-Tag,
   GPL-Header in jeder Datei, vollständige `thirdpartylibs.xml`
   (`smalot/pdfparser` bereits deklariert), keine gebündelten Secrets.
3. **Raw-SQL-DDL bewertet:** Die dbfamily-geschützten FULLTEXT/GIN-Statements in
   `install.php`/`upgrade.php` (adr02) sind ein bekannter Review-Reibungspunkt
   für moodle.org; vor Einreichung begründen oder abschwächen.
4. **Kopplung geklärt:** Die Drop-in-Kopplung an `local_ragingest`,
   `block_elediaai_tutor` und `webservice_elediamcp` (adr03) ist für einen
   öffentlichen Katalog dokumentiert bzw. das Plugin bewusst als „Teil einer
   Suite" ausgewiesen; sonst ist eine öffentliche Einreichung fachlich fraglich.
5. **Directory-Metadaten:** Support-/Doku-URL, Screenshots und
   Versionskompatibilität (`supported [405, 502]`) im Directory-Eintrag
   hinterlegt.

**Hinweis / offene Produktentscheidung:** Ob eine öffentliche moodle.org-
Einreichung überhaupt Ziel ist, ist keine Reifegrad-, sondern eine
Produktentscheidung (Johannes) — auf SUI-150 als Frage an ihn gestellt, nicht
stillschweigend gesetzt. Diese Kriterien definieren nur das Gate für den Fall,
dass sie verfolgt wird; Gate 1 (interner Stable-Release) ist davon unabhängig
und kann für sich erfüllt werden. Solange die Entscheidung offen ist, gilt Gate 2
als ruhend.

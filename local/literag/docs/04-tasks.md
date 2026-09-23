# eLeDia.ai LiteRAG — Tasks

> Arbeitslog. Offene Fragen als `qXX`, Aufgaben als `taskXX`. Neueste oben.

## Offen

### task09 — Doku nachziehen: Klassen `schema` und `rate_limiter` im Klassenregister

- **Status:** offen
- **Kontext:** Die Klassen `local\schema` (`classes/local/schema.php`,
  Volltext-Index-Verwaltung FULLTEXT/GIN) und `local\rate_limiter`
  (`classes/local/rate_limiter.php`, atomare Per-User-/Per-Tenant-
  Kostenkontrolle) werden in `03-dev-doc.md` nur inline in Architektur-/
  Sicherheitsabschnitten erwaehnt (Zeilen 17, 73–74, 174), fehlen aber im
  formalen Klassenregister unter `## Klassen`. `rate_limiter` ist zudem durch
  `tests/rate_limiter_test.php` abgedeckt.
- **Ziel:** Beide Klassen als eigene Eintraege in die `## Klassen`-Liste in
  `03-dev-doc.md` aufnehmen (Infrastruktur/Sicherheit).

## Offene Fragen (qXX)

### q01 — Was ist in 0.5.2 geändert? — beantwortet

Mit task05 beantwortet. CHANGELOG und README dokumentieren 0.5.2; die späteren
Releases 0.5.3, 0.6.0, 0.6.1, 0.6.2 und 0.6.4 sind ebenfalls im CHANGELOG nachgeführt.

### q02 — Eigener CI-Job für `local/literag` im Monorepo? — beantwortet

Beantwortet am 2026-07-08: Ja, als Gruppe `rag` in `scripts/ci-matrix.php`.
Nachtrag 2026-08-04: Der Eintrag allein genuegte nicht — die Gruppe erzeugte
wegen `mod/elli` keine Matrixzelle. Mit task04 laeuft sie wieder.

### q03 — Behat-Abdeckung gewollt? — beantwortet

Beantwortet am 2026-08-04: Nein. LiteRAG ist ein reines Backend-Plugin ohne
Lerner-UI; alle Lerner-Interaktionen laufen über `block_elediaai_tutor`
(JSON-RPC-Integration). Admin-seitige Workflows/Settings existieren nicht.
Behat-Abdeckung ist daher nicht erforderlich. Dokumentation erfolgt in
`05-quality.md` mit Neubewertungs-Bedingung (falls zukünftig Lerner-UI hinzukommt).

### q04 — Referenz auf die GitLab-CI-Pipeline — beantwortet

Beantwortet am 2026-08-04: Die GitLab-Pipeline ist kein Nachweisweg dieses
Repos. Sie stammt aus dem Standalone-Repo, die `.gitlab-ci.yml` liegt nicht
unter `public/local/literag/`, und niemand pflegt sie hier. Verbindlich ist
allein die Monorepo-CI: PostgreSQL 16 in der regulaeren Matrix, MariaDB 11.4
im Nachtjob `crossdb` (task04). Der README-Abschnitt „Continuous integration"
ist entsprechend umgeschrieben.

### q05 — Reifegrad-/Release-Kriterien — beantwortet

Beantwortet am 2026-08-04. Die prüfbaren Kriterien für den Schritt aus BETA
heraus sind jetzt als Zwei-Gate-Kriterienset in `05-quality.md`
(„Reifegrad-/Release-Kriterien") definiert: **Gate 1** (BETA → stable, interner
Produktions-Release) verlangt grüne CI inkl. Cross-DB-Nachweis, vollständig
grüne Testsuite ohne Deprecations, phpcs grün, getroffene Behat-Entscheidung
(q03), konsistente Doku (u. a. q04), einen dokumentierten End-to-End-Nachweis
und erst danach den `MATURITY_STABLE`-Bump auf SemVer `>= 1.0.0`. **Gate 2**
ergänzt die Kriterien für eine etwaige öffentliche moodle.org-/Directory-
Einreichung (Prechecker, Standalone-Release, Raw-SQL-DDL-Bewertung,
Suite-Kopplung, Directory-Metadaten). Ob die öffentliche Einreichung überhaupt
Ziel ist, bleibt eine separate Produktentscheidung (Johannes); Gate 1 ist davon
unabhängig. README/README.de-Roadmap verweisen auf dieses Kriterienset.

## Erledigt

### task04 — Externer Cross-DB-Nachweis (CI)

- **Datum:** 2026-08-04
- **Status:** erledigt
- **Kontext:** README beschreibt eine GitLab-CI-Pipeline mit
  PostgreSQL/MariaDB-Matrix als Cross-DB-Nachweis; im Monorepo-Pfad existiert
  keine `.gitlab-ci.yml`, und die Monorepo-CI (Forgejo) fuhr `local/literag`
  faktisch nicht (siehe q02).
- **Befund 2026-08-04:** Der 2026-07-08 ergaenzte Matrix-Eintrag hat nie eine
  Zelle erzeugt. `scripts/ci-matrix.php` laesst eine Gruppe nur auf Zweigen
  laufen, die *alle* ihre Plugins in `version.php` deklarieren; `mod/elli` stand
  in derselben Gruppe `rag` und deckelt mit `supported = [405, 501]` bei Moodle
  5.1, waehrend die Matrix nur `MOODLE_502_STABLE` faehrt. Damit fielen
  `local/literag`, `local/ragingest` und `webservice/elediamcp` still aus der
  Matrix — der Katalogtest hielt das sogar als Sollzustand fest.
- **Umsetzung 2026-08-04:**
  - `mod/elli` bekommt in `scripts/ci-matrix.php` eine eigene Gruppe `elli`.
    Ein gedeckeltes Plugin nimmt jetzt nur noch sich selbst aus der Matrix; der
    RAG-Stack laeuft wieder.
  - Gruppen ohne kompatiblen Zweig werden im Matrix-Log als
    `-> Ueberspringe <gruppe>/<zweig>: inkompatibel (...)` ausgewiesen, statt
    stillschweigend zu verschwinden.
  - Neuer Nachtjob `crossdb` in `.forgejo/workflows/moodle-ci.yml`: zweiter
    Install derselben Gruppe gegen MariaDB 11.4, nur die PHPUnit-Suiten. Welche
    Gruppen das betrifft, steht in `CROSS_DB_GROUPS` (aktuell `rag`) — LiteRAG
    verwaltet in `local\schema` Volltext-Indizes einmal als GIN und einmal als
    FULLTEXT, dieser Zweig blieb bei reinem PostgreSQL ungeprueft.
- **Verifikation 2026-08-04:** Der neue Job lokal in Containern nachgestellt
  (moodle-plugin-ci 4.5.10, Moodle 5.2.1, PHP 8.3): Install und PHPUnit gruen
  gegen MariaDB 11.4 *und* PostgreSQL 16, jeweils fuer alle drei Plugins der
  Gruppe. `local_literag`: 78 Tests / 278 Assertions, beide Datenbanken.
  `scripts/ci-matrix-test.php` gruen (9 Zellen, 1 Cross-DB-Zelle).
- **Restrisiko:** Der Forgejo-Nachtlauf selbst ist damit noch nicht gelaufen;
  der erste Lauf nach dem Merge ist der externe Nachweis. `mod/elli` bleibt
  ungetestet, bis es 5.2 deklariert (eigener Task in dessen DevFlow).

### task02 — Privacy-, Retention- und User-Erasure-Tests ausbauen

- **Datum:** 2026-07-09
- **Status:** erledigt (Privacy-, User-Erasure- und Retention-Tests ergaenzt
  2026-07-09)
- **Kontext:** Die Roadmap nennt den Ausbau der Privacy-, Erasure- und
  Retention-Abdeckung explizit.
- **Umsetzung 2026-07-09:** Neue enge PHPUnit-Tests:
  `tests/user_eraser_test.php` prueft, dass `user_eraser::erase()` Conversations,
  Messages, Memory und Query-Logs nur fuer den Zielnutzer loescht und Daten
  anderer Nutzer erhaelt. `tests/prune_logs_test.php` prueft, dass der Scheduled
  Task alte Query-Logs und abgelaufene Conversations inklusive Messages loescht,
  frische Zeilen erhaelt und Retention `0` als "dauerhaft behalten" behandelt.
  `tests/privacy_provider_test.php` prueft Metadata, Context-/Userlist-
  Discovery, Export von Conversations/Memory/Query-Logs sowie die drei
  Delete-Pfade (`delete_data_for_user`, `delete_data_for_users`,
  `delete_data_for_all_users_in_context`).
- **Verifikation:** `tests/prune_logs_test.php` lokal gruen (2 Tests /
  9 Assertions); `tests/privacy_provider_test.php` lokal gruen (7 Tests /
  32 Assertions); `local_literag_testsuite` lokal gruen (72 Tests /
  272 Assertions, keine PHPUnit-Deprecations).

### task08 — Ingestion-Metadaten gegen Moodle validieren

- **Datum:** 2026-07-25
- **Status:** erledigt
- **Umsetzung:** `document_store` verlangt Tenant- und Site-Identität, prüft
  `cmid` gegen den behaupteten Kurs und akzeptiert nur die kanonische lokale
  Modul-URL. Damit können Ingestion-Payloads weder kursfremde Zuordnungen noch
  externe Phishing-Links als Tutor-Quelle einschleusen.
- **Verifikation:** Die bisherigen Upsert-Tests nutzen reale Kursmodule; drei
  Negativtests decken fehlenden Tenant, Kurs/CM-Mismatch und externe URLs ab.

### task07 — Per-User-Kostenkontrolle für Tutor-Aufrufe

- **Datum:** 2026-07-25
- **Status:** erledigt
- **Umsetzung:** `rate_limiter` zählt Tutor-Turns atomar in MUC, getrennt nach
  Tenant und Nutzer. Konfigurierbare Minuten- und Tagesgrenzen werden nach
  Token- und Eingabevalidierung, aber vor Retrieval/Reranking/LLM geprüft.
  `db/caches.php` hält die Fensterzähler; Überschreitungen liefern einen
  kontrollierten JSON-RPC-Toolfehler.
- **Verifikation:** `tests/rate_limiter_test.php` deckt Minutenlimit,
  Tageslimit und Nutzerisolation ab.

### task03 — PHPUnit-Docblock-Metadaten auf Attribute migrieren

- **Datum:** 2026-07-09
- **Status:** erledigt
- **Umsetzung:** Alle neun LiteRAG-Testklassen nutzen jetzt
  `PHPUnit\Framework\Attributes\CoversClass` statt `@covers`-Docblock-
  Metadaten.
- **Verifikation:** `rg '@covers|@dataProvider|@test|@depends|@group'
  public/local/literag/tests` ohne Treffer; `php -l` fuer alle geaenderten
  Testdateien; `local_literag_testsuite` lokal gruen (62 Tests /
  222 Assertions, keine PHPUnit-Deprecations); `git diff --check`.

### task05 — README/CHANGELOG auf 0.5.2 nachziehen

- **Datum:** 2026-07-08
- **Status:** erledigt
- **Umsetzung:** EN/DE-README nennen jetzt `0.5.2` / `MATURITY_BETA`, den
  Monorepo-Forgejo-Job statt einer nicht vorhandenen Monorepo-GitLab-Pipeline
  und die verbleibenden Release-Kriterien. `CHANGELOG.md` hat einen 0.5.2-
  Eintrag fuer BETA-Reife, eigenen Forgejo-Matrix-Job und Prompt-Injection-
  Haertung fuer Kontext-/Tool-Resultate.
- **Verifikation:** `git diff --check`; `local_literag_testsuite` lokal gruen
  (62 Tests / 222 Assertions, keine PHPUnit-Deprecations).

### task06 — Prompt-Injection-Haertung fuer Kontext und Tool-Results

- **Datum:** 2026-07-08
- **Status:** erledigt
- **Umsetzung:** `prompt_builder` markiert RAG-Kontext als untrusted data und
  ergaenzt eine Systemregel, dass CONTEXT/Tool-Results keine Anweisungen sind.
  `agent` rahmt elediamcp-Tool-Ergebnisse mit Delimitern und Warnhinweis ein,
  bevor sie wieder an das LLM gehen.
- **Verifikation:** Assertions in `tests/prompt_builder_test.php` und
  `tests/agent_test.php`; `local_literag_testsuite` lokal gruen
  (62 Tests / 222 Assertions, keine PHPUnit-Deprecations); `php -l`;
  `git diff --check`.

### task01 — DevFlow-Docs 00–05 nachgezogen

- **Datum:** 2026-07-06
- **Status:** erledigt
- **Umsetzung:** Ist-Zustand erhoben (7 Tabellen, Ingestion-/MCP-Endpunkte,
  Retrieval-Pipeline, Agent/Live-Tools, Privacy/Retention, Shell-UX, 9
  Testdateien) und als Perspektiven `00`–`05` dokumentiert; sieben
  Leitentscheidungen festgehalten (u. a. adr02 embeddings-freies RAG,
  adr03 Drop-in-Schnittstellen, adr07 Write-Tools mit Zwei-Schritt-
  Bestätigung). Inhalt des Stubs `docs/devflow.md` (Stand 2026-06-28)
  eingearbeitet und die Datei gelöscht. Bestehende Nutzer-Doku
  (`02-user-doc.md` EN / `02-user-doc.de.md` DE) unverändert übernommen.

### q04 — Referenz auf die GitLab-CI-Pipeline — beantwortet

Beantwortet mit task05 (Commit fb5cb27, 2026-07-09): README-Abschnitt
„Continuous integration" wurde angepasst. Beschreibt nun den tatsächlichen
Monorepo-Stand (Forgejo-Workflow `.forgejo/workflows/moodle-ci.yml`) statt
der nicht existierenden Monorepo-`.gitlab-ci.yml`. GitLab-Pipeline ist Fallback
für das Standalone-Repo, wird im Monorepo-README klar unterschieden.

### task00 — Review-Runde und Release-Stand 0.5.x (vor dieser Session)

- **Datum:** 2026-06-28 (devflow-Stand, Branch `review_johannes`)
- **Status:** erledigt
- **Umfang:** Review-Befunde, Shell-UX, Fallback-Styling, Display-Name und
  LernHive-Handbuch umgesetzt; lokale PHPUnit- und Moodle-CS-Checks grün.
  Davor Release-Historie laut CHANGELOG: 0.1.0 Initial-Release (Ingestion,
  MCP-Endpoint, Retrieval, Token-Validierung, LLM-Client, Privacy, CI) →
  0.2.0 gebündelter PDF-Parser → 0.3.x strukturierte/deduplizierte Quellen →
  0.4.0 Live-Moodle-Tools → 0.5.0 Schreib-Tools mit Zwei-Schritt-Bestätigung
  → 0.5.1 strikte Antwort-Stil-Modi.

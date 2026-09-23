# LiteRAG - Moodle-local RAG-Backend

`local_literag` ist ein schlankes Retrieval-Augmented-Generation-Backend fuer
Moodle. Das Plugin speichert ingestierte Kursinhalte in der Moodle-Datenbank,
sucht relevante Text-Chunks mit klassischer Volltextsuche, prueft die
Moodle-Sichtbarkeit fuer die anfragende Person und laesst ein
OpenAI-kompatibles LLM eine geerdete Antwort mit Quellen erzeugen.

Das Plugin ist fuer die eLeDia.ai Tutor-Landschaft gedacht:

- `local_elediaai_sources` sendet Kursinhalte an LiteRAG.
- `block_elediaai_tutor` ruft LiteRAG ueber den JSON-RPC/MCP-Tutor-Endpunkt auf.
- Bestehende Plugins muessen nicht angepasst werden; ihre Endpoint-URLs zeigen
  auf LiteRAG.

Entwickelt von [eLeDia GmbH](https://eledia.de), Berlin.

English documentation: [README.md](README.md)

## Funktionen

- Moodle-lokales RAG-Backend ohne Embeddings und ohne Vektordatenbank.
- Ingest-Endpunkt kompatibel mit `local_elediaai_sources`.
- JSON-RPC/MCP-Tutor-Endpunkt kompatibel mit `block_elediaai_tutor`.
- Datenbank-Volltextsuche fuer PostgreSQL, MySQL/MariaDB und MSSQL, mit
  portablem `LIKE`-Fallback.
- Berechtigungssichere Suche ueber Moodle-Sichtbarkeitspruefungen fuer
  Kursmodule.
- OpenAI-kompatibler LLM-Client fuer OpenAI API oder LiteLLM-aehnliche Proxies.
- Strukturierte Quellen, Gespraechsverlauf und optionale Langzeit-Memory.
- Optionale Live-Moodle-Tools ueber `webservice_elediamcp`.
- Moodle Privacy API Provider und Retention-Cleanup-Task.
- Admin-Einstellungsseite mit optionaler LernHive/eLeDia.ai Plugin Shell und
  Moodle-nativem Fallback, wenn die Shell nicht verfuegbar ist.

## Voraussetzungen

- Moodle 4.5 bis 5.2 gemaess `version.php`.
- PHP 8.1 oder neuer.
- Eine von der Retrieval-Schicht unterstuetzte Moodle-Datenbank.
- Ein OpenAI-kompatibler Chat-Completions-Endpunkt mit API-Key.
- Aktivierte `$CFG->slasharguments` fuer Ingest-URLs wie
  `/local/literag/ingest.php/documents/upsert`.
- Optional: `local_lernhive` fuer die gemeinsame Plugin-Shell-Oberflaeche.
- Optional: `webservice_elediamcp` fuer Live-Moodle-Tools.
- Optional: Poppler `pdftotext` fuer bessere PDF-Textextraktion.

## Installation

1. Plugin in das Moodle-Local-Plugin-Verzeichnis kopieren:
   - Moodle 5.1+ Document-Root-Layout: `public/local/literag`
   - Klassisches Layout: `local/literag`
2. **Website-Administration > Mitteilungen** oeffnen.
3. Datenbank-Upgrade ausfuehren.
4. **Website-Administration > Plugins > Lokale Plugins > LiteRAG** oeffnen.
5. API-Keys, LLM-Endpunkt und Retrieval-Einstellungen konfigurieren.

## Konfiguration

### LiteRAG-Einstellungen

Oeffne **Website-Administration > Plugins > Lokale Plugins > LiteRAG**.

Wichtige Einstellungen:

- **Ingest-API-Key**: gemeinsames Secret im Header `X-API-Key`.
- **Tutor-Transport-Token**: optionales zusaetzliches Bearer-Token fuer
  Tutor-Aufrufe.
- **Tutor-Anfragen pro Person**: Minuten-Burst-Limit und harte
  Tagesobergrenze, bevor kostenpflichtige LLM-Arbeit beginnt.
- **LLM Base URL**: OpenAI-kompatible API-Basis-URL, z. B.
  `https://api.openai.com/v1`.
- **LLM API-Key**: Bearer-Key fuer den LLM-Endpunkt.
- **Modell, Temperatur, maximale Tokens und Timeout**.
- **Retrieval und Chunking**: Kandidatenanzahl, Kontextanzahl, Chunk-Groesse und
  Ueberlappung.
- **Live-Moodle-Tools**: Read-only Moodle-Toolaufrufe via
  `webservice_elediamcp` aktivieren.
- **Datenschutz und Aufbewahrung**: Query-Logging, Gespraechsaufbewahrung und
  Memory-Aufbewahrung.

Die Einstellungsseite zeigt die Endpoint-URLs an, die in `local_elediaai_sources` und
`block_elediaai_tutor` eingetragen werden muessen.

### `local_elediaai_sources` verbinden

Ingest-Endpunkt setzen auf:

```text
https://<wwwroot>/local/literag/ingest.php/documents/upsert
```

Denselben API-Key verwenden wie in LiteRAG.

### `block_elediaai_tutor` verbinden

RAG-Server-URL setzen auf:

```text
https://<wwwroot>/local/literag/mcp.php
```

Die Standard-Toolnamen im Tutor-Block passen zu den LiteRAG-Defaults.

## Live-Moodle-Tools

LiteRAG kann optional Read-only Moodle-Tools aus `webservice_elediamcp`
aufrufen. Dadurch kann der Tutor live nutzerspezifische Moodle-Daten einbeziehen,
z. B. Kurse, Aufgaben, Fristen, Bewertungen, Kalendereintraege und Fortschritt.

Der interne MCP-Client ruft immer das lokale `$CFG->wwwroot` auf.
Request-seitige `system_url`-Werte werden fuer diesen Loopback-Aufruf ignoriert,
um SSRF und Token-Exfiltration zu vermeiden.

Nur Read-only Tools werden dem Modell angeboten, ausser Schreibwerkzeuge sind
explizit fuer unterstuetzte Zwei-Schritt-Bestaetigungsablaeufe aktiviert.

## PDF-Unterstuetzung

LiteRAG bringt die pure-PHP-Bibliothek `smalot/pdfparser` fuer
PDF-Textextraktion mit. Fuer einfaches PDF-Indexing ist kein externes Binary
notwendig.

Fuer bessere Extraktion kann Poppler installiert und der absolute Pfad zu
`pdftotext` in den LiteRAG-Einstellungen oder ueber Moodles
`$CFG->pathtopdftotext` konfiguriert werden.

Beispiele:

| Umgebung | Befehl |
|---|---|
| Debian / Ubuntu | `sudo apt-get install poppler-utils` |
| Docker Debian Image | `apt-get update && apt-get install -y poppler-utils` |
| RHEL / Rocky / Alma | `sudo dnf install poppler-utils` |
| macOS Homebrew | `brew install poppler` |

## Datenmodell

LiteRAG nutzt diese Haupttabellen:

- `local_literag_sources`
- `local_literag_chunks`
- `local_literag_conversations`
- `local_literag_messages`
- `local_literag_query_log`
- `local_literag_memory`
- `local_literag_topics`

Datenbankfamilien-spezifische Volltextindizes werden ueber abgesichertes SQL in
Installations- und Upgrade-Schritten angelegt. Wenn native Volltextsuche nicht
verfuegbar ist, nutzt LiteRAG `LIKE`-Queries als Fallback.

## Sicherheit und Datenschutz

- Ingest-Anfragen werden mit konstantzeitlichem `X-API-Key`-Vergleich
  authentifiziert.
- Tutor-Anfragen validieren das Moodle-User-Token in-process.
- Gefundene Chunks werden gegen die Moodle-Sichtbarkeit der anfragenden Person
  gefiltert.
- Live-Moodle-Tools nutzen das lokale `$CFG->wwwroot` und vertrauen keinen
  request-seitigen Ziel-URLs.
- LLM API-Keys, Transport-Tokens und Moodle-User-Tokens werden nicht geloggt.
- Langzeit-Memory ist optional und zustimmungsgesteuert.
- Moodle Privacy API Export und Loeschung sind implementiert.
- Aufbewahrung wird ueber einen Scheduled Task bereinigt.

## Entwicklung und Tests

Moodle Coding Standard ausfuehren:

```sh
phpcs --standard=Moodle --ignore='*/vendor/*' public/local/literag
```

PHPUnit aus dem Moodle-Root ausfuehren:

```sh
vendor/bin/phpunit --testsuite local_literag_testsuite
```

Die lokale Docker-Verifikation am 2026-06-27 war erfolgreich mit:

```text
Tests: 57, Assertions: 188
```

PHPUnit 11 meldet Test-Runner-Deprecations fuer alte Docblock-Metadaten in den
Testklassen. Das sind keine Testfehler, sollte aber vor PHPUnit 12 auf Attribute
migriert werden.

## Continuous Integration

Im eLeDia.ai-Monorepo faehrt der Forgejo-Workflow
`.forgejo/workflows/moodle-ci.yml` LiteRAG in der Katalog-Gruppe `rag`
zusammen mit `local/elediaai_sources` und `webservice/elediamcp`; die Suite-
Abhaengigkeiten `local/elediaai_core` und `local/elediaai_tutor_premium`
werden dabei mitinstalliert.

Der Cross-DB-Nachweis kommt aus diesem Workflow, nicht aus einer
GitLab-Pipeline: die regulaere Matrix laeuft gegen PostgreSQL 16, der Nachtjob
`crossdb` installiert dieselbe Gruppe ein zweites Mal gegen MariaDB 11.4 und
faehrt dort die PHPUnit-Suiten. Welche Gruppen das betrifft, steht in
`CROSS_DB_GROUPS` in `scripts/ci-matrix.php`.

## Drittanbieterbibliotheken

Gebundelt unter `vendor/` und in `thirdpartylibs.xml` deklariert:

- `smalot/pdfparser` 2.12.5, LGPL-3.0, pure-PHP PDF-Textextraktion.

## Status und Roadmap

Aktueller Release: `0.6.4`, Maturity `MATURITY_BETA`.

Bekannte Folgearbeiten:

- Ersten gruenen Forgejo-CI-Lauf fuer den eigenen LiteRAG-Job dokumentieren.
- BETA-Ausstiegs- und Directory-Release-Kriterien sind als pruefbares
  Zwei-Gate-Kriterienset in `docs/05-quality.md` (Abschnitt
  "Reifegrad-/Release-Kriterien") definiert: Gate 1 ist das interne
  BETA-zu-stable-Gate (gruene CI inkl. Cross-DB-Nachweis, vollstaendig gruene
  Testsuite, phpcs, getroffene Behat-Entscheidung, konsistente Doku, ein
  dokumentierter End-to-End-Lauf, danach der `MATURITY_STABLE`-Bump auf SemVer
  `>= 1.0.0`); Gate 2 ergaenzt die Kriterien fuer eine optionale oeffentliche
  moodle.org-Einreichung. Ob diese oeffentliche Einreichung verfolgt wird,
  bleibt eine separate Produktentscheidung.

## Lizenz

GNU GPL v3 oder spaeter. Siehe [LICENSE](LICENSE).

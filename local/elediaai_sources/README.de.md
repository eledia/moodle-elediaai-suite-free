# local_elediaai_sources - KI Quellen fuer Moodle

[English](README.md) | Deutsch

`local_elediaai_sources` ist die **Quellenschicht** der eLeDia.ai-Suite. Es
beantwortet fuer die gesamte Suite eine Frage — *welche Moodle-Inhalte duerfen
als Wissensquelle dienen, und in welcher Form?* — und uebergibt diese Inhalte
dann an ein austauschbares Ziel.

Alles, was Inhalte nutzbar macht, liegt **einmal** hier: die Opt-in-Regeln, die
Auswahl je Aktivitaet, Extraktion und Aufbereitung, die deterministische
Dokumentidentitaet, die Mandantenableitung und der Lifecycle, wenn Inhalte sich
aendern oder verschwinden. Ein Ziel wiederholt nichts davon; es kennt nur seine
eigenen Endpunkte und seine Authentifizierung.

Diese Trennung ist der Zweck des Plugins, und sie oeffnet sich in zwei
Richtungen:

| Erweiterungsachse | Beantwortete Frage | Mechanismus |
|---|---|---|
| **Quellentypen** | Was koennen wir lesen? | `aisourcesextractor_*`-Subplugins (17 mitgeliefert) |
| **Ziele** | Wohin geht es? | Implementierungen des `sink`-Interface |

Ziele sind heute die externe **Ingestion-API** und **LiteRAG** auf derselben
Website. Ein dritter Platz ist fuer einen **OERWEAVE**-Quellenkorb reserviert;
das Interface existiert, die Implementierung noch nicht. Sie zu ergaenzen
bedeutet eine Klasse — ohne Aenderung an Auswahl, Extraktion, Identitaet oder
Lifecycle.

Es ist immer genau ein Ziel aktiv. Umschalten wird unterstuetzt und laesst jeden
markierten Kurs abweichen, sodass der vorhandene Abgleich ihn in das neue Ziel
neu aufnimmt.

> **Zum Namen:** Das Plugin hiess bis Release `0.16.0` `local_ragingest`. Der
> alte Name beschrieb das Verfahren (RAG-Ingest) statt der Aufgabe (Quellen
> kuratieren) und band eine allgemeine Faehigkeit an einen einzigen Abnehmer.

Das Plugin ist Teil des eLeDia.ai Tutor / LiteRAG Admin-Flows. Settings,
Statuskarte, Reindex-Seite und Hilfeseite werden in der gemeinsamen
Plugin-Shell angezeigt, wenn diese Shell in Moodle vorhanden ist. Die
Aktivitaetsauswahl fuer Lehrkraefte bleibt bewusst in der normalen
Kursoberflaeche.

## Status

| Punkt | Aktueller Stand |
|---|---|
| Moodle-Komponente | `local_elediaai_sources` |
| Plugin-Typ | Local plugin unter `local/elediaai_sources` |
| Offiziell unterstuetzte Moodle-Versionen | `4.5` bis `5.2` in `version.php` |
| Lokaler Kompatibilitaetscheck | Moodle `5.2.1` besteht die PHPUnit-Suite |
| PHP | Moodle-unterstuetzte PHP-Version fuer die Zielversion |
| Reifegrad | Beta |

Moodle 5.2.1 wird lokal bereits fuer Entwicklung und Tests genutzt. Die
offizielle `supported`-Angabe ist seit Release `0.12.3` auf `[405, 502]`
angehoben.

## Features

**Quellen waehlen**

- **Zweistufige Opt-in-Auswahl:** Ein Kurs muss zuerst zentral (Pilotliste,
  Kategorie-Allowlist) oder am Kurs (Custom-Field) freigegeben sein. Innerhalb
  eines freigegebenen Kurses entscheiden Lehrkraefte dann je Aktivitaet.
- **Aktivitaetsauswahl fuer Lehrkraefte:** eine Kursseite mit Sofort-Schaltern,
  nach Abschnitten gruppiert, plus ein Haken im Bearbeitungsformular jeder
  Aktivitaet. Beide schreiben dieselbe Entscheidung.
- **Konfigurierbarer Standard fuer Unentschiedenes:** Opt-out (alles
  Unterstuetzte wird aufgenommen, Standard) oder Opt-in (nichts ohne Auswahl).
  Eine ausdrueckliche Entscheidung der Lehrkraft gewinnt immer und ueberlebt
  eine Aenderung dieser Einstellung; das Umschalten entfernt nie etwas bereits
  Aufgenommenes.
- **Markierungssperre:** Im Pilotbetrieb lassen sich Kursfeld,
  Aktivitaetsseite und deren Webservice einfrieren, sodass nur zentrale
  Einstellungen gelten.

**Inhalte lesen**

- **Extractor-Subplugins:** ein `aisourcesextractor_*`-Subplugin je
  Aktivitaetstyp; 17 sind mitgeliefert.
- **Untertitel als eigentlicher Text von E-Learning-Paketen:** SCORM-Pakete
  sind oft nur eine Player-Huelle ohne Fliesstext. Der Sprechertext wird aus
  eigenstaendigen `.vtt`/`.srt`-Spuren gelesen und bei Articulate Storyline aus
  dessen JavaScript-gebuendelten Untertiteln entpackt.
- **Multi-Dokument-Module:** Ordner und aehnliche Module senden mehrere
  Dokumente mit Suffix-Source-IDs und praefixbasiertem Loeschen.
- **H5P-Aufloesung:** eingebettete H5P-Platzhalter werden zu ihrem Text
  aufgeloest.

**Den Index wahr halten**

- **Deterministische Dokumentidentitaet:** `{tenant}:course{id}:cmid{id}` plus
  optionales Suffix, abgeleitet statt gespeichert — jedes Ziel ist damit gleich
  adressierbar.
- **Tenant aus Site-URL:** abgeleitet aus `$CFG->wwwroot`; absichtlich kein
  frei editierbares Tenant-Setting.
- **Zustand je Ziel:** Jeder Kurs haelt fest, in welches Ziel, mit welchem
  Embedding-Modell und welchem Mandanten er zuletzt aufgenommen wurde — aendert
  sich eines davon, weicht er ab und wird neu aufgenommen.
- **Ereignisgesteuerter Lifecycle:** Modul-Erstellung, -Aktualisierung,
  -Loeschung und unterstuetzte Subcontent-Aenderungen planen Ad-hoc-Tasks ein.
  Beim Loeschen eines Kurses werden dessen Dokumente geraeumt, solange die
  Modul-IDs noch lesbar sind.
- **Abwaehlen entfernt Inhalte:** Ein Ausschluss loest ein praefixbasiertes
  Loeschen aus, statt das Modul nur zu ueberspringen.
- **Abgleich als Sicherungsnetz:** Eine geplante Aufgabe richtet Kurse wieder
  aus, deren Markierung und Indexzustand auseinanderlaufen.
- **Manueller Reindex:** alle ausstehenden freigegebenen Kurse sammeln
  einplanen oder einen Kurs gezielt per ID neu aufnehmen.

## Installation

1. Plugin-Code in die Moodle-Codebasis kopieren:

   ```text
   local/elediaai_sources
   ```

2. Moodle-Upgrade ueber Website-Administration oder CLI ausfuehren:

   ```bash
   php admin/cli/upgrade.php --non-interactive
   ```

3. Settings-Seite oeffnen:

   ```text
   /admin/settings.php?section=local_elediaai_sources_settings
   ```

4. RAG-Endpunkt und API-Key konfigurieren.

5. Einen oder mehrere Pilotkurse freigeben und Moodle-Cron laufen lassen, damit
   die Ad-hoc-Tasks die Indexierung verarbeiten koennen.

## Admin-Settings

Alle Einstellungen liegen auf einer Seite:

```text
/admin/settings.php?section=local_elediaai_sources_settings
```

Wenn die eLeDia.ai Tutor Shell vorhanden ist, erscheint diese Seite in der
gemeinsamen Navigation mit aktivem Menuepunkt **KI Quellen**. Das Setup ist
nicht mehr auf mehrere Moodle-Admin-Menues verteilt.

### Connection

| Setting | Beschreibung | Default |
|---|---|---|
| Ziel | Wohin Dokumente gehen: Ingestion-API oder LiteRAG | Ingestion-API |
| Ingestion-API Basis-URL | Wurzel des Dienstes, **ohne** Aktionspfad | leer |
| Ingestion-API Schluessel | Secret, das als `X-API-Key` gesendet wird, je Mandant | leer |
| Allow private target | Aktiviert Moodle-cURL `ignoresecurity` fuer lokale/private Ziele | aus |

Das Ziel wird ausdruecklich konfiguriert und nicht mehr aus der Form einer URL
erraten. Jedes Ziel kennt seine eigenen Endpunkte:

- **Ingestion-API** — die Basis-URL plus die in `docs/api-specification.md`
  festgelegten Pfade `/documents/upsert`, `/documents/delete`, `/health`.
  Einzutragen ist `http://rag-service:8001`, **nicht**
  `http://rag-service:8001/documents/upsert`.
- **LiteRAG** — nichts zu konfigurieren. Die Route wird aus `wwwroot`
  abgeleitet, der Schluessel stammt aus `local_literag` selbst.

Ein Zielwechsel laesst jeden Kurs abweichen, sodass der vorhandene Abgleich ihn
in das neue Ziel neu aufnimmt. Das alte Ziel wird **nicht** automatisch
geraeumt.

`allow_private_target` ist noetig, weil Moodle cURL-Aufrufe auf private,
Loopback- oder blockierte Ziele normalerweise schuetzt. Fuer oeffentliche
Endpunkte sollte es aus bleiben; fuer lokale Docker-/Service-Name-Ziele muss es
bewusst aktiviert werden.

### Course Selection

| Setting | Beschreibung |
|---|---|
| Ingested course categories | Suchbare Mehrfachauswahl von Kategorien. Kurse in ausgewaehlten Kategorien oder Unterkategorien sind freigegeben. |
| Pilot courses | Suchbare Mehrfachauswahl konkreter Kurse fuer Pilotphasen. |
| Activities without a decision | Standard fuer Aktivitaeten ohne Entscheidung der Lehrkraft: aufgenommen (Opt-out, Standard) oder nicht aufgenommen (Opt-in). |
| Lock course marking | Macht das Kursfeld inert/read-only und blendet die Aktivitaetsauswahl aus, sodass nur zentrale Settings entscheiden. |

Das Kursfeld **KI Quellen** (Shortname `aisources`) wird bei der Installation angelegt. Wenn die
Kursmarkierung nicht gesperrt ist, gibt es:

- `Default`: zentrale Pilot-/Kategorie-Regeln entscheiden.
- `Include`: Kurs freigeben, auch wenn zentrale Regeln ihn nicht freigeben.
- `Exclude`: Kurs ausschliessen, auch wenn zentrale Regeln ihn freigeben.

Wenn die Markierung gesperrt ist, bleiben bestehende Feldwerte erhalten, werden
aber ignoriert, bis die Sperre wieder deaktiviert wird.

### Aktivitaetsauswahl

Die Freigabe eines Kurses entscheidet, *ob* er genutzt werden darf; die
Aktivitaetsauswahl entscheidet, *was* davon genutzt wird. Lehrkraefte erreichen
sie auf zwei Wegen, die dieselbe Entscheidung schreiben:

- die Kursseite **KI Quellen** (Kursnavigation) mit einem Schalter je
  Aktivitaet, nach Abschnitten gruppiert — Aktivitaetstypen, die kein Extractor
  lesen kann, erscheinen deaktiviert statt versteckt, damit die Seite keine
  groessere Abdeckung suggeriert, als es gibt;
- der Abschnitt **eLeDia.ai | KI Quellen** im Bearbeitungsformular jeder
  Aktivitaet.

Gespeichert werden nur ausdrueckliche Entscheidungen. Aktivitaeten, die niemand
angefasst hat, folgen dem Site-Standard oben. Zwei Folgen sind wichtig:

- **Ein Wechsel des Site-Standards loescht nie.** Entfernt wird ausschliesslich
  aufgrund eines ausdruecklichen Ausschlusses; ein Umschalten auf Opt-in laesst
  alles bereits Aufgenommene stehen und nimmt nur nichts Neues mehr auf.
- **Duplizieren oder Wiederherstellen nimmt die Entscheidung mit.** Der
  Indexzustand wandert nicht mit: Er beschreibt, was diese Website an ihr
  Ziel gesendet hat.

### Limits

| Setting | Beschreibung | Default |
|---|---|---|
| Max document size (MB) | Text/HTML oberhalb des Limits wird gekuerzt; zu grosse Binaerdateien werden uebersprungen | `20` |
| Request timeout (seconds) | Timeout pro HTTP-Request-Versuch | `30` |

## Indexstatus und Reindex

Oben auf der Settings-Seite erscheint eine Statuskarte:

- **Released courses are indexed:** aktuell warten keine freigegebenen Kurse auf
  Indexierung.
- **Released courses are waiting for indexing:** mindestens ein freigegebener
  Kurs wurde noch nicht erfolgreich indexiert.

Wenn Kurse warten, plant die primaere Aktion **Index released courses now** die
ausstehenden Kurse als Moodle-Ad-hoc-Tasks ein. Die zweite Aktion oeffnet:

```text
/local/elediaai_sources/reindex.php
```

Die Reindex-Seite bietet:

- Sammel-Queueing fuer freigegebene, noch nicht indexierte Kurse
- manuelle Reindexierung eines Kurses per numerischer Moodle-Kurs-ID

Der Kurszustand wird in `local_elediaai_sources_course` gespeichert. Ein Kurs bekommt
`ingested = 1` nur, wenn ein Reindex-Lauf ohne Fehler-Resultate abgeschlossen
wurde. `skipped`-Module gelten nicht als Fehler, damit leere oder nicht
unterstuetzte Kurse nicht endlos eingeplant werden.

## Was wird indexiert?

Das Plugin indexiert nur Inhalte aus freigegebenen Kursen. Nicht indexiert
werden:

- der Site Course
- geloeschte oder unsichtbare Course Modules
- Kurse ohne Opt-in-Freigabe
- nicht unterstuetzte Modultypen
- leere Aktivitaeten
- zu grosse Binaerdateien
- persoenliche Lernendenantworten in Feedbacks und aehnlichen Extractors, wo
  diese Inhalte bewusst ausgeschlossen sind

Unterstuetzte Event-Ausloeser:

- Course Module erstellt, aktualisiert oder geloescht
- Book-Kapitel geaendert
- Glossary-Eintrag geaendert
- Lesson-Seite geaendert
- Wiki-Seite geaendert
- Database-Record geaendert
- Quiz-Struktur geaendert
- Question-Bank-Aenderungen, die Quizzes betreffen

## Mitgelieferte Extractors

Das Plugin bringt Extractors fuer verbreitete Moodle-Core-Aktivitaeten und
ausgewaehlte Paket-/Interaktionsmodule mit.

| Subplugin | Aktivitaet | Hinweise |
|---|---|---|
| `assign` | Assignment | Intro, Aktivitaetsanweisungen, Bewertungskriterien; keine Abgaben |
| `book` | Book | Sichtbare Kapitel/Subkapitel in Lesereihenfolge |
| `data` | Database | Felddefinitionen und freigegebene Records |
| `feedback` | Feedback | Fragen/Item-Definitionen; keine abgegebenen Antworten |
| `folder` | Folder | Ein Dokument pro unterstuetzter Datei, PDF bleibt PDF |
| `glossary` | Glossary | Beschreibung, freigegebene Eintraege, Aliase/Synonyme |
| `h5pactivity` | H5P Activity | Gelabelter Lerntext aus H5P JSON/Paketinhalt |
| `imscp` | IMS Content Package | Manifest-Struktur und HTML-Body-Inhalte |
| `label` | Text/media area | Intro-Inhalt |
| `lesson` | Lesson | Seitenfolge, Antworten, Feedback wo relevant |
| `page` | Page | Seiteninhalt |
| `quiz` | Quiz | Fragen, Antworten, Feedback, Hinweise und Gesamtfeedback |
| `resource` | File | Unterstuetzte Text-, HTML- und PDF-Dateien |
| `scorm` | SCORM | Intro, SCO-Titel und lokale HTML-Launch-Seiten |
| `videotime` | Video Time | Intro und VTT-Captions/Transkript |
| `wiki` | Wiki | Intro und Subwiki-Seiten |
| `workshop` | Workshop | Intro, Anweisungen, Abschluss, Bewertungsdimensionen |

Von Extractors erzeugtes HTML wird zentral fuer eingebettete H5P-Platzhalter
nachbearbeitet, soweit diese aufloesbar sind.

## API Contract

### Upsert

```http
POST /documents/upsert
X-API-Key: <configured key>
Content-Type: application/json
```

```json
{
    "source_id": "localhost:course42:cmid99",
    "content": "<base64-encoded content>",
    "content_type": "text/html",
    "qdrant_metadata": {
        "tenant_id": "localhost",
        "site_url": "http://localhost:8080",
        "course_id": 42,
        "cmid": 99,
        "module_url": "http://localhost:8080/mod/page/view.php?id=99"
    },
    "parser_options": null
}
```

### Delete

```http
POST /documents/delete
X-API-Key: <configured key>
Content-Type: application/json
```

```json
{
    "source_id": "localhost:course42:cmid99"
}
```

Multi-Dokument-Module nutzen Suffixe wie:

```text
localhost:course42:cmid99:file1
```

Vor einem Multi-Dokument-Upsert loescht der Manager die bisherige Dokumentmenge
per Prefix-Delete.

Welche Content Types erlaubt sind, entscheidet die versionierte Support-Matrix
(`classes/format_matrix.php`, beschrieben in
[`docs/format-support-matrix.md`](docs/format-support-matrix.md)) — nicht die
Extraktoren:

- **Kern, immer:** `text/plain`, `text/html`, `application/pdf`
- **Ausgehandelt, solange das aktive Ziel sie per `GET /health` ankuendigt:**
  DOCX, PPTX, `text/markdown`, XLSX, CSV, DOC, ODT/ODS/ODP

Einen dritten Zustand "noch nicht entschieden" gibt es nicht. Hier wird nichts
geparst, also ist jede offene Frage zu einem Format eine Parser-Frage: Das Ziel
beantwortet sie, indem es den Typ ankuendigt oder eben nicht.

Jede nicht gesendete Datei hinterlaesst einen Hinweis mit Dateiname und Grund:
im Cron-Protokoll, in der Ergebnismeldung und in der Vorschau der Aktivitaet.

## Architektur

```text
Moodle-Event / Schalter der Lehrkraft / geplanter Abgleich
  -> local_elediaai_sources\observer
  -> Moodle ad-hoc task
  -> course_gate      darf dieser Kurs genutzt werden?
  -> activity_gate    ist diese Aktivitaet ausgewaehlt?
  -> aisourcesextractor_* subplugin        <- hier stecken Quellentypen ein
  -> Aufbereitung + Source ID
  -> local_elediaai_sources\sink\sink      <- hier stecken Ziele ein
  -> local_elediaai_sources\api_client
  -> Ingestion-API | LiteRAG | (OERWEAVE, reserviert)
```

Alles oberhalb der beiden Pfeile teilen sich alle Ziele. Deshalb kostet ein
neues Ziel eine Klasse und ein neuer Quellentyp ein Subplugin.

Ziele:

| Sink | Endpunkte | Konfiguration |
|---|---|---|
| `ingestion_api_sink` | Basis-URL plus die in `docs/api-specification.md` festgelegten Pfade | Basis-URL und Mandanten-Schluessel |
| `literag_sink` | `local/literag/ingest.php`, aus `wwwroot` abgeleitet | Keine — der Schluessel kommt aus `local_literag` |
| *OERWEAVE* | — | Reservierter Platz; nicht implementiert |

Wichtige Klassen:

| Klasse | Aufgabe |
|---|---|
| `content_extractor` | Interface fuer Aktivitaets-Extractors |
| `multi_document_extractor` | Optionales Interface fuer mehrere Dokumente pro Modul |
| `ingestion_manager` | Findet Extractors, validiert/normalisiert Dokumente, uebergibt sie dem Ziel |
| `sink` | Interface eines Ziels: eigene Endpunkte, eigene Authentifizierung, eigenes Embedding-Modell |
| `sink_manager` | Loest das konfigurierte Ziel auf und liefert das Auswahlmenue |
| `api_client` | Nur HTTP-Transport: Wiederholungen, Zeitlimit, `X-API-Key` |
| `course_gate` | Berechnet, ob ein Kurs ueberhaupt genutzt werden darf |
| `activity_gate` | Berechnet, ob eine Aktivitaet ausgewaehlt ist; speichert ausdrueckliche Entscheidungen |
| `course_state` | Speichert Indexzustand und queued Reconciliation |
| `observer` | Wandelt Moodle-Events in Hintergrundtasks um |
| `source_id_helper` | Baut deterministische Source IDs |
| `tenant` | Leitet die Tenant-ID aus `$CFG->wwwroot` ab |
| `h5p_embed_helper` | Loest eingebettete H5P-Platzhalter in HTML auf |
| `h5p_text_extractor` | Extrahiert gelabelten Text aus H5P JSON/Paketen |
| `output\shell` | Bindet Plugin-Seiten in die eLeDia.ai Tutor Shell ein |

Tasks:

| Task | Aufgabe |
|---|---|
| `ingest_module_task` | Ein Course Module extrahieren und upserten |
| `delete_module_task` | Dokumentmenge eines Course Modules loeschen |
| `reconcile_course_task` | Einen Kurs in den gewuenschten Index-/Purge-Zustand bringen |
| `reconcile_all_task` | Regelmaessiger Safety-Net-Check fuer divergente Kurse |

## Einen Extractor schreiben

Ein Subplugin liegt unter:

```text
subplugins/{name}/
├── version.php
├── classes/extractor.php
└── lang/en/aisourcesextractor_{name}.php
```

Die Extractor-Klasse heisst:

```php
namespace aisourcesextractor_{name};

use local_elediaai_sources\content_extractor;

final class extractor implements content_extractor {
    public function supports(\cm_info $cm): bool {
        return $cm->modname === '{name}';
    }

    public function extract(\cm_info $cm): ?array {
        return [
            'content' => '<p>Extracted content</p>',
            'content_type' => 'text/html',
            'title' => $cm->name,
        ];
    }
}
```

Leitlinien:

- `null` zurueckgeben, wenn kein sinnvoller Inhalt vorhanden ist
- nur erlaubte Content Types verwenden
- User-/Editor-Inhalte escapen, bevor HTML gebaut wird
- persoenliche Abgaben nicht indexieren, ausser ein spaeteres Feature ist
  explizit dafuer freigegeben und dokumentiert
- fuer Dateien oder wiederholte Subdokumente `multi_document_extractor` nutzen
- H5P-Platzhalter in HTML zentral nachbearbeiten lassen

Ein Extractor entscheidet nie, *ob* Inhalte genutzt werden duerfen, und spricht
nie mit einem Ziel. Er bekommt ein Kursmodul und liefert Text.

## Ein Ziel ergaenzen

Ein Ziel implementiert `local_elediaai_sources\sink\sink` und wird in der
Konstante `CLASSES` des `sink_manager` eingetragen. Das ist die Naht, in die
ein kuenftiger OERWEAVE-Quellenkorb einsteckt.

```php
namespace local_elediaai_sources\sink;

class oerweave_sink implements sink {
    public static function id(): string;          // stabil; wird je Kurs gespeichert
    public static function name(): string;        // erscheint im Auswahlmenue
    public function is_configured(): bool;        // alles da, um genutzt zu werden?
    public function healthcheck(): array;         // erreichbar und gesund?
    public function embedding_model(): ?string;   // null, wenn keines ausgewiesen wird
    public function upsert(array $payload): array;
    public function delete(string $sourceid, string $scope = 'exact'): array;
}
```

Was ein Ziel geschenkt bekommt: Auswahl, Extraktion, Aufbereitung, das
Groessenlimit, die Dokumentidentitaet, die Mandantenableitung, Wiederholungen
und den gesamten Lifecycle. Was ihm gehoert: seine Endpunkte, seine
Authentifizierung und ob es ein Embedding-Modell benennen kann.

Leitlinien:

- `id()` ueber Releases stabil halten — sie wird je Kurs gespeichert; eine
  Aenderung laesst jeden Kurs abweichen und neu aufnehmen
- die Zeilenform des Transports zurueckgeben (`success`, `http_code`,
  `response`, `error`), damit alle Aufrufer unveraendert bleiben
- `delete()` mit `scope` umsetzen; `prefix` muss das Dokument **und** alle
  Teildokumente darunter entfernen, sonst bleiben bei mehrdateiigen
  Aktivitaeten Reste zurueck
- aus `embedding_model()` lieber `null` liefern als einen Wert zu erfinden —
  ein erfundenes Modell wird je Kurs gespeichert und spaeter verglichen, als
  bedeute es etwas
- fuer HTTP `api_client` nutzen, damit Wiederholungen, Zeitlimit und
  `X-API-Key` sich ueberall gleich verhalten

## Lokale Entwicklung

### Deployment in das lokale eledia.ai Moodle

Das begleitende Docker-Setup im eledia.ai-Projekt stellt ein lokales Moodle
bereit unter:

```text
http://localhost:8080
```

Typischer Ablauf:

```bash
cd /Users/moskaliuk/Documents/Code/eledia.ai
./scripts/local-deploy.sh deploy
```

Wenn dieser Plugin-Checkout nicht ins Image eingebaut ist, muss er nach:

```text
/var/www/html/public/local/elediaai_sources
```

kopiert oder synchronisiert werden. Danach Moodle-Upgrade und Cache-Purge
ausfuehren.

### Debug Server

Fuer lokale API-Tests gibt es einen kleinen Python-Mock-Server:

```bash
python3 local/elediaai_sources/debug_server.py
python3 local/elediaai_sources/debug_server.py --port 9000
python3 local/elediaai_sources/debug_server.py --fail
python3 local/elediaai_sources/debug_server.py --delay 5
```

`debug_server.py` wird ueber `.gitattributes` aus Release-Archiven
ausgeschlossen.

## Tests und Coding Style

### PHPUnit

Das lokale Docker-Setup kann Moodle-PHPUnit initialisieren und die Plugin-Suite
ausfuehren:

```bash
cd /Users/moskaliuk/Documents/Code/eledia.ai
./scripts/local-deploy.sh phpunit-init
./scripts/local-deploy.sh phpunit
PHPUNIT_TESTSUITE=local_elediaai_sources_testsuite ./scripts/local-deploy.sh phpunit
```

Aktuelles lokales Ergebnis:

```text
Tests: 164
Assertions: 373
Failures: 0
Errors: 0
Skipped: 5
PHPUnit Deprecations: 27
Notices: 1
```

In einem Moodle-Checkout mit bereits initialisierter PHPUnit-Umgebung:

```bash
vendor/bin/phpunit --testsuite local_elediaai_sources_testsuite
```

### PHP-Syntax

```bash
find public/local/elediaai_sources -name '*.php' -print0 | xargs -0 -n1 php -l
```

### Moodle Coding Style

`moodlehq/moodle-cs` ausserhalb des Plugin-Checkouts installieren und PHPCS
ausfuehren:

```bash
rm -rf /tmp/local-elediaai-sources-moodle-cs
mkdir -p /tmp/local-elediaai-sources-moodle-cs
cd /tmp/local-elediaai-sources-moodle-cs
composer init --no-interaction --name=local-elediaai-sources/moodle-cs-tools
composer config allow-plugins.dealerdirect/phpcodesniffer-composer-installer true
composer require --dev moodlehq/moodle-cs

cd /Users/moskaliuk/Documents/Code/local_elediaai_sources
/tmp/local-elediaai-sources-moodle-cs/vendor/bin/phpcs \
    --standard=moodle \
    --extensions=php \
    '--ignore=public/local/elediaai_sources/tests/fixtures/*' \
    public/local/elediaai_sources
```

Style-only-Probleme automatisch beheben:

```bash
/tmp/local-elediaai-sources-moodle-cs/vendor/bin/phpcbf \
    --standard=moodle \
    --extensions=php \
    '--ignore=public/local/elediaai_sources/tests/fixtures/*' \
    public/local/elediaai_sources
```

Der aktuelle Branch besteht `phpcs --standard=moodle`.

### Frontend-Checks

Moodles Grunt-Tooling laeuft in einem Moodle-Checkout mit Node `>=22.11 <23`.
Aus dem Plugin-Verzeichnis innerhalb dieses Checkouts:

```bash
npx grunt amd --no-color
npx grunt rawcss --no-color
```

`amd` fuehrt `ignorefiles`, `eslint:amd` und `rollup` aus und erzeugt
`amd/build/*.min.js` neu. `rawcss` fuehrt Stylelint fuer CSS-Dateien aus.

Dieses Plugin hat aktuell keine Mustache-Templates und keine gebuendelten
Third-Party-Libraries. Mustache- und Third-Party-Library-Checks sind daher im
Moment nicht anwendbar. Wenn spaeter Templates oder gebuendelte Libraries
hinzukommen, gehoeren die entsprechenden Moodle-Prechecks vor die Submission.

## Dokumentation

```text
docs/user_manual.md        — Handbuch fuer Administration und Nutzung (auch auf der Hilfeseite des Plugins)
docs/api-specification.md  — der Aufnahme-API-Vertrag (v1.2), den ein Ziel erfuellen muss
docs/submission-draft.md   — Notizen fuer die Einreichung im Moodle-Plugin-Verzeichnis
```

## Privacy

Das Plugin sendet extrahierte Kursinhalte und Modul-Metadaten an einen externen
RAG-Service. Der Privacy-Provider deklariert diese externe Location inklusive:

- Site URL
- Course ID
- Course Module ID
- extrahierter Inhalt

Das Plugin speichert keine personenbezogenen Inhaltsdatensaetze. Es speichert
einen einzigen Personenbezug: `local_elediaai_sources_cm.usermodified`, also
wer die Auswahl einer Aktivitaet zuletzt geaendert hat. Diese Tabelle und ihre
Felder sind im Privacy-Provider deklariert.

Extractors sind dafuer verantwortlich, persoenliche Lernendenabgaben zu
vermeiden, sofern kein spaeteres Feature dieses Verhalten explizit einfuehrt und
dokumentiert.

## Capabilities

| Capability | Kontext | Standardrollen | Zweck |
|---|---|---|---|
| `local/elediaai_sources:reindex` | System | manager | Manueller Reindex: freigegebene Kurse sammeln einplanen oder einen Kurs per ID neu aufnehmen |
| `local/elediaai_sources:selectactivities` | Modul | editingteacher, manager | Je Aktivitaet entscheiden, ob sie als Quelle dient — Kursseite, Formularfeld und Webservice pruefen sie |

Zwei Capabilities mit Absicht: Einen *Kurs* freizugeben ist eine
administrative Entscheidung und bleibt bei `moodle/site:config` und den
zentralen Einstellungen, waehrend die Wahl der *Aktivitaeten* innerhalb eines
freigegebenen Kurses dem gehoert, der ihn unterrichtet.

Beide sind wirkungslos, solange **Lock course marking** aktiv ist: Waehrend
einer Pilotphase sind Formularfeld, Kursseite und Webservice nicht verfuegbar,
sodass der Umfang der indexierten Inhalte zentral gesteuert wird.

## Lizenz

GNU GPL v3 oder spaeter.

## Autor

Christopher Reimann, eLeDia GmbH.

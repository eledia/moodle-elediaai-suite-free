# Datenflüsse: Ingestion (`local_elediaai_sources`)

> **Stand:** 11.09.2026 · Branch `52` · Plugin-Version `2026090613`
> **Vertragsbindung:** `docs/api-specification.md` v1.2 (Juni 2026)
> **Moodle:** 4.5 – 5.2
> **Zweck:** Technische Referenz der Datenflüsse beim Ingest von Kursinhalten.
> Dient als Zulieferdokument für AVV-Anlage (Art. 28 DSGVO), VVT-Baustein
> (Art. 30), TOM-Dokumentation (Art. 32) und DSFA-Zuarbeit (Art. 35).
> **Nicht Gegenstand:** der Abfrage-/Inferenzpfad (Chat), siehe Abschnitt 12.

Jede Aussage in diesem Dokument ist aus dem Code des genannten Standes
abgeleitet; Fundstellen sind in Klammern angegeben. Abweichungen zwischen
Code und Spezifikation sind nicht geglättet, sondern in Abschnitt 13
ausgewiesen.

---

## 1 Überblick

Das Plugin ist die **Quellenschicht** der eLeDia.ai-Suite: Es entscheidet,
welche Moodle-Inhalte als Wissensquelle verwendet werden dürfen, extrahiert
sie, normalisiert sie und übergibt sie an genau **ein** konfiguriertes Ziel
(„Sink"). Es hält selbst **keine Kopie der Inhalte** vor — lokal verbleiben
nur Auswahlentscheidungen und ein Fingerprint (siehe 3.4).

```text
Auslöser (Event / Lehrenden-Auswahl / Zeitplan / manuell)
   → observer → Moodle-Ad-hoc-Task (Cron)
   → course_gate      (darf der Kurs verwendet werden?)
   → activity_gate    (ist die Aktivität ausgewählt?)
   → Sichtbarkeitsprüfung für Lernende
   → aisourcesextractor_* (Extraktion je Aktivitätstyp)
   → Normalisierung, Größenlimit, deterministische Dokument-ID, Hash
   → sink (aktives Ziel) → api_client (HTTP)
   → LiteRAG (lokal) | Ingestion API (extern) | OERWEAVE (reserviert)
```

Zwei Grundsätze prägen jeden Fluss:

- **Opt-in auf zwei Stufen:** Ein Kurs muss zentral oder per Kursfeld
  freigegeben sein; innerhalb eines freigegebenen Kurses entscheiden
  Lehrende je Aktivität (README „Choosing sources").
- **Konvergenz statt Momentaufnahme:** Der Index soll dem Sichtbarkeits-
  und Auswahlzustand folgen. Abwahl, Verbergen oder Löschen führen zu
  aktiver Entfernung aus dem Ziel, nicht nur zum Auslassen (Abschnitt 8).

## 2 Beteiligte Systeme und Zielszenarien

Es ist immer genau **ein** Ziel aktiv (`sink_manager`); ein Zielwechsel ist
möglich und stößt Re-Ingest an (Abschnitt 8.3). Die datenschutzrechtliche
Bewertung unterscheidet sich je Ziel grundlegend:

| Szenario | Ziel | Datenpfad | Externer Empfänger |
|---|---|---|---|
| **A — LiteRAG** | `literag_sink` | Loopback auf dieselbe Moodle-Instanz: `local/literag/ingest.php`, URL aus `wwwroot` abgeleitet | **Keiner.** Inhalte verlassen den Moodle-Server beim Ingest nicht; Ablage in den Moodle-DB-Tabellen `local_literag_sources` / `local_literag_chunks`; Retrieval per DB-Volltextsuche **ohne Embeddings** (LiteRAG adr02), daher auch kein Embedding-Dienstleister |
| **B — Ingestion API** | `ingestion_api_sink` | HTTP(S) an konfigurierte Basis-URL + feste Pfade `/documents/upsert`, `/documents/delete`, `/health` | Externer RAG-Service: dekodiert, parst, chunkt, **embeddet** und speichert Vektoren samt Metadaten in Qdrant (api-specification.md „Data Flow Summary"). Betreiber, Hosting-Standort und ggf. dessen Embedding-Provider sind **außerhalb** des Plugin-Scopes und je Einsatz in der Betriebsdokumentation zu erfassen |
| — OERWEAVE | reserviert | — | nicht implementiert |

Konsequenz für die rechtliche Dokumentation: Für Szenario A genügt die
Betrachtung der lokalen Verarbeitung; für Szenario B sind zusätzlich
Empfänger, Unterauftragsverhältnis, Speicherort und ggf. Drittlandtransfer
des RAG-Service zu dokumentieren. Beides gehört als Fallunterscheidung in
AVV und VVT, nicht als Fußnote.

## 3 Datenkategorien

### 3.1 Übertragene Inhalte

Extrahiert wird ausschließlich **Kursinhalt** aus freigegebenen Kursen über
17 gebündelte Extraktoren (README „Bundled Extractors"). Bewusste
Ausschlüsse auf Extraktorebene:

| Aktivität | Erfasst | Ausdrücklich nicht erfasst |
|---|---|---|
| Aufgabe (assign) | Beschreibung, Aktivitätsanweisungen, Bewertungskriterien | **Keine Abgaben von Lernenden** |
| Feedback | Fragen-/Item-Definitionen | **Keine eingereichten Antworten** |
| Datenbank (data) | Felddefinitionen und **nur freigegebene** Einträge | nicht freigegebene Einträge |
| Glossar | Beschreibung, **nur freigegebene** Einträge, Aliase | nicht freigegebene Einträge |
| Wiki | Einführung, Subwiki-Seiten | — |
| Test (quiz) | Fragen, Antworten, Feedback, Hinweise | Antworten/Versuche von Lernenden |
| übrige (book, page, label, lesson, folder, resource, scorm, imscp, h5pactivity, videotime, workshop) | redaktioneller Inhalt inkl. PDF-Dateien, Untertitel/Transkripte (`.vtt`/`.srt`) | — |

Nie indexiert werden außerdem: die Startseite (site course), gelöschte oder
für Lernende verborgene Module, nicht freigegebene Kurse, Modultypen ohne
Extraktor, leere Aktivitäten, übergroße Binärdateien (README „What Gets
Indexed", `ingestion_manager::gate_verdict()`).

### 3.2 Personenbezug in Inhalten — Einordnung

Der Payload enthält **keine Nutzerkennungen** (siehe 3.3), und die
Extraktoren für nutzererstellte Inhalte (data, glossary, wiki) übertragen
**keine Autorenfelder** (weder Name noch User-ID; per Code-Durchsicht
geprüft). Dennoch kann Personenbezug entstehen, und zwar auf zwei Wegen:

1. **Redaktionell:** Lehrende schreiben personenbezogene Daten in
   Kursinhalte (Kontaktangaben, Namen in Beispielen, Sprechzeiten).
2. **Nutzererstellt im Freitext:** Freigegebene Glossareinträge,
   Wiki-Seiten und Datenbankeinträge sind von Nutzenden verfasste Texte;
   ihr Inhalt kann Personenbezug tragen, auch ohne Autorenfeld
   (Schreibstil, Selbstnennung, Nennung Dritter).

Bewertung für VVT/DSFA: Die Verarbeitung ist auf Inhalte gerichtet, nicht
auf Personen; Personenbezug ist möglich, aber weder Zweck noch
Regelbestandteil. Die Freigabe-Mechanik (nur „approved" bei data/glossary)
und der Ausschluss von Abgaben/Antworten sind die technischen Maßnahmen,
die den Personenbezug minimieren — sie gehören als solche in die
TOM-Anlage.

### 3.3 Übertragene Metadaten (Payload je Dokument)

Aufbau in `ingestion_manager::build_payload()`, Vertrag in
api-specification.md:

| Feld | Inhalt | Personenbezug |
|---|---|---|
| `source_id` | `{tenant}:course{id}:cmid{id}[:{suffix}]`, deterministisch abgeleitet, nie gespeichert konfiguriert | nein |
| `content` | Base64-kodierter Dokumentinhalt | möglich (siehe 3.2) |
| `content_type` | `text/plain`, `text/html` oder `application/pdf` | nein |
| `qdrant_metadata.tenant_id` | aus `wwwroot` abgeleitete Mandantenkennung | nein |
| `qdrant_metadata.site_url` | rohes `wwwroot` der Instanz | nein |
| `qdrant_metadata.course_id` | Moodle-Kurs-ID | nein |
| `qdrant_metadata.cmid` | Kursmodul-ID | nein |
| `qdrant_metadata.module_url` | Direktlink auf die Aktivität (für Zitate im Chat) | nein |
| `parser_options` | immer `null` | nein |

Es werden **keine** Aktivitätstyp-, Nutzer-, Rollen- oder Einschreibedaten
übertragen.

### 3.4 Lokal gespeicherte Daten (Moodle-DB des Kunden)

Drei Tabellen (`db/install.xml`); das Plugin hält **keinen Volltext** der
Inhalte vor, nur einen SHA1-Fingerprint („keine Zweitkopie" —
`ingestion_manager::preview_module()`-Doku):

| Tabelle | Zweck | Personenbezug |
|---|---|---|
| `local_elediaai_sources_course` | Indexzustand je Kurs: `ingested`, `pending`, Ziel-ID, Embedding-Modell, Tenant, Zeitstempel | keiner |
| `local_elediaai_sources_cm` | **explizite** Auswahlentscheidung je Aktivität: `included`, `usermodified`, `timemodified` | **`usermodified`** — wer zuletzt entschieden hat (Lehrende/Manager) |
| `local_elediaai_sources_cmstate` | Indexwahrheit je Modul: Ziel, `sourceid` (friert den Tenant ein), `contenthash` (SHA1), Status, Fehlversuche, `lasterror`, Zeitstempel | keiner |

`usermodified` ist der **einzige** direkte Personenbezug, den das Plugin
selbst speichert; er betrifft Lehrende/Manager, nicht Lernende.

### 3.5 Zugangsdaten und Konfiguration

- `sink_ingestionapi_apikey`: Geheimnis **pro Mandant**, als `X-API-Key`
  gesendet; Ausgabe/Registrierung erfolgt serverseitig je Kunde
  (api-specification.md „Authentication").
- Bei LiteRAG wird der Ingestion-Key aus `local_literag` gelesen, nicht
  dupliziert (`literag_sink::apikey()`).
- `allow_private_target`: bewusster Admin-Opt-in, der Moodles
  cURL-Schutz vor privaten/loopback-Zielen aufhebt (`ignoresecurity`);
  nur für interne Docker-/Service-Ziele gedacht (`api_client`).

## 4 Auslöser

Alle Wege münden in Ad-hoc-Tasks, die per Cron laufen; kein Ingest
geschieht synchron in einer Nutzer-Anfrage.

| Auslöser | Weg | Wirkung |
|---|---|---|
| Modul angelegt / geändert / gelöscht | Events → `observer` (`db/events.php`) | Ingest bzw. Delete des Moduls |
| Unterinhalte geändert (Buchkapitel, Glossareinträge, Lektionsseiten, Wiki-Seiten, Datenbankeinträge, Testaufbau, Fragensammlung) | modspezifische Events → `observer` | Re-Ingest des betroffenen Moduls; Fragenänderung trifft jeden referenzierenden Test |
| Lehrende wählen Aktivität ab | Kursseite „AI Sources" / Bearbeitungsformular / Webservices `set_activity_selection[_bulk]` (Capability `selectactivities`) | **präfix-basierter Delete** im Ziel, nicht bloßes Auslassen |
| Kursfreigabe geändert (Kursfeld, Kategorien, Pilotliste) | `course_created/updated` → Reconcile | Re-Index bei Freigabe, **Purge** bei Entzug |
| Kurs gelöscht | `course_deleted` → `observer` | Dokumente werden entfernt, solange die Modul-IDs noch lesbar sind |
| Site-Default wechselt auf Opt-out | `converge_undecided_task` (einmalig, fächert reguläre Ingest-Tasks auf) | unentschiedene Aktivitäten kommen in den Index; Wechsel auf Opt-in löscht **nichts** Bestehendes |
| Täglich 03:xx | `reconcile_all_task` (geplant) | Sicherheitsnetz: gleicht Kurse ab, deren Markierung und Indexstand auseinanderlaufen |
| Wöchentlich So 04:xx | `cleanup_task` (geplant) | Konvergenz für Fälle **ohne** Event: verborgene Module, verwaiste Indexinhalte |
| Manuell | Statuspanel / `reindex.php` (Capability `reindex`, Standard: Manager) | Bulk-Queue freigegebener Kurse oder Re-Index eines Kurses per ID |
| Zielwechsel-Aufräumen | `purge_old_sink_task` (explizite Admin-Aktion) | leert einen Kurs im **verlassenen** Ziel; siehe 8.3 |

## 5 Verarbeitungskette im Plugin

Reihenfolge je Modul (`ingestion_manager`):

1. **Gates:** Kursfreigabe → Aktivitätsauswahl → Lernenden-Sichtbarkeit.
   Ergebnis ist eines von *ingest*, *delete*, *skip* (`gate_verdict()`).
   Die Sichtbarkeit wird bewusst über `visible` + Abschnittssichtbarkeit
   geprüft, nicht über `uservisible`: geplante Tasks laufen als Konto mit
   `viewhiddenactivities` — was Lernende nicht sehen, darf der Tutor
   nicht zitieren.
2. **Extraktion** durch den zuständigen `aisourcesextractor_*`.
3. **Normalisierung:** H5P-Platzhalter in HTML werden zu Text aufgelöst;
   der Aktivitätsname wird als Überschrift vorangestellt, damit jeder
   Chunk seiner Aktivität zuordenbar bleibt.
4. **Typ- und Größenprüfung:** nur die drei erlaubten MIME-Typen;
   Limit standardmäßig 20 MB — Text wird **gekürzt und gesendet**,
   übergroße Binärdateien (PDF) werden **übersprungen**.
5. **Änderungserkennung:** SHA1 über die finale Sendeform; unveränderte
   Inhalte (gleicher Hash, gleiches Ziel, gleiches Embedding-Modell,
   gleiche `source_id`) werden ohne HTTP übersprungen.
6. **Versand:** Base64-Payload → aktiver Sink → `api_client`.
7. **Zustandsschreibung:** Erfolg/Fehler in `_cmstate`; nach Ausschöpfen
   des Fehlversuchsbudgets wird das Modul bis zu einem erzwungenen
   Re-Index ausgelassen, damit ein defektes Dokument den Kurs nicht
   dauerhaft blockiert.

Mehrdokument-Module (z. B. Verzeichnisse) senden zuerst **ein**
Probedokument; erst bei Erfolg wird der alte Dokumentsatz per
Präfix-Delete geräumt und der neue vollständig gesendet — ein fehlendes
Ziel hinterlässt so nie eine Sichtbarkeitslücke (`ingest_multi()`).

Die Vorschau (`preview.php`) zeigt exakt die Sendeform („wire form"),
ruft aber nie das Ziel auf und schreibt keinen Zustand; Inhalte werden
dort nur zur Anzeige berechnet, nicht gespeichert.

## 6 Übertragung und Empfänger

### 6.1 Transport (beide Ziele, `api_client`)

- JSON über HTTP(S); Authentisierung per `X-API-Key`-Header.
- Timeout konfigurierbar, Standard 30 s (Connect ≤ 10 s).
- POST: höchstens **zwei Versuche** mit 1 s Pause; kein Retry bei 4xx;
  längere Ausfälle fängt Moodles Task-Retry ab. **Achtung:** Spec v1.2
  nennt „bis zu 3 Versuche, Backoff 1 s/2 s" — Abweichung, siehe 13.1.
- Health-Probe: einzelner GET mit kurzem Timeout (≤ 5 s), kein Retry.
- Docker-Sonderfall: Zeigt `wwwroot` auf localhost, werden Aufrufe an die
  eigene Instanz über `host.docker.internal` geroutet (nur lokale
  Entwicklungsumgebungen; hebt den cURL-Schutz nicht auf).

### 6.2 Ziel LiteRAG (Szenario A)

Empfänger ist `local/literag/ingest.php` **derselben** Instanz; die URL
wird aus `wwwroot` abgeleitet, es gibt nichts zu konfigurieren und nichts
zu vertippen. Der Schlüssel ist LiteRAGs eigener `ingest_api_key`.
Ablage: `local_literag_sources` / `local_literag_chunks` in der
Moodle-Datenbank; Retrieval per DB-Volltextsuche, **kein** Vektorstore,
**kein** Embedding-Modell (adr02; `literag_sink::embedding_model()`
liefert `null`). Für die rechtliche Bewertung: kein zusätzlicher
Empfänger, kein Unterauftragsverarbeiter allein durch den Ingest.

### 6.3 Ziel Ingestion API (Szenario B)

Empfänger ist der extern betriebene RAG-Service unter der konfigurierten
Basis-URL. Serverseitig laut Vertrag: Base64-Dekodierung, Parsing nach
`content_type`, Chunking, **Embedding-Erzeugung**, Ablage der Vektoren
samt `qdrant_metadata` in Qdrant. Pflichten des Service (Vertrag v1.2):
Key-Prüfung je Request, **Key↔Tenant-Verifikation bei jedem Upsert**
(Abweisung mit 403 bei Mismatch), Mandantenfilter bei jeder Abfrage,
idempotenter Upsert/Delete, Präfix-Delete-Semantik.

Je Einsatz zusätzlich zu dokumentieren (außerhalb dieses Dokuments):
Betreiber und Hosting-Standort des Service, dessen Embedding-Provider
und Modell, Speicherort der Qdrant-Instanz, Löschnachweis.

## 7 Mandantentrennung

- Der Mandant wird aus `wwwroot` **abgeleitet, nie konfiguriert**
  (`tenant::id()`): Host kleingeschrieben, ggf. Pfad, reduziert auf
  `[a-z0-9._-]`. Beispiel: `https://Example.com/Lms/` → `example.com-lms`.
- Der RAG-Service leitet zur Abfragezeit dieselbe Identität aus der
  **verifizierten** `site.url` des Moodle-Token-Callbacks ab — Schreib-
  und Lesepfad können konstruktionsbedingt nicht auseinanderlaufen.
- API-Keys werden **pro Kunde** ausgegeben; der Service verifiziert
  Key↔Tenant bei jedem Upsert. Damit kann ein fehlkonfigurierter Klon
  (z. B. Staging mit kopiertem Key) nicht in den Korpus eines anderen
  Mandanten schreiben.
- `sourceid` in `_cmstate` friert den Tenant zum Ingest-Zeitpunkt ein,
  damit spätere Deletes exakt bleiben; ein `wwwroot`-Wechsel ist als
  **Mandantenmigration** zu behandeln (umbenennen oder neu indexieren).
- Randfall: Ohne auswertbaren Host fällt die Ableitung auf `default`
  zurück (`tenant::from_url()`) — praktisch nur bei Fehlkonfiguration;
  die serverseitige Key↔Tenant-Prüfung fängt Kollisionen ab.

Diese Mechanik ist die zentrale TOM zur Mandantentrennung und ist über
`tests/tenant_test.php` und die Vertragspflichten prüfbar formuliert.

## 8 Löschung, Lebenszyklus, Aufbewahrung

### 8.1 Ereignis → Wirkung

| Ereignis | Wirkung im Ziel | Wirkung lokal |
|---|---|---|
| Modul gelöscht | Präfix-Delete (Modul + alle Teildokumente) | `_cmstate`-Zeile entfällt |
| Aktivität explizit abgewählt | Präfix-Delete | Entscheidung bleibt in `_cm` |
| Modul für Lernende verborgen | Delete, sofern indexiert; sonst Skip | `_cmstate`-Zeile entfällt |
| Kursfreigabe entzogen | `purge_course()`: Präfix-Delete je Modul | Zustände für dieses Ziel entfallen |
| Kurs gelöscht | Dokumente werden entfernt, solange Modul-IDs lesbar | Zustände entfallen |
| Inhalt geändert | idempotenter Upsert ersetzt (kein Duplikat) | Hash/Zeitstempel aktualisiert |
| Site-Default auf Opt-in umgestellt | **kein** Delete — nur Stopp für Unentschiedenes | — |

Deletes sind idempotent (Vertrag: 200 bei Nichtvorhandensein); die
Präfix-Regel mit `:`-Grenze verhindert, dass `cmid99` versehentlich
`cmid990` trifft.

### 8.2 Aufbewahrung

Es gibt **keine TTL**: Inhalte verbleiben im Ziel, bis eines der obigen
Ereignisse den Delete auslöst. Lokal verbleiben Auswahlentscheidungen
(`_cm`) unbegrenzt — auch nach Modullöschung erst durch die wöchentliche
Konvergenz bzw. Kursbereinigung — und Zustandszeilen bis zu Delete/Purge.
Für das Löschkonzept heißt das: Die Aufbewahrungsdauer der Inhalte im
Ziel ist funktional an die Existenz und Freigabe des Quellmaterials
gekoppelt, nicht an eine Frist.

### 8.3 Zielwechsel

Ein Zielwechsel lässt jeden Kurs divergieren; die Reconciliation
re-ingestiert ins neue Ziel. Das **alte Ziel wird nicht automatisch
geleert** — das Aufräumen ist eine explizite Admin-Aktion über
`purge_old_sink_task`, die das verlassene Ziel adressiert und bei
Teilfehlern über Moodles Task-Retry erneut läuft. Begründung im Code:
Zum Wechselzeitpunkt ist das alte Backend oft unerreichbar, und ein
scheiternder Purge darf den Wechsel nicht blockieren. Für das
Löschkonzept ist der Purge des Altziels damit ein **dokumentations-
pflichtiger manueller Schritt** im Wechselprozess (siehe 13.3).

### 8.4 Grenze zu Betroffenenrechten

Moodle-Datenanfragen (Auskunft/Löschung einzelner Personen) propagieren
**nicht** in den Index — und müssen es für Inhalte auch nicht, weil keine
personenbezogenen Dokumente je Nutzer indexiert werden. Wo Freitext mit
Personenbezug im Index steht (3.2), ist der Korrekturweg die Änderung
oder Abwahl des Quellinhalts, die den Upsert/Delete auslöst. Offen ist
die Behandlung von `usermodified` (13.2).

## 9 Protokollierung

- **Cron-/Task-Ausgabe (`mtrace`):** je Vorgang `source_id`,
  `content_type`, Größe in KB, HTTP-Code sowie Kürzungshinweise —
  **keine Inhalte**.
- **`_cmstate`:** letzter Status, Fehlversuchszähler, `lasterror`
  (Fehlermeldungstext), Zeitstempel; `contenthash` ist ein SHA1 über die
  Sendeform, aus dem der Inhalt nicht rekonstruierbar ist.
- **`debugging()`:** endgültig gescheiterte Requests mit URL und
  HTTP-Code (nur bei Entwickler-Debugging sichtbar).
- Aufbewahrung der Cron-Logs folgt der Moodle-Instanz, nicht dem Plugin.

## 10 Sicherung, Wiederherstellung, Duplizierung

Beim Kurs-Backup wandern die **Auswahlentscheidungen** mit; der
**Indexzustand** wandert nicht — er beschreibt, was *diese* Instanz an
*ihr* Ziel gesendet hat (`backup/moodle2/*`). Ein wiederhergestellter
oder duplizierter Kurs wird also nach seinen mitgebrachten
Entscheidungen neu ingestiert, sobald er freigegeben ist.

## 11 Abgleich mit dem Privacy-Provider

`classes/privacy/provider.php` deklariert die Tabelle `_cm`
(`usermodified`, `included`, `timemodified`) und die externe Übermittlung
`rag_service` mit `site_url`, `course_id`, `cmid`, `module_url`,
`content`. Das deckt sich mit dem tatsächlichen Payload — mit zwei
Ausnahmen, die in Abschnitt 13 als offene Punkte geführt werden
(fehlendes `tenant_id`-Feld in der Deklaration; nur Metadata-Provider
ohne Export-/Löschimplementierung für `usermodified`).

## 12 Abgrenzung: Abfrage-/Inferenzpfad

Dieses Dokument endet dort, wo Inhalte **im Ziel liegen**. Der rechtlich
eigenständig zu dokumentierende Abfragepfad (Prompt der Lernenden +
Retrieval + LLM-Aufruf + Sitzungs-/Verlaufsspeicher) umfasst u. a.:

- LiteRAG-Abfrageseite: `local_literag_conversations`, `_messages`,
  `_query_log`, `_memory`, `_topics` — dort entstehen **äußerungs- und
  personenbezogene** Daten von Lernenden;
- die Chat-Engine (`local_elediaai_chatengine`, DEL-517) und ihre
  Placements;
- beim RAG-Backend: Token-Verifikation (`moodle_verify_user_context`),
  LLM-Provider, Sitzungsablage (Redis/LangGraph).

Der Abfragepfad ist datenschutzrechtlich der kritischere Fluss und
braucht ein eigenes Dokument gleicher Machart.

## 13 Offene Punkte und Abweichungen

| Nr. | Befund | Fundstelle | Vorschlag |
|---|---|---|---|
| 13.1 | Retry-Verhalten weicht vom Vertrag ab: Code macht max. **2 Versuche** mit 1 s Pause, Spec v1.2 verspricht „bis zu 3 Versuche, Backoff 1 s/2 s" | `api_client::MAX_RETRIES` vs. api-specification.md „Retry Behaviour" | eine Seite angleichen; Doku folgt dem Code |
| 13.2 | Privacy-Provider ist nur Metadata-Provider: für `usermodified` (personenbezogen) fehlen Export- und Löschimplementierung der Moodle-Privacy-API (Betroffenenrechte der Lehrenden) | `classes/privacy/provider.php` | Request-Provider ergänzen (writeout + delete für `_cm.usermodified`) |
| 13.3 | Zielwechsel leert das Altziel nicht automatisch; ohne den manuellen Purge verbleiben Inhalte unbegrenzt im verlassenen Ziel | README „Connection", `purge_old_sink_task` | Wechselprozess mit Purge-Schritt als Betriebsanweisung dokumentieren; Erinnerung im UI erwägen |
| 13.4 | Deklaration der externen Übermittlung führt `tenant_id` nicht auf, obwohl es Teil des Payloads ist | `provider.php` vs. `build_payload()` | Feld in der Privacy-Deklaration ergänzen (kein Personenbezug, aber Vollständigkeit) |
| 13.5 | Kursinhalte können trotz Minimierung Personenbezug im Freitext tragen (3.2); die Verantwortung liegt beim Kunden als Verantwortlichem | — | in AVV/Informationstexten ausdrücklich zuweisen; Hinweis in Lehrenden-Doku |
| 13.6 | Betreiber-, Standort- und Embedding-Provider-Angaben für Szenario B liegen außerhalb des Plugins | Abschnitt 6.3 | je Kundeninstallation in der Betriebsdokumentation erfassen; Vorlage ergänzen |

---

*Pflegehinweis: Dieses Dokument ist an Plugin-Version und Vertragsversion
im Kopf gebunden. Änderungen an Payload, Gates, Tasks, Löschpfaden oder
Sinks erfordern eine Aktualisierung; der Abschnitt 13 ist bei jedem
Release gegen den Code zu prüfen.*

# local_literag — UAT-Testanleitung

> **Zielgruppe:** technisch versierte Tester:innen / Admins. Diese Anleitung
> setzt Umgang mit Admin-Einstellungen, Kurs-Sichtbarkeiten und einfachen
> `curl`-Aufrufen voraus.
>
> Verweis: Melderegeln und Zugänge im UAT-Handbuch
> (public/local/elediaai_core/docs/uat-handbuch.md). Testinstanz:
> https://demo.eledia.ai gegen den im Issue genannten Tag.

## Was das Plugin macht

LiteRAG ist das RAG-Backend der KI-Suite: Es nimmt Kursinhalte per
Ingestion-Endpoint entgegen, findet zu einer Lernendenfrage passende Passagen
per klassischer Volltextsuche (ohne Embeddings/Vektor-DB) und filtert dabei
strikt nach der Moodle-Sichtbarkeit des Fragenden. Über einen MCP-Endpoint
(`mcp.php`) liefert es dem KI-Tutor (`block_elediaai_tutor`) fundierte Antworten
mit `[S#]`-Quellenbezug, optional angereichert um Live-Moodle-Daten. LiteRAG hat
**kein eigenes Lerner-Frontend** — die Chat-Oberfläche ist der Tutor-Block; hier
werden Endpoints, Retrieval-Verhalten und Admin-UI geprüft.

## Vorbereitung

- **Rollen:** Ein **Administrator**-Konto; ein **Teilnehmer:in**-Konto,
  eingeschrieben in einen Kurs mit ingestierten Inhalten.
- **Einstellungen:** *Website-Administration → Plugins → Lokale Plugins →
  LiteRAG* (`/admin/settings.php?section=local_literag`). Sechs Abschnitte:
  Verbindung, LLM, Retrieval, Live-Tools, Tool-Namen, Datenschutz.
- **Endpunkte** (oben im Abschnitt *Verbindung* zum Kopieren angezeigt):
  - Ingestion: `https://demo.eledia.ai/local/literag/ingest.php/documents/upsert`
  - MCP/Tutor: `https://demo.eledia.ai/local/literag/mcp.php`
  - Health: `https://demo.eledia.ai/local/literag/ingest.php/health`
- **Voraussetzungen für sinnvolle Antworten (uat06–uat09):**
  1. Im Abschnitt *Verbindung* ist ein **Ingestion-API-Key** gesetzt.
  2. Im Abschnitt *LLM* sind `llm_base_url`, `llm_api_key` und `llm_model`
     konfiguriert (ohne LLM keine Antworten).
  3. Über `local_ragingest` (oder direkten Upsert) ist mindestens ein Kurs
     ingestiert (siehe dessen UAT).
  4. Der Tutor-Block ist mit `ragserverurl` = obige MCP-URL verbunden.
- Für die Endpoint-Tests genügt ein Terminal mit `curl`; den Ingestion-Key
  bekommst du vom Admin (nicht im Issue).

## Testfälle

### uat01 — Settings-Seite mit sechs Abschnitten und Endpoint-URLs

**Rolle:** Administrator
1. `/admin/settings.php?section=local_literag` öffnen.
2. Die sechs Abschnitte durchgehen; oben im Abschnitt *Verbindung* die
   angezeigten Endpoint-URLs mit den echten Pfaden vergleichen.

**Erwartet:** Alle sechs Abschnitte (Verbindung, LLM, Retrieval, Live-Tools,
Tool-Namen, Datenschutz) erscheinen. Die angezeigten Upsert- und MCP-URLs zeigen
exakt auf `.../ingest.php/documents/upsert` bzw. `.../mcp.php`. Die Seite bleibt
auch ohne `local_lernhive` (Shell) voll bedienbar.

### uat02 — Handbuch in der richtigen Sprache

**Rolle:** Administrator
1. `/local/literag/help.php` öffnen (Nutzersprache Deutsch).

**Erwartet:** Das Handbuch erscheint als formatierter Text (aus
`02-user-doc.de.md`). Bei englischer Nutzersprache erscheint die EN-Fassung.
Ohne `local/literag:manage`-Berechtigung endet der Aufruf in einer
Moodle-„keine Berechtigung"-Meldung, nicht in einer leeren Seite.

### uat03 — Health-Endpoint erfordert gültigen Key

**Rolle:** Administrator (Terminal)
1. Ohne Key aufrufen:
   `curl -i https://demo.eledia.ai/local/literag/ingest.php/health`
2. Mit falschem Key: Header `-H "X-API-Key: falsch"`.
3. Mit dem echten Key.

**Erwartet:** Ohne/mit falschem Key antwortet der Endpoint mit `401` und
`{"status":"unauthorized"}`; ist gar kein Key konfiguriert, kommt `503`
`not_configured`. Erst mit dem korrekten Key liefert Schritt 3 `200`
`{"status":"ok"}`.

### uat04 — Idempotenter Upsert erzeugt keine Doppel-Chunks

**Rolle:** Administrator (Terminal oder via `local_ragingest`)
1. Denselben Kursinhalt zweimal ingestieren (z. B. in `local_ragingest` einen
   Pilotkurs zweimal reindexieren, ohne den Inhalt zu ändern).
2. Danach im Tutor eine Frage zu diesem Inhalt stellen und die Quellkarten
   zählen.

**Erwartet:** Der zweite Upsert liefert `200 ok`, erzeugt aber keine doppelten
Chunks (Content-Hash unverändert → kein Re-Chunk). Mehrere Passagen desselben
Moduls ergeben **eine** Quellkarte, nicht mehrere.

### uat05 — MCP-Endpoint: unautorisiert und Parse-Fehler

**Rolle:** Administrator (Terminal) — *nur relevant, wenn ein
`transport_auth_token` gesetzt ist*
1. `curl -i -X POST https://demo.eledia.ai/local/literag/mcp.php`
   mit leerem/falschem `Authorization: Bearer …`-Header und leerem Body.
2. Mit gültigem Bearer, aber kaputtem Body (`--data 'kein json'`).

**Erwartet:** Bei gesetztem Token und falschem/leerem Bearer kommt `401` mit
JSON-RPC-Fehler `-32001 unauthorized`. Bei gültigem Bearer und ungültigem JSON
kommt ein JSON-RPC-Fehler `-32700 parse error`. Der Header
`MCP-Protocol-Version: 2025-06-18` ist gesetzt.

### uat06 — Fundierte Antwort mit Quellenbezug (E2E über den Tutor)

**Rolle:** Teilnehmer:in
1. Im Kurs mit ingestierten Inhalten den KI-Tutor öffnen.
2. Eine Frage stellen, deren Antwort eindeutig im Kursmaterial steht.

**Erwartet:** Die Antwort bezieht sich beobachtbar auf das Material und trägt
`[S#]`-Marker; darunter stehen nummerierte Quellkarten, die 1:1 zu den
`[S#]`-Markern passen. Es werden keine Aussagen ohne Beleg erfunden.

### uat07 — Sichtbarkeitsgrenze: verstecktes Modul bleibt unsichtbar

**Rolle:** Administrator + Teilnehmer:in
1. Als Admin ein ingestiertes Kursmodul auf „verborgen" stellen (bzw. ein Modul
   wählen, das die Testperson nicht sehen darf).
2. Als Teilnehmer:in gezielt nach Inhalt aus genau diesem Modul fragen.

**Erwartet:** Der Tutor gibt keinen Inhalt aus dem versteckten/unsichtbaren
Modul preis und zitiert es nicht als Quelle — auch dann nicht, wenn der Chunk
inhaltlich passen würde. Sichtbare Module werden weiterhin normal zitiert.

### uat08 — Antwort-Stil Hint und Quiz *(automatisiert nur teilweise abgedeckt)*

**Rolle:** Teilnehmer:in
1. Dieselbe fachliche Frage einmal im Modus **Hinweis/Hint**, einmal im Modus
   **Quiz** stellen (Modus-Umschaltung im Tutor).

**Erwartet:** Der Hint-Modus verrät die Lösung nachweislich **nicht**, sondern
gibt nur einen Denkanstoß; der Quiz-Modus stellt Gegenfragen statt die Antwort
zu liefern. Der gewählte Stil dominiert das Verhalten sichtbar.

### uat09 — Live-Moodle-Tools: read-only und Schreib-Vorschau *(E2E, automatisiert ungetestet)*

**Rolle:** Teilnehmer:in — *nur wenn `enable_mcp_tools` aktiv und der Tutor an
`webservice_elediamcp` gebunden ist*
1. Den Tutor nach eigenen Live-Daten fragen (z. B. „Welche Abgaben stehen bei
   mir an?" oder „Wann ist meine nächste Frist?").
2. Falls `enable_write_tools` aktiv ist: eine schreibende Aktion anstoßen (z. B.
   Nachricht senden) und die Vorschau abwarten, dann bestätigen.

**Erwartet:** Read-Anfragen liefern nur Daten, die diese Person in Moodle selbst
sehen dürfte. Eine schreibende Aktion wird in **einem** Turn nie ausgeführt: Es
erscheint zuerst eine Vorschau, gesendet wird erst nach ausdrücklichem „Ja" im
Folge-Turn. Ohne aktive Live-Tools degradiert der Chat zu RAG-only (keine
Fehlermeldung).

### uat10 — Notabschaltung wirkt sofort auf beide Endpunkte

**Rolle:** Administrator
1. Im Abschnitt *Verbindung* **Notabschaltung** (`emergency_disable`) aktivieren,
   speichern.
2. `.../ingest.php/health` (mit Key) und `.../mcp.php` (POST) aufrufen.
3. Danach eine Tutor-Frage stellen.
4. Notabschaltung wieder deaktivieren.

**Erwartet:** Bei aktiver Notabschaltung antworten beide Endpunkte mit `503`
(`ingest.php` → `{"status":"disabled"}`, `mcp.php` → JSON-RPC `-32000 service
disabled`); der Tutor kann keine fundierte Antwort mehr erzeugen. Nach dem
Deaktivieren funktioniert alles wieder.

### uat11 — Retention-Task läuft fehlerfrei

**Rolle:** Administrator
1. Den geplanten Task `local_literag\task\prune_logs` einmal manuell ausführen
   (CLI `admin/cli/scheduled_task.php` oder über tool_taskrunner „Jetzt starten").

**Erwartet:** Der Task läuft ohne Fehler durch und räumt Query-Log und
abgelaufene Konversationen gemäß den Retention-Settings; Secrets/Tokens tauchen
in keinem Log auf.

## Nicht testen / bekannt

Diese Punkte sind bekannt bzw. in Arbeit — bitte **keine** Bug-Meldungen dazu
anlegen:

- **Release-/CHANGELOG-Stand:** Release `0.6.4` und die Änderungen seit 0.5.2
  sind in README und CHANGELOG synchron dokumentiert.
- **Cross-DB- und CI-Nachweis (task04, q02, q04):** Seit 2026-08-04 erzeugt die
  Gruppe `rag` wieder eine Matrixzelle, und der Nachtjob `crossdb` prüft
  dieselbe Gruppe zusätzlich gegen MariaDB. Beide Datenbanken sind lokal
  nachgestellt grün (test03/test04); der erste Forgejo-Lauf folgt mit dem Merge
  nach `main`.
- **Testabdeckung:** Privacy, User-Erasure, Retention und Rate-Limits haben
  eigene Tests. `memory_store`, `reranker` und `llm\client` sind weiterhin nur
  indirekt abgedeckt.
- **Kein Lerner-Frontend:** LiteRAG ist reines Backend; die Chat-UI und ihre
  Bedienfehler gehören zum UAT von `block_elediaai_tutor`, nicht hierher.
- **Core-Moodle OpenAI-Provider (bug01, mitigiert):** Bestimmte Modelle
  (`gpt-5*`) können ohne Deploy-Hotfix abstürzen. Nur melden, wenn es auf dem
  aktuellen Tag der Testinstanz reproduzierbar auftritt.
- **KI-Inhaltsqualität:** Ob eine Antwort fachlich gut ist, ist nicht Gegenstand
  dieses UAT — nur ob sie erscheint, sich auf die Eingabe bezieht und korrekt
  zitiert.

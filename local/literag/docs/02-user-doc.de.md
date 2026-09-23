# eLeDia.ai LiteRAG — Hilfe und Handbuch

## Überblick

eLeDia.ai LiteRAG ist das Moodle-lokale RAG-Backend für den eLeDia.ai Tutor. Es
speichert indexierte Kursinhalte in der Moodle-Datenbank, sucht passende
Textstellen, prüft die Moodle-Sichtbarkeit der anfragenden Person und erzeugt
daraus mit einem OpenAI-kompatiblen Sprachmodell fundierte Tutor-Antworten mit
Quellen.

LiteRAG ist kein Chat-Frontend für Lernende. Lernende nutzen weiterhin den
eLeDia.ai Tutor im Kurs oder auf der Tutor-Seite. LiteRAG arbeitet im
Hintergrund als sicherer Dienst für Retrieval, Gesprächsverlauf, optionale
Langzeit-Memory und die Verbindung zum LLM.

## User value

LiteRAG sorgt dafür, dass Tutor-Antworten nicht nur aus allgemeinem
Modellwissen entstehen, sondern auf freigegebenen Moodle-Kursinhalten und den
Berechtigungen der lernenden Person basieren.

### Rollen und typische Aufgaben

| Rolle | Typische Aufgaben |
|---|---|
| Lernende | Stellen Fragen im eLeDia.ai Tutor und erhalten Antworten aus freigegebenen Kursinhalten. |
| Lehrende | Geben Kursinhalte für die Indexierung frei und prüfen, ob der Tutor kursbezogen antwortet. |
| Administrator/innen | Verbinden LiteRAG mit LLM, RAG-Ingest, MCP und dem Tutor; pflegen Datenschutz, Logging und Aufbewahrung. |

## Wie LiteRAG im Tutor-Setup arbeitet

LiteRAG verbindet drei Bereiche der eLeDia.ai Tutor-Landschaft:

| Komponente | Aufgabe |
|---|---|
| eLeDia.ai Tutor | Zeigt den Chat in Moodle und ruft das LiteRAG-Tool `tutor_chat` auf. |
| RAG-Ingest | Sendet freigegebene Moodle-Kursinhalte an LiteRAG. |
| LiteRAG | Speichert, sucht, filtert und beantwortet Fragen mit Quellen. |
| MCP | Stellt optional Live-Moodle-Tools bereit, zum Beispiel Kurse, Aufgaben oder Fristen. |

Der typische Ablauf:

1. RAG-Ingest liest freigegebene Kursinhalte aus Moodle.
2. RAG-Ingest sendet Text und Metadaten an den LiteRAG-Ingest-Endpunkt.
3. LiteRAG speichert Dokumente und Chunks in Moodle-Tabellen.
4. Eine lernende Person stellt im Tutor eine Frage.
5. Der Tutor ruft den LiteRAG-MCP-Endpunkt auf.
6. LiteRAG sucht relevante Chunks und filtert sie gegen Moodle-Berechtigungen.
7. LiteRAG sendet Frage und Kontext an das konfigurierte LLM.
8. Der Tutor zeigt die Antwort mit Quellen in Moodle an.

## Einrichtung

Öffnen Sie **Website-Administration > Plugins > Lokale Plugins > eLeDia.ai LiteRAG** oder nutzen Sie die LiteRAG-Kachel in der eLeDia.ai Tutor Plugin-Shell. Aktionen bleiben im Shell-Aktionsbereich, Tastaturfokus ist sichtbar und der Inhalt kann die konfigurierte Full-Shell-Breite nutzen.

### Verbindung

Im Bereich **Verbindung** werden die Endpunkte und Secrets gepflegt.

| Einstellung | Bedeutung |
|---|---|
| Ingest-API-Key | Gemeinsames Secret, das RAG-Ingest im Header `X-API-Key` mitsendet. |
| Tutor-Transport-Token | Optionales zusätzliches Bearer-Token für Tutor-Aufrufe. |
| Endpunkte deaktivieren | Not-Aus für Ingest- und Tutor-Endpunkt. |

Die Seite zeigt die Ziel-URLs an, die in RAG-Ingest und im Tutor eingetragen werden:

```text
/local/literag/ingest.php/documents/upsert
/local/literag/mcp.php
```

### LLM

Im Bereich **LLM (OpenAI-kompatibel)** wird das Modell angebunden.

Wichtige Felder:

- **API-Basis-URL**, zum Beispiel `https://api.openai.com/v1` oder ein LiteLLM-Proxy.
- **API-Key**, der nur serverseitig in Moodle gespeichert wird.
- **Modell**, zum Beispiel ein Chat-Completions-Modell.
- **Temperatur**, maximale Ausgabe-Token und Timeout.
- **Private/Loopback-LLM-Hosts erlauben** nur für vertrauenswürdige lokale oder Docker-Endpunkte aktivieren.

Wenn der Tutor keine Antwort erzeugt, prüfen Sie zuerst API-Key, Basis-URL, Modellname und Timeout.

### Retrieval und Chunking

Im Bereich **Retrieval & Chunking** wird gesteuert, wie viele Inhalte LiteRAG sucht und an das Modell übergibt.

| Einstellung | Wirkung |
|---|---|
| Chunk-Größe | Zielgröße gespeicherter Textabschnitte. |
| Chunk-Überlappung | Überlappung zwischen Chunks, damit Kontext erhalten bleibt. |
| Kandidaten-Chunks | Anzahl der Suchtreffer vor Berechtigungsfilterung. |
| Kontext-Chunks | Anzahl der Chunks, die dem LLM als Kontext übergeben werden. |
| LLM-Reranking | Optionales Nachsortieren der Suchtreffer durch das LLM. |
| pdftotext-Pfad | Optionaler Poppler-Pfad für bessere PDF-Textextraktion. |

Mehr Kandidaten und mehr Kontext können Antworten verbessern, erhöhen aber Latenz und Kosten.

### Live-Moodle-Tools

LiteRAG kann optional lesende Moodle-Tools aus MCP verwenden. Dadurch kann der Tutor neben indexierten Kursinhalten auch Live-Daten einbeziehen, etwa:

- aktuelle Kurse,
- Aufgaben und Abgabestatus,
- Fristen aus dem Kalender,
- Bewertungen,
- Fortschritt und Kursaktivitäten.

Live-Moodle-Tools benötigen `webservice_elediamcp`. Standardmäßig sind nur
lesende Tools sinnvoll. Schreibwerkzeuge bleiben an explizite
Zwei-Schritt-Bestätigungen gebunden und sollten nur bewusst aktiviert werden.

### Tool-Namen

Die Tool-Namen in LiteRAG müssen zu den Tool-Namen im Tutor passen.

Wichtige Defaults:

| Tool | Zweck |
|---|---|
| `tutor_chat` | Antwort auf eine Tutor-Frage erzeugen. |
| `tutor_get_history` | Gesprächsverlauf abrufen. |
| `tutor_delete_conversation` | Einzelnes Gespräch löschen. |
| `tutor_delete_user_data` | Nutzerdaten löschen. |
| `tutor_set_memory_optin` | Zustimmung zum Langzeitgedächtnis setzen. |

Ändern Sie diese Namen nur, wenn der Tutor entsprechend angepasst ist.

### Datenschutz, Logging und Aufbewahrung

LiteRAG unterstützt datensparsame Konfiguration.

Wichtige Optionen:

- Langzeitgedächtnis nur aktivieren, wenn der gewünschte Nutzungsfall und die Zustimmung klar geregelt sind.
- Query-Logging möglichst niedrig halten, wenn keine Detailanalyse notwendig ist.
- Aufbewahrungsfristen für Query-Logs und Gespräche passend zur Website-Policy setzen.
- Nach Änderungen an Datenschutztexten und Backend-Speicherung den Tutor-Consent prüfen.

Der Moodle Privacy Provider exportiert und löscht LiteRAG-Daten im Rahmen der Moodle-Datenschutzprozesse.

## Kursinhalte indexieren

LiteRAG indexiert Kurse nicht selbstständig aus der Oberfläche heraus. Dafür ist RAG-Ingest zuständig.

Empfohlener Ablauf:

1. LiteRAG-Ingest-API-Key setzen.
2. In RAG-Ingest die LiteRAG-Ingest-URL und denselben API-Key eintragen.
3. Kurse oder Aktivitäten für die Ingestion freigeben.
4. RAG-Ingest ausführen oder den geplanten Task abwarten.
5. Im Tutor-Dashboard prüfen, ob der Kurs indexiert ist.
6. Im Kurs eine fachliche Testfrage stellen.

Wenn ein Kurs noch nicht indexiert ist, kann der Tutor zwar allgemein antworten, aber keine geerdeten Antworten aus diesem Kurs liefern.

## Fehlerbehebung

### Der Tutor antwortet ohne Kursquellen

Prüfen Sie:

- Ist der Kurs durch RAG-Ingest indexiert?
- Sind Dokumente in LiteRAG angekommen?
- Übergibt der Tutor den Kurskontext?
- Hat die anfragende Person Zugriff auf Kurs und Aktivität?
- Sind genügend Kandidaten- und Kontext-Chunks konfiguriert?

### RAG-Ingest kann keine Dokumente senden

Prüfen Sie:

- Endet die Ingest-URL mit `/documents/upsert`?
- Ist `$CFG->slasharguments` in Moodle aktiv?
- Stimmen Ingest-API-Key in RAG-Ingest und LiteRAG überein?
- Ist **Endpunkte deaktivieren** ausgeschaltet?
- Ist LiteRAG aus Sicht des RAG-Ingest-Servers erreichbar?

### Der Tutor-Dienst ist nicht verfügbar

Prüfen Sie:

- Ist der LiteRAG-MCP-Endpunkt im Tutor korrekt eingetragen?
- Stimmen Tool-Namen im Tutor und in LiteRAG überein?
- Ist das optionale Tutor-Transport-Token korrekt?
- Funktioniert die LLM-Verbindung?
- Ist der Timeout ausreichend, aber kleiner als der Tutor-Timeout?

### Antworten sind langsam

Mögliche Ursachen:

- Zu viele Kandidaten- oder Kontext-Chunks.
- Aktiviertes LLM-Reranking.
- Langsamer LLM-Endpunkt.
- Live-Moodle-Tools mit vielen Tool-Runden.
- PDF-Texte oder große Kursinhalte mit ungünstigem Chunking.

Reduzieren Sie testweise Kandidaten, Kontext-Chunks oder Tool-Runden und prüfen Sie die Latenz erneut.

## Betrieb

- Nach Deployments Moodle-Caches leeren.
- Nach Änderungen an LLM, Retrieval oder Tool-Namen eine allgemeine und eine kursbezogene Testfrage stellen.
- Query-Logging und Aufbewahrung regelmäßig gegen die Datenschutzvorgaben prüfen.
- RAG-Ingest-Status und Kursindex nach größeren Kursänderungen kontrollieren.
- Bei LLM- oder Proxy-Wechsel API-Basis-URL, Modellname und Token-Limits prüfen.

## Weiterführende Dokumentation

- `README.de.md` im Plugin für Installation, CI und technische Details.
- `README.md` als englische Referenz.
- eLeDia.ai Tutor Handbuch für die Chat-Oberfläche und Tutor-Profile.
- RAG-Ingest Handbuch für Kursfreigabe und Indexierungsprozesse.

# Entwickler-Dokumentation

Dieses Dokument konsolidiert Architektur-, Integrations- und RAG-Server-Notizen
fuer `block_elediaai_tutor`.

---

## Architektur

### Plugin-Shell-CSS und Token-Scope

Die vendorte Fallback-Shell in `styles.css` scoped alle `lh-*`-Regeln und
Token-Deklarationen auf `.lh-plugin-shell`. Dadurch können Moodle-seitig
konkatinierte Plugin-Styles keine globalen `body`-/`:root`-Tokens mehr auf
fremde Seiten auswirken. `scripts/lint-vendored-shell.php` schützt diesen
Vertrag repo-weit für alle `public/**/styles.css`.

```text
Browser / AMD chat.js
  -> Moodle core/ajax external functions
  -> block_elediaai_tutor local services
  -> optional token_quota adapter
  -> webservice_elediamcp token provisioning
  -> RAG/Tutor MCP endpoint via server-side HTTP
```

Der Browser spricht nur mit Moodle. RAG-URL, RAG-Auth und ein optionales
Moodle-MCP-Token bleiben serverseitig. Der externe RAG-/Tutor-Dienst ist
MCP-Server fuer den Tutor; Moodle-MCP-Callbacks werden nur genutzt, wenn MCP
freigeschaltet ist.

### RAG-Authentifizierungstoken

`admin_setting_encryptedpassword` verschluesselt neue und rotierte Tokens mit
dem Site-Schluessel, bevor Moodle sie in `config_plugins` speichert.
`local\security::rag_auth_token()` entschluesselt den Wert ausschliesslich fuer
den serverseitigen RAG-Client und liefert bei beschaedigtem Ciphertext leer
zurueck. Der Upgrade-Savepoint `2026080102` verschluesselt einen vorhandenen
Klartextwert einmalig; leere und bereits verschluesselte Werte bleiben
unveraendert.

### Komponentenumbenennung

Der kanonische Plugin-Pfad ist `public/blocks/elediaai_tutor`, die Moodle-
Komponente `block_elediaai_tutor` und der Namespace `block_elediaai_tutor\`.
Aktive Verweise in Premium, LiteRAG, RAG-Ingest, elediamcp, LernHive und den
CI-Skripten müssen denselben Namen verwenden. `component_migration` läuft beim
Installieren eines umbenannten Bestands und als Upgrade-Savepoint. Sie erstellt
oder übernimmt die kanonischen `block_elediaai_tutor_*`-Tabellen, kopiert
Bestandszeilen ID-stabil und migriert Konfiguration, Blockregistrierung samt
Instanzen, Capability-Zuweisungen, Dateien, Tasks, externe Funktionen und die
LTM-Präferenz. Alt-Tabellen und Alt-Konfiguration werden erst nach erfolgreichem
Transfer entfernt; ein erneuter Lauf ist idempotent. Nur der technische
Service-Account `elediaai_tutor_service` bleibt als stabiler externer Username
erhalten.

---

## Wichtige Pfade

| Pfad | Zweck |
|---|---|
| `block_elediaai_tutor.php` | Moodle Block Entry. |
| `settings.php` | Siteweite Admin Settings. |
| `view.php` | Standalone-Tutor-Seite und Plugin-Shell-Vorschau. |
| `classes/external/` | Die AJAX-Endpunkte, die nur der Tutor hat: Einwilligung, Langzeitgedaechtnis, Copilot-Analyse, Datenloeschung. Der Chat selbst laeuft ueber die Endpunkte der Engine. |
| `classes/chatengine/placement.php` | Der Placement-Vertrag: Bereich, Zugang, Persona, Modus, Instanz-Budget. |
| `classes/local/registry.php` | Tutor/Branding Registry. |
| `classes/local/security.php` | Was der Block selbst konfiguriert (Chat-Bereiche, eigenes CSS). Verbindung und Grenzen liegen in der Engine. |
| `classes/local/copilot_service.php` | Fragenauswertung fuer Lehrkraefte, ueber den Backend-Vertrag der Engine. |
| `classes/local/tutor_io.php` | Tutor-Import/-Export und Bildvalidierung. |
| `amd/src/` | AMD-Quellen. |
| `amd/build/` | Gebaute AMD-Assets. |
| `templates/` | Mustache Templates. |
| `tests/` | PHPUnit Tests. |

---

## Frontend-Layout

Die Shell-Adapter verwenden fuer normale Tutor-Seiten `plugin_page::MODIFIER_DEFAULT`
(72rem). Die Help-Seite uebergibt weiterhin `MODIFIER_READING` (58rem). Die
genannten Shell-Seiten setzen das Moodle-Pagelayout `report`, damit Boost und
Boost Union nicht den `standard`-Cap von 830px anwenden. Kursbezogene
`incourse`- und eingebettete Layout-Zweige bleiben unveraendert.

`view.php` rendert den Chat in `.elediaai_tutor-page`. Ausserhalb der
Plugin-Shell behaelt dieser Wrapper die kompakte Maximalbreite von 860 px. Im
Shell-Kontext hebt `.lh-plugin-shell .elediaai_tutor-page` diese zweite Grenze
auf, weil bereits `.lh-plugin-shell` die kanonische Seitenbreite vorgibt. So
fuellen Header und Chat dieselbe Inhaltsbreite, ohne den globalen
Plugin-Shell-Token oder eingebettete Ansichten zu verbreitern.

Die gemeinsame Shell wird aus `design/lh-core.css` per
`php scripts/sync-design.php` in Tutor, Filter und LiteRAG gestempelt. Der
generierte Block liefert Zone-A-Aktionen, sichtbare Fokus-Ringe, 44px-
Icon-Hit-Areas und `.lh-plugin-content-area { max-width: 100%; }`; Tutor-eigene
`.eat-*`- und Launcher-Regeln bleiben ausserhalb des Markers.

---

## Externe Abhaengigkeiten

- Release 0.19.10, Beta.
- Moodle 4.5 bis 5.2: `$plugin->requires = 2024100700` und
  `$plugin->supported = [405, 502]`.
- PHP 8.3+.
- Moodle 4.2 bis 4.4 sind bewusst nicht unterstuetzt. Die produktive
  Integration nutzt die Hooks API; Legacy-Callback-Fallbacks werden nicht
  bereitgestellt.
- Optional `webservice_elediamcp` fuer echte Moodle-MCP-Token-Provisionierung.
- Optional `local_elediaai_core` fuer zentrale Stunden-/Tages-Tokenlimits. Die
  Integration prueft per `class_exists()` den Quota-Manager und delegiert nur
  bei vollstaendig vorhandenem Vertrag. Ohne das Plugin nutzt der Tutor eine
  lokale Schaetzung fuer die eigene Kosten-Governance, ueberspringt aber die
  gemeinsame Pruefung und Buchung ohne Fatal Error.
- RAG-/Tutor-MCP-Server mit Streamable HTTP und mindestens einem Chat-Tool.

Die lokale und entfernte CI prueft den Block auf den beiden Supportgrenzen:
Pushes und Pull Requests installieren die betroffenen Gruppen mit harten
Installations-, PHP-Lint- und PHPUnit-Gates auf Moodle 4.5 und 5.2. Der Tutor
laeuft dabei in einer Standalone-Gruppe ohne `local_elediaai_core` sowie in einer
separaten Vertragsgruppe mit installiertem `local_elediaai_core`; so werden beide
Abhaengigkeitsmatrizen ausgefuehrt. Nachtlaeufe und der Standard-Dispatch pruefen
zusaetzlich Moodle 5.1; Behat laeuft auf Moodle 5.2.
`tests/hook_callbacks_test.php` wird dabei in jeder gewaehlten PHPUnit-
Matrixzelle ausgefuehrt und deckt den Hooks-Vertrag auf jeder unterstuetzten
Version ab.

`local_lernhive` ist keine Runtime-Abhaengigkeit. Die Plugin-Shell verlinkt auf
die plugin-eigene `help.php`; LernHive kann dieselbe Dokumentation optional in
seinem Support-Hub rendern.

### Autoritative AJAX-Scope-Bindung

`classes/external/helper.php` bindet AJAX-Kontexte an den fachlichen Chat-
Scope. Systemkontexte gelten ausschliesslich fuer globale Unterhaltungen;
Kurskontexte muessen exakt zum Kurs passen. Blockkontexte werden gegen eine
reale `block_elediaai_tutor`-Instanz validiert und fuer Kurschat aus
`fixedcourseid` beziehungsweise dem Kurs-Parent plus `passcoursecontext`
aufgeloest. Ein fachfremder Blockkontext ist ungueltig.

Bei bestehenden Unterhaltungen stammt der Kurs immer aus der lokalen
Conversation-Metadatenzeile. `send_message`, `get_history`,
`get_conversations` und `clear_conversation` pruefen danach aktuellen
Kurszugriff, Kurs-Opt-in und die jeweilige Capability im gebundenen Kurs- oder
Tutorblockkontext. Der globale Verlauf filtert explizit auf globale
Unterhaltungen; er ist kein kursuebergreifender Sammel-Endpunkt.

---

## RAG-/Tutor-MCP-Protokoll

Der Block ist MCP-Client und ruft den konfigurierten RAG-/Tutor-Server per
JSON-RPC 2.0 `tools/call` ueber Streamable HTTP auf.

### Request

```http
POST <RAG MCP server URL>
Content-Type: application/json
Accept: application/json, text/event-stream
MCP-Protocol-Version: 2025-06-18
Authorization: Bearer <token>        # falls konfiguriert
```

Beispiel:

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "tools/call",
  "params": {
    "name": "tutor_chat",
    "arguments": {
      "system_url": "https://moodle.example.com",
      "moodle_token": "USER_SCOPED_MOODLE_MCP_TOKEN_IF_MCP_IS_ENABLED",
      "user_message": "What do I need to do this week?",
      "course_id": "42",
      "conversation_id": "optional-existing-id",
      "answer_style": "explain",
      "user_lang": "de",
      "rag_enabled": true
    }
  }
}
```

### Chat-Argumente

| Feld | Bedeutung |
|---|---|
| `system_url` | Moodle-`wwwroot` fuer Callbacks. |
| `moodle_token` | Optionales nutzerbezogenes Moodle-MCP-Token; geheim behandeln. Fehlt, wenn MCP im Tutor deaktiviert ist. |
| `user_message` | Validierte Nutzerfrage. |
| `course_id` | Optionaler Kurskontext. |
| `conversation_id` | Optional; fehlt bei neuer Unterhaltung. |
| `ltm_enabled` | Optionaler Consent-Status fuer Langzeitgedaechtnis. |
| `answer_style` | Server-seitig erzwungener Stil: `explain`, `hint`, `quiz`. |
| `user_lang` | Moodle-Sprachcode der nutzenden Person. |
| `rag_enabled` | Wenn `false`, darf der Server keine Retrieval-Wissensbasis nutzen. |
| `persona` | Optionale Persona-Anweisungen fuer Stimme und Tonalitaet. |

### Response

Der Block liest bevorzugt `result.structuredContent`, alternativ Textteile aus
`result.content[]`. Erkannte Felder:

| Konzept | Akzeptierte Keys |
|---|---|
| Antwort | `answer`, `text`, `message`, `response`, `content`, `output` |
| Conversation-ID | `conversation_id`, `conversationId`, `session_id`, `sessionId`, `thread_id` |
| Quellen | `sources`, `citations`, `documents`, `references` |
| Thema | `topic`, `subject` |

Quellen koennen Strings oder Objekte mit `title`/`url`/`snippet`-Aliases sein.
Antworten werden als Markdown behandelt und serverseitig ueber Moodle bereinigt.
Raw HTML sollte nicht vorausgesetzt werden.

### Optional unterstuetzte Tools

| Tool-Default | Zweck |
|---|---|
| `tutor_get_history` | Verlauf einer Conversation laden. |
| `tutor_delete_conversation` | Einzelne Conversation serverseitig loeschen. |
| `tutor_delete_user_data` | Alle serverseitigen Daten einer Person loeschen. |
| `tutor_set_memory_optin` | Langzeitgedaechtnis-Consent synchronisieren. |
| `tutor_recluster_questions` | Frageanalyse-Hotspots neu clustern. |

Optionale Tools werden nur genutzt, wenn in den Blockeinstellungen ein Toolname
konfiguriert ist.

### Zentrale Benutzerdaten-Loeschung

`local\deletion_service::delete_all_for_user()` ist die gemeinsame Loeschlogik
fuer den AJAX-Selbstloeschpfad, alle drei Privacy-API-Pfade und die regulaere
Moodle-Kontoloeschung. Der Dienst entfernt Gespraechszeiger,
Fragenprotokolle, Consent, Nutzungszaehler, Diagnostik und die LTM-Praeferenz
und liefert sowohl die Summe als auch einen Zaehler je Speicherort. Das
Audit-Event uebernimmt dieselben Zaehler.

Die externe User- oder Conversation-Loeschung laeuft davor best effort. Ein
externer Fehler erhoeht `externalfailed`, blockiert die lokale Loeschung aber
nicht. Lokale Datenbankfehler werden nicht verschluckt, damit Moodle einen
fehlgeschlagenen Privacy-Lauf korrekt erkennt.

Bei der Moodle-Kontoloeschung ruft
`\core_user\hook\before_user_deleted` die Routine auf, solange das Konto noch
aktiv und sein nutzerbezogenes MCP-Token gueltig ist. Der nachgelagerte
`\core\event\user_deleted`-Observer startet dieselbe Routine nur dann erneut,
wenn der Pre-Delete-Lauf nicht vollstaendig abgeschlossen wurde. So kann ein
Connector-/Backendfehler die Kontoloeschung nie blockieren, waehrend die lokale
Bereinigung nach dem Core-Event abgesichert bleibt.

---

## Question Analysis Report

`report.php` und `classes/local/question_log.php` implementieren eine
kursbezogene, namenfreie Aggregation der Fragen, die Schüler dem Tutor gestellt
haben.

### Sammlung

`question_log::log()` wird nach jedem erfolgreichen Chat aufgerufen. Die Methode
speichert die Frage (gekürzt auf 1000 Zeichen), den Kurs, das Antwort-Zielformat
(hint/explain/quiz), die Antwort-Stil-Angabe, das primäre Topic-Label und den
primären Source-Titel zusammen mit einem Timestamp. Keine persönlichen Daten
über die Antwort werden gespeichert. Die Sammlung ist standardmäßig deaktiviert;
die Einstellung `enableanalytics` schaltet sie sitewide frei.

**Tabelle:** `block_elediaai_tutor_qlog`

**Wichtige Felder:**
- `userid` — Fragesteller (für Privacy-Export/Löschung, nicht angezeigt)
- `courseid` — Kursbezug (0 = globaler Chat)
- `question` — Frage-Text (bis 1000 Zeichen)
- `grounded` — Bool: Antwort zitierte Kursmaterialien
- `answerstyle` — Konfigurierter Stil: `hint`, `explain`, `quiz`, oder NULL
- `topic` — Canonical Topic-Label (durch Recluster aktualisiert)
- `sourcetitle` — Titel des primären zitierten Materials
- `cmid` — Course Module ID des primären Materials, falls auflösbar
- `timecreated` — Unix-Timestamp

### Report-Seite

`report.php` ist eine geschützte Berichtsseite, die nur Nutzer mit
`block/elediaai_tutor:viewreports` im Kurs besuchen können. Sie rendert:

1. **Überblickszahlen:** Summe der Fragen (Gesamt und pro Tag)
2. **Zeitreihe:** Fragen pro Tag über die letzten 30 Tage
3. **Hotspots:** Die häufigsten Topic-Labels (Top-10 Cluster)
4. **Frage-Browser:** Letzte 50 Fragen mit Topic und Source (ohne Namen)
5. **Copilot-Schaltfläche:** Wenn analytics aktiviert und mindestens eine Frage
   vorhanden, wird ein Button zum Starten einer KI-Analyse angeboten

Die Seite nutzt `classes/local/question_log::hotspots()` (Top-Topics) und
`recent()` (Stichprobe von Fragen) als Datenquellen.

### Privacy

- Schüler können ihre Fragen beim Löschen all ihrer Tutor-Daten entfernen.
- Der Privacy-Export liefert alle Frage-Zeilen des Schülers im Report-Format.
- Administratoren können alte Einträge mit dem Task `prune_question_log`
  aufräumen (konfigurierbare Retention, Standard 90 Tage).

---

## Teacher-Copilot

`classes/local/copilot_service.php` und
`classes/external/generate_copilot_analysis.php` implementieren die KI-gestützte
Analyse der gesammelten Kursfragen.

### Ablauf

1. Ein Lehrer klickt auf „Copilot-Analyse" im Report.
2. Der Block prüft Consent und Rate-Limit (wie bei Schüler-Chat).
3. Der Block sammelt Hotspots (Top-10 Topics der letzten 30 Tage) und eine
   Stichprobe der letzten 40 Fragen (jede bis 200 Zeichen).
4. Ein strukturierter Prompt wird gebaut: "Hier sind die Top-Topics und
   Beispielfragen — was verstehen die Schüler nicht und was empfehlen Sie?"
5. Der Prompt läuft durch den regulären Chat-Tool (nicht als Schüler-Gespräch
   gespeichert, kein Consent-Gate für Copilot-Läufe).
6. Die Markdown-Antwort wird auf der Report-Seite angezeigt.

**Nicht gespeichert:** Der vom Server zurückgegebene Conversation-ID wird
verworfen — Copilot-Läufe erscheinen nie in der Gesprächsliste des Lehrers
oder in Schüler-Chat-Events.

### Externe Funktion

`generate_copilot_analysis::execute()` ist ein Webservice-Endpunkt für die
Report-Seite:

```php
generate_copilot_analysis::execute(courseid: int): {
  analysishtml: string,  // Gerendertes HTML aus der Markdown-Antwort
  iserror: bool
}
```

Voraussetzungen:
- `block/elediaai_tutor:viewreports` im Kurs.
- `question_log::is_enabled() === true`.
- Mindestens eine geloggete Frage im Kurs.
- Gültiger Consent des Lehrers.
- Rate-Limit nicht überschritten.

### Rate-Limiting

`security::enforce_rate_limit()` prüft einen Pro-Nutzer-Limit wie bei
Schüler-Chat. Wiederholte Copilot-Läufe in kurzer Zeit werden abgelehnt.

### Fehlerbehandlung

Token-Rotation: Falls das MCP-Token des Lehrers veraltet/revoziert ist, wird
der Token verworfen, ein neuer geprägt und die Anfrage einmal neu versucht
(wie beim Chat-Turn). Fehler führen zu lokalen Diagnostic-Einträgen
(kein Fehler an den Schüler).

---

## Reclustering

`classes/local/recluster_service.php` implementiert batch-weise
Neu-Topic-Zuweisung für Kursfragen.

### Konzept

Im Laufe der Zeit können Topic-Labels auseinanderlaufen (z. B. wenn sich das
RAG-Modell ändert oder Prompts justiert werden). Die Recluster-Routine läuft
täglich für alle Kurse mit neuem Tutor-Chat der letzten 30 Tage und sendet die
Fragen in Batches zum konfigurierten Recluster-Tool. Der Server antwortet mit
aktualisierten Labels, die lokal gespeichert werden. Der Prozess ist idempotent.

### Scheduled Tasks

**`recluster_questions`** (Standard: täglich 2 Uhr nachts)

Ruft `recluster_service::run()` auf. Liest alle Kurse mit Aktivität in den
letzten 30 Tagen, sendet bis zu 1000 Fragen pro Kurs in Batches à 200, speichert
zurückgegebene Topics.

**`prune_question_log`** (Standard: täglich 3 Uhr nachts)

Löscht Fragen älter als die konfigurierte Aufbewahrungsdauer (Standard 90 Tage).

### Authentifizierung

Recluster läuft unter dem Maintenance-Account (`service_user::get_or_create()`):
- Falls MCP aktiviert: Token wird für das Service-Konto geprägt wie bei jedem
  anderen Tool.
- Falls MCP deaktiviert: Kein Token wird gesendet (LLM-only-Mode).

### Tool-Konfiguration

Die Einstellung `recluster_tool` gibt den Namen des Recluster-Tools an (z. B.
`tutor_recluster_questions`). Wenn leer oder `question_log::is_enabled() ===
false`, laufen die Tasks erfolgreich, aber ohne Arbeit zu verrichten
(`is_configured()` prüft beides).

### Fehlerbehandlung

Fehler sind pro Kurs Best-Effort:
- Ein Batch-Fehler überspringt diesen Kurs (nächste Nacht wird erneut versucht).
- Der Gesamt-Task läuft weiter und verarbeitet die nächsten Kurse.
- Statistiken zählen Kurse, Batches, aktualisierte Einträge und Fehler.

---

## Diagnostics

`classes/local/diagnostics.php` sammelt Admin-seitige Metadaten zu Tutor-Ausfällen.

### Zweck

Wenn ein Chat, Copilot oder andere Tutor-Operationen scheitern, wird eine
anonyme Diagnose-Zeile geloggt: Nutzer-ID, Kurs, Kontext, Phase (z. B.
`copilot_error`, `mcp`, `rag`), kurze Exception-Summary und Timestamp. Keine
Prompts, Antworten oder API-Schlüssel werden gespeichert.

### Tabelle

**`block_elediaai_tutor_diag`**

**Felder:**
- `userid` — Betroffener Nutzer
- `courseid` — Kursbezug, falls bekannt (0 = kein Kurs)
- `contextid` — Kontext-ID (System/Kurs/Block)
- `phase` — Label der fehlgeschlagenen Phase (wird bereinigt)
- `errorcode` — Exception-Klasse (z. B. `dml_exception`, `moodle_exception`)
- `detail` — Kurze, nicht-sensitive Zusammenfassung (Exception-Message gekürzt)
- `timecreated` — Unix-Timestamp

### Abfrage

`diagnostics::latest(int $limit)` liefert die letzten N Einträge (Standard 5),
geordnet nach Timestamp. Diese Einträge können auf einer Admin-Dashboard-Seite
angezeigt werden, um häufige Probleme zu identifizieren.

### Aufräumen

Einträge werden automatisch nach 30 Tagen gelöscht (`prune()` wird bei jedem
Insert aufgerufen). Fehler beim Logging werden stillschweigend ignoriert, damit
Diagnose-Fehler nie den User Flow unterbrechen.

---

## Moodle-MCP-Callbacks

Der RAG-/Tutor-Server nutzt das uebergebene Moodle-MCP-Token, um im Namen der
angemeldeten Person Moodle-Tools aufzurufen. Er darf dabei nur die Moodle-Daten
verwenden, die diese Person sehen darf. Das Token darf nie geloggt, persistiert
oder an andere Clients weitergegeben werden.

Empfohlene Regeln fuer RAG-Server:

- Immer eine stabile `conversation_id` zurueckgeben.
- `rag_enabled=false` strikt beachten und dann keine Wissensbasis abfragen.
- `answer_style=hint` darf keine vollstaendige Loesung verraten.
- In der Sprache aus `user_lang` antworten, sofern die Nutzerfrage nichts anderes
  verlangt.
- Quellen nach Relevanz sortieren; `sources[0]` ist die Primaerquelle fuer
  Analyse-Hotspots.
- Behandelte Fehler mit `isError: true` melden; Ausfaelle als JSON-RPC-Fehler.

---

## Lokaler Betrieb

Im aktuellen lokalen Setup laeuft Moodle unter `http://localhost:8080`. Der
Block ist unter `public/blocks/elediaai_tutor` installiert. Fuer den Tutor muessen
mindestens gesetzt sein:

- RAG MCP server URL.
- Chat tool name.
- MCP external service (verpflichtend): der externe Dienst aus
  `webservice_elediamcp`, auf den die nutzerbezogenen Tokens beschraenkt sind.
- Lokale HTTP/private-host Opt-ins, falls ein lokaler Docker-Endpunkt verwendet
  wird.

Wichtig: Eine Browser-URL wie `http://localhost:8080/...` ist aus einem
Moodle-Container heraus nicht automatisch derselbe Host. Fuer End-to-End-Tests
muss die RAG-URL serverseitig aus dem Moodle-Container erreichbar sein.

---

## Tests und Checks

Aus dem Moodle-Root:

```bash
php admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --filter block_elediaai_tutor
vendor/bin/phpunit blocks/elediaai_tutor/tests/placement_test.php
vendor/bin/phpcs --standard=moodle blocks/elediaai_tutor
```

Behat:

```bash
php admin/tool/behat/cli/init.php
vendor/bin/behat --tags @block_elediaai_tutor
```

Im Review-Setup wurde der offizielle Moodle CodeChecker im Demo-Container mit
0 Errors / 0 Warnings dokumentiert. Vor Submission sollten CodeChecker, PHPUnit
und ein Browser-Smoke erneut laufen.

## Output-Hook-Guard: $PAGE ohne set_url() (2026-07)

`sitewide_tutor_allowed_on_page()` prueft am Ende `$PAGE->url->get_path()`,
um die blockeigenen Seiten auszunehmen. Auf Seiten ohne `$PAGE->set_url()`
(Redirect-Zwischenseiten) loest der Magic-Getter eine `debugging()`-Notice
aus, die der Whoops-Handler auf dev zum HTTP 500 eskaliert. Das Gate liefert
deshalb ohne gesetzte URL `false` (`if (!$PAGE->has_set_url())`) — ohne URL
kein Sitewide-Tutor. Regressionstests in `tests/hook_callbacks_test.php`;
Kontrakt: "Output-hook $PAGE guard (hard rule)" in
`local/lernhive/docs/03-dev-doc.md` im lernhive-Repo.

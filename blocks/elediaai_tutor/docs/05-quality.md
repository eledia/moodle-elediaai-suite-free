# Qualitaet

Dieses Dokument sammelt Bugs, Testfaelle und Verifikationsergebnisse.

---

## Automatisierte Tests (`../tests/`)

> Bestandsaufnahme 2026-08-02 (DevFlow-Sync). **24 PHPUnit-Dateien mit
> zusammen 178 Testmethoden** sowie **5 Behat-Features mit 25 Szenarien**.
> Die Listen unten sind Inventar aus dem Code; die dokumentierten
> Verifikationslaeufe (`test01`–`test13`) bleiben separat unter „Tests".

### PHPUnit (`../tests/*_test.php`)

| Datei | Methoden | Gegenstand |
|---|---|---|
| `rag_client_test.php` | 20 | MCP-HTTP-Client: Request-Bau, Response-Parsing, Fehlerpfade. |
| `external_test.php` | 19 | AJAX-/Webservice-Endpunkte (`classes/external/`). |
| `branding_test.php` | 10 | Branding-/Tutor-Profil-Aufloesung. |
| `security_test.php` | 18 | URL-/CSS-/Transport-Haertung und verschluesseltes RAG-Token (`local\security`). |
| `widget_test.php` | 10 | Block-/Widget-Rendering. |
| `chat_service_test.php` | 10 | Chat-Orchestrierung und optionale Token-Quota (`local\chat_service`). |
| `privacy_provider_test.php` | 9 | Privacy-Provider (Export/Loeschung inkl. RAG-Propagation). |
| `registry_test.php` | 9 | Tutor-/Branding-Registry. |
| `question_log_test.php` | 8 | Frageanalyse-Log (`local\question_log`). |
| `user_audience_test.php` | 7 | Rollen-/Audience-Aufloesung (`local\user_audience`). |
| `error_message_test.php` | 6 | Fehlermeldungs-Mapping. |
| `recluster_service_test.php` | 6 | Frage-Hotspot-Reclustering (`local\recluster_service`). |
| `consent_test.php` | 5 | Consent-Gate (`local\consent`). |
| `ltm_test.php` | 5 | Langzeitgedaechtnis-Sync (`local\ltm`). |
| `conversation_repository_test.php` | 4 | Conversation-Persistenz. |
| `component_migration_test.php` | 1 | Datenmigration nach Komponenten-Rename. |
| `deletion_service_test.php` | 6 | Vollstaendige Benutzerdaten-Loeschung inkl. externem Fehlerpfad. |
| `hook_callbacks_test.php` | 4 | Output-Hooks / `$PAGE`-Guard. |
| `usage_test.php` | 4 | Nutzungs-/Limit-Zaehlung (`local\usage`). |
| `chat_mode_test.php` | 3 | Chat-Modus-Aufloesung (`local\chat_mode`). |
| `copilot_service_test.php` | 3 | Teacher-Copilot-Analyse (`local\copilot_service`). |
| `tutor_io_test.php` | 3 | Tutor-Import/-Export und Bildvalidierung. |
| `mobile_test.php` | 2 | Moodle-App-Ausgabe (`output\mobile`). |
| `lib_test.php` | 1 | `lib.php`-Callbacks (u. a. `pluginfile`). |

### Behat (`../tests/behat/*.feature`, `@block_elediaai_tutor`)

| Feature | Szenarien | Gegenstand |
|---|---|---|
| `chat_ui.feature` | 7 | Chat-Oberflaeche end-to-end. |
| `plugin_shell.feature` | 6 | Plugin-Shell (Dashboard, Tutorenverwaltung, Vorschau). |
| `privacy_controls.feature` | 6 | Consent-/Datenschutz-Controls. |
| `standalone_page.feature` | 4 | Standalone-Tutor-Seite. |
| `course_context.feature` | 2 | Kursbezogene Standalone-Seite / Block-Gating. |

Ein durchgaengiger CI-/Container-Lauf dieser Suite ist unter „Tests"
(`test02`–`test06`, `task08`) dokumentiert; die Zaehlungen hier sind rein
statisch aus dem Code erhoben.

---

## Bugs

### bug01 Lokale RAG-URL kann aus Docker heraus falsch aufloesen

Feature:  feat01 / feat02
Severity: S3
Status:   mitigated
Linked:   task02, test01

**Beschreibung**
Eine RAG-URL wie `http://localhost:8080/...` funktioniert im Browser, kann aber
aus dem Moodle-Container heraus auf den Container selbst zeigen. Wenn dort auf
Port 8080 kein Dienst laeuft, schlaegt der serverseitige RAG-Aufruf fehl.

**Reproduktion**
1. Moodle laeuft im Docker-Container.
2. Tutor-Block RAG-URL auf `http://localhost:8080/...` setzen.
3. Chat-Nachricht senden.

**Erwartet**
Moodle erreicht den RAG/Tutor-MCP-Endpunkt serverseitig.

**Tatsaechlich**
Je nach Docker-Netzwerk ist der Endpunkt nicht erreichbar oder Moodle leitet
wegen `wwwroot`-Mismatch um.

**Stand 2026-06-26**
Fuer die lokale Entwicklung wurden Docker-Loopback/Host-Header-Probleme in den
lokalen Integrationen adressiert. Der generelle Hinweis bleibt relevant fuer
neue Setups: RAG-/Ingest-URLs muessen serverseitig aus dem Moodle-Container
erreichbar sein.

### bug02 Fehlender `MOODLE_INTERNAL`-Guard in `block_elediaai_tutor.php`

Feature:  feat01
Severity: S1
Status:   fixed
Linked:   task04, task07

**Beschreibung**
Die Block-Hauptdatei war als nicht-autoloaded Moodle-Datei nicht gegen direkten
Aufruf geschuetzt.

**Fix**
`defined('MOODLE_INTERNAL') || die();` wurde direkt nach dem GPL-Header
ergaenzt.

**Reconciliation 2026-06-27 (moodle-cs)**
Der offizielle Moodle-CodeChecker (`moodle.Files.MoodleInternal`) meldet den
Guard in seiteneffektfreien bzw. autoloaded Dateien als ueberfluessig
(`MoodleInternalNotNeeded`). In `task07` wurde er daher wieder entfernt aus
`block_elediaai_tutor.php`, `lib.php`, `edit_form.php`,
`classes/hook_callbacks.php`, `classes/output/plugin_page.php` und
`plugin_shell.php`. Das ist sicherheitsneutral: Diese Dateien deklarieren nur
Klassen/Funktionen, ein Direktaufruf fuehrt keinen Code aus. Dateien mit echten
Seiteneffekten (z. B. `settings.php`, Lang-Dateien) behalten den Guard.

### bug03 Unsicheres `unserialize()` in `db/upgrade.php`

Feature:  feat03
Severity: S2
Status:   fixed
Linked:   task04

**Beschreibung**
Ein Upgrade-Step deserialisierte `block_instances.configdata` mit nacktem
`unserialize()`.

**Fix**
Die Stelle nutzt nun `unserialize_object()`.

### bug04 LTM-Sync konnte vor Privacy-Consent extern kommunizieren

Feature:  feat02
Severity: S2
Status:   fixed
Linked:   task04

**Beschreibung**
`set_ltm` konnte ueber `ltm::sync_to_rag()` einen Moodle-MCP-Token an den
RAG-Server senden, bevor der Nutzer den Datenschutzhinweis bestaetigt hatte.

**Fix**
`ltm::sync_to_rag()` prueft jetzt zentral `consent::has_consented()` und bricht
ohne Consent frueh ab.

### bug05 Citation-URLs nur indirekt ueber External-Return-Typ abgesichert

Feature:  feat02
Severity: S3
Status:   fixed
Linked:   task04

**Beschreibung**
`message.mustache` rendert Quellen als `href="{{url}}"`. Die URL war bereits
ueber `PARAM_URL` in External-Returns abgesichert, aber nicht vor der
Template-Uebergabe normalisiert.

**Fix**
URLs werden nun in `rag_client`, `send_message` und `get_history` zusaetzlich
mit `clean_param(..., PARAM_URL)` normalisiert. Das Template dokumentiert diese
Vorbedingung.

### bug06 Inline-JS/CSS im Admin-Navbar-Hook

Feature:  feat03
Severity: S3
Status:   fixed
Linked:   task04

**Beschreibung**
Der Output-Hook injizierte Inline-`style` und Inline-`script` fuer den
Admin-Launcher. Das ist mit strikter CSP nicht kompatibel.

**Fix**
CSS wurde nach `styles.css` verschoben. Das Verhalten liegt in
`amd/src/admin_launcher.js` und wird ueber `js_call_amd()` geladen.

### bug07 Veraltete Moodle Context-Alias-Klassen in Produktiv-PHP

Feature:  feat03
Severity: S3
Status:   fixed
Linked:   task04

**Beschreibung**
Produktiv-PHP nutzte noch `context_system`, `context_course` und
`context_block`.

**Fix**
Produktiv-PHP nutzt jetzt `\core\context\system`, `\core\context\course` und
`\core\context\block`. Tests koennen bei Bedarf separat stilistisch
modernisiert werden.

### bug08 Direkte DB-Abfrage im Rendering von `manage_tutors.php`

Feature:  feat03
Severity: S4
Status:   fixed
Linked:   task04

**Beschreibung**
Die Blockinstanzen wurden im Rendering-Abschnitt direkt ueber `$DB` geladen.

**Fix**
Die Abfrage liegt nun in `tutor_apply::instance_records()`.

### bug09 Help-Link der Plugin-Shell setzt `local_lernhive` voraus

Feature:  feat03
Severity: S1
Status:   fixed
Linked:   task05

**Beschreibung**
`classes/output/plugin_shell.php::action_slots()` (Zeile 56) erzeugt den
Help-Link hardkodiert auf `/local/lernhive/support.php`. `helpurl` wird immer
gesetzt, sodass das Fragezeichen im Shell-Header (`plugin_shell_header.mustache`)
stets gerendert wird -- ueberall, wo `shell::header()` greift
(`configuration.php`, `settings.php`, `manage_tutors.php`, `report.php`,
`edit_instance.php`, `instance_tutor.php` und die Preview in `view.php`). Ohne
installiertes `local_lernhive` fuehrt der Link auf eine 404-Seite.

**Einordnung als Abhaengigkeit (2026-06-27)**
Dies ist die **einzige echte Laufzeit-Abhaengigkeit** auf `local_lernhive`:
- `version.php` enthaelt **kein** `$plugin->dependencies` -- es gibt keine
  Installations-Abhaengigkeit; das Plugin installiert und laeuft ohne LernHive.
- Alle anderen Fremd-Plugin-Integrationen sind sauber per
  `class_exists()` bzw. `core_component::get_plugin_directory()` abgesichert:
  `local_literag`, `local_elediaai_sources` (`chat_mode.php`, `configuration.php`),
  `webservice_elediamcp` (`token_provider.php:57`), Premium (`premium.php`).
- Die `body:not(.theme-lernhive)`-Regeln in `styles.css` sind **keine**
  Abhaengigkeit, sondern Standalone-Fallback-Styling, das genau dann greift,
  wenn das LernHive-Theme **fehlt**. Die `.lh-*`-Slots werden vom Plugin selbst
  gestylt (Vollstaendigkeit der Fallbacks = `bug10`).
- `settings_shell.js`/`settings.php`-Erwaehnungen von „LernHive" sind nur
  Kommentare zur Design-Herkunft.

Der Help-Link ist damit die einzige Stelle, die nicht dem ansonsten
durchgaengigen Guard-Muster folgt.

**Erwartet**
Der Help-Link zeigt auf eine plugin-eigene Hilfeseite. LernHive darf dieselbe
Dokumentation optional in seinem Support-Hub darstellen, darf aber keine
Runtime-Voraussetzung fuer den Tutor sein.

**Fix 2026-06-27/28**
`plugin_shell::action_slots()` setzt `helpurl` immer auf
`/blocks/elediaai_tutor/help.php`. Die neue `help.php` rendert die vorhandene
Plugin-Dokumentation aus `docs/02-user-doc*.md` in der Plugin-Shell. Damit ist
die Hilfe auch ohne `local_lernhive` erreichbar; das LernHive-Support-Hub bleibt
rein optional.

### bug10 Settings-Shell-Fallback ist zu fragil

Feature:  feat03
Severity: S3
Status:   fixed
Linked:   task05

**Beschreibung**
`amd/src/settings_shell.js` baut die Shell-Struktur auch dann auf, wenn
`config.headerHtml` fehlt. Zudem fehlen Fallback-Regeln fuer einige in
`plugin_shell_header.mustache` angebotene `lh-*`-Slots.

**Erwartet**
Ohne Header-Kontext bleibt das Moodle-Formular nutzbar. Alle genutzten oder im
Template angebotenen Shell-Slots besitzen eine sinnvolle Fallback-Darstellung.

**Fix 2026-06-27**
`settings_shell.js` beendet die Shell-Initialisierung kontrolliert, wenn
`config.headerHtml` fehlt, und entfernt dabei den Pending-Zustand. Zusaetzlich
wurden Fallback-Regeln fuer `lh-plugin-infobar`, `lh-plugin-tag`,
`lh-btn-*` und `lh-row-actions` ergaenzt.

### bug11 Fokus- und A11y-Details in Chat- und Settings-UI

Feature:  feat01 / feat03 / feat04
Severity: S3
Status:   fixed
Linked:   task05

**Beschreibung**
Der UX-Review nennt mehrere kleine Accessibility-Punkte: `:focus`-Fallback fuer
den Send-Button, sichtbare `:focus-visible`-Ringe fuer Settings-Karten und
Backnav, `aria-label` fuer History-Liste/Composer sowie `role="note"` statt
`role="alert"` fuer statische Privacy-Hinweise.

**Erwartet**
Tastaturfokus ist sichtbar und statische Inhalte werden von Screenreadern nicht
als dynamische Alerts angesagt.

**Fix 2026-06-27**
Fokus-Fallbacks fuer Send-Button, History-Open-Button, Settings-Hub-Karten und
Backnav ergaenzt. History-Liste und Composer besitzen nun Labels. Der statische
Accuracy-Hinweis im Privacy-Modal nutzt `role="note"`, und die
Abschnittsueberschriften sind als `h3` strukturiert.

### bug12 Settings-Override-Checkboxen waren ueberstylt

Feature:  feat03
Severity: S4
Status:   fixed
Linked:   task05

**Beschreibung**
Die `Ueberschreiben erlauben`-Checkboxen in der Admin-Settings-Shell waren mit
Spezial-Pills, fetter Beschriftung und eigener Checkbox-Optik versehen. Das
wirkte nicht Moodle-nativ.

**Fix**
Die Darstellung wurde auf eine schlichte Moodle-nahe Form reduziert:
normale Checkbox, normales Label und `Standard: Ja/Nein` als einfacher Text.

### bug13 Stored XSS ueber ungepruefte SVG-Uploads (Logo/Avatar)

Feature:  feat03
Severity: S2
Status:   fixed
Linked:   task06, test04

**Beschreibung**
Tutor-Logo und -Avatar akzeptieren `.svg` als Upload-Typ
(`classes/form/tutor_edit_form.php:76`, `edit_instance.php:157`). Beim Import
prueft `tutor_io::is_safe_image()` (`classes/local/tutor_io.php:180`) nur, ob die
ersten 512 Bytes die Zeichenkette `<svg` enthalten, ohne Sanitizing. Die Datei
wird spaeter ueber `block_elediaai_tutor_pluginfile()` (`lib.php:113`) mit
`send_stored_file(..., $forcedownload, ...)` inline ausgeliefert und als
`<img src>` eingebettet. Eine praeparierte SVG mit `<script>`/Event-Handlern
fuehrt damit Code im Browser jedes betrachtenden Nutzers aus.

**Trust-Boundary**
Upload erfordert `block/elediaai_tutor:manage` bzw. `moodle/site:config`. Es ist
also ein Teacher-zu-Student/Admin-XSS, kein anonymes -- deshalb S2 statt S1.

**Erwartet**
SVGs werden vor dem Speichern saniert (Scripts/`on*=`/externe Referenzen
entfernt) oder `.svg` wird aus `accepted_types` entfernt; alternativ werden
diese Dateibereiche mit `forcedownload => true` ausgeliefert.

**Fix 2026-06-27 (Defense in Depth)**
1. Auslieferungsschicht: `block_elediaai_tutor_pluginfile()` (`lib.php`) liefert
   alle Branding-Dateibereiche jetzt unbedingt mit `forcedownload = true` aus.
   Ein als Top-Level-Dokument abgerufenes SVG fuehrt dadurch keinen Code mehr im
   Moodle-Origin aus; die `<img>`-Einbettung bleibt unveraendert. Das schuetzt
   auch bereits gespeicherte Dateien.
2. Speicherpfad (Import): `tutor_io::is_safe_image()` liest jetzt den gesamten
   SVG-Inhalt und lehnt Dateien mit `<script`, `<foreignObject`,
   `javascript:`, `<!ENTITY` oder Inline-Event-Handlern (`on...=`) ab, statt nur
   die ersten 512 Bytes auf `<svg` zu pruefen.

`php -l` fuer `lib.php` und `classes/local/tutor_io.php` gruen.

### bug14 Unescaptes `customcss` erlaubt `</style>`-Breakout

Feature:  feat03
Severity: S3
Status:   fixed
Linked:   task06, test04

**Beschreibung**
`templates/launcher.mustache:43` rendert `<style>{{{customcss}}}</style>` roh.
`security::custom_css()` (`classes/local/security.php:236`) liefert den Wert nur
`trim()`-bereinigt zurueck. Ein Schreibzugriff auf diese Site-Einstellung
erlaubt `</style><script>...</script>` und damit Stored XSS fuer alle Nutzer,
die den Block laden; zudem wird eine strikte CSP unterlaufen.

**Trust-Boundary**
Admin-gated, aber eine CSS-Einstellung ist keine erwartete Script-Flaeche.

**Erwartet**
`custom_css()` entfernt `<`/`>` bzw. lehnt `</style`/`<script` ab oder rendert
ueber einen CSS-Sanitizer.

**Fix 2026-06-27**
`security::custom_css()` entfernt NULs und Angle-Brackets, bevor der Wert in
`<style>` gerendert wird. Zusaetzlich werden `@import`, `expression()` und
`javascript:` entfernt.

### bug15 Fehlender Fokusring am History-Open-Button

Feature:  feat01
Severity: S3
Status:   fixed
Linked:   task06, test04

**Beschreibung**
`.elediaai_tutor-history-open` (`templates/conversation_list.mustache:38`) ist der
primaere Button zum OEffnen einer gespeicherten Konversation, hat in `styles.css`
aber weder `:focus` noch `:focus-visible`. Tastaturnutzer erhalten beim Tabben
durch die History keine sichtbare Fokusmarkierung, waehrend alle anderen
Panel-Controls einen Fokusring besitzen.

**Erwartet**
`.elediaai_tutor-history-open:focus-visible` erhaelt einen sichtbaren Ring analog
zu den uebrigen interaktiven Controls.

**Fix 2026-06-27**
`:focus-visible` fuer `.elediaai_tutor-history-open` ergaenzt.

### bug16 Heading-Reihenfolge im Privacy-Modal springt auf `h5`

Feature:  feat04
Severity: S4
Status:   fixed
Linked:   task06, test04

**Beschreibung**
`templates/privacy_info.mustache` nutzt durchgaengig `<h5>` fuer die
Abschnittsueberschriften, ohne vorausgehende `h1`-`h4` im Dialog. Die
Screenreader-Heading-Navigation landet damit auf `h5` ohne Outline-Kontext.

**Erwartet**
Abschnittsueberschriften beginnen passend unterhalb des Modal-Titels (z. B.
`h2`/`h3`).

**Fix 2026-06-27**
Privacy-Abschnittsueberschriften nutzen nun `h3.elediaai_tutor-privacy-heading`.

### bug17 `appendFailure` baut HTML fuer Triple-Mustache-Slot

Feature:  feat01
Severity: S4
Status:   fixed
Linked:   task06, test04

**Beschreibung**
`amd/src/chat.js:565` setzt `html: '<p>' + this.escape(message) + '</p>'`, das
ueber `{{{html}}}` (`templates/message.mustache:70`) unescaped gerendert wird.
Aktuell sicher durch `this.escape()`, aber ein fragiles Muster: Faellt der
`escape()`-Aufruf bei einer spaeteren AEnderung weg, entsteht still DOM-XSS des
AJAX-Fehlertexts.

**Erwartet**
Fehlertext ueber einen escapeten Slot rendern oder die Vorbedingung am Code
dokumentieren.

**Fix 2026-06-27**
`appendFailure()` uebergibt `failuretext`; `message.mustache` rendert diesen
Wert mit normalem Mustache-Escaping und nutzt `{{{html}}}` nur noch fuer
serverseitig sanitisierte Assistant-Antworten.

---

## Tests

### test01 Lokaler Tutor-Smoke

Feature: feat01 / feat02
Status:  passed-with-environment-note

**Schritte**
1. Moodle unter `http://localhost:8080` oeffnen.
2. Mit Testnutzer anmelden.
3. Tutor-Block oder `/blocks/elediaai_tutor/view.php` oeffnen.
4. Consent bestaetigen.
5. "Hallo Tutor" senden.

**Erwartet**
Der Tutor liefert eine Chat-Antwort. Im Fehlerfall wird die konkrete
Konfigurations- oder Transportursache dokumentiert.

**Stand 2026-06-26**
Der lokale Kurskontext/RAG-Fluss wurde im Entwicklungssetup zwischenzeitlich
erfolgreich hergestellt. Fuer eine reproduzierbare Abschlussfreigabe bleibt ein
sauberer Browser-Smoke mit dokumentiertem Nutzer/Block/Kurs offen.

**Stand 2026-08-04 (SUI-28)**
- Ziel-RAG-Server gemaess `q01`: manueller Testserver
  `tests/manual/tutor_mcp_server.py`, gestartet mit
  `python3 tutor_mcp_server.py --host 0.0.0.0 --no-moodle-callback --verbose`.
- Aus Moodle erreichbar unter `http://host.docker.internal:8765/mcp`;
  `tools/list` aus dem Container lieferte die erwarteten Tools
  (`tutor_chat`, `tutor_get_history`, `tutor_delete_conversation`,
  `tutor_delete_user_data`, `tutor_set_memory_optin`,
  `tutor_recluster_questions`).
- Verwendete Moodle-Instanz: `demo-webserver-1`, `wwwroot`
  `http://localhost:9501`, weil die lokale 8080-Instanz noch auf dem
  Alt-Komponentennamen `block_eledia_aitutor` installiert war und ein
  Komplett-Upgrade an fremden Plugin-Abhaengigkeiten scheiterte.
- Block-Settings fuer den Lauf:
  `ragserverurl=http://host.docker.internal:8765/mcp`,
  `allowinsecuretransport=1`, `allowprivatenetwork=1`,
  `chattoolname=tutor_chat`. `webservice_elediamcp` war in dieser Demo-Instanz
  nicht installiert; deshalb lief der Smoke im erlaubten LLM-only-Modus ohne
  Moodle-Callback, aber weiterhin ueber den Tutor-MCP-Endpunkt.
- Nutzer/Kontext: `admin`, Systemkontext `1`, Consent via
  `block_elediaai_tutor\external\give_consent::execute(1)`.
- Chat-Aufruf:
  `block_elediaai_tutor\external\send_message::execute(1, 'Hallo Tutor', 0, '', 'explain', 'knowledge', '')`.
- Ergebnis: `iserror=false`, Conversation-ID `conv-0af1811b31a2`; Antwort
  begann mit `Hi, I'm your eLeDia.ai Tutor` und enthielt die erwartete
  lokale Testmodus-Antwort.
- Serverlog:
  `tutor_chat conv=conv-0af1811b31a2 turn=1 course=- style=explain lang=en ltm=absent rag=False token=(none) msg='Hallo Tutor'`.

**Restrisiko**
Der geforderte Browser-/Port-8080-Lauf bleibt von der lokalen 8080-Instanz
abhaengig: Dort ist die Datenbank noch auf `block_eledia_aitutor` registriert,
waehrend der gemountete Code bereits `block_elediaai_tutor` ist. Ein
`admin/cli/upgrade.php --non-interactive` brach vor der Block-Migration wegen
fremder Plugin-Abhaengigkeiten ab. Der Block-zu-Tutor-MCP-Endpunkt selbst ist
im migrierten Demo-Container erfolgreich verifiziert.

### test02 Review-Fix-Verifikation

Feature: feat01 / feat02 / feat03
Status:  passed-with-environment-note
Linked:  task04

**Geprueft**
- `php -l` ueber alle Plugin-PHP-Dateien lokal: bestanden.
- `php -l` ueber alle Plugin-PHP-Dateien im Moodle-Container: bestanden.
- `node --check public/blocks/elediaai_tutor/amd/src/admin_launcher.js`: bestanden.
- Moodle-Container mit aktuellem Block-Plugin synchronisiert.
- Moodle-Caches im Container gepurged.

**Nicht ausgefuehrt**
PHPUnit ist in der lokalen Containerumgebung nicht initialisiert. Der direkte
Aufruf scheitert an fehlendem Moodle-Testbootstrap bzw. `@root@` in
`phpunit.xml.dist`.

**Naechster Schritt bei Bedarf**
Moodle PHPUnit-Umgebung initialisieren und mindestens `ltm_test` sowie
`rag_client_test` ausfuehren.

**Re-Verifikation 2026-06-26 (Folge-Review)**
Die Fixes zu `bug02`-`bug08` wurden am Code erneut bestaetigt:
`MOODLE_INTERNAL`-Guard, `unserialize_object()`, Consent-Gate in
`ltm::sync_to_rag()`, `PARAM_URL`-Normalisierung in `rag_client`/`send_message`/
`get_history`, `js_call_amd()` statt Inline-JS/CSS, keine Legacy-Context-Aliasse
in Produktiv-PHP und `tutor_apply::instance_records()` sind unveraendert
vorhanden.

### test03 UX/UI-Review-Verifikation

Feature: feat01 / feat03 / feat04
Status:  passed-with-environment-note
Linked:  task05

**Geprueft**
- Settings-Override-Checkboxen visuell vereinfacht.
- Geaendertes `styles.css` in den lokalen Moodle-Container kopiert.
- Moodle-Caches im Container gepurged.
- Statische Syntaxchecks fuer die geaenderten PHP- und AMD-Quellen bestanden.

**Nicht ausgefuehrt**
Vollstaendiger Screenreader-Smoke wurde nicht automatisiert ausgefuehrt.

### test04 Folge-Review-Findings verifizieren

Feature: feat01 / feat03 / feat04
Status:  partial
Linked:  task06

**Ziel**
Die im Folge-Review vom 2026-06-26 gefundenen `bug13` bis `bug17` nach der
Umsetzung verifizieren.

**Schritte**
1. Praeparierte SVG als Logo/Avatar hochladen und pruefen, dass kein Script
   ausgefuehrt bzw. die Datei nur als Download ausgeliefert wird (`bug13`).
2. `customcss` mit `</style><script>` setzen und pruefen, dass kein Script
   ausgefuehrt wird (`bug14`).
3. Mit Tastatur durch die History tabben: sichtbarer Fokusring am
   Open-Button (`bug15`).
4. Screenreader-Outline des Privacy-Modals pruefen (`bug16`).
5. Sichtkontrolle `appendFailure`-Pfad nach Umbau (`bug17`).

**Erwartet**
Keine Script-Ausfuehrung ueber SVG oder `customcss`; sichtbarer Tastaturfokus;
konsistente Heading-Outline.

**Stand 2026-06-27**
Code-Fixes fuer `bug13` bis `bug17` umgesetzt. Statische Syntaxchecks bestanden.
Ein manueller Browser-Exploit-Smoke fuer SVG/`customcss` bleibt fuer die
Abschlussfreigabe empfehlenswert.

### test05 Moodle CodeChecker (moodle-cs)

Feature: -
Status:  passed
Linked:  task07

**Geprueft**
Offizieller Moodle CodeChecker (`phpcs --standard=moodle`,
`moodlehq/moodle-cs`) ueber das gesamte Plugin im lokalen Container.

**Ergebnis 2026-06-27**
`Codechecker: 0 errors, 0 warnings`. Ausgangslage war 920 Errors + 660 Warnings;
behoben via PHPCBF-Autofix (892), manuelle Aufloesung der nicht-konvergierenden
Faelle (u. a. `manage_tutors.php`), Lang-String-Sortierung (2x 658 Strings) und
`MOODLE_INTERNAL`-Bereinigung. Siehe `task07`.

### bug18 `MODIFIER_COMPACT` hat denselben Wert wie `MODIFIER_FULL`

Feature:  feat03
Severity: S4
Status:   fixed
Linked:   task07, SUI-53

**Beschreibung**
`classes/output/plugin_page.php` definiert
`public const MODIFIER_COMPACT = 'full';` -- identisch zu `MODIFIER_FULL`. Das
sieht nach einem Copy-Paste-Fehler aus (erwartet vermutlich `'compact'`). Beim
Codechecker-Durchgang aufgefallen, aber bewusst NICHT geaendert, da es eine
Verhaltensaenderung waere.

**Ergebnis (SUI-53)**
Die Konstante war toter Code: nirgends im Monorepo verwendet, ausgeschlossen von
der Allow-List in `plugin_page::open()` und vom `match`-Statement. Ein
echtes `'compact'`-Format (mit CSS-Modifier, Allow-List-Eintrag, Match-Arm und
Design-Token-Abstimmung) existierte nicht und hatte keine Produktanforderung.
**Loesung:** Konstante entfernt. Verhaltensneutral, weil kein Aufrufer existierte.

### bug24 Standalone-Tutor ist innerhalb der Plugin-Shell doppelt begrenzt

Feature:  feat01
Severity: S3
Status:   fixed
Linked:   task13, test07, SUI-368

**Beschreibung**
Die Shell begrenzt ihre Breite bereits ueber `--lh-page-max-default` bzw.
`--lh-page-max-reading`. `view.php` legte mit `.elediaai_tutor-page` zusaetzlich
860 px fest. Dadurch blieb der Chat sichtbar schmaler als die Zone A und als
vergleichbare Plugin-Shell-Inhalte.

**Fix**
`.lh-plugin-shell .elediaai_tutor-page` hebt nur im Shell-Kontext die innere
Maximalbreite auf. Der bestehende 860-px-Wert bleibt fuer eingebettete und
ungeshellte Seiten aktiv.

### test07 Plugin-Shell-Breite der Standalone-Tutor-Seite

Feature:  feat01
Status:   passed-with-environment-note
Linked:   task13, bug24

**Ziel**
Sicherstellen, dass `view.php` innerhalb der Plugin-Shell deren volle
Inhaltsbreite nutzt, ohne eingebettete oder ungeshellte Ansichten zu
verbreitern.

**Erwartet**
Der shell-spezifische CSS-Override gewinnt gegen die 860-px-Basisregel. Ohne
`.lh-plugin-shell` bleibt die Basisregel unveraendert wirksam.

**Ergebnis 2026-07-31**
- Statischer CSS-Contract-Check bestaetigt Basisregel und nachgelagerten,
  spezifischeren Shell-Override.
- `php -l` und Moodle CodeSniffer fuer die geaenderte `version.php`: bestanden.
- `git diff --check`, `scripts/sync-design.php --check` und
  `scripts/lint-vendored-shell.php`: bestanden.
- Der komplette Plugin-CodeSniffer-Lauf bleibt wegen 34 vorbestehender Befunde
  in `classes/local/icon.php` rot; die Datei gehoert nicht zu diesem Scope.

**Nicht ausgefuehrt**
Visueller Browser-Smoke und Behat konnten in dieser Laufzeit nicht ausgefuehrt
werden; die Browser-Anbindung und eine initialisierte lokale Behat-Installation
waren nicht verfuegbar.

### test08 Supportvertrag und Versionsgrenzen

Feature: Supportvertrag
Status:  passed-with-environment-note
Linked:  task14, SUI-374

**Geprueft**
- `version.php` deklariert Release 0.19.5, Moodle 4.5 als Mindestversion,
  Moodle 5.2 als Obergrenze und PHP 8.3+ in der Dokumentation.
- `scripts/ci-matrix.php` erzeugt standardmaessig die beiden harten Grenzen
  `MOODLE_405_STABLE` und `MOODLE_502_STABLE`.

### test12 Gemeinsame Plugin-Shell-Synchronisation

Feature: feat01
Status:  passed
Linked: task18, SUI-393

**Geprueft**
- `php scripts/sync-design.php --check`: Tutor, Filter und LiteRAG enthalten
  identische `lh-core`-Stempel.
- `php scripts/lint-vendored-shell.php`: generierter Tutor-Block, Actionbar,
  Full-Shell-Content und sichtbare Fokusringe sind verifiziert; 21
  Stylesheets sind token-clean.
- `bash lernhive/playbooks/lint-vendored-shell.sh`: kanonische Shell und die
  vendorte LernHive-Kopie erfüllen denselben Marker-/Fokus-/Token-Vertrag.
- Die Forgejo-CI fuehrt `moodle-plugin-ci install`, `phplint` und `phpunit`
  ohne `continue-on-error` fuer jede gewaehlte Matrixzelle aus.
- `tests/hook_callbacks_test.php` ist Teil der Plugin-Suite und wird damit an
  beiden Supportgrenzen ausgefuehrt.

**Nicht lokal ausgefuehrt**
Die vollstaendige Moodle-Installations- und PHPUnit-Matrix benoetigt die
Forgejo-Containerumgebung; sie wird durch die geaenderte CI-Konfiguration als
harte Remote-Verifikation ausgefuehrt. Lokale statische Checks bleiben unten im
Issue-Abschluss dokumentiert.

### test09 Rename-Konsistenz

Feature:  feat05
Status:   passed-with-environment-note
Linked:   task15, SUI-377

**Geprueft**
- Kein regulärer Code-, URL-, CI- oder Dokumentationspfad verwendet nach der
  Umstellung weiterhin `eledia_aitutor` oder `elediaaitutor`; Ausnahmen sind
  ausschließlich der Migrationspfad und historische Privacy-String-IDs.
- Der technische Service-Account ist als bewusst erhaltener externer Username
  dokumentiert; persistente Plugin-Daten werden durch `test11` abgedeckt.
- Der neue Pluginpfad und die neue Komponente sind in `version.php`,
  `db/services.php`, `db/upgrade.php`, AMD-Builds und Suite-Integrationen
  konsistent.

### bug25 Undeklarierte local_elediaai_core-Abhaengigkeit im Tutor-Chat

Feature:  feat01 / feat02
Severity: S1
Status:   fixed
Linked:   task16, SUI-370

**Beschreibung**
`chat_service` rief den zentralen `local_elediaai_core`-Quota-Manager ohne Guard
auf. Der Block deklarierte keine Installationsabhaengigkeit und versprach einen
eigenstaendigen LLM-only-Betrieb, brach ohne das Begleitplugin aber beim ersten
Chat-Request mit `Class not found` ab. Die bisherige Tutor-CI installierte das
Begleitplugin als Matrix-Dependency und verdeckte den Standalone-Fehler.

**Fix 2026-07-31**
Die drei Quota-Aufrufe laufen ueber `local/token_quota.php`. Der Adapter prueft
die optionale Klasse samt Methodenvertrag, nutzt ohne Companion einen lokalen
Token-Schaetzer und behandelt die gemeinsame Pruefung/Buchung als No-op. Die
CI-Matrix entkoppelt den Tutor von der Chat-Gruppe und fuehrt zusaetzlich einen
Quota-Vertragslauf mit installiertem `local_elediaai_core` aus.

### test10 Optionaler Token-Quota-Vertrag

Feature:  feat01 / feat02
Status:   passed-with-environment-note
Linked:   task16, bug25, SUI-370

**Geprueft**
- Standalone-PHPUnit-Lauf installiert den Tutor ohne `local_elediaai_core` und
  deckt den erfolgreichen LLM-only-Chat ab.
- Der Integrationslauf installiert `local_elediaai_core`; PHPUnit prueft die
  stunden- und tagesbezogene Quota-Buchung mit `requestcount = 1` je Fenster
  sowie die Sperre des Folge-Requests.
- `version.php` weist keine harten Plugin-Abhaengigkeiten aus; README-Dateien,
  Feature-Doku und Entwickler-Doku nennen die Quota-Integration optional.

**Nicht lokal ausgefuehrt**
Die vollstaendigen Installationsmatrizen benoetigen die Forgejo-Container. Die
lokalen PHP-Lint-, Diff- und Matrix-Checks werden im Issue-Abschluss aufgefuehrt;
die beiden Installationsvarianten sind als harte CI-Gates konfiguriert.

### bug26 DDL-Kollision nach Komponenten-Rename

Feature:  feat05
Severity: S1
Status:   fixed
Linked:   task17, test11, SUI-378

**Beschreibung**
Moodle behandelte `block_elediaai_tutor` als neue Komponente, während deren
`install.xml` weiterhin die vorhandenen `block_eledia_aitutor_*`-Tabellen
anlegen wollte. Die Installation brach deshalb vor dem Install-Hook mit
`Table already exists` ab.

**Fix 2026-07-31**
Das Standardschema verwendet kanonische Tabellennamen. Ein idempotenter
Install-/Upgrade-Migrator übernimmt den Altbestand vollständig und löscht die
alten Tabellen erst nach erfolgreichem Datentransfer.

### test11 Datenmigration nach Komponenten-Rename

Feature:  feat05
Status:   implemented
Linked:   task17, bug26, SUI-378

**Geprueft**
- `component_migration_test.php` bildet eine Altinstallation mit Gesprächs-ID,
  Einstellung, Blockinstanz, Datei, LTM-Präferenz und Capability-Override nach.
- Der Test verifiziert kanonische Zielwerte, die Entfernung der Altwerte und
  einen idempotenten zweiten Lauf.
- Frische Installationen beziehen ausschließlich kanonische Tabellen aus
  `install.xml`; bereits installierte Rename-Stände laufen über Savepoint
  `2026073106`.

### bug27 Selbstloeschung liess lokale personenbezogene Daten bestehen

Feature:  feat04
Severity: S1
Status:   fixed
Linked:   task18, test12, SUI-383

**Beschreibung**
`deletion_service::delete_all_for_user()` entfernte nur Gespraechszeiger und
Fragenprotokolle. Consent, Nutzung, Diagnostik und die LTM-Praeferenz blieben
trotz bestaetigter Komplettloeschung erhalten.

**Fix 2026-07-31**
Der zentrale Dienst entfernt alle sechs lokalen Speicherbereiche, liefert
nachvollziehbare Einzelzaehler und wird direkt von UI und Privacy API genutzt.
Die Oberflaeche setzt LTM und Consent-Zustand zurueck und unterscheidet externe
Erfolge von externen Fehlern.

### test12 Vollstaendige Selbstloeschung

Feature:  feat04
Status:   passed-with-environment-note
Linked:   task18, bug27, SUI-383

**Geprueft**
- `deletion_service_test.php` befuellt alle fuenf nutzerbezogenen Tabellen und
  die LTM-Praeferenz fuer zwei Personen; nur die Zielperson wird geloescht.
- Rueckgabe und Audit-Event enthalten Gesamt- und Einzelzaehler; LTM ist danach
  aus und das Consent-Gate ist wieder scharf.
- Ein simulierter externer HTTP-Fehler erhoeht `externalfailed`, waehrend alle
  lokalen Daten trotzdem entfernt werden.
- `privacy_controls.feature` prueft Erfolgsmeldung und erneute First-Use-
  Bestaetigung nach Reload.

**Ergebnis 2026-07-31**
- Moodle 4.5.12+: 154 PHPUnit-Tests, 800 Assertions, 18 erwartete Skips;
  bestanden.
- Moodle 5.2.1+: 154 PHPUnit-Tests, 800 Assertions, 18 erwartete Skips;
  bestanden.
- Moodle CodeSniffer fuer alle in SUI-383 geaenderten PHP-Dateien, PHP-Lint,
  AMD-Lint/-Build und `git diff --check`: bestanden. Der komplette
  Plugin-CodeSniffer bleibt wegen der vorbestehenden Befunde in
  `classes/local/icon.php` rot (siehe `test07`).

**Nicht verifiziert**
Der gezielte Behat-Browserlauf erreichte Moodle 4.5 ueber Chrome/Selenium,
lief aber beim kalten Asset-Aufbau in den 30-Sekunden-Navigationstimeout des
lokalen Single-Worker-PHP-Servers. Nach zwei Infrastrukturversuchen wurde der
Best-Effort-Lauf beendet; es gab keinen Assertion-Fehler im Szenario.

### bug29 Kontoloeschung liess Tutor-Daten bestehen

Feature:  feat04
Severity: S1
Status:   fixed
Linked:   task20, test14, SUI-423

**Beschreibung**
Der `user_deleted`-Observer entfernte nur Consent und Nutzungszaehler.
Gespraechszeiger, Fragenprotokolle, Diagnostik und die LTM-Praeferenz konnten
das geloeschte Moodle-Konto ueberdauern. Nach dem Event war ausserdem kein
neues, sicher nutzbares User-MCP-Token fuer die externe Loeschung mehr
erzeugbar.

**Fix 2026-07-31**
Der Moodle-4.5+-Hook `before_user_deleted` ruft die zentrale Komplettloeschung
auf, solange das Konto aktiv ist. Das nachgelagerte `user_deleted`-Event nutzt
dieselbe Routine als idempotenten lokalen Fallback. Externe Fehler bleiben
best effort und blockieren die lokale Bereinigung nicht.

### test14 Vollstaendige Loeschung beim Moodle-Kontoloeschpfad

Feature:  feat04
Status:   passed-with-environment-note
Linked:   task20, bug29, SUI-423

**Geprueft**
- `consent_test.php::test_user_deleted_observer()` befuellt alle fuenf
  nutzerbezogenen Plugin-Tabellen und die LTM-Praeferenz fuer zwei Personen.
- Der Test loescht eine Person mit Moodles realem `delete_user()`-Ablauf und
  verifiziert die sechs Loeschzaehler sowie den unveraenderten
  Vergleichsdatenbestand.
- `deletion_service_test.php` belegt fuer das konfigurierte User-Loeschwerkzeug,
  dass ein simulierter Backendfehler als `externalfailed` gezaehlt wird und
  keine lokale Loeschung verhindert.

**Ergebnis 2026-07-31**
- Moodle 5.2.1+ / PHP 8.3 / PostgreSQL 16: gezielter Kontoloeschtest mit
  5 Tests und 35 Assertions sowie vollstaendige Plugin-Suite mit 162 Tests,
  827 Assertions und 18 erwarteten Skips bestanden.
- Vollstaendiger Tutor-Behat-Lauf: 25 Szenarios und 297 Schritte bestanden.
  Die seit Juli veraltete exakte Erwartung fuer den Missing-Add-on-Hinweis
  wurde dabei an die bereits ausgegebene Komponentenliste angeglichen.
- Moodle-CodeSniffer fuer alle geaenderten PHP-Dateien, PHP-Lint und
  `git diff --check`: bestanden. Der Voll-Plugin-CodeSniffer meldet weiterhin
  ausschliesslich die vorbestehenden Befunde in `classes/local/icon.php` und
  `classes/local/home_shortcuts.php` (siehe `test07`).

**Nicht lokal verifiziert**
Eine Moodle-4.5-Testsite war lokal nicht vorhanden; der isolierte
Moodle-4.5-Container liess sich in der ausgelasteten Docker-Umgebung nicht
starten. Die konfigurierte harte CI-Matrix fuer 4.5 und 5.2 bleibt daher die
zusaetzliche Versionsgrenzen-Verifikation.

### bug28 Client-Kontext konnte Kurs-Capabilities umgehen

Feature:  feat01
Severity: S1
Status:   fixed
Linked:   task19, test13, SUI-414

**Beschreibung**
Chat- und Verlaufsendpunkte prueften die Capability im frei gelieferten
System-, Kurs- oder Blockkontext, unabhaengig vom Kurs der Anfrage oder der
gespeicherten Unterhaltung. Dadurch konnten restriktivere Kurs-/Blockregeln
umgangen und Kursgespraeche nach verlorenem Kurszugriff weiter gelesen werden.

**Fix 2026-07-31**
Eine gemeinsame Scope-Aufloesung validiert Tutorblock-Komponente und
Kursbindung. Bestehende Unterhaltungen liefern den autoritativen Kurs;
Kurszugriff und Capability werden vor Ausgabe oder Fortsetzung erneut im
gebundenen Kontext geprueft. Der globale Verlauf liefert nur globale
Unterhaltungen.

### test13 Autoritative AJAX-Scope-Pruefung

Feature:  feat01
Status:   passed
Linked:   task19, bug28, SUI-414

**Abdeckung**
- Abweichender Systemkontext und fachfremder Blockkontext werden abgelehnt.
- Gueltige System-, Kurs- und Tutorblockablaeufe bleiben erhalten.
- `CAP_PROHIBIT` im Kurs- und konkreten Blockkontext wird erzwungen.
- Liste und RAG-Verlauf verweigern den Zugriff nach Entzug der Einschreibung.
- Eine bestehende Kursunterhaltung kann ihren gespeicherten Kurs nicht durch
  ausgelassene Client-Parameter verlieren.

**Geprueft 2026-07-31**
- Moodle 5.2.1 / PHP 8.4 / MariaDB 11.4:
  `external_test.php` mit 19 Tests und 38 Assertions gruen.
- Moodle-CodeSniffer fuer alle geaenderten PHP-Dateien: 0 Errors, 0 Warnings.
- Der zusaetzlich gestartete Voll-Plugin-PHPCS-Lauf findet ausschliesslich
  bestehende Befunde in den unveraenderten Dateien `home_shortcuts.php` und
  `icon.php`; diese liegen ausserhalb von SUI-414.

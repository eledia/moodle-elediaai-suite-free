# Tasks

Dies ist das operative Zentrum fuer die Arbeit am aiTutor.

---

## Neu

Unstrukturierter Input landet hier und wird in `taskXX` oder `qXX` triagiert.

- Uncommittete Aenderung in `classes/hook_callbacks.php`: Feinjustierung der
  SVG-Pfaddaten des Admin-Launcher-Icons (rein optisch, kein Verhalten). Bei
  naechstem Commit mitnehmen.

---

## Klaerung benoetigt

### q01 Lokale RAG-URL fuer Docker final festlegen
Linked: feat01 / feat02
Asked-by: KI
Status: open

**Frage**
Soll der lokale Tutor gegen `local_literag` in derselben Moodle-Instanz laufen
oder gegen den manuellen Testserver unter `tests/manual/tutor_mcp_server.py`?

**Kontext**
Die Browser-URL `http://localhost:8080/...` ist aus dem Moodle-Container nicht
automatisch derselbe Netzwerkpfad. Fuer End-to-End-Tests muss die RAG-URL
serverseitig erreichbar sein.

---

## Tasks

### task01 DevFlow initial anlegen
Status:    done
Feature:   -
Prioritaet: P1

**Ziel**
Projektbezogenen DevFlow auf Branch `review_johannes` anlegen.

**Ergebnis**
`public/blocks/elediaai_tutor/docs/` enthaelt die sechs DevFlow-Hauptdokumente
sowie die Zusatzdokumente `privacy.md` und `security.md`.

### task02 Lokalen Tutor-End-to-End-Smoke pruefen
Status:    done
Feature:   feat01 / feat02
Prioritaet: P1
Linked:    q01, test01

**Ziel**
Eine Chat-Nachricht in `http://localhost:8080` erfolgreich durch den Block bis
zum RAG/Tutor-MCP-Endpunkt schicken und eine Antwort erhalten.

**Schritte**
1. Ziel-RAG-Server festlegen (`q01`).
2. Tutor-Block-Einstellungen pruefen.
3. Als Moodle-Nutzer mit `block/elediaai_tutor:use` Chat oeffnen.
4. Testnachricht senden.
5. Ergebnis und Logs in `05-quality.md` dokumentieren.

**Erwartetes Ergebnis**
Der Tutor antwortet ohne Konfigurationsfehler.

**Ergebnis 2026-08-04**
Der lokale Smoke wurde gegen den mitgelieferten manuellen MCP-Testserver
`tests/manual/tutor_mcp_server.py` verifiziert und in `test01` dokumentiert.
Die aktive 8080-Instanz war wegen Alt-Komponenten-DB-Stand
(`block_eledia_aitutor`) gegen neuen Code (`block_elediaai_tutor`) nicht
upgradefaehig; der reproduzierbare Lauf erfolgte deshalb im bereits migrierten
`demo-webserver-1` unter `http://localhost:9501`.

### task03 Premium-/Review-Aenderungen dokumentieren
Status:    done
Feature:   feat03
Prioritaet: P2

**Ziel**
Die aktuell im Workspace vorhandenen Aenderungen rund um Registry, Tutor Profile
und `premium.php` in DevFlow-Dokumentation einordnen, sobald ihr Scope bestaetigt
ist.

**Hinweis**
Der Workspace hatte diese Aenderungen bereits vor dem DevFlow-Anlegen. Sie
wurden nicht veraendert.

**Ergebnis 2026-06-26**
Der Scope ist weiterhin als bestehende Workspace-Aenderung dokumentiert. Keine
zusaetzlichen Code-Aenderungen wurden allein fuer diesen Task vorgenommen.

### task04 Code-Review `review_johannes` abarbeiten
Status:    done
Feature:   feat01 / feat02 / feat03
Prioritaet: P0
Linked:    bug02, bug03, bug04, bug05, bug06, bug07, bug08, test02

**Ziel**
Die Findings aus `CODE_REVIEW_review_johannes.md` umsetzen und die kritischen
Sicherheitsbefunde vor weiterer UX-Arbeit schliessen.

**Umgesetzt**
- `block_elediaai_tutor.php`: `MOODLE_INTERNAL`-Guard ergaenzt.
- `db/upgrade.php`: nacktes `unserialize()` durch `unserialize_object()` ersetzt.
- LTM-Sync: `ltm::sync_to_rag()` bricht ohne Privacy-Consent ab.
- Citation-URLs: Server-seitige `PARAM_URL`-Normalisierung in Live- und History-Flows.
- Admin-Navbar-Hook: Inline-JS/CSS entfernt; CSS liegt in `styles.css`, Verhalten in AMD.
- Produktiv-PHP: alte Context-Alias-Klassen auf `\core\context\...` umgestellt.
- Entry-Points: `declare(strict_types=1)` ergaenzt.
- `version.php`: `$plugin->supported = [405, 502]` ergaenzt.
- `manage_tutors.php`: direkte Rendering-DB-Abfrage fuer Blockinstanzen in
  `tutor_apply::instance_records()` ausgelagert.

**Verifikation**
- Lokaler PHP-Lint ueber alle Plugin-PHP-Dateien: gruen.
- Container PHP-Lint ueber alle Plugin-PHP-Dateien: gruen.
- JS-Syntaxcheck fuer `amd/src/admin_launcher.js`: gruen.
- Moodle-Container wurde aktualisiert und Caches wurden gepurged.
- PHPUnit konnte nicht ausgefuehrt werden, weil die lokale Moodle-PHPUnit-
  Konfiguration nicht initialisiert ist (`@root@` in `phpunit.xml.dist`).

### task05 UX/UI-Review `review_johannes` abarbeiten
Status:    done
Feature:   feat01 / feat03 / feat04
Prioritaet: P1
Linked:    bug09, bug10, bug11, bug12, test03

**Ziel**
Die Findings aus dem UX/UI-Review vom 2026-06-26 in die Plugin-Shell,
Settings-Shell und Tutor-Oberflaechen uebertragen, ohne die Moodle-native
Bedienbarkeit zu ueberstylen.

**Review-Schwerpunkte**
- Kein Help-Link auf `/local/lernhive/support.php`; die Tutor-Shell muss ihre
  Hilfe plugin-eigen bereitstellen.
- Settings-Shell robust gegen fehlende `headerHtml`-Konfiguration machen.
- Fokus- und Tastaturbedienung fuer zentrale Controls sichtbar halten.
- Fallback-CSS fuer im Template angebotene `lh-*`-Slots vollstaendig machen.
- Dekorative Preview-Elemente fuer Assistive Technology ausblenden.
- Root-Markdown aus Review/Handover in DevFlow ueberfuehren.

**Umgesetzt**
- Root-Dateien `HANDOVER.md`, `CODE_REVIEW_review_johannes.md` und
  `UX_REVIEW_review_johannes.md` in DevFlow einsortiert.
- Override-Checkboxen in der Settings-Shell wieder Moodle-schlicht gestaltet:
  kein Spezial-Pill fuer `Standard: Ja/Nein`, keine fette
  `Ueberschreiben erlauben`-Beschriftung.
- `bug09` (2026-06-27/28): Help-Link der Plugin-Shell zeigt auf
  `/blocks/elediaai_tutor/help.php`. Die Seite rendert die plugin-eigene
  Dokumentation aus `docs/02-user-doc*.md`; `local_lernhive` ist fuer Hilfe
  nicht erforderlich.

**Offen**
- Keine offenen Code-Findings aus task05.

**Ergebnis 2026-06-27**
- `bug10` gefixt: `settings_shell.js` bricht robust ab, wenn `headerHtml`
  fehlt; Fallback-CSS fuer `lh-plugin-infobar`, Tags, Header-Buttons und
  Row-Actions ergaenzt.
- `bug11` gefixt: Fokus-Ringe fuer Send-Button, History-Open-Button,
  Settings-Karten und Backnav ergaenzt; History-Liste und Composer gelabelt;
  statischer Privacy-Callout nutzt `role="note"`.

### task06 Folge-Code-Review `review_johannes` abarbeiten
Status:    done
Feature:   feat01 / feat03 / feat04
Prioritaet: P0
Linked:    bug13, bug14, bug15, bug16, bug17, test04

**Ziel**
Die neuen Befunde aus dem Folge-Review vom 2026-06-26 schliessen. Prioritaet
liegt auf der Stored-XSS-Flaeche `bug13` (SVG-Upload), gefolgt von `bug14`
(`customcss`-Breakout).

**Schwerpunkte**
- SVG-Uploads sanieren oder als Download ausliefern (`bug13`, S2).
- `customcss` gegen `</style>`-Breakout absichern (`bug14`, S3).
- Fokusring fuer den History-Open-Button ergaenzen (`bug15`, S3).
- Heading-Reihenfolge im Privacy-Modal korrigieren (`bug16`, S4).
- `appendFailure`-HTML-Muster entschaerfen oder dokumentieren (`bug17`, S4).

**Stand 2026-06-27**
- `bug13` (S2) gefixt: Force-Download in `lib.php` plus gehaertetes
  `tutor_io::is_safe_image()`.
- `bug14` (S3) gefixt: `customcss` wird serverseitig gegen `</style>`-
  Breakout, `@import`, `expression()` und `javascript:` gehaertet.
- `bug15` (S3) gefixt: History-Open-Button hat sichtbaren `:focus-visible`.
- `bug16` (S4) gefixt: Privacy-Modal nutzt geordnete Abschnittsueberschriften.
- `bug17` (S4) gefixt: Fehlertexte laufen ueber escapeten Template-Slot statt
  ueber den `{{{html}}}`-Slot.

### task07 Moodle CodeChecker (moodle-cs) gruen bekommen
Status:    done
Feature:   -
Prioritaet: P1
Linked:    bug02, bug18, test05

**Ziel**
Das Plugin gegen den offiziellen `moodlehq/moodle-cs`-Standard sauber bekommen
(Vorbereitung Plugins-Directory-Submission).

**Ausgangslage**
920 Errors + 660 Warnings ueber 50 Dateien (rein Coding-Style, keine
Security-/Korrektheitsbefunde).

**Umgesetzt 2026-06-27**
- PHPCBF-Autofix: 892 Layout-Verstoesse in 47 Dateien automatisch behoben.
- `manage_tutors.php`: phpcbf-Oszillation manuell aufgeloest (Header-Reihenfolge
  `boilerplate -> Docblock -> declare`, isolierter `FunctionCallSignature`-Lauf,
  Rest-Konstrukte von Hand kanonisiert).
- Entry-Dateien `view.php`, `report.php`, `instance_tutor.php`,
  `edit_instance.php`: gleiche `declare`-Reihenfolge korrigiert.
- Docblocks fuer `plugin_page`-Konstanten und `widget::render()` ergaenzt;
  `provider`-Implements-Liste umgebrochen; lange Privacy-Zeile entschaerft;
  `lib.php`-Callback-Variable umbenannt (`$birecord_or_cm` -> `$birecordorcm`).
- `form/element_eatcolour.php`: QuickForm-API-Overrides (camelCase-Methoden,
  `$_helpbutton`) mit gezielten `phpcs:ignore`-Annotationen versehen.
- Inline-Kommentare bereinigt (Grossschreibung, Separatoren, `.eat-*`-Marker).
- Schritt 3: `MOODLE_INTERNAL`-Guard aus den 6 markierten Dateien entfernt
  (siehe `bug02`-Reconciliation).
- Schritt 2: Lang-Strings (EN + DE, je 658) alphabetisch sortiert und
  eingestreute Abschnitts-Kommentare entfernt.

**Ergebnis**
`Codechecker: 0 errors, 0 warnings` (siehe `test05`). Beifang: `bug18`
(ungenutzte Konstante `MODIFIER_COMPACT`) in SUI-53 gefixt.

**Hinweis**
Die `amd/build/*.min.js` wurden nicht neu gebaut; CodeChecker betrifft nur PHP.

### task08 Behat-Abdeckung fuer Plugin-Shell und Kurskontext erweitern
Status:    done
Feature:   feat01 / feat03
Prioritaet: P1
Linked:    test06

**Ziel**
Die nach dem UX-Umbau zentralen Admin-Flows nicht nur per PHPUnit, sondern auch
als Browser-Journeys absichern.

**Umgesetzt 2026-06-28**
- `plugin_shell.feature`: Dashboard, Tutorenverwaltung und Vorschau werden in
  der Plugin-Shell geprueft; die Moodle-Blockleiste darf auf diesen Shell-Seiten
  nicht erscheinen.
- Dashboard-Test fuer fehlende Zusatzplugins: ein einziger Hinweis oberhalb des
  Wizards plus `Plugin missing`-Status.
- Tutorenverwaltung: Action-Icons fuer `Create tutor` und `Import`.
- `course_context.feature`: Kursbezogene Standalone-Seite bleibt ohne Tutor-
  Block gesperrt und oeffnet erst, wenn der Block im Kurs vorhanden ist.

**Ausstehend**
- In CI mit voll initialisiertem Behat-Profil ausfuehren:
  `vendor/bin/behat --tags @block_elediaai_tutor`.

### task09 Hero-/AI-Home-Erlebnis in der Moodle App (Release 3)
Status:    geplant
Feature:   feat (Dashboard-Hero, AI-Home)
Prioritaet: P2
Linked:    -

**Ziel**
Die App oeffnet den Tutor derzeit als klassisches eingebettetes Chat-Widget
(`db/mobile.php` -> `classes/output/mobile.php` -> `<core-iframe>` auf
`view.php?embedded=1`). Der Release-2-Hero-Modus ("Heute schon gemoodlet?",
rollenbasierte Pills, Tagesbriefing) erscheint in der App nicht, weil er in
`home.php` / dem Dashboard-Block sitzt. In Release 3 soll die App dieselbe
AI-Home-UX zeigen.

**Umsetzungsplan**
- `view.php` einen optionalen Schalter geben (`hero=1` PARAM_BOOL), der die
  Hero-Variante rendert: intern `widget::render($ctx, 0, ['dashboard' => 1])`
  statt des klassischen embedded Widgets. Nur fuer den globalen Kontext
  (courseid 0) sinnvoll; im Kurskontext beim klassischen Chat bleiben.
- `classes/output/mobile.php::iframe_response()` fuer den globalen Handler
  `hero=1` an die URL haengen. Kurs-Handler unveraendert lassen.
  Alternative pruefen: direkt `home.php?embedded=1` einbetten statt
  `view.php?hero=1` — `home.php` muss dann den `embedded`-Pagelayout
  unterstuetzen (aktuell fest `standard`). `view.php`-Weg ist kleiner und
  haelt eine Einstiegs-Datei; bevorzugen.
- CSS: die Hero-Sticky-Composer-Leiste im chrome-losen `pagelayout-embedded`
  pruefen — `position: sticky; bottom` gegen `100vh`-Iframe testen, ggf.
  Safe-Area-Insets (`env(safe-area-inset-bottom)`) fuer Notch-Geraete.
- Rollen-Pills: `user_audience::resolve()` funktioniert in der App identisch
  (serverseitig), MUC-Cache greift. Kein Extra-Aufwand erwartet.
- `tests/mobile_test.php` um einen Fall fuer den Hero-Handler erweitern
  (`hero=1` in der iframe-URL, nur global).
- Manuell in der Moodle App gegen demo.eledia.ai testen: Auto-Login im
  core-iframe, Hero-Darstellung, Pill-Klick loest MCP-Aktion aus,
  Briefing-Button.

**Offene Entscheidung**
- Soll der App-Haupteintrag komplett auf Hero umstellen, oder bleibt der reine
  Chat als Standard und Hero ist ein Site-Schalter? Empfehlung: Site-Schalter
  (z.B. `mobileherohome`), damit Institutionen ohne AI-Home-Startseite die
  schlanke Variante behalten.

### task10 Vendored LernHive-Shell-CSS mit local_lernhive synchronisieren
Status:    done
Feature:   -
Prioritaet: P2
Linked:    lernhive `product/19-ux-a11y-audit-2026-07.md`, `product/adrs/adr-p14-*`

**Ausloeser**
Das UX-/A11y-Audit der LernHive-Plugins (2026-07-20) hat nachgewiesen, dass
`blocks/elediaai_tutor/styles.css` eine **vendored Kopie** des geteilten
Plugin-Shell-CSS aus `local_lernhive` enthaelt (`body:not(.theme-lernhive)
.lh-plugin-*`). Zentrale Fixes in `local_lernhive` erreichen diese Kopie nicht:
Ein dort gesetzter Fokus-Ring wurde zur Laufzeit von diesem Klon wieder
ueberschrieben (Ladereihenfolge der Plugin-Stylesheets).

**Ergebnis (umgesetzt 2026-07-20)**
Drei vendored `lh-*`-Regeln hatten `outline: none` im Fokus-Zustand, ersetzt nur
durch einen Hintergrund-Tint (~1.04:1 gegen Weiss) — Tastaturfokus praktisch
unsichtbar (WCAG 2.4.7 / ux-system §9 A2):
- `.lh-plugin-section-nav__item:focus-visible` → `outline: 2px solid var(--lh-accent)`
- `.lh-plugin-header__action:focus-visible` → `outline: 2px solid var(--lh-primary)`
- `.lh-btn-default:focus-visible` → `outline: 2px solid var(--lh-accent)`
Werte gespiegelt aus `local_lernhive/styles.css` (kanonische Quelle).

Zusaetzlich zwei **eigene** `.eat-*`-Komponenten gefunden, die `outline: none`
ohne jeden Ersatzindikator setzen — Fokus war nicht von Hover unterscheidbar:
- `.eat-admin-launcher-host__link:focus-visible`
- `.eat-instance-shell .eat-preview-float__toggle:focus-visible`
Beide nutzen jetzt das im Plugin etablierte Muster
`box-shadow: 0 0 0 2px var(--eat-focus-ring)` (vgl. `.elediaai_tutor-history-open`).

**Dauerhafte Konsequenz**
Der vendored `lh-*`-Block ist ein Fork und driftet. Bei Aenderungen am geteilten
Plugin-Shell-CSS in `local_lernhive` muss diese Kopie mitgezogen werden. Die
aktuelle Kopie ist ein aelterer Snapshot als `local_lernhive` (sie enthaelt
`lh-btn-default`, das dort nicht mehr existiert). Mittelfristig pruefen, ob der
Block ganz entfallen kann, sobald `local_lernhive` als Abhaengigkeit gesetzt ist.

### task11 Privacy-API-Löschung zum RAG-Dienst propagieren
Status:    done
Feature:   feat04
Prioritaet: P1
Linked:    Review-Finding `2026-07-08-G3-003`

**Ergebnis (umgesetzt 2026-07-25)**
Alle drei administrativen Moodle-Privacy-Löschpfade verwenden jetzt denselben
best-effort RAG-Löschdienst wie die benutzerseitige Aktion. Einzel-, Batch- und
Systemkontext-Löschungen entfernen damit bei konfiguriertem
`tutor_delete_user_data` auch externe Transkripte und Langzeitgedächtnis.
Ein externer Fehler wird im Developer-Debug protokolliert, blockiert aber nie
die lokale Löschung.

### task12 Doku nachziehen: Teacher-Copilot, Frageanalyse-Report und Diagnostics
Status:    open
Feature:   - (Doku)
Prioritaet: P2
Linked:    -

**Kontext (DevFlow-Sync 2026-07-31)**
Mehrere wesentliche, im Code umgesetzte und teils getestete Subsysteme haben
keine Entsprechung in `01-features.md`/`03-dev-doc.md`:

- **Frageanalyse-Report** (`report.php`, `classes/local/question_log.php`,
  Capability `block/elediaai_tutor:viewreports`, `amd/src/copilot_report.js`):
  kursbezogene, namenfreie Aggregation der Tutor-Fragen (Zusammenfassung,
  Fragen pro Tag, jüngste Fragen); Tests: `question_log_test.php` (8).
- **Teacher-Copilot** (`classes/local/copilot_service.php`,
  `classes/external/generate_copilot_analysis.php`): KI-Analyse der geloggten
  Kursfragen über das reguläre Chat-Tool, bewusst außerhalb des Chat-Logs;
  Tests: `copilot_service_test.php` (3).
- **Reclustering** (`classes/local/recluster_service.php`, Tasks
  `recluster_questions`/`prune_question_log`, Tool `tutor_recluster_questions`):
  im dev-doc nur als optionales Tool erwähnt, ohne Service-/Task-Beschreibung;
  Tests: `recluster_service_test.php` (6).
- **Diagnostics** (`classes/local/diagnostics.php`): Aufzeichnung/Abfrage der
  jüngsten RAG-Fehlerphasen — in keiner Doku erwähnt.

**Ziel**
`01-features.md` um ein Feature „Teacher-Copilot / Frageanalyse" ergänzen und
`03-dev-doc.md` um Report-/Copilot-/Recluster-/Diagnostics-Pfade erweitern.
Reine Doku-Aufgabe; kein Code-Änderungsbedarf durch diesen Task.

### task13 Standalone-Tutor an Plugin-Shell-Breite angleichen
Status:    done
Feature:   feat01
Prioritaet: P1
Linked:    bug24, test07, SUI-368

**Ausloeser**
Die Plugin-Shell von `view.php` wirkte schmaler als die RagIngest-Referenz,
obwohl beide denselben kanonischen Shell-Token verwenden.

**Ergebnis 2026-07-31**
Die Ursache war eine zweite Maximalbreite von 860 px auf
`.elediaai_tutor-page` innerhalb der bereits begrenzten Plugin-Shell. Ein
shell-spezifischer Override hebt nur diese innere Grenze auf. Eingebettete und
ungeshellte Tutor-Seiten behalten die kompakte Breite.

### task14 Supportvertrag und CI/API-Nutzung konsistent machen
Status:    done
Feature:   Supportvertrag
Prioritaet: P2
Linked:    SUI-374

**Ergebnis 2026-07-31**
- Plugin-Metadaten und beide READMEs auf Release 0.19.5, Moodle 4.5–5.2 und
  PHP 8.3+ synchronisiert.
- Moodle 4.2–4.4 als nicht unterstuetzt benannt, da keine Legacy-Callbacks
  fuer die Hooks-basierte Integration existieren.
- Forgejo-CI prueft betroffene Gruppen in der schnellen Spur mit harten
  Installations-, PHP-Lint- und PHPUnit-Gates auf Moodle 4.5 und 5.2.
- `hook_callbacks_test.php` ist in der PHPUnit-Suite jeder Supportgrenze
  enthalten.

### task15 Tutor-Block auf `elediaai_tutor` umbenennen
Status:    done
Feature:   feat05
Prioritaet: P1
Linked:    SUI-377

**Ergebnis 2026-07-31**
- Pluginpfad, Dateinamen, Namespace, Komponente, Sprachdateien, AMD-Module,
  Capabilities, Webservice-Namen, URLs und Behat-Tags auf `elediaai_tutor`
  umgestellt.
- Aktive Suite-Integrationen und CI-/Deploy-Pfade synchronisiert.
- Release auf 0.19.5 / Version `2026073104` erhöht.
- Persistente Tabellen-/Service-Account-Namen wurden in diesem ersten Rename-
  Schritt unverändert gelassen; `task17` ergänzt die anschließend gewählte
  Datenmigration der Tabellen und Präferenzen.
- Das externe GitHub-Repository konnte mit dem vorhandenen Token nicht
  umbenannt werden, weil keine Admin-Rechte vorliegen.
### task16 Optionale local_elediaai_core-Quota kapseln
Status:    done
Feature:   feat01 / feat02
Prioritaet: P1
Linked:    SUI-370

**Ziel**
Den Tutor ohne harte Abhaengigkeit auf `local_elediaai_core` installieren und im
LLM-only-Betrieb nutzen koennen, ohne die zentrale Token-Quota bei installierter
Suite-Integration zu verlieren.

**Ergebnis 2026-07-31**
- `token_quota` kapselt Ermittlung, Tokenpruefung und Nutzungsbuchung. Ohne
  `local_elediaai_core` greift ein lokaler Schaetz-Fallback; die gemeinsame Quota
  wird kontrolliert uebersprungen.
- Die CI prueft den Tutor separat ohne `local_elediaai_core` sowie in einer
  Vertragsgruppe mit installiertem Plugin. PHPUnit prueft dort je erfolgreiche
  Chat-Runde genau eine Quota-Buchung und die Sperre des Folge-Requests.
- Version, README, Feature-/Architektur-Doku und CI-Matrix beschreiben denselben
  optionalen Abhaengigkeitsvertrag.

### task17 Persistenz nach Komponenten-Rename migrieren
Status:    done
Feature:   feat05
Prioritaet: P0
Linked:    bug26, test11, SUI-378

**Ziel**
Eine bestehende `block_eledia_aitutor`-Installation ohne DDL-Kollision und ohne
Datenverlust auf `block_elediaai_tutor` übernehmen.

**Ergebnis 2026-07-31**
- `install.xml` verwendet kanonische Tabellennamen; dadurch kollidiert die
  Installation der neuen Komponente nicht mehr mit den Alt-Tabellen.
- Der Install-Hook und Upgrade-Savepoint übertragen Tabellenzeilen ID-stabil,
  Konfiguration, Blockinstanzen, Capability-Zuweisungen, Dateien, Tasks,
  externe Funktionen und die LTM-Präferenz.
- Alt-Tabellen werden erst nach erfolgreichem Transfer entfernt; der Ablauf ist
  wiederholbar und durch einen PHPUnit-Recovery-Test abgesichert.

### task18 Selbstloeschung auf alle lokalen Tutor-Daten erweitern
Status:    done
Feature:   feat04
Prioritaet: P1
Linked:    bug27, test12, SUI-383

**Ergebnis 2026-07-31**
- AJAX-Endpunkt und Privacy API verwenden denselben zentralen Loeschdienst.
- Gespraechszeiger, Fragenprotokolle, Consent, Nutzung, Diagnostik und
  LTM-Praeferenz werden vollstaendig und nutzerbezogen entfernt.
- Rueckgabe, Audit-Event und Erfolgsmeldung unterscheiden lokale Zaehler,
  externe Unterstuetzung und externe Fehler; ein RAG-Fehler blockiert die
  lokale Loeschung nicht.
- PHPUnit deckt alle lokalen Speicherorte, die Abgrenzung zu einer zweiten
  Person und den externen Fehlerpfad ab; Behat prueft das erneute Consent-Gate.

### task19 AJAX-Kontext an Kurs und Unterhaltung binden
Status:    done
Feature:   feat01
Prioritaet: P1
Linked:    bug28, test13, SUI-414

**Ergebnis 2026-07-31**
- System-, Kurs- und Tutorblockkontexte werden serverseitig an globalen Chat,
  den exakten Kurs beziehungsweise die konkrete Blockinstanz gebunden.
- Fremde Blockkomponenten und abweichende Kontexte werden abgelehnt;
  bestehende Unterhaltungen behalten ihren gespeicherten Kurs als Scope.
- Liste, Verlauf, Fortsetzung und Einzelloeschung pruefen aktuellen Kurszugriff
  und die Capability im gebundenen Kontext. Globaler Verlauf enthaelt nur
  globale Unterhaltungen.

### task20 Kontoloeschung auf vollstaendige Tutor-Loeschung umstellen
Status:    done
Feature:   feat04
Prioritaet: P0
Linked:    bug29, test14, SUI-423

**Ergebnis 2026-07-31**
- Der Core-Pre-Delete-Hook verwendet die zentrale Loeschroutine, solange das
  Nutzerkonto und sein MCP-Token noch aktiv sind.
- Alle fuenf Tutor-Tabellen und die LTM-Praeferenz werden vollstaendig und
  nutzerbezogen entfernt; das `user_deleted`-Event bleibt als lokaler Fallback.
- Externe Transkripte und Langzeitgedaechtnis werden bei konfiguriertem
  User-Loeschwerkzeug best effort angefordert. Connector- oder Backendfehler
  blockieren weder die Kontoloeschung noch die lokale Bereinigung.

### task21 RAG-Authentifizierungstoken verschluesselt speichern
Status:    done
Feature:   feat01
Prioritaet: P2
Linked:    SUI-416

**Ergebnis 2026-08-02**
- Neue und rotierte Tokens werden ueber Moodles verschluesselte
  Passwort-Einstellung persistiert und erst im serverseitigen RAG-Client
  entschluesselt.
- Upgrade `2026080102` migriert vorhandene Klartextwerte idempotent; leere und
  bereits verschluesselte Werte bleiben unveraendert.
- PHPUnit deckt Speicherung, Migration, Leerwert, Rotation und die Verwendung
  in Bearer- sowie Custom-Headern ab.
- Die durch den geaenderten Sprachdatei-Scope sichtbar gewordene
  CodeChecker-Sortierung ist in Englisch und Deutsch bereinigt.

### task22 Gemeinsame Shell-CSS auf ADR-P14-Stand heben
Status:    done
Feature:   feat01
Prioritaet: P1
Linked:    SUI-393, lernhive ADR-P14

**Ergebnis 2026-07-31**
- `design/lh-core.css` enthält Zone-A-Actionbar, 44px Icon-Hit-Areas,
  sichtbare Fokus-Ringe und Full-Shell-Content-Breite.
- Tutor, Filter und LiteRAG verwenden den gestempelten `lh-core`-Block;
  Tutor-eigene `.eat-*`-Regeln bleiben ausserhalb des Markers.
- Sync-Design und beide Cross-Repo-Shell-Linter sind gruen.

### task23 Site-weiten Shell-Token-Leak schliessen
Status:    done
Feature:   -
Prioritaet: P1
Linked:    SUI-381

**Ergebnis 2026-08-02**
- Die Tutor-Shell nutzt den aus `design/lh-core.css` gestempelten
  `.lh-plugin-shell`-Scope; keine `body:not(.theme-lernhive)`-Regeln mehr.
- Tutor-Fallbackbreiten bleiben im ADR-P14-Modell: Default/Wide 72 rem,
  Reading-Inhalte 58 rem.
- `scripts/lint-vendored-shell.php` prueft repo-weit jede
  `public/**/styles.css` auf `--lh-*`-Deklarationen in `body`-/`:root`-Regeln.
- Ragingest 72-rem-Fallback bestaetigt: kein Wide-Modifikator, sondern der
  kanonische Default; Tutor-Reading-Seiten behalten 58 rem.

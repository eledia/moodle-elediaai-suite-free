# Technischer Plan: Audit und Transparenz, neu geschnitten

- **Status:** Umgesetzt — alle elf Pakete sind fertig und geprüft (Stand 20.09.2026). Stand je Paket unten unter „Umsetzungsstand".
- **Verhaltenseingabe:** Direkter Eingabevertrag (Betreibergespräche 17. und 19.09.2026), keine formale Spezifikation
- **Repository / Branch / Commit:** `ai_suite`, Branch `52`, `9a64731`
- **Entwurf der Oberflächen:** <https://claude.ai/artifact/QbvoaXMxekizpfWNaF7zt2> (sieben Flächen, mit dem echten Token-Satz gezeichnet)
- **Ziel-Moodle:** 4.5 bis 5.2 (`supported = [405, 502]`); CI prüft gegen `MOODLE_502_STABLE`
- **Betroffene Komponenten:** `local_elediaai_core`, `local_elediaai_chatengine`,
  `block_elediaai_tutor`, `local_aitransparency`, `webservice_elediamcp`

---

## Summary — das Konzept in fünf Sätzen

Die Suite protokolliert ihre KI heute an sechs Stellen, geschnitten nach
technischer Herkunft; künftig schneidet sie nach **Lebensdauer und
Personenbezug**, weil nur dieser Schnitt sich nicht wieder aufweicht.

Es entstehen **drei Schichten**: **A — Die Handlung** (was die KI getan hat,
**mit** Person, lange Frist, Aufsichts- und Nachweiszweck), **B — Der Turn**
(was gefragt und geantwortet wurde, worauf gestützt, **ohne** Person, kurze
Frist — und zugleich die didaktische Datenbasis), **C — Das Artefakt**
(stammt dieser Inhalt von einer KI, nur Hash, überlebt den Inhalt).

Schicht B kostet weniger, als sie klingt: `quota_aware_ai_manager` ist bereits
die einzige Stelle, durch die jeder KI-Aufruf der Suite läuft, und kennt dort
Komponente, Kontext, Prompt, Antwort und Tokens — **zwei Methoden, ein
Schreibaufruf, dreizehn Plugins abgedeckt**.

Schicht B macht den toten Tutor-Report wieder lebendig und zugleich schärfer:
Die neue Leitfrage ist nicht „was wurde gefragt", sondern **„was wurde gefragt,
das das Kursmaterial nicht beantwortet hat"** — und jeder Befund bekommt eine
Handlung, die in die Suite führt.

Dieselbe Basis trägt zwei neue MCP-Werkzeuge: Lehrkräfte fragen den Tutor
natürlichsprachig, was die Lernenden beschäftigt hat; Lernende fragen ihn, was
das System über sie weiß — **der Tutor liest B, nie A**, weil ein
Chat-Interface über ein personenbezogenes Handlungsprotokoll ein
Überwachungswerkzeug wäre.

In B steht statt einer Person ein **gesalzener Pseudonym-Schlüssel**: er zählt
Fragende, ohne eine zu benennen, und macht eine Löschanfrage beantwortbar
(Entscheidung vom 19.09.2026). Alle drei Fristen sind **einstellbar**, weil
drei Protokolle mit drei Aufgaben nicht dieselbe Frist verdienen.

---

## Umsetzungsstand (19.09.2026)

| Paket | Stand | Belegt durch |
|---|---|---|
| T-003 Komponenten-Auswertung | ✅ umgesetzt, **Quelle korrigiert** | `component_usage_test`, 9 Tests |
| T-002a Die vier Altlasten | ✅ umgesetzt | `registry_test`, Kacheltexte, CHANGELOG |
| T-001 Schicht B | ✅ umgesetzt | `turn_log_test`, 19 Tests |
| T-002b Audit liest B | ✅ umgesetzt | zwei Berichte auf `audit_technical.php` |
| T-010 Aufbewahrung | ✅ umgesetzt | drei Fristen, drei Tasks, Formular |
| T-004 Schicht A | ✅ umgesetzt, **Füllweg abweichend** | `action_log_test`, 9 Tests |
| T-005 Kurs-Einblicke | ✅ umgesetzt | `insights_page_test`, Tutor-Tests nachgezogen |
| T-006 MCP-Werkzeuge | ✅ umgesetzt | `mcp_tools_test`, 8 Tests |
| T-007 Schicht C lesbar | ✅ umgesetzt | `marker_test`, `verify.php`, `report.php` |
| T-008 Art. 50 als Vertrag | ✅ umgesetzt | Abs. 2 entsteht am Engpass für alle Komponenten; Abs. 1 sichtbar in `qtype_aitext` und `selfstudy`. Die übrigen Flächen fallen unter die Offensichtlichkeits-Ausnahme und stehen begründet in `marking_coverage_test` |
| T-009 Dokumentation | ✅ umgesetzt | Wegweiser-Kapitel, `03-dev-doc.md` (Datenmodell, Datenfluss, Abschnitt 6), drei CHANGELOGs, `00-master.md` AP2 richtiggestellt |
| T-011 Benennung und Gestaltung | ✅ umgesetzt | `audit_naming_test`, Design-Lint ohne neue Verstöße, Sichtprüfung aller fünf Flächen |

### Was beim Umsetzen anders kam als geplant

**Die Kennzeichnung war nicht zu wenig, sondern doppelt.** Der Plan ging davon
aus, dass Art. 50 Abs. 2 Fläche für Fläche ausgerollt werden muss. Nachgesehen
schrieben bereits **vier Stellen auf drei Ebenen** Herkunftsnachweise:
`aiprovider_eledia` für alles, was er erzeugt, `qtype_aitext` zusätzlich für
sich, dazu Chat-Engine und LiteRAG. Eine über diesen Anbieter bewertete
aitext-Frage hatte damit **zwei Belege für denselben Text** — vor dem Umbau,
nicht durch ihn. Der Nachweis entsteht jetzt am Engpass; der Anbieter tritt
zurück, solange `quota_aware_ai_manager::in_suite_call()` gilt, und behält sein
Schreiben für Aufrufe, die nie durch die Suite kamen.

**Und Abs. 1 verlangt weniger, als es zuerst aussah.** Die Ausnahme für das
Offensichtliche („unless this is obvious ... taking into account the context")
trägt die Werkzeuge der Lehrkräfte: wer ein Werkzeug öffnet, das die KI im
Namen führt, und das Ergebnis prüft, bevor es jemand anders sieht, wird nicht
getäuscht. Sichtbar wird es nur, wo eine lernende Person die Ausgabe
ungefiltert bekommt.

**T-011 stand nicht im Plan.** Der Plan beschrieb, *was* jede Fläche zeigt, und
nahm dabei die Begriffe des Bestands mit: „technisches Audit", „didaktisches
Audit". Beim ersten Durchgehen der fertigen Oberfläche fiel auf, dass ein Leser
diese Begriffe erst übersetzen muss, bevor er weiß, wo er klicken soll — und
dass dieselbe Fläche an drei Stellen drei verschiedene Namen trug. Das ist kein
Gestaltungsdetail, sondern die Frage, ob das Audit benutzbar ist. Ergebnis:
vier Flächen, vier Namen, wortgleich in Reiter, Karte und Seitentitel,
festgehalten von `audit_naming_test`.

Dabei kamen drei Fehler mit heraus, die kein Test gefunden hätte: zwei Reiter
auf dieselbe Seite, die Einstellungen ganz ohne Reiter, und zwei Überschriften
erster Ordnung auf den Kurs-Einblicken.

**Die Quelle von T-003 war falsch angenommen, und das ist der wichtigste Fund
dieser Runde.** Der Plan sagte, die Antwort auf „welches Feature wird benutzt"
liege bereits in `local_elediaai_core_usage`, weil dort eine `component`-Spalte
steht. Sie liegt dort nicht: der eindeutige Index dieser Tabelle ist
`(userid, rolebucket, windowtype, windowstart)` **ohne** Komponente. Es gibt
eine Zeile je Person und Fenster, und die Spalte hält nur fest, welches Feature
**zuletzt** gebucht hat — ihr eigener Privacy-String sagt das wörtlich
(„the component that last updated the quota row"). Ein Test hat es aufgedeckt,
ein zweiter hält die Eigenschaft jetzt fest. Quelle ist der Turn-Speicher, der
eine Zeile je Anfrage führt.

Nebenbei aufgefallen: `commit()` schreibt dieselbe Nutzung in **alle drei**
Fenster, `reserve()` nur in begrenzte. Eine Summe über alle Zeilen hätte jeden
Token dreifach gezählt.

**Schicht A wird nicht von Ereignis-Beobachtern gefüllt, sondern direkt vom
MCP-Server.** Die Einstufung „schreibt" kommt aus den MCP-Annotationen des
Werkzeugs (`readOnlyHint` / `destructiveHint`), und die kennt nur dieser Server.
Ein Beobachter im Kern müsste sie erraten oder in ein fremdes Plugin greifen.
Die Begründung für eine eigene Tabelle statt einer Abfrage auf dem Log bleibt
unverändert. Den **Ort** leitet der Server generisch aus den Argumenten ab
(`cmid`, sonst `courseid`), ohne dass ein einzelnes Werkzeug angefasst wurde.

**Der Copilot bleibt im Tutor, die Zahlen ziehen nach Core.** Er löst einen
echten Chat-Turn mit Einwilligung und Ratenbegrenzung des Tutors aus; der Kern
darf das nicht. Zwei Flächen sind das kleinere Übel gegenüber einer Abhängigkeit
in die falsche Richtung.

**Zwei stille Gates sind gefallen.** Die Copilot-Analyse und der
Navigationseintrag hingen am Opt-in `enableanalytics` des alten Frage-Logs —
standardmäßig **aus**. Auf den meisten Websites war die Deutung damit
unerreichbar und der Eintrag erschien nie.

### Nacharbeiten, die bewusst offen sind

- **`block_elediaai_tutor_qlog`, `question_log` und `recluster_service` stehen
  noch.** Die Tabelle ist leer und die Dienste damit untätig, aber
  `recluster_service` hängt an `question_log`; ein Rückbau ohne Portierung wäre
  ein Bruch statt eines Aufräumens.
- **`audit_recorder` hat keinen Aufrufer mehr**, die Klasse und ihre Tests
  bleiben bis zum Rückbau stehen.
- **`turnid`** in Schicht A und in `local_aitransparency_rec`: es fehlt ein
  Korrelationsschlüssel (OPEN-002) und, für C, ein Schreiber, der die Turn-Id
  zurückgibt.
- **Art. 50 Abs. 1 jenseits der Chat-Engine**: Kursautor und Fragengenerator
  schreiben KI-Text in Kursinhalte, die später jemand liest, ohne die Herkunft
  zu sehen. Das ist die eigentliche Lücke von Abs. 1 und braucht den Vertrag
  aus T-008.
- **Wegweiser-Kapitel** „Was die KI-Suite protokolliert" und der Abschnitt 6 in
  `03-dev-doc.md`.

---

## Direkter Eingabevertrag

Quelle: Betreibergespräche vom 17. und 19.09.2026, sieben Entscheidungen im
Wortlaut bestätigt. Normalisiert als `REQ-###`. Nichts darüber hinaus ist
Produktentscheidung dieses Plans.

| ID | Vereinbartes Verhalten |
|---|---|
| REQ-001 | Schicht A ist Ziel: die Suite führt ein auswertbares Protokoll darüber, **was** die KI getan hat — insbesondere die schreibenden MCP-Werkzeuge —, mit der handelnden Person. |
| REQ-002 | Audit und Didaktik teilen sich **eine** anonyme Turn-Datenbasis. |
| REQ-003 | Es gibt wieder einen Tutor-Report für Lehrkräfte, **ausgeklügelter** als der abgeschaltete. |
| REQ-004 | `local_aitransparency` bekommt Markierung und Auflösung — es wird gebaut, nicht als Ablage geführt. |
| REQ-005 | Lernende können den Tutor natürlichsprachig fragen, was das System über sie weiß (Selbstauskunft). |
| REQ-006 | Lehrkräfte können den Tutor natürlichsprachig fragen, was die Lernenden beschäftigt hat. |
| REQ-010 | Die Aufbewahrungsfristen sind **einstellbar** — je Schicht, nicht eine gemeinsame. (19.09.2026) |
| REQ-011 | Alle neuen Oberflächen passen zum Gestaltungssystem der eLeDia.ai-Suite und vermitteln einen hochwertigen Eindruck. (19.09.2026) |
| REQ-012 | Schicht B führt statt einer Nutzerkennung einen **gesalzenen Pseudonym-Schlüssel**. (19.09.2026, löst die vormals offene Frage OPEN-001) |

Aus der vorangegangenen Bestandsaufnahme mitgeführt, vom Betreiber bereits
entschieden und hier **nicht** neu aufgerollt:

| ID | Bestehende Entscheidung | Datum |
|---|---|---|
| REQ-007 | Chat-Turns werden mit Prompt, Antwort, Ort und Zeit erfasst, **ohne Nutzeridentifikation**. | 05.09.2026 |
| REQ-008 | `local_elediaai_sources`, `local_literag` und DeepL sind **keine** Quota-Lücke. | 06.09.2026 |
| REQ-009 | Der Kern kennt keine Plugins: kein Plugin holt Texte oder Verhalten aus `local_elediaai_core`, der Kern nennt keine Plugin-IDs. | 06.09.2026 |

---

## Randbedingungen und Belege aus dem Bestand

- **Ein einziger Engpass für alle KI-Aufrufe.** `quota_aware_ai_manager::process_action()`
  kennt `component`, `userid`, `actionname`, Kontext und Prompt aus der Action,
  `generatedcontent` und beide Tokenzahlen aus der Response —
  `public/local/elediaai_core/classes/quota_aware_ai_manager.php`. `process_callback()`
  ist der Zwilling für den Chat. Ein Test liest den Quelltext und meldet jeden
  Aufruf daran vorbei — `public/local/elediaai_core/tests/registry_test.php:831`.
- **Moodles Register kennt die Komponente nicht.** `ai_action_register` hat
  `actionname, actionid, success, userid, contextid, provider, errorcode,
  errormessage, timecreated, timecompleted, model` — keine `component`-Spalte
  (Moodle 5.2, `lib/db/install.xml:4946`). Die Frage „welches Feature wird
  benutzt" ist dort strukturell unbeantwortbar.
- **Die Antwort darauf liegt bereits gefüllt herum.** `local_elediaai_core_usage`
  führt `component` bei **jeder** Buchung mit, auch für den Chat
  (`public/local/elediaai_core/db/install.xml:18`). `quota_manager` hat keine
  Auswertung, die danach gruppiert, und keine Oberfläche liest sie.
- **Moodles Ereignislog ist nicht per SQL auswertbar.** `other` wird je nach
  Konfiguration als JSON **oder** PHP-serialisiert abgelegt
  (`admin/tool/log/classes/helper/buffered_writer.php:76-78`). Ein Filter auf
  `toolname` wäre ein LIKE über einen kodierten Blob, dessen Kodierung
  konfigurierbar ist. Ausserdem ist der Log-Store austauschbar und
  rotierbar. Die unterstützte Lese-API `\core\log\sql_reader::get_events_select()`
  liefert Objekte, keine aggregierbaren Zeilen; eine Reportbuilder-Entity für
  das Log gibt es im Kern nicht.
- **Die Rohereignisse für Schicht A gibt es schon.** `webservice_elediamcp\event\tool_invoked`
  und `write_performed` werden gesendet, letzteres mit dem Kommentar
  *„so that audit consumers can filter on a clearly defined state-changing
  event class for compliance reporting"* —
  `public/webservice/elediamcp/classes/local/server.php:1132`. Es fehlt
  ausschliesslich der Verbraucher. Werkzeuge tragen mit
  `readOnlyHint`/`destructiveHint` eine maschinenlesbare Einstufung
  (`ai_tool::annotations()`).
- **Die Suite bewertet bereits Lernleistungen im Dialog.**
  `moodle_grade_submission`, `moodle_read_submission`, `moodle_grading_queue`
  mit sorgfältigen Grenzen in `grading_guard.php` (Blindbewertung, getrennte
  Gruppen). Über ihr Tun führt kein KI-Protokoll Buch.
- **Die didaktischen Felder liefert die Engine bis heute.** `chat_response`
  trägt `topic`, `sources` und `origin` (`grounded` / `general` / `mcp`) —
  `public/local/elediaai_chatengine/classes/adapter/chat_response.php`. Der
  Aufrufpunkt mit Komponente, Instanz, Kontext und Response liegt in
  `chat_service.php:164`.
- **Die Analytik, die sie verbrauchte, ist tot.** `question_log::log()` hat
  ausserhalb der Tests **keinen** Aufrufer; der Schreiber verschwand am
  27.08.2026 in `324f3af` („block_elediaai_tutor als Placement auf der Engine"),
  dessen Commit-Text *„ohne dass eine Fähigkeit wegfällt"* sagt.
  `report.php`, `hotspots()`, `copilot_service` und `recluster_service` lesen
  seitdem eine Tabelle, die nichts mehr füllt.
- **`local_aitransparency` ist schreibgeschützt ohne Leser.**
  `provenance::get()`, `get_by_contenthash()`, `set_mark_state()` und die
  Tabelle haben ausserhalb des Plugins null Aufrufer. Vier Schreiber
  (Chat-Engine, LiteRAG, `qtype_aitext`, `aiprovider_eledia`). Die Capability
  `local/aitransparency:viewreport` ist definiert — für einen Bericht, den es
  nicht gibt. `registerid` **setzt niemand**.
- **Kein Korrelationsschlüssel zwischen Turn und Werkzeugaufruf.** Der
  MCP-Token wird **pro Nutzer** vergeben und zwischengespeichert
  (`chatengine/classes/local/token_provider.php`). Das Backend ruft mit diesem
  Token zurück; Moodle sieht eine Webservice-Anfrage dieser Person, ohne
  Bezug zum auslösenden Turn. Siehe OPEN-002.
- **`local_elediaai_core` definiert heute keine Capability**
  (`db/access.php`: leeres Array). Neue Capabilities sind dort ein Neuanfang,
  kein Umbau.
- **Moodle 4.5 wird unterstützt.** Der heutige Audit ist dort komplett
  versteckt, weil `ai_action_register` erst mit 5.0 kam
  (`audit_config::feature_available()`). Eigene Tabellen für A und B
  funktionieren auf 4.5 — die Neukonzeption **gewinnt** eine Moodle-Version
  zurück.

---

## Technische Entscheidungen

| ID | Entscheidung | Deckt ab | Alternativen und Grund |
|---|---|---|---|
| TD-001 | **Schicht B** als eigene Tabelle `local_elediaai_core_turn` in `local_elediaai_core`, geschrieben an genau zwei Stellen: `quota_aware_ai_manager::process_action()` und `::process_callback()`. | REQ-002, REQ-007 | *Weiter in Moodles `ai_action_register` schreiben:* verworfen — kein `component`, Schema gehört dem Kern, `audit_recorder` baut eine private Kernmethode nach. *Zwei Tabellen (Audit / Didaktik):* verworfen — es sind zwei Hälften desselben Datensatzes, siehe TD-005. |
| TD-002 | **Schicht A** als Projektion `local_elediaai_core_action`, gefüllt von Ereignis-Beobachtern auf `tool_invoked` und `write_performed`. Moodles Log bleibt Wahrheit des Rohereignisses; die Projektion macht die KI-relevante Teilmenge auswertbar. | REQ-001 | *Direkt `logstore_standard_log` abfragen:* verworfen — `other` ist JSON **oder** serialisiert, Store austauschbar und rotierbar. *Nur `sql_reader`:* verworfen — liefert Objekte, nicht aggregierbar, kein Reportbuilder. |
| TD-003 | Der Audit-Bericht liest **B** (Suite-KI) und **Moodles Register** (Fremd-KI ausserhalb der Suite) als zwei Berichte unter einer Seite, plus **A** als dritten. Kein Zusammenführen in einer Abfrage. | REQ-001, REQ-002 | *Union beider Register:* verworfen — ohne gesetztes `registerid` zählt jede naive Vereinigung doppelt (Bestandsaufnahme, 05.09.). |
| TD-004 | `audit_recorder` wird stillgelegt, sobald B schreibt. Bereits geschriebene anonyme Zeilen bleiben in Moodles Tabelle liegen und werden weiter über den Core-Zweig angezeigt. | REQ-002 | *Migrieren und löschen:* verworfen — `userid = 0` ist kein sicheres Erkennungsmerkmal fremder Zeilen; ein Fehlgriff löscht Kerndaten. |
| TD-005 | `block_elediaai_tutor_qlog` entfällt. **Keine Migration:** die Tabelle wird seit dem 27.08.2026 nicht mehr gefüllt, ihr Inhalt ist auf jeder aktuellen Installation älter als jedes sinnvolle Auswertungsfenster. Ein Upgrade-Schritt löscht die Tabelle. | REQ-002, REQ-003 | *Zeilen nach B migrieren:* verworfen — Aufwand für Daten, die niemand mehr auswertet, und ein Personenbezug, den B nicht führen soll. |
| TD-006 | Der Lehrkraft-Report zieht **nach `local_elediaai_core`** als kursbezogene Seite (`course_insights.php?courseid=`). Der Tutor verlinkt ihn. Grund: B deckt alle Placements ab (Tutor, `mod_aichat`, `mod_elli`), nicht nur den Block. | REQ-003 | *Im Block belassen:* verworfen — der Report gehörte dem Block, als der Block die Engine war; seit 27.08. ist er ein Placement unter mehreren. |
| TD-007 | Fremde Plugins lesen B **nie direkt**, sondern über eine öffentliche Lese-API `local_elediaai_core\local\insights`. Der Kern nennt dabei keine Plugin-IDs. | REQ-009 | — (Architekturprinzip, nicht verhandelbar) |
| TD-008 | Zwei neue MCP-Werkzeuge, von `local_elediaai_core` über `local_elediaai_core_elediamcp_tools()` beigesteuert: `moodle_ai_course_insights` (Lehrkraft, liest B) und `moodle_my_ai_data` (Selbstauskunft, liest nur die eigene Person). Beide `readOnlyHint: true`. Beide prüfen Berechtigung **zweimal**: beim Zurückgeben des Katalogs und in `execute()`. | REQ-005, REQ-006 | *Werkzeuge in `webservice_elediamcp` selbst:* verworfen — der MCP-Server kennt die Suite-Datenmodelle nicht und soll es nicht. |
| TD-009 | **Der Tutor liest B, nie A.** A ist ein Bericht, den ein Mensch mit Capability öffnet. Einzige Ausnahme: `moodle_my_ai_data` liefert der fragenden Person ihre **eigenen** A-Zeilen. | REQ-001, REQ-005, REQ-006 | *A auch als Werkzeug:* verworfen — ein natürlichsprachiges Interface über ein personenbezogenes Handlungsprotokoll ist ein Überwachungswerkzeug, das man nicht einmal missbrauchen müsste. |
| TD-010 | **Schicht C** bekommt `marker::wrap_text()`, eine Auflösungsseite `verify.php?uuid=` und den Bericht, für den die Capability schon existiert. `registerid` wird zu `turnid` und verweist auf B. C2PA/Signing (AP5) bleibt zurückgestellt. | REQ-004 | *Erst C2PA:* verworfen — die Beschaffung blockiert, die Markierung nicht; der selbstsignierte Pfad war laut `00-master.md` ohnehin als erster vorgesehen. |
| TD-011 | Art. 50 Abs. 1 wird ein **Vertrag mit Test**: jede Komponente, die nach B schreibt, führt eine Kennzeichnungsfläche. Ein quelltextlesender Test nach dem Muster des Quota-Tests meldet Komponenten, die nach B schreiben und `marker`/`notice` nie aufrufen. | REQ-004 | *Weiter je Plugin von Hand:* verworfen — `qtype_aitext` hat einen Hinweis, der Rest nicht; genau das wird beim nächsten Plugin wieder vergessen. |
| TD-012 | Die Komponenten-Auswertung aus `local_elediaai_core_usage` wird als eigene, **von A/B unabhängige** Ansicht gebaut und zuerst geliefert. | REQ-002 | — (unabhängiger Vorzieheffekt, siehe T-003) |
| TD-013 | **Pseudonym in B:** `askerkey = sha256(userid + Site-Salt)`, 64 Zeichen, indexiert. Das Salt entsteht beim Upgrade einmalig (`random_string(40)`), liegt in der Plugin-Konfiguration und wird nie ausgegeben. Keine Oberfläche und kein Werkzeug löst den Schlüssel auf; Löschung geschieht über `delete_for_user()`, das den Schlüssel neu berechnet und die Zeilen entfernt. | REQ-012, REQ-007 | *Gar kein Schlüssel:* verworfen — kein Löschpfad für freie Prompttexte, und Fragende sind nicht von Fragen unterscheidbar. *`userid`, nie angezeigt:* verworfen — widerspricht REQ-007 am deutlichsten. |
| TD-014 | **Gestaltungsvertrag:** neue CSS gehört in `styles.css`, nicht in `<style>`-Blöcke aus PHP; Klassenpräfix ist `eai-`, nicht das geerbte `lh-`; Abstände kommen aus `--eai-space-*`. Jede Fläche nutzt `plugin_page` / `plugin_shell` / `section_nav` und `lucide_icon`. | REQ-011 | *Dem Muster von `audit_page::css()` folgen:* verworfen — es setzt Abstände als freie rem-Werte und trägt den LernHive-Präfix; der Design-Lint prüft Abstände nicht, also würde die Schuld unbemerkt wachsen. |
| TD-015 | **Drei Fristen, drei Einstellungen**, plus eine eigene Fläche, die sie zusammen mit den Sichtbarkeitsschaltern zeigt und je Frist ihre Folge in einem Satz benennt. 0 heisst „unbegrenzt". | REQ-010 | *Eine gemeinsame Frist:* verworfen — die strengste Frist würde die Didaktik löschen, die lockerste Prompttexte aufbewahren. *Nur im Admin-Baum:* verworfen — die Folgen brauchen Erklärung, die der Admin-Baum nicht trägt. |

---

## Komponentenentwurf

```
local_elediaai_core                    Eigentümer aller drei Lesezugänge
├── classes/local/turn_recorder.php    schreibt B (ersetzt audit_recorder)
├── classes/local/action_projector.php schreibt A (Ereignis-Beobachter)
├── classes/local/insights.php         öffentliche Lese-API für B (TD-007)
├── classes/local/actions.php          öffentliche Lese-API für A
├── classes/mcp/moodle_ai_course_insights.php
├── classes/mcp/moodle_my_ai_data.php
├── classes/reportbuilder/…/turn.php   Entity für B
├── classes/reportbuilder/…/action.php Entity für A
├── course_insights.php                Lehrkraft-Report (aus dem Tutor gezogen)
├── audit_technical.php                liest B + Core-Register
└── audit_actions.php                  liest A

local_elediaai_chatengine              schreibt B über den Engpass mit
                                       topic / sources / origin
block_elediaai_tutor                   verlinkt course_insights.php,
                                       qlog + report.php entfallen
local_aitransparency                   marker, verify.php, Bericht, turnid
webservice_elediamcp                   unverändert; liefert die Ereignisse,
                                       die A verbraucht
```

**Grenze zwischen A und B.** A weiss *wer* und *was getan*, nie *was gesagt*.
B weiss *was gesagt* und *worauf gestützt*, nie *wer*. Die Trennung ist keine
Vorsicht, sondern die Bedingung dafür, dass B einem natürlichsprachigen
Werkzeug offenstehen kann.

---

## Capabilities und Kontexte

| Aktion | Capability | Kontext | Durchsetzungspunkt | Ablehnung |
|---|---|---|---|---|
| Handlungsprotokoll site-weit (A) | `local/elediaai_core:viewaiactions` | System | `audit_actions.php`, System-Report `can_view()` | `required_capability_exception` |
| Handlungsprotokoll im eigenen Kurs (A) | `local/elediaai_core:viewaiactions` | Kurs | dieselbe Seite mit `courseid`, Basisbedingung auf Kurskontextpfad | leere Ergebnismenge, nicht Fehler |
| Kurs-Einblicke (B) | `local/elediaai_core:viewcourseinsights` | Kurs | `course_insights.php`; Archetyp `editingteacher` + `teacher` per Default | `required_capability_exception` |
| MCP `moodle_ai_course_insights` | `local/elediaai_core:viewcourseinsights` | Kurs | Katalog **und** `execute()` | Werkzeug fehlt im Katalog; `tool_exception` bei Aufruf |
| MCP `moodle_my_ai_data` | keine — nur eigene Person | System | `execute()` ignoriert jeden fremden Personenparameter (es gibt keinen) | — |
| Technischer Audit (B + Core-Register) | `moodle/ai:viewaiusagereport` **bleibt** | System | `audit_config::can_view()` | unverändert |
| Provenance-Bericht (C) | `local/aitransparency:viewreport` (existiert) | System | Berichtsseite | `required_capability_exception` |
| Auflösungsseite `verify.php` | keine, aber `require_login()` | System | Seite | siehe OPEN-004 |

Die drei Zugriffsmodi des Audits (`corecap` / `adminonly` / `teacherowncourses`)
und ihr Kursfilter-SQL (`audit_config::teacher_course_scope_sql()`) gelten
unverändert weiter und werden auf A und B angewandt.

---

## Daten, Upgrade und Datenschutz

### Schicht B — `local_elediaai_core_turn`

| Feld | Zweck |
|---|---|
| `component`, `instanceid` | welches Feature, welche Placement-Instanz |
| `actionname` | `generate_text`, `chat_turn`, … |
| `contextid`, `courseid` | Ort; `courseid` abgeleitet gespeichert, damit der Report nicht je Zeile den Kontextbaum läuft |
| `origin` | `grounded` / `general` / `mcp` — die schärfste didaktische Kennzahl |
| `topic`, `sourcetitle`, `cmid` | kanonisches Thema und primäre Quelle, vom Backend geliefert |
| `prompt`, `response` | Volltext |
| `prompttokens`, `completiontokens` | gemessen, nicht geschätzt |
| `success`, `provider`, `model`, `timecreated`, `durationms` | Betrieb |
| `askerkey` | gesalzener Pseudonym-Schlüssel, `char(64)`, indexiert (TD-013) |

**Kein `userid`.** (REQ-007) Der `askerkey` macht „fünf Fragen von fünf
Personen" von „fünf Fragen von einer" unterscheidbar und eine Löschanfrage
beantwortbar, ohne dass irgendeine Fläche eine Person benennen kann.

### Schicht A — `local_elediaai_core_action`

| Feld | Zweck |
|---|---|
| `userid` | **zwingend** — Aufsicht ohne Akteur ist keine |
| `component`, `toolname`, `iswrite` | was getan wurde; `iswrite` aus `annotations()` |
| `success`, `durationms`, `errorcode` | Ergebnis |
| `contextid`, `courseid`, `timecreated` | Ort und Zeit |
| `turnid` | Verweis auf B, **heute immer `null`** — siehe OPEN-002 |

### Upgrade

- Install + Upgrade-Schritte in `local_elediaai_core/db/upgrade.php` nach dem
  dort etablierten Muster (`upgrade_plugin_savepoint` je Schritt, mit
  begründendem Kommentar).
- `$plugin->version` von `local_elediaai_core` steigt; abhängige Plugins
  ziehen ihre `dependencies`-Untergrenze nach, wo sie die neue Lese-API nutzen.
- **Löschschritt:** `block_elediaai_tutor_qlog` wird gelöscht (TD-005), dazu
  der Prune-Task, die Capability bleibt (siehe unten).
- `block/elediaai_tutor:viewreports` bleibt bestehen und wird in
  `course_insights.php` als **zusätzlich** akzeptierte Berechtigung geprüft,
  damit bestehende Rollenzuweisungen nach dem Umzug nicht ins Leere laufen.

### Datenschutz

| Schicht | Personenbezug | Export | Löschung | Frist |
|---|---|---|---|---|
| A | ja | eigene Handlungen | **anonymisiert statt löscht** — `userid` auf 0, Zeile bleibt; nach dem Muster von `local_aitransparency\task\anonymise_records` | einstellbar, Vorschlag 730 Tage |
| B | pseudonym (`askerkey`) | eigene Zeilen über den neu berechneten Schlüssel | **löscht** — Zeilen des Schlüssels werden entfernt | einstellbar, Vorschlag 90 Tage |
| C | ja, mit Frist | unverändert | unverändert (anonymisiert) | einstellbar, unverändert 365 Tage |

Die drei Vorschlagswerte sind Vorschläge, keine Festlegung — sie stehen als
Default im Code und gehören fachlich der Datenschutzseite (OPEN-005). Unter 30
Tagen wird die Themenauswertung in B unbrauchbar; die Fläche sagt das.

`askerkey` ist **pseudonyme personenbezogene Daten**, nicht anonyme. Das ist
die ehrliche Einordnung und gleichzeitig der Grund, warum die Löschung
funktioniert.

Die Anonymisierung-statt-Löschung in A ist bewusst: ein Handlungsprotokoll,
das auf Zuruf verschwindet, ist als Nachweis wertlos. Das Muster existiert im
Repository bereits und ist dort begründet.

**Rollback-Grenze:** Ein Downgrade auf den Stand vor T-001 lässt die B-Zeilen
stehen und schaltet die Schreibpfade zurück auf `audit_recorder`. Turns aus
der Zwischenzeit erscheinen dann weder im alten noch im neuen Bericht. Das ist
hinnehmbar, muss aber in der Release-Note stehen.

---

## APIs und Hintergrundverhalten

- **Schreiber B:** je ein Aufruf in `quota_aware_ai_manager::process_action()`
  (nach `commit_action`) und `::process_callback()` (nach `commit`). Beide
  dürfen den Aufrufer nie zum Scheitern bringen — dasselbe
  `try/catch` + `debugging()`-Verhalten wie im heutigen `audit_recorder`, samt
  seiner Begründung: *ein Turn, der beantwortet wurde, darf nicht scheitern,
  weil die Buchführung scheiterte.*
- **Beobachter A:** `db/events.php` in `local_elediaai_core`, auf
  `\webservice_elediamcp\event\tool_invoked` und `\…\write_performed`.
  Weiche Kopplung: fehlt `webservice_elediamcp`, gibt es schlicht keine
  Ereignisse — kein `class_exists`-Geflecht nötig, Beobachter auf nicht
  existierende Ereignisse sind in Moodle unschädlich.
- **Aufräum-Tasks:** je ein `scheduled_task` für A und B nach dem Muster von
  `quota_manager::prune()` und `anonymise_records`.
- **Reportbuilder:** zwei eigene Entities, eigenes Schema — **keine**
  Vererbung von `core_ai\reportbuilder\…\ai_action_register` mehr. Damit
  entfällt auch die `is_downloading_now()`-Heuristik nicht, sie wird aber nur
  noch an einer Stelle gebraucht und kann in einen gemeinsamen Trait.
- **MCP-Beisteuerung:** `local_elediaai_core_elediamcp_tools()` in `lib.php`,
  Muster wie `local_elediaai_coursegen_elediamcp_tools()`.
- **Werkzeuge kehren sofort zurück.** Beide neuen Werkzeuge sind lesend und
  antworten aus der Datenbank — kein Sprachmodell im Aufruf, also keine
  Adhoc-Task-Konstruktion nötig (Dev-Doc: „Ein Werkzeug darf nicht warten").
- **Versionsabhängigkeit:** A, B und der Kurs-Report laufen auf 4.5 bis 5.2.
  Nur der Core-Register-Zweig des Audits bleibt 5.0+ und bleibt hinter
  `audit_config::feature_available()` versteckt.

---

## Oberfläche und Barrierefreiheit

### Der ausgeklügelte Kurs-Report (REQ-003)

Der abgeschaltete Report konnte: Menge, Trend, Grounded-Anteil, Hotspots nach
Thema mit Link aufs Kursmodul, Fragen im Wortlaut ohne Namen, KI-Analyse. Das
bleibt. Neu kommt dazu:

1. **Lückenliste statt Hotspot-Liste.** Themen gewichtet nach
   `origin != grounded` — nicht „was wird gefragt", sondern **„was wird
   gefragt, das das Material nicht beantwortet"**. Das ist die einzige Liste,
   aus der unmittelbar eine Handlung folgt.
2. **Material vorhanden, trägt aber nicht.** Themen mit Quelle *und* hoher
   Nachfassquote — unterscheidet „Inhalt fehlt" von „Inhalt ist unklar".
3. **Fragen gegen Fragende.** „Fünf Fragen von fünf Personen" ist ein
   Kursproblem, „fünf Fragen von einer" ist ein Einzelfall. Braucht OPEN-001.
4. **Nachfassquote.** Folgefrage zum selben Thema innerhalb eines Fensters →
   die Antwort hat nicht getragen.
5. **Zeitliche Kopplung.** Fragenspitzen relativ zu Abgabeterminen und zur
   Veröffentlichung von Material.
6. **Vom Befund zur Handlung.** Jeder Hotspot bekommt Aktionen, die in die
   Suite führen — Selbstlernquiz, Arbeitsblatt, H5P, Material ergänzen. Über
   die Feature-Registry, damit der Kern keine Plugin-IDs nennt (REQ-009).
7. **Copilot bleibt**, liest die reichere Grundlage.

**Bewusst nicht gebaut:** Vergleich zwischen Kursen oder Lehrkräften. Dieselbe
Zahl, die einer Lehrkraft hilft, rankt sie in fremder Hand.

### Die Flächen (REQ-011)

Sieben, im Entwurf gezeichnet:
<https://claude.ai/artifact/QbvoaXMxekizpfWNaF7zt2>

| Fläche | Wer | Was sie trägt |
|---|---|---|
| `course_insights.php` | Lehrkraft | Lückenliste, Unklar-Liste, Verlauf, Wortlaut, Copilot |
| `audit_technical.php` | Betrieb | Turns aus B mit **Feature-Spalte**, Verbrauch nach Feature |
| `audit_actions.php` | Aufsicht | Handlungen aus A, schreibend hervorgehoben |
| `audit_settings.php` | Betreiber | drei Fristen, Sichtbarkeit, Zugriffsmodus (TD-015) |
| `aitransparency/verify.php` | wer den Inhalt hat | Herkunft eines Artefakts, ohne Person |
| MCP im Tutor, Lehrkraft | Lehrkraft | „Was beschäftigte die Lernenden?" |
| MCP im Tutor, Selbstauskunft | Lernende | „Was weiss das System über mich?" |

### Gestaltungsvertrag

Das Prinzip des Themes ist **„hell und ruhig, ein Akzent"**: weisse
Arbeitsflächen auf sehr hellem Grund, getrennt mit Haarlinien und flachen
Schatten, nicht mit Grauabstufungen. Hochwertig heisst hier **Zurückhaltung**,
nicht Dekoration — Farbverläufe, bunte Diagramme und Akzente ohne Bedeutung
verletzen das Prinzip, statt es zu erfüllen.

- **Token, nicht Zahlen.** Flächen `--eai-surface` auf `--eai-bg`, Linien
  `--eai-line` / `--eai-line-soft`, Text `--eai-fg` / `--eai-fg-dim` /
  `--eai-muted` / `--eai-faint`, Abstände `--eai-space-*`, Radien
  `--eai-radius*`, Erhebung `--eai-shadow-card`. Der Design-Lint prüft
  Schriftgrösse, Farbe, Radius und `!important`; **Abstände prüft er nicht**,
  deshalb stehen sie hier ausdrücklich im Vertrag.
- **Kontrast ist gemessen, nicht geschätzt.** Füllungen mit weisser Schrift
  nutzen `--eai-accent-strong` (#bb5c05, 4,52:1). Das Markenorange #f98012
  misst auf Weiss 2,58:1 — es trägt **keine** weisse Schrift und ist **kein**
  Text auf Weiss; es erscheint nur als Fläche oder unter dunkler Schrift. Diese
  Messung steht im Theme und wird nicht neu erfunden.
- **Die Farben bedeuten etwas, und nur das.** Grün (`--eai-success`) = vom
  Material gedeckt. Gelb (`--eai-warning`) = Lücke oder Schreibhandlung.
  Magenta (`--eai-danger`) = Fehler. Indigo (`--eai-ai`) = von der KI erzeugt.
  **Eine Lücke ist kein Fehler und deshalb nicht rot** — sie ist ein Hinweis.
- **Zahlen sind Zahlen.** `font-variant-numeric: tabular-nums` in jeder Spalte
  mit Zählwerten, damit Ziffern untereinander stehen.
- **Balken statt Diagrammbibliothek.** Ein Anteil ist ein Balken mit
  `role="progressbar"` und `aria-valuenow/min/max` — das Muster des alten
  Reports war korrekt und wird übernommen. Keine Chart-Bibliothek.
- **Leerzustände benennen den Grund** („noch keine Fragen in diesem Zeitraum"),
  nie eine leere Zelle — die Lehre aus `!60`.
- **Echte Bedienelemente**, auch in einer Ansicht: `<button>`, `<a href>`,
  `<input>` mit `<label>`; `aria-label` an Symbolschaltflächen; keine
  `role`/`onclick` auf `div`. Trefferflächen mindestens 44 px.
- Prompt-/Antwortvorschau weiterhin per Modal aus einem `<template>`, Volltext
  ohne zweiten Roundtrip, mit `sr-only`-Beschriftung.
- Kein `form-autocomplete` auf geprüften Seiten (bekannter axe-Bruch).
- **Aufräumen statt fortschreiben:** neue CSS geht in `styles.css`, nicht in
  einen `<style>`-Block aus PHP, und trägt den Präfix `eai-`. Die bestehenden
  `lh-ai-audit-*`-Klassen werden dabei nicht umbenannt — das ist eine eigene
  Aufgabe, keine Beifracht dieses Plans.

---

## Dokumentation und Entscheidungsdokumente

- **Nutzer-/Admin-Doku:** aktualisieren. Der Wegweiser (`local_elediaai_guide`)
  bekommt ein Kapitel „Was die KI-Suite protokolliert" mit den drei Schichten
  in Alltagssprache — das ist die Fläche, auf der Lernende und Lehrkräfte die
  Antwort auf Art. 50 Abs. 1 tatsächlich lesen.
- **Entwickler-/Betriebsdoku:** aktualisieren. `03-dev-doc.md` Abschnitt 6
  („KI aufrufen") beschreibt heute nur die Quota; er beschreibt künftig den
  Engpass als Quota **und** Protokoll. `audit-bestandsaufnahme.md` wird
  fortgeschrieben, nicht ersetzt — sie hält fest, wie es dazu kam.
- **`local_aitransparency/docs/00-master.md`:** AP2 steht dort als „offen",
  obwohl die Instrumentierung an vier Stellen läuft. Richtigstellen.
- **Changelog:** beide `CHANGELOG.md` ergänzen. Der von
  `local_elediaai_core` endet bei `0.7.1` (05.09.) und kennt weder die
  Chat-Turns noch die Akteur-Spalte vom selben Tag — nachtragen.
- **ADR-Kandidat:** „Drei Protokollschichten, geschnitten nach Lebensdauer und
  Personenbezug". Langlebig, komponentenübergreifend, schwer umkehrbar. Das
  Projekt führt ADRs (`adr01` in `local_aitransparency`). **Nicht von mir zu
  ratifizieren** — gehört dem Betreiber.

---

## Test- und Prüfstrategie

| Verhalten | Ebene | Test oder Prüfung | Umgebung |
|---|---|---|---|
| REQ-002: jeder Aufruf über den Engpass landet in B | PHPUnit | Erweiterung von `quota_aware_ai_manager_test` um den Schreibpfad | CI |
| REQ-002: kein Plugin schreibt an B vorbei | PHPUnit (Quelltext) | Erweiterung von `registry_test::test_no_plugin_calls_the_ai_manager_past_the_quota` | CI |
| REQ-007: B enthält nie eine Person | PHPUnit | Spaltenprüfung + Schreibtest, nach dem Muster von `audit_recorder_test::test_the_person_never_enters_the_table` | CI |
| REQ-012: derselbe Nutzer ergibt denselben Schlüssel, zwei Nutzer nie denselben, das Salt wird nie ausgegeben | PHPUnit | Pseudonym-Test | CI |
| REQ-012: Löschen einer Person entfernt genau ihre B-Zeilen | PHPUnit | Privacy-Test über den neu berechneten Schlüssel | CI |
| REQ-012: „5 Fragen von 5 Personen" ≠ „5 Fragen von einer" | PHPUnit | Zähltest über `askerkey` | CI |
| REQ-010: jede Frist wirkt getrennt; 0 bewahrt unbegrenzt auf | PHPUnit | je ein Task-Test für A, B und C | CI |
| REQ-011: keine freien Farb-, Schrift- und Radiuswerte in den neuen Flächen | CI-Lint | `ci/design-lint.php`, Baseline darf nicht steigen | CI |
| REQ-001: `write_performed` erzeugt genau eine A-Zeile | PHPUnit | Beobachter-Test mit ausgelöstem Ereignis | CI |
| REQ-001: A überlebt die Löschung einer Person (anonymisiert) | PHPUnit | Privacy-Test | CI |
| Capability-Ablehnung auf allen vier neuen Flächen | PHPUnit | je ein Test ohne Berechtigung | CI |
| Kursgrenze: Lehrkraft sieht nur den eigenen Kurs | PHPUnit | nach dem Muster von `audit_entity_test::test_audit_report_teacher_mode_filters_to_own_courses` | CI |
| REQ-006: Werkzeug fehlt im Katalog ohne Berechtigung | PHPUnit | Katalog- **und** `execute()`-Test | CI |
| REQ-005: Selbstauskunft liefert nie fremde Daten | PHPUnit | Test mit zwei Personen | CI |
| REQ-004: Marker umhüllt, Auflösung findet, unbekannte UUID läuft ins Leere | PHPUnit | `marker`- und `verify`-Tests inkl. Ablehnungspfad | CI |
| TD-011: jede B-schreibende Komponente kennzeichnet | PHPUnit (Quelltext) | neuer Test nach dem Muster des Quota-Tests | CI |
| REQ-003: Report zeigt Lücke, Nachfassquote, Leerzustand | Behat | Feature in `local_elediaai_core/tests/behat/` | CI |
| Barrierefreiheit des Reports | Behat + manuell | axe auf `course_insights.php` | moodle-52 lokal |
| Moodle 4.5: A, B und Report laufen, Core-Zweig versteckt | manuell | Durchgang auf einer 4.5-Instanz | lokal |

CI-Auswahl: `PLUGIN=local/elediaai_core,local/elediaai_chatengine,blocks/elediaai_tutor,local/aitransparency`
— der Testjob hängt Geschwister-Plugins anhand von `version.php` transitiv ein.

---

## Nachverfolgbarkeit

| Anforderung | Entscheidung / Komponente | Prüfung | Stand |
|---|---|---|---|
| REQ-001 | TD-002, TD-003, TD-009 / `action_projector`, `audit_actions.php` | Beobachter-, Capability-, Privacy-Test | abgedeckt |
| REQ-002 | TD-001, TD-004, TD-005 / `turn_recorder`, `insights` | Engpass-, Quelltext-, Spaltentest | abgedeckt |
| REQ-003 | TD-006, TD-007 / `course_insights.php` | Behat + axe | abgedeckt |
| REQ-004 | TD-010, TD-011 / `local_aitransparency` | Marker-, Verify-, Quelltexttest | abgedeckt |
| REQ-005 | TD-008, TD-009 / `moodle_my_ai_data` | Zwei-Personen-Test | abgedeckt |
| REQ-006 | TD-008 / `moodle_ai_course_insights` | Katalog- + `execute()`-Test | abgedeckt |
| REQ-007 | TD-001, TD-013 / Schema ohne `userid` | Spaltentest | abgedeckt |
| REQ-008 | — | — | keine Arbeit (bewusst) |
| REQ-009 | TD-007, TD-008 / Lese-API, Feature-Registry | bestehender Test verbietet `get_string(…, 'local_elediaai_core')` in fremden Providern | abgedeckt |
| REQ-010 | TD-015 / `audit_settings.php`, drei Tasks | Task-Test je Schicht + Formulartest | abgedeckt |
| REQ-011 | TD-014 / `styles.css`, Seitenhülle | Design-Lint, axe, Behat | abgedeckt |
| REQ-012 | TD-013 / `askerkey`, Salt im Upgrade | Pseudonym-, Zähl- und Löschtest | abgedeckt |

---

## Umsetzungsreihenfolge

1. **T-003 — Komponenten-Auswertung aus dem Guthaben-Hauptbuch**
   - Deckt ab: REQ-002 (Vorzieheffekt)
   - Bereiche: `classes/local/quota_manager.php`, neue Ansicht in `audit.php`
   - Hängt ab von: nichts
   - Fertig, wenn: „welches Feature verbraucht wie viel" ohne neue Tabelle
     beantwortet ist. **Bewusst zuerst, weil unabhängig von jeder offenen Frage.**

2. **T-002a — Die vier Altlasten**
   - Deckt ab: Vorarbeit
   - Bereiche: Kacheltexte `feature_audit_desc`/`_detail` (behaupten noch, Chat-Turns
     seien nicht erfasst), verwaister String `audit_intro`, Rückfall in
     `registry::visible()` (Kachel sichtbar, Seite weist ab), fehlende
     CHANGELOG-Einträge
   - Hängt ab von: nichts
   - Fertig, wenn: kein Text mehr etwas anderes sagt als der Code tut.

3. **T-001 — Schicht B**
   - Deckt ab: REQ-002, REQ-007, REQ-012
   - Bereiche: `db/install.xml`, `db/upgrade.php` (Schema **und** Site-Salt),
     `classes/local/turn_recorder.php`, `classes/local/insights.php`,
     `classes/quota_aware_ai_manager.php`, `chatengine/classes/chat_service.php`,
     Privacy, Task, Tests
   - Hängt ab von: nichts
   - Fertig, wenn: ein Chat-Turn und ein `generate_text` je eine B-Zeile mit
     Komponente und `askerkey` erzeugen, der Quelltexttest keinen Umgeher
     findet und das Löschen einer Person genau ihre Zeilen trifft.

4. **T-002b — Audit liest B**
   - Deckt ab: REQ-002
   - Bereiche: neue Reportbuilder-Entity, `audit_technical.php`,
     `audit_recorder` stilllegen
   - Hängt ab von: T-001
   - Fertig, wenn: der Bericht Suite-KI aus B und Fremd-KI aus dem Core-Register
     zeigt, ohne doppelt zu zählen.

5. **T-004 — Schicht A**
   - Deckt ab: REQ-001
   - Bereiche: `db/events.php`, `action_projector`, `db/access.php` (erste
     Capability des Plugins), `audit_actions.php`, Privacy, Task, Tests
   - Hängt ab von: nichts fachlich; nach T-001, damit `turnid` schon existiert
   - Fertig, wenn: ein `moodle_grade_submission` über MCP als Zeile
     „Schreibaktion, Werkzeug, Person, Kurs, Zeit" im Bericht steht.

6. **T-010 — Aufbewahrung und Sichtbarkeit als eigene Fläche**
   - Deckt ab: REQ-010
   - Bereiche: `audit_settings.php`, `classes/form/audit_settings.php`,
     `settings.php`, `classes/local/audit_config.php`, drei Aufräum-Tasks
   - Hängt ab von: T-001 (Frist B), T-004 (Frist A)
   - Fertig, wenn: jede der drei Fristen getrennt gesetzt werden kann, jede
     ihre Folge in einem Satz benennt und jeder Task nur seine eigene Schicht
     anfasst.

7. **T-005 — Der ausgeklügelte Kurs-Report**
   - Deckt ab: REQ-003
   - Bereiche: `course_insights.php`, `insights`-API, Copilot umhängen,
     `qlog` + `report.php` + Prune-Task entfernen, Tutor verlinkt
   - Hängt ab von: T-001
   - Fertig, wenn: Lückenliste, Nachfassquote und Handlungsvorschläge stehen
     und der alte Report samt Tabelle weg ist.

8. **T-006 — Die beiden MCP-Werkzeuge**
   - Deckt ab: REQ-005, REQ-006
   - Bereiche: `lib.php` (`…_elediamcp_tools()`), `classes/mcp/*`
   - Hängt ab von: T-005 (Einblicke), T-004 (eigene Handlungen)
   - Fertig, wenn: eine Lehrkraft im Tutor „Was beschäftigte meine Lernenden
     letzte Woche?" fragen kann und ein Lernender „Was weiss das System über
     mich?" — und beide nichts sehen, was ihnen nicht zusteht.

9. **T-007 — Schicht C wird lesbar**
   - Deckt ab: REQ-004
   - Bereiche: `marker`, `verify.php`, Bericht, `registerid` → `turnid`
   - Hängt ab von: T-001
   - Fertig, wenn: eine markierte Ausgabe über ihre UUID auflösbar ist.

10. **T-008 — Art. 50 Abs. 1 als Vertrag**
   - Deckt ab: REQ-004
   - Bereiche: `marker::notice()`, Ausrollung, Quelltexttest
   - Hängt ab von: T-007
   - Fertig, wenn: der Test jede B-schreibende Komponente ohne Kennzeichnung meldet.

11. **T-009 — Dokumentation**
    - Bereiche: Wegweiser-Kapitel, `03-dev-doc.md`, beide CHANGELOGs,
      `aitransparency/docs/00-master.md`, Fortschreibung der Bestandsaufnahme
    - Hängt ab von: den jeweils beschriebenen Paketen

---

## Risiken und offene technische Entscheidungen

- **OPEN-001 — entschieden am 19.09.2026: gesalzener Pseudonym-Schlüssel.**
  Der abgeschaltete `qlog` speicherte `userid` ausdrücklich, damit die
  Privacy-API exportieren und löschen kann; das heutige Audit speichert gar
  keine Person (REQ-007). Der Schlüssel erfüllt beides: die Identifikation
  erreicht die Tabelle nicht, aber Fragende bleiben zählbar und eine
  Löschanfrage beantwortbar. Umsetzung in TD-013, Einordnung als **pseudonyme**
  Daten in Abschnitt „Datenschutz". Kein Blocker mehr.

- **OPEN-002 (betrifft `turnid` in A):** Es gibt heute keinen Schlüssel, der
  einen Werkzeugaufruf mit dem auslösenden Turn verbindet — der MCP-Token ist
  pro Nutzer, nicht pro Turn. Optionen: *(a)* nicht verknüpfen, im Bericht
  über Person + Zeitfenster korrelieren (kostenlos, schwach); *(b)* eine
  Korrelationskennung durch das Backend reichen und auf dem MCP-Weg
  zurückerwarten (braucht eine Zusage der Backend-Seite); *(c)* kurzlebige
  Token je Turn statt je Nutzer (teurer, aber die Verknüpfung entsteht von
  selbst und ein abgeflossener Token gilt nur für einen Turn). Plan geht von
  *(a)* aus, `turnid` bleibt vorbereitet und `null`.

- **OPEN-003 (Rechtsseite, kein Umsetzungshindernis):** Die Einstufung der
  Bewertungswerkzeuge nach Anhang III Nr. 3 und die geltenden Fristen sind
  eine juristische Entscheidung. Die Dokumente im Repository nennen
  02.12.2026 (Art. 50) und 2027 (Anhang III); ob das der Stand ist, konnte ich
  von hier nicht prüfen. **Nachprüfbar und nachgeprüft ist nur das Sachliche:**
  `moodle_grade_submission` existiert und bewertet Lernleistungen, und über
  sein Tun führt heute kein KI-Protokoll Buch. Schicht A ist die günstigste
  Antwort darauf, unabhängig davon, wie die Einstufung ausfällt.

- **OPEN-004:** Braucht `verify.php` einen Login? Art. 50 Abs. 2 spricht dafür,
  dass jeder, der den Inhalt hat, ihn prüfen kann; eine offene Seite verrät
  aber, dass ein Datensatz existiert. Plan geht von `require_login()` ohne
  Capability aus und zeigt Komponente, Modell, Zeit, nie die Person.

- **OPEN-005 — Mechanik entschieden am 19.09.2026: alle drei Fristen sind
  einstellbar** (REQ-010, TD-015). Offen bleiben nur die **Vorgabewerte**: der
  Plan schlägt 730 / 90 / 365 Tage vor und liefert sie als Default, aber welche
  Frist fachlich richtig ist, gehört der Datenschutzseite — dieselbe Frage
  steht bereits als `R-04` in `offene-rechtsfragen.md`. Kein Blocker: ein
  Default lässt sich ändern, ohne den Code zu berühren.

- **Risiko — Doppelbefüllung während des Umbaus.** Zwischen T-001 und T-002b
  schreiben `turn_recorder` und `audit_recorder` beide. Das ist kurz und
  sichtbar, aber es zählt Chat-Turns doppelt, solange beide Berichte laufen.
  Gegenmittel: T-002b unmittelbar nach T-001, oder `audit_recorder` schon in
  T-001 stilllegen und die Anzeigelücke für einen Commit hinnehmen.

---

## Übergabe

- **Nächste Schritte:** Umsetzung → `moodle-code`; Tests → `moodle-tests`;
  Dokumentation → `moodle-docs`; nach Fertigstellung → `moodle-review`.
- **Vor dem Review erforderlich:** phpcs (`moodle-extra`), Design-Lint
  (Baseline darf nicht steigen), Semgrep, PHPUnit und Behat je berührtem Plugin
  über die Suite-Pipeline; axe-Durchgang auf `course_insights.php` und
  `audit_settings.php`; ein manueller Durchgang auf einer Moodle-4.5-Instanz.
- **Gestaltungsvorlage:** der Entwurf der sieben Flächen liegt unter
  <https://claude.ai/artifact/QbvoaXMxekizpfWNaF7zt2>. Er ist mit dem echten
  Token-Satz gezeichnet und gilt als Vorlage für Aufbau, Hierarchie und
  Farbbedeutung — nicht als abzutippendes Markup.
- **Bewusst zurückgestellt:** C2PA und Signierdienst (AP5), `filter_aitransparency`
  (AP3b), die Zusammenführung von B und `local_aitransparency_rec` zu einer
  Abfrage (braucht erst durchgängig gesetztes `turnid`), Vergleichszahlen
  zwischen Kursen.
- **Nicht Teil dieses Plans:** neue Produktanforderungen, Code-Änderungen,
  Commits, Umgebungsänderungen.

# Changelog — local_elediaai_core

Wesentliche Änderungen, neueste zuerst. Version = `release` aus version.php.
Format angelehnt an Keep a Changelog.
Frühere Stände siehe git log.

## [1.0.2] – 2026-09-25
### Geändert
- **Nicht lizenzierte Premium-Funktionen bleiben in der Übersicht** (#29),
  gesperrt gezeichnet mit „Nicht lizenziert" und einem Link ins Handbuch statt
  in die Funktion. Vorher verschwand die Kachel, und eine abgeschaltete
  Freigabe sah aus wie ein fehlendes Plugin. Neu: `registry::launcher()` und
  `registry::is_locked()`; `registry::visible()` bleibt unverändert.
- **Platzhalter für nicht installierte Funktionen sind per Vorgabe aus** (#31).
  Neue Einstellung `showplaceholders` („Nicht installierte Funktionen als
  Vorschau zeigen") für Demo- und Vertriebsinstanzen. Der Platzhalter des
  Kursautors heißt nicht mehr „In Vorbereitung" — das Plugin existiert —,
  sondern „Installierbar", und nur für die Administration.

## [1.0.1] – 2026-09-25
### Hinzugefügt
- **`local\course_creator`**: Bereichswahl und Einschreibung des Erstellers
  rund um `create_course()`, wie sie `course/edit.php` macht. Die MCP-Werkzeuge
  und der Kursautor riefen `create_course()` allein auf; Kursersteller eines
  Bereichs scheiterten am Standardbereich oder standen vor dem eigenen Kurs
  ohne Zugang (#33, #34).

## [0.8.0] – 2026-09-19
### Hinzugefügt
- **Der Reifegrad steht auf der Kachel.** Alles ist Beta außer Tutor, MCP,
  Übersetzung und Quellen (Betreiberentscheidung 19.09.2026). Die Angabe kommt
  aus `$plugin->maturity` des jeweiligen Plugins — kein zweiter Vertrag, kein
  Deskriptorfeld, das dieselbe Auskunft ein zweites Mal gibt. Überraschung
  dabei: Moodle **behält** den Wert für installierte Plugins nirgends;
  `plugininfo` weist ihn mit „Invalid plugin property accessed" ab, weil die
  Konstante nur dem Aktualisierungsprüfer dient. `registry::maturity()` liest
  die `version.php` deshalb selbst und merkt sich die Antwort für den
  Seitenaufbau. Wer nichts deklariert, gilt als freigegeben.
- **Ein Handbuchkapitel „Was die KI-Suite protokolliert".** Die drei
  Protokolle in Alltagssprache, im Vertrauensteil des Wegweisers — das ist die
  Fläche, auf der Lernende und Lehrkräfte die Antwort auf Art. 50 Abs. 1
  tatsächlich lesen. Das Datenschutzkapitel daneben behauptete bis dahin,
  Moodle behalte nur „Zeiger auf Ihre Gespräche und eine kurze Vorschauzeile";
  das stimmt seit Schicht B nicht mehr und ist richtiggestellt.
- **Ein eigener Turn-Speicher für die Suite** (`local_elediaai_core_turn`).
  Bisher war das Audit eine Sicht auf Moodles `ai_action_register` und erbte
  dessen Grenzen: keine `component`-Spalte, also war „welches Feature wird
  benutzt" dort unbeantwortbar, und alles außerhalb von `core_ai` fehlte. Die
  Zeile entsteht an genau zwei Stellen — `quota_aware_ai_manager::process_action()`
  und, für den Chat, `chat_service` —, durch die per Test jeder KI-Aufruf der
  Suite läuft. Damit sind dreizehn Plugins mit einem Aufruf abgedeckt.
- **Die Felder, die seit dem 27.08.2026 ins Leere liefen.** `chat_response`
  trägt `origin` (gedeckt / aus dem Modell / aus Moodle gelesen), `topic` und
  die Quellenangabe; seit der Block ein Placement wurde, nahm sie niemand mehr
  entgegen. Sie stehen jetzt im Turn-Speicher und sind die Grundlage für die
  Kurs-Einblicke.
- **Ein gesalzener Pseudonym-Schlüssel** statt einer Nutzerkennung
  (Betreiberentscheidung 19.09.2026). Er zählt Fragende, ohne eine zu benennen,
  und macht eine Löschanfrage beantwortbar; keine Oberfläche löst ihn auf. Das
  ist pseudonym, nicht anonym — und genau deshalb funktioniert das Löschen.
- **Welches Feature wird benutzt.** Das Audit zeigt den Tokenverbrauch je
  Suite-Komponente, gezählt aus dem Turn-Speicher.
- **Einstellbare Aufbewahrungsfristen** für Turns und Guthaben-Hauptbuch, samt
  den beiden Aufräum-Tasks. Das Plugin hatte bis hierher **gar keine**
  `db/tasks.php`: `quota_manager::prune()` existierte seit Anfang und hatte
  keinen Aufrufer, das Hauptbuch wuchs unbegrenzt.
- Ein zweiter Bericht auf der technischen Audit-Seite: Suite-Turns und Moodles
  Register für Fremd-KI stehen nebeneinander statt in einer Vereinigung. Solange
  `local_aitransparency_rec.registerid` niemand setzt, würde ein Zusammenführen
  dieselbe Aktion doppelt zählen.

### Korrigiert gegenüber dem ersten Anlauf dieses Release
- Die erste Fassung las „welches Feature wird benutzt" aus
  `local_elediaai_core_usage`, weil dort eine `component`-Spalte steht. Das war
  falsch: der eindeutige Index dieser Tabelle ist
  `(userid, rolebucket, windowtype, windowstart)` **ohne** Komponente. Es gibt
  eine Zeile je Person und Fenster, und die Spalte hält nur fest, welches
  Feature **zuletzt** gebucht hat — ihr eigener Privacy-String sagt das wörtlich.
  Ein Test hat es aufgedeckt, und ein Test hält die Eigenschaft jetzt fest,
  damit sie nicht erneut für eine Zuordnung gehalten wird. Quelle ist der
  Turn-Speicher, der eine Zeile je Anfrage führt.

### Geändert
- **Ein Ziel, ein Name.** „Technisches Audit" und „didaktisches Audit" sind
  weg: beides waren Begriffe, die man erklären musste, bevor jemand wusste, wo
  er klicken soll. Dieselbe Fläche hieß außerdem an drei Stellen verschieden —
  „Kurs-Einblicke" im Reiter, „Was die Lernenden gefragt haben" auf der Karte,
  „Welcher Kurs?" in der Titelzeile. Es gibt jetzt vier Flächen mit vier
  Namen, wortgleich in Reiter, Einstiegskarte und Seitentitel: **Was die
  Lernenden gefragt haben**, **Was die KI getan hat**, **Jede KI-Anfrage**,
  **Zugang und Aufbewahrung**. `audit_naming_test` hält das fest, auch gegen
  die Seitenskripte selbst — ein Seitentitel steht in keiner Klasse und driftet
  sonst unbemerkt ab.
- **Die Übersicht zeigt zuerst die Wege, dann die Zahlen.** Vorher standen
  zwei Zahlenblöcke oben und man musste an ihnen vorbeiscrollen, um zu
  erfahren, wohin es überhaupt geht.
- Der Zustandsblock nennt seine Grundmenge („Ganze Website, ganze Laufzeit",
  aus Moodles Register). Daneben steht eine Aufteilung über 30 Tage aus dem
  Turn-Protokoll; ohne die Angabe las sich das wie ein Widerspruch.
- Die Anfragenseite zeigt nur noch Anfragen. Sie trug dieselben zwei
  Zahlenblöcke ein zweites Mal und schob damit ihre eigenen Tabellen unter den
  Falz.
- Die Kacheltexte des Audits sagen wieder, was der Bericht tut. Sie behaupteten
  seit dem 05.09.2026, Chat-Turns würden „gegen das Token-Budget gezählt statt
  hier verzeichnet" — das war 32 Minuten lang wahr, dann schrieb der
  Chat-Recorder sie mit.

### Behoben
- **Eine Antwort hatte zwei Herkunftsnachweise.** Vier Stellen schrieben sie
  auf drei Ebenen: `aiprovider_eledia` für alles, was er erzeugt,
  `qtype_aitext` zusätzlich für sich, dazu Chat-Engine und LiteRAG. Eine über
  diesen Anbieter bewertete aitext-Frage sammelte damit zwei Belege für
  denselben Text. Der Nachweis entsteht jetzt am Engpass der Suite — der
  einzigen Stelle, die Komponente, Turn und Registerzeile kennt —, und der
  Anbieter tritt zurück, solange `in_suite_call()` gilt.
- **Die beiden Anfragenlisten überschnitten sich.** Eine Anfrage der Suite
  läuft durch `core_ai` und stand deshalb auch in Moodles Register. Der Turn
  merkt sich jetzt die Nummer seiner Registerzeile, und die Registeransicht
  lässt genau diese Zeilen aus.
- **Der Tutor kostete laut Audit nichts, laut Guthaben aber doch.** Meldet ein
  Chat-Backend keinen Verbrauch — der RAG-Agent tut das nicht —, fällt der
  Engpass auf eine Schätzung zurück und bucht sie auf das Guthaben; ins
  Protokoll schrieb die Chat-Engine daneben die rohe Null der Antwort. Der
  Bericht behauptete damit, jede Tutor-Anfrage auf diesem Backend habe nichts
  gekostet. `process_callback()` gibt das Gebuchte jetzt heraus, die Engine
  schreibt dieselbe Zahl, und die neue Spalte `tokensestimated` hält fest,
  welche der beiden man vor sich hat (`≈ 120 / 60` im Bericht).
- **Der Provider hieß „ingestionapi".** Das ist die Kennung des Chat-Backends,
  nicht die eines Providers — der Tutor spricht nie selbst mit einem Modell.
  Gespeichert bleibt die Kennung (stabil, sprachunabhängig), gezeigt wird der
  Name des Adapters, aufgelöst beim Chat-Plugin.
- Ein Thema ohne eine einzige Nachfrage stand unter „Material da, trägt aber
  nicht" — mit 0 %. Dieselbe Regel wie bei den Lücken und aus demselben Grund:
  ein Gegenbeispiel in einer Liste lässt den Leser an der ganzen Liste
  zweifeln.
- Zwei Reiter zeigten auf dieselbe Seite („Überblick" und „Audit"). Die
  Einstellungen hatten umgekehrt gar keinen und waren im Menü nicht
  auffindbar.
- `course_insights.php` hatte zwei Überschriften erster Ordnung mit
  verschiedenem Wortlaut; die Handlungstabelle hatte gar keine.
- Die Reiterzeile schob die letzten Punkte aus dem Sichtfeld, seit die Namen
  länger sind — sie bricht jetzt um statt zu scrollen. Die Zahlenkacheln
  standen je nach Breite drei plus eine; sie stehen jetzt fest zwei mal zwei,
  ab Tablettbreite vier.
- Die Audit-Kachel folgt ihrer eigenen Zugangsregel, auch wenn diese nein sagt.
  Ein „nein" von `audit_config::can_view()` fiel vorher in die normale
  Capability-Prüfung durch: im Modus „nur Administration" sah jede Person mit
  `moodle/ai:viewaiusagereport` die Kachel und bekam auf `audit.php` eine
  Fehlerseite. Auf Moodle 4.5 kam dazu eine Debugging-Meldung je Seitenaufruf,
  weil die Capability dort nicht existiert.

### Entfernt
- **`audit_recorder`.** Er schrieb Suite-Anfragen in Moodles Kernregister,
  damit sie im Audit auftauchen. Das tun sie jetzt über Schicht B, und zwar
  mit Komponente — was das Register nicht kann. Die Zeilen, die er zwischen
  dem 05. und 19.09.2026 dort hinterlassen hat, bleiben liegen und lesbar.
- Rund dreißig Sprachstrings der alten Aufteilung (`audit_technical_*`,
  `audit_didactic_*`, `nav_audit_didactic`, `nav_audit_technical` und die
  doppelten Namen der vier Flächen). Alle in `deprecated.txt` eingetragen.
- Der verwaiste Sprachstring `audit_intro` — Rest der einseitigen Audit-Fläche
  vor der Aufteilung in Übersicht, technische und didaktische Seite. Kein
  Aufrufer in PHP oder Mustache.

### Nachgetragen
Die folgenden Änderungen vom 05.09.2026 fehlten in diesem Changelog:
- Chat-Turns stehen im Audit — mit Prompt, Antwort, Ort und Tokens, **ohne
  Nutzerkennung** (`audit_recorder`, Betreiberentscheidung vom 05.09.2026).
- Eine anonym aufgezeichnete Zeile sagt „Nicht erfasst" statt eine leere
  Akteur-Zelle zu zeigen (eigene Spalte `actor_named`; Moodles
  `user:fullnamewithlink` liefert bei leerem LEFT JOIN den leeren String).

## [0.7.1] – 2026-09-05
### Entfernt
- **Der KI-Session-Komponist (feat13).** Sein einziger Einstieg war
  `local_elediaai_core_lernhive_session_compose()` — ein Callback für den Hook
  `lernhive_session_compose`, den nur `local_lernhive` aufgerufen hätte.
  Dieses Plugin gehört nicht zur Suite und existiert nicht; die Funktion konnte
  nie laufen. Weg sind der Callback, `classes/local/session_composer.php`, die
  Einstellung `enable_session_composer` samt beider Sprachstrings und
  `tests/session_composer_test.php`. Ein Upgrade-Schritt räumt den verwaisten
  Konfigurationswert weg.
- Damit löst die Suite von sich aus keine KI-Aktion mehr aus: jede verbliebene
  geht von einer Eingabe aus.

## [0.6.4] – 2026-08-25
### Geändert
- Seitenrahmen, Header-Aktionen, Dashboard-Icons und Handbuch-Rendering sind
  jetzt vollständig Teil von AI Suite Core. `local_lernhive` wird für die
  Installation oder Laufzeit nicht mehr benötigt (DEL-518).

## [0.6.3] – 2026-08-20
### Hinzugefügt
- Legacy-Adapter können `process_callback()` einen optionalen Usage Resolver
  übergeben. Gemessene Prompt-/Completion-Tokens werden normalisiert und
  gebucht; fehlende oder ungültige Werte fallen feldweise auf die bestehende
  Schätzung zurück (DEL-518).

## [0.6.2] – 2026-08-19
### Hinzugefügt
- Ein Compatibility-Einstieg bindet bestehende Suite-Adapter mit eigener
  Transportlogik an denselben zentralen Quota-Lebenszyklus an (DEL-518).

## [0.6.1] – 2026-08-19
### Geändert
- Session Composer und Elli-Bildassistent nutzen ebenfalls die zentrale
  quota-fähige Ausführungsgrenze (DEL-518).

## [0.6.0] – 2026-08-19
### Hinzugefügt
- `quota_aware_ai_manager` ist die zentrale Ausführungsgrenze für eLeDia.ai:
  Quota wird vor dem Moodle-Core-AI-Aufruf reserviert, bei Erfolg mit den
  gemessenen Tokens verbucht und bei Fehlern wieder freigegeben (DEL-518).

## [0.5.6] – 2026-08-14
### Sicherheit
- Der LLM-Session-Composer kennzeichnet Kandidatenfelder ausdrücklich als
  nicht vertrauenswürdige Daten, grenzt sie klar vom Anweisungsteil ab und
  verbietet dem Modell, darin enthaltenen Anweisungen zu folgen.

## [0.5.5] – 2026-08-14
### Behoben
- Fehlerhafte Feature-Provider bleiben voneinander isoliert, werden aber mit
  Klassenname und Ursache in Moodles Developer-Diagnose gemeldet. Dies umfasst
  unlesbare Dateien, nicht ladbare oder inkompatible Klassen, Exceptions und
  ungültige Descriptor-Werte.

## [0.5.4] – 2026-08-14
### Behoben
- Quota-Reservierungen tragen jetzt ihren ursprünglichen Rollen-Bucket und die
  Startzeitpunkte der Stunden-, Tages- und Monatsfenster. `commit()` und
  `release()` rechnen dadurch auch über Fenster- oder Rollenwechsel hinweg
  gegen dieselben Zeilen ab, in denen der Request reserviert wurde (DEL-518).

## [0.5.3] – 2026-08-06
### Neu
- Bildguthaben für `generate_image`: Token-äquivalente Preisstufen nach Größe
  und Anzahl (1.000 / 2.000 / 4.000 Credits), atomare Reservierung und
  Verbuchung über denselben `quota_manager`-Pfad wie Text-Tokens; Audit zeigt
  Bildguthaben in der bestehenden Token-Spalte (SUI-613).
### Geändert
- Suite-Dashboard nutzt das Moodle-`report`-Layout, damit die Wide-Shell und
  das dreispaltige Feature-Raster den verfügbaren Seitenbereich ausnutzen
  (SUI-660).
- Token-Quota und LLM-Session-Composer in `03-dev-doc.md` dokumentiert
  (Public-API-Tabellen, Reservierungs-Lebenszyklus, Grounding-Vertrag,
  Fallback-Verhalten); `01-features.md` beschreibt feat12/feat13 anwendernah
  (task18).

## [0.5.1] – 2026-08-04
### Geändert
- Suite-Dashboard (`/local/elediaai_core/index.php`) öffnet die Plugin-Shell jetzt mit `MODIFIER_WIDE` (88rem statt 72rem), damit das bereits gesetzte 3-Spalten-Kartenraster (`lh-plugin-grid--cols-3`) tatsächlich dreispaltig statt zweispaltig rendert (SUI-602)

## [0.5.0] – 2026-08-03
### Geändert
- **Breaking:** Frankenstyle-Komponente von `local_lernhive_ai` auf `local_elediaai_core` umbenannt — Verzeichnis, Namespaces, Sprachdateien, Capabilities, Webservice-Funktionen, Tasks, Events und AMD-Module ziehen mit. Tabelle `local_lernhive_ai_usage` → `local_elediaai_core_usage`. Kein Migrationspfad: Moodle sieht ein neues Plugin, die Suite wird auf dev und demo neu installiert (SUI-560)

## [0.4.0] – 2026-08-02
### Geändert
- Token-Quota als harte Credit-Grenze: Vor einem externen AI-Request werden Prompt-Schaetzung plus konfigurierter Completion-Puffer (`quota_completion_buffer`, Standard 500) pro Stunden-/Tages-Fenster atomar reserviert; parallele Requests koennen das Limit nicht mehr durch gleichzeitige Preflight-Pruefung ueberziehen. Nach der Antwort wird auf die tatsaechlichen Tokens korrigiert, Fehlerpfade geben die Reservierung frei (SUI-517)

## [0.3.2] – 2026-07-31
### Neu
- Kundenfähige Produktdoku: 13 deutsche Handbücher zentral unter `content/docs/plugins/` (außerhalb des Moodle-Webroots), je Katalogprodukt ein Handbuch (task15)
### Geändert
- Translation-Spans für `qtype_aitext`-Felder: `graderinfo`/`responsetemplate` werden explizit ihren `...format`-Feldern zugeordnet; alle Wartungstasks nutzen die zentrale Zuordnung (task11)
- Kursgenerator-Track `local_elediaai_coursegen` als eigenes Suite-Plugin vorbereitet und live in der AI Suite angemeldet (ersetzt Coming-soon-Stub) (task12)
- Website-UX/UI- und Code-Review umgesetzt: mobile Navigation, Skip-Link/Fokusführung, Kontrast/Touch-Ziele, Breadcrumbs, protokollbeschränkte Chat-Quellen-Validierung, gehärteter Chat-Proxy (task17)
- CI-Matrix an deklarierte Plugin-Versionen gebunden: `scripts/ci-matrix.php` erzeugt Installationszellen nur noch innerhalb `requires`/`supported` je Plugin; Regressionstest deckt Mindest-/Obergrenzen ab (task19)

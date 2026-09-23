# Changelog — block_elediaai_tutor

Wesentliche Änderungen, neueste zuerst. Version = `release` aus version.php.
Format angelehnt an Keep a Changelog. Frühere Stände siehe git log.

## [0.29.8] – 2026-09-20

### Geändert

- **Die Startseite der Website ist ein Kurs und wird wie einer behandelt.**
  Sie war an fünf Stellen ausgenommen und verhielt sich dadurch wie das
  Dashboard — eine Sonderbehandlung, die in der Oberfläche nirgends sichtbar
  war. Betroffen ist je ein Block pro Instanz. Achtung: der Website-Kurs wird
  nie indexiert, ein Tutor dort braucht also eine Wissensbasis, um überhaupt
  aus Material zu antworten.
- **Die Wissensbasis gilt jetzt auch ohne Kurskontext.** Wer in den
  Instanzeinstellungen *Kurskontext übergeben* abschaltet und eine
  Wissensbasis wählt, bekommt genau diese Kurse — das ist der gerade Weg zu
  einem kursübergreifenden Tutor. Vorher wurde die Auswahl dort ignoriert,
  die Einstellung war also wirkungslos, wo man sie am ehesten brauchte.
- Ist dabei die fragende Person in keinem der gewählten Kurse eingeschrieben,
  antwortet der Tutor **ohne** Kursmaterial statt auf ihre eigenen Kurse
  zurückzufallen. Letzteres wäre das Gegenteil dessen, was die Auswahl
  bezweckt, und das Protokoll kann „keine Kurse" nicht ausdrücken.
- **Eigene Karte „Wissensbasis"** in den Instanzeinstellungen, neben Design,
  Gespräch & Anzeige und Technischen Einstellungen. Darauf liegen die drei
  Einstellungen, die zusammen bestimmen, woraus der Tutor antwortet:
  *Kurskontext übergeben*, *Antwortquelle* und die Wissensbasis selbst.
- Hilfetext und Feldbeschreibung sagen beides ausdrücklich: dass die
  Startseite keine Ausnahme ist, und was ohne Kurskontext gilt.

## [0.29.7] – 2026-09-20

### Geändert

- **Die Wissensbasis liegt jetzt ausdrücklich auf der Karte „Technische
  Einstellungen"**, neben „Kurskontext übergeben" und „Antwortquelle" — die
  drei zusammen sagen, woraus dieser Tutor schöpfen darf. Vorher stand die
  Gruppe in keiner der beiden Zuordnungslisten und landete über den Fallback
  des Hubs auf der letzten Karte: dasselbe Ergebnis, aber aus Versehen. Die
  Kartenbeschreibung nennt die Wissensbasis jetzt.

## [0.29.6] – 2026-09-20

### Entfernt

- **Die Einstellung „Feste Kurs-ID (optional)".** Sie nannte einen Kurs
  **ohne jede Einschreibungsprüfung** — genau die Regel, für die die
  Wissensbasis gebaut wurde. Zwei Einstellungen, die gleich aussehen und sich
  darüber uneinig sind, wer was sehen darf, sind eine Falle. Auf beiden
  Sandbox-Instanzen war sie in keinem einzigen von 70 Tutor-Blöcken gesetzt,
  die Entfernung ändert also für niemanden etwas. Wer einen Tutor auf fremdes
  Kursmaterial richten will, wählt es in der Wissensbasis — dort entscheidet
  weiterhin die Einschreibung.

## [0.29.5] – 2026-09-20

### Behoben

- **Die Wissensbasis war im Formular, das man wirklich benutzt, wirkungslos.**
  Die Block-Konfiguration leitet auf `edit_instance.php` um; dort kannte der
  Schalter den neuen Feldtyp nicht und rendete ein einfaches Textfeld, und
  `registry::sanitise()` gab für den unbekannten Typ `null` zurück — der Wert
  wäre also nicht einmal gespeichert worden. Jetzt steht dort dasselbe
  Auswahlfeld mit Bilanzzeile, Hilfetext und der Prüfung, dass eine Lehrkraft
  nur eigene Kurse wählt.

## [0.29.4] – 2026-09-20

### Geändert

- **Ausführlicher Hilfetext am Feld** (Fragezeichen): was durchsucht wird, warum die Auswahl einschränkt statt zu erweitern, wie Kursbereiche sich auflösen, was „nicht indexiert" bedeutet, warum die Startseite ausgenommen ist, was eine Lehrkraft wählen darf und was leer lassen heißt.

- **Die Wissensbasis steht jetzt vorn und aufgeklappt.** Sie lag als neunter
  von vierzehn zugeklappten Abschnitten zwischen den Design-Token und wurde
  prompt als fehlend gemeldet. Was ein Tutor weiß, ist eine andere Art von
  Frage als wie er aussieht: der Abschnitt ist die erste Gruppe, im
  Instanzformular direkt unter den Feldern zum Kurskontext und offen, im
  Profileditor ebenfalls offen.

## [0.29.3] – 2026-09-20

### Hinzugefügt

- **Die Wissensbasis ist wählbar.** Unter „Wissensbasis" lassen sich
  Kursbereiche und einzelne Kurse auswählen, aus denen dieser Tutor antworten
  darf — site-weit, je Block und je Tutor-Profil. Die Auswahl schränkt ein und
  erweitert nie: geantwortet wird nur aus Kursen, in denen die fragende Person
  eingeschrieben ist und die indexiert sind. Auf der Tutor-Startseite wirkt sie
  nicht; dort gelten immer alle Kurse der Person.
- Unter dem Feld steht, wie viele der gewählten Kurse tatsächlich indexiert
  sind — die Frage, die sonst erst mitten im Gespräch auffällt.
- Eine Lehrkraft kann am Block nur Kurse wählen, in denen sie selbst
  unterrichtet; geprüft wird beim Speichern, nicht nur in der Auswahlliste.
- Ein Kurs ohne eigenen Index zählt jetzt als „hat Material", wenn seine
  Wissensbasis welches hat. Vorher antwortete der Tutor dort modellbasiert,
  obwohl ihm Material ausdrücklich zugewiesen war.

## [0.29.2] – 2026-09-20

### Geändert

- **Das Datenschutz-Schild wandert im Gespräch mit in die Fußzeile.** Es stand
  bisher nur unter der Begrüßung — und die wird mit der ersten Nachricht
  ausgeblendet. Damit waren die Hinweise mitten im Gespräch gar nicht mehr
  erreichbar: der Einwilligungshinweis blendet sich nach der Zustimmung aus,
  und die Kopfzeile führt auf dieser Fläche keinen.
- Beide Schilde sind nie gleichzeitig zu sehen. Die stille Ecke unten rechts
  erscheint genau dann, wenn der Hero verschwindet, und trägt dann Schild und
  Mülleimer nebeneinander.

## [0.29.1] – 2026-09-20

### Geändert

- **Die Tutor-Startseite bekommt einen Mülleimer statt eines zweiten
  Gesprächs.** Gemeldet war, dass sich auf dem Dashboard kein neues Gespräch
  beginnen lässt (AI-76). Der erste Versuch war ein benannter Knopf „Neues
  Gespräch" in der Kopfzeile; der ist wieder weg. Auf dieser Seite gibt es ein
  Gespräch, und es ist die Seite — was fehlte, war der Weg, es zu beenden.
- Der Mülleimer steht in der klebenden Fußzeile, rechts unter den
  Schnellstart-Kacheln und auf der rechten Kante des Eingabefelds. Nicht neben
  dem Schild im Hero: der Hero wird mit der ersten Nachricht ausgeblendet, ein
  Knopf dort gäbe es also nur, solange es nichts zu löschen gibt. Nicht in der
  Kopfzeile: die ist auf dieser Fläche absichtlich leer, und ein einzelnes
  Zeichen in der Ecke einer breiten Karte liest sich als Zierde.
- Er erscheint mit dem Gespräch und geht mit ihm. Die Zeile behält ihre Höhe,
  solange er verborgen ist, damit das Eingabefeld beim Erscheinen nicht
  springt.
- Gelöscht wird nur das offene Gespräch, nach Rückfrage; die übrigen bleiben.
  Der Eintrag im Verlaufsbrowser verschwindet mit — dieselbe Buchführung, die
  der Verlauf für seinen eigenen Löschknopf nutzt, jetzt an einer Stelle.

## [0.29.0] – 2026-09-19

### Entfernt

- **Das Frage-Log (`qlog`) samt Opt-in-Schalter.** Es hielt Frage, Thema und
  Kurs — genau die Spalten, die `local_elediaai_core_turn` seit dem 19.09.2026
  führt, nur ohne Pseudonym und hinter einer Einstellung, die niemand fand.
  Weg sind: die Tabelle, die Aufräum-Aufgabe `prune_question_log`, die
  Einstellung `enableanalytics`, der zugehörige Privacy-Eintrag und die
  eigenen Tests. `recluster_service` liest jetzt `insights::*`.
- Der Schalter schaltete eine Auswertung ab, deren Daten ohnehin anfallen. An
  seine Stelle tritt beim Reclustern die Frage, ob überhaupt ein Backend
  eingerichtet ist — und die nur, wenn kein Adapter übergeben wurde. Sonst
  wäre es dieselbe Prüfung zweimal, und die zweite wiese einen eingereichten
  Adapter ab.

### Geändert

- Der Kursbericht des Blocks ist auf den Copiloten zusammengeschrumpft. Die
  Auswertung der Fragen steht in der Suite unter **Was die Lernenden gefragt
  haben** (`local_elediaai_core/course_insights.php`); der Menüpunkt im Kurs
  zeigt dorthin. Die Fläche des Blocks war ein zweiter Ort für dieselbe Frage.
- Der Copilot liest seine Lücken und Zitate aus Schicht B statt aus dem
  eigenen Log. Die Analyse hängt damit nicht mehr an einer Einstellung,
  sondern nur noch an der Berechtigung.

## [0.28.0] – 2026-09-06

### Hinzugefügt

- **RAG-Server-Spezifikation 0.28.0: das Chat-Argument `moodle_tools_enabled`.**
  Ein Verbot pro Zug, in Moodle zurueckzurufen — kein Hinweis, den ein Server
  abwaegen darf. Bisher konnte Moodle „diese Flaeche darf nicht handeln" nur
  sagen, indem es den undokumentierten `intent`-Hinweis auf `knowledge`
  herunterstufte; ein Server durfte den ignorieren, und nebenbei unterdrueckte
  das auch das Retrieval. Betroffen ist vor allem `mod_elli`, dessen Rollenspiel
  nie im Namen der lernenden Person handeln soll. Der alte `intent`-Weg wird
  eine Version lang weiter mitgeschickt, damit Server, die noch nicht
  nachgezogen haben, sich verhalten wie bisher. In diesem Plugin aendert sich
  nur die Dokumentation — gesendet wird das Argument von
  `local_elediaai_chatengine`.

## [0.25.1] – 2026-09-01

### Geändert

- **Das Abzeichen an belegten Antworten heisst nicht mehr „Kursbasiert",
  sondern „Quellenbasiert"** (englisch „Source based"). Der alte Text
  versprach mehr, als die Antwort haelt: laeuft der Chat websiteweit, geht gar
  keine `course_id` ans Backend, die Suche laeuft also ueber die ganze
  Wissensbasis des Mandanten und nicht ueber einen Kurs. Und eine Wissensbasis
  kann Inhalte enthalten, die nie aus einem Moodle-Kurs kamen. Die Kurzhinweise
  sprechen jetzt ebenfalls von der Wissensbasis. Die Zeichenketten liegen in
  der Chat-Engine; der Block haelt nur noch seine eigene Kopie fuer den Bericht.

## [0.25.0] – 2026-09-01

### Geändert

- **Der Tutor benennt jetzt die Grundanweisung, in der er beantwortet wird**
  (`system_prompt_id: tutor`). Fuer den Tutor aendert sich damit nichts — es ist
  genau der Wert, den ein Server annimmt, wenn das Argument fehlt. Der Vertrag
  in `docs/rag_server_spec.md` ist entsprechend erweitert (neues Argument,
  praezisierte `persona`-Zeile), weil ihn inzwischen drei Oberflaechen und zwei
  Backends teilen und bisher **jede** davon als Tutor beantwortet wurde.

## [0.24.1] – 2026-09-01

### Behoben

- **Das Plus im Chat-Kopf oeffnete keine neue Unterhaltung.** Der Knopf leerte
  nur das Protokoll und liess den Zeiger des Panels fallen — und ein Turn ohne
  Unterhaltungs-Id heisst fuer die Engine „mach dort weiter, wo ich aufgehoert
  habe". Die lernende Person sah einen leeren Verlauf, waehrend das Backend die
  komplette vorige Unterhaltung mitgeschickt bekam, und in der Gespraechsliste
  erschien nie ein zweiter Eintrag. Seit der Umstellung auf die Chat-Engine
  (DEL-517) so; vorher war die Unterhaltungs-Id eine Zeichenkette, die leer an
  den RAG-Server ging und dort einen frischen Faden begann.

  Der Wunsch wird jetzt ausgesprochen (`newthread`) und faehrt auf beiden
  Transportwegen mit. Er gilt bis eine Unterhaltung zurueckkommt, damit ein
  zweiter Anlauf desselben Turns — ein Wiederholen, oder der Ruecksprung vom
  Datenstrom auf den gepufferten Weg — nicht doch in der alten landet. Die
  vorige Unterhaltung bleibt erhalten; angelegt wird die neue erst vom Turn,
  ein Klick ohne Nachricht hinterlaesst also keinen leeren Faden.

- Dieselbe Luecke beim Loeschen: wer die gerade offene Unterhaltung aus der
  Liste entfernte, landete mit der naechsten Nachricht stillschweigend in der
  davor.

## [0.24.0] – 2026-08-31

### Behoben

- **Die Instanz-Einstellungen wirkten auf keinen einzigen Turn.** Persona,
  Antwortmodus, Tagesbudget und die Sperre der Stilwahl liegen an der
  Block-Instanz, `helper::block_config()` liefert aber nur fuer einen
  Block-Kontext etwas — und die Chat-Platzierung reichte ihr grundsaetzlich
  einen Kurs- oder Systemkontext, weil `instanceid` beim Tutor die Kurs-Id ist.
  Vier Aufrufstellen bekamen dadurch immer ein leeres Objekt; konfiguriert
  wurde, gewirkt hat nichts.

### Geändert

- Der Tutor sendet wieder ueber eigene Endpunkte
  (`block_elediaai_tutor_send_message` und `stream.php`), damit ein Turn die
  Flaeche benennen kann, von der er kommt. Der Faden bleibt am Kurs — die
  eigenstaendige Seite beantwortet ueber `view.php?courseid=` jeden Kurs, ohne
  dass es dafuer eine Block-Instanz gaebe —, die Konfiguration kommt jetzt von
  der Instanz, die die Person vor sich hat. Der benannte Kontext wird
  serverseitig gegen den Kurs geprueft, bevor er geglaubt wird.
- Verlauf, Gespraechsliste und Loeschen bleiben bei der Chat-Engine. Am Vertrag
  mit dem RAG-Backend aendert sich nichts: es werden dieselben Argumente
  gesendet, nur mit den Werten, die tatsaechlich eingestellt sind.

## [0.23.1] – 2026-08-31

### Behoben

- Die Chat-Platzierung rief `security::memory_optin_tool_name()` auf. Die
  Methode ist mit dem Werkzeugkatalog in die Chat-Engine gewandert und im Block
  entfallen; jeder Tutor-Turn brach dort ab, bevor er ein Backend erreichte.
  Gefragt wird jetzt `connection::tool_name('memoryoptin')`.
- Der Kurschat brach mit "Ungueltiger Parameterwert" ab: das Panel schickt seit
  dem Umbau auf die Chat-Engine `answerstyle` und `intent` mit jedem Turn, der
  gemeinsame Endpunkt kannte beide nicht und Moodle lehnt unbekannte Schluessel
  ab. Beide Einstiegspunkte der Engine nehmen sie jetzt als optionale Hinweise
  an. Der Antwortstil wird weiterhin serverseitig aufgeloest: gesperrt bleibt
  gesperrt, ein unbekannter Stil faellt auf die Instanzvorgabe zurueck.

## [0.20.2] – 2026-08-14

### Geändert

- Der optionale Core-Quota-Adapter reicht fenstergebundene Reservierungs-Handles
  durch `reserve()`, `commit()` und `release()`. Requests werden dadurch auch
  über Stunden-, Tages-, Monats- oder Rollenwechsel hinweg dem ursprünglichen
  Quota-Fenster zugeordnet (DEL-518).

## [0.20.1] – 2026-08-06

### Geändert

- Tutor-Shell nutzt den gestempelten `lh-core`-Block aus `design/lh-core.css`
  (Zone-A-Actionbar, 44px-Hit-Areas, sichtbare Fokus-Ringe, Full-Shell-Content-Breite);
  vendored `lh-*`-Regeln in `body:not(.theme-lernhive)`-Scope entfernt (task22, SUI-393)
- Site-weiter Shell-Token-Leak geschlossen: keine `body:not(.theme-lernhive)`-Deklarationen
  mehr in `styles.css`; Lint-Script `scripts/lint-vendored-shell.php` prüft
  repo-weit auf verbleibende `--lh-*`-Deklarationen in `body`/`:root`-Regeln (task23, SUI-381)
- Tutor-Shell-Seiten verwenden das `report`-Page-Layout und die Standard-72-rem-Breite;
  Help-Seiten bleiben auf Lesebreite (58 rem) begrenzt

## [0.20.0] – 2026-08-03
### Geändert
- Referenzen auf die umbenannten Suite-Komponenten nachgezogen (`local_lernhive_ai` → `local_elediaai_core` usw.); keine Verhaltensänderung (SUI-560)
### Behoben
- Kontrakt-Test der geteilten Token-Quota erwartete zwei Usage-Zeilen (Stunde, Tag), obwohl `quota_manager` seit dem Monatsfenster drei schreibt. Erwartung korrigiert und um eine Prüfung der Fensterarten ergänzt; der Test lief nur in der CI-Gruppe `tutor-with-quota` und dort zuletzt nicht mit (SUI-560)
### Entfernt
- Einmal-Migration vom früheren Komponentennamen `block_eledia_aitutor` (`classes/local/component_migration.php`, `db/install.php`, zugehoeriger Test). Die beiden Upgrade-Schritte bleiben als reine Versions-Gates stehen (SUI-560)

## [0.19.14] – 2026-08-02
### Geändert
- Chat-Turn reserviert die Token-Quota (Prompt-Schaetzung plus Completion-Puffer) atomar vor dem RAG-Call und gibt sie im Fehlerfall wieder frei; `token_quota`-Adapter stellt `reserve()`/`commit()`/`release()` bereit (SUI-517)

## [0.19.13] – 2026-08-01
### Sicherheit
- Das RAG-Authentifizierungstoken wird jetzt tatsächlich verschlüsselt at rest
  gespeichert (`admin_setting_encryptedpassword` statt `configpasswordunmask`)
  und nur serverseitig in `security::rag_auth_token()` entschlüsselt. Ein
  Upgradeschritt verschlüsselt vorhandene Klartexttokens genau einmal
  (idempotent, keine Doppelverschlüsselung); leere Werte und Tokenrotation
  funktionieren weiter. Sprachstrings und `docs/security.md` beschreiben den
  Schutz nun zutreffend (SUI-416, P2).

## [0.19.12] – 2026-08-01
### Behoben
- Die Komponenten-Umbenennung migriert Dateien jetzt mit korrekt neu
  berechnetem `pathnamehash`, sodass gespeicherte Logos und Avatare über
  `get_file()` und Pluginfile-URLs auffindbar bleiben statt 404 zu liefern.
  Kollisionen erhalten die kanonische Datei und legen Legacy-Inhalte
  deterministisch unter `legacy-<id>-…` ab; ein Reparatur-Upgradeschritt
  korrigiert bereits umgestellte Sites (SUI-382).

## [0.19.11] – 2026-08-01
### Behoben
- Fixkurs-Block-Capability-Bypass geschlossen (SUI-454, P1): Die kursbezogenen
  AJAX-Endpunkte (`send_message`, `get_conversations`, `get_history`,
  `clear_conversation`) prüfen die fachliche Capability nun zusätzlich im
  autoritativen Kurskontext. Ein Tutorblock mit `fixedcourseid` auf Kurs B — im
  Dashboard oder in Kurs A platziert — kann ein `CAP_PROHIBIT` in Kurs B für
  `use`, `viewhistory` oder `deleteownhistory` nicht mehr umgehen; die
  bestehende Block-Kontext-Prüfung bleibt für lokale Instanzverbote erhalten.

## [0.19.10] – 2026-07-31
### Behoben
- Die reguläre Moodle-Kontolöschung entfernt jetzt alle fünf nutzerbezogenen
  Tutor-Tabellen und die LTM-Präferenz über die zentrale Löschroutine
  (SUI-423, bug29).
- Externe Transkripte und Langzeitgedächtnis werden best effort angefordert,
  solange das Nutzerkonto und sein MCP-Token noch aktiv sind; der nachgelagerte
  `user_deleted`-Observer sichert die lokale Bereinigung als Fallback ab.

## [0.19.9] – 2026-07-31
### Behoben
- AJAX-Kontexte sind jetzt serverseitig an globalen Chat, den exakten Kurs
  oder eine konkrete Tutorblock-Instanz gebunden; fremde Blockkomponenten und
  abweichende System-/Kurskontexte werden abgelehnt (SUI-414, bug28).
- Gesprächsliste, Verlauf, Fortsetzung und Einzellöschung prüfen den aktuellen
  Kurszugriff und lokale Capability-Verbote erneut; globaler Verlauf enthält
  ausschließlich globale Gespräche (task19).

## [0.19.8] – 2026-07-31
### Behoben
- Selbstlöschung entfernt jetzt vollständig und nutzerbezogen Gespräche,
  Fragenprotokolle, Consent, Nutzung, Diagnostik und LTM-Präferenz; UI und
  Privacy API verwenden dieselbe zentrale Löschroutine (SUI-383, bug27).
- Lokale Gesamt-/Einzelzähler und externe Fehlschläge werden korrekt
  ausgewiesen; ein externer Fehler blockiert die lokale Löschung nicht und das
  First-Use-Consent-Gate wird anschließend erneut aktiviert (task18).

## [0.19.7] – 2026-07-31
### Neu
- Optionale `local_elediaai_core`-Token-Quota gekapselt: Tutor läuft ohne harte Abhängigkeit im LLM-only-Betrieb (lokaler Schätz-Fallback), nutzt bei installierter Suite aber die gemeinsame Quota (task16).
### Behoben
- Undeklarierte `local_elediaai_core`-Abhängigkeit im Chat behoben: Quota-Aufrufe laufen über `local/token_quota` und brechen ohne Companion-Plugin nicht mehr mit `Class not found` ab (bug25, S1).
- DDL-Kollision nach Komponenten-Rename behoben: kanonische Tabellennamen und idempotenter Install-/Upgrade-Migrator übernehmen den Altbestand verlustfrei, bevor Alt-Tabellen entfernt werden (bug26, S1; task17).
- Privacy-API-Löschung propagiert nun auch bei Einzel-, Batch- und Systemkontext-Löschung an den RAG-Dienst (`tutor_delete_user_data`); externe Fehler blockieren die lokale Löschung nie (task11).
### Geändert
- Block auf Komponente `elediaai_tutor` umbenannt: Pfad, Namespace, Sprachdateien, AMD-Module, Capabilities, Webservice-Namen, URLs und Behat-Tags umgestellt; Persistenz per Datenmigration übernommen (task15/task17).
- Standalone-Tutor an die Plugin-Shell-Breite angeglichen: shell-spezifischer Override hebt die innere 860-px-Grenze nur im Shell-Kontext auf (bug24, S3; task13).
- Supportvertrag konsolidiert: Moodle 4.5–5.2 / PHP 8.3+; CI prüft die Supportgrenzen mit harten Installations-, PHP-Lint- und PHPUnit-Gates (task14).

## [0.19.x] – 2026-06-27
### Behoben
- Stored XSS über ungeprüfte SVG-Uploads (Logo/Avatar) geschlossen: Force-Download aller Branding-Dateien plus gehärtete `tutor_io::is_safe_image()` (bug13, S2).
- `unserialize()` in `db/upgrade.php` durch `unserialize_object()` ersetzt (bug03, S2).
- LTM-Sync kommuniziert nicht mehr vor Privacy-Consent extern: `ltm::sync_to_rag()` prüft zentral den Consent (bug04, S2).
- `customcss` gegen `</style>`-Breakout, `@import`, `expression()` und `javascript:` gehärtet (bug14, S3).
- Citation-URLs serverseitig mit `PARAM_URL` normalisiert (bug05, S3); Admin-Navbar-Hook ohne Inline-JS/CSS, CSP-kompatibel (bug06, S3).
- Help-Link der Plugin-Shell zeigt auf plugin-eigene `help.php` statt auf `local_lernhive`; keine Runtime-Abhängigkeit mehr (bug09, S1).
- Diverse Fokus-/A11y-Fixes in Chat- und Settings-UI (bug11, bug15).
### Geändert
- Moodle CodeChecker (moodle-cs) grün: 0 errors / 0 warnings (task07).

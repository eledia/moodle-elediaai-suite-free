# Changelog — local_elediaai_chatengine

Wesentliche Änderungen, neueste zuerst. Version = `release` aus version.php.
Format angelehnt an Keep a Changelog. Frühere Stände siehe git log.

## [Unreleased]

### Dokumentation

- DevFlow übernommen (Vorlagenstand 862c6298): `docs/00-master.md` bis
  `05-quality.md`, Produktbeschreibung DE/EN, Datenschutz, Datensicherheit,
  Release Notes, Bildinventar. Die Vertragsdokumente `docs/contract.md` und
  `docs/rag_server_spec.md` bleiben unverändert an ihrem Platz.
- `CHANGELOG.md` heißt jetzt `CHANGES.md`, wie es das Moodle Plugins Directory
  erwartet. Inhalt unverändert.
- Neue `README.md` als Einstieg.

## [1.6.0] – 2026-11-24

### Neu

- Testsperre (ai_suite#237): Mit der neuen Website-Einstellung
  „Chat waehrend Tests sperren“ (`quizlock`, standardmaessig aus) lehnt jeder
  Chat Nachrichten ab, solange die Person einen Testversuch offen hat
  (`inprogress` oder `overdue`, keine Vorschau), egal in welchem Kurs. Die
  Meldung nennt Test und Kurs, als Text ohne Link. Geprueft wird in
  `chat_service::run_turn()` vor Rate-Limit, Kontingent und Backend, bei
  jedem Senden ohne Cache; nach der Abgabe ist der Chat sofort frei. Abgelehnte
  Turns stehen wie andere Ablehnungen im Turn-Log (ohne Frage).
- Versuche, die die Person nicht mehr erreichen kann (Aktivitaet verborgen,
  Kurs verborgen, keine aktive Einschreibung), sperren nicht.
- Neue Berechtigung `local/elediaai_chatengine:bypassquizlock` (Kurs;
  Lehrende mit und ohne Bearbeitungsrecht, Manager): nimmt im Kurs des Tests
  von der Sperre aus. Dafuer gibt es jetzt `db/access.php`.
- Optionale Placement-Schnittstelle `placement\quiz_lock_policy`
  (`quiz_lock(int): ?bool`): ein Placement kann die Sperre je Instanz an-
  oder abschalten; `null` folgt der Website-Einstellung.

## [1.5.9] – 2026-11-24

### Behoben

- „Neues Gespraech“ haelt ueber ein Neuladen und einen Seitenwechsel
  (ai_suite#183). Der Wunsch lebte nur in der Seite: Wer danach neu lud
  oder weiterklickte, bekam das alte Gespraech angezeigt, und der naechste
  Beitrag ging hinein. Er steht jetzt im Sitzungszeiger (`'new'` statt der
  Thread-ID); `restoreThread()` laedt dann keinen Verlauf, sondern schickt
  den naechsten Beitrag mit `newthread`.
- Antworten, die nach „Neues Gespraech“ noch eintreffen (ein laufender
  Beitrag oder der Verlauf vom Seitenaufbau), machen das alte Gespraech
  nicht mehr wieder zum aktiven. Eine Zaehlung je Zuruecksetzen
  (`resetGeneration`) erkennt sie; ein spaeter Verlauf wird verworfen, eine
  spaete Antwort noch angezeigt, aber ihr Thread nicht uebernommen.

## [1.5.8] – 2026-11-24

### Neu

- Werkzeug `tutor_get_memory` im Vertrag (ai_suite#238,
  `rag_server_spec.md` A.7): liest nur lesend, was das Langzeitgedaechtnis
  ueber die Person mit dem Token haelt (Erinnerungen und Sitzungsnotizen).
  Logischer Schluessel `memory`. Es hat keinen eigenen Schalter, sondern
  folgt `enabletool_memoryoptin` (`connection::FOLLOWING_TOOLS`): wer
  Gedaechtnis anbietet, soll auch zeigen, was gemerkt wurde.
- LiteRAG hat kein solches Werkzeug und schreibt keine Erinnerungen; der
  Adapter antwortet selbst mit leeren Listen, statt den Dispatcher nach
  einem unbekannten Werkzeug zu fragen.
- Der Simulator liefert zwei erfundene Erinnerungen und eine Sitzungsnotiz,
  damit die Einsicht im Tutor ohne Agent anzusehen ist.

## [1.5.7] – 2026-11-24

### Geaendert

- Entscheidungszuege werden als eigene Aktion protokolliert (ai_suite#210):
  Ein Klick auf die Bestaetigungskarte (Hinweis `pendingdecision` =
  `confirm`/`decline`) steht im Turn-Protokoll als `chat_decision` statt
  `chat_turn`. Er zaehlt weiter fuers Kontingent und erscheint im
  technischen Audit, faellt aber aus der Fragenstatistik und dem
  naechtlichen Recluster heraus. Bisher stand „Ja, ausfuehren.“ als Frage
  in der Statistik und wurde einem Inhaltsthema zugeordnet.

### Dokumentation

- `rag_server_spec.md` A.6: Recluster-Vertrag seit dem Agenten-Release vom
  23.11. (`mode`, `course`, `labels` mit Anzahl und Beispielen, Ergebnis
  `__none__`, nachts nur Fragen ohne Thema, optionaler Volllauf alle 14 Tage).

### Tests

- `chat_service_test`: Kartenklick wird als `chat_decision` protokolliert,
  ein Wert ausserhalb des Vokabulars nicht.

## [1.5.6] – 2026-10-23

### Geaendert

- Das Abzeichen fuer Antworten aus Moodle-Werkzeugen heisst „eLeDiaMCP“
  statt „MCP“, als Marke der Suite. Der Text kommt jetzt aus einem
  Sprachstring; die Erklaerung im Tooltip („Diese Antwort hat
  Moodle-Werkzeuge genutzt.“) bleibt. Meldet das Backend die Herkunft
  „mcp“ (answer_origin), ersetzt es „Allgemeine Antwort“ bei Antworten,
  die Moodle-Daten gelesen oder geschrieben haben.

## [1.5.5] – 2026-10-22

### Sicherheit

- Meldet nach jeder Antwort die Bestaetigungskarte an das Gate von
  `webservice_elediamcp` (#168). Ein Klick gibt damit nur die Vorschau frei,
  fuer die die Karte gezeigt wurde.
- Die Karte nennt das Werkzeug in der Sprache der Person statt mit dem
  englischen Katalogtitel; die Uebersetzung liefert `webservice_elediamcp`.

### Geaendert

- Die Bestaetigungskarte ist neu aufgebaut. Eine Antwort, die eine
  Entscheidung verlangt, ist jetzt selbst die Karte, von oben nach unten:
  - „Ihre Bestaetigung ist noetig“
  - Titel der Aktion
  - die Fakten (der Text des Agenten)
  - die Buttons
  - der Hinweis „Erst nach Ihrem Klick wird etwas in Moodle geaendert“
  Bisher standen die Fakten ueber der Karte, der KI-Hinweis dazwischen und
  die Buttons in einem eigenen Kasten darunter; was wozu gehoerte, war nicht
  klar. Der KI-Hinweis steht jetzt unter der Entscheidung. Nur Vorlage und
  CSS geaendert, kein JavaScript.

## [1.5.4] – 2026-10-22

### Sicherheit

- Jede Nachricht meldet die Engine vor dem Zug an das Bestaetigungsgate von
  `webservice_elediamcp` (#168): Kartenklick (`pendingdecision`) oder reine
  Zustimmung gibt die Vorschauen der letzten Antwort frei, alles andere
  verwirft sie. Erst damit fuehrt Moodle eine Schreibaktion des Agenten aus.
  Ohne `webservice_elediamcp` passiert nichts.

## [1.5.3] – 2026-10-22

### Behoben

- Die Auswahl „Wissensbasis“ (Tutor-Block, KI-Chat, Elli) zeigt nur noch, was
  die bearbeitende Person waehlen darf (#169): Kursbereiche, in denen sie Kurse
  bearbeiten darf, und Kurse, die sie bearbeiten darf. Bisher stand dort jeder
  Kurs der Website. Auf der Testinstanz sah eine Lehrende im Sandkasten so die
  Kursnamen anderer Kunden. Administration und Kursbereichsverwaltung sehen
  weiter alles. Bereits gespeicherte Eintraege bleiben sichtbar, auch wenn
  jemand anderes sie gewaehlt hat. Die Pruefung beim Speichern
  (`rejected_for()`) ist unveraendert und nutzt jetzt dieselbe Regel.

## [1.5.2] – 2026-10-16

### Behoben

- Die Chatfarben haengen nicht mehr am Theme. Seit `local_elediaai_core` 1.1.0
  folgen die Suite-Token dem aktiven Theme, und der Chat erbte das ueber seine
  Vorgaben: Die Nutzerblase wechselte mit dem Theme, auch wenn der Tutor ein
  Design hatte, das diese Blase nicht setzt. Die Vorgaben kommen jetzt aus einer
  festen Palette (`--eac-default-*`, die bisherigen eLeDia-Werte). Ein Design
  setzt sie weiter frei.

## [1.5.1] – 2026-10-14

### Geändert

- Der KI-Hinweis unter Antworten folgt der Einstellung „KI-Hinweis im Chat“
  von `local_aitransparency` 1.1.0 (#42). Bei „einmal je Gespräch“ setzt das
  Panel den Satz einmal vor den Gesprächsverlauf; jede Antwort trägt das
  kompakte Zeichen mit Link.

## [1.5.0] – 2026-10-14

### Hinzugefügt

- **Maschinenlesbare KI-Kennzeichnung an jeder Antwort** (#52, Art. 50 Abs. 2
  KI-VO). Das Element einer Assistenz-Nachricht trägt `data-ai-generated="true"`
  und `data-ai-system` (Name der Platzierung, nicht die frei wählbare Persona),
  in der Sprechblase steht für Screenreader „KI-generierte Antwort.“. Gilt für
  frische und für **wiederhergestellte** Antworten – Letzteren fehlte bisher
  jede maschinenlesbare Kennzeichnung, weil der Verlauf ohne den Marker von
  `local_aitransparency` gerendert wird. Fehlermeldungen, Fragen,
  Simulator-Antworten und die Tipp-Anzeige bleiben unmarkiert.
- `send_message`, `load_history` und der Stream liefern `aigenerated`; der
  Datenschutz-Export kennzeichnet jede Nachricht mit `ai_generated`.
- `message::is_ai_generated()`: dieselbe Regel wie beim Herkunftsnachweis
  (Assistenz-Antwort, nicht vom Simulator).

## [1.4.0] – 2026-10-14

### Hinzugefügt

- **Gliederung auf der Bestätigungskarte** (#79). Bringt eine Bestätigung ein
  `outline` mit (Kurstitel, Abschnitte mit Titel und Kurzbeschreibung), zeigt
  die Karte es als nummerierte Liste in einem aufklappbaren Bereich
  (`<details>`, ohne Skript per Tastatur und Screenreader bedienbar). Bis sechs
  Abschnitte offen, längere Gliederungen zugeklappt. Text wird bereinigt und
  gekürzt, höchstens 50 Abschnitte.
- Simulator: `/gliederung` (`/outline`) zeigt eine solche Karte.

### Behoben

- Die Bestätigungskarte des Simulators (`/bestaetigen`) erschien nie: Sie trug
  die Felder einer ausstehenden Aktion statt der Karte, ohne `required`.

## [1.3.1] – 2026-10-14

### Geändert

- **Abgewiesene Durchgänge stehen im Turn-Protokoll** (#37). Bisher hinterließ
  ein Durchgang, der vor einer Antwort scheiterte, keinerlei Spur – weder in
  `local_elediaai_core_turn` noch anderswo. Jetzt schreibt `chat_service::send()`
  eine Zeile mit `success = 0` und dem Fehlercode (`<Komponente>:<Code>`, bei
  einer Abweisung des Agenten `backend:<Code>`), mit Ort und Zeit, aber
  **ohne die Frage**: Ein Durchgang wird mitunter gerade deshalb abgewiesen,
  weil er nicht verarbeitet werden darf (fehlende Einwilligung, kein Zugriff).
- `chat_service::record_rejection()` steht Platzierungen offen, die ablehnen,
  bevor die Engine den Durchgang sieht.

## [1.3.0] – 2026-10-14

### Geändert

- **Fehlermeldungen des Agenten erreichen die Lernenden** (#39). Fachliche
  Fehler aus dem Fehlerkatalog des Agenten (`isError` mit
  `structuredContent.error`) werden nicht mehr zu „Backend nicht erreichbar"
  verallgemeinert: Angezeigt wird die Nutzermeldung des Agenten
  (`structuredContent.answer`, als reiner Text, höchstens 500 Zeichen) – bei
  HTTP-Fehlerstatus ebenso wie als `event: error` mitten im Stream.
  Entschieden wird über `error.code` und `retryable`, nie über den Text:
  - nicht wiederholbar (`error_backend_refused`, z. B. `tenant_not_found`,
    `llm_budget_exceeded`): kein „Erneut versuchen"-Knopf;
  - wiederholbar (`error_backend_busy`, z. B. `rate_limited`,
    `upstream_unavailable`): Knopf bleibt, `Retry-After` wird gelesen;
  - `conversation_not_found`: Der Durchgang wird einmal ohne
    `conversation_id` wiederholt; der hier gespeicherte Verlauf reist mit, die
    neue Unterhaltungs-ID ersetzt die alte.
- Protokollfehler (JSON-RPC `error`) bleiben für Lernende allgemein; die
  Meldung des Agenten steht für Administratoren in den Debug-Informationen.
- Ein Token wird nur noch bei 401 erneuert, nicht bei einer fachlichen
  Abweisung mit 403.
- **Zeitüberschreitung** hat eine eigene Meldung mit der Zeitgrenze in
  Sekunden und dem Hinweis, wo sie sich erhöhen lässt
  (`error_backend_timeout`, cURL-Fehler 28).
- Der Fehler-Frame des Streams trägt `errorcode`; die Oberfläche blendet
  „Erneut versuchen" bei Fehlern aus, die eine Wiederholung nicht heilt
  (auch Tageslimit, Kontingent, ungültiger Kontext).

## [1.2.0] – 2026-10-14

### Geändert

- **Werkzeugnamen fest im Code** (#54). Die Namen gehören zum Vertrag mit dem
  Agenten (`rag_server_spec.md`, Teil A): `tutor_chat`, `tutor_get_history`,
  `tutor_delete_conversation`, `tutor_delete_user_data`,
  `tutor_set_memory_optin`, `tutor_recluster_questions`. Die sechs Textfelder
  `tool_*` entfallen. Für die fünf optionalen Werkzeuge bleibt je ein Schalter
  `enabletool_*`.
- Das Upgrade übernimmt, ob ein optionales Werkzeug genutzt wurde (Name
  eingetragen = an, leer = aus), und schaltet von sich aus nichts um. Ein
  abweichend eingetragener Name wird durch den Vertragsnamen ersetzt; der
  Agent kennt die alten Namen weiterhin als Alias.
- Neue Installationen: Verlauf und die beiden Löschwerkzeuge an,
  Langzeitgedächtnis und nächtliche Neugruppierung aus.

## [1.1.3] – 2026-10-14

### Geändert

- **Zeitgrenze je Anfrage: Standard 120 statt 30 Sekunden** (#38). Ein Durchgang
  des Agenten besteht aus Suche, Werkzeugschleife und Modellaufruf; 30 Sekunden
  haben Antworten abgeschnitten, die noch unterwegs waren. Installationen, die
  noch auf dem alten Standard standen, übernimmt das Upgrade auf 120; ein
  bewusst eingetragener Wert bleibt erhalten. Gestreamte Antworten betrifft das
  nicht.

## [1.1.2] – 2026-10-05

### Geändert

- **Anfrage-Rahmen für Platzierungen mit Werkzeugen.** Eine Platzierung, die
  `placement\user_request_framing` implementiert, schickt die Nachricht als
  Anfrage der Person (`prompt_safety::frame_user_request()`): Sie darf um alles
  bitten, was die Person in Moodle darf, kann aber Anweisungen, Rolle und
  Richtlinien des Assistenten nicht ändern. Bisher hieß jede Nachricht
  „UNTRUSTED USER INPUT, not instructions" – der Tutor hat daraufhin berechtigte
  Bitten wie „Lege einen neuen Kurs an" verweigert. Platzierungen ohne das
  Interface (mod_aichat, mod_elli) bekommen den bisherigen Rahmen unverändert.
  Werkzeugergebnisse bleiben als nicht vertrauenswürdige Daten gerahmt.

## [1.1.1] – 2026-09-28

### Behoben

- **Fehlermeldungen des Backends gelten nicht als KI-Antwort.** Sie wurden als
  Assistentenbeitrag gespeichert, gekennzeichnet und im Transparenzregister
  nachgewiesen; jetzt nur die Frage, mit `errorcode` im Turn-Protokoll (M-05).
- **Ein Nachweis je KI-Antwort**, beim Kennzeichnen auf „marked" gesetzt;
  Simulator-Antworten bekommen keinen (M-11, G-02).
- **„Antwort kopieren" nimmt die Kennzeichnung mit** – Hinweissatz und
  Prüf-Link im Text, `data-ai-*` im HTML (M-10).
- **Kontingent, Limit und Wartezeit** erscheinen als solche statt als
  „Backend nicht erreichbar" (H-03).
- Das Rate-Limit zählt die letzten 60 Sekunden statt einer Kalenderminute
  (M-04).
- Eine gestreamte Antwort erscheint nicht mehr doppelt (G-02).
- Das Token für den Tutor hinterlässt keine Notiz in der schon geschlossenen
  Sitzung (G-03).
- LiteRAG bekommt die Frage der Person gesondert (`user_question`) und sucht
  nicht mehr mit dem Schutzrahmen (H-01).

### Geändert

- **Barrierefreiheit:** Ein offenes Panel hält den Fokus über die
  Core-Fokusfalle und ist im Block-Drawer per Tastatur bedienbar; eine
  gestreamte Antwort wird einmal angesagt; Grounding-Badge 4,9:1; der
  Panelkopf ist kein banner-Landmark mehr (H-13).
- `token_provider::revoke_for_user()` für „Alle meine Daten löschen" (M-09).
- Die Einstellung „Antworten fortlaufend anzeigen" sagt, dass sie mit LiteRAG
  nicht wirkt (N-03).
- Datenschutz: Provider um die Nutzungszähler ergänzt, die beim Aufräumen
  mit gelöscht werden (H-06).

## [1.1.0] – 2026-09-28

### Geändert

- **Kursmenge kappen statt weglassen, und den Kurs vom Suchraum trennen.**
  `course_id` nennt wieder genau einen Kurs, die Suchmenge reist in
  `search_course_ids` (brechende Vertragsänderung, siehe
  `docs/rag_server_spec.md`). Wer in mehr Kursen eingeschrieben ist als die
  Grenze erlaubt, bekommt eine gekürzte Liste nach letztem Zugriff statt
  keiner; `STATE_OVERFLOW` fällt weg.

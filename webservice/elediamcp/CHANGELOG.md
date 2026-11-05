# Changelog

All notable changes to the **webservice_elediamcp** plugin are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.20.0] – 2026-11-05

### Hinzugefuegt

- Text der Bestätigungskarte in der Sprache der Person (#198, Entscheidung
  vom 05.11.2026). Alle Werkzeuge mit `confirm`-Schritt liefern in der
  Vorschau `preview.card_summary`: ein bis zwei Sätze aus den Sprachdateien
  (Deutsch und Englisch), ohne Werkzeugnamen und ohne Anweisung an das Modell.
  Neu sind es `moodle_create_course`, `moodle_update_course`,
  `moodle_create_activity`, `moodle_update_activity`, `moodle_manage_sections`,
  `moodle_create_user`, `moodle_enrol_user`, `moodle_grade_submission`,
  `moodle_send_message`, `moodle_message_course_students`,
  `moodle_generate_questions`, `moodle_generate_h5p` und
  `moodle_selfstudy_create_quiz`; die Quiz-Werkzeuge hatten es seit 1.19.1.
  Die englische `summary` bleibt unverändert, sie richtet sich an das Modell.
  Der bestätigte Aufruf trägt das Feld nicht.
- `moodle_update_activity`, `moodle_send_message` und
  `moodle_message_course_students` haben dafür ein `preview`-Objekt in der
  Vorschau bekommen, das nur `card_summary` enthält; ihre bisherigen Felder
  sind unverändert.
- Klasse `card_summary` und Test `tests/card_summary_test.php`: Jedes
  Werkzeug mit `confirm`-Argument muss `preview.card_summary` in
  `output_schema()` deklarieren. Konvention in `docs/03-dev-doc.md`.

## [1.19.2] – 2026-11-05

### Hinzugefuegt

- Geprüfte Antwort im Ergebnis (#195). `moodle_quiz_check_question` liefert
  `answer_checked`, die Antwort so, wie die Person sie sieht: die Beschriftung
  der gewählten Option (Liste bei Mehrfachauswahl und Zuordnung, jeweils
  „Begriff → Auswahl“), bei Freitext und Zahl der Text (gekürzt ab 200
  Zeichen). `moodle_quiz_save_answers` liefert `answers_checked`, je Slot
  dasselbe (null für eine geleerte Antwort). Anlass: Das Modell hatte einmal „Wahr“ als `value: "0"` geschickt,
  Moodle hat „Falsch“ geprüft und die Frage festgeschrieben, ohne dass es
  jemand bemerkte.
- `value` und `values` nehmen bei Wahr/Falsch, Auswahl- und Zuordnungsfragen
  auch die Beschriftung der Option an (ohne Groß-/Kleinschreibung und
  überzählige Leerzeichen; bei Wahr/Falsch auch die englische), bei Zuordnung
  auch die Beschriftung des Begriffs statt seines Schlüssels. Zwei Optionen
  mit gleicher Beschriftung sind mehrdeutig, ebenso eine Eingabe, die der
  Wert der einen und die Beschriftung einer anderen Option ist (eine Option
  „4“ neben der Option mit der Id 4): dann wird nicht geraten, der Fehler
  nennt die Optionen.
- Passt eine Antwort zu keiner Option, trägt der Fehler `retryable: true` und im
  `context` die gültigen Optionen (`options`, Wert und Beschriftung), aber
  **keinen** `user_message`: Der Agent zeigt `user_message` wörtlich an und
  beendet den Zug, hier soll das Modell den Aufruf selbst korrigieren. Allgemein
  gilt: Setzt ein Werkzeug `retryable` (bool) im Kontext, steht es in der
  Antwort neben dem Fehler und nicht im `context`; ein `user_message` setzt es
  weiterhin auf `false`.
- Vorschau von `moodle_quiz_start_attempt` und `moodle_quiz_finish` als
  `preview`-Objekt mit `card_summary` (ein bis drei Sätze Klartext in der
  Sprache der Person, Englisch und Deutsch, ohne Werkzeugnamen), dazu
  `quiz_name`, beim Start `attempt_number`, `attempts_allowed` und
  `time_limit_minutes` (null ohne Grenze), bei der Abgabe `answered` und
  `unanswered`. Die Fakten stehen nur dort.

### Geändert

- Die Vorschau von Start und Abgabe trägt `state`, `unanswered`, `max_grade`,
  `attempts_allowed`, `attempts_used`, `time_limit_seconds`, `page_count` und
  `current_page` nicht mehr auf oberster Ebene; die Karte druckte sie sonst
  neben dem Text („State: inprogress“, „Unanswered: 0“). Das Ausgabeschema
  verlangt für beide Werkzeuge nur noch die Felder, die jede Antwort hat. Nach
  der Abgabe (und beim Weiterführen) bleiben `state` und `unanswered` wie bisher.
- Die `summary` der Quiz-Werkzeuge nennt keine Werkzeugnamen und keine
  Anweisungen ans Modell mehr, nur noch, was passiert ist. Was als Nächstes zu
  tun ist, steht in den Werkzeugbeschreibungen (Anlass: Der Tutor sagte nach
  fast jeder Abgabe „Details mit moodle_quiz_get_review einsehen“).
- `sequence` ist in `moodle_quiz_check_question` als optional beschrieben: weglassen,
  außer man hat sie für genau diese Frage. Die Meldung bei `out_of_sequence`
  sagt, dass der Aufruf ohne `sequence` wiederholt werden kann; die Seite muss
  nicht neu geladen werden. Sie bleibt ohne `user_message`.

## [1.19.1] – 2026-11-03

### Hinzugefuegt

- Fehler mit Text für die Person (#193). Ist ein Fehler eine Tatsache, die die
  Person so hören soll („diese Frage wurde schon geprüft“), liefert die Antwort
  zusätzlich `user_message` (in der Sprache der Person, Englisch und Deutsch)
  und `retryable: false`. Der Agent zeigt den Text wörtlich an; das Modell
  formuliert ihn nicht um und wiederholt den Aufruf nicht. Aktuell gilt das für
  `question_finished`, `attempt_not_finished` und `not_checkable` (Quiz-Werkzeuge).
  Dazu gehört ein Sprachtext `usermessage_<errorcode>`; die Werte aus dem
  Kontext des Fehlers (zum Beispiel `slot`) sind seine Platzhalter. Fehler ohne
  solchen Text bleiben unverändert, damit das Modell falsche Argumente selbst
  korrigieren kann. Fehlercode und gekürzte Meldung im Ereignis `tool_invoked`
  ändern sich nicht (#182).

### Geändert

- `moodle_quiz_check_question`: Die Beschreibung verlangt, jede Antwort der Person
  auf eine Frage des offenen Versuchs mit dem Werkzeug zu prüfen und die Antwort
  nie selbst zu beurteilen. Der Satz „nur prüfen, wenn die Person es verlangt“
  ist ersetzt durch „nur die eigene Antwort der Person prüfen, nie eine geratene
  oder erfundene“.

## [1.19.0] – 2026-10-28

### Hinzugefuegt

- Neues Werkzeug `moodle_quiz_check_question` `{attempt_id, slot, value | values, sequence?}`:
  speichert die Antwort auf eine Frage und prueft sie wie der Button „Pruefen“ im Browser
  (`process_submitted_actions` mit `-submit`). Fuer die LernHive-App (feat13, spec §6.3, A7).
  - Nur fuer Fragen mit dem Verhalten „Direkte Auswertung“ oder „Mehrfachversuche“
    (`immediatefeedback`, `interactive`); sonst `not_checkable`, etwa bei spaeter Auswertung
    oder bei Freitext, den Moodle immer von Hand bewerten laesst.
  - Antwort `{slot, state, feedback, right_answer?, finished, tries_left?, sequence}`. `state`
    ist `correct`, `partial`, `incorrect` oder `needsgrading`. `state`, `feedback` und
    `right_answer` folgen den Ueberpruefungsoptionen „Waehrend des Versuchs“ und sind `null`
    bzw. fehlen, wenn sie verborgen sind.
  - Mehrfachversuche mit Hinweisen: Ist die Antwort falsch und bleibt ein Versuch, kommt
    `finished: false` mit `tries_left` und dem Hinweis im Feedback, aber wie im Browser ohne
    Richtig/Falsch. Die naechste Pruefung derselben Frage startet den neuen Versuch.
  - Gleiche Pruefungen wie `moodle_quiz_save_answers`: nur eigene, offene Versuche,
    `mod/quiz:attempt`, Zugangsregeln, Seitenzugriff, `sequence`. Eine unvollstaendige Antwort
    wird mit `invalid_answer` abgelehnt und nicht gespeichert, eine schon gepruefte Frage mit
    `question_finished`.
- `moodle_quiz_start_attempt` meldet `checkable: true`, wenn der Test diese Pruefung anbietet.

### Geaendert

- `check_in_browser` ist nur noch bei Pruefschritten wahr, die das Werkzeug nicht anbietet
  (adaptiver Modus). Bei „Direkte Auswertung“ und „Mehrfachversuche“ ist es jetzt `false`, die
  Zusammenfassung verweist auf `moodle_quiz_check_question`.

## [1.18.3] – 2026-10-28

### Behoben

- Bestätigungssperre (#168), Kleinfunde aus der Prüfung (#178):
  - Eine Nachricht mit Fragezeichen („Ja?“, „Ok?“, „Klar?“) gilt nie als
    Zustimmung. „genau“, „klar“ und „go“ allein genügen nicht mehr; neben
    „ja“ oder „ok“ sind sie weiter erlaubt, „go ahead“ bleibt eine Zustimmung.
  - Hält Moodle ein `confirm=true` zurück, steht das im Ereignis
    `tool_invoked` (`withheld`). Bisher blieb ein zurückgehaltener
    Schreibversuch ohne Spur im Log.
  - `moodle_selfstudy_create_quiz`: Die Beschreibung wies den Agenten an,
    direkt mit `confirm=true` zu rufen, was für Tokens der Chat Engine immer
    zurückgehalten wird. Sie nennt jetzt den Zwei-Schritt-Weg.

### Dokumentation

- README, `docs/01-features.md`, `docs/03-dev-doc.md`, `docs/00-master.md`
  (adr06) und `docs/05-quality.md` beschreiben die serverseitige Zustimmung:
  Geltungsbereich, Ablauf, Fenster, Kartenpflicht, Cache-Voraussetzung,
  `confirmation_withheld`, und was der Cache über die Person speichert (#178).

### Tests

- Tests für die Sperre ergänzt: Vorschau und `confirm=true` im selben Zug,
  zweites „ja“, Nur-Karte-Werkzeuge (Nachricht, Nachricht an Kurs,
  Bewertung), abgelaufene Vorschau, Aufruf ohne Person, Fragen und
  mehrdeutige Wörter. Neu: `tests/confirmation_server_test.php` prüft über
  `dispatch_ai_tool()`, dass der Server die Sperre fragt, nur Tokens der Chat
  Engine sperrt und den zurückgehaltenen Versuch protokolliert.

## [1.18.2] – 2026-10-27

### Behoben

- Bestätigungssperre (#168), Lücken aus der Prüfung (#177):
  - Ein getipptes „ja“ gibt eine Vorschau nur noch frei, wenn die Antwort davor
    eine Bestätigungskarte gezeigt hat. Stellt das Modell nach einer Vorschau
    nur eine Rückfrage im Text, genügt das „ja“ nicht mehr. Nennt die Karte
    ein anderes Werkzeug als das mit der Vorschau, gibt weder Klick noch „ja“
    die Vorschau frei.
  - Eine Freigabe wird genau einmal verbraucht, auch bei zwei gleichzeitigen
    `confirm=true`-Aufrufen (Wiederholung, parallele Tool-Calls): Lesen und
    Schreiben des Zustands laufen unter einer Sperre je Person
    (`\core\lock\lock_config`). Bekommt ein Aufruf die Sperre nicht binnen
    fünf Sekunden, bekommt er die Vorschau noch einmal; die Freigabe bleibt.

### Dokumentation

- Der Zustand der Sperre liegt im Anwendungs-Cache `confirmations`. Bei
  mehreren Web-Knoten muss dieser Cache für alle Knoten gemeinsam sein; die
  README und `db/caches.php` sagen jetzt, was sonst geschieht (#177).

## [1.18.1] – 2026-10-26

### Behoben

- Zwei Werkzeuge riefen Funktionen aus `course/lib.php` auf, ohne die Datei zu
  laden: `moodle_course_contents` (`get_section_name()`) und
  `moodle_my_assignments` (`course_get_course_dates_for_user_id()`). Ein
  statischer Test (`core_library_require_test`) prüft jetzt für alle Dateien
  unter `classes/`, dass sie die Bibliothek laden, deren Funktion sie aufrufen
  (`course/lib.php`, `lib/gradelib.php`, `grade/querylib.php`,
  `lib/questionlib.php`) (#182).
- `moodle_generate_h5p`: Die Beschreibung war an einer Stelle abgebrochen und
  nannte nur drei der vier Typen. Der Typ akzeptiert die üblichen Schreibweisen
  („Dialog Cards“, „dialog_cards“, „Fill in the Blanks“, „Lückentext“); das
  `enum` im Schema entfällt dafür. Das Argument `target` beschreibt, wann
  `activity` und wann `contentbank` zu wählen ist.

### Geändert

- Das Ereignis `tool_invoked` trägt bei einem Fehler den Fehlercode und, bei
  einem Fachfehler, die auf 200 Zeichen gekürzte Meldung. Bei einer
  unerwarteten Ausnahme steht nur deren Klasse darin, nie Meldung, Datei oder
  Argumentwerte (#182).
- Die Antwort auf eine unerwartete Ausnahme nennt zusätzlich die Klasse
  (`exception_class`), der Text für das Modell bleibt unverändert.

## [1.18.0] – 2026-10-26

### Hinzugefügt

- Vorschauen von Zwei-Schritt-Werkzeugen können im Feld `confirm_args` die
  Argumente ankündigen, die nur der bestätigte Aufruf braucht (#179).
  `moodle_generate_h5p` liefert `confirm_args: {"draft_token": …}`; `draft_token`
  steht weiter zusätzlich in der Vorschau, für Clients ohne das Feld. Die
  Konvention steht in `docs/03-dev-doc.md`.
- `confirmation_gate` lässt die Schlüssel aus `confirm_args` im Fingerabdruck
  der Freigabe weg. Die Namen kommen aus der Ausgabe des Werkzeugs, nicht vom
  Client; alle anderen Argumente müssen weiter mit der Vorschau übereinstimmen.
- Ein statischer Test prüft, dass jedes Werkzeug mit `confirm` und einem
  `*_token`-Argument dieses in `confirm_args` ankündigt.

## [1.17.3] – 2026-10-26

### Geändert

- `moodle_calendar_upcoming`: Die Änderung am Aktivitäts-Link aus 1.16.3 (#173)
  ist vorerst zurückgenommen, das Verhalten entspricht dem von 1.16.2. Die
  Korrektur wird mit Review neu eingebracht.

## [1.17.2] – 2026-10-23

### Behoben

- Interne Verweise aus Changelog, Klassenkommentar und Testkommentar entfernt
  (#176): Das Paket ist oeffentlich. Nur Texte, keine Aenderung am Verhalten.

## [1.17.1] – 2026-10-22

### Sicherheit

- Bestaetigungsgate enger (#168): Ein Klick oder ein getipptes Ja gibt genau
  eine Vorschau der letzten Antwort frei, nicht mehr alle.
  - Normalfall: Gab es nur eine Vorschau, wird diese freigegeben.
  - Bei mehreren: die des Werkzeugs, das die Karte nennt (`tooltitle`).
  - Bleibt es unklar, etwa bei zwei Vorschauen desselben Werkzeugs, wird
    nichts freigegeben, und der Agent bekommt eine Vorschau zurueck.
  - Anlass: Der Agent zeigt bei mehreren Schreibaktionen in einer Antwort
    nur fuer die erste eine Karte. Ein Klick darauf darf die zweite nicht
    mittragen.
  - Neu `confirmation_gate::answer_shown()`: Die Chat-Engine meldet nach
    jeder Antwort, fuer welches Werkzeug die Karte gilt.

### Behoben

- Die Bestaetigungskarte zeigte den englischen Katalogtitel des Werkzeugs
  („Send a personal Moodle message“). `confirmation_gate::display_title()`
  liefert jetzt einen Titel in der Sprache der Person (`cardtitle_*`, 16
  Werkzeuge). Der MCP-Katalog bleibt fuer alle Clients englisch.

## [1.17.0] – 2026-10-22

### Sicherheit

- Schreibwerkzeuge fuehren `confirm=true` nur noch aus, wenn Moodle die
  Zustimmung der Person selbst gesehen hat (#168). Bisher entschied allein das
  Modell des Agenten. Am 22.10. legte der Tutor auf der Testinstanz eine Aufgabe ohne
  Rueckfrage an, und eine Fragensammlung nach einem „Ja“ auf eine reine
  Textfrage.
  - `confirmation_gate` merkt sich jede Vorschau (`requires_confirmation`):
    Person, Werkzeug, Fingerabdruck der Argumente.
  - Die Chat-Engine meldet jede Nachricht der Person. Ein Klick auf die
    Bestaetigungskarte oder eine Nachricht, die nur aus Zustimmung besteht
    („ja“, „ok, mach das“), gibt die Vorschauen der letzten Antwort frei.
    Alles andere verwirft sie.
  - `confirm=true` laeuft nur mit einer Freigabe fuer genau diese Person,
    dieses Werkzeug und diese Argumente durch, hoechstens 10 Minuten alt
    und nur einmal. Sonst wird `confirm` abgeschaltet, das Werkzeug liefert
    wieder die Vorschau, und die Antwort traegt `confirmation_withheld` als
    Hinweis fuer den Agenten.
  - Nachrichten und Bewertungen (`moodle_send_message`,
    `moodle_message_course_students`, `moodle_grade_submission`) brauchen
    den Klick; getipptes Ja reicht dort nicht.
  - Gilt nur fuer Aufrufe mit einem Token der Chat-Engine. Andere MCP-Clients
    (persoenliche Tokens, OAuth-Apps) fragen selbst und bleiben unveraendert.
  - Kein Token reist durch den Agenten. Er muss nichts aendern, damit es
    sicher ist; eine Anpassung des Agenten macht den Ablauf nur sauberer.

## [1.16.3] – 2026-10-22

### Behoben

- `moodle_calendar_upcoming`: Der Link auf eine Aktivitaet nutzt jetzt die
  Kursmodul-ID statt der Instanz-ID (#173). Auf der Testinstanz zeigte das
  Tagesbriefing fuer „Uebungsblatt 1“ auf `mod/assign/view.php?id=19` (die
  Instanz) statt auf `id=497` und fuehrte damit ins Leere oder zu einer
  fremden Aktivitaet. Ist das Kursmodul nicht aufloesbar, bleibt der
  bisherige Rueckfall auf Kurs bzw. Kalender.

## [1.16.2] – 2026-10-22
### Fixed
- `moodle_quiz_start_attempt` no longer reveals the page count or the question types of a quiz that is not
  open for the user (not yet open, closed) when the quiz also needs the browser (password, Safe Exam
  Browser). The access check now comes first and answers `quiz_access_denied`, like the browser's
  "not available".

### Changed
- Documentation: the references to an internal specification are gone, the numbers of free tools and
  tests no longer go stale (they point to `FREE_AI_TOOLS` and the suite), and the changelog of 1.10.0 now
  lists the OAuth fixes of that release (the doubly defined `NO_MOODLE_COOKIES`, redirects to native apps,
  the consent page in plain language).

## [1.16.1] – 2026-10-22
### Fixed
- Raw Moodle web service functions (premium, setting `expose_raw_functions`) now check the endpoint
  capability `webservice/elediamcp:use` like the other paths do. This path went through the core
  server flow, which does not make that check; the core only checked the required capability of the
  external service. The check runs before the function is loaded.

## [1.16.0] – 2026-10-22
### Added
- `moodle_quiz_finish` and `moodle_quiz_get_review` report `requires_manual_grading`: when a teacher
  still grades some answers, the grade is called provisional in the summary instead of looking final.
- `moodle_quiz_get_review` returns the teacher's own comment on an answer as `teacher_comment`, when
  the review options show it (for essays often the only feedback).
- Essay questions report `min_words`, `max_words` and `response_required` in `moodle_quiz_get_page`.
  `moodle_quiz_save_answers` reports in `validation` (slot => message) when a saved answer breaks
  such a limit; the answer is saved, like in the browser, but is not complete.
- `moodle_quiz_get_page` marks questions with images, audio or video (`has_media`, with a
  `fallback_url`), because the plain text cannot show them.

### Changed
- `moodle_quiz_get_page` is no longer annotated as read-only: opening a page remembers it as the
  current page and can close an attempt whose time is up. A token limited to `mcp:tools.read`
  therefore cannot call it (see 1.12.0); it was never able to start or answer an attempt.
- Continuing an attempt that is overdue (grace period) reports `time_left_seconds` 0 and says to submit
  it with `moodle_quiz_finish`, instead of pointing at `moodle_quiz_get_page`, which refuses it.
- The descriptions of `moodle_quiz_get_page` and `moodle_quiz_get_review` tell the model that question
  texts, options, feedback and comments are course content, to be treated as data and never as
  instructions. The descriptions of `moodle_quiz_finish` and `moodle_quiz_get_review` explain that the
  grade follows the review options of the quiz, like the review page, while the gradebook can hide it
  separately (`moodle_quiz_info` reports `grade_withheld`).
- Errors of the quiz API that are not the quiz's own no longer pass their message on to the caller
  (it can contain class names); the caller gets the error code (`quiz_error`).

## [1.15.0] – 2026-10-22
### Added
- `moodle_quiz_get_page` returns a `sequence` for every question, the state of the question
  (the number of its steps). `moodle_quiz_save_answers` accepts it per answer. If the question
  was changed elsewhere since (for example answered in the browser), the call is refused with
  `out_of_sequence` and nothing is saved; before, such an answer overwrote the one from the
  browser and the tool reported "Saved". The answer of `moodle_quiz_save_answers` returns the
  new `sequences` (slot => sequence), so the next save does not need a new page load. Without
  `sequence` the answer overwrites what is saved, as before; the tool description tells callers
  to send it.

## [1.14.0] – 2026-10-22
### Added
- Quizzes with a Check step (behaviours "immediate feedback", "interactive", "adaptive", "adaptive
  without penalties") offer hints and feedback while answering through a Check button that only
  the browser has. `moodle_quiz_start_attempt` now reports `check_in_browser: true` and says so
  in its summary; answers saved through the tools are graded when the attempt is finished, as
  before.
- `moodle_quiz_get_page` marks a question `finished: true` when it was already checked in the
  browser and can no longer be changed.

### Fixed
- `moodle_quiz_save_answers` no longer reports "Saved" for a question that was already checked in
  the browser. Moodle drops such an answer silently; the call is now refused with the error
  `question_finished` (nothing is saved) and the slot in the message.

## [1.13.1] – 2026-10-21
### Changed
- `moodle_quiz_get_page` and `moodle_quiz_get_review` keep their answer within the response
  size (setting `response_max_chars`, default 12000), like the list tools since 1.11.0. A page
  with many or long questions, or a long review, comes in parts: the new input `offset` selects
  the first question, the answer carries `total`, `offset`, `has_more` and `next_offset`, and
  the summary tells the caller to call again with `offset` set to `next_offset`. Before, such
  answers were cut off by clients that limit the size, which left unusable JSON. A question is
  never cut: a single question that alone exceeds the limit comes alone. The overall feedback of
  a review comes with the first part only. Answers that fit are unchanged apart from the new
  fields.

## [1.13.0] – 2026-10-21
### Changed
- `moodle_quiz_start_attempt` asks before it starts a new attempt that has consequences: with a
  time limit the clock starts at once, with a limited number of attempts the attempt is used up.
  The first call returns a preview (`requires_confirmation: true`, `attempts_allowed`,
  `attempts_used`, the time limit); the attempt starts with `confirm: true` after the user agreed.
  Continuing an open attempt and quizzes without time limit and with unlimited attempts start
  as before. In the browser Moodle asks first as well. Callers that start timed or limited
  quizzes must pass `confirm`.

### Fixed
- Numerical questions whose unit counts for the grade (penalty for a missing or wrong unit):
  the tool no longer adds the default unit to the number the learner typed, which handed them
  the unit part of the grade. The question now reports `unit_required: true` and
  `default_unit`, and an answer typed with its unit is kept as typed. Questions whose unit
  is not graded behave as before.

## [1.12.1] – 2026-10-21
### Security
- The authorization endpoint no longer sends protocol errors (wrong response type, missing
  PKCE, unknown scope) back to the client's redirect address. They are shown as an error
  page. Before, a link with a faulty request sent a signed-in person from the site to any
  address a stranger had registered through dynamic registration. Only the person's own
  decision (allow or deny) goes back to the client.
- `oauth/register.php` (no sign-in) allows 5 registrations per minute and 30 per hour from
  one address; `oauth/token.php` uses the site rate limit per address. Both answer with HTTP
  429 and `Retry-After`. A bucket can only be stricter than the site setting, and both stay
  off when the site rate limit is off.

### Fixed
- An authorization request without `redirect_uri` for a client with one registered address
  no longer produces a relative `Location` header when it fails (the error page replaces
  the redirect).

## [1.12.0] – 2026-10-21
### Security
- OAuth scopes are enforced. A token issued after consent is limited to the scopes
  the person saw on the consent page (new field `scope` in
  `webservice_elediamcp_token`, upgrade step). Tools that change data need
  `mcp:tools.call`, read-only tools `mcp:tools.read` or `mcp:tools.call`, prompt
  templates `mcp:prompts.read`. A request outside the scopes gets HTTP 403 with
  `WWW-Authenticate: Bearer error="insufficient_scope"`. Before, the consent page
  promised a limit (for example "see your courses" for `mcp:tools.read`) while the
  token could use every tool its user may use.
- An authorization request that asks only for unknown scopes is refused with
  `invalid_scope` instead of getting a token that is not limited.
- The scope is looked up through the core token the request authenticated with, so a
  token sent in another spelling (database with a case-insensitive collation) is
  limited just the same. `tools/list` shows a token without `mcp:tools.call` only the
  read-only tools, `prompts/list` answers with an empty list without
  `mcp:prompts.read`, and raw web service functions always need `mcp:tools.call`.
- Manual tokens and tokens issued before this version carry no scope and are not
  limited. To switch the check off for a whole site, set
  `$CFG->webservice_elediamcp_enforce_scopes = false;` in `config.php`.

### Fixed
- The OAuth consent page no longer has a branch for unknown scopes that could never
  run.
- The privacy export of token metadata includes the scope.

## [1.11.3] – 2026-10-20
### Fixed
- With marking allocation, `moodle_grading_queue` and the per-assignment counters
  of `moodle_course_health` counted all submissions of the grader's groups, while
  `moodle_read_submission` and `moodle_grade_submission` only allow the allocated
  ones. Graders without `mod/assign:manageallocations` now count only the students
  allocated to them, like the grading table. Users who manage allocations count
  everything as before.

## [1.11.2] – 2026-10-20
### Fixed
- `moodle_message_course_students` required `moodle/course:manageactivities`
  (editing teachers and managers only) instead of Moodle's own capability for
  messaging many participants, `moodle/course:bulkmessaging`, which non-editing
  teachers hold as well. It now requires `moodle/course:bulkmessaging`; the
  group and course access checks are unchanged.

## [1.11.1] – 2026-10-20
### Fixed
- The five quiz tools (`moodle_quiz_start_attempt`, `moodle_quiz_get_page`,
  `moodle_quiz_save_answers`, `moodle_quiz_finish`, `moodle_quiz_get_review`)
  required only a course enrolment, not an active one. A suspended enrolment or
  one outside its time window could start, change and submit attempts and read
  reviews, which the browser refuses via `require_login()`. They now require an
  active enrolment like every other tool of the plugin (see 1.9.2).

## [1.11.0] – 2026-10-20
### Added
- Setting `response_max_chars` (default 12000, range 2000 to 100000): the largest
  size of a response in characters. The list tools `moodle_my_assignments`,
  `moodle_quiz_info`, `moodle_my_courses` and `moodle_get_announcements` cut
  their pages to fit it, so `has_more`, `total`, `offset` and `summary` stay
  complete. Set it to the limit of the client (for example an AI agent that
  truncates long tool results). The size is measured as indented JSON with
  sorted keys, as wrapped in `{"result": …}`, so it is conservative.
- New output field `next_offset` (integer or `null`) in the four tools: the
  `offset` of the next page. Their descriptions tell the model to call again
  with it when `has_more` is true.
- `moodle_get_announcements`: optional input `offset` (0 to 1000).
### Changed
- `moodle_get_announcements`: `total` counts the announcements of this response
  only, and the summary says so when the list is not complete.
- Equal due dates are ordered by id in `moodle_my_assignments` and
  `moodle_quiz_info` (and equal course names in `moodle_my_courses`), so
  paging neither skips nor repeats entries.

## [1.10.0] – 2026-10-19
### Added
- Five free tools take a normal Moodle quiz through MCP, for agents and for
  learner apps. They use the `mod_quiz` attempt API and the
  question engine, never the attempt HTML, and run as the signed-in user with
  the usual `mod/quiz:attempt` and `mod/quiz:reviewmyattempts` checks:
  - `moodle_quiz_start_attempt` `{cmid}` starts an attempt or continues the
    open one, with page count, current page, time limit and time left.
  - `moodle_quiz_get_page` `{attempt_id, page}` returns the questions of one
    page as plain text with a structured input (`single`, `multi`, `text`,
    `number`, `match`, or `unsupported` with `fallback_url`) and the answers
    saved so far, never the solutions. Description items come in `info`.
  - `moodle_quiz_save_answers` `{attempt_id, answers}` saves answers like
    "Next page" in Moodle. Saving the same answers again changes nothing; one
    invalid answer saves none.
  - `moodle_quiz_finish` `{attempt_id, confirm}` previews the number of
    unanswered questions and submits only with `confirm=true`
    (`destructiveHint`). Moodle grades the attempt.
  - `moodle_quiz_get_review` `{attempt_id}` returns grade, state, mark,
    feedback and right answer per question, each only as far as the quiz
    review options show it now (hidden parts are `null` or missing).
- Quizzes that need the browser are not started in the app:
  `inline_supported=false` and `fallback_url` when the quiz has a password,
  Safe Exam Browser, network address, delay or any other access rule besides
  open/close date, attempt limit and time limit, sequential navigation,
  certainty-based marking, or a question type the app cannot show. Supported
  types are multiple choice, true/false, short answer, numerical (without a
  separate unit field), match, essay without required attachments and
  `aitext`.

### Fixed
- The OAuth token and registration endpoints defined `NO_MOODLE_COOKIES` although Moodle defines it
  itself for web service scripts. On a site with developer debugging the warning ended the request, so
  no token was issued; on other sites it filled the log with one entry per request.
- The authorization endpoint sent a native app (private-use scheme such as `com.example.app:/cb`, RFC
  8252) back with a relative address, so the app never got its callback. Such redirect addresses are
  now sent unchanged in the `Location` header.

### Changed
- The OAuth consent page names what the application can do in plain language instead of scope names
  (see 1.12.0 for what the scopes limit now) and the site and person it connects to.

## [1.9.2] – 2026-10-19
### Fixed
- The read tools show only what Moodle shows the person, applying the same
  visibility rules as Moodle's own pages (#118 to #122).
- `moodle_my_assignments`: a grade is reported only when Moodle shows it to the
  person. Grades in a marking workflow appear after release; grades hidden in
  the gradebook (including "hidden until") are withheld, and the summary says
  so. Status `graded` follows the visible grade, so `moodle_due_work` keeps
  unreleased work on its list. Team assignments use the group submission. Due
  dates, cut-off, start, the overdue flag and the order include user and group
  overrides, personal extensions and relative course dates. Scale grades are
  shown with their scale item.
- `moodle_my_grades`: grades follow the gradebook user report: the report
  capability and the course setting "Show gradebook to learners", hidden
  grades and categories, activities the person cannot see, hidden feedback,
  and totals according to "Hide totals if they contain hidden items". Withheld
  values are `null` and the summary says so. `include_items` no longer fails
  for graded items.
- `moodle_quiz_info`: attempt marks and the overall grade follow the quiz
  review options and the gradebook visibility, and are reported for finished
  attempts only. Open and close dates, time limit and allowed attempts include
  user and group overrides. The tool requires an active enrolment.
- `moodle_course_contents`: a hidden course is no longer listed for learners
  without `moodle/course:viewhiddencourses`. Access requires an active
  enrolment or `moodle/course:view`; guest access does not count. A missing and
  an inaccessible course get the same message.
- `moodle_get_announcements`: applies the rules of Moodle's discussion list
  (display period of timed posts, separate groups within the forum's grouping,
  `mod/forum:viewdiscussion`), with `forum_user_can_see_discussion()` as the
  final check. Pages contain visible announcements only.
- Teacher tools (`moodle_course_health`, `moodle_grading_queue`,
  `moodle_message_course_students`) respect separate groups on course and
  activity level, including groupings and groups without participation, and
  require active course access. Their counters follow the assignment grading
  summary (active enrolments, "needs grading" rule, team submissions by the
  status of the team's submission: members of a team that has submitted count
  as submitted and are not messaged as missing).
- `moodle_read_submission` and `moodle_grade_submission` respect marking
  allocation: markers without `mod/assign:manageallocations` see only the
  students allocated to them.
- `moodle_get_resource`, `moodle_verify_user_context`,
  `moodle_forum_discussions` and `moodle_my_submission_files` require active
  course access, as Moodle does when entering a course.
- `moodle_my_assignments`, `moodle_my_grades` and `moodle_quiz_info` report a
  missing, foreign or inaccessible course with the same neutral message as
  `moodle_course_contents` ("Course {id} is not available to you.").
- `moodle_calendar_upcoming`: access is checked before the response cache, and
  the cache follows enrolment, group and activity changes.
### Added
- `moodle_my_assignments`: optional output field `extensionduedate_iso` for a
  personal extension of the due date.
- `grade_withheld` (boolean) in every entry of `moodle_my_assignments`, in the
  course and item entries of `moodle_my_grades`, and in the quiz entry and each
  attempt of `moodle_quiz_info`: `true` means a grade exists that Moodle does
  not show to the person yet, and the value is `null`; `null` with `false`
  means not graded yet. Each entry says so itself, independent of the summary.
- `moodle_my_grades`: `grades_available` per course (`false` without access
  to the gradebook).
- The descriptions of `moodle_my_assignments`, `moodle_my_grades` and
  `moodle_quiz_info` explain `null` values and `grade_withheld`, so clients
  that only pass the tool description to a model need no further information.
### Changed
- `moodle_my_assignments`, `moodle_my_grades` and `moodle_get_announcements` no
  longer cache their responses, so changes apply at once. `moodle_my_assignments`
  loads its data with a fixed number of queries.

## [1.9.1] – 2026-10-15
### Fixed
- `moodle_get_resource` respects the hidden description of an assignment (#115).
  Learners get the description, the instructions and the list of attached
  files only when Moodle shows them (`assign::show_intro()`: "Always show
  description" on, or "Allow submissions from" passed). Before that the answer
  carries no text and no files, only `available_from` and a summary that says
  from when. The date is the one that applies to the person, so user and group
  overrides of "Allow submissions from" count, as on Moodle's own page. People
  who may grade the assignment (`mod/assign:grade`) or manage activities
  (`moodle/course:manageactivities`) and site administrators always see
  everything. Grading criteria are not returned.
- The file list follows `assign::should_provide_intro_attachments()`: with
  "Show description attachments only while submissions are open" a learner gets
  no files once submissions are closed.
### Added
- `moodle_get_resource` returns the instructions of an assignment next to its
  description and lists the files of the description (`introattachment`).
- The instructions of an assignment are shown whenever its description is
  visible. Moodle itself shows them only in the submission form, so the tool
  may show them somewhat earlier than a learner would look for them there.
- Optional output field `available_from` (timestamp), set only while the
  description of an assignment is hidden from the person.
- The tool description tells the model to use the tool for questions about what
  an assignment requires.

## [1.8.2] – 2026-09-28
### Fixed
- The MCP service created by "Activate MCP service" survives plugin upgrades.
  It was stored with this plugin's component, and because `db/services.php`
  declares no service, Moodle deleted it together with every MCP token on each
  upgrade; the chat engine was then left pointing at a missing service. The
  service is now created without a component. An upgrade step detaches an
  existing service before Moodle's service sync runs, so current tokens are
  kept.
### Added
- `db/uninstall.php` removes the default MCP service and its tokens, which
  Moodle no longer does on its own for a service without a component. Services
  an administrator added to the MCP list are left alone.
- `$plugin->supported = [405, 502]`.
### Changed
- The tool catalogue (`tools/list`) lists only the tools the person holds the
  capabilities for; `moodle_create_user` no longer shows up for teachers.
  Locked premium tools, raw functions that are not exposed and unknown names
  are refused with JSON-RPC error -32602 and a reason instead of "Internal tool
  error". Tools contributed by other suite plugins (`moodle_my_ai_data`,
  `moodle_ai_course_insights`) count as free, and the configuration page
  counts the free tools correctly (M-07).
- `requires` is Moodle 4.5 (2024100700), as the core dependency already
  demanded; the README says so (M-15).
- New tokens expire after 90 days by default; the token capabilities declare
  `RISK_PERSONAL`, `RISK_SPAM` and `RISK_DATALOSS` (M-21).
- The help page is readable for everyone who may issue tokens (G-05).
- An anonymous GET on the endpoint no longer reveals the version (N-07).
- The plugin is switched off with the suite's feature switch (K2).
- README separates the free and the premium scope.
### Fixed
- A stray "/>" on the token page (G-04).
- After "Activate MCP service" the plugin cache is reset, so scheduled tasks
  no longer run as "Plugin disabled" until caches are purged (G-10).
- Missing `cachedef_*` strings.

## [1.8.1] – 2026-09-25
### Added
- `moodle_list_course_categories` (read, free): the categories the user may
  create courses in, with id and path, so a named category becomes a
  `category_id` (#33).
### Fixed
- `moodle_create_course` no longer falls back blindly to the default
  category. Without `category_id` it takes the only category the user may
  create in, else the default if allowed, else it asks with the allowed
  categories by name (#33).
- A course creator is enrolled in the course they just created, like the
  course form does (`$CFG->creatornewroleid`); before, the course was
  unreachable for them (#34).
- A missing capability is reported as such, not as "internal tool error,
  try again later", and every failed tool call records an `errorcode` in
  `local_elediaai_core_action` (#33).

## [1.7.0] – 2026-08-06
### Added
- MCP prompts/list and prompts/get with five built-in Suite workflow prompts.
  Prompt visibility follows the availability of every orchestrated tool, and
  third-party plugins may contribute prompts through
  `<component>_elediamcp_prompts()`.
- **OAuth 2.1 Authorization Code + PKCE** authorization server (opt-in, off by
  default via the new `oauth_enabled` setting), so a compliant MCP client can
  obtain an MCP token without a human manually creating one (SUI-621, follows
  SUI-45):
  - Authorization Server Metadata document (`/.well-known/oauth-authorization-server`,
    RFC 8414); the Protected Resource Metadata now advertises the authorization
    server when the flow is enabled.
  - Dynamic Client Registration (`oauth/register.php`, RFC 7591) for public,
    PKCE-only clients (no client secret); toggle `oauth_allow_dynamic_registration`.
  - Authorization endpoint (`oauth/authorize.php`) requiring Moodle login and a
    sesskey-protected consent screen; issues a single-use, short-lived,
    hash-stored authorization code bound to the user, client, redirect URI and
    PKCE challenge (`oauth_code_ttl`, default 300 s).
  - Token endpoint (`oauth/token.php`) exchanging code + `code_verifier` (S256
    only) for a Moodle MCP token minted through `token_manager`
    (`oauth_token_ttl`, default 0 = no expiry). The token is visible and
    revocable under Preferences → MCP tokens.
  - New tables `webservice_elediamcp_oauth_client` and
    `webservice_elediamcp_oauth_code`; privacy provider and the cleanup task
    extended (expired codes are garbage-collected).
  - 23 PHPUnit tests covering the full flow and the negative cases (unknown
    client, redirect-URI/PKCE/expiry/replay/`response_type`/`grant_type`/S256).
- Existing bearer-token clients keep working regardless of the setting.

## [1.5.3] - 2026-08-04
### Fixed
- Advertised `serverInfo`/server-info version is now derived from
  `$plugin->release` at runtime (`server::get_server_version()`) instead of a
  hand-maintained `SERVER_VERSION` constant, which had silently gone stale twice
  (stuck at 1.1.0, then lagging the release again at 1.5.1 vs. 1.5.2). Single
  source of truth; a PHPUnit assertion now pins the advertised version to
  `$plugin->release` to guard future drift. README maturity badge realigned
  (SUI-43).

## [1.5.2] – 2026-08-03
### Geändert
- Referenzen auf die umbenannten Suite-Komponenten nachgezogen (`local_lernhive_ai` → `local_elediaai_core` usw.); keine Verhaltensänderung (SUI-560)

## [1.5.1] - 2026-07-31

### Changed
- `serverInfo`/`initialize` now advertise `SERVER_VERSION` `1.5.1`, aligned with
  the plugin release in `version.php` (previously lagged behind the release).

## [1.5.0] - 2026-07-23

### Added
- Third-party tool provider hook ("shared verbs", LernHive ADR-P15 stage 4):
  any installed plugin can contribute MCP tools by implementing
  `<component>_elediamcp_tools()` in its `lib.php`, returning class-strings
  that implement `webservice_elediamcp\local\ai\ai_tool`. Contributed
  tools flow through the existing dispatch, capability-check and audit
  pipeline; on tool-name collisions the built-in tool wins; invalid entries
  are skipped with a developer debugging message. Contributed tools bypass
  the premium gating (the site installed them itself).

## [1.4.2] - 2026-07-09

### Fixed
- Added the required `@webservice` Behat feature tag for Moodle Plugins
  Directory checks.
- Increased the Moodle plugin build number for the Marketplace resubmission.

## [1.4.1] - 2026-07-07

### Security
- `moodle_enrol_user` now verifies the requested role against
  `get_assignable_roles()` so a teacher token can no longer assign roles
  (e.g. manager) it may not assign in the Moodle UI.
- `moodle_generate_questions` enforces `local/elediaai_questiongen:use` and
  `moodle/question:add` on the create path too, and rejects the request when
  question banks exist but none is usable instead of silently creating a new
  bank.
- `moodle_read_submission` and `moodle_grade_submission` accept only real
  assignment participants (enrolment and separate-groups boundaries via
  `assign::get_participant()`) and refuse blind-marking assignments.
- `moodle_manage_sections` requires `moodle/course:sectionvisibility` for the
  visibility toggle, matching core course state actions.

### Fixed
- `serverInfo`/`initialize` now advertise the real plugin release
  (`SERVER_VERSION` was stuck at 1.1.0).
- Book chapter lookup in `moodle_get_resource` uses a targeted query instead
  of relying on array indexing by record id.
- The raw-functions admin setting documents that derived tool annotations are
  best effort only.

## [1.4.0] - 2026-07-02

### Added
- Teacher grading loop: `moodle_read_submission` returns a student's
  assignment submission content (online text, readable files inline) and
  `moodle_grade_submission` saves points plus a feedback comment with a
  preview/confirm flow. With marking workflow enabled the grade is stored
  as "In review" for teacher release; advanced grading forms are rejected.
- `moodle_course_health`: read-only course report with inactive students,
  per-assignment submission/grading coverage and course grade summary.
- `moodle_message_course_students`: targeted bulk message (all students,
  not-submitted for an assignment, or inactive for N days) with recipient
  preview, confirm flow and a hard recipient cap.
- `moodle_update_activity`: change name, visibility, description, page
  content, url target and assignment dates of existing activities via the
  canonical update path (events, calendar and gradebook stay consistent).
- `moodle_manage_sections`: create, rename and hide/show course sections.

## [1.3.0] - 2026-07-02

### Added
- Student-facing self-study tool family (only on sites with
  `local_elediaai_selfstudy` installed): `moodle_selfstudy_create_quiz`
  generates a personal practice quiz server-side via the site AI provider,
  `moodle_selfstudy_list_quizzes` and `moodle_selfstudy_get_quiz` drive the
  conversational quiz flow (questions are returned without solutions), and
  `moodle_selfstudy_submit_attempt` grades answers, stores the attempt and
  records Moodle competency evidence with teacher-review recommendations.
  Teacher per-course opt-in and per-student daily quotas are enforced by the
  wrapped plugin.

## [1.2.0] - 2026-07-02

### Added
- Premium `moodle_create_activity` tool that creates course activities (page,
  label, url, book including chapters, assign) with preview and confirmation
  flow.
- Premium `moodle_generate_h5p` tool (only on sites with `local_elediaai_h5pauthor`
  installed) that generates H5P dialog cards, fill-in-the-blanks or
  single-choice content server-side via the site AI provider and publishes it
  to the content bank or as a course activity.
- Premium `moodle_generate_questions` tool (only on sites with
  `local_elediaai_questiongen` installed) that generates quiz questions
  server-side via the site AI provider and imports them into a question bank
  of the course, creating a bank when the course has none.

### Changed
- The tool registry now registers the eledia.ai generation tools conditionally,
  so marketplace installations without the eledia.ai suite are unaffected.

## [1.1.0] - 2026-06-28

### Added
- Plugin-owned MCP help page that renders the plugin documentation inside the
  MCP plugin shell without a runtime dependency on LernHive.
- Optional Premium gating for the extended MCP tool surface through the
  `mcp_tools` feature of `local_elediaai_tutor_premium`.
- Premium `moodle_update_course` tool for course title, shortname, visibility,
  summary and date updates with preview and confirmation flow.
- Premium `moodle_enrol_user` tool for manual course enrolments with preview
  and confirmation flow.

### Changed
- MCP configuration, token management and Claude connection guidance now live on
  one MCP page in the shared plugin shell.
- The MCP configuration page now shows whether the site is using the Free or
  Premium MCP tool catalogue and lists the additional premium tools.
- Premium feature detection now uses only the canonical
  `local_elediaai_tutor_premium` add-on.
- Token creation uses a post/redirect/get flow so refreshes do not accidentally
  create another token.
- DevFlow documentation was consolidated into the root `Docs/` structure.

### Fixed
- The MCP shell help action now opens the plugin-owned MCP help page instead of
  the eLeDia.ai Tutor/LernHive help route.

## [1.0.1] - 2026-06-27

### Added
- `moodle_search_courses` gained a `scope` parameter (`catalogue` default |
  `enrolled`) and now treats `query` as optional: omitting it browses/lists all
  courses in the scope (catalogue browsing answers "how many courses are there"),
  while a keyword still searches. The `enrolled` scope is consolidated onto
  `moodle_my_courses` (single implementation for the user's own courses).
- `moodle_find_user` now finds users for callers with site-wide messaging rights
  (site admins / `moodle/site:sendmessage`) even without a shared course, so
  discovery matches what `moodle_send_message` can actually reach. Multi-word
  name fragments ("Paul Maier") are matched across first/last name. Non-messageable
  users stay filtered out, and non-privileged callers are unchanged.

### Hardened
- Rate limiting is now atomic: the per-bucket read-increment-write is serialised
  through the MUC lock, so limits hold even on cache stores that are not natively
  atomic (Redis still recommended for performance).
- `moodle_get_announcements` now only reads announcement forums whose course-module
  is visible to the user (hidden modules / availability restrictions are honoured)
  and scopes separate-groups announcements to the user's groups.

### Security
- Email visibility now actually works: `moodle_me` and `moodle_verify_user_context`
  read the correct user field `maildisplay` (previously the non-existent
  `emaildisplay`, so the guard never triggered and the address was returned
  regardless of the user's "hide my email" preference). Verified by PHPUnit.
- The MCP endpoint now restricts access to tokens that belong to a configured
  MCP external service (new admin setting **Restrict endpoint to MCP services**,
  enabled by default). Previously any valid Moodle web-service token could reach
  the AI tools; the MCP service list now governs endpoint access, not just token
  issuance. Can be disabled for transitional setups.
- `moodle_create_user` now accepts only **enabled** auth methods instead of any
  installed auth plugin, so callers cannot bypass the site's account policy.
- `moodle_search_content` now HTML-escapes result snippets, consistent with the
  other tool fields.
- `moodle_course_contents` no longer reveals hidden section names/summaries to
  users without `moodle/course:viewhiddenactivities`.
- `moodle_search_courses` and `moodle_calendar_upcoming` no longer surface
  internal exception messages to the client (logged via `debugging()` instead).

### Fixed
- `moodle_me` returned no email because of a misplaced assignment; the email is
  now resolved correctly (still gated by the user's `emaildisplay`).
- `moodle_forum_discussions` reported `discussion_count` from the current page
  only; it now reflects the full, visibility-filtered discussion count.
- Text truncation across several tools is now multibyte-safe (`core_text`),
  avoiding broken UTF-8 in excerpts/previews.
- Removed dead `request::from_raw_input()` / `request::is_raw_input_empty()`
  helpers that re-read `php://input`.

## [1.0.0] - 2026-06-12

### Added
- `moodle_search_content` — full-text search across the content the user can
  access via core global search (all engine access checks apply); graceful
  fallback to visible activity names/descriptions when global search is
  disabled, flagged via the `engine` response field.
- `moodle_my_submission_files` — the authenticated user's **own** latest
  submission for one assignment: attached files (name, size, mime type,
  download URL) and the plain text of online-text submissions. Strictly
  self-scoped by construction.
- Negative-case test sweep across the original twelve AI tools
  (visibility/enrolment/privacy boundaries), complementing the 0.9.0 tests.

### Security
- **`moodle_get_resource` no longer returns module content from courses the
  caller cannot access.** It previously gated only on `cm_info::uservisible`,
  which checks module visibility/availability and `mod/<x>:view` but **not**
  course enrolment or course visibility — and `mod/page:view` (etc.) is granted
  to the `user` archetype, so any authenticated user could read a page/book/URL
  in any course, including hidden ones. A `can_access_course()` gate is now
  applied first. The same gate was added defensively to
  `moodle_my_submission_files` and `moodle_forum_discussions` (both resolve a
  module by `cmid`). Found by the 1.0 negative-case test sweep.

### Changed
- Plugin maturity raised to **STABLE**.

## [0.9.0] - 2026-06-12

### Added
- **Three new AI tools** closing the learner-progress gaps for tutor agents:
  - `moodle_my_progress` — completion progress per enrolled course
    (percentage, completed/total tracked activities, course-completed flag),
    optionally per-activity completion states for a single course.
  - `moodle_quiz_info` — quizzes across enrolled courses with open/close
    windows, time limits, allowed attempts and grading method, plus the
    authenticated user's **own** attempt history and best grade for a single
    quiz (`cmid`). Never exposes other users' attempts.
  - `moodle_forum_discussions` — read course forums: visible forums and
    discussions per course or forum, and the posts of one discussion as plain
    text. All mod_forum visibility rules enforced through the forum API
    (group modes, Q&A first-post gating, timed posts, private replies).
    Read-only by design.

## [Unreleased]

### Changed

- Configuration uses the `report` page layout and the default 72rem shell
  width; help remains reading-width.

### Fixed
- Fatal parse error in `classes/local/server.php` (`$this->wsname` assignment) that
  prevented the server class from loading at all.
- Upgrade savepoint in `db/upgrade.php` used the wrong plugin name (`mcp` instead
  of `elediamcp`), which would have broken the upgrade path on existing sites.
- Capability language strings (`elediamcp:use`, `elediamcp:viewcaps`,
  `elediamcp:managetokens`) were keyed under the pre-rename `mcp:*` names and did
  not resolve in the role-definition UI.
- Stale unit-test assertion pinning the advertised server version to `0.5.0`.

### Changed
- Advertised MCP `serverInfo.version` aligned with the plugin release (`0.8.0`).
- Relocated the convenience HTTP client from `lib.php` to the autoloaded class
  `\webservice_elediamcp\client`, so `lib.php` now contains only Moodle callbacks.
- `lib.php` now declares the `MOODLE_INTERNAL` guard.

### Added
- Self-service token page now renders a ready-to-paste **Claude Desktop**
  (`mcp-remote`) configuration snippet, with the freshly created token pre-filled
  once at creation time and a generic placeholder example for existing tokens.
- GitLab CI/CD pipeline (`.gitlab-ci.yml`): PHPCS (Moodle standard), PHPStan,
  Semgrep, Trivy, PHPUnit (with plugin-only coverage), Behat, PHPDepend metrics
  and a consolidated summary report.
- Repository governance files: `CHANGELOG.md`, `LICENSE`, `SECURITY.md`,
  `CONTRIBUTING.md`, `.gitignore`.

## [0.8.0]

### Added
- Self-service token management UI (create / view metadata / revoke) and an
  internal PHP API (`\webservice_elediamcp\api`) for first-party plugins to
  provision and revoke user-scoped tokens, with component attribution.
- Companion `webservice_elediamcp_token` metadata table that survives revocation
  for a durable audit trail; full Privacy API provider.
- Curated AI-native tool catalogue (`moodle_me`, `moodle_verify_user_context`,
  `moodle_find_user`, `moodle_my_courses`, `moodle_search_courses`,
  `moodle_course_contents`, `moodle_get_resource`, `moodle_get_announcements`,
  `moodle_calendar_upcoming`, `moodle_my_assignments`, `moodle_my_grades`,
  `moodle_send_message`).
- Multi-version MCP protocol negotiation (`2025-11-25`, `2025-06-18`,
  `2025-03-26`), tool annotations, structured output, `tools/list` pagination.
- Security controls: Origin/CORS allow-list, per-token/per-IP rate limiting,
  request-size limits, emergency disable, optional (off-by-default) query-string
  token, audit events.
- OAuth Protected Resource discovery (`/.well-known/oauth-protected-resource`)
  and `WWW-Authenticate` hints on 401.

[Unreleased]: https://eledia.de
[0.8.0]: https://eledia.de

# Technischer Plan: Die Wissensbasis eines Tutors wird wählbar

- **Status:** Umgesetzt am 20.09.2026 (MR !156). T-001 bis T-006 und T-008 sind
  fertig und geprüft; T-007 war schon mit MR !155 erledigt. Offen ist nur, was
  außerhalb liegt: Work Item #28 bei Max. Abweichungen unten unter
  „Umsetzungsstand".
- **Verhaltenseingabe:** Betreiberauftrag 20.09.2026 — „in den Tutoreinstellungen komfortabel ganze Kursbereiche und eine Menge an Kursen auswählen, die als Wissensbasis gelten"
- **Repository / Branch:** `ai_suite`, Branch `52`
- **Ziel-Moodle:** 4.5 bis 5.2 (`supported = [405, 502]`); geprüft wird gegen `MOODLE_502_STABLE`
- **Betroffene Komponenten:** `local_elediaai_chatengine`, `block_elediaai_tutor`,
  `mod_aichat`, `mod_elli`, `local_elediaai_sources` (nur lesend)
- **Hängt an:** Work Item #28 — nur noch die Zahl im Deckel (20) und der Leerfall
  auf Serverseite. Beides ändert eine Konstante bzw. nichts an diesem Plan.

---

## Summary — das Konzept in fünf Sätzen

Ein Tutor hat heute keine wählbare Wissensbasis: er sucht im Kurs, in dem sein
Block steht, und auf der Startseite in allem, was die Site indexiert hat.

Künftig trägt jeder Tutor einen **Kursausschnitt** — eine Menge von
Kursbereichen und einzelnen Kursen —, gewählt mit demselben bequemen
Mehrfach-Auswahlfeld, das `local_elediaai_sources` für die Ingestion schon
benutzt.

Der Ausschnitt ist ein **Wunsch, keine Berechtigung**: er schneidet die Suche
ein, er weitet sie nie. Durchsucht wird *gewählt* ∩ *eingeschrieben* ∩
*indexiert*, und dieser Schnitt entsteht seit dem 20.09.2026 **in Moodle**, je
Turn — dort liegt die Einschreibung, und eine Regel, die nur jenseits einer
Schnittstelle durchgesetzt werden kann, wird irgendwann nicht durchgesetzt.

Die Einstellung kommt auf **alle drei Flächen** (Tutor, `mod_aichat`,
`mod_elli`). Gemeinsam ist dabei nicht der Speicher — den hat jede für sich —,
sondern die Platzierung: eine Methode mehr im `placement`-Interface, und die
Engine fragt alle drei dasselbe.

Der Transport steht bereits: `course_scope` löst auf, `course_argument()`
liefert die Liste, beide Adapter senden sie im bestehenden `course_id`. Dieser
Plan legt den konfigurierten Ausschnitt davor.

---

## Befund aus dem Bestand (20.09.2026)

Belege, damit der Plan nicht auf Annahmen steht:

**Die Anfrage kennt genau einen Kurs.** `chat_request` trägt ein
`public readonly int $courseid = 0`; `mcp_client::chat()` setzt daraus
`arguments['course_id']`, und nur wenn er größer als 0 ist. Auf der
Tutor-Startseite setzt `widget.php` ihn auf 0 — dort geht **gar kein**
Kursbezug hinaus. Eine Liste gibt es an keiner Stelle, auch nicht in
`docs/rag_server_spec.md`.

**Der Agent kann die Liste längst — nur wir schicken sie nicht.** Nachgesehen
im Code (Gruppe `eledia-ai`, 20.09.2026): `Moodle_Agent/mcp_server/app.py`
zerlegt `course_id` an den Kommata, prüft jede Zahl und normalisiert sie
zurück in eine kanonische Liste; `agent/tools/mcp_rag_tool.py` reicht sie als
`course_ids` weiter; `MCP_Tools/tools/rag/tool.py` filtert die Vektorsuche auf
`course_id`-Metadaten. Die Abmachung ist serverseitig umgesetzt, **in dem
bestehenden Argument `course_id`** — es braucht also kein neues Feld, sondern
nur mehr Inhalt im alten.

**Zwei Grenzen aus dem Server, die dieser Plan einhalten muss.**
`SearchCourseInput` (Pydantic, `MCP_Tools/server/validation.py`) verlangt
`min_length=1` und erlaubt `max_length=20`. Mehr als **zwanzig** Kurse pro
Anfrage nimmt der Server heute nicht an, und **null** auch nicht.

**Daraus folgt ein Fehler, den man heute schon sehen kann.** Auf der
Tutor-Startseite geht kein `course_id` hinaus, `course_ids` ist dort also
leer, die Pydantic-Prüfung weist den Aufruf ab, und der Agent antwortet mit
„Die Suche in den Kursinhalten ist derzeit nicht verfügbar." Der globale Chat
bekommt von Moodle `rag_enabled: true` und kann trotzdem nichts aus dem
Kursmaterial holen.

**Der Schnitt mit den Einschreibungen fehlte auf beiden Seiten — jetzt zieht
ihn Moodle.** Der Agent holt die eingeschriebenen Kurse ab und legt sie in den
Zustand; sein eigener Typ sagt dazu `available_courses  # Courses the user is
enrolled in (not consumed yet)`. Die Spezifikation verlangte bis zum
20.09.2026 als einzige Eingrenzung den Mandanten — und der ist die ganze Site.
Seit MR !155 schickt Moodle nur noch Kurse, die die Person sehen darf, und der
Kontrakt hält fest, dass der Server auf genau das filtert (Absatz
„Entitlement"). Bei Max bleibt der Leerfall: ohne Argument muss er die
Einschreibungen selbst auflösen, statt mit leerer Liste in seine Prüfung zu
laufen.

**Was indexiert wird, entscheidet ein anderes Plugin.**
`local_elediaai_sources\course_gate::should_ingest()` kennt drei Regeln:
Kategorie-Positivliste (`enabledcategories`), Pilotkursliste (`pilotcourses`)
und ein Kurs-Feld `aisources` mit `Include`/`Exclude`/`Default`. Die beiden
Listen sind bereits als `autocomplete` mit `multiple => true` gebaut — **die
Bedienung, die der Betreiber sich für den Tutor wünscht, gibt es in der Suite
also schon, nur an der falschen Stelle und mit anderer Bedeutung.**

**Der Tutor hat heute keinen einzigen Kursbezug in seinen Einstellungen.** Die
Registry führt Persona, Design-Token, Verhalten, Launcher, Fußzeile, Dateien —
`classes/local/registry.php`, Gruppen `persona`…`files`. Kursauswahl: keine.

**Grounded oder nicht hängt an einer einzigen Kurs-ID.**
`chat_mode::resolve()` fragt `ingestion_available($courseid)`; für 0 (globaler
Chat) lautet die Antwort „ja, wenn überhaupt ein Ingestionsziel konfiguriert
ist". Mit einer Kursmenge wird aus dieser Ja/Nein-Frage eine Frage nach der
Schnittmenge.

**Jeder Chunk trägt seinen Kurs.** `qdrant_metadata.course_id` ist Pflichtfeld
der Ingestion-Spezifikation. Ein serverseitiger Filter auf eine Kursliste ist
also technisch vorbereitet; es fehlt die Liste und die Pflicht.

---

## Die Leitentscheidung: Wunsch und Berechtigung sind zwei Dinge

Die Versuchung ist, die gewählte Kursmenge als Berechtigung zu behandeln — „der
Tutor darf diese Kurse". Das wäre falsch und gefährlich:

- Eine Konfiguration, die **weitet**, würde Material aus Kursen zitieren, in
  denen die fragende Person nicht eingeschrieben ist. Wer den Ausschnitt
  pflegt, würde Berechtigungen vergeben, ohne es zu merken.
- Eine Konfiguration veraltet; Einschreibungen ändern sich täglich.

Deshalb gilt in diesem Plan durchgehend:

> Der gewählte Ausschnitt **schneidet ein**. Die Einschreibung **entscheidet**.
> Die durchsuchte Menge ist `gewählt ∩ eingeschrieben ∩ indexiert`, gebildet je
> Turn und dort, wo die Einschreibung zu Hause ist: in Moodle.

Dass der Schnitt bei uns liegt und nicht beim Server, ist eine eigene
Entscheidung (20.09.2026) mit einem eigenen Grund: der Mandant ist
authentifiziert und das Token gilt genau einer Person, die Liste ist also
keine fremde Eingabe. Zwei Implementierungen derselben Regel, die sich
widersprechen können, wären schlechter als eine an der richtigen Stelle — und
welche von beiden dann gewinnt, merkt niemand.

---

## Wo die Einstellung lebt

Auf allen drei Flächen — so entschieden —, und das heißt an drei verschiedenen
Orten, weil die Suite hier keinen gemeinsamen Speicher hat:

| Fläche | Wo der Wert liegt | Womit er gerendert wird |
|---|---|---|
| `block_elediaai_tutor` | Registry (`config_plugins`, `block_instances.configdata`, Profile) | neuer Feldtyp `coursescope`, vier Renderstellen erben ihn |
| `mod_aichat` | neue Spalte in `mdl_aichat` | `mod_form.php` |
| `mod_elli` | neue Spalte in `mdl_elli` | `mod_form.php` |

**Die gemeinsame Naht ist die Platzierung, nicht die Speicherung.** Das
`placement`-Interface der Engine beantwortet heute schon je Fläche, was die
Engine wissen muss (`courseid()`, `mode()`, `persona()`, `allow_tools()`). Eine
Methode mehr — `knowledge_scope(int $instanceid): string` — und die Engine
fragt jede Fläche dasselbe, ohne zu wissen, ob die Antwort aus einer Registry,
einer Aktivitätstabelle oder gar nicht kommt. Die Vorgabe ist der leere String,
also das heutige Verhalten; eine Fläche, die nie eine Wissensbasis bekommt,
muss nichts tun.

Gemeinsam ist außerdem die Auswahlliste: die Engine liefert die Optionen
(Bereiche und Kurse, gemischt, nach Recht gefiltert), damit nicht drei
Formulare dieselbe Abfrage bauen.

Beim Tutor bleibt es beim Registry-Weg — als neuer Feldtyp `coursescope` in
einer neuen Gruppe `knowledge` (Überschrift „Wissensbasis", einsortiert direkt
vor `conversation`). Das ist dort der billigste Weg zu Site-Vorgabe,
Instanz-Überschreibung, Profilen und Import/Export.

Die Registry wird an vier Stellen gerendert, und alle vier erben die neue
Zeile:

| Stelle | Datei | Was dort entsteht |
|---|---|---|
| Site-Einstellungen | `settings.php` | die Vorgabe für alle Tutoren |
| Block-Instanz | `edit_form.php` | Abweichung für diesen einen Block |
| Tutor-Profile | `manage_tutors.php` | Teil eines benannten Tutors |
| Import/Export | `classes/local/tutor_io.php` | wandert mit dem Profil mit |

Jede dieser vier Stellen kennt heute `text|textarea|select|checkbox|file` und
muss um den neuen Typ ergänzt werden — das ist der eigentliche Aufwand und der
Grund, warum Paket T-001 nur die Mechanik baut und noch keine Bedeutung.

**Der Leerfall gehört dem Server, nicht uns.** Ohne gewählten Ausschnitt
schickt Moodle wie heute kein `course_id` — auf der Startseite und überall
sonst, wo die Fläche keinen Kurs hat. Dann gilt als Basis, was die Person
belegt hat, und das weiß der Agent besser als wir: er holt die Einschreibungen
ohnehin über `moodle_verify_user_context` ab (Betreiberentscheidung
20.09.2026). Moodle rechnet die Liste also **nicht** vorsorglich aus; sie wäre
pro Person verschieden, sofort veraltet und würde den Cache wertlos machen.
Was heute fehlt, ist nur, dass der Agent im Leerfall tatsächlich auf diese
Kurse zurückfällt statt mit leerer Liste in die abweisende Prüfung zu laufen —
Work Item #28.

**Empfehlung zur Zuständigkeit:** `instanceable => true`, aber mit der
Einschränkung aus T-004 — eine Lehrkraft darf im Block **einschränken**, nicht
erweitern. Sonst setzt jemand mit `block/elediaai_tutor:addinstance` im eigenen
Kurs eine Wissensbasis aus fremden Kursbereichen.

---

## Datenmodell

Ein Feld, ein String, zwei Präfixe — gespeichert wie die bestehenden
`autocomplete`-Einstellungen, komma-getrennt:

```
cat:12,cat:34,course:7,course:915
```

Warum ein Feld und nicht zwei: Bereiche und Kurse sind **eine** Auswahl mit
einer gemeinsamen Semantik (Vereinigungsmenge). Zwei Felder hätten die Frage
aufgeworfen, ob sie sich schneiden oder vereinigen, und die Antwort wäre in
jeder zweiten Rückfrage neu zu erklären.

Warum Präfixe und keine zwei Zahlenräume: eine `12` ohne Präfix ist nicht
lesbar, und beim Import eines Profils aus einer anderen Site wäre nicht
erkennbar, was gemeint war.

Leerer String = „nicht gesetzt" = heutiges Verhalten (siehe T-003).

---

## Auflösung zur Kursmenge

Das Wertobjekt `course_scope` in `local_elediaai_chatengine\local` gibt es
seit MR !155; es bekommt die Einstellung als zusätzlichen Schritt vorgeschaltet
(nicht im Block, weil alle drei Flächen dieselbe Rechnung brauchen):

1. **Bereiche rekursiv auflösen.** `core_course_category::get($id)` →
   `get_courses(['recursive' => true])`. Unterbereiche zählen mit; ein Bereich
   ist eine Absicht („alles Kaufmännische"), keine Liste.
2. **Mit den einzeln gewählten Kursen vereinigen**, dedupliziert.
3. **Diesen Zwischenstand zwischenspeichern.** Bis hierher hängt nichts an der
   fragenden Person, also lohnt der Cache: `cache_definition` `coursescope`
   (application, TTL 15 Minuten), Schlüssel = Hash der Rohzeichenkette,
   Invalidierung an `course_category_created/updated/deleted` und
   `course_created/updated/deleted`. Ohne ihn kostet jeder Turn eine rekursive
   Kategorieabfrage.
4. **Mit den Einschreibungen schneiden.** Ab hier ist die Menge pro Person
   verschieden und wird nicht zwischengespeichert — `enrol_get_users_courses()`
   liefert sie frisch, wie schon heute in `course_scope`.
5. **Auf indexierte Kurse schneiden.** Dafür braucht es eine Mengen-Abfrage in
   `local_elediaai_sources` (`course_state::ingested_within(array $courseids):
   array`), weil heute nur die Einzelprüfung existiert und dreihundert
   Einzelabfragen pro Turn nicht in Frage kommen.
6. **Den Kurs der Fläche dazunehmen**, falls es einen gibt — Entscheidung 2,
   und ohne Einschreibungsprüfung (siehe unten).
7. **Deckeln — an einer Zahl, die dem Backend gehört.** `SearchCourseInput`
   im RAG-Werkzeug ließ am 20.09.2026 `max_length=20` zu. Fest verdrahtet ist
   das hier nicht: die Grenze steht als Einstellung `maxscopecourses` der
   Chat-Engine (Vorgabe zwanzig), weil ein Backend sie anheben kann, ohne uns
   zu fragen. Abgeschnitten wird nicht — welche zwanzig wären es dann?
   Stattdessen fällt das Argument weg, und die Einstellung sagt es beim
   Speichern (T-005).

**Die Reihenfolge ist der Punkt.** Geschnitten wird vor dem Zählen: ein
Kursbereich mit dreihundert Kursen ist harmlos, solange die Person in sieben
davon eingeschrieben ist. Der Deckel trifft dadurch fast nur noch Menschen mit
sehr vielen Einschreibungen, nicht große Bereiche.

---

## Transport zum Agenten — erledigt

Steht seit MR !155 und ist nicht mehr Teil dieses Plans:

- `local_elediaai_chatengine\local\course_scope` löst je Turn auf, welche
  Kurse durchsucht werden dürfen — eine Kursfläche nennt ihren Kurs, eine
  seitenweite Fläche die Einschreibungen der Person.
- `chat_request::course_argument()` liefert die komma-getrennte Zeichenkette,
  beide Adapter senden sie **im bestehenden `course_id`**.
- Über der Grenze von zwanzig bleibt das Argument weg statt abzuschneiden.
- Der Kontrakt führt beides (A.1 und der Absatz „Entitlement").

Was dieser Plan hinzufügt, ist ein **Schnitt davor**: die konfigurierte
Wissensbasis, geschnitten mit dem, was ohnehin gilt.

```text
gesendet = { Kurs der Fläche, falls es einen gibt }
         ∪ ( (Bereiche + Kurse der Einstellung, aufgelöst)
             ∩ Einschreibungen der Person
             ∩ indexierte Kurse )
```

Die Vereinigung links ist Entscheidung 2 und zugleich der Grund, warum der
Schnitt rechts steht und nicht über allem: der Kurs, in dem die Fläche steht,
gilt **ohne** Einschreibungsprüfung, weil die Platzierung den Zugang bereits
entschieden hat und er auf mehr ruhen kann als einer Einschreibung. Jeder
*andere* Kurs kommt nur herein, wenn die Person darin eingeschrieben ist.

**Die Startseite ist keine Ausnahme, sondern die andere Hälfte der Regel**
(Betreiber, 20.09.2026): eine Fläche ohne Kurs sucht in **allen
eingeschriebenen** Kursen, und eine konfigurierte Wissensbasis schränkt sie
nicht ein. Das ist keine Vereinfachung um der Einfachheit willen, sondern die
Bedeutung dieser Fläche — der Tutor auf der Startseite gehört der Person, nicht
einem Kurs.

Damit ist die Rechnung geschlossen, und zwar ohne Randfall:

| Fläche | Durchsucht wird |
|---|---|
| mit Kurs | Kurs der Fläche ∪ (Wissensbasis ∩ Einschreibungen ∩ indexiert) |
| ohne Kurs | alle eingeschriebenen Kurse |

**Die leere Menge kann damit nicht mehr entstehen.** Auf einer Kursfläche steht
der eigene Kurs immer darin (Entscheidung 2); auf der Startseite gilt die
Wissensbasis nicht. Es gibt also keinen Fall, in dem Moodle „keine Kurse" sagen
müsste — und weil das der einzige Fall war, der ein neues Feld gebraucht hätte,
**ändert sich am Kontrakt mit dem RAG-Backend nichts.** Dieselbe Angabe,
dasselbe Format, nur weniger Inhalt.

Der Preis ist eine Einstellung, die auf der Startseite nicht wirkt. Also darf
sie dort auch nicht angeboten werden: der Registry-Wert bleibt die Vorgabe für
Kursinstanzen, und an der Platzierung ohne Kurs erscheint das Feld nicht. Eine
Einstellung, die dasteht und nichts tut, ist schlechter als keine.

**Und das entschärft den Deckel.** Ein Kursbereich mit dreihundert Kursen ist
als *Einstellung* unproblematisch, solange die Person nur in sieben davon
eingeschrieben ist — geschnitten wird vor dem Zählen, und erst das Ergebnis
muss unter zwanzig bleiben. Die Überlauf-Regel trifft damit fast nur noch
Personen mit sehr vielen Einschreibungen, nicht große Bereiche.

## Auswirkung auf `chat_mode`

`ingestion_available(int $courseid)` wird zu
`ingestion_available(int $courseid, array $scope = [])`:

- Ausschnitt gesetzt → verfügbar, wenn **mindestens ein** Kurs der aufgelösten
  Menge indexiert ist.
- Ausschnitt leer → heutiges Verhalten, unverändert.

Damit fällt nebenbei eine bestehende Unschärfe: der globale Chat gilt heute als
„grounded", sobald irgendein Ingestionsziel konfiguriert ist — auch wenn kein
einziger Kurs indexiert wurde. Mit einem Ausschnitt ist die Frage beantwortbar.

---

## Oberfläche

Ein Feld, zwei Zeilen Hilfe, eine Bilanz:

```
Wissensbasis
[ Kursbereiche und Kurse suchen …                          ]
  ▸ Kaufmännische Grundbildung (Bereich, 34 Kurse)   ×
  ▸ Arbeitsrecht 2026 (Kurs)                          ×

  Durchsucht werden 34 von 35 gewählten Kursen.
  Ein Kurs ist noch nicht indexiert: „Arbeitsrecht 2026" → Wissensquellen öffnen
```

- **Ein Auswahlfeld für beides.** Die Vorschlagsliste führt Bereiche und Kurse
  gemischt, jeder Eintrag mit seiner Art beschriftet. Getrennte Felder wären
  zwei Suchvorgänge für eine Absicht.
- **Die Bilanz ist der eigentliche Nutzen.** „34 von 35" beantwortet die Frage,
  die sonst erst im Gespräch auffällt: warum der Tutor etwas nicht weiß. Der
  Verweis führt in die Wissensquellen, wo der Kurs freigeschaltet wird.
- **Leer heißt „wie bisher", und das steht auch da.** Die Beschreibung sagt:
  „Ohne Auswahl sucht dieser Tutor im Kurs, in dem er steht. Auf der Startseite
  sucht er immer in allen Kursen, in denen die fragende Person eingeschrieben
  ist — dort wirkt diese Einstellung nicht."
- Barrierefreiheit: Moodles `autocomplete` bringt sie mit; die Bilanz ist Text,
  kein Symbol, und steht in einem `aria-live="polite"`-Bereich, weil sie sich
  beim Auswählen ändert.

---

## Berechtigungen

**Keine neue Capability.** Wer eine Fläche konfigurieren darf, entscheidet
schon heute deren eigenes Recht — `block/elediaai_tutor:manage`,
`moodle/course:manageactivities` bei den beiden Aktivitäten. Drei neue
Capabilities für dieselbe Frage wären drei Stellen, an denen eine Rolle
vergessen wird.

Was hinzukommt, ist eine **Prüfung**, einmal in der Engine, von allen drei
Formularen gerufen:

| Wer | Darf wählen |
|---|---|
| Admin und Manager im Systemkontext | jeden Bereich, jeden Kurs |
| alle anderen, die die Fläche konfigurieren dürfen | nur Kurse, in denen sie selbst eine Lehrrolle haben, und Bereiche, deren Kurse sämtlich darunter fallen |

Geprüft wird **beim Speichern**, nicht nur beim Anzeigen der Auswahlliste —
eine Auswahlliste ist eine Bequemlichkeit, keine Kontrolle. Die Regel ist
Entscheidung 1: einschränken ja, erweitern nein.

---

## Upgrade und Rückwärtskompatibilität

Beim Tutor kein Datenbank-Upgrade: die Registry speichert in `config_plugins`
und in `block_instances.configdata`, ein neuer Schlüssel entsteht einfach.
Profile in `block_elediaai_tutor_tutor` tragen ihre Werte als serialisiertes
Feld und vertragen einen zusätzlichen Schlüssel.

Bei den beiden Aktivitäten schon: je eine Spalte `coursescope` (`char`, 1333)
in `mdl_aichat` und `mdl_elli`, mit `install.xml`, `upgrade.php` und
Versionserhöhung. Vorgabe ist der leere String.

Bestehende Installationen bekommen den leeren Ausschnitt und damit exakt ihr
bisheriges Verhalten. Es gibt keinen Zeitpunkt, an dem ein Tutor plötzlich
weniger weiß als am Tag davor.

Ein Profil aus einer anderen Site trägt fremde Kurs-IDs. Beim Import werden sie
**verworfen, nicht übersetzt**, mit einer Meldung: „3 Kurse und 1 Bereich aus
der Wissensbasis wurden nicht übernommen (unbekannte IDs)." Ein stillschweigend
übersetzter Kursbezug wäre geraten.

---

## Prüfstrategie

| Was | Wie |
|---|---|
| Auflösung | Unit: Bereich mit Unterbereichen, doppelte Nennung, gelöschter Kurs, gelöschter Bereich, Deckelung bei 20 |
| Reihenfolge | Unit: großer Bereich + wenige Einschreibungen ⇒ kurze Liste, **kein** Überlauf. Das ist die Annahme, auf der der Deckel erträglich ist |
| Eigener Kurs | Unit: Kurs der Fläche bleibt drin, auch ohne Einschreibung; ein anderer Kurs ohne Einschreibung fällt raus |
| Schnitt mit Indexierung | Unit gegen `course_state`, mit und ohne `local_elediaai_sources` installiert |
| Rechte | Unit: Lehrkraft speichert fremden Kurs → abgewiesen; Manager → angenommen |
| Leerfall | Unit: leerer Ausschnitt ⇒ `chat_mode::resolve()` liefert exakt das Ergebnis von heute (Regressionsschutz) |
| Startseite | Unit: Fläche ohne Kurs ⇒ Einschreibungen, **auch wenn eine Wissensbasis gesetzt ist**. Der Test hält die Entscheidung fest, an der hängt, dass der Kontrakt unverändert bleibt |
| Renderstellen | Behat je Fläche: Feld erscheint (Tutor: Site-Einstellungen, Instanzformular, Profil-Editor; `mod_aichat` und `mod_elli`: Aktivitätsformular), Wert überlebt Speichern und beim Tutor auch Export/Import |
| Transport | Unit gegen den Simulator-Adapter: Ausschnitt gesetzt ⇒ genau diese Ids in `course_id`; über dem Deckel ⇒ Argument fehlt |

---

## Umsetzungsreihenfolge

| Paket | Inhalt | Abhängigkeit |
|---|---|---|
| **T-001** | Engine: `placement::knowledge_scope()` im Interface, leere Vorgabe in allen Platzierungen, gemeinsame Auswahlliste (Bereiche + Kurse, rechtegefiltert) | — |
| **T-001a** | Tutor: Registry-Typ `coursescope` + Gruppe `knowledge`; alle vier Renderstellen; Speichern/Validieren | T-001 |
| **T-001b** | `mod_aichat`: Spalte, `install.xml`, `upgrade.php`, Formularfeld, Platzierung | T-001 |
| **T-001c** | `mod_elli`: dasselbe. Hier zahlt sich der Schnitt in Moodle doppelt aus — die Fläche verbietet die Rückrufe (`moodle_tools_enabled: false`), der Server könnte die Einschreibungen also gar nicht erfragen | T-001 |
| **T-002** | `course_scope` um die Einstellung erweitern: Bereiche rekursiv auflösen, mit den Einzelkursen vereinigen, mit den Einschreibungen und den indexierten Kursen schneiden; Cache und Invalidierung; `course_state::ingested_within()` in `local_elediaai_sources` | T-001 |
| **T-003** | Leerfall festnageln: Regressionstests, die das heutige Verhalten einzementieren, bevor T-004 daran rührt | T-002 |
| **T-004** | Rechte: Capability, serverseitige Prüfung, gefilterte Auswahlliste für Lehrkräfte | T-001 |
| **T-005** | Oberfläche: Bilanzzeile, Hinweis auf nicht indexierte Kurse, Verweis in die Wissensquellen; Deckelungsmeldung | T-002 |
| **T-006** | `chat_mode` auf die Menge umstellen | T-002, T-003 |
| ~~T-007~~ | ~~Transport~~ — **erledigt** mit MR !155 (chatengine 0.7.0) | — |
| **T-008** | Dokumentation: `03-dev-doc.md` des Kerns (neuer Registry-Typ ist ein Vertrag), Nutzerdoku, CHANGELOG | alle |

### Umsetzungsstand (20.09.2026)

Alle Pakete sind umgesetzt. Drei Dinge liefen anders als hier geplant, und
zwar zum Besseren:

- **Bereiche werden über die Tabellen aufgelöst**, nicht über
  `core_course_category::get_courses()`. Die Methode antwortet für den
  angemeldeten Menschen; derselbe Ausschnitt hätte sich je Person anders
  aufgelöst, und der Cache darüber hätte die Antwort der einen Person der
  nächsten gereicht. Jetzt entscheidet allein der Schnitt mit den
  Einschreibungen über die Sichtbarkeit, also die Stelle, an der das hingehört.
- **Über dem Deckel behält eine Kursfläche ihren eigenen Kurs**, statt das
  Argument wegzulassen. Weglassen hieße für den Server „alle Kurse dieser
  Person" — eine zu groß geratene Wissensbasis hätte die Suche also
  ausgeweitet statt eingeschränkt.
- **Keine neue Capability**, sondern eine Prüfung in der Engine, die alle drei
  Formulare beim Speichern rufen. Dieselbe Frage dreimal neu zu beantworten
  wären drei Stellen, an denen eine Rolle vergessen wird.

Eine Einschränkung bleibt: die Bilanzzeile beschreibt den **gespeicherten**
Wert. Ohne JavaScript folgt sie einer noch nicht abgeschickten Auswahl nicht,
und eine Zahl, die dem Feld stillschweigend hinterherhinkt, wäre schlechter
als keine — der Satz sagt deshalb, worauf er sich bezieht.

Alle verbleibenden Pakete sind ohne den Server nützlich und ohne ihn prüfbar.
Auf eine fremde Antwort wartet nur noch die Zahl im Deckel (#28) — und die
ändert eine Konstante, kein Paket.

---

## Risiken und offene Entscheidungen

**Vom Betreiber entschieden (20.09.2026):**

1. **Eine Lehrkraft darf am Block im eigenen Kurs einschränken, nicht
   erweitern.** Die Auswahlliste zeigt ihr nur Kurse mit eigener Lehrrolle,
   und geprüft wird beim Speichern, nicht nur in der Liste (T-004).
2. **Der Kurs, in dem die Fläche steht, zählt immer dazu**, implizit und nicht
   abwählbar.
3. **Alle drei Flächen bekommen die Einstellung**, nicht nur der Tutor.

**Technische Risiken:**

- **Große Bereiche** sind kleiner, als sie aussehen: geschnitten wird vor dem
  Zählen, und niemand ist in zweitausend Kursen eingeschrieben. Bleibt der
  Fall der Person mit mehr als zwanzig belegten Kursen aus der Wissensbasis.
  Dann fällt das Argument weg und der Server löst die Einschreibungen selbst
  auf — das ist heute schon so und verliert nur die Einschränkung durch die
  Einstellung. Sichtbar machen: in den Einstellungen, nicht zur Laufzeit, wo
  es niemand liest.
- **Der Ausschnitt suggeriert Sicherheit, die er nicht gibt.** Solange #28
  offen ist, kann ein Server die Liste ignorieren. Die Oberfläche darf deshalb
  nirgends „nur diese Kurse" versprechen; die Beschriftung lautet
  „Wissensbasis", nicht „Zugriffsbeschränkung".
- **Zwei Orte, die nach Kursen fragen.** Wissensquellen (was wird indexiert)
  und Tutor (worin wird gesucht) sind verschiedene Fragen, sehen aber gleich
  aus. Die Bilanzzeile aus T-005 ist die Brücke: sie zeigt genau dort, wo die
  zweite Frage gestellt wird, was die erste geantwortet hat.

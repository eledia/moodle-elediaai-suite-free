# Support-Matrix Dokumentformate

> **Matrix-Version:** 1.1
> **Stand:** 16.09.2026
> **Quelle der Wahrheit im Code:** `classes/format_matrix.php`
> **Gegenstück im Backend:** [AI-48](https://eledia-team-solutions.atlassian.net/browse/AI-48),
> gemeinsamer Vertrag in [`api-specification.md`](api-specification.md) (v1.3)

Diese Datei beschreibt für jeden Dateityp, ob Moodle ihn exportiert, wovon das
abhängt und — wo er nicht exportiert wird — warum nicht. „Nicht unterstützt“ ist
hier ein Ergebnis mit Begründung, kein Loch in der Liste.

Die Matrix im Code und diese Tabelle müssen übereinstimmen; der Test
`format_matrix_test::test_matrix_entries_are_wellformed` hält den Code-Teil
zusammen, die Fassung hier wird bei jeder Änderung mitgezogen.

## Die zwei Zustände

| Zustand | Bedeutung |
|---|---|
| **Kern** | Jedes Ziel muss den Typ verarbeiten. Wird immer exportiert. |
| **Ausgehandelt** | Wird exportiert, **solange das aktive Ziel den Typ ankündigt** (`GET /health` → `supported_content_types`). Kündigt es ihn nicht an, wird die Datei mit Begründung übersprungen. |

Einen dritten Zustand „auf dieser Seite noch nicht entschieden" gibt es
bewusst **nicht**. Auf der Moodle-Seite wird nichts geparst: Dateien werden aus
dem Dateispeicher gelesen und base64-kodiert weitergereicht. Jede offene Frage
zu einem Format — bleibt eine Tabelle als verwertbare Struktur erhalten? ist
ein Alt-Container sicher zu öffnen? — ist damit eine Parser-Frage und wird
dadurch beantwortet, dass das Ziel den Typ ankündigt oder eben nicht. Ein
zweites Tor hier wäre nur eine zweite Stelle zum Vergessen. Ein Typ, den
niemand ankündigt, bleibt ohnehin zu; ein Typ, der grundsätzlich nicht
hinausgehen soll, steht gar nicht erst in der Tabelle.

## Die Matrix

| Dateityp | MIME | Zustand | Binär | Anmerkung |
|---|---|---|---|---|
| Text | `text/plain` | Kern | nein | Auch das Ergebnis aller Text-Extraktoren (H5P, SCORM-Text). |
| HTML | `text/html` | Kern | nein | Seiten, Bücher, Glossare, Ordnerbeschreibungen, SCORM-HTML. |
| PDF | `application/pdf` | Kern | ja | Bytes reisen unverändert; geparst wird im Ziel. |
| Markdown | `text/markdown` | Ausgehandelt | nein | Moodle kennt den Typ nicht: Eine `.md`-Datei liegt als `document/unknown` im Dateispeicher und wird **über die Dateiendung** aufgelöst (`md`, `markdown`). |
| Word | `application/vnd.openxmlformats-officedocument.wordprocessingml.document` | Ausgehandelt | ja | Priorität 1 aus AI-48. |
| PowerPoint | `application/vnd.openxmlformats-officedocument.presentationml.presentation` | Ausgehandelt | ja | Priorität 1 aus AI-48. |
| Excel | `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` | Ausgehandelt | ja | Priorität 2 aus AI-48: Der Beispielkorpus entscheidet, ob Tabellen, Blattnamen und Zellbezüge für die Suche verwertbar bleiben — und zwar drüben, am Parser. |
| CSV | `text/csv` | Ausgehandelt | nein | Gleiche Frage wie XLSX. Technisch Text, inhaltlich eine Tabelle. |
| Word (alt) | `application/msword` | Ausgehandelt | ja | Altformat; ob ein Konverter dafür betrieben und verantwortet wird, entscheidet die Backend-Seite (AI-48). |
| OpenDocument Text | `application/vnd.oasis.opendocument.text` | Ausgehandelt | ja | wie DOC: Konverter-, Wartungs- und Sicherheitsfrage am Parser. |
| OpenDocument Tabelle | `application/vnd.oasis.opendocument.spreadsheet` | Ausgehandelt | ja | wie ODT. |
| OpenDocument Präsentation | `application/vnd.oasis.opendocument.presentation` | Ausgehandelt | ja | wie ODT. |

Alles, was hier nicht steht — Bilder, Videos, Archive, ausführbare Dateien —,
ist **kein Dokumentformat** und wird mit dem Hinweis „Dateityp gehört nicht zu
den unterstützten Dokumentformaten“ übersprungen.

## Was daraus folgt

**Wo die Matrix greift.** In `resource` (die Hauptdatei) und in `folder` (jede
Datei einzeln). Alle anderen Aktivitätstypen liefern Text oder HTML und damit
immer Kern-Typen; H5P und SCORM werden weiterhin im Plugin zu Text bzw. HTML
aufbereitet und sind von dieser Matrix nicht betroffen. Foren bleiben bewusst
ausgeklammert: Diskussionen gelten nicht als kuratierte Wissensquelle.

**Wer wann entscheidet.** Die Extraktoren fragen nur, ob ein Typ überhaupt ein
Dokumentformat ist (Kern + ausgehandelt). Ob das aktive Ziel ihn *heute*
annimmt, entscheidet der `ingestion_manager` beim Vorbereiten des Dokuments.
Das hat zwei Gründe: Extraktoren müssten sonst für jede Datei das Ziel
befragen, und in einem Ordner hinge die Nummerierung der Teildokumente
(`…:cmid99:file3`) davon ab, was das Backend gerade kann — eine Freischaltung
im Backend würde jede Datei dahinter umnummerieren und neu schreiben.

**Was der Nutzer sieht.** Jede übersprungene Datei erzeugt einen Hinweis mit
Dateiname und Grund: im Cron-Protokoll, in der Ergebnismeldung der Indexierung
und in der Vorschau der Aktivität. Zwei verschiedene Gründe sind möglich —
„gehört nicht zu den unterstützten Dokumentformaten" (ein Video, ein Bild) und
„das aktive Ziel verarbeitet diesen Typ derzeit nicht" (ein DOCX, solange die
Pipeline es nicht ankündigt). Das sind unterschiedliche Fragen und sie
verdienen unterschiedliche Antworten: Die erste ist dauerhaft, die zweite
erledigt sich mit dem nächsten Parser.

**Was der Betrieb sieht.** Der Systemzustand des Plugins nennt die Formate, die
gerade exportiert werden. Die Antwort des Ziels wird 15 Minuten
zwischengespeichert (`db/caches.php`), damit ein Cron-Lauf über tausend Module
nicht tausend Anfragen stellt; eine Freischaltung im Backend wirkt also
spätestens nach einer Viertelstunde.

## Wenn ein Format dazukommt

1. Eintrag in `classes/format_matrix.php` ergänzen und hier eintragen, dabei
   `VERSION` erhöhen.
2. Weiter nichts: Das Backend kündigt den Typ an, sobald sein Parser steht,
   und ab da geht er mit — ohne Moodle-Release.
3. Bei einem Format, das aus Moodle nicht mit eigenem MIME-Typ kommt
   (wie Markdown): Dateiendungen im Eintrag nennen.
4. `docs/api-specification.md` und den Changelog mitziehen.

Ein Format wieder **schließen** heißt: Eintrag entfernen. Dann gilt es als
kein Dokumentformat und wird mit entsprechendem Grund übersprungen, egal was
das Ziel ankündigt.

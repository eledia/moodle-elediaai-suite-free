# Changelog — local_elediaai_sources

Wesentliche Änderungen, neueste zuerst. Version = `release` aus version.php.
Format angelehnt an Keep a Changelog.
Ältere Historie: siehe `CHANGES.md` / git log.

Einträge bis einschließlich 0.15.0 sind unter dem früheren Namen
`local_ragingest` erschienen und nennen ihn deshalb weiterhin.

## [1.0.1] – 2026-09-25

### Behoben

- **Nie indizierte Aktivitäten wurden vom Abgleich nie nachgeholt** (#32).
  Der Abgleich fragte nur den Kurs-Merker `pending`, und den setzt allein ein
  wiederholbarer Fehler. Eine ausgewählte, sichtbare Aktivität, die nie einen
  Versuch bekam, hinterließ keine Spur — der Kurs galt für immer als fertig
  (Kurs 27 auf der Sandbox: drei Einträge). Jetzt gilt ein Kurs erst als
  abgeglichen, wenn jede ausgewählte sichtbare Aktivität einen Stand für das
  aktive Ziel hat; der geplante und der gezielte Abgleich holen fehlende nach.
- Damit „versucht, nichts zu senden" (kein Extraktor, leerer Inhalt, nur nicht
  sendbare Dateien) nicht als „nie versucht" gilt, wird es als eigener Stand
  `empty` festgehalten und in der Aktivitätenliste als „Nichts zu indizieren"
  gezeigt. Ein bestehender Erfolg wird davon nie überschrieben.
- Nach dem Update gleicht der Abgleich jeden freigegebenen Kurs mit solchen
  Aktivitäten einmal ab; unveränderte Inhalte werden dabei nicht erneut
  gesendet.

## [0.28.0] – 2026-09-20

### Geändert

- **Die Startseite der Website kann jetzt indexiert werden.** Sie war überall
  ausgeschlossen — Gatter, alle vier Durchläufe und die Pilotliste —, obwohl
  sie in Moodle ein Kurs ist, Aktivitäten trägt und einen Tutor aufnehmen
  kann. Ihr Material war für die Suite damit unsichtbar, ohne dass irgendwo
  stand, warum.
- Sie hat **keine Kategorie**, die Kategorie-Positivliste kann sie also nicht
  erfassen. Zwei Wege bleiben: sie in der Pilotkursliste benennen (dort steht
  sie jetzt zur Auswahl), oder **„Alle Kursbereiche"** einschalten — dieser
  Schalter bedeutet ab sofort wirklich *alle* Kurse, die Startseite
  eingeschlossen. Wer ihn gesetzt hat, bekommt sie beim nächsten Durchlauf
  mit indexiert.


## [0.27.1] – 2026-09-16
### Geändert (AI-75, Nachtrag)
- **XLSX, CSV, DOC und ODT/ODS/ODP sind jetzt ausgehandelte Formate** statt auf der Moodle-Seite gesperrt. Der dritte Matrix-Zustand „noch nicht entschieden“ entfällt ersatzlos (Matrix-Version 1.1)
- **Warum:** Auf dieser Seite wird nichts geparst — Dateien werden aus dem Dateispeicher gelesen und base64-kodiert weitergereicht. Ob eine Tabelle als verwertbare Struktur ankommt und ob ein Alt-Container sicher zu öffnen ist, entscheidet sich ausschließlich am Parser. Eine zusätzliche Sperre hier hätte dasselbe Tor ein zweites Mal gebaut und wäre die zweite Stelle zum Vergessen gewesen
- In der Sache ändert sich für den Betrieb nichts: Ein Format, das niemand ankündigt, bleibt zu. Es braucht nur keine Moodle-Änderung mehr, wenn die Auswertung in AI-48 für eines dieser Formate positiv ausfällt
- Übersprungene Dateien nennen jetzt zwei statt drei Gründe: „kein Dokumentformat“ (dauerhaft) und „das aktive Ziel verarbeitet den Typ derzeit nicht“ (erledigt sich mit dem nächsten Parser)
- Ein Format wieder schließen heißt: Eintrag aus der Matrix entfernen

## [0.27.0] – 2026-09-16
### Neu (AI-75, Gegenstück zu AI-48)
- **Eine versionierte Support-Matrix** (`classes/format_matrix.php`, v1.0, dokumentiert in `docs/format-support-matrix.md`) sagt für jeden MIME-Typ, ob er immer exportiert wird, ob er vom Ziel abhängt oder ob er bewusst noch nicht freigegeben ist. Bisher führten `resource`, `folder` und der `ingestion_manager` **drei** Listen, die nur so lange übereinstimmten, wie jemand an alle drei dachte
- **DOCX, PPTX und Markdown sind vorbereitet und werden ausgeliefert, sobald das aktive Ziel sie annimmt.** Das Ziel sagt selbst, was es lesen kann (`GET /health` → `supported_content_types`, API-Spezifikation v1.3); wird DOCX in der Pipeline freigeschaltet, geht es ohne Moodle-Release mit. Ein Format, das niemand verarbeiten kann, verlässt die Seite nicht — genau das war die Bedingung aus AI-75
- **Der Original-Dateiname reist mit** (`qdrant_metadata.title`). Eine Fundstelle lässt sich damit als „Skript_Woche3.pdf“ zitieren, und ein Parser-Log benennt die Datei statt nur eine Quell-ID. Der Titel gehört zum Inhalts-Hash, damit eine Umbenennung im Ordner ankommt
- **Übersprungene Dateien verschwinden nicht mehr still.** Ein Ordner, der vier von neun Dateien exportiert, sah bisher aus wie ein vollständig indizierter. Jede nicht exportierbare Datei erzeugt jetzt einen Hinweis mit Dateiname und Grund — im Protokoll, im Ergebnis und in der Vorschau, die zusätzlich den Dateinamen jedes Teildokuments zeigt
- XLSX/CSV sowie DOC und ODT/ODS/ODP stehen als **bewusst nicht freigegeben** in der Matrix, mit Begründung. Sie bleiben zu, auch wenn ein Ziel sie anbietet: Die offene Frage ist eine Produktentscheidung auf dieser Seite (Beispielkorpus bzw. Parser- und Sicherheitsbewertung, AI-48)
- Die Moodle-Seite meldet unter „Systemzustand“, welche Formate gerade hinausgehen
### Geändert
- Ordner-Teildokumente behalten ihre Nummerierung, wenn ein Format dazukommt oder wegfällt: Übersprungene Dateien belegen keinen Suffix
- Nach dem Upgrade wird **jede Aktivität einmal neu gesendet** — der Inhalts-Hash enthält jetzt den Titel. Der viertelstündliche Abgleich erledigt das von allein
- H5P, SCORM und die bewusste Ausklammerung von Foren bleiben unverändert

## [0.26.1] – 2026-09-16
### Behoben (Hotfix AI-80)
- **Ein Kurs, der leer abgeglichen und danach befüllt wurde, galt dauerhaft als „nicht indiziert“.** Die Aktivitäten lagen im Index, der Tutor nutzte sie aber nicht: Einzel-Ingests schrieben nur den Aktivitätsstatus, und der Abgleich überspringt Kurse, bei denen nichts mehr aussteht. Nach jedem Einzel-Ingest wird der Kursstatus jetzt nachgeführt
- Abgleich und „Neu indizieren“ ohne Force zählen **unveränderte, aber indizierte** Aktivitäten mit. Bisher meldeten sie `skipped` und hielten das Kursflag unten
- Bereits betroffene Kurse repariert die Aufgabe „Abgleich aller Kurse“ beim nächsten Lauf, ohne etwas neu zu senden
- Bewusst nur in eine Richtung: Das Flag wird aus dem Aktivitätsstatus angehoben, nie abgesenkt, weil Kurse, die vor Einführung des Aktivitätsstatus (Version 2026082304) indiziert wurden, keine Aktivitätszeilen haben

## [0.25.0] – 2026-08-24
### Behoben (nach Rückmeldung aus dem Test)
- Ein **verwaister Untertitel-Verweis** wird jetzt benannt statt nur verworfen: Verweist das Paket auf eine Untertiteldatei, die es nicht enthält, sind die Untertitel bereits erstellt und der Export ist kaputt — das behebt man anders als fehlende Untertitel. Bisher sah beides in der Ausgabe gleich aus
- Die Liste unter dem Befund trägt die Begründung, aus der sie entstanden ist. „Nicht zuzuordnen" über Dateien, die nie einer Spur zugeordnet werden sollten, widersprach dem Satz darüber
- Zahlwörter entschärft („1 media files") und ein Doppelpunkt nach der Überschrift, die sonst in den Text lief
### Neu
- **Barrierefreiheit im Probelauf.** Oben auf der Seite steht künftig, wie viele Medien des Pakets Untertitel haben — und welche nicht. Bisher ließ sich das nur erahnen, indem man ans Ende eines sehr langen Dokuments scrollte und nachsah, ob dort ein Transkript steht
- Der Befund kennt **vier** Ausgänge statt zwei: alle Medien untertitelt · nachweislich unvollständig · nicht zuzuordnen · gar keine Medien. „Nicht zuzuordnen" gibt es, weil ein Paket vierzig Medien und vierzig Untertiteldateien enthalten kann, die sich nicht paaren lassen; das als barrierefrei zu melden wäre ein Freispruch, es als nicht barrierefrei zu melden eine Unterstellung
- **Bewusst nicht an ein Autorenwerkzeug gebunden.** Gesucht werden die *Formen*, die die Verbindung annehmen kann, nicht die Produkte: `<track kind="captions|subtitles">` im HTML, eine nach der Mediendatei benannte Beidatei (auch `lektion.de.vtt` zu `lektion.mp4`), und eine Mediendatei, die im selben Datenobjekt neben einer Untertiteldatei steht. Damit werden Storyline, Rise, Captivate, iSpring, Lectora und handgeschriebene Pakete gleich behandelt
- Ein Verweis zählt nur, wenn die Datei, auf die er zeigt, **auch im Paket liegt**. Genau das lässt ein kaputter Export zurück, und es zu übersehen hieße, fehlenden Untertiteln einen Freibrief auszustellen
- Ein `<video>` **ohne** `<track>` gilt als Beweis für fehlende Untertitel, nicht als Zweifelsfall — es ist der klassische Barrierefreiheitsmangel und steht wörtlich im Markup
- **Ton und Video werden getrennt beurteilt, weil die Norm sie trennt.** Für Video mit Ton verlangt WCAG 1.2.2 Untertitel, und ein Transkript ersetzt sie nicht. Für Ton ohne Bild genügt nach 1.2.1 eine Textalternative — ein Audio-Paket mit Transkript als „nicht barrierefrei" zu melden wäre eine Falschbeschuldigung gewesen. Als Transkript-Beleg zählt eine Datei, die es im Namen führt, oder ein im Player aktivierter Transkript-Bereich; Untertitelspuren zählen hier bewusst **nicht**, weil sie bereits je Medium gewogen werden und sonst Medien freisprächen, die sie nie abgedeckt haben
- Die Prüfung liegt in einer eigenen Einheit und hängt an einer optionalen Schnittstelle. Aktivitätstypen, die die Frage nicht beantworten können, zeigen den Block schlicht nicht, statt eine Antwort zu erfinden
### Behoben
- **Das Player-Gerüst eines erstellten Pakets landete im Index.** Leere Struktur-Container, der Offline-Warndialog samt SVG-Pfaddaten, `<link>`-Elemente und HTML-Kommentare — für eine Wissensbasis reines Rauschen, und die Pfaddaten sind als bedeutungsloser Text sogar schädlich. Am Beispielpaket gemessen: 1760 → 274 Zeichen Markup, 165 → 0 Zeichen Klartext
- Die Regeln sind bewusst strukturell statt eine Liste bekannter Container-Namen: verworfen wird, was für Lernende unsichtbar ist (`hidden`, `display:none`), was gar keinen Text trägt, und was per ARIA-Rolle nur vorübergehend erscheint (`alert`, `alertdialog`, `dialog`, `status`). Landmarken wie `navigation` bleiben — in einem handgeschriebenen Paket kann dort ein Inhaltsverzeichnis stehen

## [0.24.3] – 2026-08-24
### Behoben
- **Die Einstellungsseite meldete „Too much data passed as arguments to js_call_amd".** Der Seitenkopf und die Indexierungs-Karte reisten als HTML durch die JavaScript-Argumentliste; Moodle warnt dort ab 1024 Zeichen, und der Kopf überschreitet das allein. Aufgefallen ist es erst mit 0.24.1: Vorher war der Prüfpunkt für die Hülle immer negativ, dieser Zweig lief also nie
- Die Markup reist jetzt über die Seite statt über die Argumentliste. Das Modul verschiebt die fertigen Knoten an ihren Platz, statt Markup aus einer Zeichenkette neu zu parsen — die Argumentliste trägt nur noch den Plugin-Namen
### Neu
- Zwei Prüfungen dazu: dass die Einstellungsseite kein Markup mehr an JavaScript übergibt, und dass die ausgelieferte AMD-Datei dieselben Container kennt wie ihre Quelle. Letzteres, weil hier keine JavaScript-Werkzeugkette verfügbar ist und die Bau-Datei von Hand kopiert wird — sie läuft sonst still auseinander, und ausgeliefert wird die Bau-Datei

## [0.24.2] – 2026-08-24
### Behoben
- **Der Probelauf scheiterte für die Administration mit „Attempt to read property id on null".** Die Seite setzte nur den Modulkontext, nie das Modul selbst. Damit blieb die Startseite ihr Kurs, während sie einen Modulkontext behauptete — und der Navigationsaufbau griff für diese Kombination auf das Modul zu, das die Seite nie bekommen hat. Lehrkräfte waren verschont, weil `require_login($course, false, $cm)` beides mitsetzt; die Administration nimmt einen kürzeren Weg durch die Anmeldung und blieb dabei ohne Modul
- Gesetzt wird jetzt `$PAGE->set_cm()`, das Kurs, Modul und Kontext gemeinsam vergibt. Damit entfällt auch die abweichende Seitenvorlage für die Administration: Mit gesetztem Modul **ist** die Seite eine Modulseite, und `incourse` ist für beide richtig
### Neu
- Zwei Tests dazu. Einer baut die Navigation für diese Seitenkonstellation wirklich auf — er reproduzierte die Meldung vor dem Fix. Der zweite prüft die Einstiegsdatei selbst, weil der Fehler im Seitenaufbau liegt, den kein Test ausführen kann: Ein Test, der den Aufbau nachstellt, bliebe grün, während die echte Seite kaputtgeht

## [0.24.1] – 2026-08-24
### Behoben
- **Die Admin-Seiten hatten seit der Suite-Umbenennung keinen Kopfbereich mehr.** Der Prüfpunkt fragte, ob `local_lernhive` installiert ist — dieses Plugin gibt es nicht mehr, seine Rolle liegt bei `local_elediaai_core`. Die Prüfung war damit dauerhaft negativ, und Einstellungen, Reindex und Hilfe rendered ihre Kopfzeile still gar nicht
- Gefragt wird jetzt nach dem tatsächlichen Anbieter der Hülle: Template und die `lh-plugin-*`-Gestaltung liegen beim Tutor-Block, dessen Stylesheet dieses Plugin ohnehin schon lud und an den es die Sektionsnavigation schon delegierte. Eine dritte Kopie des 233-zeiligen Templates wäre der Preis für scheinbare Unabhängigkeit gewesen
- Der tote Stylesheet-Verweis auf `/local/lernhive/styles.css` ist weg
### Neu
- Test für die Hülle: Der Templatename gehört einem fremden Plugin und ist damit das Einzige, was still verrotten kann — eine Umbenennung dort würde jede Admin-Seite dieses Plugins in eine Exception verwandeln, und nichts sonst in der Suite bemerkte es

## [0.24.0] – 2026-08-24
### Neu
- **Probelauf je Aktivität** (`preview.php`, verlinkt aus jeder Zeile der Aktivitätsauswahl): zeigt das Urteil samt Begründung, jedes Dokument mit Quell-ID, Typ, Größe und Fingerabdruck, den Wortlaut des Inhalts **so wie er gesendet würde** (escapt, nicht gerendert), die mitgesendeten Metadaten und das Verhältnis zum Index. Angeboten wird er auch für nicht unterstützte Aktivitätstypen — dort ist „kein Extractor" gerade die gesuchte Antwort
- Das Verhältnis zum Index wird in fünf ehrlichen Zuständen benannt statt als Ja/Nein: nicht im Index · identisch · ältere Fassung (nur deren Fingerabdruck ist gespeichert, nicht der Wortlaut) · Eintrag beschreibt das vorherige Ziel · letzter Versuch fehlgeschlagen. Dazu der Sonderfall, dass der Inhalt gleich blieb und nur das Embedding-Modell abweicht
- Zugang mit `:selectactivities` im Modulkontext oder `:reindex` im System. Die Testphasen-Sperre entzieht den Probelauf nur den Lehrkräften: Sie steuert, wer entscheidet, nicht wer nachsehen darf
- Die Reindex-Seite öffnet den Probelauf auch direkt über eine Aktivitäts-ID. Der Fehlerbericht dort und die Quell-IDen im Ziel nennen cmids; ohne diesen Einstieg wären sie eine Sackgasse, weil ein Admin selten im betroffenen Kurs eingeschrieben ist
### Geändert
- Die Gate-Kette (Kurs freigegeben? Aktivität ausgewählt? sichtbar? Extractor da?) liegt jetzt in einer eigenen Methode, die nur urteilt und nicht handelt. Aufnahme und Probelauf lesen dieselbe Quelle — eine zweite Kopie würde auseinanderlaufen, und der Probelauf löge dann ausgerechnet über seinen einzigen Zweck
### Grenzen
- Der Wortlaut einer **älteren** indexierten Fassung lässt sich nicht zeigen: Das Plugin speichert bewusst nur deren Fingerabdruck und hält keine zweite Kopie von Kursinhalten vor

## [0.23.0] – 2026-08-24
### Neu
- **Geführtes Räumen des bisherigen Ziels.** Nach einem Zielwechsel bleiben die Dokumente im alten Ziel liegen — das ist Absicht, weil es beim Umschalten oft nicht erreichbar ist und ein fehlschlagendes Räumen den Wechsel nicht blockieren darf. Die Reindex-Seite bietet das Räumen jetzt als ausdrückliche Aktion an, mit Erreichbarkeitsprüfung vorab und Bestätigung. Geräumt wird je Kurs über eine Hintergrundaufgabe, die ihr Ziel in den eigenen Daten trägt — bei einem erneuten Wechsel spricht sie weiterhin das Ziel an, für das sie eingeplant wurde
- `sink_manager::instance()` löst ein Ziel unabhängig davon auf, ob es das aktive ist; eine Schatten-Einstellung merkt sich das Vorgängerziel, weil der Updated-Callback den alten Wert bereits überschrieben vorfindet
### Behoben
- Das Räumen eines Ziels löschte den Indexzustand unabhängig davon, welches Ziel die Zeile beschreibt. Beim Räumen des **alten** Ziels hätte das die Wahrheit über das **neue** mit weggeworfen — frisch aufgenommene Inhalte hätten danach als nicht indexiert gegolten. Zustandszeilen werden jetzt nur noch für das tatsächlich geräumte Ziel entfernt

## [0.22.0] – 2026-08-23
### Neu
- Die Aufnahme-Entscheidung je Aktivität überlebt **Backup und Restore** und wird beim **Duplizieren** mitgenommen (Duplizieren läuft intern über Backup/Restore, erbt also automatisch). Bisher fiel eine Kopie auf den Site-Standard zurück — eine Kopie einer ausgeschlossenen Aktivität wäre also stillschweigend wieder aufgenommen worden
- Gesichert wird nur die Entscheidung, nicht der Indexzustand: Zustand beschreibt, was eine bestimmte Website an ein bestimmtes Ziel gesendet hat, und wäre anderswo eine Behauptung über einen Index, der die Inhalte nie erhalten hat. `usermodified` wird beim Wiederherstellen auf 0 gesetzt statt eine fremde Nutzer-ID zu übernehmen

## [0.21.0] – 2026-08-23
### Neu
- **PDFs innerhalb eines SCORM-Pakets** werden als eigene Teildokumente gesendet, statt im HTML unterzugehen. Sie behalten ihren Inhaltstyp, sodass der Dienst sie selbst zerlegt; das Suffix folgt dem Dateipfad und bleibt damit über Neu-Uploads stabil. Der Extractor ist dafür ein `multi_document_extractor` — die vorhandene Probe-und-Präfix-Löschung räumt umbenannte Dateien selbst ab
- **Untertitel-Sprachwahl:** Tragen Spuren eine Sprachkennung (`lesson.de.vtt` oder Storyline-`langCode`) und passt eine zur Kurssprache, wird nur diese aufgenommen — sonst standen alle Sprachfassungen nebeneinander im Index. Ohne Kurssprache, ohne Kennung oder ohne Treffer bleibt es bei allen Spuren, damit das einzige vorhandene Transkript nie verlorengeht
### Geändert
- **Navigationsbeschriftungen werden aus Bildschirmtexten gefiltert** („Weiter", „75 %", „Folie 3 von 12"). Nur vollständige Treffer gelten — „Weiter denken lohnt sich." bleibt Inhalt. Untertitel werden nie gefiltert
- Neue Einstellung **Bildschirmtexte aus SCORM-Paketen lesen** (Standard an) schaltet die Bildschirmtexte ganz ab, falls die Suche unruhig wird; der Sprechertext ist davon nie betroffen

## [0.20.0] – 2026-08-23
### Neu
- Die Kursseite zeigt je Aktivität den **Indexstatus** aus dem Zustand von 0.19.0: indexiert (mit Zeitpunkt), Fehler (mit Meldung) oder ehrlich „unbekannt", wenn das Plugin keine Aufnahme verbucht hat
- **Sammelaktionen je Kursabschnitt** („Alle aufnehmen" / „Alle ausschließen") über die neue External Function `local_elediaai_sources_set_activity_selection_bulk` — eine Transaktion statt vieler Einzelaufrufe, und eine fremde cmid bricht ab, bevor irgendetwas geschrieben ist, damit ein Abschnitt nie halb umgestellt zurückbleibt
- **Zurücksetzen auf den Site-Standard** je Aktivität. Die Schnittstelle konnte das seit 0.18.0 (`state=default`), die Oberfläche hat es nur nie angeboten
- **Filterfeld** auf der Kursseite für Kurse mit vielen Aktivitäten
- **Letzte Aufnahmefehler** auf der Reindex-Seite: fehlgeschlagene Aufnahmen standen bisher nur im Cron-Log, das niemand liest, bevor Inhalte in den Antworten fehlen
### Geändert
- Ein Wechsel von Opt-in auf Opt-out plant unentschiedene Aktivitäten freigegebener Kurse zur Aufnahme ein (ein Adhoc-Task fächert auf, damit der Speichern-Vorgang schnell bleibt). Die Gegenrichtung tut weiterhin bewusst nichts — auf Opt-in umzuschalten darf nie Inhalte entfernen

## [0.19.0] – 2026-08-23
### Neu
- Zustand je Aktivität (`local_elediaai_sources_cmstate`): was zuletzt wohin ging, unter welcher Quell-ID und mit welchem Inhalts-Hash. Eine fehlende Zeile bedeutet „nicht im Index" — Statusanzeigen können damit ehrlich sein statt zu raten
- Idempotenter Reindex: unveränderte Inhalte werden übersprungen statt blind neu gesendet. Auf der Reindex-Seite gibt es dafür „erzwingen", das alles erneut sendet — der Rettungsweg, falls das Ziel Daten verloren hat
- Wöchentlicher Aufräum-Task (So 04:00): entfernt Indexinhalte verwaister und verborgener Aktivitäten (über die **gespeicherte** Quell-ID, die den Mandanten des Aufnahmezeitpunkts einfriert) und löscht verwaiste Entscheidungs-/Zustandszeilen. Zeilen fremder Ziele werden ohne HTTP fallengelassen — das alte Ziel wird weiterhin nie automatisch angefasst
### Geändert
- **Verborgen heißt raus:** Eine für Lernende verborgene Aktivität (direkt oder über ihren Kursabschnitt) wird aus dem Index entfernt — was Lernende nicht sehen, darf der Tutor nicht zitieren. Sichtbarkeits-Umschalter feuern in Moodle kein Ereignis (in 4.5 und 5.2 verifiziert), deshalb konvergiert das über Reindex, Ereignispfad beim Speichern und den Aufräum-Task. Bewusst nicht über `uservisible` geprüft: geplante Aufgaben laufen als Nutzer, der Verborgenes sieht
- Fehlgeschlagene Aufnahmen hinterlassen jetzt einen sichtbaren Fehlerzustand je Aktivität statt nur einer Cron-Logzeile

## [0.18.0] – 2026-08-23
### Neu
- Lehrkräfte wählen je Aktivität, was in die Wissensbasis aufgenommen wird: über eine Kursseite mit Sofort-Schaltern (erreichbar aus der Kursnavigation, neue Capability `local/elediaai_sources:selectactivities` für editingteacher/manager) und über einen Abschnitt im Bearbeitungsformular jeder Aktivität. Ein ausdrücklicher Ausschluss entfernt bereits indexierte Inhalte der Aktivität per Prefix-Delete — bisher wurde ein abgelehntes Modul nur übersprungen und blieb im Index
- Admin-Einstellung „Aktivitäten ohne Entscheidung" (`activitydefault`): Standard bleibt Opt-out (alles Unterstützte wird aufgenommen); auf Opt-in umgestellt, werden unentschiedene Aktivitäten nur noch übersprungen. Ausdrückliche Entscheidungen haben immer Vorrang und überleben den Moduswechsel; das Umschalten selbst entfernt nichts aus dem Index, weil nur ausdrückliche Ausschlüsse löschen
- Neue Tabelle `local_elediaai_sources_cm` (nur ausdrückliche Entscheidungen, mit `usermodified` — im Privacy-Provider deklariert) und External Function `local_elediaai_sources_set_activity_selection`
### Grenzen
- Beim Duplizieren oder Wiederherstellen einer Aktivität geht die Entscheidung verloren; die Aktivität folgt dann wieder dem Modus-Standard. Während der Markierungssperre (Testphase) sind Feld, Seite und Schnittstelle deaktiviert

## [0.17.0] – 2026-08-23
### Neu
- Der SCORM-Extractor liest den Sprechertext aus den Untertiteln. Ein veröffentlichtes Paket ist oft nur eine Player-Hülle: Die Startseite trägt keinen Fließtext, sondern das Skript, das den Kurs lädt. Die Worte stehen in der Untertitelspur, die ein barrierefreies Paket ohnehin mitbringt — häufig der einzige verwertbare Text. Gelesen wird sie, wo immer sie liegt: als eigenständige `.vtt`/`.srt`-Datei (der werkzeugunabhängige Fall) und aus Articulate Storylines `*_captions.js`, das dieselbe WebVTT-Nutzlast URL-kodiert in einem JavaScript-Wrapper trägt
- Bildschirmtexte und Folientitel aus Storyline-Paketen (`window.globalProvideData('slide', …)`)
### Behoben
- Der Extractor sendete Inline-CSS als Kursinhalt. Veröffentlichte Pakete betten ihr Player-Stylesheet in die Startseite ein; ohne `style`- und `script`-Blöcke zu entfernen, bestand der „Text" dieses Dokuments aus CSS-Regeln, die als Inhalt eingebettet und wiedergefunden worden wären. Beim Beispielpaket schrumpft der Startseitentext dadurch von 1,4 kB CSS auf 98 Zeichen echten Text

## [0.16.0] – 2026-08-23
### Geändert
- **Breaking:** Die Komponente heißt `local_elediaai_sources`, Produktname „KI Quellen" bzw. „AI Sources". Der alte Name beschrieb das Verfahren (RAG-Ingest), nicht die Aufgabe: Kursinhalte auswählen, aufbereiten und an austauschbare Ziele übergeben (DEL-516, T-005, AC-1)
- **Breaking:** Der Subplugin-Typ heißt `aisourcesextractor`; die 17 Extraktoren heißen entsprechend `aisourcesextractor_*`
- **Breaking:** Tabelle `local_elediaai_sources_course`, Capability `local/elediaai_sources:reindex`, Einstellungsseite `local_elediaai_sources_settings`
- **Breaking:** Das Kurs-Custom-Field trägt den Shortname `aisources` statt `ragingest`. Bestehende Markierungen im alten Feld werden nicht übernommen und müssen neu gesetzt werden
- Verweise in `block_elediaai_tutor`, `local_elediaai_core`, `webservice_elediamcp` und `local_literag` sind nachgezogen. Diese Zugriffe sind mit `class_exists()` abgesichert und wären sonst nicht fehlgeschlagen, sondern stillschweigend zu „nicht verfügbar" geworden
- Die Einrichtungstexte in `local_literag` nennen für LiteRAG keine Endpunkt-URL mehr: Es gibt dort nichts zu konfigurieren, seit die Route aus `wwwroot` abgeleitet wird

### Migration
Es gibt **keinen** Migrationspfad. Die Komponente wird neu installiert, nicht
aktualisiert; `db/upgrade.php` beginnt leer. Eine Bestandsinstallation behält
`local_ragingest` als eigenständiges, dann funktionsloses Plugin — es muss über
*Website-Administration → Plugins → Plugin-Übersicht* **von Hand deinstalliert**
werden. Tabelle und Einstellungen des alten Plugins verschwinden erst damit.
Das Aufnahmeziel und die Kursmarkierungen sind danach neu zu setzen.

## [0.15.0] – 2026-08-23
### Behoben
- Das Löschen eines Kurses räumte seine Dokumente **nicht** aus dem Index. Erwartet worden war, dass dabei je Modul ein `course_module_deleted` anfällt; das stimmt nicht — `remove_course_contents()` ruft die `delete_instance()` der Module direkt auf und entfernt die `course_modules`-Zeilen selbst, sodass der Modul-Observer diese Module nie sieht. Da `course_deleted` zugleich die Zustandszeile vergaß, blieben die Dokumente dauerhaft zurück, ohne dass ein späterer Abgleich sie noch finden konnte. Der neue Hook auf `\core_course\hook\before_course_deleted` plant die Löschungen ein, solange der Kurs noch existiert und seine Modul-IDs lesbar sind (DEL-516, T-004, AC-3)
### Neu
- Prüfung, dass die Upsert-Nutzlast genau die in `docs/api-specification.md` v1.2 festgelegten Metadaten trägt — Mandant, Quell-IDs und Modulbezug — und kein erfundenes Feld dazukommt (DEL-516, T-003, AC-4)

## [0.14.0] – 2026-08-21
### Neu
- `local_ragingest_course` führt mit, in welches Ziel ein Kurs zuletzt aufgenommen wurde: `sink`, `embeddingmodel`, `tenant`. Ein Kurs gilt nur als aufgenommen, wenn alle drei zum konfigurierten Zustand passen (DEL-516, T-002)
### Geändert
- Zielwechsel, Modellwechsel oder Mandantenwechsel machen jeden Kurs abweichend; der vorhandene Abgleich nimmt ihn neu auf. Das bisherige Ziel wird dabei bewusst **nicht** geräumt — beim Umschalten ist es oft nicht mehr erreichbar, und ein fehlschlagendes Räumen dürfte das Umschalten nicht blockieren
- Upgrade-Schritt ergänzt die drei Spalten; Bestandszeilen tragen kein Ziel und werden dadurch einmalig neu aufgenommen

## [0.13.0] – 2026-08-21
### Entfernt
- Die DevFlow-Perspektivdokumente `docs/00-` bis `06-*.md` samt der Admin-Seite „DevFlow-Dokumentation" (`docs.php`), ihrem Menüpunkt, den zugehörigen Sprachstrings und `markdown_renderer`
### Neu
- `docs/user_manual.md` als einziges Handbuch für Administration und Nutzung, auf dem Stand der Zielauswahl; die Hilfeseite des Plugins rendert es
### Geändert
- Das Aufnahmeziel wird konfiguriert statt aus der Endpunkt-URL geraten: neue Einstellung `sink` mit genau einem aktiven Ziel, dahinter die Sink-Abstraktion (`sink`, `sink_manager`, `ingestion_api_sink`, `literag_sink`). `api_client::get_action_url()` mit seinen fünf URL-Mustern entfällt ersatzlos; `api_client` ist jetzt reiner HTTP-Transport (DEL-516, T-001)
- **Breaking:** Die Ingestion-API wird über `sink_ingestionapi_baseurl` (Basis-URL, ohne Pfad) und `sink_ingestionapi_apikey` konfiguriert. Die bisherigen Einstellungen `rag_endpoint_url` und `rag_api_key` entfallen — bestehende Instanzen müssen das Ziel neu konfigurieren
- LiteRAG als Ziel braucht keine eigenen Einstellungen mehr: Die Route wird aus `wwwroot` gebildet, der API-Schlüssel stammt aus `local_literag`

## [0.12.4] – 2026-08-03
### Geändert
- Referenzen auf die umbenannten Suite-Komponenten nachgezogen (`local_lernhive_ai` → `local_elediaai_core` usw.); keine Verhaltensänderung (SUI-560)
- Vollständiger E2E-Smoke (Observer → Cron → Upsert → Prefix-Delete) im Demo-Stack gegen `debug_server.py` erfolgreich abgeschlossen; Observer-, Ad-hoc-Task- und Delete-Pfad nachgewiesen (task04)

## [0.12.3] – 2026-07-09
### Neu
- Moodle-5.2-Support offiziell angehoben (`supported = [405, 502]`) nach erfolgreichem Marketplace-Preflight-PHPUnit-Lauf auf Moodle 5.2 (task03, risk01).
- GPL-Lizenzdatei für die Einreichung im Moodle Plugins Directory ergänzt.

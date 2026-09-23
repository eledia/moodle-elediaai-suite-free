# local_elediaai_core — Features

Diese Datei listet *was* das Plugin tut, nicht *wie*. Implementierungs-Details → `03-dev-doc.md`.

---

## feat01 — AI-Suite Launcher in der Navigation

**Intent:** Eine sichtbare, immer erreichbare Pille in der obersten Navigation, die zur zentralen KI-Suite-Übersicht führt.

**Verhalten:**
- Pille mit Astroid-Icon, sitzt direkt neben dem LernHive-Launcher
- Klick → `/local/elediaai_core/index.php`
- Einzelne KI-Plugins werden nicht im Launcher angeboten, sondern auf der KI-Suite-Übersicht
- Nicht sichtbar für Gäste / nicht eingeloggte User
- Nicht sichtbar in den Layouts `login`, `popup`, `embedded`, `maintenance`

**Akzeptanzkriterien:**
- AC01: Pille erscheint in `theme_lernhive` im Brand-Row, in anderen Themes neben `.usermenu` in der Navbar
- AC02: Klick auf die Pille öffnet die AI-Suite-Übersicht
- AC03: Es gibt kein Launcher-Flyout und keine direkten Plugin-Einträge im Launcher

## feat02 — Feature-Discovery via `feature_provider`

**Intent:** Sub-Plugins melden sich selbst an, ohne dass das Umbrella-Plugin sie kennen muss.

**Verhalten:**
- `registry::all()` durchsucht alle installierten Plugins nach `*\elediaai_core\feature_provider`-Klassen
- Jeder Provider liefert eine Liste von `descriptor`-Objekten
- Reihenfolge: alphabetisch nach `name`
- `registry::visible()` filtert per Capability + Plugin-Config-Toggle

**Akzeptanzkriterien:**
- AC01: Neu installiertes Sub-Plugin mit gültigem `feature_provider` erscheint ohne Code-Änderung in der AI-Suite-Übersicht
- AC02: Descriptor mit `capability` ist nur sichtbar für User, die die Capability halten
- AC03: Descriptor wird per `feature_<id>_enabled = 0` admin-seitig ausblendbar

## feat03 — Feature-Infoseiten (`feature.php`)

**Intent:** Jedes Feature hat eine kurze, lesbare Beschreibung der wesentlichen Funktionen mit klarem nächsten Schritt.

**Verhalten:**
- URL `/local/elediaai_core/feature.php?id=<descriptor-id>`
- Rendert: Icon (groß, im Feature-Pastell), Status-Pille (Verfügbar / In Vorbereitung), Name, Beschreibung
- Rendert eine kurze Liste „Wesentliche Funktionen", damit Admins pro Plugin den Nutzen schnell verstehen
- Kein Body-CTA-Doppel: „Funktion öffnen", Inline-„Einstellungen" und „Zurück zur KI-Suite" werden nicht auf der Karte gerendert
- Settings sitzt in Zone A als Action-Icon, aber nur bei in-shell `configurl` und Site-Config-Capability
- „KI-Suite" ist Teil der Feature-Section-Nav, kein Body-Link

**Akzeptanzkriterien:**
- AC01: Existierender Feature-Slug rendert die Karte
- AC02: Unbekannter / unsichtbarer Slug zeigt eine Notification, nicht 404
- AC03: Coming-Soon-Feature zeigt Status-Pille + Beschreibung, kein „Öffnen"-CTA
- AC04: Live-Features zeigen keine doppelten Body-Actions, sondern nutzen Shell-Navigation und Zone-A-Actions
- AC05: Hilfe-Link nutzt das Descriptor-Component-Handbuch, nicht pauschal `local_elediaai_core`
- AC06: Feature-Seiten zeigen pro Descriptor die wichtigsten Funktionen; wenn ein Sub-Plugin keine Liste liefert, nutzt die Suite einen Fallback nach Feature-ID

## feat04 — AI-Suite Dashboard (`index.php`)

**Intent:** Eine zentrale Übersicht aller registrierten Features mit kompakten Action-Icons pro Karte.

**Verhalten:**
- URL `/local/elediaai_core/`
- Bootstrap-Card-Grid, eine Karte pro `visible()`-Descriptor
- Karte enthält Status, Name, Kurzbeschreibung und eine Icon-Action rechts unten
- Primäraktion führt auf die Feature-Infoseite oder bei fehlendem externem Plugin auf den Install-Hinweis
- Admin-Einstellungen bleiben als Zahnrad-Icon sichtbar, wenn der Descriptor eine in-Shell-Konfigurationsseite liefert
- Der Launcher führt nur noch auf diese Übersicht

**Akzeptanzkriterien:**
- AC01: Empty-State zeigt eine Notification, wenn keine Features sichtbar sind
- AC02: Karte verlinkt per Icon-Action auf die Feature-Infoseite; Coming-Soon zeigt keinen Live-Start in der Detailseite
- AC03: Install-required Roadmap-Tiles zeigen eine Install-Action für Admins, ohne das externe Plugin in dieses Repo zu kopieren

## feat04b — Kursgebundenes Elli-Teacher-Dashboard (entfernt 2026-09-06)

**Entfernt.** Die Seite lag unter `/local/elediaai_core/teacher.php` und
buendelte Elli-Aktivitaeten eines Kurses, eine Werkzeug-Galerie und einen
sechsstufigen Assistenten zum Anlegen eines Szenarios.

Nichts davon war nur dort zu haben. Der Assistent lief durch dieselben sechs
Schritte, in die das Aktivitaetsformular von `mod_elli` ohnehin gegliedert ist
(`wizardstep_task`, `wizardstep_ai`, `wizardstep_access`, `wizardstep_timing`),
legte am Ende einen versteckten Entwurf an und uebergab an genau dieses
Formular. Die Werkzeug-Galerie war eine zweite Darstellung von
`registry::visible()`, die die Suite-Uebersicht schon zeigt; `teachertools`
ist ohnehin direkt erreichbar. Uebersicht, Sitzungen, Pruefen und Einblicke
waren gefilterte Listen dessen, was `mod/elli/index.php` und die Berichte der
Aktivitaeten fuehren.

Mit entfernt: `elli_wizard.php` samt Service, Entwurfsspeicher und
Schrittformular, `mod_elli\local\authoring_service` (hatte ausser dem
Assistenten keinen Aufrufer), der Kursnavigationseintrag, die Capability
`local/elediaai_core:manageteacherdashboard` und die Bildgenerierung fuer
Szenariobilder, die es nur im Assistenten gab und die nicht gebraucht wird.


## feat05 — Audit (Stufe 1 + 2 + UX-Refresh)

**Intent:** Eine schnell erreichbare Übersicht „wer hat wann welche KI-Aktion ausgelöst" innerhalb der AI-Suite.

**Verhalten:**
- URL `/local/elediaai_core/audit.php`
- Mountet einen eigenen System-Report (`local_elediaai_core\reportbuilder\local\systemreports\audit`), gebaut auf `ai_action_register` + den Core-Detailtabellen (`ai_action_generate_text|summarise_text|explain_text|generate_image`) via `LEFT JOIN` + `COALESCE`; der Bildjoin ist optional für Moodle-Matrix-Kompatibilität
- Spalten: Zeit · Nutzer:in · Aktion · Provider · Modell · Kontext · Prompt (👁) · Antwort (👁) · ✓/✗ · Fehler · Tokens bzw. Bildguthaben
- Filter: Provider, Aktion, Zeitraum, Erfolg, Nutzer:in
- Pagesize 25, Reportbuilder-Pagination
- Capability: `moodle/ai:viewaiusagereport`

**Akzeptanzkriterien:**
- AC01: User ohne Capability bekommen `report_access_exception` vor Render (kein leerer Report)
- AC02: Bericht ist downloadbar (CSV) — Download-Pfad liefert reinen Text statt der Icon-/Modal-HTML
- AC03: Audit-Karte erscheint in der Übersicht nur für User mit der Capability
- AC04: Prompt + Antwort werden als Auge-Icon-Button gerendert; Klick öffnet `ModalCancel` mit Volltext im `<pre>`-Wrapper (Whitespace + Zeilenumbrüche erhalten)
- AC05: Erfolg-Spalte rendert ein Häkchen-/X-Icon (grün/rot, inline Lucide-SVG) auf Screen, „Yes/No" beim Download
- AC06: Tokens-Spalte zeigt `prompt / completion`, kollabiert auf `—` wenn beide 0 (fehlgeschlagener Call)
- AC07: Fehlgeschlagene Aktionen werden in der Tabellenzeile visuell hervorgehoben, damit Admins Fehler schnell scannen können
- AC08: `generate_image` erscheint als eigene Aktion; Bildguthaben wird nach Größe/Anzahl in der bestehenden Token-Äquivalenz angezeigt und abgerechnet

### Bildguthaben und Quota

Bildaktionen werden vor dem Provider-Aufruf mit konfiguriertem token-äquivalentem
Guthaben reserviert. Standardwerte pro Bild sind 1.000 (bis 256px), 2.000 (bis
512px) und 4.000 (bis 1024px); `numimages` multipliziert den Preis. Damit bleiben
bestehende Stunden-/Tages-/Monatslimits und deren Währung unverändert. Nach Erfolg
wird der bestätigte Preis verbucht, bei Fehlern wird die Reservierung freigegeben.
Die Limits selbst sind in feat12 beschrieben.

**Stand 2026-05-16:**
- Stufe 1 (Core-Usage-Report-Wrap): done
- Stufe 2 (eigener Entity mit Prompt/Response, Fehler, Modell): done
- UX-Refresh Schritt 1 (Eye-Icon-Modal, Erfolg-Icon, kombinierte Tokens, Pagesize 25): done

**Roadmap (UX-Refresh Schritt 2+):**
- Free-Text-Suche über Prompt/Antwort (Custom-Filter)
- Fehlerzeilen rot eingefärbt
- Relative Zeit (`vor 2 Std.`) mit Tooltip auf absolute Zeit
- CSV-Download als Icon in Zone-A statt Reportbuilder-Default-Dropdown
- „Erneut prüfen"-Action pro Zeile (Modal mit Copy-Buttons für Prompt + Antwort)

## feat06 — Roadmap-Tiles (Coming-Soon)

**Intent:** Den geplanten Funktionsumfang der Suite sichtbar machen, auch bevor die Sub-Plugins existieren.

**Verhalten:**
- Stub-Descriptors in `local_elediaai_core\elediaai_core\feature_provider`: `coursegen` (Kurse), `translate` (Übersetzen), `tutor` (Tutor)
- Stubs haben `comingsoon: true`, kein `launchurl`
- Wenn ein Sub-Plugin später denselben `id` registriert und nicht mehr `comingsoon` ist, gewinnt der Live-Descriptor — geplant: Roadmap-Tile wird automatisch ersetzt sobald das Feature live ist
- Stand 2026-06-30: `filter_eledia_translate`, eLeDia.ai Tutor und weitere externe Plugins koennen sich per Provider live anmelden. Wenn sie fehlen, zeigt die Suite Install-Hinweise statt Code aus anderen Repos zu vendoren.

**Akzeptanzkriterien:**
- AC01: Roadmap-Tile zeigt Status-Pille „In Vorbereitung" auf der Feature-Seite
- AC02: Kein „Öffnen"-CTA für Roadmap-Tiles

## feat07 — Per-Feature On/Off-Toggle

**Intent:** Admin kann einzelne Features deaktivieren, ohne den ganzen Sub-Plugin zu deinstallieren.

**Verhalten:**
- Plugin-Config-Key: `feature_<id>_enabled` (1 = on, 0 = off, default = 1)
- `registry::visible()` filtert deaktivierte Features raus

**Akzeptanzkriterien:**
- AC01: Setzen von `feature_aichat_enabled = 0` versteckt die Chat-Karte in der Übersicht
- AC02: Direkter Aufruf der Feature-Seite eines deaktivierten Features → Not-Found-Notification (kein Crash)

## feat07b — Premium-Policy-Gate

**Intent:** Premium-Funktionen bleiben Teil der zentralen `local_elediaai_core`-Suite, werden aber erst angezeigt, wenn ein installiertes Premium-Addon sie fuer die Site freigibt.

**Verhalten:**
- `descriptor` hat ein `tier`-Feld (`free` default, `premium` optional)
- `registry::visible()` filtert Premium-Descriptors ueber `local_elediaai_core\feature\policy`
- Premium-Addons registrieren Policy-Provider unter `*\elediaai_core_policy\policy_provider`
- Das erste Addon heisst bewusst `local_elediaai_core_premium`; kein separates `local_eledia`-/`local_eledia_premium`-Umbrella

**Akzeptanzkriterien:**
- AC01: Free-Features bleiben ohne Policy-Provider sichtbar wie bisher
- AC02: Premium-Features sind in `registry::all()` registriert, aber ohne Grant nicht in `registry::visible()`
- AC03: Admin-Grant in `local_elediaai_core_premium` macht Premium-Features sichtbar
- AC04: Der bestehende Per-Feature-Toggle kann auch freigegebene Premium-Features ausblenden

## feat08 — AI-Feedback-Aktivität (mod_aifeedback)

**Status:** weitgehend fertig (2026-06-20): installierbare Activity, DB-Schema, AI-Suite-Kachel, Sync aus `mod_feedback`, asynchrone `core_ai`-Generierung mit lokalem Fallback, Teacher-Edit, Release an Lernende, Moodle-Notification, PDF-Download **und optionaler PDF-Anhang** für freigegebenes Feedback, **Backup/Restore**, **Regenerate-Button + Stale-Banner**, voller Privacy-Request-Provider, `course_module_viewed`-Event, PHPUnit-Coverage und Behat-E2E-Workflow (sync→review→release→Student-Sicht, Regenerate/Stale, Released-Schutz). Verifikation (PHPUnit/Behat-Lauf auf der Instanz) steht noch aus. Inspiration: gtn/moodle-mod_exaaifeedback.

**Intent:** Eigenständige Kursaktivität, die *nach* einem vorhandenen `mod_feedback`-Fragebogen pro Submission ein KI-generiertes Feedback erzeugt. Pädagog:in hinterlegt einen Prompt, KI generiert, Pädagog:in editiert + gibt frei („Release to user"), Lernende:r bekommt das Feedback inklusive optionaler PDF/E-Mail-Zustellung.

**Verhalten (Soll):**
- Neue Activity `mod_aifeedback` (frankenstyle eindeutig) mit Feldern: name, intro, feedbackid (FK zu `mod_feedback`), prompt, notification-Texte
- Per (Feedback-Submission, User) ein Result-Record: `data` (JSON: answers + metadata), `generatedfeedback`, `teacherfeedback`, `status`, `timereleased`
- Workflow: Student füllt Fragebogen → Lehrkraft synchronisiert Abgaben → Adhoc-Task ruft `core_ai\generate_text` auf (Fallback: lokaler Entwurf) → Pädagog:in reviewt/editiert → klickt „Release" → Feedback-Web-Ansicht + Moodle-Notification + PDF-Download frei
- Optionaler PDF-Anhang in der Notification: ✅ umgesetzt (Instanz-Option `attachpdf`)
- `regenerate_feedback`-Button, der den Cache verwirft und neu generiert (vgl. feat09 Stale/Regenerate): ✅ umgesetzt inkl. Stale-Banner
- Kompletter Behat-Course-End-to-End-Workflow: ✅ umgesetzt (`tests/behat/teacher_workflow.feature`)

**Akzeptanzkriterien:**
- AC01: Aktivität in der AI-Suite-Launcher-Übersicht (eigener Descriptor mit Icon)
- AC02: Audit-Trail erfasst Provider-Generierungen automatisch (über `core_ai`-Logging); Fallback-Entwürfe markieren `generation=local_fallback`
- AC03: Edit-before-Release ist **Pflicht** — kein Auto-Release-Pfad in v1
- AC04: PDF-Export über Moodle `pdflib` für freigegebenes Feedback
- AC05: Notification nutzt Moodle-Messaging mit Custom-Subject/Body-Placeholders

**Bewusst NICHT übernommen aus mod_exaaifeedback:**
- GTNs eigener AI-Endpoint und Provider-Layer → wir bleiben bei `core_ai` (siehe `00-master.md` adr01)

**Geschätzter Aufwand:** ~3-5 Tage.

## feat09 — Stale-/Regenerate-Pattern über Eingabe-Hash

**Status:** teilweise umgesetzt — Question-Generator für Kursinhalte (2026-05-23)

**Intent:** Wenn sich der Eingabekontext eines gecachten KI-Outputs ändert (Prompt-Template editiert, Quellinhalt geändert, System-Prompt aktualisiert), zeigt die UI an „kann neu generiert werden" — ohne automatisches Re-Trigger, das Token kostet.

**Anwendungsfälle:**
- **Question-Generator** (feat: lokal in `local_elediaai_questiongen`): Wenn eine Quellaktivität (Page/Book/Lesson) geändert wurde, kann der bereits importierte Fragensatz erneut generiert werden
- **mod_aichat**: Wenn der System-Prompt der Aktivität neu gesetzt wurde, signalisiere Lernenden „Tutor hat neue Einstellungen", optional Thread leeren
- **feat08 mod_aifeedback**: „Regenerate AI feedback" mit Bestätigungs-Dialog (vgl. exa-Pattern)

**Verhalten (Soll):**
- Hash über `(prompt, payload, model)` beim Generieren persistieren
- Beim Aufrufen der View-Seite: aktuellen Hash neu berechnen und gegen den gecachten vergleichen
- Bei Drift: dezenter „Neu generieren möglich"-Banner mit Button

**Stand 2026-05-23:**
- `local_elediaai_questiongen` speichert `sourcecmid` + `inputhash` pro Job
- Für `coursecontents` liest `review.php` die Quellaktivität erneut, vergleicht den aktuellen Hash und zeigt bei Drift einen Warnhinweis
- Button „Aus aktueller Quelle neu generieren" legt einen neuen Job mit aktuellem Payload an und führt zurück in den Progress-/Review-Flow
- Topic/Story-Quellen bleiben stabil, weil ihre Payload bei Job-Erstellung eingefroren ist

**Geschätzter Aufwand:** ~½-1 Tag pro Feature.

## feat10 — KI-Ergebnis-Zustellung (Notification + PDF-Export)

**Status:** geplant (Voraussetzung für feat08, optional für Audit-Export)

**Intent:** Generische Pipeline „KI-Ergebnis zustellen" — Notification mit Custom-Subject/Body und Placeholders (`{user.firstname}`, `{feedbackurl}` …) + optionaler PDF-Export mit Logo und Google-Font.

**Anwendungsfälle:**
- feat08 mod_aifeedback: Notification + PDF an Lernende:n
- feat05 Audit-Stufe 2: PDF/CSV-Export einzelner Audit-Zeilen für DSGVO-Auskunftsersuchen

**Geschätzter Aufwand:** ~1 Tag (Notification-API + tcpdf-Wrapper).

## feat11 — Question-Generator Edit-before-Release

**Status:** done (2026-05-23), Shell-Settings + Help-Hub + Prompt/Model controls added (2026-05-23)

**Intent:** KI-generierte Fragen sollen nicht mehr direkt in den Fragenpool geschrieben werden. Lehrende prüfen, bearbeiten und selektieren die Ausgabe, bevor daraus Moodle-Fragen werden.

**Verhalten:**
- `local_elediaai_questiongen` erzeugt im Adhoc-Task nur Moodle-XML und setzt den Job auf `review`
- Progress-Poller leitet bei Status `review` auf `/local/elediaai_questiongen/review.php`
- Review-Seite extrahiert einzelne `<question>`-Blöcke, repariert typische LLM-XML-Near-Misses und zeigt pro Frage eine editierbare Vorschau mit Fragetyp, Name, Fragetext, Antwortoptionen, korrekter Antwort und Feedback
- Unterstützte Review-/Import-Typen in v1: `multichoice`, `truefalse`, `shortanswer`, `matching`, `ordering`; Moodle-XML ist kein primärer Teacher-Workflow mehr
- Generate-Formular bietet die Fragetypen-Auswahl, Guided/Normal Mode, Source-Modi (Thema, Text, Upload, Kursinhalte), Prompt-Anzeige und gespeicherte Prompts
- Upload-Modus akzeptiert TXT, Markdown, DOCX, alte DOC-Dateien und textbasierte PDFs; gescannte PDFs benötigen später OCR
- `/local/elediaai_questiongen/configuration.php` ist die in-shell Einstellungsseite für die Modellwahl
- Hilfe führt auf ein eigenes `local_elediaai_questiongen`-Handbuch im Help Hub
- Buttons: „Auswahl importieren", „Alle importieren", „Verwerfen"
- Erst der Review-POST baut aus den Vorschau-Feldern Moodle-XML, ruft den Moodle-XML-Importer auf und setzt den Job auf `done`
- Auf der Questionbank-Seite erscheint zusätzlich zum qbank-Menüeintrag ein prominenter „KI: Fragen generieren"-Button

**Akzeptanzkriterien:**
- AC01: Nach erfolgreicher KI-Generierung wird nichts automatisch importiert
- AC02: Lehrende können einzelne Fragen abwählen oder per strukturierter Vorschau bearbeiten; XML ist nur Experten-Fallback
- AC03: „Auswahl importieren" importiert nur selektierte Fragen; „Alle importieren" importiert alle sichtbaren Fragen
- AC04: Rohantworten ohne prüfbare Moodle-Fragen zeigen eine Fehlermeldung und bleiben sichtbar zur Diagnose
- AC05: Original-Hash pro Frage ist sichtbar/persistiert, damit spätere Änderungsanzeige darauf aufbauen kann
- AC06: Generator ist auf der Questionbank-Seite prominent erreichbar, nicht nur im Dropdown
- AC07: Lehrende wählen vor der Generierung die gewünschten Fragetypen aus
- AC08: Lehrende können Text-, Markdown-, Word- und PDF-Dateien als Quelle hochladen
- AC09: Admins konfigurieren das Questiongen-Modell in der Plugin Shell, nicht in `/admin/settings.php`
- AC10: Help-Icon der Questiongen-Feature-Seite öffnet eine vorhandene Hilfe-Seite mit Questiongen-spezifischem Inhalt

## feat12 — KI-Guthabenlimits (Token-Quota pro Nutzer:in)

**Status:** done — Limits, atomare Reservierung und Bildguthaben umgesetzt; automatische Bereinigung alter Zähler steht aus (siehe „Grenzen").

**Intent:** Eine Site soll ihre KI-Kosten deckeln können, ohne einzelne Plugins
zu konfigurieren. Das Limit gilt pro Nutzer:in und greift, *bevor* eine externe
KI-Anfrage rausgeht — nicht als Rechnung hinterher.

**Verhalten:**
- Zwei Gruppen: **Student** und **Teacher**. Wer irgendwo eine Rolle mit
  Archetyp „Teacher" oder „Editing Teacher" hält, zählt als Teacher.
- Drei Zeitfenster je Gruppe: pro Stunde, pro Tag, pro Monat. Es sind
  Kalenderfenster (volle Stunde, Tagesbeginn, Monatserster), keine gleitenden.
- Sechs Limits unter *Website-Administration → Plugins → Lokale Plugins →
  eLeDia.ai* („KI-Guthabenlimits"). **`0` = unbegrenzt**, und das ist der
  Auslieferungszustand: ohne Konfiguration limitiert nichts.
- Gezählt wird in Tokens. Bilder kosten token-äquivalentes Guthaben nach Größe
  und Anzahl (feat05, „Bildguthaben und Quota"), damit es nur *eine* Währung gibt.
- Vor jeder Anfrage wird die geschätzte Prompt-Länge plus ein konfigurierbarer
  Completion-Puffer (Default 500) reserviert. Die Reservierung zählt sofort
  gegen das Limit, sodass parallele Anfragen es nicht gemeinsam überziehen.
  Nach der Antwort wird auf die tatsächliche Nutzung korrigiert; bei Fehlern
  wird die Reservierung freigegeben und nichts verbraucht.
- Am Limit bekommt die Nutzer:in eine verständliche Meldung mit Limit und
  Zeitfenster, keine technische Exception.
- Gespeichert werden nur Zähler pro Nutzer:in und Fenster — keine Prompts, keine
  Antworten. Auskunfts- und Löschanfragen (DSGVO) bedient der Privacy-Provider.

**Akzeptanzkriterien:**
- AC01: Ohne konfigurierte Limits verhält sich die Suite wie vorher (keine Drosselung)
- AC02: Ein erreichtes Stunden-, Tages- oder Monatslimit blockt die nächste Anfrage mit einer lokalisierten Meldung, die Limit und Zeitfenster nennt
- AC03: Teacher und Lernende werden getrennt gezählt und getrennt konfiguriert
- AC04: Zwei gleichzeitige Anfragen am Limit können es nicht gemeinsam überziehen
- AC05: Eine fehlgeschlagene Anfrage verbraucht kein Guthaben
- AC06: Bildaktionen belasten dasselbe Guthaben wie Textaktionen
- AC07: Die Zähler einer Nutzer:in werden auf Löschanfrage vollständig entfernt

**Grenzen:**
- Das Limit greift nur bei Providern, die den Quota-Vertrag der Suite nutzen
  (`aiprovider_eledia`, Elli-Wizard, Tutor-Block). Ein direkt konfigurierter
  Fremd-Provider wird nicht gedeckelt.
- Die 90-Tage-Aufbewahrung der Zähler ist implementiert, aber noch nicht an den
  Moodle-Cron gehängt; alte Zeilen bleiben bis dahin stehen.

## feat13 — KI-Session-Komposition für LernHive — **entfernt am 05.09.2026**

**Status:** entfernt. Der einzige Einstieg war
`local_elediaai_core_lernhive_session_compose()` in `lib.php` — ein Callback für
den Hook `lernhive_session_compose`, den **nur `local_lernhive` aufgerufen
hätte**. Dieses Plugin ist nicht Teil der Suite und existiert nicht. Nichts in
der Suite hat die Funktion je gerufen; sie konnte nie laufen.

Entfernt wurden: der Callback, `classes/local/session_composer.php`, die
Einstellung `enable_session_composer` samt beider Sprachstrings, und
`tests/session_composer_test.php`. Ein Upgrade-Schritt räumt den verwaisten
Konfigurationswert weg.

**Was damit auch weg ist:** die einzige Stelle, an der die Suite von sich aus
eine KI-Aktion auslöste, ohne dass eine Person sie angestoßen hat. Jede
verbliebene KI-Aktion geht von einer Eingabe aus.

## feat14 — Entwicklerseite: das Design-System, gerendert

**Status:** umgesetzt (05.09.2026, task24). `developer.php`,
`classes/output/developer_page.php`, `amd/src/developer.js`.

**Intent:** Wer an der Suite baut oder eine fremde Installation diagnostiziert,
soll sehen, wie die Suite *hier* aussieht — nicht, wie sie aussehen sollte.

**Verhalten:**
- Eine Seite in `local_elediaai_core`, erreichbar nur mit `moodle/site:config`
  und nur, wenn `local_elediaai_core/developerdocs` an ist (Vorgabe: aus).
- Zeigt oben die Umgebung: aktives Theme, ob es die Suite-Token überschreibt,
  Versionen der Suite-Plugins.
- Zeigt jeden Token **aufgelöst und mit Herkunft**: aus dem Theme, aus der
  Core-Vorgabe oder als Rückfall im Plugin. Der dritte Fall wird als Befund
  markiert — das Plugin liest die Skala nicht.
- Rendert die Skalen (Schrift, Abstand, Radius, Schatten) als Beispiel und
  jeden gemeinsamen Baustein live in seinen Zuständen.

**Wie umgesetzt, mit den Abweichungen:**

- Die Token-Liste und ihre Gruppierung werden zur Laufzeit aus dem
  `:root`-Block von `styles.css` gelesen, nicht im PHP gepflegt. Ein neuer
  Token erscheint damit ohne Zutun; eine Liste von Hand wäre binnen eines
  Release neben der Quelle gelaufen.
- Die **Herkunft** bestimmt der Browser, nicht der Server: er durchläuft alle
  Stylesheets und nimmt je Token die stärkste Deklaration, bei Gleichstand die
  späteste. Der Wert allein taugt nicht als Kennzeichen — ein abgeleitetes
  Token steht als `var(--x)` in der Datei und aufgelöst im Browser.
- Gemessen auf Moodle 5.2: 56 Token aus Core, **genau die fünf**, die
  `theme_elediaai` laut adr05 überschreiben darf, aus dem Theme. Die Seite
  belegt die Zusage damit selbst, an jeder Installation.
- Die Skalen für Abstand, Radius und Schatten sind als Token-Tabelle da, aber
  nicht eigens als Beispiel gerendert — anders als die Schriftskala, wo die
  gezeichnete Stufe die eigentliche Aussage ist.
- Rendert den Vertrag aus `docs/03-dev-doc.md` (Abschnitt „Design-System")
  über `FORMAT_MARKDOWN` — eine Quelle, zwei Ausgaben.
- Ist das Setting an, führt der Wegweiser über einen Eintrag „Entwicklung"
  dorthin; ist es aus, existiert weder Eintrag noch Seite für Nutzende.

**Nicht-Ziele:** keine Redaktionsfunktion, keine Bearbeitung von Token in der
Oberfläche, keine Kopplung an `$CFG->debugdeveloper`.

## Release-Übersicht

| Release | Inhalt | Status |
|---|---|---|
| rel01 | feat01..feat04, feat06, feat07 (Launcher + Discovery + Dashboard + Info-Seiten + Roadmap-Tiles + Toggle) | done |
| rel02 | feat05 Audit Stufe 1 | done |
| rel03 | Audit Stufe 2 (Prompt/Response sichtbar) | done |
| rel04 | feat11 Question-Generator Edit-before-Release | done |
| rel05 | feat08 mod_aifeedback Activity | fertig — Backup/Restore, Regenerate/Stale, Privacy, PHPUnit, Behat-E2E done; Instanz-Verifikation ausstehend |
| rel06 | feat10 Notification + PDF-Export-Pipeline | done — Moodle-Notification, PDF-Download und optionaler PDF-Anhang umgesetzt |
| rel07 | feat09 Stale/Regenerate-Pattern (Question-Generator zuerst) | partially done |
| rel08 | feat12 Token-Quota (Limits, atomare Reservierung, Bildguthaben) | done — Retention-Task offen |
| rel09 | feat13 KI-Session-Komposition (`lernhive_session_compose`) | entfernt 05.09.2026 |

# Roadmap — eLeDia / LernHive Plugin-Suite

> Stand: 2026-07-25. Dieses Dokument ist als **Arbeitsliste für KI-Agents** gedacht.
> Jeder Task ist eigenständig abgrenzbar und enthält Ziel, betroffene Dateien,
> Akzeptanzkriterien und Abhängigkeiten. Die Beschreibungen abgeschlossener Tasks
> bleiben als Entscheidungshistorie erhalten; maßgeblich ist die Status-Checkliste
> am Dokumentende.

## Konventionen für Agents

- Vor Code-Änderung: relevanten Skill lesen (`moodle-framework` bzw. `moodle-dev`).
- Jede Plugin-Änderung: `version.php` hochzählen, deutsche **und** englische Lang-Strings pflegen.
- Nach Änderung: PHPUnit/Behat des betroffenen Plugins lokal grün (`./scripts/local-deploy.sh phpunit`).
- Keine Secrets committen. `.env*` bleibt lokal.
- Status pro Task unten in der Checkliste pflegen.

---

## Phase 0 — Architektur-Cleanup (zuerst, blockierend)

> ✅ **Erledigt (verifiziert 2026-07-06).** `public/local/eledia` und
> `local/eledia_premium` sind entfernt; kein Code, kein Deploy-Skript und keine CI
> referenziert die `local_eledia`-Foundation mehr (die scheinbaren Treffer sind
> Substrings von `local_elediaai_tutor_premium`, einem legitimen Plugin).
> `local_elediaai_core\feature\registry` ist wieder die kanonische Registry (kein
> deprecated-Delegator mehr). Von den AI-Tutor-Blöcken existiert nur noch
> `block_elediaai_tutor`; der Duplikat-Block ist weg. Suite-Upgrade läuft in
> `elediaai-moodle-1` sauber durch.

### T0.1 — Suite-Registry zurück auf Legacy, dann `local_eledia` + `local_eledia_premium` löschen
**Status:** ✅ erledigt · **Priorität:** Hoch · **Aufwand:** L · **Abhängigkeit:** keine (muss vor allem anderen laufen)

**Kontext:** `local_eledia\suite\registry` ist aktuell die Foundation-Registry, zu der
die Suite migriert wurde. Die alte Registry in `local_elediaai_core` (`classes/feature/registry.php`)
ist nur noch ein deprecated Delegator. Entscheidung: **zurück auf die Legacy-Registry**,
dann Foundation entfernen.

**Schritte:**
1. Echte Registry-Logik aus `local/eledia/classes/suite/` (`registry.php`, `descriptor.php`,
   `tier.php`, `policy.php`, `policy_provider.php`, `feature_provider.php`) nach
   `local_elediaai_core` zurückholen (Git-Historie vor der Migration nutzen oder Implementierung
   aus `local_eledia\suite` dorthin verschieben). `local_elediaai_core\feature\registry` wieder
   zur kanonischen Implementierung machen (deprecated-Hinweis entfernen).
2. Konsumenten umstellen — `use local_eledia\suite\...` → `use local_elediaai_core\...`:
   - `local/elediaai_questiongen/classes/eledia_suite/feature_provider.php` (4 Treffer)
   - `local/elediaai_tactics/classes/eledia_suite/feature_provider.php` (2 Treffer)
   - `local/elediaai_core/{index.php, feature.php, classes/hook_callbacks.php, classes/output/section_nav.php, classes/feature/registry.php, tests/registry_test.php}`
   - `local/elediaai_strategy/plugins.php`
3. `local_eledia` aus den `dependencies`-Arrays entfernen in:
   `local/elediaai_core/version.php`, `local/elediaai_strategy/version.php`,
   `local/elediaai_questiongen/version.php`.
4. Ordner löschen: `public/local/eledia/`, `public/local/eledia_premium/`.
5. Deploy-Bundle/Skripte prüfen: keine Referenz auf `local_eledia` / `local_eledia_premium`
   mehr in `scripts/`, `infra/docker/`, CI.

**Akzeptanzkriterien:**
- `grep -r "local_eledia" public/` liefert **null** Treffer (außer ggf. den
  aitutor-Blöcken, siehe T0.2).
- Suite-Launcher listet weiterhin alle Features (questiongen, tactics, strategy …).
- PHPUnit von `local_elediaai_core`, `elediaai_questiongen`, `elediaai_tactics`, `elediaai_strategy` grün.

### T0.2 — Doppelte AI-Tutor-Blöcke aus dem Repo nehmen
**Status:** ✅ erledigt · **Priorität:** Mittel · **Aufwand:** S · **Abhängigkeit:** —

Im historischen Bundle gab es zwei nahezu identische, unterschiedlich benannte
AI-Tutor-Blöcke (beide 0.14.1, je ~17k LOC), die `local_eledia` referenzierten.
**Entscheidung des Owners:** Der AI-Tutor wurde durch ein fertiges externes
Plugin ersetzt → die historischen lokalen Blöcke werden **nicht
weiterentwickelt**. Der heute gepflegte Monorepo-Block heißt kanonisch
`block_elediaai_tutor`.

**Schritte:** Beide historischen Block-Ordner aus dem Deploy-Bundle entfernen (oder Repo), damit keine
toten `local_eledia`-Referenzen nach T0.1 zurückbleiben. Falls vorerst behalten: in T0.1 die
`local_eledia`-Referenzen in `classes/eledia_suite/feature_provider.php` und
`classes/local/premium.php` mit-bereinigen, damit der Build nicht bricht.

**Akzeptanzkriterium:** Build/Upgrade läuft ohne fehlende `local_eledia`-Klassen.

---

## Phase 1 — Qualität & Compliance

### T1.1 — Privacy-Provider für `local_elediaai_strategy`
**Priorität:** Hoch · **Aufwand:** S · **Abhängigkeit:** —

Einziges Plugin der Suite ohne Privacy-Provider. DSGVO/BFSG-relevant.
Provider nach Moodle-Privacy-API ergänzen (mindestens `null_provider` falls keine
personenbezogenen Daten gespeichert werden — sonst vollständige Metadata-Deklaration).

**Akzeptanz:** `classes/privacy/provider.php` vorhanden; Privacy-Test grün; Plugin erscheint
sauber im Privacy-Register.

### T1.2 — Testabdeckung für dünne Plugins
**Priorität:** Mittel · **Aufwand:** M · **Abhängigkeit:** —

- `qbank/elediaai_questiongen` — **keine Tests** (237 LOC). Mindestens Smoke-/Integrationstest.
- `mod/aichat` — nur 1 Test (1.7k LOC).
- `mod/aifeedback` — nur 4 Tests (2.8k LOC), Review-/Release-Flow abdecken.

**Akzeptanz:** Jedes der drei Plugins hat sinnvolle Unit-Tests für Kernpfade; CI grün.

### T1.3 — CI: Test-/Lint-Workflow ergänzen
**Priorität:** Hoch · **Aufwand:** M · **Abhängigkeit:** —

Aktuell existiert nur `.github/workflows/deploy.yml` (Deploy). Es fehlt ein Pflicht-Gate
vor Merge.

**Schritte:** `moodle-plugin-ci`-Workflow hinzufügen (PHP Lint, Code Checker, PHPDoc,
PHPUnit, Behat, Mustache/Grunt) für alle `public/`. Matrix über Moodle 5.2 / PHP 8.3+8.4.

**Akzeptanz:** PR-Checks laufen automatisch; rote Checks blockieren Merge.

### T1.4 — Accessibility-Audit der UI-Plugins (BFSG)
**Priorität:** Mittel · **Aufwand:** M · **Abhängigkeit:** —

BFSG ist seit 2025 verpflichtend. Skill `webui-accessibility-auditor` nutzen.
Fokus: Views/Reports/Forms von `local_lernhive`, `local_elediaai_strategy`,
`local_elediaai_questiongen`, `mod_aichat`, `mod_aifeedback`, `block_elediaai_chat`.

**Akzeptanz:** Befundbericht mit Priorisierung; kritische WCAG-2.2-Verstöße behoben.

### T1.5 — Versionsnummern an Reifegrad angleichen
**Priorität:** Niedrig · **Aufwand:** S · **Abhängigkeit:** T1.2/T1.3 sinnvoll vorher

`local_lernhive` ist real Beta-reif (18k LOC, 23 Tests), läuft aber als ALPHA 0.5.97.
Maturity/Release-Strings konsistent über die Suite setzen (Maturity-Stufen begründet vergeben).

---

## Phase 2 — Strategisches Feature: Lehrplan-Kompetenz-Kursgenerator (HOHE PRIO)

> Top-Feature laut Marktanalyse (`FEATURE-KONZEPTE.md`): einziger Bereich ohne Moodle-natives
> Pendant. Neues Plugin **`local_elediaai_coursegen`**. Kann parallel zu Phase 1 von einem eigenen
> Agent bearbeitet werden, sollte aber **nach T0.1** starten (stabile Suite-Registry).
> Agents: Skill `moodle-framework` für APIs (Competency, course/modlib, Forms, Privacy) lesen.

> **Stand 2026-09-08 — abgelöst durch Kursautor 2.** KG.1–KG.7 waren erledigt,
> ihr Zuschnitt (ein Lehrplan-Einstieg, drei Aktivitätstypen, Session-Zustand)
> ist inzwischen durch einen didaktisch geführten Generator ersetzt. Plan,
> Entscheidungen und Stufen: `../../elediaai_coursegen/docs/plan-kursautor-2.md`.
>
> | Stufe | Inhalt | Stand |
> |---|---|---|
> | KA.1 | Premium-Gate, eigene Einstellungsseite, `health_provider` | ✅ 2026-09-07 |
> | KA.2 | Gefuehrter Steckbrief in fünf Schritten, didaktisches Modell als Daten | ✅ 2026-09-07 |
> | KA.3 | Zwölf Bausteine, Vorlage v2, gestufte Erzeugung | ✅ 2026-09-07 |
> | KA.4 | Auftrag statt Session, adhoc-Tasks, Privacy | ✅ 2026-09-08 |
> | KA.5 | Quiz- und H5P-Adapter, Links, Untergruppen, Bilder | ✅ 2026-09-08 |
> | KA.6 | Zwei MCP-Werkzeuge, Tutor-Starter | ✅ 2026-09-08 |
> | KA.7 | Handbuch, Ankündigung, README, Roadmap | ✅ 2026-09-08 |
> | KA.8 | Barrierefreiheit, Release | offen |
>
> **Der Kursautor ist seither Premium.** Damit das ging, musste
> `local_elediaai_tutor_premium` geweitet werden: sein `policy_provider`
> antwortete zuvor nur für Descriptoren der eigenen Komponente und machte
> jedes Premium-Feature anderswo unfreischaltbar. Siehe
> `03-dev-doc.md`, Abschnitt „5. Premium freischalten".

### KG.1 — Plugin-Gerüst `local_elediaai_coursegen`
**Prio:** Hoch · **Aufwand:** S · **Abh.:** T0.1
`version.php` (dependency `local_elediaai_core`), `db/access.php` (Capability „darf generieren"),
`settings.php`, Lang `de`+`en`, Privacy-Provider (mind. `null_provider`).
**Akzeptanz:** Plugin installiert sauber, erscheint im Admin, keine PHPCS-Fehler.

### KG.2 — Lehrplan-Datenquelle + Auswahl-UI
**Prio:** Hoch · **Aufwand:** M · **Abh.:** KG.1
Auswahl Bundesland → Fach → Klasse/Stufe → Themenbereich. **Datenquelle abstrahieren**
(Provider-Interface), damit Fächer/Länder erweiterbar sind. **MVP: Fach Mathematik** auf Basis
des `mathe-rahmenlehrplaene`-Wissens.
**Akzeptanz:** Lehrkraft kann einen konkreten Lehrplan-Ausschnitt auswählen; Auswahl wird als
strukturiertes Objekt an die Pipeline übergeben.

### KG.3 — KI-Generierungs-Pipeline
**Prio:** Hoch · **Aufwand:** L · **Abh.:** KG.2
Aus dem Lehrplan-Ausschnitt erzeugt der AI-Manager (`local_elediaai_core`) einen **strukturierten
Vorschlag als validiertes JSON**: Abschnitte → Lernziele/Kompetenzen → Aktivitäten (Seite/Aufgabe/
Quiz-Platzhalter). Prompt-Template + Schema-Validierung + Fehlerbehandlung.
**Akzeptanz:** Reproduzierbarer, schema-valider Strukturvorschlag; Unit-Test mit Mock-AI-Antwort grün.

### KG.4 — Kompetenz-Mapping, zentrale Wiederverwendung (Differenzierer)
**Prio:** Hoch · **Aufwand:** M · **Abh.:** KG.3
Über die Moodle **Competency-API**: Kompetenzrahmen + Kompetenzen anlegen und Aktivitäten zuordnen.
**Owner-Entscheidung:** EIN Rahmen je **Land/Fach/Klasse**, kursübergreifend wiederverwenden —
per `idnumber` (`lh-<land>-<fach>-<klasse>`) suchen, nur bei Fehlen anlegen. Strikt idempotent.
**Akzeptanz:** Zweiter Lauf mit gleichem Land/Fach/Klasse nutzt denselben Rahmen wieder, keine Duplikate.

### KG.5 — Teacher-Review + Schreiben (neuer ODER bestehender Kurs)
**Prio:** Hoch · **Aufwand:** L · **Abh.:** KG.3, KG.4
Vorschau-/Bearbeiten-UI; **nichts wird ohne Bestätigung angelegt**. **Owner-Entscheidung:** Zielkurs
pro Lauf wählbar — *neuer* Kurs oder Einfügen in *bestehenden* (Abschnitts-Offset + Duplikat-Schutz).
Erstellung via Moodle course-/modlib-API in einer Transaktion.
**Akzeptanz:** Beide Zielkurs-Pfade erzeugen korrekte Struktur; Behat-Test für beide grün.

### KG.6 — Quizfragen via `local_elediaai_questiongen` (MVP-Bestandteil)
**Prio:** Hoch · **Aufwand:** M · **Abh.:** KG.5
**Owner-Entscheidung: Teil des MVP** (nicht optional). Quiz-Platzhalter direkt mit echten,
review-baren Fragen aus dem Question Generator befüllen.
**Akzeptanz:** Aus einem Platzhalter entsteht im generierten Kurs ein Quiz mit echten Fragen.

### KG.7 — Tests & Doku
**Prio:** Hoch · **Aufwand:** M · **Abh.:** KG.1–KG.5
PHPUnit (Pipeline, Mapping), Behat (Review-Flow), `README.md` (de/en), Eintrag in CI (T1.3).
**Akzeptanz:** Coverage der Kernpfade; CI grün; Kurzanleitung vorhanden.

---

## Phase 3 — Weitere Feature-Ideen (bewertet, nach Stabilisierung)

> Priorisierung siehe `FEATURE-KONZEPTE.md`.
> **Adaptiver Lernpfad** — geparkt (Big Win, aber Scope-Risiko): später als schlanker MVP.
> **MCP-Aktions-Agent** — zurückgestellt (Infrastruktur `webservice_elediamcp` existiert bereits).
> **Kompetenz-Mapping als eigenes Produkt** — gespeichert, Nischenmarkt (läuft als Baustein in KG.4 mit).

### T2.1 — AI-Kosten-/Nutzungs-Dashboard
**Priorität:** Mittel · **Aufwand:** L

`local_elediaai_core` hat bereits eine Audit-Seite. Ausbauen zu echtem Reporting:
Token-/Request-Verbrauch pro Provider, Kurs, Nutzer; Zeitverlauf; ggf. Budget-Warnungen.

### T2.1b — Teacher-Tools-Wizard-Schicht
**Priorität:** Hoch · **Aufwand:** L · **Abhängigkeit:** stabiler `local_elediaai_core` Launcher

Viele kleine KI-Helfer, Moodle-nativ mit Kurs-/Abschnitt-/Aktivitätskontext und
Review-vor-Schreiben. Details und Agent-Aufteilung stehen in
`HANDOVER-teacher-dashboard.md`.

**Geändert 2026-09-06:** Das kursgebundene Teacher-Dashboard in
`local_elediaai_core` war ursprünglich als Einstieg dafür gedacht und ist
entfernt worden — es bot nichts, was die Suite-Übersicht, `mod/elli/index.php`
und `local_elediaai_teachertools` nicht schon boten. Die Werkzeug-Schicht steht
seitdem auf ihrem eigenen Plugin; ein neuer Einstieg wäre eigens zu entwerfen
und nicht wiederherzustellen.

**MVP:** neues Plugin `local_elediaai_teachertools` mit erstem Wizard
„Arbeitsblatt erstellen" und Speichern als Moodle Page.

**Akzeptanz:** Lehrkraft kann aus Thema/Aktivität/Abschnitt ein Arbeitsblatt erzeugen,
prüfen, editieren und als Moodle Page in einem Kursabschnitt speichern.

**Nächster Prompt-/Wizard-Sprint:** Arbeitsblatt-Prompts didaktisch ausbauen.
Aufgabentypen als Wizard-Chips/Multi-Select statt nur Freitext: Gemischt,
Lückentext, Multiple Choice, Zuordnung, offene Fragen, Reflexion, Transfer,
Kreativaufgabe, Dialog/Rollenspiel, Experiment/Beobachtung, Schritt-für-Schritt-
Erklärung. Zusätzlich Kompetenzfokus (Wissen, Anwenden/Üben, Analyse,
Diskussion, Transfer, Wiederholung) und Schwierigkeit (leicht, mittel,
anspruchsvoll, differenziert). Prompt bleibt JSON-basiert; Tasks sollen `type`,
`competency`, `difficulty`, `prompt`, `items`, `answer`, `hint` liefern. Layout
bleibt serverseitig im Renderer, nicht im Modell-Prompt.

**Kompetenzmodell-Spur:** Moodle-Kompetenzen als Lehrplan-/Rahmenlehrplan-Kontext
im Arbeitsblattgenerator nutzen. Wizard soll kursnahe Kompetenzen zuerst anbieten
und bei Bedarf Framework-Kompetenzen auswählbar machen. Ausgewählte Kompetenzen
gehen mit id + Beschreibung in den Prompt; die KI darf nur aus diesen
Kompetenzen wählen und keine Kompetenznamen/-IDs erfinden. Beim Speichern als
`mod_page` sollen die ausgewählten Kompetenzen über Moodle Competency API an die
Aktivität gehängt werden. Aufgaben im JSON sollen, soweit eindeutig, `competency`
und `competencyid` tragen; unklare Zuordnungen werden als Teacher Note markiert.

**Target-Group-Taxonomie:** Die Zielgruppe soll nicht mehr als generische
Wizard-Preset-Liste gepflegt werden. Stattdessen braucht Teacher Tools eine
Deutschland-orientierte, admin-pflegbare Taxonomie, z. B. Bundesland /
Bildungskontext -> Schulform -> Jahrgang/Stufe -> optional Bildungsgang oder
Niveau. Lehrkräfte wählen in ihrem Moodle-/Teacher-Profil einmal die für sie
relevanten Einträge. Der Worksheet-Wizard übernimmt daraus den finalen Kontext
wie `Mathematik, Klasse 7, Gymnasium, Berlin` und zeigt nur noch einen
Override für abweichende Einzelfälle. Der Prompt erhält diesen finalen
Zielgruppen-Kontext, aber keine offene, widersprüchliche Kombination aus altem
Preset plus Freitext.

**Sprint-Stand 2026-07-05:** Prompt-/Wizard-Sprint ist im MVP-Pfad umgesetzt:
Aufgabentypen, Kompetenzfokus und Schwierigkeit steuern den JSON-Prompt; Tasks
tragen `competency`, `competencyid` und `difficulty`. Kursnahe Moodle-
Kompetenzen sind auswählbar, werden in den Prompt gegeben und beim Speichern
der `mod_page` an das Course Module gehängt. Nächster Ausbau: Framework-
Kompetenzsuche/Fallback, visuelle Chip-Controls statt nativer Multi-Selects,
und Behat-Demo für den kompletten Arbeitsblattfluss.

**Nächster Qualitäts-Sprint:** Drei Themen sind MVP-kritisch und im DevFlow von
`local_elediaai_teachertools` als `task05`-`task07` angelegt:
1. ✅ **Erledigt 2026-07-05 (task05):** Ein Worksheet-Design-Baseline spannt sich
   über Review-Vorschau, PDF und DOCX (Palette, nummerierte Task-Abstände, gezeichnete
   Antwortlinien, gestylte Task-Typ-Blöcke). PDF-Typografie und DOCX-Styles überarbeitet;
   DOCX bleibt editierbar und strukturell valide.
2. ✅ **Erledigt 2026-07-05 (task06):** Task-Typen wirken jetzt im Prompt (selektions-
   bewusste Regeln) und in der serverseitigen Darstellung sichtbar. Neue Klasse
   `task_types` erzeugt render-fertige Strukturen; `gaptext`/`multiplechoice`/
   `matching` werden in HTML, PDF und DOCX unterschiedlich dargestellt.
3. 🟡 **Teilweise (task07):** Deterministische Evaluations-Harness steht — `worksheet_evaluator`
   labelt Fehler automatisch, `cli/evaluate_worksheets.php` fährt 7 repräsentative Fälle,
   Rubrik in `docs/06-evaluation.md`. Offen: die eigentlichen `--generate`-Läufe gegen
   konfigurierte Provider und die Produktions-Empfehlung (`q01`).

Verifikation: 39 PHPUnit-Tests grün in `elediaai-moodle-1` (inkl. task04-Async).

### T2.2 — Eigenes Suite-Theme / konsistentes Look-and-feel
**Priorität:** Niedrig · **Aufwand:** L

Aktuell kein Theme. Skill `eledia-moodle-ux` als Design-System nutzen, um ein konsistentes
eLeDia/LernHive-Erscheinungsbild zu etablieren.

### T2.3 — Moodle Plugins Directory: Submission der stabilen Kandidaten
**Priorität:** Niedrig · **Aufwand:** M

Reif genug zur Einreichung: `qtype_aitext` (STABLE), `filter_eledia_translate` (STABLE),
`local_activityfilter` (STABLE). Skill `moodle-plugin-submit` nutzen (Metadaten, Release-ZIP,
QA-Bot-Feedback).

---

## Statusüberblick (aktualisiert 2026-07-25)

| Plugin | Release | Maturity | Privacy |
|---|---|---|:--:|
| webservice_elediamcp | 1.5.1 | STABLE | ✅ |
| local_activityfilter | 1.1.2 | STABLE | ✅ (`core_ai`-Link) |
| qtype_aitext | 2.02-lernhive.7 | STABLE | ✅ |
| filter_eledia_translate | 3.0.3 | STABLE | ✅ |
| block_elediaai_tutor | 0.19.1 | BETA | ✅ |
| local_literag | 0.6.2 | BETA | ✅ |
| local_elediaai_questiongen | 0.3.14 | ALPHA | ✅ |
| local_elediaai_strategy | 0.1.3 | ALPHA | ✅ (`core_ai`-Link) |
| local_elediaai_core | 0.2.9 | ALPHA | ✅ |
| local_elediaai_tactics | 0.1.8 | ALPHA | ✅ (`core_ai`-Link) |
| mod_aifeedback | 0.7.1 | ALPHA | ✅ |
| mod_aichat | 0.2.2 | ALPHA | ✅ |
| block_elediaai_chat | 0.2.3 | ALPHA | ✅ |
| block_elediaai_tactics | 0.1.2 | ALPHA | ✅ |
| local_elediaai_selfstudy | 0.2.1 | BETA | ✅ (`core_ai`-Link) |
| local_elediaai_teachertools | 0.4.4 | ALPHA | ✅ (`core_ai`-Link) |
| qbank_elediaai_questiongen | 0.1.1 | ALPHA | ✅ |

---

## Checkliste

- [x] T0.1 — Suite-Registry zurück auf Legacy + `local_eledia`(+premium) löschen — 2026-07-06 verifiziert (Ziel bereits über den `local_elediaai_core`+`local_elediaai_core_premium`-Pfad erreicht statt via Foundation-Umbau, PR #5 verworfen: Ordner `local/eledia`+`local/eledia_premium` entfernt; Registry kanonisch in `local_elediaai_core\feature\registry` (Provider-Namespace `elediaai_core`, keine Deprecation/Delegation); keine `local_eledia`-Referenzen mehr in Code/version.php/scripts/CI (verbleibende 4 Treffer sind historische Doku-Notizen); PHPUnit grün: ai 33, questiongen 47, tactics 27, strategy 57. Nebenbefund gefixt: tactics `draft_writer_test` verglich `FORMAT_HTML` (String '1') via `assertSame` gegen `(int)`-Cast → jetzt treiberunabhängiges `assertEquals`.)
- [x] T0.2 — Doppelte historische AI-Tutor-Blöcke aus Bundle nehmen — 2026-07-08 verifiziert (die historischen Duplikate existieren nicht mehr; der aktuelle Deploy prüft den kanonischen `block_elediaai_tutor`)
- [x] T1.1 — Privacy-Provider `local_elediaai_strategy` — 2026-07-02 (Provider existierte bereits; 7 Privacy-Tests ergänzt, grün)
- [x] T1.2 — Testabdeckung qbank/aichat/aifeedback — 2026-07-06 (qbank Privacy-null-provider-Test; mod_aichat lib.php Lifecycle + Reset-Cascade; mod_aifeedback lib.php Lifecycle + source-data-Normalisierung + Release-Notification via Message-Sink; +21 Tests, lokal grün in elediaai-moodle-1: qbank 4, aichat 19, aifeedback 23)
- [x] T1.3 — CI Test-/Lint-Workflow (moodle-plugin-ci) — 2026-07-02 (.github/workflows/moodle-ci.yml, 16-Plugin-Matrix, phplint/phpunit hart + Style soft; Verifikation mit erstem Push; tactics wegen externem local_lernhive ausgenommen; aiagent + coursegen vorab phpcs-clean gefixt)
- [x] T1.4 — Accessibility-Audit (BFSG) — 2026-07-08 (Bericht `accessibility-audit-2026-07-08.md`; kritische statische Befunde in Strategy Phase 1 und Questiongen Progress behoben; Browser-/axe-Pass als Follow-up)
- [ ] T1.5 — Versionsnummern/Maturity angleichen
- [x] **KG.1 — Plugin-Gerüst `local_elediaai_coursegen`** (Kursgenerator, hohe Prio) — 2026-06-30
- [x] **KG.2 — Lehrplan-Datenquelle + Auswahl-UI** — 2026-07-02 (source-Interface + `data/mathematik.json`, 2-Schritt-UI, Payload an Pipeline; DevFlow-Docs im Plugin)
- [x] **KG.3 — KI-Generierungs-Pipeline** — 2026-07-02 (proposal_generator/-validator, Schema v1, Repair-Retry, 11 Mock-AI-Tests, Live-Flow verifiziert)
- [x] **KG.4 — Kompetenz-Mapping** — 2026-07-02 (competency_mapper, idempotent per idnumber, 7 Tests; Modul-Zuordnung in KG.5)
- [x] **KG.5 — Teacher-Review + Schreiben in den Kurs** — 2026-07-02 (course_writer: beide Zielpfade, Duplikat-Schutz, graceful Kompetenz-Mapping; 8 Tests + Live-Write; Behat → KG.7)
- [x] **KG.6 — Quizfragen via questiongen (MVP-Bestandteil)** — 2026-07-02 (quiz_filler, Kategorie im Quiz-Kontext, graceful je Quiz; Upstream-Transaktionsbug in questiongen gefixt)
- [x] **KG.7 — Tests & Doku** — 2026-07-02 (43 PHPUnit + 4 Behat-Szenarien grün, README de/en; CI-Eintrag folgt mit T1.3)
- [ ] T3 — Weitere Ideen (Lernpfad geparkt · MCP-Agent zurückgestellt · siehe FEATURE-KONZEPTE.md)

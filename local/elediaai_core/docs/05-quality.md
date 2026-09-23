# Qualitaet

## Meta

Dieses Dokument erfasst Bugs und Tests.

Es enthaelt:

- Bugs (`bugXX`) mit Severity
- Tests (`testXX`) mit Bezug auf Akzeptanzkriterien aus `01-features.md` (`featXX.ACyy`)
- lokale Verifikationslage, manuelle QA-Checkliste, Accessibility/UX- und Privacy-Status

---

## Bugs

### Severity-Skala

| Severity | Bedeutung | Reaktion |
| --- | --- | --- |
| S1 | Kritisch: Kernfunktion kaputt, Datenverlust, Sicherheitsluecke | Sofortiger Hotfix |
| S2 | Schwer: Feature unbrauchbar, kein guter Workaround | laufende Iteration |
| S3 | Mittel: eingeschraenkt, Workaround vorhanden | naechstes Release |
| S4 | Gering: kosmetisch oder Edge Case | Backlog |

### Vorlage

```text
### bugXX Kurztitel
Feature:  featXX
Severity: S1 | S2 | S3 | S4
Status:   open | in_progress | fixed | wontfix
Linked:   taskXX, testXX

Beschreibung
...

Reproduktion
1. ...

Erwartet / Tatsaechlich
...
```

### Aktueller Bug-Stand

### bug01 `core_ai` OpenAI-Provider greift hart auf `system_fingerprint` zu

Feature: feat05 (qtype_aitext-Submission)
Severity: S2
Status: mitigated (Deploy-Hotfix in `playbooks/deploy.sh`)
Linked: 04-tasks.md task06

**Beschreibung**
Moodles OpenAI-Provider (`public/ai/provider/openai/classes/process_generate_text.php`) las `system_fingerprint` ohne Null-Check. GPT-5/GPT-5-mini-kompatible Antworten ohne dieses Feld erzeugten `Undefined property: stdClass::$system_fingerprint` und brachen qtype_aitext-Submissions ab.

**Reproduktion**
1. Provider in `core_ai` auf `gpt-5*` stellen.
2. Quiz mit einer `qtype_aitext`-Frage abgeben.

**Tatsaechlich**
Mitigated: Der Deploy-Patch setzt den Zugriff idempotent auf `$bodyobj->system_fingerprint ?? null`. Plan: entfernen, sobald Moodle upstream null-safe ist. Dies ist ein Core-Bug ausserhalb dieses Plugins; nur die KI-Suite ist als Konsument betroffen.

---

## Tests

### Vorlage

```text
### testXX Kurztitel
Feature:            featXX
Akzeptanzkriterium: featXX.ACyy
Typ:                manuell | automatisiert (PHPUnit/Behat)
Status:             pending | pass | fail | blocked

Schritte / Erwartetes Ergebnis / Beobachtetes Ergebnis
```

### test01 Registry-Discovery + Sichtbarkeit

Feature:            feat02, feat06, feat07
Akzeptanzkriterium: feat02.AC01, feat02.AC02, feat02.AC03, feat06.AC01, feat07.AC01
Typ:                automatisiert (PHPUnit)
Status:             pass

Datei: `tests/registry_test.php`

Deckt ab (16 Tests, Stand 2026-08-14): `all()` findet die Roadmap-Descriptors; Roadmap-Stubs sind `comingsoon`; Live-`translate` ersetzt den Stub; Audit-Descriptor ist ohne Capability versteckt und fuer Admins sichtbar; Per-Feature-Toggle (`feature_<id>_enabled = 0`) versteckt die Karte; `is_enabled()` defaultet auf `true`; `set_enabled()` persistiert; alphabetische Reihenfolge nach Name; Descriptor defaultet auf Free-Tier; ein installierbarer Descriptor kann modelliert werden. Fehlerhafte Provider werden isoliert und mit Developer-Diagnose gemeldet: Exceptions, falsches Interface, unlesbare Provider-Datei und gemischte gültige/ungültige Descriptor-Werte. Ein fehlerhafter Provider unterdrückt keinen gesunden Provider.

### test02 Audit-Entity Spalten + Reporting

Feature:            feat05
Akzeptanzkriterium: feat05.AC01, feat05.AC02, feat05.AC04, feat05.AC05, feat05.AC06
Typ:                automatisiert (PHPUnit)
Status:             pass

Datei: `tests/audit_entity_test.php`

Deckt ab (14 Tests, Stand 2026-07-31): erweiterte Spalten registriert (Actor, Action, Context, Prompt, Response, Provider, Model, Status, Tokens); Core-Spalten vererbt; Entity-Name `ai_action_audit`; `report_access_exception` ohne Capability (`test_audit_report_requires_capability`); End-to-end-Render zeigt Prompt + Response im HTML; `format_success_icon()` rendert ein Häkchen-/X-Icon (inline Lucide-SVG) und versteckt im Download den Fehlertext, wenn `audit_show_error` deaktiviert ist; `format_tokens_cell()` kombiniert/fällt auf `—`; `format_provider_name()` kuerzt gaengige Provider-Labels; `format_provider_model()` kombiniert Provider + Modell; `audit_config`-Defaults + Sichtbarkeits-Toggles; Bericht honoriert die Sichtbarkeitseinstellungen; Quick-Filter nutzen gueltige Reportbuilder-Parameter; Teacher-Modus filtert auf eigene Kurse (`test_audit_report_teacher_mode_filters_to_own_courses`).

### test03 Stale-/Regenerate-Fingerprint-Helper

Feature:            feat09
Akzeptanzkriterium: feat09 (Hash-Stabilitaet + Rueckwaertskompatibilitaet)
Typ:                automatisiert (PHPUnit)
Status:             pass

Datei: `tests/stale_marker_test.php`

Deckt ab (6 Tests): Ein-Element-Hash gleich `sha1(trim(...))` (byte-kompatibel zum Alt-`mod_aifeedback`-Hash); deterministisch + getrimmt; bei mehreren Teilen zaehlen Reihenfolge und Grenzen (keine Kollision); Keys werden ignoriert; leerer gespeicherter Hash ist nie stale; Aenderungen werden erkannt.

### test04 Launcher-Smoke-Pfad (Behat)

Feature:            feat01, feat03, feat05
Akzeptanzkriterium: feat01.AC01, feat01.AC02, feat03.AC01, feat05.AC03
Typ:                automatisiert (Behat, ohne `@javascript`)
Status:             pass

Datei: `tests/behat/launcher_smoke.feature`

Deckt ab (3 Szenarien): Admin oeffnet die Suite → Free-text-Karte → Settings-Cog → zurueck zur Uebersicht; Audit-Tab fuer Admins vorhanden; Audit-Tab fuer regulaere Nutzer nicht in der Section-Nav. Reine Links, daher kein `@javascript`.

### test05 Suite-Seiten im Plugin-Shell (Behat)

Feature:            feat01, feat03, feat04, feat05, feat06
Akzeptanzkriterium: feat03.AC01, feat03.AC03, feat04.AC02, feat05.AC03, feat06.AC01, feat06.AC02
Typ:                automatisiert (Behat)
Status:             pass

Datei: `tests/behat/launcher_pages.feature`

Deckt ab (7 Szenarien): Dashboard mit Feature-Karten (Questions, Free text, Chat, Translate, AI Feedback); Free-text-Erklaerseite („Available", Overview, Settings); Coming-Soon (`tutor`) ohne „Open feature"-Link; Live-`translate` mit „Open feature"-Link statt „Coming soon"; `aifeedback`-Erklaerseite ohne Open-Link (kein eigener `launchurl`); Audit-Tab + Reportseite fuer Admins; Audit-Tab fuer regulaere Nutzer nicht sichtbar.

Custom-Steps: `tests/behat/behat_local_elediaai_core.php` (`I open the AI Suite`, `I open the AI Suite feature "<id>"`, `I open the AI Suite audit`).

### test22 Token-Quota-Manager (PHPUnit)

Feature:            feat12 Token-Quota (01-features.md)
Typ:                automatisiert (PHPUnit)
Status:             pass

Datei: `tests/quota_manager_test.php`

Deckt ab (14 Tests, Stand 2026-08-14): Nutzungszähler werden in Stunden-,
Tages- und Monats-Fenstern erfasst
(`test_records_hourly_daily_and_monthly_token_windows`);
`assert_can_request()` erzwingt Student-Limits für Tages- und Monatsfenster
(`test_assert_can_request_enforces_student_limit`,
`test_assert_can_request_enforces_student_month_limit`); Teacher-Rollen nutzen
den Teacher-Bucket (`test_teacher_role_uses_teacher_bucket`). Die atomare
Reservierung blockt parallele Preflight-Pruefungen am Limit
(`test_reserve_blocks_parallel_preflights`), laesst das exakte Limit noch durch
(`test_reserve_exact_limit_passes`), rechnet den Completion-Puffer ein
(`test_reserve_includes_completion_buffer`), rollt bei einem gescheiterten
Fenster die bereits reservierten zurueck
(`test_reserve_partial_rollback_across_windows`), bucht die Reserve nach der
Antwort auf die tatsaechlichen Tokens um (`test_commit_corrects_to_actual`)
und gibt sie im Fehlerfall wieder frei (`test_release_frees_reserved_tokens`).
Fenster- und Rollenwechsel zwischen Reservierung und Abschluss lösen den
ursprünglichen Handle korrekt auf
(`test_release_uses_original_window_and_role_bucket`) und buchen Nutzung in das
ursprüngliche Zeitfenster (`test_commit_charges_original_window`).
Bildguthaben rechnet nach Groesse und Anzahl
(`test_image_cost_uses_size_and_count_defaults`) und laeuft durch denselben
Reservierungs-/Verbuchungspfad
(`test_image_reservation_and_commit_use_image_credits`).
Bezieht sich auf `classes/local/quota_manager.php` (Tabelle
`local_elediaai_core_usage`).

**Nicht abgedeckt:** `prune()` und `delete_for_user()` haben keinen eigenen
Test; `prune()` haengt ausserdem an keinem Scheduled Task (siehe
`03-dev-doc.md`, Abschnitt „Token-Quota").

### test23 LLM-Session-Composer (PHPUnit) — **entfallen am 05.09.2026**

Mit feat13 entfernt: der Hook `lernhive_session_compose` wurde nur von
`local_lernhive` bedient, und dieses Plugin gibt es nicht.

### test24 Content-Extractor für Grounding (PHPUnit)

Feature:            feat02 / Grounding
Typ:                automatisiert (PHPUnit)
Status:             pass

Datei: `tests/content_extractor_test.php`

Deckt ab (3 Tests, Stand 2026-07-31): `extract_page()` strippt HTML
(`test_extract_page_strips_html`); die Liste unterstützter Module enthält
`page`, schließt `forum` aus (`test_list_supported_includes_page_excludes_forum`);
nicht unterstützte Module werfen eine Exception (`test_unsupported_module_throws`).
Bezieht sich auf `classes/content/content_extractor.php`.

### test25 Kontext-Bundle (PHPUnit)

Feature:            feat02 / Kontext-DTOs
Typ:                automatisiert (PHPUnit)
Status:             pass

Datei: `tests/context_bundle_test.php`

Deckt ab (3 Tests, Stand 2026-07-31): kombinierter Text nutzt Item-Titel und
überspringt leeren Text (`test_combined_text_uses_item_titles_and_skips_empty_text`);
der Fingerprint ist stabil bei äquivalenter Metadaten-Reihenfolge und ändert
sich bei Textänderung (`test_fingerprint_is_stable_for_equivalent_metadata_order`,
`test_fingerprint_changes_when_text_changes`). Bezieht sich auf
`classes/context/context_bundle.php` und `classes/context/context_item.php`.

### test26 Feature-Grid-Renderer (PHPUnit)

Feature:            feat01, feat03
Typ:                automatisiert (PHPUnit)
Status:             pass

Datei: `tests/feature_grid_test.php`

Deckt ab (5 Tests, Stand 2026-07-31): Audience-Badge unterdrückt bei
deaktivierter Option; Settings-Zahnrad respektiert Option und Capability;
leere Liste rendert korrekt; Primär-Action nutzt die Launch-URL; ohne Mapping
defaultet ein Feature auf die Teacher-Audience. Bezieht sich auf
`classes/output/feature_grid.php`.

### test06 Audit-Tabelle: Augen-Icon-Modal (manuell A11y)

Feature:            feat05
Akzeptanzkriterium: feat05.AC04
Typ:                manuell
Status:             open

```
Given:  Admin auf /local/elediaai_core/audit_technical.php mit >=1 Eintrag
When:   Klick auf das Augen-Icon in der Prompt-Spalte
Then:   ModalCancel oeffnet sich, Body zeigt Volltext in <pre> (Whitespace + Umbrueche erhalten)
        Tastatur-Fokus springt in den Modal, Esc/Close schliessen, Fokus kehrt auf das Icon zurueck
```

PHPUnit-Teil (Button + `<template>`-Payload) ist in test02 abgedeckt.

### test07 Audit-CSV-Download bleibt textuell (manuell)

Feature:            feat05
Akzeptanzkriterium: feat05.AC02
Typ:                manuell
Status:             open

```
Given:  Admin auf /local/elediaai_core/audit_technical.php
When:   CSV-Download ueber den Reportbuilder
Then:   Prompt + Antwort als reiner Text (max. 240 Zeichen), keine HTML-Button-Markup
        Erfolg-Spalte enthaelt localisiertes "Ja"/"Nein"
        Tokens-Spalte in Form "420 / 180"
        Deaktivierte Bestandteile (audit_show_*) bleiben leer
```

Sichtbarkeits-Kopplung des Fehlertexts ist in test02 PHPUnit-seitig abgedeckt.

### test08 Audit-Einstellungen wirken (manuell)

Feature:            feat05
Akzeptanzkriterium: feat05 (Audit-Settings, 04-tasks.md task06)
Typ:                manuell
Status:             open

```
Given:  Admin auf /local/elediaai_core/audit_settings.php
When:   audit_access auf "adminonly", audit_anonymize_users an, einzelne audit_show_* aus, speichern
Then:   Nicht-Admin (mit moodle/ai:viewaiusagereport) bekommt keinen Zugang mehr
        Audit-Tabelle zeigt Rollen-/Kontext-Label statt Klarnamen
        Deaktivierte Spalten zeigen keine Inhalte (Anzeige + CSV)
```

### test09 Schnellfilter im technischen Audit (manuell)

Feature:            feat05
Akzeptanzkriterium: feat05.AC07
Typ:                manuell
Status:             open

```
Given:  Audit mit gemischten Aktionen + mindestens einem Fehler
When:   Schnellfilter "Nur Fehler" / "Nur Teilnehmer:innen" / je Aktionstyp anklicken
Then:   Tabelle und Export honorieren die Auswahl; nur allowlistete quick-Werte greifen
        Fehlerzeilen sind rot hervorgehoben (lh-audit-row--failed)
```

### test10 Didaktisches Audit (manuell)

Feature:            feat05
Akzeptanzkriterium: feat05 (didaktische Sicht)
Typ:                manuell
Status:             open

```
Given:  Mehrere summarise_text/explain_text-Aktionen in unterschiedlichen Kontexten
When:   /local/elediaai_core/audit_didactic.php oeffnen
Then:   Top-Kontexte nach Anzahl sortiert, mit Aufschluesselung Zusammenfassungen/Erklaerungen
        Leerer Zustand, wenn keine solchen Aktionen vorliegen
        limit zwischen 10 und 100 begrenzt
```

### test11 Pluggable Discovery: neues Sub-Plugin (manuell)

Feature:            feat02
Akzeptanzkriterium: feat02.AC01
Typ:                manuell
Status:             open

```
Given:  Frisch installiertes Sub-Plugin mit *\elediaai_core\feature_provider
When:   Cache-Purge + Reload der KI-Suite
Then:   Neuer Descriptor erscheint in der Uebersicht ohne Code-Aenderung am Umbrella-Plugin
```

### test12 Plugin-Shell-Konsistenz auf allen Seiten (manuell)

Feature:            feat01, feat03, feat04, feat05
Akzeptanzkriterium: Plugin-Shell-Wrap
Typ:                manuell
Status:             open

```
Given:  Admin auf index/feature/help/audit/audit_technical/audit_didactic/audit_settings
When:   Seite rendert (theme_lernhive aktiv)
Then:   Zone-A "AI Suite | <Kontext>", Hilfe-Button vorhanden, Section-Nav mit korrektem aktiven Tab
        Keine doppelten Body-Actions, keine Boost-Site-Heading darueber
```

Pruefpfad: dev.lernhive.de.

### test13 Premium-Policy-Gate (automatisch + manuell)

Feature:            feat07b
Akzeptanzkriterium: feat07b.AC01..AC04
Typ:                PHPUnit + lokal per Moodle-CLI
Status:             pass

```
Given:  local_elediaai_core_premium ist installiert
When:   grant_tutorpremium leer/0 ist
Then:   registry::all() kennt tutorpremium, registry::visible() zeigt es nicht

When:   grant_tutorpremium = 1 ist
Then:   registry::visible() zeigt tutorpremium fuer berechtigte Nutzer:innen

When:   zusaetzlich feature_tutorpremium_enabled = 0 ist
Then:   registry::visible() blendet tutorpremium trotz Premium-Grant aus
```

Beobachtet am 2026-06-30 lokal:

```text
all=yes visible_without_grant=no
visible_with_grant=yes
visible_with_toggle_off=no
```

### test14 Lokales Deploy mit externem `local_lernhive`

Feature:            lokale Entwicklungsarchitektur
Akzeptanzkriterium: Suite-Repo vendort `local_lernhive` nicht, lokale Instanz kann
                    es trotzdem sauber installieren
Typ:                Shell/CLI
Status:             pass

Seit 2026-07-12 abgelöst: `local_lernhive` und alle Custom-Plugins werden
per Bind-Mount aus dem Workspace bereitgestellt (`infra/docker-compose.local.yml`),
nicht mehr per `docker cp` injiziert. Aktuelles Szenario:

```
Given:  ../Lernhive/lernhive ist ausgecheckt (Quelle des local_lernhive-Mounts)
When:   ./scripts/local-deploy.sh deploy ausgeführt wird
Then:   alle Custom-Plugins sind über die Bind-Mounts präsent
        Moodle-Upgrade und Cache-Purge laufen ohne Downgrade-Fehler
```

Beobachtete lokale DB-Versionen: `local_lernhive=2026062408`,
`local_elediaai_tactics=2026062800`, `local_elediaai_strategy=2026062800`,
`block_elediaai_tactics=2026062800`.

### test15 Kursgenerator-Scaffold meldet sich in der Suite an

Feature:            Feature-Discovery
Akzeptanzkriterium: Ein separates Plugin kann den Coming-soon-Stub der Suite
                    durch eine Live-Kachel ersetzen
Typ:                PHP-Lint + lokaler Moodle-CLI-Check
Status:             pass

```
Given:  local_elediaai_coursegen ist installiert
When:   local_elediaai_core\feature\registry::all() ausgelesen wird
Then:   coursegen kommt von local_elediaai_coursegen
And:    coursegen ist nicht mehr comingsoon
And:    launchurl zeigt auf /local/elediaai_coursegen/index.php
```

Beobachtet am 2026-06-30 lokal:

```text
coursegen=local_elediaai_coursegen:comingsoon=no:launch=http://localhost:8080/local/elediaai_coursegen/index.php
visible-as-admin=aifeedback,tactics,activityfilter,audit,aichat,tutor,questiongenerator,strategyhelper,coursegen,tutorpremium,translate
```

### test16 H5P Author meldet sich in der Suite an

Feature:            Feature-Discovery
Akzeptanzkriterium: Der H5P Author erscheint als eigenes AI-Suite-Feature und
                    fuehrt ohne Kurskontext auf einen Kurs-Picker
Typ:                PHP-Lint + lokaler Moodle-CLI-Check
Status:             pass

```
Given:  local_elediaai_h5pauthor ist installiert
When:   local_elediaai_core\feature\registry::all() ausgelesen wird
Then:   h5pauthor kommt von local_elediaai_h5pauthor
And:    launchurl zeigt auf /local/elediaai_h5pauthor/index.php
```

Beobachtet am 2026-06-30 lokal:

```text
h5pauthor all=local_elediaai_h5pauthor:http://localhost:8080/local/elediaai_h5pauthor/index.php visible=yes
```

Hinweis: Lokal waren `H5P.Dialogcards` und `H5P.Blanks` noch nicht in
`h5p_libraries` installiert; der Wizard kann daher erst nach Installation der
H5P-Inhaltstypen End-to-End publizieren.

### test17 Accessibility-Audit UI-Plugins

Feature:            Suite-Qualitaet / T1.4
Akzeptanzkriterium: BFSG-relevante statische UI-Huerden sind bekannt und
                    kritische Low-Hanging-Fruits behoben
Typ:                Code-Review + statische Checks
Status:             partial pass (2026-07-08)

Bericht: `docs/accessibility-audit-2026-07-08.md`

Behoben: Strategy Phase-1-Zielkarten sind semantisch als Radiogroup/Radio
markiert; Questiongen-Progress-Aktionen sind echte Buttons statt klickbarer
Links. Offen: Browser-/axe-Pass auf der laufenden Instanz.

---

## Cross-Plugin-Tests (kurze Verlinkung)

Diese Tests leben **nicht** in `local_elediaai_core`, sind aber Teil der Suite-Qualitaetszusicherung (verifiziert per Code-Suche):

- `block_elediaai_chat/tests/thread_store_test.php` — Thread-Persistierung (Block-Variante).
- `mod_aichat/tests/thread_store_test.php` — Thread-Persistierung (Activity-Variante, scoped per `aichatid`); nutzt den geteilten `stale_marker` fuer System-Prompt-Drift (Tabelle `aichat_thread`).
- `mod_aifeedback/tests/submission_sync_test.php` — KI-Feedback-Generierungspipeline (lokaler Fallback, Regenerate, `is_stale` ueber `stale_marker`).
- `mod_aifeedback/tests/submission_sync_test.php::test_sync_creates_draft_from_assignment_online_text` — Assignment-Abgaben als Quelle: `assign_submission` + Online-Text werden in einen Queue-Entwurf synchronisiert.
- `mod_aifeedback/tests/privacy_provider_test.php` — Privacy-Provider (Export/Delete/Userlist).
- `mod_aifeedback/tests/behat/teacher_workflow.feature` — Course-End-to-End (sync→review→edit→release→Student-Sicht, Regenerate/Stale, Released-Schutz).
- `local_elediaai_questiongen/tests/job_store_test.php` — Job-State-Machine + Review-State.
- `local_elediaai_questiongen/tests/review_item_test.php` — Review-Parser, XML-Reparatur, Structured-Review-XML-Builder.
- `local_elediaai_questiongen/tests/input_fingerprint_test.php` — Stale-Hash-Stabilitaet (eigener Fingerprint fuer Kursinhalte).
- `local_elediaai_questiongen/tests/prompts_test.php` — Fragetyp-Prompting.
- `local_elediaai_questiongen/tests/upload_text_extractor_test.php` — Upload-Text-Extraktion.
- `local_elediaai_tactics/tests/` — Action-Service, Content-Extraction, Course-Capability-Listing, Draft-Writer, Prompt-Presets und Quality-Checklist.
- `qtype_aitext/tests/` — `question_test.php`, `question_type_test.php`, `restore_test.php`, `aitext_repeated_restore_test.php`, `upgradelib_test.php`; Behat: `backup_and_restore`, `edit`, `edit_min_max_fields`, `export`, `import`, `preview`.
- `local_elediaai_core_premium/tests/policy_test.php` — Premium-Policy-Provider, Grant-Default, Sichtbarkeit und Per-Feature-Toggle.

---

## Architektur-/Repo-Cleanup-Checks

### test19 Externe Plugin-Grenzen

Feature:            feat02, feat04, feat06
Typ:                manuell / Code-Review
Status:             pass (2026-06-30)

Erwartung:
- `local_lernhive`, Translate, LiteRAG, RAGIngest und MCP werden nicht in dieses
  Repo kopiert.
- Die Suite funktioniert ohne diese Plugins und zeigt Missing-/Install-Hinweise.
- Installierte externe Plugins koennen sich per `*\elediaai_core\feature_provider`
  anmelden und muessen ohne Suite weiter eigenstaendig funktionieren.

### test20 ActivityFilter nur im Activity-Chooser

Feature:            feat04
Typ:                manuell / Code-Review
Status:             pass (2026-06-30)

Erwartung:
- Kein Eintrag `Open AI activity search` im Kurs-Plus-Menue.
- Die KI-Aktivitaetssuche erscheint im Moodle Activity-Chooser unter
  „Interaktiver Inhalt".
- Code-Suche findet keine alte `newContentDropdown`-/`open-activityfilter`-
  Injektion mehr.

### test21 Suite-Karten mit Icon-Action

Feature:            feat04
Typ:                manuell / Code-Review
Status:             pass (2026-06-30)

Erwartung:
- Feature-Karten nutzen rechts unten eine Icon-Action mit `title` und
  `aria-label`, nicht mehr den breiten Textbutton „Funktion oeffnen".
- Feature-Detailseiten behalten ihre erklaerende Start-/Install-Aktion.

### test29 CI-Matrix-Kompatibilitaet

Feature:            repositoryweite Moodle-CI-Matrix
Akzeptanzkriterium: Plugin-Mindestversionen und obere `supported`-Grenzen
Typ:                automatisiert (PHP)
Status:             pass

Datei: `scripts/ci-matrix-test.php`

Deckt ab: `local_elediaai_subscription` wird nur auf Moodle 5.2 erzeugt;
`mod_elli`-haltige Gruppen werden auf Moodle 4.5/5.1, nicht auf 5.2 erzeugt;
die kompatible Core-Gruppe bleibt auf allen drei konfigurierten Branches
vertreten. Der Test wird im Forgejo-Changes-Job vor der Matrixausgabe gestartet.

---

## Teststrategie

- **PHPUnit fokussiert die Logik mit Verzweigungen:** Discovery + Sichtbarkeit (`registry`), Audit-Entity-Spalten + Anzeige/Download-Pfad + Sichtbarkeits-Kopplung (`ai_action_audit`/`audit_config`), Stale-Fingerprint (`stale_marker`). Reine Render-/Shell-Wrapper werden nicht unit-getestet.
- **Behat fokussiert den Klickpfad:** Launcher → Uebersicht → Feature-Karte → Settings-Cog → zurueck, sowie die Capability-Sichtbarkeit des Audit-Tabs. Beide Behat-Dateien laufen ohne `@javascript`, weil Launcher, Karten und Cog reine Links sind.
- **Manuelle QA deckt das ab, was nur in der laufenden Instanz pruefbar ist:** Modal-Fokusverhalten, CSV-Download-Inhalt, Audit-Einstellungs-Effekte, Schnellfilter, didaktische Aggregation und die Plugin-Shell-Optik im echten Theme.
- **Audit-Coverage ist strukturell automatisch:** Da jede `core_ai`-Aktion in `ai_action_register` landet (adr04), muss kein Sub-Plugin eigenes Logging mittesten.

---

## Manuelle QA-Checkliste

- [ ] Launcher-Pille erscheint fuer eingeloggte Nutzer, nicht fuer Gaeste, nicht in login/popup/embedded/maintenance.
- [ ] Uebersicht zeigt alle berechtigten Funktionen alphabetisch; Empty-State bei null sichtbaren Funktionen.
- [ ] Coming-Soon-Karten ohne Live-Start; Live-Karten mit Icon-Action, Feature-Detailseiten mit erklaerender Startaktion.
- [ ] Feature-Seite: Status-Pille korrekt, keine doppelten Body-Actions, Hilfe-Icon fuehrt auf das richtige Sub-Plugin-Handbuch.
- [ ] Settings-Cog nur bei in-Shell-`configurl` + Site-Config; `/admin/`-URLs nicht als Cog-Ziel.
- [ ] Audit-Tab nur bei Berechtigung; Zugriff per `audit_access` umschaltbar (corecap / adminonly).
- [ ] Audit-Uebersicht: Kennzahlen plausibel; zwei Pfade fuehren auf technisch/didaktisch.
- [ ] Technisches Audit: Augen-Modal, Erfolg-Icon, kombinierte Tokens, Schnellfilter, rote Fehlerzeilen, CSV-Download textuell.
- [ ] Audit-Einstellungen: Anonymisierung und Show-Toggles wirken in Anzeige und Export.
- [ ] Per-Feature-Toggle versteckt die Karte; direkter Feature-Aufruf zeigt Not-Found-Notification.
- [ ] Neu installiertes Sub-Plugin erscheint nach Cache-Purge ohne Code-Aenderung am Umbrella.

---

## Bekannte Limitierungen / Risiken

- **Audit nur so vollstaendig wie `core_ai`:** Aktionstypen, die nicht in `ai_action_register` + den drei Detailtabellen (`generate_text`, `summarise_text`, `explain_text`) landen, erscheinen nicht im Audit. Neue Core-Aktionstypen erfordern eine Erweiterung der Entity-Joins.
- **`completiontoken` (Singular):** Die Token-Aggregation haengt am Core-Feldnamen; ein Umbenennen upstream wuerde die Token-Summe stillschweigend auf 0 setzen.
- **Anonymisierung ist nur Anzeige-Ebene:** Moodle Core speichert die echte Nutzer-ID weiter in seinen `core_ai`-Tabellen.
- **bug01 (Core-OpenAI-Fingerprint):** nur per Deploy-Hotfix mitigiert; Wiederkehr moeglich, falls der Patch nicht angewendet ist.
- **Idempotenz-Annahmen bei Schwester-Plugins** (z. B. doppeltes Sync in `mod_aifeedback`) liegen ausserhalb dieses Plugins, beeinflussen aber die Audit-Zahlen.
- **PHP-Version lokal:** Behat/PHPUnit-Laeufe haengen an einer Moodle-kompatiblen PHP-Version (Docker-Stand PHP 8.4); zu neue lokale PHP-Versionen blockieren `moodle-plugin-ci`-Composer-Locks.

---

## Accessibility / UX-Hinweise

- Icons tragen `aria-hidden="true"` und werden von Screenreader-Text (`.sr-only` / `.accesshide`) begleitet (Action-Pille, Augen-Button, Erfolg-Icon, Settings-Cog).
- Die Augen-Vorschau oeffnet ein `core/modal_cancel`; der Volltext steht in `<pre>` mit `white-space: pre-wrap` und `overflow-wrap: anywhere`, sodass lange Prompts keinen horizontalen Scroll erzeugen. Fokus-Rueckkehr auf das ausloesende Icon ist manuell zu verifizieren (test06).
- Die Section-Nav nutzt `aria-current="page"` fuer den aktiven Tab und ein `aria-label` fuer die Nav-Region; tote Links werden vermieden (Audit-Tab nur bei Berechtigung).
- Fehlerzeilen sind nicht nur farblich (rot), sondern auch ueber das Erfolg-Icon und den Klassen-Hook erkennbar; Farbe ist nicht das einzige Signal.

---

## Privacy-Status

- `classes/privacy/provider.php` implementiert `\core_privacy\local\metadata\null_provider`: die KI-Suite-Shell speichert selbst **keine** personenbezogenen Daten.
- Die Begruendung steht in der Sprachzeichenkette `privacy:metadata`: einzelne KI-Funktionen und die Moodle-Core-`core_ai`-Protokollierung deklarieren ihre eigenen Privacy-Daten.
- Das Audit zeigt personenbezogene Daten (Prompts, Nutzernamen) lediglich an; gespeichert werden sie in Core-Tabellen. Die Anonymisierungs- und Show-Einstellungen reduzieren die Anzeige, ersetzen aber keine Core-Privacy-Loeschung.
- Offen (04-tasks.md task06): Privacy-API-Anbindung fuer Export/Delete der *lokal sichtbaren* Audit-Erweiterungen und klare Dokumentation, dass die Basistabelle aus Moodle Core stammt.

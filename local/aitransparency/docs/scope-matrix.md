# AP0 — Scope-Matrix `local_aitransparency`

Bestandsaufnahme für Art. 50 EU AI Act (SUI-568). Eine Zeile je Plugin mit
Ausgabetyp, betroffenem Absatz, heutigem Ausgabepfad im Code und Aufwand.

**Verifikation:** Plugin-Code gegen `eledia.ai@dev/sven/SUI-568` (Basis
`origin/main` `f01aa99`), Stand 2026-08-03. Moodle-Core-Fakten gegen die lokale
Core-Installation `.../moodle/public` (Core ist in diesem Monorepo **nicht**
vendored — `public/ai/` enthält hier nur `provider/eledia`).

Plugin-Namen auf Stand nach dem `elediaai_*`-Rename (SUI-560, inzwischen in
`main`): z. B. `local_elediaai_core` (ehem. `local_lernhive_ai`). Der externe
Core `local_lernhive` (`@lernhive`) behält seinen Namen.

Legende Absatz: **50/1** = interaktive KI, Nutzer muss KI erkennen (sichtbarer
Hinweis) · **50/2** = maschinenlesbare Markierung synthetischer Ausgabe.
Aufwand: S/M/L (Integrationsaufwand für Marker/Provenance im jeweiligen Plugin).

---

## 1. Die Chokepoints — zentrale Erkenntnis

Der Handover (4.1) geht von **einem** Chokepoint aus (`core_ai` →
`aiprovider_eledia`) mit nur zwei Nicht-`core_ai`-Ausnahmen (`ragingest`,
`elediamcp`). AP0 bestätigt den Haupt-Chokepoint, findet aber einen **zweiten,
nutzerseitigen und nicht erfassten** LLM-Pfad:

| # | Chokepoint | Datei:Methode | Deckt ab | Provenance heute |
|---|------------|---------------|----------|------------------|
| 1 | `aiprovider_eledia` (core_ai-Provider) | `ai/provider/eledia/classes/abstract_processor.php::query_ai_api()` (L88); Erfolg + Quota bei `quota_manager::commit()` **L175** | alle `core_ai`-Textaktionen, sofern eLeDia-Provider aktiv | nur Quota, keine Provenance |
| 1b | `core_ai` generisch (Fremd-Provider) | `ai/classes/manager.php::store_action_result()` → `ai_action_register` | `core_ai`-Aktionen über openai/gemini/… | Register-Eintrag ohne Dateiref |
| 2 | **`local_literag` (eigener LLM-Client, NICHT core_ai)** | `local/literag/classes/local/llm/client.php::complete()` (L81) / `chat()` (L58); Rückgabe an Nutzer über `.../mcp/tools/tutor_chat.php::handle()` (L312) | **`mod_elli`**-Chat | **keine** — kein Quota, keine Provenance, kein `core_ai` |

**Konsequenz für AP2:** Die AP2-DoD („jede erfolgreiche Textaktion über
`aiprovider_eledia`") deckt Chokepoint 2 **nicht** ab. `mod_elli` bezieht seine
Chat-Ausgabe über `local_literag`, nicht über `core_ai`/`aiprovider_eledia`.
Provenance für Elli/Literag braucht eine **eigene** Instrumentierung an
`literag\...\llm\client::complete()` bzw. `tutor_chat::handle()`. Das ist eine
Scope-Ergänzung zu AP2 und in AP0 zu entscheiden (siehe §5).

Korrektur zum Handover: Die Quota-Buchung heißt `quota_manager::commit()` /
`::reserve()` / `::release()`, **nicht** `record_usage()`. Der Provenance-Record
gehört unmittelbar neben den `commit()`-Aufruf (L175) nach erfolgreicher Antwort.

`aiprovider_eledia` hat **keinen** `process_generate_image` (nur
`process_generate_text`, `process_summarise_text`, `process_explain_text`) →
bestätigt den Bedarf aus AP6.

---

## 2. Scope-Matrix (Plugins mit nutzerseitiger KI-Ausgabe)

| Plugin | Asset | Absatz | Heutiger Ausgabepfad (Datei:Methode) | AI-Route | Dependency-Weg | Aufwand |
|--------|-------|--------|--------------------------------------|----------|----------------|---------|
| `mod_aichat` | text | 50/1 (+2) | `mod/aichat/classes/external/send_message.php::execute()` → JSON `response`; JS `textContent` | core_ai | transitiv via `local_elediaai_core` | M |
| `mod_aifeedback` | text | 50/1 (+2) | `mod/aifeedback/classes/local/submission_sync.php::generate_feedback()`; Render `view.php:91` (`nl2br(s())`) | core_ai | transitiv | M |
| `mod_elli` | text | 50/1 (+2) | `mod/elli/classes/external/send_message.php::execute()` → `answerhtml` (`format_text`) | **literag (Chokepoint 2)** | **direkte Dep nötig** | L |
| `block_elediaai_chat` | text | 50/1 (+2) | `blocks/elediaai_chat/classes/external/send_message.php::execute()` → JSON; JS `textContent` | core_ai | transitiv | M |
| `block_elediaai_tutor` | text | 50/1 (+2) | `blocks/elediaai_tutor/classes/local/chat_service.php::send()`; `markdown_renderer::render()` → `answerhtml` | core_ai | **direkte Dep nötig** (R-02) | M |
| `block_elediaai_path` | text | 50/1 | `blocks/elediaai_path/classes/local/rationale_generator.php::generate()` → `next_step.mustache` `{{rationale}}` | core_ai | transitiv | S/M |
| `qtype_aitext` | text | 50/1 | `qtype/aitext/question.php::grade_response()` (Feedback, `format_text`); `renderer.php::feedback()` — **hat bereits einen Art.-50-Hinweis** (`renderer.php:116-120`) | core_ai | transitiv | S/M |
| `local_elediaai_coursegen` | text/HTML | 50/1+2 | `classes/local/course_writer.php::create_course()`/`add_activity()` → **schreibt in `course.summary`, `section.summary`, `page.content` (FORMAT_HTML)** → Core rendert später | core_ai | transitiv | L |
| `local_elediaai_questiongen` | text | 50/1+2 | `classes/local/xml_importer.php::import()` → Fragenbank (`question.questiontext` …), Core rendert später | core_ai | transitiv | L |
| `local_elediaai_selfstudy` | text | 50/1 (+2) | `classes/local/quiz_service.php::create()` (JSON), Render `output/quiz_renderer.php::render_question()` (`s()`) | core_ai | transitiv | M |
| `local_elediaai_teachertools` | text | 50/1 | `classes/local/generator.php::worksheet()`; `worksheet_renderer::render_html()` (`s()`) | core_ai | transitiv | M |
| `local_elediaai_h5pauthor` | text + **file (H5P/ZIP)** | 50/1+2 | `classes/local/generator.php::draft_*()`; Paket `classes/local/h5p_package_builder.php::build()`; Publish `publisher::publish()` → **Sidecar-Pfad** | core_ai | transitiv | L |
| `filter_eledia_translate` | **translation (AI MODIFIED)** | 50/2 | `filter/eledia_translate/classes/text_filter.php::filter()` (L234 raw in HTML-Stream) | eigener Übersetzer | **direkte Dep nötig** | M |
| `local_elediaai_tactics` | text | 50/1 (+2) | `classes/local/generator.php::generate_text()` → `core_ai`; Render `action.php:155` (`textarea`, `s()`) | core_ai | **direkte Dep nötig** (AP0-Fund) | M |

Transitive Inheritors erben `local_aitransparency`, sobald `local_elediaai_core`
die Dependency deklariert (AP1/AP2). `qbank_elediaai_questiongen` erbt über
`local_elediaai_questiongen`, emittiert selbst nichts (nur Navigations-Wrapper).

---

## 3. Direkte Dependency nötig (nicht über `local_elediaai_core`)

Handover 4.2 nannte drei Standalone-Plugins; AP0 fügt ein viertes hinzu:

1. `block_elediaai_tutor` — `dependencies = []`; bewusst standalone → **R-02 abstimmen** (harte Dep vs. `class_exists()`-Guard + Admin-Warnung).
2. `mod_elli` — nur `local_literag`; Ausgabe über Chokepoint 2 → Dep **und** eigene Provenance-Instrumentierung (§1).
3. `filter_eledia_translate` — `dependencies = []`.
4. **`local_elediaai_tactics`** — nur `local_lernhive`; erzeugt via `core_ai` Text-Entwürfe (`generate_text()` → `action.php:155`). `block_elediaai_tactics` ist reiner Launcher und erbt darüber. → **Antwort auf die offene Handover-Frage: ja, der Tactics-Zweig erzeugt KI-Ausgaben.**

---

## 4. Nicht-`core_ai`-Pfade & Out-of-Scope (mit Begründung)

| Komponente | Nutzerseitige KI-Ausgabe? | Einstufung |
|------------|---------------------------|------------|
| `local_ragingest` (`classes/api_client.php::upsert()`) | nein — nur Ingest/Embedding-Index | **out of scope** (keine Ausgabe) |
| `webservice/elediamcp/locallib.php` | nein — reiner Test-Client-Stub (`webservice_test_client_interface`), kein LLM-Call | **out of scope** |
| `local_literag` (`llm/client.php::complete()`) | **ja** — Tutor-Antworten an Nutzer, kein Quota/Provenance | **in scope → Chokepoint 2** |
| `local_elediaai_tutor_premium` | nein — reiner Feature-Flag-Provider (`classes/feature.php`) | **out of scope** |
| `local_elediaai_strategy` | nein — KI-Ausgabe wird nur intern als JSON zur Klassifikation genutzt (`ai/goal_interpreter.php`, `ai/config_enricher.php`), nicht an Nutzer gerendert | **out of scope** (kein Art.-50-Bezug) |
| `qbank_elediaai_questiongen` | nein — Navigations-Wrapper | out of scope (erbt via questiongen) |
| `block_elediaai_tactics` | nein — Launcher | out of scope (erbt via tactics) |

---

## 5. Offene Punkte für menschliche Entscheidung (AP0-DoD)

Diese Teile der AP0-DoD kann der Agent nicht allein schließen — sie sind
Entscheidungen/Aktionen für Johannes bzw. Infra/Einkauf:

1. **Bildmodell im LiteLLM-Backend?** (Voraussetzung für AP6 Spur 1). Mit Infra klären — offen.
2. **Upstream-Moodle-Issue** (Spur 3, `before_generated_file_stored`-Hook zwischen Modellantwort und Dateiablage). Der Moodle-Tracker liegt außerhalb der Agenten-Rolle → filing durch Mensch, Issue-ID danach in `03-dev-doc.md` verlinken.
3. **`block_elediaai_tutor`-Dependency** (R-02): harte Dep vs. Guard — Produktentscheidung, vor AP3.
4. **Signing-Dienstleister-Beschaffung** (AP5b): längste Vorlaufzeit im Projekt, in Woche 1 von AP5 anstoßen; Vertrag außerhalb der Agenten-Rolle.
5. **Chokepoint-2-Scope** (Literag/Elli): Bestätigen, dass Provenance auch an `literag\...\llm\client::complete()` instrumentiert wird (Ergänzung zur AP2-DoD).

Matrix mit einem Menschen abstimmen (AP0-DoD) — offen.

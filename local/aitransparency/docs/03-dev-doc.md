# Dev-Doc `local_aitransparency`

## AP0 — Bestandsaufnahme (2026-08-03)

Reine Analyse, kein Produktivcode. Ergebnis: `scope-matrix.md`. Diese Datei
hält die verifizierten Belege und die Abweichungen vom Handover fest.

### Verifizierte Core-Baseline (Handover Abschnitt 3)

Geprüft gegen die lokale Core-Installation (Core ist im Monorepo nicht vendored):

- `\core_ai\manager::store_action_result()` → `ai/classes/manager.php:171`,
  `insert_record('ai_action_register', …)` bei `manager.php:198`. ✔
- `\core_ai\ai_image::add_watermark()` → `ai/classes/ai_image.php:229`; ohne
  eigenen String Fallback `get_string('contentwatermark', 'core_ai')`. ✔
- `core_ai`-Hooks = exakt die vier Admin-/Formular-Hooks aus dem Handover
  (`after_ai_action_settings_form_hook`, `after_ai_provider_form_hook`,
  `before_provider_deleted`, `before_provider_disabled`) → **kein** Hook zwischen
  Modellantwort und Auslieferung. ✔ (bestätigt Spur 3 / Upstream-Bedarf)

### Abweichungen / Korrekturen zum Handover

1. **Quota-API-Name.** Handover nennt `quota_manager::record_usage()`. Tatsächlich:
   `reserve()` (L99), `commit()` (L175), `release()` (mehrfach) in
   `ai/provider/eledia/classes/abstract_processor.php`. Der Provenance-Record
   gehört neben den `commit()`-Aufruf (L175, nach `handle_api_success`).
2. **Zwei Chokepoints, nicht einer.** `local_literag` besitzt einen eigenen
   OpenAI-kompatiblen Client (`local/literag/classes/local/llm/client.php::complete()`
   L81 / `chat()` L58) und liefert Tutor-Antworten über
   `.../mcp/tools/tutor_chat.php::handle()` (L312) an Nutzer aus — ohne `core_ai`,
   ohne Quota, ohne Provenance. `mod_elli` hängt daran. → AP2-Scope-Ergänzung.
3. **Tactics-Zweig erzeugt KI-Ausgaben.** Offene Handover-Frage beantwortet:
   `local_elediaai_tactics::generator::generate_text()` ruft `core_ai`, Ausgabe
   an Lehrkraft in `action.php:155`. Braucht direkte Dependency. Der Block
   `block_elediaai_tactics` ist nur Launcher und erbt darüber.
4. **`aiprovider_eledia` ohne Bild-Prozessor.** Nur `process_generate_text`,
   `process_summarise_text`, `process_explain_text`. `process_generate_image`
   fehlt → AP6.

### Nicht-`core_ai`-Pfade (Audit)

- `local_ragingest` — Ingest/Embedding, keine Nutzerausgabe → out of scope.
- `webservice/elediamcp/locallib.php` — Test-Client-Stub, kein LLM-Call → out of scope.
- `local_literag` — **in scope**, Chokepoint 2 (siehe oben).

### Entscheidungen Johannes (2026-08-03)

- **Weiterbau:** AP1 als eigener PR, Review vor Merge — läuft autonom AP-für-AP.
- **Bildmodell im LiteLLM-Backend:** vorhanden (bestätigt) → AP6 Spur 1 tragfähig.
- **Signing-Beschaffung (AP5):** angestoßen; blockiert keinen AP (selbstsignierter
  Pfad AP5a zuerst).
- **`block_elediaai_tutor` (R-02):** `class_exists()`-Guard **plus** Admin-Warnung
  im Statusbericht — keine harte Dependency. Umsetzung in AP3.
- **Chokepoint 2 (Literag/Elli):** als AP2-Scope-Ergänzung bestätigt.

### Offene Aktion (Mensch)

- **Upstream-Moodle-Issue (Spur 3):** Text vorbereitet in `upstream-issue-draft.md`.
  Filing im Moodle-Tracker macht ein menschlicher Senior; Issue-ID danach hier
  verlinken: `TODO(upstream-issue-id)`.

Siehe `offene-rechtsfragen.md` für Einstufungsfragen (R-02 jetzt entschieden: Guard).

## CI-Härtung — XMLDB-Default für `model` (2026-08-04)

`local_aitransparency_rec.model` ist ein `CHAR NOT NULL`-Feld. Ein leerer
String als XMLDB-Default ist für diesen Feldtyp ungültig und löst beim
PHPUnit-Site-Setup in Moodle 5.2 eine Debugging-Meldung aus, die
`moodle-plugin-ci install` als Fehler behandelt. Der Default bleibt deshalb
undefiniert; Provenance-Aufrufer liefern bei unbekanntem Modell weiterhin
explizit den leeren String.

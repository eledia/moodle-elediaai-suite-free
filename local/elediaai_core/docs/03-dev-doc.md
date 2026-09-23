# Entwickler-Dokumentation

## Meta

Dieses Dokument beschreibt, wie das Plugin tatsaechlich implementiert ist.

Quelle der Wahrheit fuer den technischen Ist-Zustand. Begruendete Architektur-Entscheidungen stehen in `00-master.md` (adr01..adr04), das *Was/Warum* in `01-features.md`.

---

# System-Uebersicht

## Architektur

`local_elediaai_core` ist ein Moodle Local Plugin und das Dach („Umbrella") der LernHive-KI-Suite. Es enthaelt **keine** eigene KI-Geschaeftslogik, sondern ist reine Orchestrierung und UI-Mantel: Discovery, Launcher-Platzierung, Feature-Erklaerseiten und Audit. Jede konkrete KI-Aktion laeuft durch `core_ai\manager`.

Hauptebenen:

- **Discovery:** `classes/feature/registry.php` findet pro Request die Funktionen der Schwester-Plugins ueber die `feature_provider`-Konvention.
- **UI-Boundary:** `index.php` und die vier Audit-Seiten rendern in der LernHive Plugin-Shell. Die Suite-Uebersicht nutzt das Moodle-`report`-Layout als full-width entry point und `MODIFIER_WIDE`, damit das dreispaltige Feature-Raster nicht durch den schmalen Standard-Inhaltsbereich begrenzt wird.
- **Launcher:** `classes/hook_callbacks.php` injiziert die Navbar-Pille ueber einen Hook.
- **Audit:** Reportbuilder-Entity-Subclass + System-Report auf den `core_ai`-Tabellen, plus aggregierende Helper-Klassen.
- **Shared Helper:** `classes/local/stale_marker.php` (Stale/Regenerate-Fingerprint), genutzt von mehreren Schwester-Plugins.
- **Shared Context DTOs:** `classes/context/context_item.php` und `classes/context/context_bundle.php` modellieren die erste gemeinsame, resolver-freie Kontext-Schicht fuer Suite-Plugins.
- **Token-Quota:** `classes/local/quota_manager.php` ist die suiteweite Guthaben-Waehrung. Einzige eigene Tabelle: `local_elediaai_core_usage`.
- **Persistenz:** eine eigene Tabelle (`local_elediaai_core_usage`, nur Quota-Zaehler), Plugin-Config (`feature_<id>_enabled`, `audit_*`, `quota_*`) und die Moodle-Core-`core_ai`-Tabellen.

## Installation fuer Entwicklung

Das Repository wird als Moodle-Plugin-Pfad eingebunden:

```bash
export MOODLE_ROOT=/path/to/moodle
ln -s /Users/moskaliuk/Documents/Code/eledia.ai/public/local/elediaai_core "$MOODLE_ROOT/local/elediaai_core"
cd "$MOODLE_ROOT"
php admin/cli/upgrade.php
```

Die Plugin Shell ist eine Core-eigene Runtime-Komponente unter
`classes/output/`. `local_lernhive` ist keine UI-Abhängigkeit.
Die Suite rendert ihre Seiten daher ohne externe Runtime-Prüfung. Der optionale
Session-Composer-Hook bleibt davon getrennt und wird nur aufgerufen, wenn ein
anderes Plugin ihn bereitstellt.

## Repository-CI-Matrix

`scripts/ci-matrix.php` ist der zentrale Matrix-Katalog fuer das Monorepo. Er
liest die Kompatibilitaet lokaler Plugins direkt aus `version.php`:

- `$plugin->requires` wird gegen den Build der jeweiligen Moodle-Branch
  geprueft. Die Builds stehen in `MOODLE_RELEASES` und sind die `.0`-Releases
  der offiziellen Releaseliste: 4.5.0 = `2024100700`, 5.1.0 = `2025100600`,
  5.2.0 = `2026042000`. Ein Zahlendreher faellt hier nicht auf, sondern
  entfernt still eine Zelle.
- `$plugin->supported` wird als inklusiver Bereich `[minimum, maximum]`
  ausgewertet; ohne Feld gilt die Kompatibilitaet ab `requires` aufwaerts.
- Die Pruefung umfasst Haupt-Plugins und `TEST_EXTRAS`, weil beide im Job
  installiert werden.

Gruppen mit keinem gemeinsamen Supportbereich werden getrennt katalogisiert:
`core-elli` fuehrt die `local_elediaai_core`-Vertragstests mit `mod_elli` auf
Moodle 4.5/5.1, `subscription` prueft `local_elediaai_subscription` ab
Moodle 5.2, und `rag` bleibt wegen `mod_elli` auf 4.5/5.1 begrenzt. Der
Regressionstest `scripts/ci-matrix-test.php` wird im Changes-Job ausgefuehrt
und verifiziert diese unteren und oberen Grenzen vor der Matrixausgabe.

Welche Zweige ein Lauf ueberhaupt anfordert, entscheidet `matrix_request()`
im selben Skript aus `EVENT_NAME` und `MOODLE_INPUT`: Push und Pull-Request
fahren die schnelle Spur gegen den niedrigsten und den hoechsten Zweig,
`schedule` und `workflow_dispatch` die volle Matrix. Diese Auswahl gehoert
bewusst nicht mehr in den Workflow — ein `schedule` liefert keinen
`moodle`-Input, und die dortige Standardverzweigung liess den Nachtlauf auf
die 4.5/5.2-Grenzen zurueckfallen, sodass Moodle 5.1 nachts ungeprueft blieb.
Der Workflow reicht nur noch Event, Input und die geaenderten Dateien durch;
`ci-matrix-test.php` deckt Schedule-, Push- und Dispatch-Auswahl ab.

## Kernkomponenten

| Komponente | Zweck |
| --- | --- |
| `classes/feature/descriptor.php` | Readonly-Value-Object, das eine KI-Funktion beschreibt, inklusive Tier, Install-Status und Key-Features. |
| `classes/feature/feature_provider.php` | Interface, das jedes Sub-Plugin implementiert (`get_descriptors()`). |
| `classes/feature/registry.php` | Discovery, Sichtbarkeit (Capability + Toggle), Reihenfolge, In-Process-Cache. |
| `classes/elediaai_core/feature_provider.php` | Eigene Descriptors der Suite: Audit-Kachel + Roadmap-Stubs (coursegen/translate/tutor). |
| `classes/context/context_item.php` | Readonly-DTO fuer ein einzelnes Kontextstueck mit Typ, Course-/Context-ID, Titel, Text, Metadaten, Sensitivitaet und Truncation-Flag. |
| `classes/context/context_bundle.php` | Readonly-DTO fuer eine geordnete Item-Sammlung mit kombiniertem Text, Summary, Target-Context und stabilem Fingerprint. |
| `classes/content/content_extractor.php` | Lesbarer Text einer **Kursaktivitaet** (Seite, Buch, Datei, Verzeichnis, Lektion). Geteilt von questiongen, h5pauthor, teachertools und coursegen. |
| `classes/content/upload_text_extractor.php` | Lesbarer Text einer **hochgeladenen Datei** (docx, doc, pdf, html, md, txt) — ohne Fremdbibliothek. Am 07.09.2026 aus `local_elediaai_questiongen` hierher gezogen, weil der Kursautor denselben Text braucht; dort steht ein BC-Shim. |
| `classes/feature/policy.php` | Premium-Freigaben: `is_allowed(descriptor)`, `allows_feature(id)`, `require_allowed(id)`. |
| `classes/hook_callbacks.php` | Launcher-Injektion ueber `before_standard_top_of_body_html_generation`. |
| `classes/output/feature_grid.php` | Geteilter Renderer fuer Suite-Karten, Zielgruppenlegende und die aufgabenorientierten Teacher-Einstiege. |
| `classes/output/section_nav.php` | Suite- und Feature-Section-Navigation (Tab-Renderer). |
| `classes/local/audit_config.php` | Liest alle audit-bezogenen Plugin-Settings an einer Stelle. |
| `classes/local/audit_page.php` | Geteilte Rendering-Helper fuer die Audit-Seiten (CSS, Metriken, Panels, Aggregate). |
| `classes/local/stale_marker.php` | Gemeinsamer Stale/Regenerate-Fingerprint-Helper (Hash + Vergleich). |
| `classes/local/quota_manager.php` | Per-User-Token-Quota als harte Kreditgrenze: atomare Reservierung, Verbuchung und Freigabe in Stunden-, Tages- und Monatsfenster. |
| `classes/reportbuilder/local/entities/ai_action_audit.php` | Entity-Subclass von `core_ai`-`ai_action_register` mit Praesentationsspalten. |
| `classes/reportbuilder/local/systemreports/audit.php` | Der auf `audit_technical.php` gemountete System-Report. |
| `classes/form/audit_settings.php` | Moodle Form fuer die in-Shell-Audit-Einstellungen. |
| `classes/privacy/provider.php` | Metadata-, Plugin- und Userlist-Provider fuer die Quota-Zaehler; die Audit-Basisdaten bleiben `core_ai`-Hoheit. |
| `lib.php` | `local_elediaai_core_lernhive_session_compose()` als Hook-Provider. |
| `index.php` | KI-Suite-Uebersicht (Karten-Grid mit Icon-Actions). |
| `styles.css` | Core-eigene Shell, Karten, Tags, Buttons und Action-Icons. |
| `feature.php` | Feature-Erklaerseite pro Descriptor mit wesentlichen Funktionen. |
| `help.php` | Rendert das mitgelieferte KI-Suite-Handbuch direkt als lokales Markdown. |
| `audit.php` | Audit-Uebersicht mit zwei Pfaden (technisch/didaktisch). |
| `audit_technical.php` | Technisches Audit (System-Report + Schnellfilter). |
| `audit_didactic.php` | Didaktisches Audit (aggregierte Support-Kontexte). |
| `audit_settings.php` | In-Shell-Settingseite fuer die Audit-Anzeige. |
| `templates/launcher.mustache` | Icon-Link auf die Suite-Uebersicht. |
| `templates/feature_info.mustache` | Feature-Erklaerkarte. |
| `amd/src/audit_preview.js` | Augen-Icon → Modal-Vorschau fuer Prompt/Antwort/Fehler. |
| `db/hooks.php` | Registriert den Launcher-Hook-Callback. |
| `db/access.php` | Leer. Das Plugin fuehrt keine eigene Capability mehr; Audit nutzt die Core-Capability. |
| `db/install.xml` | Einzige eigene Tabelle `local_elediaai_core_usage` (Quota-Zaehler). |
| `settings.php` | Admin-Settingpage `local_elediaai_core_audit` (Spiegel der Audit-Einstellungen). |

## Datenmodell

`local_elediaai_core` legt **drei** eigene Tabellen an. Alles Uebrige liegt in Plugin-Config und in Core-Tabellen:

- **`local_elediaai_core_usage`** (`db/install.xml`) — die Quota-Zaehler, siehe „Token-Quota".
- **`local_elediaai_core_turn`** — Schicht B: eine Zeile je KI-Anfrage der Suite, mit Frage, Antwort, Herkunft der Antwort, Thema, Komponente und Tokenzahlen. **Ohne Nutzerkennung:** an ihrer Stelle steht `askerkey`, ein gesalzenes Pseudonym (`sha256(userid . '|' . Salz)`, Salz einmalig im Upgrade erzeugt). Das ist pseudonym, nicht anonym — es traegt weiter einen Personenbezug, nur keinen aufloesbaren.
- **`local_elediaai_core_action`** — Schicht A: eine Zeile je Werkzeugaufruf des Assistenten, **mit** `userid`, aber ohne Inhalt. Sie beantwortet „in wessen Auftrag hat die KI was getan", und nur das.

Die drei sind nach Lebensdauer und Personenbezug geschnitten, nicht nach Thema: A traegt die Person und die laengste Frist, B den freien Text und die kuerzeste, und der Herkunftsnachweis in `local_aitransparency` ueberlebt beide, weil er nur einen Hash haelt. Jede Frist ist einzeln einstellbar (`action_retentiondays`, `turn_retentiondays`, `usage_retentiondays`); `0` bewahrt unbegrenzt auf. Beim Ablauf wird B geloescht, A dagegen **anonymisiert** — ein Aufsichtsnachweis, der auf Zuruf verschwindet, belegt nichts.

Alle drei sind personenbezogen und zusammen der Grund, warum `classes/privacy/provider.php` kein `null_provider` mehr ist.
- **Plugin-Config** (`mdl_config_plugins`, Komponente `local_elediaai_core`):
  - `feature_<id>_enabled` — Per-Feature-Toggle (`feat07`), Default `1`.
  - `audit_access` — `corecap` (Standard), `teacherowncourses` oder `adminonly`.
  - `audit_anonymize_users` — `0/1`.
  - `audit_show_prompt`, `audit_show_response`, `audit_show_error`, `audit_show_tokens` — je `0/1`, Default `1`.
  - `quota_<bucket>_<window>` — sechs Limits (`student|teacher` × `hour|day|month`), Default `0` = unbegrenzt.
  - `quota_completion_buffer` — Default `500`.
  - `quota_image_cost_256|512|1024` — Defaults `1000` / `2000` / `4000`.
- **Moodle Core `core_ai`-Tabellen** als Source-of-Truth fuer das Audit (siehe unten).

Die Context-DTOs sind bewusst reine In-Memory-Transportobjekte. Sie speichern
nichts selbst und enthalten noch keine Picker-/Resolver-Logik. Schwester-Plugins
koennen daraus einen `context_bundle` bauen, dessen `combinedtext` direkt als
Prompt-Grounding verwendbar ist und dessen `fingerprint` fuer Stale-/Cache-
Vergleiche stabil bleibt.

### Quelle des Audits: `ai_action_register` + Detailtabellen

Moodle 5.x schreibt jede `core_ai`-Aktion automatisch in `ai_action_register` und je nach Aktionstyp in eine Detailtabelle:

- `ai_action_generate_text`
- `ai_action_summarise_text`
- `ai_action_explain_text`

Die Detailtabellen tragen Prompt, generierten Inhalt und Tokenfelder; `core_ai` speichert die Antwort-Tokenzahl als `completiontoken` (Singular). `ai_action_generate_image` trägt zusätzlich die Bildparameter `quality`, `aspectratio` und `numimages`. `ai_action_register` traegt `actionname`, `actionid`, `userid`, `contextid`, `provider`, `model`, `success`, `errormessage`, `timecreated`.

### Versionsgate: Audit nur ab Moodle 5.0

Die `ai_action_register`-Tabelle, die gleichnamige Reportbuilder-Entity und die Capability `moodle/ai:viewaiusagereport` gibt es erst ab Moodle 5.0 (das AI-Subsystem in 4.5 hat weder Tabelle noch Report noch Capability, `ai/db/` ist dort leer). Auf Moodle 4.5 wuerde deshalb jeder Audit-Pfad fatal — die Entity kann ihre fehlende Core-Oberklasse nicht laden, die Queries laufen gegen eine nicht existente Tabelle, und `has_capability('moodle/ai:viewaiusagereport')` wirft eine `coding_exception`. Kritisch: `audit_config::can_view()` wird ueber den geteilten Launcher-Hook (`registry::visible()`) bei jedem eingeloggten Seitenaufbau erreicht, sodass der 4.5-Bruch nicht nur die Audit-Seiten, sondern jede AI-Suite-Seite (und die PHPUnit-Runs aller abhaengigen Plugin-Gruppen) trifft.

`audit_config::feature_available()` (`class_exists(\core_ai\reportbuilder\local\entities\ai_action_register::class)`) ist die zentrale Feature-Erkennung. Alles Audit-Bezogene ist dahinter gegatet: Der Launcher blendet die Audit-Kachel auf < 5.0 aus (`elediaai_core\feature_provider`), `can_view()` gibt dort `false` zurueck (nie die fehlende Capability), die Audit-Seiten rendern eine `render_unavailable_page()`-Hinweisseite statt zu fatalen, und die Audit-Tests (`audit_entity_test`, die Audit-Assertions in `registry_test`) ueberspringen sich. Alle uebrigen Suite-Funktionen bleiben auf 4.5 voll nutzbar. Feature-Detection statt harter Versionsnummer haelt das robust gegen kuenftige Core-Aenderungen.

## Datenfluss: KI-Aktion → Audit

Zwei Wege, die sich nicht vereinigen lassen.

**Aus der Suite heraus** (Schicht B):

1. Ein Sub-Plugin ruft `quota_aware_ai_manager::process_action()` oder
   `process_callback()` auf.
2. Der Einstieg reserviert Guthaben, fuehrt aus, verbucht — und schreibt dabei
   eine Zeile nach `local_elediaai_core_turn`. Das Sub-Plugin tut dafuer
   nichts.
3. `audit_technical.php` mountet den System-Report `turns`.

**Aus dem Rest von Moodle** (Moodles eigenes Register):

1. Ein fremdes Plugin ruft `core_ai\manager::process_action()` direkt auf.
2. Moodle Core protokolliert in `ai_action_register` und der passenden
   Detailtabelle.
3. Derselbe Bildschirm mountet daneben den System-Report `audit`; die Entity
   joint die Detailtabellen per `LEFT JOIN` und `COALESCE`, sodass eine
   einzige Spalte fuer jeden Aktionstyp funktioniert. Der Bildjoin wird nur
   aktiviert, wenn Moodle die Tabelle bereitstellt.

**Warum zwei Listen und nicht eine.** Eine Suite-Anfrage steht in beiden
Speichern: der Kern protokolliert sie ebenfalls. Nichts verbindet die beiden
Zeilen zuverlaessig miteinander — `ai_action_register` fuehrt keine Komponente
und `local_elediaai_core_turn` keine `registerid`. Eine Vereinigung wuerde
dieselbe Aktion doppelt zaehlen; getrennt nebeneinander ist die ehrlichere
Darstellung und der Grund, warum die Ueberschriften „Anfragen aus der Suite"
und „Anfragen anderer Plugins" lauten.

**Warum die Komponenten-Auswertung aus B kommt und nicht aus dem Hauptbuch.**
`local_elediaai_core_usage` fuehrt eine `component`-Spalte, was es wie die
naheliegende Quelle aussehen laesst. Sein eindeutiger Index ist aber
`(userid, rolebucket, windowtype, windowstart)` **ohne** Komponente: es gibt
eine Zeile je Person und Fenster, und die Spalte haelt nur fest, welches
Feature zuletzt gebucht hat. `component_usage_test` haelt diese Eigenschaft
fest, damit sie niemand erneut fuer eine Zuordnung haelt.

## Token-Quota (`classes/local/quota_manager.php`)

`quota_manager` ist die gemeinsame Guthaben-Währung der Suite: eine
**harte Kreditgrenze pro Nutzer:in**, die *vor* dem externen Provider-Aufruf
greift. Die Klasse ist final, static-only und hat einen privaten Konstruktor;
sie kennt weder `core_ai` noch einen konkreten Provider und ist deshalb aus
jedem Schwester-Plugin aufrufbar.

### Buckets, Fenster und Limits

- **Buckets:** `BUCKET_STUDENT` / `BUCKET_TEACHER`. `role_bucket()` liefert
  `teacher`, sobald der Nutzer irgendwo eine Rolle mit Archetyp
  `editingteacher` oder `teacher` hält — sonst `student`. Die Prüfung ist
  bewusst site-weit und nicht kurskontext-abhängig, weil die Quota selbst
  nutzerweit ist.
- **Fenster:** `WINDOW_HOUR`, `WINDOW_DAY`, `WINDOW_MONTH`. `window_start()`
  rastert auf die volle Stunde, den Tagesbeginn bzw. den Monatsersten in der
  Serverzeitzone. Es sind Kalenderfenster, keine gleitenden.
- **Limit:** `get_config('local_elediaai_core', 'quota_<bucket>_<window>')`.
  `0` (Default) bedeutet **unbegrenzt** — das Fenster wird dann komplett
  übersprungen, es entsteht auch keine Reservierung. Sind alle sechs Limits
  `0`, ist die Quota faktisch aus.

### Reservieren → Verbuchen → Freigeben

Der Preflight allein reicht nicht: zwei parallele Requests könnten beide
denselben freien Rest sehen und passieren. Deshalb ist der reguläre Pfad
dreistufig, und eine Reservierung zählt sofort gegen das Limit:

1. `reserve($userid, $estimatedtokens, $component): quota_reservation` —
   reserviert
   `max(0, $estimatedtokens) + quota_completion_buffer` atomar in **jedem**
   limitierten Fenster und gibt einen Handle mit Betrag, Rollen-Bucket und den
   exakten Startzeitpunkten aller drei Fenster zurück. Schlägt ein Fenster fehl,
   werden die bereits erfolgreichen Fenster wieder freigegeben, bevor die
   Exception weiterfliegt — es bleibt keine halbe Reservierung stehen.
2. `commit($userid, $reservation, $prompttokens, $completiontokens, $component)`
   — bucht die tatsächlich gemessene Nutzung in die beim Reservieren erfassten
   Fenster und gibt die Reservierung im selben UPDATE frei (`reservedtokens`
   wird nie negativ). Fehlt die Zeile, wird sie angelegt; ein Unique-Konflikt
   beim Insert fällt auf ein zweites UPDATE zurück.
3. `release($userid, $reservation)` — der Fehlerpfad. Gibt die Reservierung in
   den ursprünglichen Fenstern und im ursprünglichen Rollen-Bucket frei, ohne
   Nutzung zu verbuchen.

Atomarität kommt aus `\core\lock\lock_config` (Lock-Typ
`local_elediaai_core_quota`, ein Lock pro `userid/bucket/window`, 5 s Timeout).
Nur die Read-check-write-Sequenz der Reservierung läuft unter dem Lock;
`commit()`/`release()` sind reine relative UPDATEs und brauchen keinen. Ist der
Lock nicht zu bekommen, wirft die Klasse `error_quota_unavailable` — Requests
laufen im Zweifel **nicht** ungezählt durch.

Konsumenten müssen denselben `quota_reservation`-Handle vom Preflight bis zum
Erfolgs- oder Fehlerpfad weiterreichen. Dadurch bleibt die Zuordnung auch dann
korrekt, wenn ein Request eine Stunden-, Tages- oder Monatsgrenze überschreitet
oder sich der Rollen-Bucket zwischenzeitlich ändert. Die optionale
Workspace-Quota aus `local_elediaai_subscription` läuft daneben.

### Public API

| Methode | Zweck |
| --- | --- |
| `reserve(int $userid, int $estimatedtokens, string $component): quota_reservation` | Prompt-Schätzung + Completion-Puffer atomar reservieren und ursprüngliche Fenster festhalten. |
| `reserve_for_action(int $userid, string $actiontype, array $params, string $component, int $estimatedtokens = 0): quota_reservation` | Wie `reserve()`, aber mit aktionsspezifischem Preis (Bilder). |
| `commit(int $userid, quota_reservation\|int $reservation, int $prompttokens, int $completiontokens, string $component): void` | Gemessene Nutzung in den ursprünglichen Fenstern verbuchen und Reservierung auflösen. |
| `commit_action(int $userid, quota_reservation\|int $reservation, string $actiontype, array $actualparams, int $prompttokens, int $completiontokens, string $component): void` | Verbuchen mit vom Provider bestätigten Aktionsparametern. |
| `release(int $userid, quota_reservation\|int $reservation): void` | Reservierung in den ursprünglichen Fenstern ohne Verbuchung freigeben. |
| `assert_can_request(int $userid, int $estimatedtokens = 0): void` | Nicht-mutierender Preflight; wirft `error_quota_token_exceeded`. |
| `assert_can_request_for_action(int $userid, string $actiontype, array $params = [], int $estimatedtokens = 0): void` | Preflight mit Aktionspreis. |
| `action_cost(string $actiontype, array $params = [], int $estimatedtokens = 0): int` | Token-äquivalenter Preis einer Aktion. |
| `record_usage(int $userid, ?int $prompttokens, ?int $completiontokens, string $component): void` | Rückwärtskompatible Verbuchung ohne Reservierung (= `commit()` mit `$reservedtokens = 0`). |
| `used_tokens(int $userid, string $bucket, string $window): int` | Verbuchte `totaltokens` im aktuellen Fenster (**ohne** offene Reservierungen). |
| `estimate_tokens(string $text): int` | Grobe Schätzung `ceil(strlen / 4)` für Systeme ohne Usage-Werte. |
| `role_bucket(int $userid): string` | `student` oder `teacher`. |
| `prune(int $days = self::RETENTION_DAYS): int` | Löscht Zeilen mit `windowstart` älter als `$days` (Default 90) und gibt die Anzahl zurück. |
| `delete_for_user(int $userid): int` | Löscht alle Zähler eines Nutzers und gibt die Anzahl zurück. |

`assert_can_request()` ist der Alt-Pfad für Aufrufer ohne Reservierungslogik
(z. B. `block_elediaai_tutor\local\token_quota::assert_can_request()`). Er
verwendet dieselbe Formel, sieht aber offene Reservierungen anderer Requests
nicht — er ist eine Anzeige, keine Garantie. Neuer Code nutzt `reserve()`.

### Tabelle `local_elediaai_core_usage`

Eine Zeile pro `(userid, rolebucket, windowtype, windowstart)`, erzwungen durch
den Unique-Index `userwindow_uix`; `windowstart_ix` bedient `prune()`.

| Feld | Bedeutung |
| --- | --- |
| `userid`, `rolebucket`, `windowtype`, `windowstart` | Fenster-Schlüssel. |
| `prompttokens`, `completiontokens`, `totaltokens` | Verbuchte Nutzung. `totaltokens` ist die Größe, gegen die das Limit prüft. |
| `reservedtokens` | Offene, noch nicht verbuchte Reservierungen. Limitprüfung rechnet `totaltokens + reservedtokens`. |
| `requestcount` | Anzahl `commit()`-Aufrufe im Fenster. |
| `component` | Zuletzt schreibende Komponente (Diagnose, keine Abrechnungsdimension). |

Es werden **keine** Prompts oder Antworten gespeichert, nur Zähler.
`classes/privacy/provider.php` exportiert und löscht diese Zeilen im
System-Kontext (`delete_data_for_user`, `delete_data_for_users`,
`delete_data_for_all_users_in_context`, `get_users_in_context`).

**Bekannte Lücke:** `prune()` ist implementiert und getestet, aber an keinen
Scheduled Task gebunden (`db/tasks.php` existiert nicht). Die 90-Tage-Retention
gilt heute nur, wenn ein Aufrufer `prune()` selbst anstößt. Wer die Retention
zusagt, muss vorher den Task nachziehen.

### Quota-Contract für Bildaktionen

Bilder nutzen dieselbe Währung wie Text, damit bestehende Limits unverändert
bleiben:

- `action_cost('generate_image', ['size' => '1024x1024', 'numimages' => 2])` berechnet die token-äquivalente Reservierung.
- `reserve_for_action($userid, $actiontype, $params, $component)` prüft und reserviert atomar vor `process_action()`.
- `commit_action($userid, $reserved, 'generate_image', $actualparams, 0, 0, $component)` verbucht den tatsächlich bestätigten Bildpreis; `release()` bleibt der Fehlerpfad.
- Für Textaktionen bleiben `reserve()`, `assert_can_request()` und `commit()` unverändert.

Der Schwester-Processor muss `size` (alternativ `quality`) und `numimages` im
Parameterarray übergeben. Die Preisstufen sind als `quota_image_cost_256`,
`quota_image_cost_512` und `quota_image_cost_1024` konfigurierbar; unbekannte Größen
werden sicher auf die 1024px-Stufe gerundet. Fehlt `size`, wird `quality`
(`low`/`medium`/sonst) auf 256/512/1024 px abgebildet.

Aufrufer im Repo: `aiprovider_eledia` (Text + Bild),
`block_elediaai_tutor\local\token_quota` (Fassade für den Chat) und
`local_elediaai_core\local\audit_page` (rechnet Bildguthaben für die
Audit-Summen nach).

## LLM-Session-Composer — **entfernt am 05.09.2026**

Er beantwortete den Hook `lernhive_session_compose`, den nur `local_lernhive`
aufgerufen haette. Dieses Plugin gehoert nicht zur Suite und existiert nicht,
also ist die Funktion nie gelaufen. Siehe `01-features.md` feat13.

Damit loest die Suite **von sich aus gar keine** KI-Aktion mehr aus: jede
verbliebene geht von einer Eingabe aus.


## Die Vertraege der Suite

Ein Suite-Plugin spricht ueber sieben Schnittstellen mit dem Kern und seinen
Nachbarn. Fuenf sind Konventionen ueber Klassennamen — es gibt keine
Registrierungstabelle, keinen Install-Hook und nichts, was beim Deinstallieren
zurueckbleibt. Installieren genuegt, Deinstallieren auch. Zwei sind Aufrufe:
etwas, das ein Plugin benutzt, statt etwas, das es bereitstellt.

| Vertrag | Wo | Wofuer | Provider im Repo |
| --- | --- | --- | --- |
| **Anmelden** | `\<component>\elediaai_core\feature_provider` | Die Kachel auf dem Dashboard | 23 |
| **Erklaeren** | `\<component>\elediaai_guide\guide_provider` | Die Kapitel im Handbuch | 22 |
| **Zustand melden** | `\<component>\elediaai_core\health_provider` | Die Zeile im Zustandsbericht | 6 |
| **Chat anbieten** | `\<component>\chatengine\placement` | Eine Oberflaeche, auf der ein Chat laeuft | 3 |
| **Premium freischalten** | `\<component>\elediaai_core_policy\policy_provider` | Zugang zu Premium-Features | 1 |
| **KI aufrufen** | `local_elediaai_core\quota_aware_ai_manager` | Guthaben und Audit | *Aufruf, kein Provider* |
| **Aussehen** | `local_elediaai_core\output\plugin_page` + `plugin_shell` | Die gemeinsame Seitenhuelle | *Aufruf, kein Provider* |

Die Zahlen sind Dateien im Repo, Stand 08.09.2026, und altern:

```bash
find public -path '*/classes/elediaai_core/feature_provider.php' | wc -l
find public -path '*/classes/elediaai_guide/guide_provider.php' | wc -l
find public -path '*/classes/elediaai_core/health_provider.php' | wc -l
```

Sie sind hoeher als die Zahl der Kacheln: `mod_aichat` etwa liefert bewusst
einen Provider, der nichts zurueckgibt — seine Kachel kommt vom Block.

**Alle fuenf Provider liegen im selben Namensraum je Komponente**, deshalb
filtert jede Registry nach dem Klassennamen und nicht nur nach dem
implementierten Interface. Ohne das meldet die Feature-Registry den
`health_provider` desselben Plugins als kaputten Feature-Provider — eine
Fehlermeldung ueber Code, der in Ordnung ist. Das ist genau einmal passiert,
und der Filter sitzt seitdem in `registry::collect()`, **nicht** in
`collect_providers()`: dort speisen die Tests anonyme Attrappen ein, deren
Name auf nichts endet, und ein Filter haette die Pruefung der
Ausfallsicherheit stillgelegt.

Vier davon sind optional in dem Sinn, dass ein Plugin ohne sie laeuft — es ist
dann nur nicht im Index, nicht im Handbuch oder sieht anders aus. Der dritte
ist es nicht: wer ihn umgeht, verbraucht Guthaben ohne es zu buchen.

### Optional heisst nicht unauffaellig

Die vier optionalen Vertraege haben eine gemeinsame Eigenschaft, die sie
gefaehrlicher macht als eine harte Abhaengigkeit: **sie scheitern lautlos.**
Wer `guide_provider` vergisst, bekommt keinen Fehler — das Handbuch hat eben
ein Kapitel weniger. Wer im `descriptor` `imagename` weglaesst, bekommt keinen
Fehler — die Kachel faellt auf `render_image_fallback()` zurueck und zeigt den
blossen Symbolkreis. Beides sieht aus wie Absicht.

`format_elediaai` ist damit angetreten und hat es zweimal auf einmal
vorgefuehrt: Feature-Provider ja, `imagename` nein, `guide_provider` gar nicht.
Auf dem Dashboard war es die einzige Kachel ohne Zeichnung, im Handbuch war es
gar nicht vorhanden — und gemeldet hat das nicht die CI, sondern ein Mensch,
der hingesehen hat.

Deshalb prueft ein neues Plugin seine eigenen Vertraege in seinen eigenen
Tests, nicht der Kern die aller anderen: eine Pruefung in `local_elediaai_core`
sieht in der CI nur die Plugins, die in diesem Job gemountet sind. Das Muster
steht in `format_elediaai/tests/feature_provider_test.php` und
`guide_provider_test.php` — drei Zusicherungen, die genau die stillen Faelle
abdecken:

- `imagename` ist nicht `null`, und die Datei ist unter
  `<component>/pix/<name>.svg` lesbar.
- `lucide_icon::render($icon)` gibt nicht die leere Zeichenkette zurueck (ein
  unbekannter Name malt nichts und sagt nichts).
- Der Wegweiser-Test ueberspringt sich, wenn `local_elediaai_guide` fehlt —
  die weiche Abhaengigkeit bleibt weich.

### 1. Anmelden — `feature_provider`

Siehe „Feature-Discovery" unten fuer die Mechanik. Was ein Plugin liefert, ist
ein `descriptor` je Feature.

### 2. Erklaeren — `guide_provider`

```php
namespace local_meinplugin\elediaai_guide;

use local_elediaai_guide\content\audience;
use local_elediaai_guide\content\guide_provider as guide_provider_contract;
use local_elediaai_guide\content\topic;

final class guide_provider implements guide_provider_contract {
    public static function get_topics(): array {
        if (!interface_exists(guide_provider_contract::class)) {
            return [];   // Wegweiser nicht installiert.
        }
        return [
            new topic(
                id: 'meinplugin_was',
                component: 'local_meinplugin',
                title: get_string('guide_title', 'local_meinplugin'),
                summary: get_string('guide_summary', 'local_meinplugin'),
                body: get_string('guide_body', 'local_meinplugin'),   // Markdown
                audiences: [audience::TEACHER],   // leer = alle
                icon: 'circle-question',          // Name aus dem Sprite
                order: 100,
                section: topic::SECTION_USING,    // START | USING | TRUST | ADMIN
            ),
        ];
    }

    public static function get_announcements(): array {
        return [];
    }
}
```

Drei Dinge, die man wissen muss, bevor man das schreibt:

- **Ein Provider ersetzt den gebuendelten Text seiner Komponente.** Der
  Wegweiser bringt fuer einige Komponenten eigene Texte mit; sobald ein Plugin
  einen Provider hat, gelten nur noch dessen Kapitel. Wer einen Provider
  hinzufuegt und die alten Texte nicht mitnimmt, loescht sie.
- **Der Icon-Name muss im Sprite stehen** (`local/elediaai_core/pix/lucide.svg`).
  `lucide_icon::render()` liefert bei einem unbekannten Namen den leeren String —
  das Kapitel verliert still seine Verzierung, ohne dass etwas bricht.
- **`get_topics()` laeuft bei jedem Seitenaufruf.** Strings und Value-Objects,
  keine Datenbankabfragen, kein Backend.

Ein Kapitel gehoert dem Plugin, das es beschreibt. Die einzige Ausnahme ist das
Herkunfts-Kapitel in `local_elediaai_core`: es fasst zusammen, was **alle**
Features ueber ihre Urheber angeben, und diese Frage kann kein einzelnes Plugin
beantworten.

### 3. Zustand melden — `health_provider`

```php
namespace local_meinplugin\elediaai_core;

use local_elediaai_core\health\check;
use local_elediaai_core\health\health_provider as health_provider_contract;

final class health_provider implements health_provider_contract {
    public static function get_checks(): array {
        if (!class_exists(check::class)) {
            return [];   // Kern nicht installiert.
        }

        return [
            new check(
                id: 'backend',
                component: 'local_meinplugin',
                label: get_string('health_backend', 'local_meinplugin'),
                status: check::STATUS_OK,   // OK | UNCONFIGURED | WARNING | ERROR | DISABLED
                detail: get_string('health_backend_ok', 'local_meinplugin'),
                actionurl: new moodle_url('/admin/settings.php', ['section' => 'local_meinplugin']),
                actionlabel: get_string('settings'),
            ),
        ];
    }
}
```

Der Bericht steht unter `/local/elediaai_core/health.php`, als Reiter
„Zustand" neben dem Audit auf der Suite-Leiste und als Querverweis in der
Infrastruktur-Leiste. Wie der Reiter aussieht, sagt
`section_nav::health_item()` — einmal, fuer alle drei Leisten. Drei Dinge, die diesen Vertrag von den anderen
unterscheiden:

- **Er darf langsam sein.** `feature_provider` und `guide_provider` laufen bei
  jedem Seitenaufruf und duerfen kein Backend fragen. Dieser laeuft auf einer
  Seite, die jemand absichtlich geoeffnet hat, und *soll* fragen. Eigene
  Zeitgrenzen setzen; ein nicht erreichbares Backend ist eine Meldung mit
  `STATUS_ERROR`, keine Ausnahme.
- **Fuenf Zustaende, und die Unterscheidung ist der Zweck.** „Nicht
  eingerichtet" ist eine Aufgabe, „abgeschaltet" eine Entscheidung, „gestoert"
  ein Alarm. Nur `ERROR` und `WARNING` gelten als handlungsbeduerftig
  (`check::needs_attention()`). Wer alles auf „gestoert" abbildet, bekommt eine
  Seite, die immer rot ist und die deshalb niemand mehr liest.
- **Nur ueber sich selbst berichten.** Der Kern fragt bewusst niemanden aus:
  ob ein Aufnahmeziel antwortet, weiss `local_elediaai_sources`, und niemand
  sonst kann es wissen, ohne in dessen Innereien zu greifen. Genau das tat die
  frueher zustaendige Seite in `block_elediaai_tutor`.

Ein Provider, der wirft, kostet sich selbst: sein Scheitern wird zu einer
eigenen Meldung, die uebrigen Berichte bleiben stehen. Nichts wird
zwischengespeichert — ein Bericht aus dem Cache ist kein Bericht.

### 4. Chat anbieten — `chatengine\placement`

```php
namespace mod_meinchat\chatengine;

use local_elediaai_chatengine\placement\placement as placement_contract;

final class placement implements placement_contract {
    // Wo der Chat erscheint, wer ihn benutzen darf, in welcher Stimme er
    // antwortet. Die Engine fuehrt den Turn aus, die Placement entscheidet
    // den Rahmen.
}
```

Eine Placement ist eine Oberflaeche, auf der ein Chat laeuft — ein Block, eine
Aktivitaet. Sie besitzt den Ort, die Berechtigung und die Persona; die Engine
besitzt den Turn, das Guthaben und das Audit. Umgesetzt von
`block_elediaai_tutor`, `mod_aichat` und `mod_elli`.

**Seit 20.09.2026 gehoert `knowledge_scope(int $instanceid): string` dazu** —
die Kursbereiche und Kurse, aus denen diese Flaeche antworten darf, als
gespeicherte Auswahl im Format von
`local_elediaai_chatengine\local\knowledge_scope` (`cat:12,course:7`). Eine
Flaeche ohne solche Einstellung gibt den leeren String zurueck; das ist kein
Sonderfall, sondern die Vorgabe.

Der Wert ist ein **Wunsch, keine Berechtigung**. Die Engine schneidet damit
ein und weitet nie: gesucht wird in der Auswahl **geschnitten mit** den
Einschreibungen der fragenden Person **und** mit dem, was indexiert ist. Eine
Flaeche ohne Kurs — die Tutor-Startseite — ignoriert die Auswahl und sucht in
allen Kursen der Person; sie gehoert der Person, nicht einem Kurs
(Betreiberentscheidung 20.09.2026). Die aufgeloeste Menge geht als
kommagetrennte Liste im Argument `course_id` an das Backend; der Kontrakt
dazu steht in `local_elediaai_chatengine/docs/rag_server_spec.md`, Abschnitt
A.1 und „Entitlement".

Wer eine eigene Placement mitbringt, muss die Methode implementieren — das
Interface verlangt sie, ein Plugin ohne sie laedt nicht mehr.

Die Engine bucht ueber `quota_aware_ai_manager::process_callback()` und
schreibt ihr Audit ueber
`local_elediaai_core\local\audit_recorder::record_without_user()` — **ohne
Nutzerkennung**, so vom Betreiber entschieden: Prompt, Antwort, Ort und Zeit
werden erfasst, wer gefragt hat nicht.

### 5. Premium freischalten — `policy_provider`

```php
namespace local_meinplugin_premium\elediaai_core_policy;

use local_elediaai_core\feature\policy_provider as policy_provider_contract;

final class policy_provider implements policy_provider_contract {
    // Sagt, ob ein Feature mit tier::PREMIUM benutzt werden darf.
}
```

Ein Descriptor mit `tier: tier::PREMIUM` bleibt unsichtbar, solange kein
Provider ihn freigibt. `local_elediaai_tutor_premium` setzt das um: eine
Checkbox je Premium-Descriptor in seinen Einstellungen, **standardmaessig
aus**. Installieren allein schaltet nichts frei.

**Das Add-on ist die Lizenzstelle der Suite, kein Feature unter Feature**
(seit 07.09.2026). Sein `policy_provider` antwortete zuvor nur fuer
Descriptoren der **eigenen** Komponente und gab fuer alle anderen `false`
zurueck. Da es der einzige Provider der Suite ist, war eine Premium-Funktion
in einem anderen Plugin damit dauerhaft ungrantbar — der Schalter existierte
nirgends. Der Schluessel bleibt `grant_<id>`: Descriptor-IDs sind suiteweit
eindeutig (die Registry schluesselt ihr Ergebnis danach), ein
Komponenten-Praefix ist unnoetig, und die bereits gespeicherten Freigaben
gelten weiter.

**Zwei verschiedene Fragen, zwei verschiedene Pruefungen.** Eine Capability
sagt, *wer* darf; ein Grant sagt, ob die Website *ueberhaupt* darf. Beides
gehoert an jede Flaeche eines Premium-Features:

```php
use local_elediaai_core\feature\policy;

require_login();
require_capability('moodle/site:config', $context);   // wer
policy::require_allowed('coursegen');                 // ob die Website
```

`allows_feature(string $id): bool` fuer den weichen Fall (eine Kachel
weglassen, ein Werkzeug nicht in den Katalog stellen),
`require_allowed(string $id): void` fuer die harte Grenze. **Eine unbekannte
Descriptor-ID gilt als nicht erlaubt** — ein Premium-Feature, dessen
Descriptor fehlt, ist dadurch nicht frei.

**Beigesteuerte MCP-Werkzeuge muessen selbst pruefen.**
`webservice_elediamcp` laesst Werkzeuge aus fremden Plugins bewusst am
Premium-Gate des Servers vorbei („die Website hat sie ja selbst
installiert"). Wer ein Premium-Feature per `<component>_elediamcp_tools()`
beisteuert, prueft deshalb zweimal selbst: beim Zurueckgeben des Katalogs und
in `execute()`. Ein Werkzeug, das im Katalog fehlt, kann ein Agent nicht
raten; eines, das trotz geschlossenem Gate ausfuehrt, waere ein Lizenzloch.

**Ein Werkzeug darf nicht warten.** Agenten brechen einen Werkzeugaufruf nach
wenigen Sekunden ab -- der eLeDia.ai-Agent nach **zehn**. Wer ein
Sprachmodell im Aufruf befragt, ist darueber: der Kursautor brauchte fuer
seine Gliederung zwoelf Sekunden, Moodle antwortete mit `HTTP 200`, und der
Agent hatte laengst aufgegeben (`MoodleCallbackError: Moodle callback timed
out`). Ergebnis am 08.09.2026: zwei fertige Gliederungen in der Datenbank,
zwei Fehlermeldungen beim Nutzer, kein Kurs. Im Zugriffsprotokoll sieht so
ein Fall aus wie lauter Erfolge.

Deshalb: **jeder Aufruf kehrt sofort zurueck.** Lange Arbeit wird als
`adhoc_task` eingereiht, der Aufruf gibt eine Kennung zurueck, und ein
zweites, lesendes Werkzeug liefert den Fortschritt und am Ende das Ergebnis.
Der Web-Weg des Kursautors machte das von Anfang an richtig; nur sein
MCP-Werkzeug war die Ausnahme. Wer das Ergebnis der langen Arbeit braucht,
holt es aus dem Statuswerkzeug -- also muss dieses es auch herausgeben, sonst
ist die Kette unterbrochen.

Dazu gehoert, dass das Werkzeug **sagt, wie lange es dauert**. Ein Agent
fragt sonst ein paar Sekunden lang ab und gibt auf -- am 08.09.2026 vier
Abfragen in vier Sekunden, waehrend die Arbeit drei Minuten brauchte, weil
sie am Cron-Takt haengt. Die Antwort des Statuswerkzeugs nennt deshalb die
uebliche Dauer und sagt ausdruecklich, der Lehrkraft Bescheid zu geben statt
stumm aufzuhoeren.

**Ein Werkzeug verlangt kein Vokabular, das der Nutzer nicht hat.** Eine
Lehrkraft sagt „ein Kurs zur Bruchrechnung fuer die 6. Klasse", nicht
`targetgroup=secondary1, level=6, recipe=activating`. Wo ein Schema
Aufzaehlungen braucht, ist das die Aufgabe des **Agenten**, nicht des
Menschen -- also gehoert die Zuordnung von Alltagssprache auf die Schluessel
in die `description`, mit Beispielen („Azubis" -> `vocational`/`training`).
Und was sich sinnvoll ableiten laesst, wird abgeleitet statt verlangt: der
Kursautor waehlt den Nachweis nach der Anspruchsstufe des Lernziels, wenn
niemand einen nennt. Pflichtfelder sind nur die, die wirklich niemand
erraten kann. Abgeleitetes bleibt sichtbar -- es steht in der Gliederung, die
die Lehrkraft ohnehin bestaetigt.

### 6. KI aufrufen — `quota_aware_ai_manager`

**Nie direkt an Moodles `manager::process_action()`.** Der Aufruf funktioniert
dann tadellos — die Antwort kommt ja —, er zaehlt nur gegen kein Guthaben und
steht in keinem Suite-Audit. Vier Plugins taten das monatelang, ohne dass es
jemandem auffiel; ein Test in `local_elediaai_core` prueft es seitdem.

```php
use local_elediaai_core\local\quota_manager;
use local_elediaai_core\quota_aware_ai_manager;

$action = new generate_text($contextid, $userid, $prompt);
$response = quota_aware_ai_manager::process_action(
    $action,
    'local_meinplugin',                        // Frankenstyle, fuer die Buchung
    quota_manager::estimate_tokens($prompt),   // konservative Schaetzung
);
```

Der Einstieg reserviert vorher, verbucht bei Erfolg mit den echten Tokenzahlen
und gibt bei Misserfolg wieder frei. **Und er protokolliert.** Der Engpass ist
seit dem 19.09.2026 nicht nur die Guthabenstelle, sondern auch die
Protokollstelle: dieselbe Buchung schreibt eine Zeile nach
`local_elediaai_core_turn`. Deshalb kann das Audit sagen, welches Feature was
verbraucht hat — eine Frage, die Moodles Register nicht beantworten kann, weil
es keine Komponente fuehrt.

Wer einen eigenen Aufruf protokollieren will, ohne den Engpass zu benutzen,
ruft `turn_recorder::record()` direkt; es gibt die id der geschriebenen Zeile
zurueck und wirft nie in den Aufrufer. Ein fehlendes Protokoll darf keine
KI-Antwort verhindern.

Zwei Sonderfaelle:

- **`process_callback()`** ist fuer Aufrufe, die nicht ueber `core_ai` laufen:
  reservieren, eigenen Transport ausfuehren, verbuchen. Einziger Aufrufer ist
  die Chat-Engine. Ihre Turns landen in derselben Schicht B wie alle anderen —
  ohne Nutzerkennung, mit dem Pseudonym an deren Stelle, so vom Betreiber
  entschieden. Bis zum 19.09.2026 schrieb sie stattdessen ueber einen eigenen
  Recorder in Moodles Kernregister; die Zeilen von damals liegen dort noch und
  bleiben lesbar.
- **`require_text_provider()`-Muster:** die Frage, *ob* ein Anbieter da ist,
  geht weiter direkt an Moodles Manager. Das ist kein Aufruf und verbraucht
  nichts.

Wer den Kern so aufruft, braucht ihn als **harte Abhaengigkeit** in seiner
`version.php`. Fuer die optionalen Vertraege genuegt eine weiche Pruefung
(`class_exists` / `interface_exists`).

### 7. Aussehen — die gemeinsame Seitenhuelle

```php
use local_elediaai_core\output\plugin_page;
use local_elediaai_core\output\plugin_shell;

echo $OUTPUT->header();
plugin_page::open([
    'name' => get_string('shell_name', 'local_elediaai_core'),
    'tagline' => get_string('pluginname', 'local_meinplugin'),
    'subtitle' => '…',
    'homeurl' => (new moodle_url('/local/elediaai_core/index.php'))->out(false),
    'sectionnav' => section_nav::render_for_feature('meinfeature', section_nav::FEATURE_OVERVIEW),
] + plugin_shell::action_slots('local_meinplugin', $cansiteconfig, $settingsurl),
    plugin_page::MODIFIER_DEFAULT);   // DEFAULT | READING | WIDE
plugin_shell::content_open();
// … Inhalt …
plugin_shell::content_close();
plugin_page::close();
echo $OUTPUT->footer();
```

`action_slots()` setzt den Hilfe-Knopf selbst — auf das Handbuch des
Wegweisers, oder auf nichts, wenn der nicht installiert ist. Ein Plugin muss
`helpurl` nicht mehr angeben.

Das CSS der Huelle liegt in `local/elediaai_core/styles.css` und ist **nicht**
an einen Pfad gebunden. Das war es einmal, und genau deshalb entstanden zwei
Kopien der Huelle in anderen Plugins: wer sie benutzen wollte, musste das CSS
mitnehmen. Ein Plugin braucht sein eigenes Stylesheet nur noch fuer seinen
eigenen Inhalt.

Eigene Huellen haben heute noch `block_elediaai_tutor` (echte Erweiterung: zwei
zusaetzliche Breiten), `filter_eledia_translate`, `local_elediaai_sources`,
`local_literag` und `webservice_elediamcp`.

**Wer sich nachladen laesst, muss seinen Zustand in `$PAGE->url` tragen.**
`$PAGE->set_periodic_refresh_delay()` ist der Weg, eine laufende Erzeugung
ohne JavaScript aktuell zu halten. Das Ziel des Meta-Refresh baut Moodle aber
nicht aus der aufgerufenen Adresse, sondern aus `$PAGE->url` (siehe
`core\output\core_renderer::standard_head_html()`):

```php
$jobid = optional_param('job', '', PARAM_ALPHANUM);
$PAGE->set_url($jobid !== '' ? new moodle_url($url, ['job' => $jobid]) : $url);
```

Wird die Seiten-URL ohne den Parameter gesetzt, laedt sich die laufende Seite
selbst **ohne** ihren Auftrag nach, findet keinen und faellt auf ihren
Anfangszustand zurueck. Der Auftrag laeuft dabei weiter — er ist nur nicht
mehr erreichbar. Am 08.09.2026 im Kursautor aufgetreten; der Fehler sieht von
aussen aus wie „das Formular springt auf Anfang" und hat mit dem Formular
nichts zu tun.

Dazu gehoert die Gegenprobe: ein Auftrag, der Minuten laeuft, ueberlebt das
Fenster, das ihn gestartet hat. Wer das in seinen Texten zusagt, schuldet
auch eine Liste, ueber die man in ihn zurueckfindet — sonst haengt der einzige
Weg dorthin an der Adresszeile. Im Kursautor macht das
`output\job_list`.

## Feature-Discovery (Registry-Contract)

`registry::all()` ruft `core_component::get_component_classes_in_namespace(null, 'elediaai_core')` und akzeptiert jede Klasse, die `feature_provider` implementiert. Jeder Provider liefert ein Array von `descriptor`-Value-Objects. Discovery ist statefrei (kein Install-Hook, keine Registry-Tabelle, siehe `00-master.md` adr02).

Eigenschaften:

- **Kollision per id:** Existieren zwei Descriptors mit gleicher `id`, gewinnt der Live-Descriptor (`comingsoon=false`) ueber den Roadmap-Stub (`comingsoon=true`). So ersetzt z. B. ein installiertes Translate-Plugin (`id=translate`) den Translate-Stub automatisch.
- **Robustheit:** Nicht lesbare Provider-Dateien, ungueltige Klassen und Provider-Exceptions werden uebersprungen (`provider_file_is_readable()` + try/catch), damit ein defektes Schwester-Plugin die Suite nicht lahmlegt.
- **Reihenfolge:** `uasort()` alphabetisch nach Anzeigename — deterministisches Render.
- **Cache:** `self::$descriptors` (Request-Scope). `registry::reset_cache()` ist fuer Tests.

`registry::visible()` filtert zusaetzlich:

1. Per-Feature-Toggle `feature_<id>_enabled` (`is_enabled()`, Default `1`).
2. Descriptor-`capability` (falls gesetzt) gegen den System-Kontext.

Der `descriptor` (alle Felder readonly):

```php
new descriptor(
    id:                'qtype_aitext',   // stabiler Slug fuer URLs + Config-Keys
    component:         'qtype_aitext',   // Frankenstyle des besitzenden Plugins
    name:              'Free text',       // lokalisierter Anzeigename
    description:       '…',               // kurze Karten-Beschreibung
    launchurl:         null | moodle_url, // Tile-Deeplink; null = kein Einstiegspunkt
    icon:              'pen-fancy',        // Icon-Name (via lucide_icon aufgelöst, historischer FA-Name ohne 'fa-')
    capability:        null | 'mod/...:view',
    configurl:         null | moodle_url, // Einstellungsseite
    tier:              tier::FREE,         // Premium-Features brauchen Policy-Grant
    installrequired:   false,              // Install-Hinweis fuer fehlende externe Plugins
    installurl:        null | moodle_url,  // Ziel fuer Install-/Paket-Hinweis
    comingsoon:        bool,
    detaildescription: null | '…',        // laengerer Nutzentext
    keyfeatures:       ['…', '…'],         // kurze Funktionsliste
    audience:          registry::AUDIENCE_TEACHER,   // admin|teacher|designer|student
    imagename:         'feature_meins',     // SVG in pix/, ohne Endung
    kind:              descriptor::KIND_PAGE,        // PAGE | IN_COURSE
    usagehint:         null | '…',          // wo man eine Kurs-Faehigkeit findet
    origin:            null | ['title' => …, 'authors' => …, 'url' => …, 'note' => …],
);
```

### Wie Schwester-Plugins ihr Feature anmelden

Jedes Sub-Plugin liefert eine Klasse `<frankenstyle>\elediaai_core\feature_provider` (Datei `classes/elediaai_core/feature_provider.php`), die `feature_provider` implementiert und in `get_descriptors()` einen oder mehrere `descriptor` zurueckgibt. Installation genuegt — keine weitere Registrierung noetig.

Die Provider dieser Installation — 21 Features aus 21 Komponenten (Stand
07.09.2026, aus `registry::all()` erzeugt statt von Hand gepflegt):

| Komponente | Descriptor-id(s) |
| --- | --- |
| `block_elediaai_path` | `learningpath` |
| `block_elediaai_tutor` | `tutor` |
| `filter_eledia_translate` | `translate` |
| `local_activityfilter` | `activityfilter` |
| `local_elediaai_core` | `audit` |
| `local_elediaai_coursegen` | `coursegen` |
| `local_elediaai_guide` | `guide` |
| `local_elediaai_h5pauthor` | `h5pauthor` |
| `local_elediaai_questiongen` | `questiongenerator` |
| `local_elediaai_selfstudy` | `selfstudy` |
| `local_elediaai_sources` | `sources` |
| `local_elediaai_strategy` | `strategyhelper` |
| `local_elediaai_subscription` | `subscription` |
| `local_elediaai_tactics` | `tactics` |
| `local_elediaai_teachertools` | `teacher_tools` |
| `local_elediaai_tutor_premium` | `tutorpremium` |
| `local_literag` | `literag` |
| `mod_aifeedback` | `aifeedback` |
| `mod_elli` | `elli` |
| `qtype_aitext` | `qtype_aitext` |
| `webservice_elediamcp` | `mcp` |

Ohne Kachel bleiben absichtlich: `theme_elediaai` (zeichnet die Suite),
`local_elediaai_chatengine` (traegt sie), `local_aitransparency`
(protokolliert), `aiprovider_eledia` (die Anbindung) sowie drei
Zweitoberflaechen zu Kacheln, die es schon gibt — der Tactics-Block, die
qbank-Integration des Fragengenerators und die Tiny-Schaltflaeche der
Uebersetzung. Ein Test in `local_elediaai_core` haelt diese Liste fest und
meldet jedes Suite-Plugin, das neu dazukommt, ohne sich anzumelden.

Externe Provider, die **nicht** in dieses Repo kopiert werden sollen, koennen
dieselbe Konvention nutzen: sie bleiben eigene Plugins, und die Suite zeigt nur
den registrierten Descriptor.

## Launcher-Platzierung

`hook_callbacks::inject_launcher()` lauscht auf `before_standard_top_of_body_html_generation` (Hook in `db/hooks.php`, Prioritaet `500`, also nach dem Haupt-Launcher mit `600`, damit die KI-Pille rechts davon sitzt). Die gerenderte Pille ist ein direkter Link auf `/local/elediaai_core/index.php`; einzelne Funktionen werden im Launcher bewusst nicht gerendert (`00-master.md` adr03). JS verschiebt das Host-Element neben die LernHive-Launcher-Pille, mit Fallback auf `.usermenu` bzw. den Navbar-Container.

## Feature-Textebenen

Der Feature-Text ist bewusst dreigeteilt:

1. `descriptor::description` — kurze Karten-Beschreibung fuer `index.php`.
2. `descriptor::detaildescription` — entscheidungsorientierter Nutzentext fuer `feature.php`.
3. Support-Hub-Handbuch — funktionale Bedienhilfe im Help Hub.

So bleiben Uebersichtskarten scanbar, ohne das laengere Handbuch zu duplizieren.

## Section-Navigation

`section_nav` hat zwei Modi:

- `render(SECTION_OVERVIEW|SECTION_AUDIT)` — Suite-Scope.
- `render_for_feature(string $featureid, $current)` — Feature-Scope: „KI-Suite" → `index.php`, „Uebersicht" → die *Feature*-Startseite (`feature.php?id=...`), „Einstellungen" nur, wenn der Descriptor eine in-Shell-`configurl` mitbringt und der Nutzer `moodle/site:config` haelt. Admin-Tree-URLs (`/admin/`) werden als Settings-Ziel verweigert (ux-system §3.5).

## Feature-Shell-Action-Routing

`feature.php` haelt doppelte Body-Actions bewusst aus `feature_info.mustache` heraus. Feature-Seiten nutzen:

- Zone-A-Hilfe ueber `plugin_shell::action_slots($descriptor->component, ...)`, damit der Help Hub das Sub-Plugin-Handbuch oeffnet (z. B. `local_elediaai_questiongen` fuer **Fragen**) statt des Suite-Handbuchs. Nur fuer Component `local_elediaai_core` wird die `helpurl` explizit auf `/local/elediaai_core/help.php` gesetzt.
- Zone-A-Einstellungen nur, wenn `configurl` eine plugin-eigene in-Shell-URL ist; `/admin/`-URLs werden als Cog-Ziel unterdrueckt (`$shellsettingsurl`-Logik).
- Feature-Section-Navigation zur Rueckkehr in die KI-Suite und zum Wechsel zwischen Uebersicht/Einstellungen.

## Audit-Architektur

### Reportbuilder-Entity

`ai_action_audit` erweitert `core_ai\reportbuilder\local\entities\ai_action_register` und erbt damit die Core-Spalten (Provider, Aktion, `timecreated`, `success`, Token-Spalten). Darueber legt sie Praesentationsspalten:

| Spalten-id | Quelle | Render (Anzeige) | Render (CSV) |
| --- | --- | --- | --- |
| `actor_anonymized` | `userid` + `contextid` | Rollen-/Kontext-Label statt Klarname (wenn Anonymisierung aktiv) | dito |
| `action_icon` | `actionname` | kompaktes Action-Icon mit Tooltip | roher Action-Name |
| `context_link` | `contextid` | verlinkter Kontextname, wenn Moodle eine URL liefert | Kontextname |
| `prompt` | `COALESCE` ueber `prompt` der drei Detailtabellen | Augen-Icon-Button + `<template>` mit Volltext | gekuerzter 240-Zeichen-Text |
| `generatedcontent` | `COALESCE` ueber `generatedcontent` | Augen-Icon-Button + `<template>` | gekuerzter 240-Zeichen-Text |
| `provider_display` | `provider` | Kurzlabel (z. B. `OpenAI`) | dito |
| `model` | `model` | Text | dito |
| `success_icon` | `success` + `errormessage` | Häkchen gruen / X rot (inline Lucide-SVG); Fehlertext im Modal | „Ja"/„Nein", Fehlertext bei Fehlern |
| `tokens` | `COALESCE` ueber `prompttokens` + `completiontoken` | `prompt / completion` mit Tooltip, `—` bei `0/0` | dito |

Details:

- Der Entity-Name (Basename der Klasse) ist `ai_action_audit`; der System-Report referenziert Spalten als `ai_action_audit:prompt` etc. Die geerbten Core-Spalten bleiben verfuegbar, sind aber nicht Teil des Default-Spaltensets.
- Der Anzeige/Download-Schalter ist `ai_action_audit::is_downloading_now()` und liest `optional_param('download', '', PARAM_ALPHA)`. So bleiben die Callback-Signaturen frei von Tabellen-State.
- Moodle 5.2 kann formatierte Tokenwerte als String an Callbacks reichen, daher akzeptiert `format_tokens_cell()` `int|string|null` und castet die Row-Felder selbst.
- Sichtbarkeit: `format_success_icon()` gibt im Downloadpfad nur dann den Fehlertext aus, wenn `audit_show_error` aktiv ist; `audit_config` kapselt diese Checks.

### Seitenstruktur

- `audit.php` — Uebersicht: technisches Summary-Panel + didaktisches Vorschau-Panel + zwei Pfad-Karten (Open technical / Open didactic). Mountet keinen Report.
- `audit_technical.php` — mountet den System-Report ueber `system_report_factory::create(audit_report::class, $context)`, rendert die allowlisteten Schnellfilter (`participants`, `generate_text`, `summarise_text`, `explain_text`, `failed`) und laedt das AMD-Modul `audit_preview`.
- `audit_didactic.php` — rendert aggregierte Support-Kontexte (`audit_page::support_contexts()`), `limit` zwischen 10 und 100, plus eine „Naechster Ausbau"-Liste.
- `audit_settings.php` — Moodle Form (`classes/form/audit_settings.php`) + `set_config`-Persistenz, gerendert in der Plugin-Shell.

### Aggregations-Helper

`audit_page::overview_data()` zaehlt Gesamt-/Fehler-/Support-Requests und summiert Tokens ueber alle drei Detailtabellen (`completiontoken` als Antwort-Tokenfeld). `audit_page::support_contexts()` gruppiert `summarise_text`/`explain_text` nach `contextid` und sortiert nach Anzahl. Beide vermeiden eine eigene Tabelle und lesen direkt aus den Core-Tabellen.

### Augen-Icon-Modal (`amd/src/audit_preview.js`)

Jede Prompt-/Antwort-/Fehlerzelle rendert einen Button mit `data-action="lh-audit-preview"` und ein nicht gerendertes `<template class="lh-audit-preview-content">` mit dem `s()`-escapeten Volltext. Das AMD-Modul bindet **einen** delegierten Click-Listener auf `document` (Reportbuilder rerendert Zeilen bei Filter/Sort/Page), liest Titel + Template-`innerHTML`, wrappt den Body in `<pre class="lh-audit-preview-body">` und oeffnet ein `core/modal_cancel`. Fehlgeschlagene Zeilen werden nach dem Render mit `lh-audit-row--failed` markiert (rote Zeilenbetonung, scoped unter `.lh-ai-audit`).

Hardening (2026-05-25):

- Schnellfilter-Parameter werden auf Seitenebene allowlistet, bevor sie den System-Report erreichen.
- Der Teilnehmerfilter ist ueber den Aktionskontext-Pfad begrenzt, nicht ueber beliebige historische Rollen sitewide.
- Provider-Discovery ist defensiv (siehe Registry).
- Der Fehlertext-Export respektiert die Audit-Sichtbarkeitseinstellung.

## Stale-/Regenerate-Helper (`classes/local/stale_marker.php`)

`stale_marker` zentralisiert das Stale/Regenerate-Pattern (`feat09`): ein KI-Output wird aus Eingabeteilen (Prompt, System-Prompt, Modell) erzeugt; aendern sich die Inputs, ist der Output „stale" und der Nutzer bekommt eine Regenerate-/Reset-Aktion angeboten.

Normalisierungs-Contract (stabil — bestehende Hashes muessen weiter matchen):

- Jedes Teil wird zu String gecastet und getrimmt.
- **Ein** Teil → `sha1(trim($value))` ohne Wrapping. Das ist byte-kompatibel zum historischen `mod_aifeedback`-Hash `sha1(trim((string) $prompt))`, daher keine Migration noetig.
- **Mehrere** Teile → `sha1` ueber die getrimmten Teile, verbunden mit dem Record-Separator `\x1e`. Der Separator kann in normalem Text nicht vorkommen, daher kollidieren unterschiedliche Grenzen nie.
- Array-Keys werden ignoriert; nur die **Reihenfolge** der Werte zaehlt.

API:

- `stale_marker::hash(array $parts): string` — 40-stelliger sha1-Hex.
- `stale_marker::is_stale(?string $storedhash, array $currentparts): bool` — ein leerer/fehlender gespeicherter Hash gilt als „nicht stale" (rueckwaertskompatibel).

### Nutzung in anderen Plugins

| Plugin | Datei | Nutzung |
| --- | --- | --- |
| `mod_aichat` | `view.php` | `is_stale($storedhash, [(string) ($aichat->systemprompt ?? '')])` — Drift des System-Prompts erkennen. |
| `mod_aichat` | `classes/external/send_message.php` | `hash([(string) ($aichat->systemprompt ?? '')])` — Hash beim ersten Turn pinnen (Tabelle `aichat_thread`, Feld `systemprompthash`). |
| `mod_aifeedback` | `classes/local/submission_sync.php` | `hash([(string) $aifeedback->prompt])` + `is_stale(...)` — Prompt-Fingerprint fuer Stale-Banner und Regenerate. |

`local_elediaai_questiongen` nutzt fuer `coursecontents` weiterhin seinen eigenen Eingabe-Fingerprint (Quell-cmid + Inhalts-Hash); der gemeinsame Helper deckt die prompt-basierten Faelle ab.

## Plugin-Layout

```
local_elediaai_core/
├── classes/
│   ├── feature/
│   │   ├── descriptor.php
│   │   ├── feature_provider.php
│   │   └── registry.php
│   ├── form/
│   │   └── audit_settings.php
│   ├── hook_callbacks.php
│   ├── elediaai_core/
│   │   └── feature_provider.php
│   ├── local/
│   │   ├── audit_config.php
│   │   ├── audit_page.php
│   │   ├── quota_manager.php
│   │   └── stale_marker.php
│   ├── output/
│   │   └── section_nav.php
│   ├── privacy/
│   │   └── provider.php
│   └── reportbuilder/local/
│       ├── entities/ai_action_audit.php
│       └── systemreports/audit.php
├── amd/{src,build}/audit_preview*.js
├── db/{hooks.php, access.php, install.xml, upgrade.php}
├── docs/00-master.md … 05-quality.md
├── lang/{en,de}/local_elediaai_core.php
├── templates/{launcher.mustache, feature_info.mustache}
├── audit.php, audit_technical.php, audit_didactic.php, audit_settings.php
├── feature.php, help.php, index.php
├── lib.php, settings.php
└── version.php
```

## Design-System

`local_elediaai_core/styles.css` beginnt mit einem `:root`-Block. Das ist die
Design-Schicht der Suite (adr05): **hier** entstehen die Werte, überall sonst
werden sie gelesen. Jedes Suite-Plugin hängt per `$plugin->dependencies` an
Core, es gibt also keine Installation, in der die Token fehlen könnten —
Rückfallwerte in `var(--x, #abc)` sind deshalb weder nötig noch erwünscht.

Das Theme ist die **Haut**, nicht die Quelle. `theme_elediaai` überschreibt in
`scss/elediaai/_tokens.scss` genau fünf Token aus seinen eigenen Einstellungen
(`--eai-bg`, `--eai-accent`, `--eai-accent-wash`, `--eai-brand`, `--eai-font`)
und sonst nichts. Ohne das Theme sehen die Plugin-Flächen gleich aus, nur der
Rahmen ist Boosts. Gemessen und belegt: `ci/README.md`, Abschnitt „Messlauf
gegen zwei Themes".

### Die Skalen

**Schrift — acht Stufen, benannt nach der Rolle, nicht nach der Größe.**

| Token | Wert | Wofür |
|---|---|---|
| `--font-size-display` | 2.5rem | Anmeldung, Dashboard-Hero |
| `--font-size-h1` | 2rem | Seitentitel |
| `--font-size-h2` | 1.5rem | Abschnitt einer Seite |
| `--font-size-h3` | 1.25rem | Untertitel, Kartengruppe |
| `--font-size-h4` | 1.125rem | Kartentitel, kleine Überschrift |
| `--font-size-body` | 1rem | Fließtext, Bedienelemente, Eingaben |
| `--font-size-small` | 0.875rem | gedämpfter Nebentext, Metazeilen |
| `--font-size-caption` | 0.75rem | Marken, Zustände, Versalien |

Für Bedienoberflächen beginnt die Skala bei `body`. `caption` ist die
Untergrenze — darunter fängt Text an, unlesbar zu werden.

**Abstand — ein Achter-Rhythmus**, `--eai-space-1` bis `-8`, benannt nach dem
Zweck: eng Zusammengehöriges, verwandte Bedienelemente, Überschrift zu ihrem
Inhalt, innerhalb eines Abschnitts, zwischen Abschnitten. Wo ein Abstand nicht
auf der Liste steht, ist das ein Hinweis auf das Layout und nicht auf eine
weitere Zahl.

**Form** — `--eai-radius`, `--eai-radius-lg`, `--eai-radius-pill`. `0` und
`50%` sind Formen und keine Werte; die dürfen roh dastehen.

**Farbe** — Flächen (`--eai-bg`, `--eai-surface`, `--eai-line`), Schrift
darauf (`--eai-fg`, `--eai-muted`, `--eai-faint`), Akzent und Marke. Rollen,
keine Farbnamen: wer `--eai-muted` liest, bekommt „gedämpfter Text" und nicht
„grau".

**Herkunft** — `--eai-ai-wash` und `--eai-ai`. Ein Lavendel, das quer durch
die Suite markiert „hier ist KI im Spiel": an Funktionskacheln,
Empfehlungsmarken, Bausteinen des Strategieassistenten, im Launcher. Es ist
kein Zustand und kein Akzent, sondern eine Herkunftsangabe. Den Namen
`--lh-pastel-ai` gab es längst; definiert war er nirgends, sechs Stellen
hielten ihn nur über ihren Rückfallwert am Leben.

**Zustand** — `--eai-success`, `--eai-warning`, `--eai-danger`, jeweils mit
einem `-wash` als Fläche und der starken Farbe als Text darauf. Sie gehören
**nicht** zum Akzent: der Akzent sagt „hier entlang", ein Zustand sagt „so
steht es". Bis zum 05.09.2026 führten die Plugin-Hüllen eigene Werte — Erfolg
war ein Türkis mit 2,71:1 auf Weiß, Warnung war die Markenfarbe selbst mit
2,58:1. Beides zu schwach für Text nach WCAG 1.4.3, und eine Warnung, die
aussieht wie der Akzent, ist keine. Die Nachfolger tragen 5,07:1 (Erfolg),
7,09:1 (Warnung) und 6,60:1 (Gefahr).

### Symbole: zwei Sätze, und sie sind nicht austauschbar

**Im Inhalt: Lucide.** Kachelsymbole, Kapitel im Wegweiser, Einträge im
Launcher, die Zeichnungen auf den Kacheln — aus
`local_elediaai_core/pix/lucide.svg` über `lucide_icon::render()`.

**Im Chrome: Font Awesome**, also Moodles eigener Satz. Alles, was dauerhaft
in der Leiste oder in einer Bildschirmecke steht. Die Glocke kommt als
`fa-bell` aus dem Kern; wer sich danebenstellt, stellt sich in diese Reihe.
Über `pix_icon()` beziehungsweise den pix-Helfer, damit Moodles Symbolsystem
die Zuordnung macht und nicht das Plugin.

**Gefüllt oder Kontur entscheidet das Paar, nicht die Regel.** Eine gefüllte
Glyphe trägt bei 15px sichtbarer Zeichnung deutlich mehr Farbe als eine
Kontur derselben Größe; nebeneinander wirkt die Kontur dünn, auch wenn beide
gleich groß gemessen sind. Zeichen, die zusammen stehen, müssen deshalb
dasselbe Gewicht haben — quer über die ganze Oberfläche lässt sich das nicht
verordnen, weil der Kern seine eigenen mitbringt.

Am 09.09.2026 durchgespielt an den beiden Knöpfen unten rechts: erst standen
sie als Lucide-Konturen neben einer gefüllten Glocke und wirkten dünn, dann
beide gefüllt — und das gefüllte `fa-circle-question` war ein Ring im Ring,
weil der Knopf schon der Kreis ist. Geworden sind es ein bloßes `fa-question`
und eine Kontur-Blase: zwei leichte Zeichen, die zueinander passen.

Zwei Zahlen, die dabei entstanden sind und beim nächsten Mal Zeit sparen: der
Launcher zeigt **15px** sichtbare Zeichnung, die Glocke steht auf
`font-size: 16px`.

**Ein Fallstrick beim Dokumentieren:** ein Mustache-Kommentar endet an den
ersten `}}`. Wer den pix-Helfer darin ausschreibt, beendet den Kommentar
mittendrin — der Rest steht danach als Text auf der Seite. Einmal passiert,
am selben Tag.

### Was ein Plugin darf und was nicht

**Darf:**

- Token lesen: `font-size: var(--font-size-small)`.
- Eigene Token **aus** Suite-Token ableiten, wenn es einen eigenen Namensraum
  führt: `--eac-accent: var(--eai-accent)`. So bleibt eine plugin-eigene
  Stellschraube erhalten, ohne dass die Farbe zweimal existiert.
- Rohe Werte dort setzen, wo es keine Rolle gibt: Zustandsfarben (Fehler,
  Erfolg), die Größe eines Zeichens, `0` und `50%`.

**Darf nicht:**

- Eine Schriftgröße als Zahl schreiben. 13px, 15px und 17px gibt es in dieser
  Suite nicht.
- Eine Rollenfarbe als Hex-Wert schreiben.
- `var(--eai-x, #abc)` mit Rückfallwert. Jedes Plugin hängt an Core; der
  Rückfall ist eine zweite Wahrheit, die niemand pflegt.
- `!important` ohne Kommentarzeile darüber, die sagt, wogegen es sich wehrt.

### Wer das nachhält

- **`ci/design-lint.php`** prüft die Quelle — vier Regeln, läuft je Plugin in
  der Stage `lint`. Liest `.css`, `.scss`, `.php` und `.mustache`, denn die
  Suite gibt CSS auch aus PHP und aus `<style>`-Blöcken aus.
- **`ci/design-measure.mjs`** prüft das Ergebnis im Browser und vergleicht zwei
  Themes miteinander.
- **Die Entwicklerseite** `local/elediaai_core/developer.php` zeigt denselben
  Vertrag an der laufenden Installation: aufgelöste Token mit ihrer Herkunft,
  die Skalen gerendert, die Bausteine live. Sie hängt am Setting
  `local_elediaai_core/developerdocs` und ist ohne `moodle/site:config`
  unerreichbar.

Beides zusammen ist der Nachweis: der Lint sagt, was geschrieben steht, der
Messlauf, was ankommt. Ein Plugin kann sauber geschrieben sein und trotzdem
falsch aussehen.

### Eine Reihe Knöpfe ist eine Flex-Zeile, keine Kette von Abständen

`theme_elediaai` überschreibt `.btn` auf `display: inline-flex` mit
`min-height: 2.25rem`; Bootstrap richtet denselben Knopf `vertical-align:
middle` aus. Ein Element daneben, das **kein** `.btn` ist — typisch ein
`<form class="d-inline-block">` um einen Submit herum — hängt dagegen auf der
Grundlinie und hat keine Mindesthöhe. Die beiden stehen dann sichtbar
versetzt, und zwar nur im Suite-Theme: unter Boost fällt es nicht auf.

Deshalb: mehrere Aktionen nebeneinander kommen in **einen** Container,

```php
['class' => 'd-flex flex-wrap align-items-center gap-2 mt-3']
```

und die einzelnen Knöpfe tragen **keine** eigenen `mt-*`/`me-*`. Das trägt
auch den zweiten Fehler ab, den die Einzelabstände begünstigen: ein Knopf mit
`mt-3` neben einem ohne. Gefunden am 08.09.2026 im Kursautor, siehe
`local/elediaai_coursegen/index.php`.

**Nebenbefund:** `mr-2`/`ml-2` sind Bootstrap 4. Moodle 5 liefert sie noch
über `theme/boost/scss/moodle/bs4-compat.scss` aus, markiert sie aber mit
`deprecated-styles()` — im Theme-Designer-Modus und auf jeder Behat-Site
bekommt so ein Element einen roten Rahmen und den eingeblendeten Text
„Deprecated style in use (.mr)". Die Suite schreibt `me-*`/`ms-*`.

---

## Erweiterungspunkt: neues Feature anbinden

1. Im neuen/bestehenden Sub-Plugin eine Klasse `<frankenstyle>\elediaai_core\feature_provider` in `classes/elediaai_core/feature_provider.php` anlegen, die `local_elediaai_core\feature\feature_provider` implementiert.
2. In `get_descriptors()` einen `descriptor` mit stabiler `id`, `component`, lokalisiertem `name`/`description`/`detaildescription`, `keyfeatures`, `icon`, optionaler `launchurl`, `capability` und `configurl` liefern.
3. Soll eine Roadmap-Kachel ersetzt werden, dieselbe `id` wie der Stub verwenden und `comingsoon` weglassen/auf `false` setzen.
4. Eine in-Shell-Einstellungsseite als `configurl` liefern, damit das Zahnrad in Zone A erscheint. Wenn das Plugin eigenstaendig ohne Suite/Core laufen muss, diese Seite mit einem Shell-Fallback bauen. `/admin/`-URLs werden in der Feature-Section-Nav abgewiesen.
5. Caches leeren. Die Funktion erscheint automatisch in der Suite; kein Code im Umbrella-Plugin noetig.

## Abhaengigkeiten und Integrationspunkte

- Keine UI-Runtime-Abhängigkeit: `output\plugin_page`, `output\plugin_shell`,
  `output\icon_kit` und das Handbuch-Rendering gehören zu Core. Lediglich der
  `lernhive_session_compose`-Hook bleibt eine defensive, optionale Integration
  (ADR-P15 Stufe 2).
- `core_ai` — `manager::process_action()` (von Sub-Plugins und vom Session-Composer genutzt), `ai_action_register` + Detailtabellen (Audit-Quelle), Capability `moodle/ai:viewaiusagereport`.
- `core_reportbuilder` — `system_report_factory` als Host des Audit-Reports.
- `\core\lock` — Lock-Factory `local_elediaai_core_quota` fuer die atomare Quota-Reservierung.
- `aiprovider_eledia`, `block_elediaai_tutor` — Konsumenten von `quota_manager`; `local_elediaai_subscription\ai_quota` laeuft optional als zweite, workspace-weite Quota daneben.
- Hook `before_standard_top_of_body_html_generation` — Launcher-Injektion.

## Capabilities

`db/access.php` ist leer: seit dem Wegfall des kursgebundenen Teacher-Dashboards (2026-09-06) fuehrt das Plugin keine eigene Capability mehr. Der Audit-Zugang nutzt weiterhin die Core-Capability `moodle/ai:viewaiusagereport` bzw. den Admin-Only-Modus aus `audit_config`. Site-Config-Aktionen (Settings-Cog, Audit-Einstellungen, Quota-Limits) sind ueber `moodle/site:config` gegated. Die Token-Quota selbst ist **keine** Capability-Frage: sie wird ueber den Rollen-Archetyp-Bucket aus `quota_manager::role_bucket()` aufgeloest.

## Technische Constraints

- Das Plugin benoetigt Moodle-Bootstrap; isolierte Ausfuehrung ist nur begrenzt moeglich.
- Ausser den Quota-Zaehlern legt es keine eigenen Daten an; das Audit ist nur so vollstaendig wie die Core-`core_ai`-Protokollierung.
- Die Token-Quota greift nur, wenn der aufrufende Provider sie aufruft. Sie ist ein Vertrag zwischen Suite und Provider, keine Core-Schranke: ein `core_ai`-Provider, der `quota_manager` nicht nutzt (z. B. `aiprovider_openai` direkt), wird nicht gedeckelt.
- Die Quota-Reservierung braucht eine funktionierende `\core\lock`-Factory; ohne Lock wird der Request abgewiesen statt ungezaehlt durchgelassen.
- UI-Ausgaben sollen ueber die LernHive Plugin-Shell, Mustache und AMD laufen — kein eigenes Chrome.
- Provider-Verwaltung ist `core_ai`-Hoheit; das Plugin pflegt keine API-Keys.

## CI-Fehlerdiagnose

Die repositoryweite Forgejo-CI schreibt bei fehlgeschlagenen Testschritten die
Ausgabe zusätzlich nach `$RUNNER_TEMP/ci-output.log`. Ein nachgelagerter,
fehlertoleranter Schritt extrahiert daraus die ersten relevanten Zeilen und
setzt über die Forgejo-API den Commit-Status `ci-fixer/error`; dessen
`target_url` verweist auf den konkreten Lauf. Der CI-Fixer erhält bei einem
roten `main`-Push außerdem genau einen POST an das Secret
`CI_FIXER_WEBHOOK_URL`. Die URL wird weder im Workflow noch in einem Status
gespeichert. Da Forgejo-Job-Logs und Artifacts für den CI-Fixer nicht
zuverlässig lesbar sind, ist der Commit-Status der maschinenlesbare
Fehlerkanal. Der gemeinsame Helper erzeugt das Status-JSON mit dem in allen
fünf Prüf-Images verfügbaren Node.js; PHP ist dafür keine implizite
Laufzeitabhängigkeit. `scripts/ci-fixer-status.test.mjs` prüft den Boundaries-
Fehlerpfad gegen einen lokalen HTTP-Endpunkt und erwartet genau einen
erfolgreichen Status-POST.

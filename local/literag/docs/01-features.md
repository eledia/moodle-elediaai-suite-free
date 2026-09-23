# eLeDia.ai LiteRAG — Features

> Was das Plugin leisten soll (Intent). Umsetzung in `03-dev-doc.md`,
> Nutzerführung in `02-user-doc.md` (EN) / `02-user-doc.de.md` (DE),
> Leitentscheidungen in `00-master.md`.

## Ziel

Tutor-Antworten hängen von freigegebenen Moodle-Kursinhalten und den
Moodle-Berechtigungen des anfragenden Lernenden ab — nicht nur vom allgemeinen
Modellwissen. LiteRAG liefert dafür Ingestion, Retrieval, Konversationen,
optionale Live-Moodle-Daten und die LLM-Anbindung als Moodle-lokalen Dienst,
ohne externe RAG-Infrastruktur.

## feat01 Ingestion-Endpoint (RAG-Ingestion-API v1.2)

- `ingest.php` nimmt `POST /documents/upsert` und `/documents/delete` von
  `local_ragingest` entgegen (plus `health`); authentifiziert per
  `X-API-Key`-Header (Konstantzeit-Vergleich).
- Upserts sind idempotent (Contenthash: unverändert → kein Re-Chunk);
  Deletes exakt oder prefix-scoped (die `:`-Grenze verhindert, dass `cmid99`
  auf `cmid990` matcht).

**Akzeptanz:** Falscher/fehlender Key → 401; unkonfigurierter Key → 503
`not_configured`; unbekannte Route → 404; gleicher Inhalt zweimal ingestiert
erzeugt keine doppelten Chunks; Prefix-Delete löscht nur den Prefix-Scope.

## feat02 Embeddings-freies, berechtigungssicheres Retrieval

- `retriever` nutzt die native DB-Volltextsuche (PostgreSQL/MySQL/MariaDB/
  MSSQL) mit portablem `LIKE`-Fallback; `query_normaliser` macht aus der
  Frage Keyword-Terme (EN/DE-Stopwörter); optionales LLM-Reranking
  (`enable_rerank`, degradiert bei Fehlern zur Eingabereihenfolge).
- `permission_filter` prüft jeden Kandidaten gegen die Moodle-Sichtbarkeit
  des anfragenden Nutzers (`get_fast_modinfo()` / `uservisible`).

**Akzeptanz:** Ein Chunk aus einem für den Nutzer unsichtbaren Kursmodul wird
nie zurückgegeben; ohne Volltext-Index funktioniert der `LIKE`-Fallback;
Kandidaten-/Kontextanzahl folgen den Settings.

## feat03 Fundierte Tutor-Antworten mit Quellen (`tutor_chat`)

- `mcp.php` (JSON-RPC 2.0 `tools/call`) bedient `block_elediaai_tutor`;
  `prompt_builder` erzwingt den Grounding-Kontrakt (Antwort aus den
  Kontext-Chunks, Zitate `[S#]`), berücksichtigt Persona, `user_lang` und
  `answer_style` (explain|hint|quiz) als dominante Direktive — Hint verrät
  nie die Lösung, Quiz stellt Fragen (strikt seit 0.5.1).
- Quellen werden pro Dokument dedupliziert und nummeriert; `[S#]`-Marker
  mappen 1:1 auf die Quellkarten (0.3.1). Antworten erzeugt der
  OpenAI-kompatible `llm\client`.

**Akzeptanz:** Antwort enthält nur belegte Aussagen mit `[S#]`-Zitaten;
Hint-/Quiz-Modus ändert das Verhalten nachweislich; mehrere Passagen desselben
Moduls ergeben eine Quellkarte.

## feat04 Konversationen, Verlauf und Opt-in-Memory

- `conversation_repository` persistiert Konversationen/Nachrichten owner-scoped
  (öffentliche `conversation_id`); `tutor_get_history` liefert den Verlauf
  inklusive strukturierter `sources` je Assistant-Nachricht (0.3.0).
- `memory_store`: Langzeit-Memory strikt opt-in (`enable_memory` + per-Request
  `ltm_enabled`); Opt-out löscht bereits Gespeichertes
  (`tutor_set_memory_optin`). `topic_registry` pflegt je Kurs ein kanonisches
  Topic-Label-Register für Analytik (`tutor_recluster_questions`).

**Akzeptanz:** Nur der Besitzer kann eine Konversation laden/fortsetzen/
löschen; Resume zeigt dieselben Quellkarten wie die Live-Antwort; ohne Opt-in
wird nichts gemerkt, Opt-out leert den Bestand.

## feat05 Live-Moodle-Tools (agentisches Tool-Calling, optional)

- Bei aktiviertem `enable_mcp_tools` ruft der `agent` die read-only
  `moodle_*`-Tools von `webservice_elediamcp` als der Lernende auf (Abgaben,
  Fristen, Noten, Kalender, Fortschritt …); begrenzter Loop
  (`max_tool_iterations`, Deadline), Bootstrap mit
  `moodle_verify_user_context`.
- Schreiben (`enable_write_tools`, Default aus): ein Turn liefert immer nur
  eine **Vorschau** (`confirm=false`); gesendet wird erst nach explizitem Ja
  des Lernenden im Folge-Turn (`confirmation::is_yes`, `pendingaction`).

**Akzeptanz:** Ohne Tools/Endpoint degradiert der Chat zu RAG-only; ein
einzelner Turn kann nie senden; Loopback nur gegen `$CFG->wwwroot`
(request-`system_url` wird ignoriert).

## feat06 Datenschutz, Löschung und Retention

- Privacy-API-Provider (Export/Löschung); `user_eraser` als Single Source of
  Truth für `tutor_delete_user_data` **und** den Privacy-Provider;
  `tutor_delete_conversation` löscht einzelne Gespräche.
- Scheduled Task `prune_logs` (täglich 04:17) räumt Query-Log und abgelaufene
  Konversationen gemäß Retention-Settings; `log_verbosity` steuert, was
  überhaupt geloggt wird. Secrets/Tokens werden nicht geloggt.

**Akzeptanz:** Privacy-Export enthält Transkripte/Memory/Logs des Nutzers;
Tool-Löschung und Privacy-Löschung entfernen exakt dieselben Zeilen; nach
Ablauf der Retention verschwinden Alt-Daten per Task.

## feat07 Admin-Oberfläche mit Plugin-Shell und Handbuch

- Settings-Seite (Website-Administration → Plugins → Lokale Plugins →
  LiteRAG) mit sechs Sektionen (Verbindung, LLM, Retrieval, Live-Tools,
  Tool-Namen, Datenschutz) und Anzeige der Endpoint-URLs zum Kopieren.
- Optionale LernHive-/eLeDia.ai-Plugin-Shell (`output\shell` +
  `amd/src/settings_shell.js`) mit Moodle-nativem Fallback, wenn
  `local_lernhive` fehlt; `help.php` rendert das Handbuch
  (`02-user-doc.de.md` bei DE-Sprache, sonst `02-user-doc.md`),
  capability-geschützt (`local/literag:manage`).

**Akzeptanz:** Ohne `local_lernhive` bleibt die Settings-Seite voll bedienbar
(Fallback); Endpoint-URLs stimmen mit `ingest.php`/`mcp.php` überein;
`help.php` zeigt die Sprache passend zur Nutzersprache.

## Nicht-Ziele (aktuell)

- **Keine Embeddings, keine Vektor-Datenbank** — bewusst klassische
  Volltextsuche (adr02).
- **Kein Lerner-Frontend** — die Chat-Oberfläche ist `block_elediaai_tutor`;
  LiteRAG ist reines Backend.
- Keine Moodle-Webservice-Funktionen (`db/services.php` existiert nicht);
  die Schnittstellen sind die beiden HTTP-Endpunkte.
- Keine Behat-Features (siehe `05-quality.md` / q03 in `04-tasks.md`).

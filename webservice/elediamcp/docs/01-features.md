# Moodle MCP Web Service — Features

> Was das Plugin leisten soll (Intent). Umsetzung in `03-dev-doc.md`,
> Nutzerführung in `02-user-doc.md` (EN) / `02-user-doc.de.md` (DE),
> Leitentscheidungen in `00-master.md`.

## Ziel

Die Konfigurationsoberflaeche nutzt die breite Suite-Shell (72rem); die
Hilfeseite bleibt fuer Fliesstext auf 58rem begrenzt.

AI-Agenten und MCP-Clients erhalten einen einzigen, standardkonformen Zugang zu
Moodle: eine kuratierte, stabile, LLM-freundliche Tool-Schicht über Moodles
Webservices — jeder Aufruf läuft als der authentifizierte Nutzer und kann nie
mehr sehen oder tun, als dieser Nutzer in Moodle selbst dürfte.

## feat01 MCP-Server (Streamable HTTP, Multi-Version)

- Endpoint `server.php`: JSON-RPC 2.0 über POST, Server-Info über GET,
  CORS-Preflight über OPTIONS; `Authorization: Bearer`-Auth.
- Methoden: `initialize`, `notifications/initialized`, `ping`, `tools/list`
  (paginiert, opaque Cursor), `tools/call` und `prompts/get`; `resources/list`
  bleibt reserviert.
- Protokoll-Verhandlung über `initialize` und `MCP-Protocol-Version`-Header:
  `2025-11-25`, `2025-06-18`, `2025-03-26` (Legacy mit `{result: …}`-Envelope).

**Akzeptanz:** Unbekannte Protokollversion → HTTP 400 mit Liste der
unterstützten Versionen; unauthentifizierter Request → 401 mit
`WWW-Authenticate` + OAuth-Discovery-URL; Legacy-Client erhält das Envelope.

## feat12 MCP-Prompts (seit 1.7.0)

`prompts/list` liefert die verfügbaren Workflow-Vorlagen
`kurs_aus_dokument`, `wochenueberblick`, `kurs_health_check`,
`bewertungs_session` und `kursmaterial_zusammenfassen`. Ein Prompt wird nur
angezeigt, wenn alle von ihm orchestrierten Tools für das Token verfügbar sind.
`prompts/get` validiert Argumente und liefert eine Nachrichtenfolge. Schreibtools
müssen weiterhin ihren Vorschau-/Bestätigungsablauf verwenden; die Dokumentquelle
bei `kurs_aus_dokument` bleibt clientseitig.

## feat02 Kuratierter AI-nativer Tool-Katalog (lesend)

- Stabile, denormalisierte Read-Tools: Identität (`moodle_me`,
  `moodle_verify_user_context`), Personen (`moodle_find_user`), Kurse
  (`moodle_my_courses`, `moodle_search_courses`, `moodle_list_course_categories`, `moodle_course_contents`,
  `moodle_get_resource`, `moodle_search_content`), Kommunikation
  (`moodle_get_announcements`, `moodle_forum_discussions`,
  `moodle_calendar_upcoming`), Lernstand (`moodle_due_work`,
  `moodle_my_assignments`, `moodle_my_grades`, `moodle_my_progress`,
  `moodle_quiz_info`, `moodle_my_submission_files`).
- Jedes Tool deklariert Titel, Beschreibung, Input-/Output-JSON-Schema und
  MCP-Annotationen (`readOnlyHint` etc.).

**Akzeptanz:** Tools respektieren Sichtbarkeit, Enrolment, Gruppenmodi,
Privacy-Einstellungen (z. B. `maildisplay`) und versteckte Bewertungen;
eigene Daten (Quiz-Versuche, Abgaben) sind strikt self-scoped.

## feat03 Write-Tools mit Zwei-Schritt-Bestätigung

- `moodle_send_message`, `moodle_create_user`, `moodle_create_course`,
  `moodle_update_course`, `moodle_enrol_user`, `moodle_create_activity`,
  `moodle_update_activity`, `moodle_manage_sections`,
  `moodle_grade_submission`, `moodle_message_course_students`.
- Erster Aufruf liefert eine Vorschau (`requires_confirmation=true`), erst
  `confirm=true` führt aus; jede Aktion prüft die einschlägige Moodle-Capability.

**Akzeptanz:** Ohne `confirm=true` keine Zustandsänderung; ohne Capability
klarer Tool-Fehler; erfolgreiche Writes erzeugen ein `write_performed`-Event.

## feat04 Lehrer-Workflows (seit 1.4.0)

- Grading-Loop: `moodle_grading_queue`, `moodle_read_submission`
  (Abgabeinhalt inkl. lesbarer Dateien), `moodle_grade_submission`
  (Punkte + Feedback, Marking-Workflow „In review", Advanced Grading wird
  abgelehnt).
- `moodle_course_health` (inaktive Studierende, Abgabe-/Bewertungsstand,
  Notenübersicht), `moodle_unanswered_forum_posts`,
  `moodle_message_course_students` (zielgruppen-gefiltert, Empfänger-Cap).

**Akzeptanz:** Nur mit Lehrer-Capabilities nutzbar; Bulk-Nachricht zeigt
Empfänger-Vorschau und respektiert den harten Cap.

## feat05 Suite-Generierungs-Tools (bedingt registriert)

- Nur wenn das jeweilige Plugin installiert ist: `moodle_generate_h5p`
  (`local_elediaai_h5pauthor`), `moodle_generate_questions`
  (`local_elediaai_questiongen`), Self-Study-Familie
  `moodle_selfstudy_create_quiz` / `_list_quizzes` / `_get_quiz` /
  `_submit_attempt` (`local_elediaai_selfstudy`).

**Akzeptanz:** Auf Installationen ohne die Suite tauchen die Tools weder in
`tools/list` auf noch sind sie aufrufbar; Self-Study respektiert Kurs-Opt-in
und Tages-Quota des gewrappten Plugins.

## feat06 Raw-Function-Exposure (Opt-in, doppelt gegatet)

- Admin-Setting `expose_raw_functions` (Default aus) + Premium-Feature
  `mcp_tools`: dann werden die dem authentifizierten Service zugeordneten
  External Functions zusätzlich als MCP-Tools exponiert (Annotationen aus der
  Namenskonvention inferiert, Parameter typ-koerziert).

**Akzeptanz:** Bei ausgeschaltetem Setting oder ohne Premium nur der kuratierte
Katalog; nur Funktionen des eigenen Service sichtbar; deprecatede Funktionen
und Namenskollisionen mit AI-Tools werden ausgefiltert.

## feat07 Token-Management (Self-Service + interne API)

- Self-Service-UI unter *Preferences → MCP tokens* (Capability
  `webservice/elediamcp:managetokens`): Token erstellen (Label, MCP-Service,
  optionales Ablaufdatum), Metadaten einsehen (Status aktiv/abgelaufen/
  widerrufen, Last-Access), widerrufen. Tokenwert wird genau einmal angezeigt;
  fertiges Claude-Desktop-Snippet (`mcp-remote`) wird mitgerendert.
- Interne PHP-API `\webservice_elediamcp\api` für First-Party-Plugins
  (create/revoke/list, Komponenten-Attribution, komponenten-scopte Revocation;
  Self-Service-Tokens bleiben unberührt).
- Admin-Verwaltung auf der MCP-Konfigurationsseite (`configuration.php`);
  Token-Ausstellung nur für admin-konfigurierte MCP-Services.

**Akzeptanz:** Secret nie persistiert (nur SHA-256-Hash); Widerruf löscht den
Core-Token sofort, Metadaten bleiben auditierbar; Ablauf in der Vergangenheit,
deaktivierte oder Nicht-MCP-Services werden abgelehnt.

## feat08 Sicherheits-Hardening am Endpoint

- Origin-Validierung + CORS-Allow-List (kein `*` mit Credentials), Rate-Limit
  pro Token/IP (429 + `Retry-After`, atomar über MUC-Lock), Request-Size-Limit
  (413), Emergency-Disable (503, Kill-Switch), Query-String-Token nur per
  Opt-in (mit `Deprecation`-Header), Endpoint-Bindung an MCP-Services
  (`enforce_mcp_service`, Default an), Capability `webservice/elediamcp:use`.
- OAuth-Protected-Resource-Discovery
  (`/.well-known/oauth-protected-resource`); bei aktivem OAuth-Server (feat11)
  listet sie den Authorization-Server, sonst leer.

**Akzeptanz:** Verbotene Origin → 403; Limit überschritten → 429 mit
Rate-Limit-Headern; Emergency-Disable antwortet vor jeder Verarbeitung;
Token eines Nicht-MCP-Service wird (bei aktivem Enforcement) abgewiesen.

## feat11 OAuth 2.1 Authorization Code + PKCE (Opt-in, seit 1.6.0)

- Admin-Setting `oauth_enabled` (Default **aus**): schaltet einen
  in-Moodle-OAuth-2.1-Autorisierungsserver frei, damit konforme MCP-Clients
  (Claude Desktop, Cursor, …) ein MCP-Token per Authorization Code + PKCE
  erhalten, ohne dass ein Mensch manuell ein Token erzeugt.
- Discovery: `/.well-known/oauth-authorization-server` (RFC 8414) veröffentlicht
  Authorization-/Token-/Registrierungs-Endpoint, `response_types=code`,
  `grant_types=authorization_code`, `code_challenge_methods=S256`,
  `token_endpoint_auth_method=none`.
- Dynamische Client-Registrierung (RFC 7591, `oauth_allow_dynamic_registration`,
  Default an): öffentliche PKCE-Clients registrieren sich selbst und erhalten
  eine `client_id` (kein Client-Secret).
- Authorization-Endpoint (`oauth/authorize.php`): erzwingt Moodle-Login, zeigt
  einen Consent-Screen (sesskey/CSRF-geschützt), stellt bei Zustimmung einen
  einmaligen, kurzlebigen Autorisierungscode aus (an Nutzer, Client, Redirect-URI
  und PKCE-Challenge gebunden; nur als Hash gespeichert).
- Token-Endpoint (`oauth/token.php`): tauscht Code + `code_verifier` gegen ein
  MCP-Token (`token_manager::create_token`, an einen MCP-Service gebunden,
  im Self-Service widerrufbar).
- Bestehende Bearer-Tokens bleiben unabhängig vom Setting voll funktionsfähig.

**Akzeptanz:** Ein konformer MCP-Client kann den Authorization-Server entdecken
und Authorization Code + PKCE abschließen, ohne manuell ein Moodle-Token zu
erzeugen. Ungültige, abgelaufene, wiederverwendete (single-use) oder
nicht-passende Requests (Redirect-URI-, Client-, PKCE-, `response_type`-,
`grant_type`-, S256-Prüfung) werden abgewiesen, ohne Secrets oder interne
Details preiszugeben. Bei ausgeschaltetem `oauth_enabled` liefert die
Discovery keinen Authorization-Server und die Endpoints antworten mit 404.

## feat09 Audit & Privacy

- Events: `tool_invoked` (mit Dauer, Fehlerflag, Protokollversion),
  `write_performed`, `context_verified`, `token_created`, `token_revoked` —
  sichtbar in den Moodle-Logs.
- Privacy-Provider: exportiert/löscht Token-Metadaten
  (`webservice_elediamcp_token`); die Protokollschicht speichert keine
  personenbezogenen Daten.

**Akzeptanz:** Jeder Tool-Call erzeugt ein Event; Audit-Fehler brechen nie die
Response; Privacy-Export/-Löschung deckt die Token-Tabelle ab.

## feat10 Free/Premium-Katalog

- Ohne Premium-Add-on: 15 freie Tools (`tool_provider::FREE_AI_TOOLS`).
- Mit `local_elediaai_tutor_premium` (Feature `mcp_tools`): voller Katalog
  inkl. Write-, Lehrer-, Generierungs-Tools und (mit feat06) Raw-Funktionen.
- Konfigurationsseite zeigt die aktive Edition und die Premium-Tools an.

**Akzeptanz:** Free-Installation listet exakt die freien Tools; `tools/call`
auf ein Premium-Tool ohne Add-on schlägt fehl.

## Nicht-Ziele (aktuell)

- **Kein SSE / keine Sessions:** GET mit `Accept: text/event-stream` → 405,
  DELETE → 405 (es werden keine Session-IDs ausgegeben).
- `resources/*` und `prompts/*` sind nicht implementiert (leere Listen,
  Capability nicht annonciert).
- OAuth 2.1 Authorization Code + PKCE ist umgesetzt (feat11), aber **opt-in**
  (Default aus). Refresh-Tokens werden nicht ausgegeben (langlebige
  Moodle-Tokens); Scopes sind advisory (die effektive Autorität ist das
  Moodle-Rechtemodell des Nutzers). Die extensionslosen `/.well-known/*`-Pfade
  setzen ein Web-Server-Rewrite auf die `*.php`-Dateien voraus (wie schon bei
  der bestehenden Protected-Resource-Discovery).
- Keine eigene External-Function-Deklaration (kein `db/services.php`): die
  Tools existieren nur als MCP-Tools, nicht als Moodle-WS-Funktionen.

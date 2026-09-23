# webservice_elediamcp — UAT-Testanleitung

> **Zielgruppe:** technisch versierte Tester:innen / Admins. Diese Anleitung
> setzt Umgang mit der Webservice-Verwaltung, JSON-RPC und `curl` bzw. einem
> MCP-Client voraus.
>
> Verweis: Melderegeln und Zugänge im UAT-Handbuch
> (public/local/elediaai_core/docs/uat-handbuch.md). Testinstanz:
> https://demo.eledia.ai gegen den im Issue genannten Tag.

## Was das Plugin macht

Der eLeDia MCP-Server stellt eine kuratierte, LLM-freundliche Tool-Schicht über
Moodles Webservices als MCP-Endpoint (`server.php`, JSON-RPC 2.0 über POST)
bereit. Jeder Tool-Aufruf läuft als der authentifizierte Nutzer und kann nie
mehr sehen oder tun, als dieser Nutzer in Moodle selbst darf; Tokens werden per
Self-Service erzeugt und an einen MCP-Service gebunden. Ohne Premium-Add-on sind
15 lesende AI-Tools verfügbar; mit `local_elediaai_tutor_premium` (Feature
`mcp_tools`) kommen Schreib-, Lehrer- und Generierungs-Tools sowie optional
Raw-Funktionen hinzu.

## Vorbereitung

- **Rollen:** Ein **Administrator**-Konto; für die Tutor-Tests eine
  **Teilnehmer:in** in einem Kurs.
- **Webservices einschalten:** *Website-Administration → Server → Webservices →
  Übersicht* aktivieren; unter *Webservices → Protokolle verwalten* das
  Protokoll **elediamcp** aktivieren (sonst antwortet `server.php` mit
  `403 Forbidden`).
- **Konfigurationsseite:** *Website-Administration → Server → Webservices →
  eLeDia MCP*, bzw. direkt `/webservice/elediamcp/configuration.php`. (Die
  Settings-Sektion `webservicesettingelediamcp` leitet dorthin um.)
- **Endpoint:** `https://demo.eledia.ai/webservice/elediamcp/server.php`
- **Token-Self-Service:** *Nutzermenü → Einstellungen → MCP-Tokens*
  (`/webservice/elediamcp/token/index.php`) oder auf der Konfigurationsseite.
  Benötigt Capability `webservice/elediamcp:managetokens`.
- **Für die Tutor-Tool-Aufrufe (uat07):** In `local_literag` müssen die
  Live-Tools aktiv sein (`enable_mcp_tools`), und der Tutor-Block muss über
  seine `mcpserviceid`-Einstellung an den hier aktivierten MCP-Service gebunden
  sein.
- Für die Endpoint-Tests genügt ein Terminal mit `curl`.

## Testfälle

### uat01 — Premium-Status und Tool-Zahlen auf der Konfigurationsseite

**Rolle:** Administrator
1. `/webservice/elediamcp/configuration.php` öffnen.
2. Die Status-Karte oben lesen (Edition, Anzahl freier/Premium-Tools).

**Erwartet:** Die Karte zeigt die aktive Edition (**Free** oder **Premium**).
Ohne Add-on werden **15 freie Tools** genannt und ein Info-Hinweis eingeblendet;
mit Add-on erscheint die Premium-Liste als Pillen. Die Zahl der freien Tools
stimmt mit `tools/list` (uat05) überein.

### uat02 — MCP-Service aktivieren

**Rolle:** Administrator
1. Zum Abschnitt *Token erstellen* scrollen. Ist noch kein MCP-Service
   konfiguriert, erscheint ein Hinweis mit Button **„Service aktivieren"**.
2. Den Button klicken.

**Erwartet:** Nach dem Klick meldet die Seite den Standard-MCP-Service als
aktiviert; das Token-Formular (Label, Service-Auswahl, optionales Ablaufdatum)
wird verfügbar. Der Service tauct anschließend auch in der Service-Auswahl der
Konfiguration auf.

### uat03 — Token erzeugen (Reveal-once) und Claude-Snippet

**Rolle:** Administrator
1. Im Token-Formular ein Label eingeben, den MCP-Service wählen, absenden.
2. Den einmalig angezeigten Tokenwert kopieren.
3. Das mitgerenderte `mcp-remote`-Konfigurationssnippet ansehen.

**Erwartet:** Der Tokenwert wird **genau einmal** angezeigt (danach nur noch
Metadaten: Status aktiv, Erstellzeit, Last-Access). Das Snippet enthält die
`server.php`-URL und den Bearer-Header. Beim Neuladen der Seite ist der
Klartext-Token nicht mehr sichtbar.

### uat04 — Server-Info über GET

**Rolle:** Administrator (Terminal)
1. `curl -s https://demo.eledia.ai/webservice/elediamcp/server.php` (GET).

**Erwartet:** Eine JSON-Server-Info-Antwort. **Bekannt:** Das Feld
`serverInfo.version` meldet `1.1.0`, obwohl das Plugin auf Release `1.4.0` steht
— siehe „Nicht testen / bekannt". Nicht als Bug melden.

### uat05 — Tool-Liste per JSON-RPC

**Rolle:** Administrator (Terminal)
1. `initialize` senden:
   ```
   curl -s -X POST https://demo.eledia.ai/webservice/elediamcp/server.php \
     -H "Authorization: Bearer <TOKEN>" -H "Content-Type: application/json" \
     -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{}}}'
   ```
2. Danach `{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}` senden.

**Erwartet:** `initialize` verhandelt eine unterstützte Protokollversion und
setzt den `MCP-Protocol-Version`-Header. `tools/list` liefert die kuratierten
`moodle_*`-Tools mit Titel, Beschreibung und Input-Schema. Auf einer
Free-Installation erscheinen genau die 15 freien Tools; Premium-/Write-Tools
fehlen dort.

### uat06 — Tool-Aufruf per JSON-RPC (read-only)

**Rolle:** Administrator (Terminal)
1. `tools/call` mit `moodle_me` aufrufen:
   ```
   -d '{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"moodle_me","arguments":{}}}'
   ```
2. Danach `moodle_my_courses` mit leeren Argumenten aufrufen.

**Erwartet:** `moodle_me` liefert die Identität des Token-Besitzers;
`moodle_my_courses` nur dessen Kurse. Ein `tools/call` auf ein **Premium-Tool**
(z. B. `moodle_enrol_user`) schlägt auf einer Free-Installation fehl.

### uat07 — Tool-Aufrufe über den Tutor als MCP-Client *(E2E, automatisiert ungetestet)*

**Rolle:** Teilnehmer:in
1. Im Kurs den KI-Tutor öffnen (Live-Tools aktiv, Service gebunden — siehe
   Vorbereitung).
2. Zwei bis drei Fragen stellen, die Moodle-Live-Daten brauchen, z. B.:
   „Welche Kurse habe ich?", „Welche Abgaben stehen bei mir an?", „Wann ist
   meine nächste Frist?".

**Erwartet:** Der Tutor beantwortet die Fragen mit echten Moodle-Daten, die die
Testperson selbst sehen darf (die Tools `moodle_my_courses`, `moodle_due_work`,
`moodle_calendar_upcoming` o. ä. werden im Hintergrund als diese Person
aufgerufen). Es werden keine Daten fremder Nutzer:innen oder versteckter Kurse
preisgegeben.

### uat08 — Sichtbarkeits-/Self-Scope-Grenze

**Rolle:** Administrator (Terminal, Token einer Teilnehmer:in) oder über den
Tutor
1. Ein Tool aufrufen, das Fremddaten liefern könnte (z. B. `moodle_find_user`
   nach einer Person mit `maildisplay = niemand`, oder `moodle_my_grades`).

**Erwartet:** Tools respektieren Enrolment, Gruppenmodi, versteckte Bewertungen
und Privacy-Einstellungen (z. B. `maildisplay`); eigene Daten (Quiz-Versuche,
Abgaben, Noten) sind strikt auf den Token-Besitzer beschränkt.

### uat09 — Notabschaltung (Kill-Switch)

**Rolle:** Administrator
1. In der Konfiguration **`emergency_disable`** aktivieren, speichern.
2. `initialize`/`tools/call` gegen `server.php` senden.
3. Notabschaltung wieder deaktivieren.

**Erwartet:** Bei aktiver Notabschaltung antwortet der Endpoint vor jeder
Verarbeitung mit `503`. Nach dem Deaktivieren funktionieren die Aufrufe wieder.

### uat10 — Unautorisierter Zugriff

**Rolle:** Administrator (Terminal)
1. `tools/call` **ohne** `Authorization`-Header senden.

**Erwartet:** Antwort `401` mit `WWW-Authenticate`-Header und Hinweis auf die
OAuth-Discovery-URL. Es werden keine internen Exception-Details an den Client
zurückgegeben.

### uat11 — Token widerrufen

**Rolle:** Administrator
1. Auf der Konfigurationsseite (Abschnitt Tokens) einen aktiven Token über das
   Widerrufen-Icon widerrufen und bestätigen.
2. Denselben Token danach für `tools/call` verwenden.

**Erwartet:** Der Widerruf löscht den Core-Token sofort; der Status wechselt auf
„widerrufen" (Metadaten bleiben auditierbar). Der widerrufene Token wird bei
`tools/call` abgewiesen.

## Nicht testen / bekannt

Diese Punkte sind bekannt bzw. in Arbeit — bitte **keine** Bug-Meldungen dazu
anlegen:

- **Stale Server-Version (q01):** `serverInfo.version` und das README-Badge
  nennen `1.1.0`, obwohl `version.php` auf Release `1.4.0` steht. Bekannte
  Doku-/Konstanten-Differenz, kein Funktionsfehler.
- **Nur englische Sprachdatei (q03):** UI-Texte (Token-/Konfigurationsseite,
  Fehlermeldungen) liegen nur auf Englisch vor. Deutsch/Englisch-Mix aus diesem
  Plugin ist bekannt — nicht als `S3` melden.
- **resources/prompts leer, kein OAuth-2.1-Flow (q04):** `resources/list` und
  `prompts/list` liefern bewusst leere Listen; es gibt nur OAuth-Discovery-
  Metadaten, keinen Authorization-Code-Flow. Ebenso: kein SSE/keine Sessions
  (`GET` mit `text/event-stream` → 405, `DELETE` → 405).
- **Monorepo-CI (q02):** `webservice/elediamcp` ist nicht im Forgejo-Katalog des
  Monorepos; das Plugin bringt eigenes CI mit. Kein Testgegenstand.
- **KI-/Antwortqualität des Tutors:** In uat07 wird nur geprüft, ob echte
  Moodle-Daten korrekt und sichtbarkeitskonform zurückkommen — nicht, wie gut
  die formulierte Antwort ist (das gehört zum Tutor-UAT).

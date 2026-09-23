# Eduplaces-Integration für eledia.ai — verbindliche Doku-Analyse

> **Nicht mehr umgesetzt (07.09.2026).** Das Auth-Plugin
> `auth_eledia_eduplaces` ist aus der Suite entfernt worden: es meldete sich
> über `feature_provider` selbst als Kachel im Suite-Dashboard an, obwohl eine
> Anmeldung an einer Schulplattform keine KI-Funktion ist. Diese Analyse bleibt
> stehen, weil sie eine gelesene Fremddokumentation zusammenfasst und nicht
> billig wiederzubeschaffen ist -- wer die Integration erneut angeht, faengt
> hier an, nicht bei null. Was hier steht, beschreibt also einen Plan, keinen
> Zustand.

> Quelle: <https://developer.eduplaces.de> (vollständig gelesen 2026-07-10).
> Ziel-Scope: **Voll inkl. IDM-Sync** (SSO-Login + Tile/Initiated Login + Directory-Sync).
> Status: Konzept-Grundlage, noch kein Code.

## 1. Was Eduplaces ist

SaaS-Schulplattform (~3,2 Mio. Nutzer, DACH). Für uns drei Integrationsflächen:

1. **OIDC Identity Provider** — vollwertiger OpenID-Connect-IdP auf OAuth 2.0 (Ory Hydra, `ory_at_*`/`ory_rt_*`-Tokens).
2. **Apps & Tiles** — eledia.ai wird als „App" registriert, erscheint als Kachel im Eduplaces-Dashboard; Klick = IdP-initiated Login.
3. **IDM (Directory API)** — Schulen/Gruppen/Personen/Users, Schulconnex-konform, mit Webhooks.

## 2. Umgebungen & Basis-URLs

| | Sandbox | Produktion |
|---|---|---|
| Auth (OIDC) | `https://auth.sandbox.eduplaces.dev/` | `https://auth.eduplaces.io/` |
| API (IDM) | `https://api.sandbox.eduplaces.dev/` | `https://api.eduplaces.io/` |
| UI (Dashboard) | `https://app.sandbox.eduplaces.dev/` | `https://app.eduplaces.de/` |
| Discovery | `…/.well-known/openid-configuration` | `…/.well-known/openid-configuration` |
| `iss`-Wert | `https://auth.sandbox.eduplaces.dev` | `https://auth.eduplaces.io` |

- Token-Endpoint: `POST /oauth2/token`; Auth-Endpoint: `/oauth2/auth` (via Discovery beziehen, nicht hardcoden).
- JWKS via Discovery → ID-Token- und Logout-JWT-Signaturen prüfen.

## 3. Authentifizierung (SSO)

**Protokoll:** OpenID Connect. Empfohlen **Authorization Code + PKCE** (S256).

### 3.1 Login-Flows
- **Login-Button** (User startet in Moodle): Redirect zu `/oauth2/auth` mit `response_type=code`, `client_id`, `redirect_uri` (vorregistriert), `code_challenge`, `code_challenge_method=S256`, `scope` (muss `openid` enthalten), plus `state`+`nonce` (dringend empfohlen).
- **Initiated Login / Tile** (User klickt Kachel im Dashboard): Eduplaces schickt `GET <unsere Login-URL>?iss=…&login_hint=…`.
  1. `iss` gegen erwarteten Wert prüfen (nur dann Flow starten).
  2. Bestehende lokale Session beenden (Session-Hijacking-Schutz).
  3. OIDC-Auth-Code-Flow starten und `login_hint` mitgeben.
- **Token-Tausch:** `POST /oauth2/token` mit `grant_type=authorization_code`, `code`, `code_verifier`, `redirect_uri`; Confidential Client → HTTP Basic (`client_id:client_secret`).
- **Antwort:** `access_token`, `id_token` (JWT), `refresh_token` (nur mit `offline_access`), `expires_in` (~3599 s).

### 3.2 ID-Token-Claims
- `sub` = **stabile, permanente User-ID → primärer Mapping-Key.**
- `sid` = nur Session-ID (für Logout), **nicht** zur User-Identifikation.
- `iss`, `aud`, `nonce`, `exp`, `iat`, `auth_time`.
- Weitere Claims je Scope (siehe §4).

### 3.3 Logout — **Back-Channel**
- Eduplaces schickt `POST <Logout-Callback>` mit signiertem JWT (`iss`, `aud`, `sid`, `jti`, `iat`, `events`).
- Wir: JWT-Signatur (JWKS) prüfen → `sid` extrahieren → **serverseitig** Session invalidieren → HTTP 200.
- Reines Cookie-Löschen reicht nicht (kein Browser beteiligt).
- **Nicht** den User bei Eduplaces ausloggen, wenn er sich bei uns ausloggt.

### 3.4 Refresh
- `offline_access` beim ersten Auth anfordern → `refresh_token`. Rotierend (alter Token wird ungültig). `grant_type=refresh_token`.

## 4. Scopes

### 4.1 OIDC-/SSO-Scopes (User-Kontext, Auth Code Flow)
| Scope | Liefert | Verfügbarkeit |
|---|---|---|
| `openid` | Pflicht; Basis-ID-Token (`sub`, `sid`, Timestamps) | immer |
| `profile` | `given_name`, `call_name`, `family_name` | **fehlt bei ~40 %** (nicht-personalisierte Konten) |
| `pseudonym` | Zufalls-Pseudonym für nicht-personalisierte Konten | Fallback für die 40 % |
| `role` | `STUDENT` / `TEACHER` / `OTHER` | i. d. R. |
| `groups` | Array `{id, name}` der Gruppen des Users | i. d. R. |
| `school` | interne Schul-ID (`school`-Claim) | i. d. R. |
| `school_name` | Anzeigename der Schule | i. d. R. |
| `school_location` | Land/Bundesland | ~75 % |
| `schooling_level` | Schultyp-Enum (GRUNDSCHULE, GYMNASIUM …) | ~5 % |
| `school_address` | Straße/Ort/PLZ | ~3 % |
| `school_official_id` | offizielle Landes-Schul-ID | ~3 % |

> **Produkt-Konsequenz:** Der personalisierte AI-Tutor muss den namenlosen Fall (nur `pseudonym`) sauber abfangen.

### 4.2 IDM-Scopes (App-Kontext, Client Credentials)
- `urn:eduplaces:idm:v1:schools:read`
- `urn:eduplaces:idm:v1:groups:read`
- `urn:eduplaces:idm:v1:people:read`
- `urn:eduplaces:idm:v1:users:read`

## 5. IDM / Directory-Sync

**Client Credentials Flow** (`grant_type=client_credentials`, HTTP Basic, `scope=`…). **Nur serverseitig** — Secret nie am Client. Man kann Client-Credentials nicht direkt gegen die API nutzen, immer erst Token holen.

### 5.1 Access vs. Sync (zentrales Modell)
- **People (Sync):** Von der Schule bereitgestellte Personen — **auch ohne Login** vorhanden. Schule entscheidet, welche Gruppen/Daten synchronisiert werden.
- **Users (Access):** Personen, die sich mind. einmal per SSO eingeloggt haben.
- Ein Mensch kann People, User oder beides sein.
- **Wichtig:** Endpoints liefern nur Daten von Schulen, die Sync **für unsere App aktiviert** haben. Leere Antwort = niemand hat aktiviert.

### 5.2 Endpoints (Basis-Pfad `/idm/ep/v1`)
| Endpoint | Scope | Inhalt |
|---|---|---|
| `GET /schools` | `schools:read` | Schulen mit Sync für uns: `id`, `name`, `address{…}`, `location{country,state}`, `officialId` |
| `GET /groups/:id` | `groups:read` | Gruppe: `id`, `name`, `members[]{id, role, name{firstFull,firstCall,last}}` |
| `GET /schools/:id/users` | `users:read` | Users mit App-Zugang je Schule: `id`, `name`, `status`, `role`, `groups[]` |
| `GET /users/:id` | `users:read` | Einzel-User (alle je eingeloggten); 404 = permanent gelöscht, `SOFT_DELETED` = evtl. wiederherstellbar |

### 5.3 Schulconnex (Standard-Schnittstelle, Basis `/idm/schulconnex/v1`)
- `GET /person-info` — User-Kontext (Auth Code), Scope `people:read`; Einzelperson + Home-Organisation + Rollenkontexte.
- `GET /personen-info` — App-Kontext (Client Credentials), Scope `people:read`; Batch-Array mit Org-Kontexten & Gruppen.
- Felder: Name, Organisation (`id`, `kennung`), Personenkontexte (Rolle/Gruppen), Org-Details.

### 5.4 Webhooks
- Event-Typen: **Person**, **Group**, **School** (auch im Header `X-EP-Event-Type`).
- Payload: `event`, `action` (`create|update|delete|restore`), optional `updatedProperties`; je Event `personId`/`groupId`/`schoolId`.
- Signatur: Header `X-EP-Signature-Sha256` = HMAC-SHA256(Body, Webhook-Secret) → **prüfen**.
- Antwort **2xx** nötig; sonst bis zu **3 Retries**. Best Practice: Event lesen → bei Bedarf IDM-API für aktuellen Stand nachfragen.

## 6. Access Reports (Lizenz-/Abrechnungsrelevant)
- `POST /v1/apps/access_report` mit **User-Access-Token**.
- Felder: `identifier` (eigene Report-Referenz), `type` (`SCHOOL|GROUP|SINGLE_USER|NONE`), optional `since`/`until` (UNIX), Trial-Status.
- Eduplaces rechnet **pro Schule mit ≥1 zugreifendem User** ab. Ohne Reports werden alle Schulen mit Aktivierung berechnet.
- **Wir müssen annehmen: SSO-Login ≠ Lizenz.** Lizenzprüfung liegt bei uns. Reports optional, aber dringend empfohlen (Kostenvermeidung).

## 7. Harte Anforderungen (MUST — sonst Ablehnung)
- Pädagogischer Nutzen; erreichbarer Support für Lehrkräfte/Admins.
- **Keine Kaufoptionen/Werbung an Schüler:innen**, keine Auto-Verlängerung ohne Zustimmung, Preise beim Onboarding klar.
- **DSGVO** vollständig; starke Verschlüsselung; **AVV** einreichen; **keine personenbezogenen Daten von Schüler:innen anfragen** (Datenminimierung).
- SSO exakt nach Eduplaces-Spezifikation; „Login mit Eduplaces"-Button auf der Login-Seite.
- Mobile: Tile öffnet App direkt mit aktivem Login. Desktop ohne Web-Login: Landingpage mit Anleitung.
- E-Mail nur anfragen, wenn funktional nötig (nicht verpflichtend für Infozwecke).

## 8. Login-Button (Branding)
- Zwei SVG-Varianten (Icon / Logo-mit-Text). Font Montserrat 700, 12px.
- Farben: Space Dust `#404261`, Infra Purple `#4A00B8`, Redshift `#7D285A`, Moon `#FAFAFA`.
- Größe/Radius/Border anpassbar; **Farben nicht ändern** (außer Moon → Weiß). Logo/Marke nicht anderweitig verwenden. Mono-Variante für neutrale Optik.

## 9. Abbildung auf Moodle / eledia.ai (Ist-Stand + Bauplan)

**Ist:** eledia.ai = Moodle-Aufsatz (`public/local/*`, `mod/*`, `block/*`). **Kein** Auth-/OIDC-Plugin im Repo. Integration also auf Plattform-Ebene.

**Warum kein Standard-Plugin reicht:**
- `auth_oauth2` (Core) verlangt lokale Kontobestätigung/E-Mail → passt nicht zu Auto-Provisioning namenloser Schüler-Accounts; kein `login_hint`/Tile.
- `auth_oidc` (Microsoft) kann Initiated Login + `login_hint` + Back-Channel-Logout nicht sauber.

**Bauplan — neues Plugin `auth_eledia_eduplaces` (+ ggf. `local_eledia_eduplaces` für IDM):**
1. **Discovery/PKCE-Client** gegen `/.well-known/openid-configuration`.
2. **Login-URL-Endpoint** (Initiated Login): `iss` prüfen → Session beenden → Flow mit `login_hint`.
3. **Callback**: Token-Tausch, ID-Token gegen JWKS validieren (`iss`,`aud`,`nonce`,`exp`).
4. **User-Mapping**: `sub` → `user.idnumber`/eigenes Feld; Auto-Provisioning; namenloser Fall via `pseudonym`.
5. **Claim-Mapping**: `role` → Moodle-Rolle, `groups` → Cohorts, `school`/`school_name` → Mandant/Kategorie.
6. **Back-Channel-Logout-Endpoint**: JWT prüfen, `sid`→Moodle-Session invalidieren.
7. **Privacy-Provider**: gespeicherte `sub`/`sid`/Eduplaces-Zuordnung deklarieren.
8. **IDM-Sync** (getrennt): Scheduled Task (Client Credentials) für `schools`/`groups`/`users` → Cohorts/Kurse; Webhook-Endpoint mit HMAC-Prüfung für Live-Updates.
9. **Access Reports**: nach Login/Lizenzprüfung `POST /v1/apps/access_report`.

## 10. Onboarding-Prozess (organisatorisch)
1. Partnerformular <https://eduplaces.de/partner> — inkl. Angabe **ob/wie IDM genutzt** wird.
2. Sandbox-`client_id`/`client_secret` + Testnutzer (verschiedene Schulrollen).
3. Gegen Sandbox entwickeln/testen → Pre-Production-Review (App-Listing).
4. Produktions-Credentials + gemeinsamer Abschlusstest → Go-Live.

## 11. Offene Punkte / zu klären mit Eduplaces
- Genaue Registrierung von **Redirect-URI, Login-URL, Logout-Callback, Webhook-URL** (nicht in der Doc, läuft über Partner-Setup).
- Consent-/Freigabemodell (User- vs. Admin-Consent) auf `/concepts/authorization` nicht detailliert — beim Onboarding erfragen.
- Multi-Tenant-Strategie in Moodle (eine Instanz mit Kategorie-Trennung vs. Mandanten) für `school`-Mapping festlegen.

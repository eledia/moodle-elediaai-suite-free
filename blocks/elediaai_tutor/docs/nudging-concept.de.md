# Nudging-Konzept — eLeDia.ai Tutor (Release 2+)

Stand: 2026-07-02 · Status: **Konzept, nicht implementiert**

## 1. Ziel & Abgrenzung

Nudging ist der Push-Gegenpart zum Briefing-Button (Release 1): Statt dass
Nutzer/innen den Tutor fragen ("Pull"), meldet sich der Tutor proaktiv über
Moodle-Messaging mit einer kontextuellen, hilfreichen Erinnerung ("Push").
Beispiel: „Deine Abgabe *Statistik-Essay* ist in 2 Tagen fällig — du hast Teil 2
noch nicht begonnen. Soll ich dir beim Einstieg helfen?" mit Deep-Link in den
Tutor-Chat.

Nicht Ziel: Überwachung, Gamification-Druck, Marketing. Jeder Nudge muss eine
konkrete, überprüfbare Handlungsempfehlung enthalten und abschaltbar sein.

## 2. Anlässe (Occasions)

| Anlass | Datenquelle | Erkennungslogik | Beispieltext |
|---|---|---|---|
| `deadline_approaching` | `assign`, `quiz`, `lesson` (duedate/timeclose) + Submission-Status | Fällig in ≤ Vorlaufzeit UND keine (vollständige) Abgabe | „*{Aktivität}* ist am {Datum} fällig. Brauchst du Unterstützung?" |
| `inactivity` | `user_lastaccess` pro Kurs | Kein Kurszugriff seit ≥ X Tagen (Kurs läuft, nicht beendet) | „In *{Kurs}* ist einiges passiert, seit du zuletzt da warst. Soll ich dich kurz auf Stand bringen?" |
| `unanswered_forum` | Foren-Posts des Users ohne Antwort | Eigene Frage seit ≥ 48 h unbeantwortet | „Deine Forenfrage ist noch offen — der Tutor kann sie dir vielleicht schon beantworten." |
| `low_progress` | Activity Completion / Kursfortschritt | Fortschritt deutlich unter Kursdurchschnitt bei > 50 % Kurslaufzeit | „Du hast {n} von {m} Abschnitten geschafft. Ein guter nächster Schritt wäre *{Aktivität}*." |

Jeder Anlass ist einzeln aktivierbar; die Texte sind Templates (Lang-Strings),
optional KI-personalisiert (siehe 5.4).

## 3. Teacher-Steuerungsmodell

Grundsatz: **Der Kurs entscheidet, ob genudged wird; die Person entscheidet, ob
sie es empfängt** (Opt-out, siehe 4).

- **Aktivierung pro Kurs** über die Blockinstanz-Einstellungen (neue Registry-
  Gruppe `nudging`, analog `dashboard`): Master-Schalter + Checkbox je Anlass.
  Kein Block im Kurs = kein Nudging (konsistent mit dem bestehenden
  Opt-in-Signal „Tutor-Block vorhanden").
- **Vorlaufzeit** (`deadline_approaching`): 1–7 Tage, Default 2.
- **Frequency-Cap**: max. N Nudges pro Person pro Woche über alle Kurse
  (Site-Setting, Default 3); innerhalb eines Kurses max. 1 pro Anlass pro
  Woche. Deduplizierung über `nudgelog` (siehe 5.3).
- **Ruhezeiten**: Versand nur im konfigurierbaren Fenster (Default 8–18 Uhr,
  Server-Zeitzone), nie am Wochenende (Site-Setting).
- **Vorschau/Protokoll für Teacher**: Reiter auf der Analytics-Report-Seite:
  welche Nudges gingen (anonymisiert aggregiert) raus, welche stehen an.

## 4. Studierenden-Perspektive

- **Message-Provider** `nudge` in `db/messages.php` → Nutzer/innen steuern
  Kanal (Web/E-Mail/Push/aus) über die Standard-Benachrichtigungseinstellungen.
  Das ist das native Opt-out und DSGVO-konform (berechtigtes Interesse +
  einfacher Widerspruch); zusätzlich respektiert der Task das bestehende
  Tutor-Consent: **kein Nudge vor der ersten Consent-Bestätigung**.
- **Ton & Wording**: unterstützend, nie vorwurfsvoll; immer mit konkretem
  nächsten Schritt und Deep-Link (Aktivität oder Tutor-Chat mit vorbefülltem
  Prompt via `?prompt=` Parameter — Erweiterung von view.php).
- Jede Nachricht endet mit dem Hinweis, wie man Nudges abstellt.

## 5. Architektur

### 5.1 Message-Provider
`db/messages.php`: Provider `nudge` (capability-frei, default „Web + E-Mail").

### 5.2 Scheduled Task
`classes/task/send_nudges.php`, täglich (z. B. 07:30). Ablauf:
1. Kurse mit aktivem Nudging ermitteln (Blockinstanz-Config).
2. Pro Kurs die aktivierten Occasion-Detektoren ausführen.
3. Kandidaten gegen `nudgelog`, Frequency-Cap, Consent und Ruhezeiten filtern.
4. Nachricht rendern (Template oder KI, 5.4) und via Message-API senden.
5. `nudgelog`-Eintrag schreiben.

### 5.3 Occasion-Strategien & Dedup-Tabelle
- Interface `classes/local/nudge/occasion.php`:
  `detect(int $courseid): candidate[]` + `id(): string`; je Anlass eine Klasse
  (`deadline_approaching.php`, …). Neue Anlässe = neue Klasse, keine
  Task-Änderung (Registry-Pattern).
- Tabelle `block_elediaai_tutor_nudgelog`:
  `id, userid, courseid, occasion (char 32), instanceid (z. B. cmid), timecreated`
  — Index `userid-timecreated` (Cap-Prüfung), Unique `userid-courseid-occasion-instanceid`
  (Dedup pro konkretem Anlass-Objekt).

### 5.4 Textgenerierung: Template vs. KI
- **v1: Templates** (Lang-Strings mit Platzhaltern). Deterministisch, billig,
  übersetzbar, kein Datenabfluss.
- **v2 (optional, Site-Schalter)**: KI-Personalisierung über `rag_client`
  mit dem Service-Account-Token (wie `recluster_service`) — der Prompt enthält
  nur die Anlass-Fakten (Aktivität, Datum, Fortschritt), nie Verlaufsdaten.

## 6. Privacy & Compliance

- `nudgelog` in die Privacy-Provider aufnehmen (Export + Löschung pro User).
- Keine Nachrichteninhalte an den externen Server in v1 (Templates lokal).
- Nudging-Auswertung für Teacher nur aggregiert und anonym (wie der
  Fragen-Report).
- Events: `nudge_sent` (ohne Inhalt) für Audit.

## 7. Offene Fragen & Roadmap

- Pilotkurs auf demo.eledia.ai mit 1–2 Anlässen (`deadline_approaching`,
  `unanswered_forum`) und Template-Texten.
- Metriken: Zustellrate, Klickrate auf den Deep-Link, Chat-Folgeaktivität
  (via `usage`/`qlog` innerhalb 24 h nach Nudge), Opt-out-Quote.
- Zu klären: Nudges für Teacher (Korrektur-Rückstau, unbeantwortete Foren) als
  eigener Provider? Interaktion mit bestehenden Moodle-Reminder-Features
  (`assign`-Benachrichtigungen), um Doppelungen zu vermeiden.
- Mehrsprachigkeit: Nudge in der Sprache des Empfängers (`$user->lang`).

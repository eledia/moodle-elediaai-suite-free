# UAT-Handbuch — manuelles Testen der eLeDia-KI-Suite

> Für das menschliche Test-Team. Eine Seite, bitte einmal ganz lesen.
> Je Plugin gibt es eine eigene Testanleitung: `public/<typ>/<name>/docs/06-uat.md`
> — ihr bekommt sie als Checkliste im jeweiligen Ticket (Jira oder Forgejo),
> ihr braucht das Repo nicht.

## Testumgebung

- **Instanz:** https://demo.eledia.ai — getestet wird immer der getaggte
  Stand einer Testrunde (steht im Issue, z.B. `v0.9`). Bitte nicht auf
  dev.eledia.ai testen; dort ändert sich der Stand laufend.
- **Testzugänge:** je Rolle ein Konto (Admin / Trainer:in / Teilnehmer:in).
  Zugangsdaten kommen separat (nicht im Issue, nicht im Repo!).
- **Browser:** aktueller Chrome oder Firefox; eine Stichprobe pro Runde
  bitte mobil (Smartphone-Breite).

## Ablauf pro Plugin

1. Öffnet das Ticket eurer Testrunde zum Plugin (Titel:
   `UAT <runde> — <plugin>`, in Jira bzw. Forgejo). Darin steht die
   Checkliste `uat01…uatNN`.
2. Arbeitet die Fälle in Reihenfolge ab. Jeder Fall nennt Rolle, Schritte
   und das **erwartete Ergebnis**.
3. Tragt je Fall den Status ein:
   - ✅ **OK** — Verhalten wie erwartet
   - ❌ **Fehler** — weicht ab → Beobachtung + Screenshot dazu
   - ❓ **Unklar** — ihr wisst nicht, ob das so gedacht ist → beschreiben
4. Am Ende: kurzes Gesamtfazit als Kommentar (3 Sätze reichen).

## Melderegeln

- **Ein Fund = ein Kommentar** im Plugin-Ticket, mit: uat-Nummer, was ihr
  getan habt, was passiert ist, was ihr erwartet habt, Screenshot.
- **Severity** dazuschreiben:
  - `S1` Datenverlust / Sicherheitsproblem / Seite komplett kaputt
  - `S2` Kernfunktion falsch oder blockiert
  - `S3` kosmetisch, Text, Layout, unklare Formulierung
- Bitte **keine** Duplikate zu Punkten, die in der Anleitung unter
  „Nicht testen / bekannt" stehen.
- KI-Antworten: Inhaltliche Qualität ist subjektiv — meldet nur
  reproduzierbare Probleme (leere Antwort, Fehlermeldung, falsche Sprache,
  offensichtlich ignorierter Auftrag), nicht „Antwort könnte besser sein".
- Deutsch/Englisch-Mix in der Oberfläche ist immer meldenswert (`S3`).

## Was mit euren Ergebnissen passiert

Nach der Runde werden alle Issues ausgewertet: Fehler wandern als Aufgaben
in die Plugin-Dokumentation (`04-tasks.md`), das Testergebnis wird mit
Datum und Stand in `06-uat.md`/`05-quality.md` festgehalten. Ihr müsst
nichts weiter tun als melden.

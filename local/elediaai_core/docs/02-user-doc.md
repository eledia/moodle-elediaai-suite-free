# Benutzer-Dokumentation

## Meta

Dieses Dokument beschreibt, wie Nutzer mit dem Plugin interagieren.

Quelle der Wahrheit fuer sichtbares Verhalten.

`local_elediaai_core` ist die KI-Suite-Shell: ein Dach ueber alle LernHive-KI-Funktionen. Es bringt selbst keine KI-Logik mit, sondern buendelt die Funktionen der Schwester-Plugins (Chat, Fragen-Generator, Freitext-Fragen, KI-Feedback, Uebersetzen) zu *einer* sichtbaren Suite mit gemeinsamem Launcher, Feature-Seiten und Audit. Jede konkrete KI-Aktion laeuft ueber Moodles `core_ai`. Was *warum* gebaut wird, steht in `01-features.md`; Architektur-Entscheidungen in `00-master.md`.

---

## Zielgruppen

- **Lernende und normale Nutzer:** sehen nur die KI-Funktionen, fuer die sie berechtigt sind, und nutzen sie ueber die jeweilige Aktivitaet/den Block.
- **Paedagog:innen / Kursverantwortliche:** oeffnen die KI-Suite, finden ihre KI-Werkzeuge an einem Ort und springen direkt in den jeweiligen Workflow.
- **Administratoren:** schalten Features ein/aus, konfigurieren die Anzeige des Audits und pruefen die KI-Nutzung der gesamten Site.

---

## Voraussetzungen

- Moodle 4.5+ mit aktiviertem `core_ai`-Stack.
- Mindestens ein konfigurierter `core_ai`-Provider (z. B. OpenAI) unter `Site administration > KI > AI providers`. Ohne Provider erscheinen die Karten zwar, aber KI-Aktionen schlagen fehl.
- AI Suite Core enthält seine Seiten-Shell, Theme-Konventionen und das
  Handbuch-Rendering selbst. Ein zusätzliches `local_lernhive`-Plugin ist
  weder zur Installation noch zur Nutzung erforderlich.
- Es werden **keine** eigenen API-Keys im Plugin gepflegt. Die gesamte Provider-Steuerung bleibt in Moodles `core_ai` (siehe `00-master.md` adr01).

---

## Produkt-Nutzung

### KI-Suite-Launcher (`feat01`)

**Was tut es?**
Eine kleine Pille mit Astroid-Icon in der obersten Navigation, die direkt zur zentralen KI-Suite-Uebersicht fuehrt.

**Wann nutze ich es?**
Immer dann, wenn eine KI-Funktion gesucht oder geoeffnet werden soll, ohne sich durch die Moodle-Administration oder einzelne Aktivitaetstypen zu klicken.

**Bedienung**

1. Eingeloggt eine beliebige Moodle-Seite oeffnen.
2. Die Astroid-Pille in der Navbar anklicken (in `theme_lernhive` sitzt sie in der Brand-Row, in anderen Themes neben dem Nutzermenue).
3. Die Pille oeffnet `/local/elediaai_core/index.php`.

**Erwartetes Ergebnis**
Die KI-Suite-Uebersicht erscheint mit allen sichtbaren Funktionen.

**Hinweise**

- Die Pille ist fuer Gaeste und nicht eingeloggte Nutzer nicht sichtbar.
- Sie erscheint nicht in den Layouts `login`, `popup`, `embedded` und `maintenance`.
- Der Launcher enthaelt bewusst kein Flyout mit einzelnen Funktionen — die Auswahl lebt nur auf der Uebersicht (siehe `00-master.md` adr03).

---

### KI-Suite-Uebersicht (`feat04`)

**Was tut es?**
Eine zentrale Karten-Uebersicht aller registrierten KI-Funktionen. Jede Karte zeigt Status (Verfuegbar / In Vorbereitung), Name und Kurzbeschreibung.

Die Uebersicht nutzt den verfuegbaren Seitenbereich wie die eLeDia.ai Tutor-Startseite; auf breiten Bildschirmen erscheinen die Feature-Karten dadurch in einem grosszuegigeren Raster.

**Wann nutze ich es?**
Um zu sehen, welche KI-Funktionen verfuegbar oder geplant sind, und um in eine Funktion einzusteigen.

**Bedienung**

1. Ueber den Launcher die KI-Suite oeffnen.
2. Eine Karte ueber die Icon-Action rechts unten anklicken.
3. Es oeffnet sich die Feature-Seite der jeweiligen Funktion.

**Erwartetes Ergebnis**
Verfuegbare Funktionen verlinken auf ihre Feature-Seite; geplante Funktionen zeigen den Status „In Vorbereitung" ohne Live-Start. Fehlende externe Plugins zeigen fuer Admins einen Install-Hinweis.

**Hinweise**

- Die Karten sind alphabetisch nach Anzeigename sortiert, damit die Reihenfolge stabil ist.
- Funktionen hinter einer Berechtigung, die der Nutzer nicht hat, erscheinen gar nicht.
- Sind keine Funktionen sichtbar, zeigt die Seite einen Hinweis statt einer leeren Liste.
- Admins sehen pro Karte zusaetzlich ein Zahnrad-Icon, sofern die Funktion eine eigene Einstellungsseite mitbringt.

---


---

### Feature-Seiten (`feat03`)

**Was tut es?**
Jede Funktion hat eine eigene Erklaerseite mit grossem Icon, Status-Pille, Name und einer entscheidungsorientierten Nutzenbeschreibung.

**Wann nutze ich es?**
Bevor man eine Funktion startet, um Zweck und Nutzen zu verstehen, und um von dort in den eigentlichen Workflow oder die Einstellungen zu wechseln.

**Bedienung**

1. Auf der Uebersicht die Feature-Icon-Action anklicken (URL `/local/elediaai_core/feature.php?id=<feature-id>`).
2. Die Erklaerseite lesen.
3. Bei verfuegbaren Funktionen ueber **Start** in den Funktions-Workflow wechseln.
4. Zwischen **KI-Suite**, **Uebersicht** und (falls vorhanden) **Einstellungen** ueber die Section-Navigation springen.

**Erwartetes Ergebnis**
Verfuegbare Funktionen zeigen Beschreibung, Nutzentext und einen Start-Bereich; geplante Funktionen zeigen Status-Pille und Beschreibung ohne Start.

**Hinweise**

- Die Karte rendert bewusst **keine** doppelten Body-Buttons. Navigation und Einstellungen liegen in der Plugin-Shell (Section-Nav und Zone-A-Icons), nicht im Karteninhalt.
- Das Hilfe-Icon oeffnet das Handbuch des jeweiligen Sub-Plugins (z. B. `local_elediaai_questiongen` fuer **Fragen**), nicht pauschal das Suite-Handbuch.
- Das Einstellungs-Zahnrad in Zone A erscheint nur, wenn die Funktion eine in-Shell-Einstellungsseite mitbringt und der Nutzer Site-Config-Rechte hat. Reine `/admin/`-Settingseiten werden als Zahnrad-Ziel bewusst nicht zugelassen.
- Ein unbekannter oder unsichtbarer Slug zeigt eine Notification mit Zurueck-Link, kein 404.

---

### Audit-Uebersicht (`feat05`)

**Was tut es?**
Die Audit-Funktion zeigt, wer wann welche KI-Aktion ausgeloest hat, und trennt dabei eine **technische** von einer **didaktischen** Sicht. Einstieg ist die Audit-Uebersichtsseite (`/local/elediaai_core/audit.php`) mit zwei Pfaden.

**Wann nutze ich es?**
Fuer Governance, Datenschutzpruefung und Kostenkontrolle (technisch) sowie zum Erkennen von Themen mit hohem Unterstuetzungsbedarf (didaktisch).

**Bedienung**

1. In der KI-Suite den Tab **Audit** oeffnen (nur sichtbar bei passender Berechtigung).
2. Die Uebersicht zeigt eine technische Zusammenfassung (Requests, Fehler, Unterstuetzungsaktionen, Tokens) und eine didaktische Vorschau (Top-Kontexte).
3. Ueber **Technisches Audit oeffnen** bzw. **Didaktisches Audit oeffnen** in die jeweilige Detailansicht wechseln.

**Erwartetes Ergebnis**
Berechtigte Nutzer sehen aggregierte Kennzahlen und zwei Detailpfade; Nicht-Berechtigte erhalten gar keinen Zugang.

**Hinweise**

- Wer das Audit sehen darf, ist konfigurierbar: entweder ueber die Moodle-Berechtigung `moodle/ai:viewaiusagereport` (Standard) oder nur fuer Site-Administratoren.
- Die Audit-Karte erscheint in der Uebersicht und in der Section-Nav nur fuer Berechtigte — es gibt keine toten Links.
- Die zugrunde liegenden Daten stammen aus Moodle Core (`ai_action_register` und die Detailtabellen), nicht aus einer eigenen Logging-Tabelle (siehe `00-master.md` adr04).

---

### Technisches Audit (`feat05`)

**Was tut es?**
Eine sortier- und filterbare Tabelle jeder protokollierten `core_ai`-Aktion: Zeit, Nutzer:in, Aktion, Provider, Modell, Kontext, Prompt, Antwort, Erfolg, Fehler und Tokens.

**Wann nutze ich es?**
Fuer Fehleranalyse, Provider-/Modellkontrolle, Tokenverbrauch und Nachvollziehbarkeit einzelner Aktionen.

**Bedienung**

1. In der Audit-Uebersicht **Technisches Audit oeffnen** waehlen (`/local/elediaai_core/audit_technical.php`).
2. Bei Bedarf die Schnellfilter oberhalb der Tabelle nutzen: Alle, Nur Teilnehmer:innen, Text generieren, Zusammenfassen, Erklaeren, Nur Fehler.
3. Lange Prompts/Antworten ueber das Augen-Icon in einem Modal mit Volltext oeffnen.
4. Bei Bedarf die Reportbuilder-Filter (Provider, Aktion, Zeitraum, Erfolg, Nutzer:in) verwenden.
5. Ueber den Reportbuilder-Download den Bericht als CSV exportieren.

**Erwartetes Ergebnis**
Pro Zeile sind alle Eckdaten sichtbar; lange Texte ueberlaufen die Tabelle nicht, sondern oeffnen im Modal. Die Erfolg-Spalte zeigt ein gruenes Haken- oder rotes Kreuz-Icon; fehlgeschlagene Zeilen sind rot hervorgehoben.

**Hinweise**

- Tokens stehen als kombinierte Spalte `Prompt / Antwort`, z. B. `420 / 180`; bei `0 / 0` (fehlgeschlagener Call) zeigt die Zelle `—`.
- Im CSV-Export werden Prompt und Antwort zu reinem, gekuerztem Text (max. 240 Zeichen), die Erfolg-Spalte zu „Ja/Nein". So bleibt die Tabellenkalkulation sauber lesbar.
- Welche Spalten ueberhaupt Inhalte zeigen (Prompt, Antwort, Fehlertext, Tokens), steuert der Admin in den Audit-Einstellungen. Ist eine Spalte deaktiviert, bleibt sie auch im Export leer.
- Prompt-Texte koennen personenbezogene Daten enthalten — entsprechend sorgsam behandeln.

---

### Didaktisches Audit (`feat05`)

**Was tut es?**
Eine kompakte Auswertung, in welchen Kursbereichen Lernende oder Lehrende besonders haeufig KI-Aktionen zum Zusammenfassen oder Erklaeren ausgeloest haben.

**Wann nutze ich es?**
Als Indikator fuer Inhalte, die moeglicherweise klarere Struktur, Beispiele oder Scaffolding brauchen.

**Bedienung**

1. In der Audit-Uebersicht **Didaktisches Audit oeffnen** waehlen (`/local/elediaai_core/audit_didactic.php`).
2. Die Top-Kontexte nach Anzahl der Unterstuetzungsanfragen pruefen (mit Aufschluesselung in Zusammenfassungen und Erklaerungen).
3. Optional ueber den `limit`-Parameter die Anzahl der gezeigten Kontexte zwischen 10 und 100 anpassen.

**Erwartetes Ergebnis**
Eine Liste der Kursbereiche mit dem groessten Unterstuetzungsbedarf, verlinkt auf den jeweiligen Kontext, sofern Moodle eine URL liefert.

**Hinweise**

- Sind noch keine Zusammenfassungs-/Erklaer-Anfragen protokolliert, zeigt die Seite einen Leerzustand.
- Die Ansicht ist bewusst kompakt; weitere Signale (Chat, Tactics, Fragen-Generator, Empfehlungen) sind als Ausbau vorgesehen.

---

### Audit-Einstellungen (Admin)

**Was tut es?**
Eine in der Plugin-Shell gerenderte Einstellungsseite, mit der Admins steuern, wer das Audit sieht und welche Inhalte angezeigt werden.

**Wann nutze ich es?**
Bei der Erstkonfiguration und immer dann, wenn Datenschutz- oder Support-Vorgaben die sichtbaren Audit-Bestandteile aendern.

**Bedienung**

1. Auf einer Audit-Seite das Einstellungs-Zahnrad in Zone A oeffnen oder direkt `/local/elediaai_core/audit_settings.php` aufrufen (erfordert `moodle/site:config`).
2. Festlegen, wer Zugriff hat (Core-Berechtigung vs. nur Administratoren).
3. Optional die Anonymisierung aktivieren (echte Namen werden durch ein Rollen-/Kontext-Label ersetzt).
4. Die sichtbaren Bestandteile aktivieren/deaktivieren: Prompts, Antworten, Fehlerdetails, Tokenwerte.
5. Speichern.

**Erwartetes Ergebnis**
Die Audit-Anzeige folgt sofort den gewaehlten Einstellungen — auch im CSV-Export.

**Hinweise**

- Die Anonymisierung wirkt nur auf die LernHive-Audit-Anzeige. Moodle Core speichert die echte Nutzer-ID in seinen `core_ai`-Tabellen unveraendert weiter.
- Dieselben Einstellungen sind alternativ unter `Site administration > Plugins > Local plugins > KI-Audit` erreichbar.

---

### Hilfe-Handbuch

**Was tut es?**
Das KI-Suite-Handbuch wird innerhalb der KI-Suite-Shell gerendert (`/local/elediaai_core/help.php`), inklusive Zone-A und Section-Navigation.

**Wann nutze ich es?**
Wenn eine Bedienhilfe zur Suite gebraucht wird, ohne die Navigation zu verlassen.

**Bedienung**

1. Auf einer Suite-Seite das Hilfe-Icon in Zone A anklicken.
2. Das Handbuch wird aus dem LernHive Support-Hub geladen und im Lesemodus angezeigt.

**Erwartetes Ergebnis**
Das Suite-Handbuch erscheint als formatierter Text in der Shell.

**Hinweise**

- Existiert noch kein Handbuch oder nur eine englische Fassung, zeigt die Seite einen entsprechenden Hinweis.
- Feature-Seiten verlinken ihr Hilfe-Icon auf das Handbuch des jeweiligen Sub-Plugins, nicht auf dieses Suite-Handbuch.

---

### Funktionen ein-/ausschalten (`feat07`)

**Was tut es?**
Admins koennen einzelne KI-Funktionen deaktivieren, ohne das jeweilige Sub-Plugin zu deinstallieren.

**Wann nutze ich es?**
Wenn eine Funktion vorerst nicht angeboten werden soll, das Plugin aber installiert bleibt.

**Bedienung**

1. Den Plugin-Config-Wert `feature_<id>_enabled` auf `0` setzen (z. B. `feature_aichat_enabled = 0`).
2. Caches leeren / Seite neu laden.

**Erwartetes Ergebnis**
Die deaktivierte Funktion verschwindet aus der Uebersicht und der Section-Navigation. Standard ist `1` (eingeschaltet).

**Hinweise**

- Der direkte Aufruf der Feature-Seite einer deaktivierten Funktion fuehrt zu einer Not-Found-Notification, nicht zu einem Crash.

---

## Angebundene Funktionen

Die KI-Suite zeigt Funktionen an, die von Schwester-Plugins selbst angemeldet werden. Aktuell:

- **Fragen** (`local_elediaai_questiongen`) — Moodle-Fragen aus Thema, Datei, Freitext oder Kursinhalten generieren und vor dem Import pruefen.
- **Tactics** (`local_elediaai_tactics`) — didaktische Kursverbesserungen wie Aktivitaetsbeschreibungen, Abschnittszusammenfassungen, Lernziele und Analysechecks aus bestehendem Kursinhalt erzeugen.
- **Freitext** (`qtype_aitext`) — Freitext-Fragetyp, der offene Antworten KI-gestuetzt bewertet und Feedback erzeugt.
- **Chat** (`block_elediaai_chat` / `mod_aichat`) — KI-Chat fuer Lernende als Block oder Aktivitaet; Unterhaltungen werden pro Nutzer:in persistiert.
- **KI-Feedback** (`mod_aifeedback`) — Aktivitaet, die nach einem `mod_feedback`-Fragebogen pro Abgabe ein KI-Feedback erzeugt, das Lehrende pruefen und freigeben.
- **Uebersetzen** (`filter_eledia_translate`) — Kursinhalte uebersetzen und Review-/Glossar-/Import-Workflows verwalten.

Die geplanten Roadmap-Funktionen **Kurse** und **Tutor** erscheinen als „In Vorbereitung". Details und Akzeptanzkriterien stehen in `01-features.md`.

---

## Was der Nutzer NICHT tun muss

- Niemals fuer Funktions-Einstellungen durch `Site administration > KI` navigieren — die Einstellungen leben in der Plugin-Shell der jeweiligen Funktion.
- Keine zwei Navigationsmuster jonglieren — jede Suite-Seite nutzt dieselbe LernHive Plugin-Shell mit gleichem Header, Hilfe-Button und Section-Nav.
- Keine doppelten Body-Links nutzen, wenn dasselbe Ziel bereits im Shell-Header oder in der Section-Navigation existiert.
- Keine eigenen API-Keys im Plugin pflegen — alles laeuft ueber Moodles `core_ai`.

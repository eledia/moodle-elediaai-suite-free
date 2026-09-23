# local_elediaai_core — UAT-Testanleitung

> Verweis: Melderegeln und Zugänge im UAT-Handbuch (public/local/elediaai_core/docs/uat-handbuch.md). Testinstanz: https://demo.eledia.ai gegen den im Issue genannten Tag.

## Was das Plugin macht

Die KI-Suite ist das Dach über alle LernHive-KI-Funktionen: Sie bündelt die Werkzeuge der Schwester-Plugins (Fragen-Generator, Chat, KI-Feedback, Übersetzen u. a.) in einer zentralen Karten-Übersicht mit eigenem Launcher-Icon in der Navigation. Jede Funktion hat eine eigene Infoseite mit Status („Verfügbar" / „In Vorbereitung"), Beschreibung und Einstieg; Lehrkräfte bekommen zusätzlich eine aufgabenorientierte Ansicht. Ein Audit-Bereich zeigt Berechtigten, wer wann welche KI-Aktion ausgelöst hat — technisch (Tabelle mit Prompt, Antwort, Fehlern, Tokens) und didaktisch (Kontexte mit hohem Unterstützungsbedarf).

## Vorbereitung

- **Rollen:** Ein **Administrator**-Konto und ein **Lehrkraft**-Konto (Trainer:in in mind. einem Kurs, ohne Admin-Rechte).
- **Einstieg:** Übersicht unter `/local/elediaai_core/index.php`, erreichbar über die Astroid-Pille in der obersten Navigation (nur eingeloggt sichtbar).
- **Voraussetzungen:** Theme `lernhive` aktiv; mindestens ein KI-Provider unter *Website-Administration > KI > AI-Provider* konfiguriert.
- **Für die Audit-Tests (uat07–uat12):** Vorher als Lehrkraft oder Teilnehmer:in mindestens 2–3 KI-Aktionen auslösen (z. B. im KI-Chat eine Frage stellen oder eine Zusammenfassung anfordern), damit die Audit-Tabelle Einträge hat. Idealerweise ist auch ein fehlgeschlagener Aufruf dabei (z. B. Aktion auslösen, während der Provider kurz deaktiviert ist).

## Testfälle

### uat01 — Launcher-Pille öffnet die KI-Suite

**Rolle:** Lehrkraft
1. Einloggen und eine beliebige Moodle-Seite (z. B. Dashboard) öffnen.
2. Die Astroid-Pille in der obersten Navigation suchen und anklicken.
3. Ausloggen und die Login-Seite betrachten.

**Erwartet:** Die Pille ist eingeloggt sichtbar und öffnet `/local/elediaai_core/index.php` mit der Karten-Übersicht „KI-Suite". Auf der Login-Seite und für nicht eingeloggte Besucher erscheint keine Pille.

### uat02 — Übersicht zeigt Karten mit Status und Icon-Aktion

**Rolle:** Administrator
1. `/local/elediaai_core/index.php` öffnen.
2. Die Karten prüfen: Jede zeigt Status, Name, Kurzbeschreibung und rechts unten ein Pfeil-Icon.
3. Reihenfolge der Karten notieren.

**Erwartet:** Karten sind alphabetisch nach Anzeigename sortiert. Verfügbare Funktionen tragen den Status „Verfügbar", geplante „In Vorbereitung". Als Admin erscheint bei Funktionen mit eigener Einstellungsseite zusätzlich ein Zahnrad-Icon; als Lehrkraft (Gegenprobe) erscheinen keine Zahnräder.

### uat03 — Feature-Infoseite einer verfügbaren Funktion

**Rolle:** Lehrkraft
1. In der Übersicht die Icon-Aktion der Karte **Fragen** anklicken (führt auf `/local/elediaai_core/feature.php?id=questiongenerator`).
2. Die Seite lesen: Icon, Status-Pille, Name, Beschreibung, Liste „Wesentliche Funktionen".
3. Den Start-Bereich mit „Funktion öffnen" anklicken.

**Erwartet:** Es erscheint eine Infoseite mit Status-Pille „Verfügbar", einer Aufzählung der wesentlichen Funktionen und einem Start-Bereich. Der Klick öffnet den Fragen-Generator. Auf der Karte gibt es keine doppelten Buttons wie „Zurück zur KI-Suite" im Textbereich — Navigation läuft über die Tab-Leiste oben.

### uat04 — Coming-Soon-Funktion ohne Start-Button

**Rolle:** Lehrkraft
1. In der Übersicht eine Karte mit Status „In Vorbereitung" öffnen (z. B. **Tutor**, `/local/elediaai_core/feature.php?id=tutor`).

**Erwartet:** Die Infoseite zeigt die Status-Pille „In Vorbereitung" und die Beschreibung, aber **keinen** „Funktion öffnen"-Button. (Admins können stattdessen einen Install-Hinweis sehen — das ist korrekt.)

### uat05 — Unbekannte Funktions-ID zeigt Hinweis statt Fehlerseite

**Rolle:** Lehrkraft
1. Direkt `/local/elediaai_core/feature.php?id=gibtsnicht` in die Adresszeile eingeben.

**Erwartet:** Es erscheint eine gelbe Hinweis-Meldung „Funktion nicht gefunden" (o. ä.) mit einem Button „Zurück zur KI-Suite" — keine weiße Fehlerseite, kein 404, keine Fehlermeldung mit Code.


### uat07 — Audit nur für Berechtigte

**Rolle:** Administrator + Lehrkraft (zwei Browser/Fenster)
1. Als Admin die KI-Suite öffnen: Der Tab **Audit** ist sichtbar; anklicken (`/local/elediaai_core/audit.php`).
2. Als Lehrkraft die KI-Suite öffnen: Den Tab Audit suchen.
3. Als Lehrkraft `/local/elediaai_core/audit.php` direkt in die Adresszeile eingeben.

**Erwartet:** Der Admin sieht die Audit-Übersicht mit Kennzahlen (Requests, Fehler, Tokens) und zwei Buttons „Technisches Audit öffnen" / „Didaktisches Audit öffnen". Die Lehrkraft sieht keinen Audit-Tab, und der direkte Aufruf endet in einer Moodle-Fehlermeldung „keine Berechtigung" — nicht in einem leeren Bericht.

### uat08 — Technisches Audit: Volltext-Modal über Augen-Icon *(automatisiert ungetestet — manuelle Prüfung besonders wertvoll)*

**Rolle:** Administrator
1. `/local/elediaai_core/audit_technical.php` öffnen (mind. 1 Eintrag vorhanden, siehe Vorbereitung).
2. In der Spalte Prompt auf das Augen-Icon klicken.
3. Das Modal mit `Esc` schließen und dasselbe für die Spalte Antwort wiederholen.

**Erwartet:** Ein Dialogfenster öffnet sich mit dem vollständigen Prompt- bzw. Antwort-Text; Zeilenumbrüche bleiben erhalten, die Tabelle dahinter läuft nicht über. `Esc` und der Schließen-Button schließen das Fenster, der Tastatur-Fokus kehrt zum Augen-Icon zurück.

### uat09 — Schnellfilter und rote Fehlerzeilen *(automatisiert ungetestet — manuelle Prüfung besonders wertvoll)*

**Rolle:** Administrator
1. Im technischen Audit die Schnellfilter oberhalb der Tabelle nacheinander anklicken: **Alle**, **Nur Teilnehmer:innen**, **Text generieren**, **Zusammenfassen**, **Erklären**, **Nur Fehler**.
2. Bei „Nur Fehler" die Zeilendarstellung prüfen.

**Erwartet:** Jeder Filter reduziert die Tabelle auf die passenden Einträge; der aktive Filter ist hervorgehoben. Fehlgeschlagene Aktionen zeigen ein rotes X in der Erfolg-Spalte und die ganze Zeile ist rötlich hervorgehoben; die Tokens-Zelle fehlgeschlagener Aufrufe zeigt „—".

### uat10 — CSV-Export bleibt lesbarer Text *(automatisiert ungetestet — manuelle Prüfung besonders wertvoll)*

**Rolle:** Administrator
1. Im technischen Audit den Bericht über die Download-Funktion als CSV herunterladen.
2. Die Datei in einer Tabellenkalkulation öffnen.

**Erwartet:** Prompt und Antwort erscheinen als reiner, gekürzter Text (max. 240 Zeichen) ohne HTML-Code oder Button-Reste; die Erfolg-Spalte enthält „Ja"/„Nein"; Tokens stehen im Format „420 / 180".

### uat11 — Didaktisches Audit *(automatisiert ungetestet — manuelle Prüfung besonders wertvoll)*

**Rolle:** Administrator
1. In der Audit-Übersicht **Didaktisches Audit öffnen** anklicken (`/local/elediaai_core/audit_didactic.php`).
2. Die Liste der Top-Kontexte prüfen.
3. `?limit=10` an die URL anhängen und neu laden.

**Erwartet:** Eine nach Anzahl sortierte Liste der Kursbereiche mit den meisten Zusammenfassungs-/Erklär-Anfragen, aufgeschlüsselt nach beiden Typen. Ohne solche Anfragen erscheint ein Leerzustand-Hinweis. Mit `limit=10` werden höchstens 10 Kontexte gezeigt.

### uat12 — Audit-Einstellungen wirken sofort *(automatisiert ungetestet — manuelle Prüfung besonders wertvoll)*

**Rolle:** Administrator + Lehrkraft
1. Als Admin `/local/elediaai_core/audit_settings.php` öffnen (Zahnrad auf einer Audit-Seite).
2. Zugriff auf „nur Administratoren" stellen, Anonymisierung aktivieren, die Anzeige von Prompts deaktivieren, speichern.
3. Das technische Audit als Admin neu laden; danach als Nutzer mit Audit-Berechtigung (Nicht-Admin) den Zugriff testen.
4. Anschließend alle Einstellungen wieder zurücksetzen.

**Erwartet:** Nach dem Speichern erscheint „Änderungen gespeichert". Die Nutzer-Spalte zeigt Rollen-/Kontext-Labels statt Klarnamen, die Prompt-Spalte bleibt leer (auch im CSV-Export), und Nicht-Admins haben keinen Zugang mehr zum Audit.

### uat13 — Hilfe-Handbuch in der Suite

**Rolle:** Lehrkraft
1. Auf einer Suite-Seite das Hilfe-Icon oben (Zone A) anklicken (`/local/elediaai_core/help.php`).

**Erwartet:** Das Suite-Handbuch erscheint als formatierter Text innerhalb der KI-Suite-Oberfläche (gleicher Kopfbereich und gleiche Tab-Leiste). Fehlt das Handbuch oder gibt es nur eine englische Fassung, erscheint ein entsprechender Hinweis — keine Fehlerseite.

## Nicht testen / bekannt

Diese Punkte sind bekannt bzw. in Arbeit — bitte **keine** Bug-Meldungen dazu anlegen:

- **Übersetzungs-Wartung für Namen:** `quiz.name` und `question.name` besitzen kein Formatfeld und bleiben deshalb bewusst ausserhalb der Rich-Text-Span-Wartung.
- **Kursgenerator:** `coursegen` wird als eigene Funktion getestet — siehe `public/local/elediaai_coursegen/docs/06-uat.md`. Hier in der Suite nur prüfen, dass die Kachel erscheint und startet.
- **bug01 (Core-Moodle, mitigiert):** Der OpenAI-Provider von Moodle kann bei bestimmten Modellen (`gpt-5*`) ohne Deploy-Hotfix abstürzen. Fehlgeschlagene KI-Aktionen mit diesem Muster bitte nur melden, wenn sie auf der Testinstanz trotz aktuellem Tag auftreten.
- **Anonymisierung ist nur Anzeige-Ebene:** Moodle Core speichert echte Nutzer-IDs weiter in seinen `core_ai`-Tabellen — das ist dokumentiertes Verhalten, kein Datenschutz-Bug.
- **Audit-Vollständigkeit:** Das Audit zeigt nur Aktionstypen, die Moodle Core protokolliert (Text generieren, Zusammenfassen, Erklären). Fehlende andere Aktionstypen sind keine Lücke des Plugins.
- **KI-Inhaltsqualität:** Ob eine KI-Antwort fachlich gut ist, ist nicht Gegenstand dieses UAT — nur ob sie erscheint und sich beobachtbar auf die Eingabe bezieht.
- Die Workflows der Schwester-Plugins (Fragen-Generator-Review, KI-Feedback-Freigabe, Chat-Threads) haben eigene Testanleitungen und werden hier nur als Einstiegspunkte geprüft.

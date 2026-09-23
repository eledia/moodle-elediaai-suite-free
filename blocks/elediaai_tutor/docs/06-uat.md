# eLeDia.ai Tutor (Chat-Block) — UAT-Testanleitung

> Verweis: Melderegeln und Zugänge im UAT-Handbuch (public/local/elediaai_core/docs/uat-handbuch.md). Testinstanz: https://demo.eledia.ai gegen den im Issue genannten Tag.

## Was das Plugin macht

Der eLeDia.ai Tutor ist ein Moodle-Block, der Lernenden und Lehrenden eine eingebettete KI-Chat-Oberfläche direkt in Kursen und auf dem Dashboard bereitstellt. Die eigentlichen Antworten erzeugt ein externer RAG-/Tutor-Dienst; Moodle bleibt die Vertrauens- und Kontextquelle, sodass keine Server-Schlüssel in den Browser gelangen. Je nach Einrichtung beantwortet der Tutor allgemeine Fragen (globaler Chat) oder erklärt kursbezogene Inhalte (Kurs-Chat), inklusive Datenschutz-Zustimmung, Gesprächsverlauf und eigener Daten-Löschung.

## Vorbereitung

- **Rollen:** Ein **Administrator**-Konto, ein **Trainer:in**-Konto (in mind. einem Kurs) und ein **Teilnehmer:in**-Konto.
- **Block platzieren:** Als Trainer:in im Testkurs **Bearbeiten einschalten** → **Block hinzufügen** → **eLeDia.ai Tutor**. In der Blockkonfiguration prüfen: Blocktitel, **Kurskontext übergeben**, Anzeigemodus, Persona/Begrüßung. Speichern.
- **Kapazität:** Zum Chatten braucht die Rolle `block/elediaai_tutor:use` (Standard: eingeloggte Nutzer:innen). Ohne diese Berechtigung bleibt der Block leer.
- **Backend:** Der Tutor braucht einen erreichbaren RAG-/Tutor-Server. Ist er auf der Testinstanz nicht eingerichtet, erscheint statt der Chat-UI eine Hinweis-/Fehlermeldung (für Lernende freundlich, für Admins mit Detail). Das ist erwartetes Verhalten — siehe „Nicht testen".
- **Standalone-Seite:** Der Chat ist zusätzlich unter `/blocks/elediaai_tutor/view.php` (globaler Chat) bzw. `?courseid=<id>` (Kurs-Chat) erreichbar.

## Testfälle

### uat01 — Block hinzufügen und Chat sichtbar

**Rolle:** Trainer:in
1. Im Testkurs Bearbeiten einschalten und den Block **eLeDia.ai Tutor** hinzufügen.
2. Blockkonfiguration öffnen, Titel und Anzeigemodus prüfen, speichern.
3. Bearbeiten ausschalten und die Kursseite normal betrachten.

**Erwartet:** Der Block erscheint mit dem gesetzten Titel und einer Chat-Oberfläche bzw. einer Start-Schaltfläche „Tutor öffnen" (je nach Anzeigemodus: eingebettet, angedockt, modal, Vollbild).

### uat02 — Datenschutz-Zustimmung bei Erstöffnung

**Rolle:** Teilnehmer:in (Konto, das den Tutor noch nie genutzt hat)
1. Den Tutor-Chat zum ersten Mal öffnen.
2. Den angezeigten Datenschutzhinweis lesen.
3. Das Kästchen „Ich habe die Datenschutzhinweise zur Kenntnis genommen." aktivieren und **Zustimmen und starten** klicken.

**Erwartet:** Vor der ersten Nutzung erscheint ein Zustimmungs-Gate. Erst nach Bestätigung wird das Eingabefeld freigegeben. Ohne Häkchen lässt sich nicht fortfahren.

### uat03 — Nachricht senden und Antwort erhalten

**Rolle:** Teilnehmer:in
1. Im Eingabefeld „Fragen Sie den Tutor etwas …" eine Frage eingeben.
2. Absenden (Enter oder Senden-Schaltfläche).
3. Warten, bis die Antwort erscheint.

**Erwartet:** Die eigene Nachricht erscheint als Blase, danach die Tutor-Antwort. Ist der Backend-Dienst nicht erreichbar, erscheint stattdessen eine verständliche Fehlermeldung (z. B. „Der Tutor konnte gerade nicht antworten. Bitte versuchen Sie es erneut.") — kein stilles Hängenbleiben, keine weiße Fehlerseite.

### uat04 — Tastaturbedienung Enter / Shift+Enter

**Rolle:** Teilnehmer:in
1. Text eingeben und **Enter** drücken.
2. Neuen Text eingeben, **Shift+Enter** drücken, weiterschreiben, dann senden.

**Erwartet:** Enter sendet die Nachricht; Shift+Enter fügt einen Zeilenumbruch ein, ohne zu senden.

### uat05 — Antwort kopieren und Fehler erneut versuchen

**Rolle:** Teilnehmer:in
1. Bei einer erhaltenen Antwort die Kopier-Aktion („Antwort kopieren") nutzen.
2. Falls eine Anfrage fehlschlägt, die angebotene Wiederholung nutzen.

**Erwartet:** Der Antworttext landet in der Zwischenablage. Bei einem Fehler lässt sich die letzte Anfrage erneut auslösen.

### uat06 — Verlauf überlebt Reload und lässt sich fortsetzen

**Rolle:** Teilnehmer:in (Verlauf muss aktiviert sein)
1. Ein paar Nachrichten senden.
2. Die Seite neu laden.
3. Den Gesprächsverlauf öffnen und ein früheres Gespräch erneut anzeigen.

**Erwartet:** Nach dem Reload ist das laufende Gespräch wieder da. Über „Gesprächsverlauf" lassen sich frühere Unterhaltungen öffnen. Ist der Verlauf deaktiviert, entfällt diese Ansicht ohne Fehler.

### uat07 — Neues Gespräch und Gespräch leeren

**Rolle:** Teilnehmer:in
1. „Neues Gespräch" starten.
2. Danach „Gespräch leeren" nutzen.

**Erwartet:** „Neues Gespräch" beginnt einen leeren Thread (Bestätigung „Neues Gespräch gestartet."); „Gespräch leeren" entfernt die sichtbaren Nachrichten des aktuellen Gesprächs.

### uat08 — Eigene Tutor-Daten löschen

**Rolle:** Teilnehmer:in (mit `deleteownhistory`)
1. Im Datenschutz-/Verlaufsbereich **Alle meine Tutor-Daten löschen** wählen.
2. Die Rückfrage „Alle Tutor-Daten löschen?" mit **Ja, alles löschen** bestätigen.

**Erwartet:** Es erscheint eine Bestätigung mit der Anzahl gelöschter Gespräche. Der Verlauf ist danach leer. Rollen ohne diese Berechtigung sehen die Löschaktion nicht.

### uat09 — Kurs-Chat ohne Block bleibt gesperrt

**Rolle:** Teilnehmer:in
1. Einen Kurs **ohne** Tutor-Block wählen und `/blocks/elediaai_tutor/view.php?courseid=<kursid>` direkt aufrufen.

**Erwartet:** Es erscheint der Hinweis „Der Tutor ist in diesem Kurs nicht aktiviert. Lehrende aktivieren ihn, indem sie den eLeDia.ai-Tutor-Block zum Kurs hinzufügen." — keine Chat-Oberfläche, keine Fehlerseite.

### uat10 — Berechtigung: Rolle ohne Zugriff sieht nichts

**Rolle:** Administrator + Teilnehmer:in
1. Als Admin für die Teilnehmer:in-Rolle die Berechtigung `block/elediaai_tutor:use` im Kurs entziehen (oder ein Konto ohne diese Berechtigung nutzen).
2. Als diese:r Teilnehmer:in die Kursseite mit dem Block öffnen.

**Erwartet:** Der Block-Inhalt bleibt leer; es gibt keine Eingabemöglichkeit und keinen Chat.

### uat11 — Anzeigemodus und Start-Schaltfläche

**Rolle:** Trainer:in
1. In der Blockkonfiguration den Anzeigemodus auf „Angedocktes schwebendes Panel", „Modaler Dialog" bzw. „Vollbild" stellen und speichern.
2. Als Teilnehmer:in die Start-Schaltfläche „Tutor öffnen" anklicken.

**Erwartet:** Je nach Modus öffnet sich der Chat als schwebendes Panel, modaler Dialog oder Vollbild. Der eingebettete Modus zeigt den Chat direkt im Block ohne Start-Schaltfläche.

### uat12 — Deutsche Oberfläche

**Rolle:** Teilnehmer:in (Sprache Deutsch)
1. Chat, Verlauf, Zustimmungs-Gate und Fehlermeldungen durchgehen.

**Erwartet:** Alle sichtbaren Beschriftungen sind deutsch (z. B. „Fragen Sie den Tutor etwas …", „Gesprächsverlauf", „Zustimmen und starten"). Deutsch-Englisch-Mischungen bitte melden (S3).

## Nicht testen / bekannt

Diese Punkte sind bekannt bzw. in Arbeit — bitte **keine** Bug-Meldungen dazu anlegen:

- **Lokaler End-to-End-Smoke offen (task02 / test01, partial):** Ein sauberer, reproduzierbarer Chat-Durchlauf gegen einen echten RAG-Server ist noch nicht abgeschlossen dokumentiert. Wenn der Tutor auf der Testinstanz gar nicht antwortet, prüfen, ob das Backend im Issue als konfiguriert angegeben ist.
- **RAG-URL aus Docker (bug01, mitigiert):** In manchen Setups ist der RAG-Endpunkt aus dem Server heraus nicht erreichbar. Nur melden, wenn es trotz laut Issue konfiguriertem Backend auftritt.
- **`MODIFIER_COMPACT` (bug18, fixed SUI-53):** Ungenutzte interne Konstante wurde entfernt — nicht relevant für den UAT.
- **AI-Home/App-Hero (task09, geplant):** Der Hero-Startseiten-Modus in der Moodle-App ist für ein späteres Release geplant; nicht prüfen.
- **Automatisiert ungetestet (05-quality):** Das AMD-/JavaScript-Verhalten, der Screenreader-Durchlauf sowie die SVG-/`customcss`-Härtung (bug13/bug14) sind nur statisch bzw. per Code-Review abgesichert — manuelle Beobachtung hier besonders wertvoll, aber kein automatischer Testnachweis.
- **KI-Inhaltsqualität:** Ob eine Antwort fachlich gut ist, ist nicht Gegenstand des UAT — nur ob sie erscheint und sich beobachtbar auf die Eingabe bezieht.

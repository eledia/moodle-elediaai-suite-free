# eLeDia.ai Tutor — Hilfe und Handbuch

## Meta

Deutsche Fassung der Nutzerdokumentation (`feat01`–`feat08`). Die Basisdatei
`02-user-doc.md` ist Englisch (`docsBase: en`). `help.php` zeigt diese Datei für
deutschsprachige Personen zur Laufzeit an; dieser Abschnitt erscheint dort
nicht. Datei nicht umbenennen oder verschieben. Stand: Release 1.0.22.

---

## Überblick

Der eLeDia.ai Tutor bringt eine KI-Tutor-Chatoberfläche direkt in Moodle: in Kurse, auf das Dashboard, als schwebenden Startknopf auf jeder Seite, auf eine eigene Tutor-Seite und in die Moodle App.

Der Tutor unterstützt Lernende dort, wo sie gerade arbeiten. Je nach Einrichtung beantwortet er Fragen aus den Kursmaterialien, erklärt Inhalte, gibt Orientierung, stellt Rückfragen oder nutzt Moodle-Werkzeuge im Namen der fragenden Person.

Das Plugin ist die Moodle-Oberfläche des Tutors. Das Gespräch führt die KI-Chat-Engine der Suite, die Antworten erzeugt der angebundene KI-Dienst. API-Schlüssel, Service-Tokens und technische Endpunkte bleiben auf dem Moodle-Server und werden nicht an den Browser ausgeliefert.

### Rollen und typische Aufgaben

| Rolle | Typische Aufgaben |
|---|---|
| Lernende | Tutor öffnen, Fragen stellen, Antworten lesen, Verlauf nutzen, Langzeitgedächtnis verwalten, eigene Daten löschen. |
| Lehrende | Tutor-Block im Kurs platzieren, Wissensbasis und Blockinstanz einstellen, Fragen der Lernenden auswerten lassen. |
| Administrator/innen | Tutor-Profile und Designs verwalten, Datenschutztext und Tutor-Einstellungen pflegen, Verbindung und Grenzen in der KI-Chat-Engine einrichten. |

## Den Tutor nutzen

1. Öffnen Sie einen Kurs, das Dashboard oder eine Seite mit dem **eLeDia.ai Tutor**.
2. Klicken Sie auf die Startschaltfläche des Tutors oder nutzen Sie den eingebetteten Chat.
3. Geben Sie eine Frage ein oder wählen Sie eine vorgeschlagene Einstiegsfrage.
4. Lesen Sie die Antwort und stellen Sie bei Bedarf eine Folgefrage.

Hinweise zur Bedienung:

- **Enter** sendet eine Nachricht, **Shift+Enter** fügt einen Zeilenumbruch ein.
- Wenn die Website oder der Block es erlaubt, wechseln Sie den Antwortstil zwischen **Erklären**, **Nur Hinweise** und **Abfragen**.
- Antworten lassen sich kopieren. Schlägt eine Antwort fehl, können Sie es erneut versuchen.
- **Neues Gespräch** (Plus) beginnt ein neues Gespräch, auch wenn das Fenster gerade leer ist.
- Ist der Gesprächsverlauf eingeschaltet, öffnen Sie frühere Gespräche über das Verlaufssymbol. Der Papierkorb neben einem Gespräch löscht es, hier und beim KI-Agenten.
- Antworten, die nicht aus den Kursmaterialien stammen, sind als „Allgemeine Antwort“ gekennzeichnet.
- Haben Sie gerade einen Test offen, lehnt der Tutor Ihre Nachrichten möglicherweise ab und nennt den Test. Nach der Abgabe können Sie ihn wieder nutzen (siehe „Während Tests sperren“).

### Datenschutzhinweise, Langzeitgedächtnis und Ihre Daten

Das Schild in der Kopfzeile des Tutors öffnet die **Datenschutzhinweise**. Dort finden Sie:

- einen Hinweis, dass KI-Antworten falsch sein können, und was gesendet und gespeichert wird,
- den Schalter für das **Langzeitgedächtnis**: Damit darf sich der Tutor Informationen über Gespräche hinweg merken. Er ist standardmäßig aus und nur verfügbar, wenn die Website ihn anbietet,
- **„Ansehen, was der Tutor sich über mich gemerkt hat“**: eine Übersicht der Erinnerungen nach Kategorie und der Notizen zu Ihren Gesprächen mit Kurs und Datum. Die Daten werden beim Öffnen abgerufen und nicht in Moodle gespeichert. Ist nichts gespeichert, steht dort „Es ist nichts gespeichert.“ Stimmt eine Erinnerung nicht, sagen Sie es dem Tutor im Gespräch,
- **„Alle meine Tutor-Daten löschen“**.

Wenn Sie das Langzeitgedächtnis abschalten, fragt der Tutor nach: Alles Gemerkte wird sofort und endgültig gelöscht. Mit **Vorher ansehen** prüfen Sie zuerst, was gespeichert ist. Abbrechen oder Schließen lässt das Gedächtnis eingeschaltet.

## Kurskontext und Wissensbasis

Aus welchem Material der Tutor antwortet, legt die Blockinstanz fest. Für kursbezogene Antworten müssen drei Dinge zusammenpassen:

1. Der Block steht in einem Kurs, oder in der Instanz ist eine **Wissensbasis** gewählt.
2. In der Blockinstanz ist **Kurskontext übergeben** aktiviert, oder eine Wissensbasis ist gewählt.
3. Die Kursinhalte wurden über **KI-Quellen** eingelesen und sind beim KI-Dienst verfügbar.

Die **Wissensbasis** einer Instanz besteht aus Kursbereichen und Kursen. Ein Bereich schließt alle Kurse darunter ein, auch später hinzugefügte. Ohne Auswahl sucht der Tutor in dem Kurs, in dem er steht. Ist die fragende Person in keinem der gewählten Kurse eingeschrieben, antwortet der Tutor ohne Kursmaterial.

Die **Antwortquelle** einer Instanz ist entweder „In Kursmaterialien verankert“ oder „Allgemeinwissen (nur LLM)“. Den zweiten Modus gibt es nur, wenn die Website „Nur-LLM-Modus erlauben“ eingeschaltet hat. Ein Kurs ohne eingelesene Wissensbasis antwortet dann aus dem Allgemeinwissen; ist der Modus nicht erlaubt, zeigt der Tutor „nicht verfügbar“.

Wenn der Tutor nur allgemeine Antworten gibt, obwohl er im Kurs verwendet wird, prüfen Sie zuerst diese Punkte. Ein nicht eingelesener Kurs ist die häufigste Ursache. Die Startseite der Website wird nie eingelesen; ein Tutor dort braucht eine Wissensbasis, um aus Material zu antworten.

### Tutor-Block bereitstellen

1. Öffnen Sie den Kurs als Trainer/in oder Administrator/in.
2. Aktivieren Sie **Bearbeiten**.
3. Fügen Sie den Block **eLeDia.ai Tutor** hinzu.
4. Öffnen Sie die Blockkonfiguration.
5. Prüfen Sie insbesondere:
   - **Blocktitel**
   - **Kurskontext übergeben**, **Antwortquelle** und **Wissensbasis**
   - **Tägliches Nachrichtenlimit** (-1 = Website-Voreinstellung, 0 = unbegrenzt)
   - **Persona & System-Prompt**
   - **Begrüßung und Vorschlagsfragen**
   - **Während Tests sperren**
   - **Design-Einstellungen**, falls pro Instanz erlaubt
6. Speichern Sie und testen Sie den Tutor mit einer Rolle, die den Block nutzen darf (`block/elediaai_tutor:use`).

## Einrichtung

Die Einrichtung verteilt sich auf zwei Stellen.

**Tutor-Konfiguration** (Plugin-Shell des Tutors, auch unter *Website-Administration > Plugins > Blöcke > eLeDia.ai Tutor*):

| Bereich | Zweck |
|---|---|
| Zustand | Prüfen, ob der Tutor einsatzbereit ist: Chat-Bereiche, Datenschutztext, letzter Fehler. |
| Einstellungen | Design, Gespräch & Anzeige, Datenschutz und technische Optionen des Tutors. |
| Tutoren | Websiteweite Tutor-Profile und Designs verwalten, importieren und exportieren. |
| Vorschau | Nutzererlebnis mit der aktuellen Konfiguration prüfen. |
| LiteRAG, KI-Quellen, MCP | Sprung zu den Zusatzplugins, sofern installiert. |

Wichtige Tutor-Einstellungen:

| Einstellung | Bedeutung |
|---|---|
| Globalen Chat aktivieren / Kurs-Chat aktivieren | Ob allgemeine bzw. kursbezogene Gespräche erlaubt sind. |
| Websiteweiter schwebender Tutor | Zeigt den Tutor auch außerhalb der Blockbereiche. Kurse werden weiterhin durch den Tutor-Block freigeschaltet; danach bleibt der Tutor auf Unterseiten und in Aktivitäten verfügbar. |
| Nur-LLM-Modus erlauben | Ob Instanzen aus dem Allgemeinwissen des Sprachmodells antworten dürfen. |
| Bestätigung bei der ersten Nutzung verlangen | Standardmäßig aus. Wer eine dokumentierte Kenntnisnahme braucht, schaltet sie ein. |
| Text der Datenschutzhinweise | Einrichtungsspezifischer Text, jederzeit über das Schild im Tutor erreichbar. |
| Alle 14 Tage alle Fragen neu einordnen | Standardmäßig aus; siehe „KI-Deutung der Fragen“. |
| Protokollierungsdetailgrad | Wie ausführlich Fehler protokolliert werden. |
| Eigenes CSS | Vertrauenswürdige Anpassungen für die Klassen `.elediaai-chat-*`. |

Unter **Technische Einstellungen** stehen zwei Hinweise ohne eigenen Wert: **Verbindung zum Agenten** verlinkt die Einstellungen der KI-Chat-Engine, **Aufbewahrung der Fragen** die Seite *KI-Audit* des Suite-Kerns. Dort legt „Fragen und Antworten aufbewahren“ (Standard 90 Tage) fest, wann aufgezeichnete Fragen gelöscht werden. Bis 1.0.22 bot der Tutor eine eigene „Aufbewahrung der Fragen-Analyse (Tage)“ an; sie hatte keine Wirkung und ist seit 1.0.23 entfernt. Das Upgrade übernimmt den Tutor-Wert in den Kern, wenn er kürzer ist als die Frist des Kerns; eine längere Frist wird nie übernommen.

**KI-Chat-Engine** (*Website-Administration > Plugins > Lokale Plugins > KI-Chat-Engine*): Hier liegen Verbindung und Grenzen, die für alle Chat-Flächen der Suite gelten.

| Einstellung | Bedeutung |
|---|---|
| MCP-Endpunkt des Agenten, Authentifizierungs-Token | Adresse und Zugang des RAG-Agenten. Welches Backend antwortet, folgt dem Ziel, das in KI-Quellen festgelegt ist. |
| Webservice für Rückrufe | Webservice aus MCP, auf den die nutzerbezogenen Moodle-Tokens beschränkt sind. |
| Gültigkeit des Rückruf-Tokens | Lebensdauer dieser Tokens. |
| Langzeitgedächtnis anbieten, Fragen nachts neu gruppieren, Verlauf und Löschen beim Agenten | Optionale Werkzeuge des Agenten. |
| Zeitgrenze je Anfrage, Maximale Nachrichtenlänge, Anfragen je Minute, Nachrichten je Tag | Grenzen je Anfrage und Person. |
| Antworten fortlaufend anzeigen | Streaming; braucht einen Webserver ohne Pufferung (siehe README). |
| Chat während Tests sperren | Websiteweite Testsperre, standardmäßig aus. |

### Empfohlene Einrichtungsschritte

1. Zusatzplugins installieren: KI-Chat-Engine, MCP und KI-Quellen; bei Bedarf LiteRAG.
2. Das Ziel für Kursmaterial in KI-Quellen festlegen und die Verbindung zum Agenten in der KI-Chat-Engine einrichten.
3. Den Webservice für Rückrufe in der KI-Chat-Engine wählen.
4. In der Tutor-Konfiguration Datenschutztext und Chat-Bereiche setzen.
5. Den Bereich **Zustand** prüfen, bis alles bereit ist.
6. Einen Kurs über KI-Quellen einlesen.
7. Den Tutor-Block im Kurs hinzufügen und **Kurskontext übergeben** aktivieren oder eine Wissensbasis wählen.
8. Im Kurs eine fachliche Testfrage stellen.
9. Standard-Tutor und Design für den Produktivbetrieb festlegen.

## Tutor-Profile und Design

Tutor-Profile beschreiben, wie ein Tutor wirkt und antwortet. Sie bündeln unter anderem:

- Name und sichtbare Persona.
- Begrüßung und Einstiegsfragen, auf dem Dashboard getrennt nach Lernenden, Trainer/innen und Manager/innen.
- System-Prompt und Antwortstil.
- Farben, Avatar, Nachrichtenblasen und Startschaltfläche.
- Stil der Startschaltfläche, darunter **Schwebende Schaltfläche mit Beschriftung (untere Ecke)**: Logo und Text als Pille. Der Text kommt aus „Beschriftung der Startschaltfläche“; ist sie leer, steht der Name des Tutors darauf. Unter 768 Pixel Breite wird die Schaltfläche wieder zum Kreis.
- Breite des Chatfensters: Standard (380 / 640 px), Breit (480 / 800 px) oder Sehr breit (600 / 960 px) für das angedockte Fenster bzw. den Dialog. Auf kleinen Bildschirmen wird das Fenster nie breiter als der Bildschirm.
- Governance-Regeln, welche Werte pro Blockinstanz überschrieben werden dürfen.

Ein websiteweiter Standard-Tutor sorgt für ein konsistentes Erlebnis. Einzelne Blockinstanzen können davon abweichen, wenn Administrator/innen die jeweiligen Überschreibungen erlauben.

### Mehrsprachige Texte

Begrüßung, Persona, Einstiegsfragen, Dashboard-Begrüßung, Startschaltfläche und Fußzeile lassen sich in mehreren Sprachen hinterlegen. Jede Person sieht dann die Sprache, die sie in Moodle eingestellt hat; auch das KI-Modell erhält nur diese. Jede Sprache steht in einem eigenen Block:

```
<span lang="de" class="multilang">Hallo! Wie kann ich helfen?</span><span lang="en" class="multilang">Hello! How can I help?</span>
```

Mit dem Zusatzplugin „Multi-Language Content (v2)“ geht auch `{mlang de}Hallo!{mlang}{mlang en}Hello!{mlang}`. Voraussetzung ist, dass der Filter „Mehrsprachiger Inhalt“ (bzw. v2) unter *Website-Administration > Plugins > Filter > Filter verwalten* eingeschaltet ist; „Alle Zeichenketten filtern“ ist nicht nötig. Die Hilfe-Icons an den Textfeldern zeigen dasselbe Beispiel.

## Während Tests sperren

Der Tutor kann Nachrichten ablehnen, solange eine Person einen Testversuch offen hat. Jeder offene Versuch zählt, in jedem Kurs; statt einer Antwort nennt der Tutor den Test. Vorschauversuche und Tests, die die Person nicht mehr erreichen kann, zählen nicht. Lehrende sind über die Berechtigung „Chat während Tests nutzen“ (`local/elediaai_chatengine:bypassquizlock`) ausgenommen.

Die Einstellung **Während Tests sperren** gibt es auf der Website und je Blockinstanz:

- **Website-Standard verwenden**: was die Website-Einstellungen des Tutors sagen.
- **Wie in der Chat-Engine eingestellt** (Standard): die Website-Einstellung „Chat während Tests sperren“ der KI-Chat-Engine.
- **Sperren** / **Nicht sperren**: für diesen Tutor, unabhängig von der Website.

Der websiteweite Startknopf folgt der Blockinstanz, die den Kurs freischaltet.

## KI-Deutung der Fragen

Lehrende mit dem Recht `block/elediaai_tutor:viewreports` öffnen im Kurs die Seite **KI-Deutung der Fragen**. Dort lässt sich eine KI-Analyse erstellen: Sie fasst zusammen, womit Lernende Schwierigkeiten haben, und schlägt Maßnahmen vor. Grundlage sind anonymisierte Fragen der Lernenden. Die Zahlen, die Themen und die Fragen selbst stehen in den **Kurs-Einblicken** der Suite, die von dieser Seite verlinkt sind. Liegen noch zu wenige Fragen vor, sagt die Seite das.

Jede Nacht ordnet der Tutor neue Fragen einem Thema zu, sofern das Backend das anbietet. Eine einmal eingeordnete Frage behält ihr Thema. Wer abgedriftete Themen reparieren will, schaltet „Alle 14 Tage alle Fragen neu einordnen“ ein; an diesem Tag ändern sich die Zahlen.

Wie lange die Fragen aufbewahrt werden, legt der Kern der Suite fest („Fragen und Antworten aufbewahren“ auf der Seite *KI-Audit*, Standard 90 Tage); unter 30 Tagen wird die Themenauswertung unbrauchbar.

## Moodle App

Weil Blöcke von Drittanbietern in der Moodle App nicht wie im Web erscheinen, bringt der Tutor eine eigene Seite mit:

```text
https://IHRE-WEBSITE/blocks/elediaai_tutor/view.php
https://IHRE-WEBSITE/blocks/elediaai_tutor/view.php?courseid=N
```

`?embedded=1` zeigt eine reduzierte Ansicht ohne Moodle-Rahmen. Anmeldung, Einschreibung, Berechtigungen und Grenzen gelten wie im Web.

## Datenschutz und Datensparsamkeit

Der Tutor arbeitet serverseitig. Der Browser spricht nur mit Moodle, nicht direkt mit dem KI-Dienst. Dadurch bleiben technische Zugangsdaten geschützt.

Wichtig für den Betrieb:

- Die Datenschutzhinweise werden bereitgestellt; Art. 13 DSGVO verlangt Information, keine Zustimmung. Wer trotzdem eine dokumentierte Kenntnisnahme braucht, schaltet „Bestätigung bei der ersten Nutzung verlangen“ ein (standardmäßig aus).
- Die Gespräche speichert die KI-Chat-Engine in Moodle; deren Datenschutz-Provider exportiert und löscht sie. Ein KI-Dienst, der Gespräche speichert, hält eine eigene Kopie nach seinen Regeln.
- Der Tutor selbst speichert nur die Bestätigung bei der ersten Nutzung (wenn verlangt), kurze Diagnosen ohne Prompts und Antworten und die Einstellung zum Langzeitgedächtnis.
- „Alle meine Tutor-Daten löschen“ entfernt die lokalen Tutor-Daten und fordert, wenn der KI-Dienst Löschwerkzeuge anbietet, die externe Löschung zusätzlich an. Ihr Ergebnis wird separat angezeigt.
- Die reguläre Moodle-Kontolöschung entfernt dieselben lokalen Tutor-Daten automatisch. Ist die externe Löschung eingerichtet, fordert Moodle sie an, bevor das Nutzer-Token ungültig wird; Fehler beim Dienst verhindern die lokale Bereinigung nicht.
- Der Datenschutz-Provider des Plugins unterstützt Moodle-Exporte und Löschprozesse.

## Fehlerbehebung und Wartung

### Der Tutor-Dienst ist nicht verfügbar

Prüfen Sie:

- Ist der MCP-Endpunkt des Agenten in der KI-Chat-Engine gesetzt und vom Moodle-Server aus erreichbar?
- Ist die Authentifizierung korrekt?
- Ist „Antworten fortlaufend anzeigen“ aktiv, obwohl der Webserver puffert? Dann erscheinen Antworten erst nach einer Wartezeit am Stück.
- Sind KI-Chat-Engine und MCP installiert und aktiv?

### Der Tutor antwortet, nutzt aber keinen Kurskontext

Prüfen Sie:

- Ist **Kurs-Chat aktivieren** eingeschaltet?
- Übergibt die Blockinstanz den Kurskontext, oder ist eine Wissensbasis gewählt?
- Wurde der Kurs über KI-Quellen eingelesen?
- Hat die angemeldete Person Zugriff auf den Kurs und die relevanten Inhalte?

### Der Tutor lehnt Nachrichten während eines Tests ab

Das ist die Testsperre. Prüfen Sie die Einstellung **Während Tests sperren** der Blockinstanz und „Chat während Tests sperren“ in der KI-Chat-Engine.

### Der Chat-Button reagiert nicht

Prüfen Sie:

- Sind Moodle-Caches geleert?
- Enthält die Browser-Konsole JavaScript-Fehler?
- Ist die Blockinstanz sichtbar und für die Nutzerrolle erlaubt?

### Eine Blockinstanz zeigt andere Werte als erwartet

Prüfen Sie:

- Ob die Website einen Standard-Tutor erzwingt.
- Ob die jeweilige Einstellung pro Instanz überschrieben werden darf.
- Ob ein Tutor-Profil auf die Blockinstanz angewendet wurde.
- Ob veraltete Moodle-Caches aktiv sind.

### Betrieb

- Prüfen Sie nach Updates den Bereich **Zustand** des Tutors.
- Testen Sie nach Backend-Änderungen eine allgemeine und eine kursbezogene Frage.
- Leeren Sie Moodle-Caches nach Deployments.
- Überwachen Sie Fehler im Moodle-Log und beim KI-Dienst.
- Halten Sie Datenschutztexte aktuell, insbesondere wenn sich Dienst, Speicherorte oder Löschprozesse ändern.

## Barrierefreiheit

Die Oberfläche ist per Tastatur bedienbar. Neue Antworten werden Screenreadern angesagt, reduzierte Bewegung wird berücksichtigt, und Fokuszustände sind sichtbar. Das Overlay-Fenster des Tutors ist ein modaler Dialog.

### Weiterführende Dokumentation

- `README.de.md` im Plugin für Installation und Streaming-Betrieb.
- `docs/privacy.md` für Datenschutzdetails.
- `docs/security.md` für Sicherheitsmodell und technische Schutzmaßnahmen.

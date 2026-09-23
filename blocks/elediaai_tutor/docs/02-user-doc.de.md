# eLeDia.ai Tutor — Hilfe und Handbuch

## Überblick

Der eLeDia.ai Tutor bringt eine sichere KI-Tutor-Chatoberfläche direkt in Moodle-Kurse und Dashboards und verbindet sie serverseitig mit RAG-/MCP-Diensten, Kurskontext und zentral verwalteten Tutor-Profilen.

Der Tutor unterstützt Lernende dort, wo sie gerade arbeiten: im Kurs, auf einer Kursseite oder auf einer zentralen Tutor-Seite. Je nach Einrichtung kann er allgemeine Fragen beantworten, kursbezogene Inhalte erklären, Orientierung geben, Rückfragen stellen oder auf Moodle-Werkzeuge zugreifen.

Das Plugin selbst ist die Moodle-Oberfläche und der sichere Connector. Die eigentliche Antwortgenerierung, Retrieval-Logik und LLM-Verbindung liegen in den angebundenen Diensten, zum Beispiel LiteRAG, RAG-Ingest und MCP. Dadurch bleiben API-Schlüssel, Service-Tokens und technische Endpunkte auf dem Moodle-Server und werden nicht an den Browser ausgeliefert.

### Rollen und typische Aufgaben

| Rolle | Typische Aufgaben |
|---|---|
| Lernende | Tutor öffnen, Fragen stellen, Antworten lesen, Verlauf nutzen, eigene Daten löschen. |
| Lehrende | Tutor-Block im Kurs platzieren, Kurskontext aktivieren, Blockinstanz anpassen. |
| Administrator/innen | RAG-/MCP-Verbindungen einrichten, Tutor-Profile verwalten, Datenschutztexte und Limits pflegen. |

## Nutzung im Kurs

1. Öffnen Sie einen Kurs oder eine Kursseite mit dem Block **eLeDia.ai Tutor**.
2. Klicken Sie auf den Tutor-Button oder öffnen Sie den eingebetteten Chat.
3. Bestätigen Sie die Datenschutzhinweise, falls diese zum ersten Mal angezeigt werden.
4. Geben Sie eine Frage ein oder nutzen Sie eine vorgeschlagene Einstiegsfrage.
5. Lesen Sie die Antwort und stellen Sie bei Bedarf eine Folgefrage.

Hinweise zur Bedienung:

- **Enter** sendet eine Nachricht.
- **Shift+Enter** fügt einen Zeilenumbruch ein.
- Antworten können kopiert werden.
- Bei einem Fehler kann die letzte Anfrage erneut versucht werden.
- Wenn Verlauf aktiviert ist, können frühere Unterhaltungen wieder geöffnet werden.
- Im Datenschutzbereich können Nutzer/innen ihre eigenen Tutor-Daten löschen.

## Kurskontext und Blockinstanzen

Der Tutor kann nur mit Kurskontext arbeiten, wenn drei Dinge zusammenpassen:

1. Der Tutor-Block befindet sich in einem Kurs oder bekommt eine feste Kurs-ID.
2. In der Blockinstanz ist **Kurskontext übergeben** aktiviert.
3. Die Kursinhalte wurden durch RAG-Ingest indexiert und sind im RAG-Backend verfügbar.

Wenn der Tutor nur allgemeine Antworten gibt, obwohl er im Kurs verwendet wird, prüfen Sie zuerst diese Punkte. Ein nicht indexierter Kurs ist die häufigste Ursache dafür, dass keine kursbezogenen Antworten entstehen.

### Tutor-Block bereitstellen

1. Öffnen Sie den Kurs als Trainer/in oder Administrator/in.
2. Aktivieren Sie **Bearbeiten**.
3. Fügen Sie den Block **eLeDia.ai Tutor** hinzu.
4. Öffnen Sie die Blockkonfiguration.
5. Prüfen Sie insbesondere:
   - **Blocktitel**
   - **Kurskontext übergeben**
   - **Feste Kurs-ID** nur bei Sonderfällen, zum Beispiel auf Dashboard-Seiten
   - **Tägliches Nachrichtenlimit**
   - **Persona & System-Prompt**
   - **Begrüßung und Vorschlagsfragen**
   - **Design-Einstellungen**, falls pro Instanz erlaubt
6. Speichern Sie und testen Sie den Tutor mit einer Rolle, die den Block auch tatsächlich nutzen darf.

## Einrichtung und Zusatzplugins

Die zentrale Einrichtung erfolgt in der Plugin-Shell des Tutors und in der Moodle-Administration.

Wichtige Bereiche:

| Bereich | Zweck |
|---|---|
| Dashboard | Setup-Status prüfen und fehlende Zusatzplugins erkennen. |
| Einstellungen | Verbindung, Datenschutz, Limits, Anzeige und technische Optionen pflegen. |
| Tutoren | Websiteweite Tutor-Profile und Designs verwalten, importieren und exportieren. |
| Vorschau | Nutzererlebnis mit aktueller Konfiguration prüfen. |
| LiteRAG | RAG-Backend und LLM-Verbindung einrichten. |
| RAG-Ingest | Moodle-Kursinhalte für Retrieval indexieren. |
| MCP | Moodle-Werkzeuge und externe MCP-Services bereitstellen. |

Für einen funktionsfähigen Tutor müssen mindestens diese Punkte stimmen:

| Einstellung | Bedeutung |
|---|---|
| RAG-MCP-Server-URL | Endpunkt des Tutor-/RAG-Servers, zum Beispiel LiteRAG. |
| Chat-Tool-Name | MCP-Tool, das Chatantworten liefert, meist `tutor_chat`. |
| Externer MCP-Service | Service aus MCP, über den nutzerbezogene Moodle-Tokens erzeugt werden. Nur für geerdete Antworten mit Wissensbasis erforderlich. |
| Token-Lebensdauer | Gültigkeitsdauer der serverseitigen Moodle-MCP-Tokens. |
| Datenschutztext | Hinweis, den Nutzer/innen vor dem ersten Chat bestätigen. |
| Globaler Chat / Kurs-Chat | Legt fest, ob allgemeine und kursbezogene Gespräche erlaubt sind. |

Der reine LLM-Modus (Antworten ohne Retrieval und ohne Rückruf in Moodle) benötigt keinen externen MCP-Connector; er kommt zum Einsatz, wenn ein Kurs keine Wissensbasis hat.

Der Dashboard-Status sollte erst dann als bereit gelten, wenn die Verbindung zum LLM/RAG-Backend funktioniert und die erforderlichen Zusatzplugins verfügbar sind.

### Empfohlene Einrichtungsschritte

1. Zusatzplugins installieren: LiteRAG, RAG-Ingest und MCP.
2. LiteRAG mit LLM-Verbindung und Retrieval konfigurieren.
3. RAG-Ingest auf den LiteRAG-Endpunkt ausrichten.
4. MCP-Service konfigurieren und für den Tutor freigeben.
5. Tutor-Einstellungen öffnen und RAG-/MCP-Endpunkte, Tool-Namen und Datenschutztext setzen.
6. Dashboard prüfen, bis alle relevanten Bereiche grün sind.
7. Einen Kurs indexieren.
8. Tutor-Block im Kurs hinzufügen und **Kurskontext übergeben** aktivieren.
9. Im Kurs eine fachliche Testfrage stellen.
10. Standard-Tutor und Design für den Produktivbetrieb festlegen.

## Tutor-Profile und Design

Tutor-Profile beschreiben, wie ein Tutor wirken und antworten soll. Sie bündeln unter anderem:

- Name und sichtbare Persona.
- Begrüßung und Einstiegsfragen.
- System-Prompt und Antwortstil.
- Farben, Avatar, Nachrichtenblasen und Start-Schaltfläche.
- Governance-Regeln, welche Werte pro Blockinstanz überschrieben werden dürfen.

Ein websiteweiter Standard-Tutor sorgt für ein konsistentes Erlebnis. Einzelne Blockinstanzen können davon abweichen, wenn Administrator/innen die jeweiligen Überschreibungen erlauben.

## Datenschutz und Datensparsamkeit

Der Tutor arbeitet serverseitig. Der Browser spricht nur mit Moodle, nicht direkt mit dem RAG-/MCP-Backend. Dadurch bleiben technische Zugangsdaten geschützt.

Wichtig für den Betrieb:

- Nutzer/innen müssen die Datenschutzhinweise vor dem ersten Chat bestätigen.
- Moodle speichert nur notwendige lokale Referenzen, zum Beispiel Conversation-IDs und Vorschautexte.
- Vollständige Transkripte liegen beim angebundenen Tutor-/RAG-Dienst, sofern dieser sie speichert.
- „Alle meine Tutor-Daten löschen“ entfernt lokale Gesprächszeiger,
  Fragenprotokolle, Consent, Nutzungszähler, Diagnostik und die LTM-Präferenz.
  Danach ist die First-Use-Bestätigung erneut erforderlich. Wenn das Backend
  Löschwerkzeuge bereitstellt, wird die externe Löschung zusätzlich best effort
  angefordert und ihr Ergebnis separat angezeigt.
- Die reguläre Moodle-Kontolöschung entfernt dieselben lokalen Tutor-Daten
  automatisch. Ist das externe User-Löschwerkzeug konfiguriert, fordert Moodle
  die Löschung von Transkripten und Langzeitgedächtnis an, bevor das
  Nutzer-Token ungültig wird; Backendfehler verhindern die lokale Bereinigung
  nicht.
- Die Privacy-Provider-Implementierung des Plugins unterstützt Moodle-Exports und Löschprozesse.

## Fehlerbehebung und Wartung

### Der Tutor-Dienst ist nicht verfügbar

Prüfen Sie:

- Ist die RAG-MCP-Server-URL aus Sicht des Moodle-Servers erreichbar?
- Ist die Authentifizierung korrekt?
- Heißt das Chat-Tool im Backend genauso wie in Moodle konfiguriert?
- Ist Streaming aktiviert, obwohl der Server kein Streaming unterstützt?
- Sind LiteRAG und MCP installiert und aktiv?

### Der Tutor antwortet, nutzt aber keinen Kurskontext

Prüfen Sie:

- Ist **Kurs-Chat aktivieren** eingeschaltet?
- Übergibt die Blockinstanz den Kurskontext?
- Wurde der Kurs durch RAG-Ingest indexiert?
- Sind im RAG-Backend Dokumente für diesen Kurs vorhanden?
- Hat die angemeldete Person Zugriff auf den Kurs und die relevanten Inhalte?

### Das Dashboard zeigt ein fehlendes Zusatzplugin

Installieren oder aktivieren Sie die fehlenden Komponenten:

- LiteRAG
- RAG-Ingest
- MCP

Ohne diese Dienste kann der Tutor zwar installiert sein, aber nicht vollständig arbeiten.

### Der Chat-Button reagiert nicht

Prüfen Sie:

- Sind Moodle-Caches geleert?
- Wurde nach Änderungen an `amd/src` der AMD-Build erzeugt?
- Enthält die Browser-Konsole JavaScript-Fehler?
- Ist die Blockinstanz sichtbar und für die Nutzerrolle erlaubt?

### Eine Blockinstanz zeigt andere Werte als erwartet

Prüfen Sie:

- Ob die Website einen Standard-Tutor erzwingt.
- Ob die jeweilige Einstellung pro Instanz überschrieben werden darf.
- Ob ein Tutor-Profil auf die Blockinstanz angewendet wurde.
- Ob veraltete Moodle-Caches aktiv sind.

### Betrieb

- Prüfen Sie nach Updates das Dashboard des Tutors.
- Testen Sie nach Backend-Änderungen eine allgemeine und eine kursbezogene Frage.
- Leeren Sie Moodle-Caches nach Deployments.
- Überwachen Sie Fehler im Moodle-Log und im RAG-/MCP-Backend.
- Halten Sie Datenschutztexte aktuell, insbesondere wenn sich Backend, Speicherorte oder Löschprozesse ändern.

### Weiterführende Dokumentation

- `README.de.md` im Plugin für Installations- und Release-Hinweise.
- `docs/privacy.md` für Datenschutzdetails.
- `docs/security.md` für Sicherheitsmodell und technische Schutzmaßnahmen.
- `docs/03-dev-doc.md` für RAG-/MCP-Integration und Serververtrag.

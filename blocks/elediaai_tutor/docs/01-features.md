# Features

Dieses Dokument beschreibt das gewuenschte Produktverhalten fuer
`block_elediaai_tutor`.

---

## Produkt-Uebersicht

Der eLeDia.ai Tutor ist ein Moodle-Block, der Lernenden und Lehrenden eine
eingebettete Chat-Oberflaeche bereitstellt. Die eigentliche Antwortgenerierung
liegt bei einem externen RAG/Tutor-MCP-Server. Moodle bleibt die Vertrauens- und
Kontextquelle.

## Supportvertrag

Release 0.19.10 unterstuetzt Moodle 4.5 bis 5.2 und PHP 8.3 oder neuer. Moodle
4.2 bis 4.4 sind nicht unterstuetzt, weil die Kernintegrationen die Moodle
 Hooks API verwenden und keine Legacy-Callbacks bereitstellen. Die niedrigste
 und die hoechste unterstuetzte Moodle-Version werden in der CI bei Installation
 und PHPUnit als harte Gates geprueft. Der Tutor deklariert keine harten
 Plugin-Abhaengigkeiten. Ist `local_elediaai_core` installiert, prueft und bucht
 der Block Tokenverbrauch ueber dessen zentrale Quota; fehlt das Plugin, wird
 diese Integration kontrolliert uebersprungen.

## Plugin-Shell-Vertrag

Die Tutor-Shell nutzt den gestempelten `lh-core`-Block mit Zone-A-Actionbar,
sichtbaren `:focus-visible`-Ringen, 44px-Hit-Areas fuer `.lh-icon-action` und
Full-Shell-Content-Breite. Tutor-eigene `.eat-*`-Regeln bleiben ausserhalb des
generierten Blocks.

## Kernkonzepte

Normale Tutor-Shell-Seiten nutzen die breite 72rem-Darstellung; die Help-Seite
bleibt fuer lesbaren Fliesstext auf 58rem begrenzt. Eingebettete und
kursbezogene Seiten behalten ihre jeweiligen Layout-Zweige.

- **Tutor-Block:** Moodle UI, Consent, Limits und Chat-Frontend.
- **Moodle MCP Connector:** Fuer geerdete Antworten erforderlich.
  `webservice_elediamcp` erstellt das nutzerbezogene Token, mit dem der
  RAG/Tutor-Server in Moodle zurueckrufen kann. Im reinen LLM-Modus (kein
  Retrieval, kein Rueckruf) wird kein Token gemuenzt und der Connector wird
  nicht benoetigt.
- **RAG/Tutor-MCP-Server:** Externer Dienst, der `tools/call` verarbeitet und
  Antworten erzeugt.
- **Tutor Profile:** Konfigurierbare Persona, Branding- und UI-Vorgaben.
- **Optionale Token-Quota:** `local_elediaai_core` kann stunden- und tagesbezogene
  Tokenlimits zentral durchsetzen. Der Tutor bleibt ohne dieses Plugin
  installierbar und der LLM-only-Chat bleibt nutzbar.

> **Zwei-Schichten-Modell:** Der Block *provisioniert* den geerdeten Rueckruf
> immer (im geerdeten Modus wird ein nutzerbezogenes Token gepraegt; es gibt
> keinen Abschalter im Block). Ob der Rueckruf tatsaechlich *genutzt* wird,
> entscheidet das Backend (z. B. `enable_mcp_tools` in `local_literag`). Beides
> ist getrennt: "kein Abschalter" ist eine Aussage zur Provisionierung im Block,
> kein Verbot, das Backend-seitige Tool-Nutzungsschalter umzulegen.

---

## Features

### feat01 Moodle-native Tutor chat

**Ziel**
Nutzer koennen innerhalb von Moodle mit einem Tutor chatten, ohne dass Secrets
oder Server-Tokens in den Browser gelangen.

**Akzeptanzkriterien**

- feat01.AC01
  Given: Ein Nutzer hat `block/elediaai_tutor:use`
  When: Der Tutor-Block oder die Tutor-Seite geoeffnet wird
  Then: Die Chat-UI wird angezeigt, sofern Kurs-/Globalchat aktiviert ist

- feat01.AC02
  Given: Der Nutzer sendet eine Nachricht
  When: Der Block die Anfrage verarbeitet
  Then: Moodle ruft serverseitig den konfigurierten RAG/Tutor-MCP-Endpunkt auf
  und sendet keine Secrets an den Browser

- feat01.AC03
  Given: Die Standalone-Tutor-Seite wird innerhalb der Plugin-Shell geoeffnet
  When: Die Chat-Oberflaeche gerendert wird
  Then: Sie nutzt die volle Inhaltsbreite der Shell ohne eine zweite
  Maximalbreite; eingebettete und ungeshellte Ansichten bleiben kompakt

- feat01.AC04
  Given: Eine Chat- oder Verlaufsanfrage ist kursbezogen
  When: Moodle den AJAX-Aufruf autorisiert
  Then: Kurszugriff und Capability werden im serverseitig gebundenen Kurs-
  beziehungsweise Tutorblockkontext geprueft; System- und fremde Blockkontexte
  koennen diese Pruefung nicht ersetzen

- feat01.AC05
  Given: Eine gespeicherte Unterhaltung gehoert zu einem Kurs
  When: Der Nutzer Liste, Verlauf oder eine Fortsetzung anfordert
  Then: Der gespeicherte Kurs bleibt autoritativ und die Daten werden nur bei
  weiterhin bestehendem Kurszugriff geliefert

### feat02 Moodle MCP token delegation

**Ziel**
Der Tutor kann im Namen des eingeloggten Moodle-Nutzers auf erlaubte Moodle-MCP
Tools zugreifen.

**Akzeptanzkriterien**

- feat02.AC01
  Given: Der Kurs wird im geerdeten Modus beantwortet, `webservice_elediamcp` ist
  installiert und ein MCP-externer Dienst ist ausgewaehlt
  When: Eine Chat-Nachricht verarbeitet wird
  Then: Der Block provisioniert ein nutzerbezogenes MCP-Token fuer diesen Dienst

- feat02.AC02
  Given: Der Kurs wird im geerdeten Modus beantwortet, aber kein MCP-externer
  Dienst (bzw. der Connector) ist verfuegbar
  When: Die Chat-UI geladen wird
  Then: Manager sehen eine klare Konfigurationsmeldung; der Tutor degradiert
  nicht stillschweigend

- feat02.AC03
  Given: Der Kurs wird im reinen LLM-Modus beantwortet (kein Retrieval,
  kein Rueckruf)
  When: Eine Chat-Nachricht verarbeitet wird
  Then: Der Block ruft den Tutor ohne Moodle-MCP-Token auf; der Connector wird
  dafuer nicht benoetigt

### feat03 Tutor configuration and profiles

**Ziel**
Admins und berechtigte Lehrende koennen Tutor-Persona, Branding und Verhalten
konfigurieren, ohne Code zu aendern.

**Akzeptanzkriterien**

- feat03.AC01
  Given: Ein Admin oeffnet die Block-Einstellungen
  When: Persona-/Branding-Werte angepasst werden
  Then: Neue Block-Instanzen verwenden die Site-Defaults

- feat03.AC02
  Given: Eine Einstellung ist fuer Teacher-Overrides freigegeben
  When: Ein Teacher eine Block-Instanz konfiguriert
  Then: Die Instanz kann diesen Wert lokal ueberschreiben

### feat04 Privacy, consent and deletion

**Ziel**
Nutzer verstehen vor der ersten Nutzung, welche Daten verarbeitet werden, und
koennen eigene Tutor-Daten loeschen.

**Akzeptanzkriterien**

- feat04.AC01
  Given: Ein Nutzer hat die Datenschutz-Hinweise noch nicht bestaetigt
  When: Der Tutor geoeffnet wird
  Then: Der Consent-Gate wird angezeigt

- feat04.AC02
  Given: Remote-Delete-Tools sind konfiguriert
  When: Der Nutzer eigene Tutor-Daten loescht
  Then: Moodle loescht Gespraechszeiger, Fragenprotokolle, Consent, Nutzung,
  Diagnostik und LTM-Praeferenz und fordert Remote-Loeschung beim
  RAG/Tutor-MCP-Server an

- feat04.AC03
  Given: Der externe Tutor-Dienst kann die Loeschung nicht ausfuehren
  When: Der Nutzer alle eigenen Tutor-Daten loescht
  Then: Die vollstaendige lokale Loeschung gelingt trotzdem, der Fehler wird
  gezaehlt und die Oberflaeche meldet den externen Fehlschlag

- feat04.AC04
  Given: Ein Moodle-Nutzerkonto wird regulaer geloescht
  When: Moodle den Pre-Delete-Hook und das `user_deleted`-Event ausfuehrt
  Then: Alle lokalen Tutor-Daten und die LTM-Praeferenz werden nutzerbezogen
  entfernt; eine konfigurierte externe User-Loeschung wird mit dem noch
  aktiven Nutzer-Token best effort angefordert

### feat06 Teacher-Copilot and question analytics

**Ziel**
Lehrende können aggregierte, namenfreie Analysen der Schülerfragen eines Kurses
einsehen und eine KI-gestützte Empfehlung zur Kursverbesserung erhalten.

**Akzeptanzkriterien**

- feat06.AC01
  Given: Ein Lehrer hat `block/elediaai_tutor:viewreports` im Kurs
  When: Der Lehrer die Frage-Analytics-Seite besucht
  Then: Die Seite zeigt aggregierte Metadaten: Fragen pro Tag, Hotspots
  (häufige Verständnisprobleme) und eine Auswahl der letzten Fragen

- feat06.AC02
  Given: Die Seite ist ganz oder teilweise leer (keine Fragen geloggt)
  When: Der Lehrer auf „Copilot-Analyse" klickt
  Then: Eine Meldung erklärt, dass mindestens einige Fragen nötig sind

- feat06.AC03
  Given: Es existieren geloggete Kursfragen und die Frage-Analytics sind aktiviert
  When: Der Lehrer „Copilot-Analyse starten" anklickt
  Then: Der Block sendet Hotspots und eine Stichprobe der letzten Fragen an den
  RAG-Server, erhält eine Markdown-Analyse ("Schüler verstehen X nicht, Empfehlung:
  Y") und zeigt sie auf der Seite

- feat06.AC04
  Given: Die Frage-Analytics sind aktiviert und mindestens ein Recluster-Tool
  ist konfiguriert
  When: Eine tägliche Aufgabe läuft
  Then: Der Block sendet die Fragen der letzten 30 Tage pro Kurs an den
  Recluster-Dienst, empfängt aktualisierte Topic-Labels und speichert sie

### feat05 Canonical plugin component name

**Ziel**
Der Tutor-Block verwendet in Moodle und in den Suite-Integrationen den
kanonischen Namen `elediaai_tutor`.

**Akzeptanzkriterien**

- feat05.AC01
  Given: Der Block wird aus dem Repository installiert
  When: Moodle den Plugin-Pfad und die Metadaten einliest
  Then: Pfad und Komponente lauten `blocks/elediaai_tutor` und
  `block_elediaai_tutor`.

- feat05.AC02
  Given: Ein Suite-Plugin verlinkt auf den Tutor oder prüft seine Komponente
  When: Der Link oder die Prüfung ausgeführt wird
  Then: Sie verwendet ausschließlich den neuen Pfad bzw. Komponentennamen.

- feat05.AC03
  Given: Eine Installation enthält Daten der früheren Komponente
  `block_eledia_aitutor`
  When: Moodle Release 0.19.7 installiert oder aktualisiert
  Then: Tabelleninhalte und IDs, Einstellungen, Blockinstanzen,
  Capability-Zuweisungen, Dateien und LTM-Präferenzen bleiben unter dem
  kanonischen Namen erhalten.

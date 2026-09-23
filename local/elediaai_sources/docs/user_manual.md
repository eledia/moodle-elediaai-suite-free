# Benutzerhandbuch — KI Quellen (`local_elediaai_sources`)

## Zweck

Das Plugin indexiert Moodle-Kursinhalte für eine Wissensbasis, aus der der
KI-Tutor seine Antworten belegt. Aus Administrationssicht geht es um drei Dinge:

1. Das Aufnahmeziel auswählen und konfigurieren.
2. Festlegen, welche Kurse aufgenommen werden dürfen.
3. Kurse bei Bedarf neu indexieren.

Das Plugin arbeitet im Hintergrund. Lehrende müssen für normale Kursänderungen
nichts exportieren.

## Zielgruppen

- **Administration:** installiert und konfiguriert das Plugin.
- **Manager und berechtigte Rollen:** markieren Kurse für die Aufnahme, sofern
  die Markierung nicht gesperrt ist.
- **Lehrende:** ändern Kursinhalte wie gewohnt; die Aufnahme reagiert selbst.

## Installation

1. Plugin-Code nach `local/elediaai_sources` kopieren.
2. Moodle-Upgrade ausführen, über die Website-Administration oder per CLI.
3. Einstellungen unter **Website-Administration → Plugins → Lokale Plugins →
   KI Quellen** prüfen.

## Das Aufnahmeziel

Es ist **immer genau ein Ziel aktiv**. Umgeschaltet werden kann jederzeit,
parallel betrieben wird nicht.

### Auswahl

Die Einstellung **Aufnahmeziel** bietet:

- **eLeDia.ai Ingestion-API (externer Dienst)** — der externe Aufnahmedienst.
- **LiteRAG (auf dieser Website)** — die lokale Route im Plugin `local_literag`.

### Ziel: eLeDia.ai Ingestion-API

- **Basis-URL des Dienstes:** die Adresse des Dienstes ohne angehängten Pfad,
  zum Beispiel `http://rag-service:8001`. Die Aktionen darunter
  (`/documents/upsert`, `/documents/delete`, `/health`) ergänzt das Plugin
  selbst — sie stehen in der API-Spezifikation und werden nicht konfiguriert.
- **API-Schlüssel:** wird serverseitig als Header `X-API-Key` gesendet. Ohne
  Schlüssel gilt das Ziel als nicht konfiguriert und es werden keine Dokumente
  gesendet.

Der Mandant wird **nicht** eingestellt. Er leitet sich aus der Adresse der
Website ab, und der Dienst prüft, ob er zum Schlüssel passt. Ändert sich die
Adresse der Website, ist das ein Mandantenwechsel und erfordert eine
Neuaufnahme.

### Ziel: LiteRAG

Hier gibt es nichts einzustellen. Die Route ergibt sich aus der Adresse dieser
Website, und als Schlüssel dient der in LiteRAG selbst hinterlegte
Aufnahmeschlüssel. Ist LiteRAG nicht installiert oder dort kein Schlüssel
gesetzt, meldet das Ziel genau das.

### Beim Umschalten

Nach einem Wechsel des Ziels gelten alle freigegebenen Kurse als abweichend und
werden in das neue Ziel aufgenommen. Das bisherige Ziel wird **nicht**
automatisch geräumt — es ist beim Umschalten oft gar nicht mehr erreichbar, und
ein fehlschlagendes Räumen dürfte das Umschalten nicht blockieren.

Zum Aufräumen bietet die Reindex-Seite danach **Bisheriges Ziel räumen** an.
Die Aktion prüft zuerst, ob das alte Ziel überhaupt antwortet, fragt dann nach
und plant das Entfernen je Kurs als Hintergrundaufgabe ein. Sie betrifft genau
die Kurse, die laut Zustand noch im alten Ziel liegen; am neuen Ziel ändert sie
nichts.

## Weitere Einstellungen

- **Privates Ziel erlauben:** erlaubt private Hosts, interne Dienstnamen oder
  ungewöhnliche Ports. Nur aktivieren, wenn der Dienst in einem
  vertrauenswürdigen internen Netz läuft, etwa Docker oder Kubernetes.
- **Maximale Dokumentgröße (MB):** Text und HTML werden bei Überschreitung
  gekürzt und trotzdem gesendet; Binärdokumente wie PDF lassen sich nicht sinnvoll
  kürzen und werden übersprungen.
- **Anfrage-Timeout (Sekunden):** Netzwerk- und Serverfehler werden kurz erneut
  versucht. Längere Ausfälle fängt Moodles Task-Wiederholung ab, damit
  Cron-Worker nicht blockieren.

## Statusanzeige

Die Einstellungsseite zeigt oben den Indexierungsstand:

- **Freigegebene Kurse warten auf die Indexierung** — es gibt freigegebene
  Kurse, die noch nicht erfolgreich aufgenommen wurden. Der Button
  **Freigegebene Kurse jetzt indexieren** plant sie als Hintergrundaufgaben ein.
- **Freigegebene Kurse sind indexiert** — es wartet nichts.

## Kurse freigeben

Die Aufnahme ist Opt-in: ohne Markierung wird nichts gesendet. Drei Wege führen
zur Freigabe, in dieser Reihenfolge geprüft:

1. **Sperre der Kursmarkierung** — ist sie aktiv, zählen nur Pilotliste und
   Kategorien; das Kursfeld wird aus dem Kursformular entfernt.
2. **Kursfeld „KI Quellen"** — `Include` nimmt den Kurs auf, `Exclude`
   schließt ihn aus, `Default` überlässt die Entscheidung den zentralen Regeln.
3. **Pilotkursliste und Kategorie-Freigabeliste** — Mehrfachauswahl mit Suche.
   Bei Kategorien zählen auch deren Unterkategorien.

## Aktivitäten auswählen

Innerhalb eines freigegebenen Kurses entscheiden Lehrkräfte je Aktivität, was
in die Wissensbasis gelangt. Zwei Wege, dieselbe Entscheidung:

- **Kursseite „KI Quellen"** (über die Kursnavigation): alle Aktivitäten nach
  Kursabschnitten gruppiert, je Zeile ein Schalter. Umlegen wirkt sofort;
  Aktivitätstypen ohne Extractor sind gedimmt und als „Nicht unterstützt"
  gekennzeichnet. Dazu je Zeile der Indexstatus — **Indexiert** (Zeitpunkt im
  Tooltip), **Fehler** (Meldung im Tooltip) oder **Unbekannt**, wenn keine
  Aufnahme verbucht ist. Je Abschnitt gibt es **Alle aufnehmen / Alle
  ausschließen**, je entschiedener Zeile **Zurücksetzen** auf den
  Site-Standard, und oben ein Filterfeld für lange Kurse.
- **Bearbeitungsformular der Aktivität**: Abschnitt „eLeDia.ai | KI Quellen"
  mit dem Haken „In die Wissensbasis des KI-Tutors aufnehmen".

Beides erfordert die Capability `local/elediaai_sources:selectactivities`
(standardmäßig editingteacher und manager) und ist während der
Markierungssperre deaktiviert.

Die Regeln dahinter:

- **Ausdrückliche Entscheidung gewinnt.** Was eine Lehrkraft gesetzt hat,
  gilt — unabhängig von der Site-Einstellung.
- **Die Site-Einstellung „Aktivitäten ohne Entscheidung"** füllt nur die
  Lücke: Opt-out (Standard) nimmt Unentschiedenes auf, Opt-in überspringt es.
- **Entfernt wird nur bei ausdrücklichem Ausschluss.** Das Abwählen einer
  Aktivität löscht ihre Dokumente mit dem nächsten Hintergrundlauf aus dem
  Index. Ein Wechsel der Site-Einstellung löscht dagegen nie etwas.
- **Von Opt-in zurück auf Opt-out** plant unentschiedene Aktivitäten
  freigegebener Kurse automatisch zur Aufnahme ein. Umgekehrt geschieht
  nichts: Auf Opt-in umzuschalten stoppt nur Neues, es entfernt nichts.
- **Duplizieren/Wiederherstellen** überträgt die Entscheidung mit. Der
  Indexzustand wandert nicht mit — die Kopie gilt zunächst als nicht
  indexiert, bis sie aufgenommen wurde.

## Probelauf einer Aktivität

Über **Probelauf** in jeder Zeile der Aktivitätsauswahl lässt sich für eine
einzelne Aktivität nachsehen, was an die Wissensbasis ginge — ohne dass dabei
etwas gesendet wird. Die Seite zeigt:

- das **Urteil**: würde gesendet, würde aus dem Index entfernt, oder würde
  nicht gesendet — mit Begründung (Kurs nicht freigegeben, Aktivität nicht
  ausgewählt, verborgen, kein Extractor, kein Inhalt);
- **jedes Dokument** mit Quell-ID, Inhaltstyp, Größe und Fingerabdruck, dazu
  den Inhalt im Wortlaut, **wie er gesendet würde** — also als Quelltext, nicht
  gerendert. Binärinhalte wie PDF werden beschrieben, nicht abgedruckt;
- die **mitgesendeten Metadaten** (Mandant, Kurs, Modul, Modul-URL);
- das **Verhältnis zum Index**: nicht im Index, identisch, ältere Fassung,
  Eintrag des vorherigen Ziels, oder letzter Versuch fehlgeschlagen.

Bei Paketen mit Ton oder Video steht oben zusätzlich ein Befund zur
**Barrierefreiheit**: wie viele Medien Untertitel haben, und welche nicht.
Er kennt vier Ausgänge — alle untertitelt, nachweislich unvollständig, nicht
zuzuordnen, oder gar keine Medien. „Nicht zuzuordnen" ist keine Ausflucht: Ein
Paket kann Untertitel mitbringen, die sich keiner Mediendatei zuordnen lassen,
und dann wäre sowohl ein Freispruch als auch eine Unterstellung falsch.

**Ton und Video werden unterschiedlich beurteilt**, weil die Norm sie
unterscheidet: Video mit Ton braucht Untertitel (WCAG 1.2.2), und ein Transkript
ersetzt sie dort nicht. Für Ton ohne Bild genügt dagegen eine Textalternative
(WCAG 1.2.1) — bringt das Paket ein Transkript mit, gilt fehlender Untertitel
dort nicht als Mangel, sondern als „nicht abschließend beurteilbar".

Der Probelauf ist auch für Aktivitätstypen verfügbar, die kein Extractor lesen
kann — dort beantwortet er genau die Frage, warum nichts ankommt.

Zwei Dinge kann er nicht: Er zeigt **nicht den Wortlaut einer älteren
indexierten Fassung**, denn davon ist nur der Fingerabdruck gespeichert und
keine zweite Kopie der Kursinhalte. Und bei großen Paketen dauert er einen
Moment, weil der Inhalt dafür wirklich extrahiert wird.

Lehrkräfte brauchen dafür dasselbe Recht wie für die Auswahl; während der
Markierungssperre ist der Probelauf nur für die Administration erreichbar.

Die Reindex-Seite öffnet den Probelauf zusätzlich direkt über eine
**Aktivitäts-ID** (cmid) — die Zahl, auf die eine Quell-ID endet und die auch
der dortige Fehlerbericht nennt. Das ist der übliche Weg für die
Administration, die im betroffenen Kurs meist nicht eingeschrieben ist.

## Sichtbarkeit und unveraenderte Inhalte

- **Verborgene Aktivitaeten sind nicht Teil des Index.** Wird eine Aktivitaet
  (oder ihr Kursabschnitt) fuer Lernende verborgen, entfernt das Plugin ihre
  Dokumente — beim naechsten Speichern, Reindex oder spaetestens mit dem
  woechentlichen Aufraeumlauf. Sichtbar schalten bringt sie zurueck.
- **Unveraendertes wird nicht neu gesendet.** Der Reindex ueberspringt
  Aktivitaeten, deren Inhalt seit der letzten Aufnahme gleich ist. Auf der
  Reindex-Seite gibt es „erzwingen", das alles erneut sendet — etwa wenn das
  Ziel Daten verloren hat.

## Typischer Ablauf

1. Ziel auswählen und dessen Einstellungen ausfüllen.
2. Einen oder mehrere Pilotkurse eintragen.
3. Moodle-Cron laufen lassen, damit die Hintergrundaufgaben abgearbeitet werden.
4. Bei Bedarf freigegebene Kurse über die Statuskarte gesammelt indexieren oder
   einen einzelnen Kurs manuell neu indexieren.
5. Ergebnis im Ziel prüfen.
6. Nach erfolgreichem Pilotbetrieb Kategorien freigeben oder weitere Kurse
   aufnehmen.

## Automatische Aufnahme

Ändert sich in einem freigegebenen Kurs eine unterstützte Aktivität, legt Moodle
eine Hintergrundaufgabe an; die Aktion der Nutzerin oder des Nutzers wartet nicht
auf das Ziel. Erkannt werden unter anderem: angelegte, geänderte und gelöschte
Kursmodule, Buchkapitel, Glossareinträge, Lektionsseiten, Wiki-Seiten,
Datenbankeinträge sowie Änderungen an Testaufbau und verwendeten Fragen.

Zusätzlich gleicht eine geplante Aufgabe regelmäßig Markierung und tatsächlichen
Stand ab. Sie ist das Sicherheitsnetz für Änderungen, die kein Ereignis auslösen
— etwa eine geänderte Kategorie-Freigabeliste.

## Manuelle Neuindexierung

Unterhalb der Aktionen listet die Reindex-Seite die **letzten Aufnahmefehler**
je Aktivität, sofern es welche gibt — mit Kurs, Meldung und Zeitpunkt. Der
zuvor indexierte Inhalt liegt dabei weiterhin im Index; nur der letzte Versuch
schlug fehl.

Die Reindex-Seite kennt zwei Aktionen:

- **Freigegebene Kurse jetzt indexieren** — plant alle freigegebenen, noch nicht
  aufgenommenen Kurse ein.
- **Manuelle Kurs-Neuindexierung** — nimmt gezielt einen Kurs anhand seiner
  numerischen Kurs-ID erneut auf.

Dabei gilt: Unterstützte Aktivitäten werden neu ausgelesen, nicht unterstützte
oder leere übersprungen. Fehler werden je Modul gemeldet, stoppen aber nicht den
ganzen Kurslauf. Treten Fehler auf, bleibt der Kurs als nicht aufgenommen
vermerkt, damit ein späterer Abgleich es erneut versucht.

## Was nicht aufgenommen wird

- Kurse ohne Freigabe
- die Startseite der Website
- gelöschte oder nicht sichtbare Kursmodule
- nicht unterstützte Modultypen
- leere Inhalte
- Binärdokumente oberhalb der eingestellten Größengrenze
- Nutzerantworten, etwa in Feedbacks, soweit die Extraktoren sie ausschließen

## Wenn nichts ankommt

- **Meldung „Das ausgewählte Aufnahmeziel ist nicht konfiguriert":** beim
  externen Dienst fehlt Basis-URL oder Schlüssel; bei LiteRAG fehlt das Plugin
  oder dessen Aufnahmeschlüssel.
- **Kurs wird übersprungen:** die Freigabe fehlt — Pilotliste, Kategorie und
  Kursfeld prüfen, und ob die Sperre der Kursmarkierung aktiv ist.
- **Nichts passiert nach einer Änderung:** die Aufnahme läuft über Cron. Ohne
  laufenden Cron bleiben die Aufgaben in der Warteschlange.
- **Interner Dienstname oder ungewöhnlicher Port wird nicht erreicht:**
  „Privates Ziel erlauben" ist nötig.

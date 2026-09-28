# Changelog — local_elediaai_chatengine

Wesentliche Änderungen, neueste zuerst. Version = `release` aus version.php.
Format angelehnt an Keep a Changelog. Frühere Stände siehe git log.

## [1.1.1] – 2026-09-28

### Behoben

- **Fehlermeldungen des Backends gelten nicht als KI-Antwort.** Sie wurden als
  Assistentenbeitrag gespeichert, gekennzeichnet und im Transparenzregister
  nachgewiesen; jetzt nur die Frage, mit `errorcode` im Turn-Protokoll (M-05).
- **Ein Nachweis je KI-Antwort**, beim Kennzeichnen auf „marked" gesetzt;
  Simulator-Antworten bekommen keinen (M-11, G-02).
- **„Antwort kopieren" nimmt die Kennzeichnung mit** – Hinweissatz und
  Prüf-Link im Text, `data-ai-*` im HTML (M-10).
- **Kontingent, Limit und Wartezeit** erscheinen als solche statt als
  „Backend nicht erreichbar" (H-03).
- Das Rate-Limit zählt die letzten 60 Sekunden statt einer Kalenderminute
  (M-04).
- Eine gestreamte Antwort erscheint nicht mehr doppelt (G-02).
- Das Token für den Tutor hinterlässt keine Notiz in der schon geschlossenen
  Sitzung (G-03).
- LiteRAG bekommt die Frage der Person gesondert (`user_question`) und sucht
  nicht mehr mit dem Schutzrahmen (H-01).

### Geändert

- **Barrierefreiheit:** Ein offenes Panel hält den Fokus über die
  Core-Fokusfalle und ist im Block-Drawer per Tastatur bedienbar; eine
  gestreamte Antwort wird einmal angesagt; Grounding-Badge 4,9:1; der
  Panelkopf ist kein banner-Landmark mehr (H-13).
- `token_provider::revoke_for_user()` für „Alle meine Daten löschen" (M-09).
- Die Einstellung „Antworten fortlaufend anzeigen" sagt, dass sie mit LiteRAG
  nicht wirkt (N-03).
- Datenschutz: Provider um die Nutzungszähler ergänzt, die beim Aufräumen
  mit gelöscht werden (H-06).

## [1.1.0] – 2026-09-28

### Geändert

- **Kursmenge kappen statt weglassen, und den Kurs vom Suchraum trennen.**
  `course_id` nennt wieder genau einen Kurs, die Suchmenge reist in
  `search_course_ids` (brechende Vertragsänderung, siehe
  `docs/rag_server_spec.md`). Wer in mehr Kursen eingeschrieben ist als die
  Grenze erlaubt, bekommt eine gekürzte Liste nach letztem Zugriff statt
  keiner; `STATE_OVERFLOW` fällt weg.

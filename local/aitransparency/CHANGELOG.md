# Changelog — local_aitransparency

Wesentliche Änderungen, neueste zuerst. Version = `release` aus version.php.
Format angelehnt an Keep a Changelog.
Frühere Stände siehe git log.

## [1.1.1] – 2026-10-23

### Behoben

- Die Pruefseite „Herkunft pruefen“ war eine Sackgasse. Ueber der Karte steht
  jetzt ein Rueckweg im Stil der Suite (der leise Textlink wie „Zurueck zum
  Kurs“ in den Kurs-Einblicken). Das Ziel ist zuerst die Seite, von der man
  kam (meist der Chat), sonst der Ort, an dem der Inhalt entstand (Kurs oder
  Aktivitaet, mit Namen), sonst das Dashboard. Kurs- und Aktivitaetsnamen
  sieht nur, wer den Kurs sehen darf.

## [1.1.0] – 2026-10-14
### Hinzugefügt
- **Darstellung des KI-Hinweises einstellbar** (#42). Zwei Einstellungen:
  `noticestyle_chat` (voll · kompakt · einmal je Gespräch) und `noticestyle`
  für alles außerhalb eines Chats (voll · kompakt). **Standard bleibt „voll“**,
  es ändert sich also nichts, bis jemand umstellt.
- Kompakt: ein kleines „KI“-Zeichen mit Link zum Herkunftsnachweis; der Satz
  steht weiter unsichtbar im Baum, für Screenreader und beim Kopieren.
- „Einmal je Gespräch“: Der Satz steht einmal am Chat (`marker::chat_notice()`),
  jede Antwort trägt das kompakte Zeichen.
- `marker::notice()` hat einen optionalen Stil-Parameter, `marker::style()`
  liefert die Wahl je Oberfläche. Die maschinenlesbare Kennzeichnung
  (`wrap_text()`, `data-ai-record`) ist in allen Stilen unverändert.
- Ob „einmal je Gespräch“ Art. 50 Abs. 1 genügt, ist rechtlich noch zu
  bestätigen; die Beschreibung der Einstellung sagt das.

## [1.0.3] – 2026-09-28
### Geändert
- LICENSE beigelegt; `$plugin->supported = [405, 502]`.
- Die Chat-Engine legt je KI-Antwort genau einen Nachweis an und setzt ihn
  beim Kennzeichnen auf „marked"; der doppelte Eintrag aus LiteRAG und der
  ewige Zustand „pending" sind damit behoben (M-11, Änderung in der
  Chat-Engine).

## [1.0.2] – 2026-09-25
### Neu
- **Handbuchkapitel „KI-Transparenz: der Herkunftsbericht"** (Abschnitt
  Vertrauen, für die Administration). Jede Kachel ohne eigene Seite führt ins
  Handbuch, und der Wegweiser prüft, dass keine stumm ist — mit der Kachel aus
  #30 fehlte das Kapitel.

## [1.0.1] – 2026-09-25
### Neu
- **Kachel im Launcher der KI-Suite** (#30). Das Plugin meldete keinen
  `feature_provider` an und fehlte darum unter `/local/elediaai_core/index.php`
  — auf einem Kundensystem sah das aus wie „nicht installiert". Die Kachel
  führt zum Herkunftsbericht und hängt an dessen eigener Berechtigung
  `local/aitransparency:viewreport` (Manager, Administration), damit Launcher
  und Bericht dieselbe Antwort geben.

## [0.3.0] – 2026-09-19
### Neu
- **Der Nachweis kennt den Turn, aus dem er stammt** (`turnid`). Schicht C
  belegt, dass ein Inhalt aus der KI kam; `local_elediaai_core_turn` weiß, was
  gefragt und geantwortet wurde. Zwischen beiden fehlte die Verbindung. Die
  Spalte ist bewusst kein Fremdschlüssel: der Nachweis überlebt den Turn
  planmäßig, und läuft dessen Frist ab, bleibt der Nachweis stehen und die
  Spalte zeigt ins Leere.
- **Ein Wächter für die Kennzeichnung** (`marking_coverage_test`). Ob eine
  Fläche den Hinweis nach Art. 50 Abs. 1 zeigt, sieht man dem Bildschirm nicht
  an, nur dem Quelltext. Der Test liest ihn: er findet jede installierte
  Komponente, die den Engpass der Suite benutzt, und meldet die ohne
  Marker-Aufruf. Zwölf stehen als Schuld in einer Liste im Test — sie darf
  kürzer werden, aber nie länger, ohne dass jemand die Datei anfasst. Wo der
  Hinweis auf diesen Flächen stehen soll, ist eine Produktentscheidung: es
  hängt davon ab, wer die Ausgabe zu sehen bekommt, und das ist je Fläche
  verschieden.
- **Kennzeichnung (`marker`).** `wrap_text()` umhüllt KI-Ausgabe mit dem
  IPTC-Vokabular `trainedAlgorithmicMedia` und der auflösbaren UUID des
  Nachweises — Art. 50 Abs. 2 in der Auslegung, die `offene-rechtsfragen.md`
  unter R-01 festhält. `notice()` liefert den sichtbaren Hinweis für Abs. 1.
  Beide tun **nichts**, wenn kein Nachweis existiert: eine Markierung, die ins
  Leere zeigt, ist schlimmer als keine, weil sie wie ein Beleg aussieht.
- **Auflösung (`verify.php`).** Eine UUID lässt sich nachsehen: Komponente,
  Art der Ausgabe, Anbieter, Modell, Zeit, Zustand der Kennzeichnung und die
  Prüfsumme. **Ohne Person** — der Nachweis beantwortet, *ob* eine KI beteiligt
  war, nicht wer sie bedient hat. Login nötig, keine Capability: Art. 50 Abs. 2
  spricht dafür, dass jeder mit dem Inhalt prüfen kann, eine offene Seite würde
  aber jedem bestätigen, dass ein Datensatz existiert.
- **Bericht (`report.php`).** Die Capability `local/aitransparency:viewreport`
  war seit dem Grundgerüst definiert und beschrieb „the site-level report of
  provenance records and unsigned artefacts" — es gab ihn nicht. Er zählt jetzt
  vor allem eines: wie viele Ausgaben ohne ihre Kennzeichnung hinausgingen.
- Lesewege auf dem Nachweis (`recent()`, `count_records()`,
  `count_unmarked()`). Das Plugin hatte vier Schreiber und **keinen einzigen
  Leser** — nichts, was es aufzeichnete, war je sichtbar.
- Eigenes Stylesheet, das die Token aus `local_elediaai_core` liest (adr05).

### Geändert
- Die Chat-Engine kennzeichnet ihre Antworten. `chat_service::audit()` gibt die
  UUID des Nachweises zurück, und die gerenderte Antwort trägt Markierung und
  Hinweis. Bisher legte die Engine den Nachweis an und verwarf die Kennung.

### Offen
- `turnid`: der Nachweis soll auf den Turn in `local_elediaai_core_turn`
  verweisen. `registerid` setzt bis heute niemand; die Verknüpfung braucht, dass
  der Schreiber die Turn-Id zurückgibt, und steht als Nacharbeit im Plan.
- C2PA und Signierdienst (AP5), `filter_aitransparency` (AP3b) — unverändert
  zurückgestellt.

## [0.2.1] – 2026-08-06
### Neu
- Plugin-Grundgerüst: XMLDB-Schema, Privacy-API, Admin-Settings, Anonymisierungs-Task, CI-Registrierung (AP1)
- Scope-Matrix und Bestandsaufnahme: zwei KI-Chokepoints identifiziert (`core_ai`/`aiprovider_eledia` und `local_literag`) (AP0)

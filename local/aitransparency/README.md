# KI-Transparenz (local_aitransparency)

Moodle-Plugin der eLeDia.ai-Suite für die Kennzeichnung KI-erzeugter Inhalte
nach Art. 50 der europäischen KI-Verordnung.

- Legt je KI-Ausgabe einen Herkunftsnachweis an (ohne den Inhalt zu speichern).
- Kennzeichnet KI-Text sichtbar für Menschen und maschinenlesbar für Programme.
- Zeigt auf einer Prüfseite, ob und welche KI an einem Inhalt beteiligt war.
- Listet im Herkunftsbericht alle Nachweise und die Ausgaben ohne Kennzeichnung.
- Entfernt die Person nach einer einstellbaren Frist aus dem Nachweis.

## Voraussetzungen

- Moodle 4.5 bis 5.2 (`version.php`)
- `local_elediaai_core` (eLeDia.ai | KI-Suite)

## Installation

Das Plugin gehört nach `local/aitransparency` und wird über die
Moodle-Administration installiert. Einstellungen: Website-Administration >
Plugins > Lokale Plugins > KI-Transparenz.

## Hinweise

- Bilder und Dateien werden noch nicht gekennzeichnet.
- Das Plugin ersetzt keine rechtliche Bewertung.

## Dokumentation

- Bedienung: [docs/02-user-doc.md](docs/02-user-doc.md)
- Datenschutz: [docs/privacy.md](docs/privacy.md)
- Sicherheit: [docs/security.md](docs/security.md)
- Release Notes: [docs/releasenotes.md](docs/releasenotes.md)
- Änderungen: [CHANGES.md](CHANGES.md)

Projektstand und Entwicklungsdokumentation: [DevFlow](docs/00-master.md).

## Lizenz

GNU GPL v3 or later, © 2026 eLeDia GmbH.

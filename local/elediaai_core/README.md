# eLeDia.ai | KI-Suite (local_elediaai_core)

Dach-Plugin der eLeDia.ai-Suite für Moodle. Es bündelt die KI-Funktionen der
Suite zu einer sichtbaren Oberfläche und stellt die gemeinsamen Schnittstellen
bereit, über die sich die übrigen Suite-Plugins anmelden.

- Knopf in der Navigation und Übersicht aller KI-Funktionen
- Technisches und didaktisches Audit der KI-Nutzung, Handlungsprotokoll der KI
- Kurs-Einblicke: Themen und Fragen der Lernenden, ohne Personen
- Token-Kontingente je Rolle und Zeitfenster
- Funktionen einzeln ein- und ausschalten
- Gemeinsame Gestaltung (Design-Token und Bausteine) für alle Suite-Plugins
- Zustandsseite und optionale Entwicklerseite

KI-Aufrufe laufen über Moodles `core_ai`. Das Plugin verwaltet keine eigenen
KI-Anbieter oder Schlüssel.

## Voraussetzungen

- Moodle 4.5 bis 5.2 (`version.php`); das Audit der `core_ai`-Aufrufe ab
  Moodle 5.0
- mindestens ein KI-Anbieter in den KI-Einstellungen von Moodle
- optional `pdftotext` (Poppler) für bessere PDF-Auszüge

## Installation

Das Plugin gehört nach `local/elediaai_core` und wird über die
Moodle-Administration installiert. Einstellungen: Website-Administration >
Plugins > Lokale Plugins > KI-Audit (Audit, Aufbewahrung, Kontingente,
Funktionsschalter, Entwicklerseite).

## Dokumentation

- Bedienung: [docs/02-user-doc.md](docs/02-user-doc.md)
- Datenschutz: [docs/privacy.md](docs/privacy.md)
- Sicherheit: [docs/security.md](docs/security.md)
- Release Notes: [docs/releasenotes.md](docs/releasenotes.md)
- Änderungen: [CHANGES.md](CHANGES.md)

Projektstand und Entwicklungsdokumentation: [DevFlow](docs/00-master.md).

## Lizenz

GNU GPL v3 or later, © 2026 eLeDia GmbH. Mitgelieferte Bibliotheken und
Symbole: siehe `thirdpartylibs.xml`.

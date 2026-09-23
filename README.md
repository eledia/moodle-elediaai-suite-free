# eLeDia.ai Suite free

Freies Teilpaket der eLeDia.ai Suite — KI-Erweiterungen für Moodle: ein Tutor-Chat im Kurs,
eine gemeinsame Chat-Engine, die Aufbereitung von Kursinhalten als Wissensbasis, eine lokale
RAG-Variante ohne externe Dienste, KI-Transparenz und der MCP-Connector.

Alle Plugins stehen unter GPLv3+. Dieses Repository ist ein Spiegel; entwickelt wird bei
eLeDia in GitLab. Pull Requests können wir hier deshalb nicht direkt übernehmen —
Fehlermeldungen und Vorschläge über Issues sind trotzdem willkommen.

## Enthaltene Plugins

| Komponente | Moodle-Verzeichnis | Zweck |
|---|---|---|
| `block_elediaai_tutor` | `blocks/elediaai_tutor` | Tutor-Block: Chat im Kurs und im Dashboard |
| `local_elediaai_chatengine` | `local/elediaai_chatengine` | gemeinsame Chat-Oberfläche, Threads, Streaming |
| `local_elediaai_core` | `local/elediaai_core` | Suite-Kern: Feature-Registry, Quota, Audit |
| `local_elediaai_sources` | `local/elediaai_sources` | Auswahl und Aufbereitung von Kursinhalten (17 Extractor-Subplugins) |
| `local_literag` | `local/literag` | lokale RAG-Variante auf Basis der Moodle-Volltextsuche |
| `local_aitransparency` | `local/aitransparency` | Kennzeichnung KI-erzeugter Inhalte |
| `webservice_elediamcp` | `webservice/elediamcp` | MCP-Connector: Moodle-Werkzeuge für KI-Assistenten |

## Installation

Die Plugins hängen voneinander ab. Empfohlene Reihenfolge:

1. `local_elediaai_core`
2. `local_elediaai_chatengine`
3. `webservice_elediamcp`
4. `block_elediaai_tutor`
5. optional `local_elediaai_sources` und `local_literag` für Antworten aus Kursinhalten
6. optional `local_aitransparency`

**Aus dem Release:** Unter [Releases](../../releases) liegt für jedes Plugin ein eigenes ZIP.
Diese ZIPs lassen sich in Moodle unter *Website-Administration → Plugins → Plugin installieren*
einzeln hochladen.

**Aus dem Repository:** Die Ordner dieses Repos entsprechen den Moodle-Verzeichnissen und
können direkt in eine Moodle-Installation kopiert werden.

```bash
git clone https://github.com/eledia/moodle-elediaai-suite-free.git
rsync -a --exclude .git moodle-elediaai-suite-free/ /pfad/zu/moodle/public/
```

Danach *Website-Administration → Benachrichtigungen* aufrufen und die Installation bestätigen.

## Kostenpflichtige Ergänzungen

Den vollen MCP-Werkzeugkatalog und das White-Label-Branding des Tutors schaltet
`local_elediaai_tutor_premium` frei, erhältlich im Moodle Marketplace. Ohne dieses Add-on
laufen die Plugins hier mit ihrem freien Funktionsumfang.

Für den externen RAG-Stack (Agent, MCP-Tools, Ingestion, Vektordatenbank) und für gehosteten
Betrieb siehe [eledia.de](https://eledia.de/moodle-ki/).

## Unterstützte Moodle-Versionen

Entwicklungslinie ist Moodle 5.2. Welche Versionen ein Plugin unterstützt, steht in seiner
`version.php`.

## Lizenz

GNU General Public License v3 oder später. Siehe [LICENSE](LICENSE).

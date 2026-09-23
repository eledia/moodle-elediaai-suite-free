# eLeDia.ai Tutor (block_elediaai_tutor)

[English README](README.md)

[Dokumentation](docs/02-user-doc.md) · [Datenschutz](docs/privacy.md) · [Sicherheit](docs/security.md)

Der **eLeDia.ai Tutor** ist ein Moodle-nativer Chatbot-Block. Er verbindet
Moodle serverseitig mit einem externen RAG-/Tutor-MCP-Server und stellt die
Chat-Oberfläche, sichere Token-Übergabe und Moodle-Integration bereit.

Der Block ist bewusst **kein eigener RAG-Server**. Kursinhalte, Retrieval,
LLM-Zugriff und Tool-Ausführung liegen in den angebundenen Zusatzdiensten.

Der **vollständige geerdete Tutor** benötigt `local_elediaai_sources` (für die
kursbezogene Wissensbasis) und `webservice_elediamcp` (für den MCP-Rückruf): Im
geerdeten Modus prägt der Block immer ein nutzerbezogenes Token, damit das
RAG-/Tutor-Backend im Namen der lernenden Person in Moodle zurückrufen kann –
hierfür gibt es **keinen Abschalter**. Dieses "kein Abschalter" bezieht sich auf
die Provisionierung des geerdeten Rückrufs im Block, **nicht** auf eine
Installationsvoraussetzung und **nicht** darauf, ob ein Backend den Rückruf
tatsächlich *nutzt* (z. B. `enable_mcp_tools` in literag, was das Backend
entscheidet). Der **reine LLM-Chat läuft eigenständig** – ohne Token, ohne
Connector und ohne `webservice_elediamcp`. Der Block deklariert **keine harten
Plugin-Abhängigkeiten** in `version.php`: Er installiert und aktualisiert sich
unabhängig, fehlende Begleitplugins degradieren kontrolliert. Das optionale
`local_elediaai_core` stellt die zentrale Stunden-/Tages-Tokenquota bereit; fehlt
es, nutzt der Tutor seinen lokalen Fallback und der LLM-only-Chat bleibt
funktionsfähig.

- **Reifegrad:** Beta (`0.19.10`)
- **Moodle-Unterstützung:** 4.5–5.2 (Mindestversion: 4.5)
- **PHP-Unterstützung:** 8.3+
- **Erforderliche Laufzeit-Integration (geerdete Antworten):**
  `webservice_elediamcp` für den nutzerbezogenen Moodle-MCP-Rückruf, dazu
  `local_elediaai_sources` für die Wissensbasis; der reine LLM-Chat läuft eigenständig
- **Lizenz:** GNU GPL v3 oder später
- **Autor:** Christopher Reimann · © 2026 eLeDia GmbH, Berlin

---

## Architektur

```text
Browser (AMD chat.js)
  │  Moodle core/ajax  (authentifiziert, sesskey-geschützt)
  ▼
block_elediaai_tutor external functions  ──►  chat_service
  │                                            │
  │  webservice_elediamcp-Token (verpflichtend) │  rag_client (MCP Streamable HTTP, tools/call)
  ▼                                            ▼
nutzerbezogenes Moodle-MCP-Token  ───────►  externer RAG-/Tutor-MCP-Server
                                               │
                                               ▼
                                      Moodle MCP Server / weitere Tools
```

Geheimnisse werden nicht an den Browser ausgeliefert. MCP-Token, RAG-Token und
RAG-Server-URL bleiben serverseitig in Moodle.

## Installation

Release 0.19.10 unterstützt Moodle 4.5 bis 5.2 und PHP 8.3 oder neuer. Moodle
4.2–4.4 wird nicht unterstützt, weil der Block die Moodle Hooks API ohne
Legacy-Callback-Fallbacks verwendet. Aktualisieren Sie Moodle vor der
Installation auf Version 4.5 oder neuer.

1. Dieses Verzeichnis nach `blocks/elediaai_tutor` in die Moodle-Installation
   kopieren. Der Verzeichnisname muss exakt `elediaai_tutor` lauten.
2. Optional: `webservice_elediamcp` installieren und aktivieren, wenn der Tutor
   Moodle-MCP-Werkzeuge nutzen soll.
3. In Moodle **Website-Administration ▸ Mitteilungen** aufrufen, um die
   Installation bzw. Aktualisierung auszuführen.
4. Nur bei Änderungen an `amd/src` die JavaScript-Dateien neu bauen:
   ```bash
   cd /pfad/zu/moodle
   npx grunt amd --root=blocks/elediaai_tutor
   ```

Die gebauten Dateien unter `amd/build` sind enthalten, daher ist für die normale
Installation kein Build-Schritt nötig.

## Konfiguration

Die Einstellungen finden sich unter:

**Website-Administration ▸ Plugins ▸ Blöcke ▸ eLeDia.ai Tutor**

Wichtige Einstellungen:

| Einstellung | Erforderlich | Beispiel |
|---|---:|---|
| RAG-MCP-Server-URL | ja | `https://rag.example.com/mcp` |
| RAG-Authentifizierung / Token | falls benötigt | `Bearer` + Token |
| Chat-Tool-Name | ja, Standard meist passend | `tutor_chat` |
| History-Tool-Name | optional | `tutor_get_history` |
| Externer MCP-Service | ja (verpflichtend) | Service aus `webservice_elediamcp` |
| Token-Lebensdauer | ja, Standard meist passend | `3600` |

Danach kann der Block in Kursen oder auf dem Dashboard hinzugefügt werden.

## Zusatzplugins

Für den vollständigen geerdeten Betrieb werden diese Komponenten kombiniert:

- **eLeDia MCP** (`webservice_elediamcp`) für den nutzerbezogenen MCP-Rückruf und
  Moodle-Werkzeuge – im geerdeten Modus erforderlich.
- **RAG-Ingest** (`local_elediaai_sources`) zur Indexierung von Moodle-Kursinhalten in
  die Wissensbasis – im geerdeten Modus erforderlich.
- **LiteRAG** als RAG-/Tutor-MCP-Backend (alternativ ein externes Backend).

Der reine LLM-Chat läuft ohne diese Zusatzplugins. Sind sie für einen geerdeten
Betrieb nicht installiert oder deaktiviert, zeigt das Dashboard entsprechende
Hinweise an.

## Tests

```bash
# Aus dem Moodle-Root.
php admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --filter block_elediaai_tutor

vendor/bin/phpunit blocks/elediaai_tutor/tests/rag_client_test.php

php admin/tool/behat/cli/init.php
vendor/bin/behat --tags @block_elediaai_tutor

vendor/bin/phpcs --standard=moodle blocks/elediaai_tutor
```

## Dokumentation

- [Projektkontext](docs/00-master.md)
- [Funktionen](docs/01-features.md)
- [Dokumentation für Nutzer/innen, Lehrende und Admins](docs/02-user-doc.md)
- [Entwicklung und RAG-/MCP-Integration](docs/03-dev-doc.md)
- [Aufgaben und offene Punkte](docs/04-tasks.md)
- [Qualität und Verifikation](docs/05-quality.md)
- [Datenschutz](docs/privacy.md)
- [Sicherheit](docs/security.md)

## CI, Mirror und Release

Das Entwicklungsrepo führt den Pluginordner unter `public/blocks/elediaai_tutor`,
sodass das Repo ein Moodle-5.x-Dokumentenstammverzeichnis spiegelt. Die
CI-Konfiguration liegt im Repository-Root (eine Ebene über `public/`):

```text
<repo root>/
├── .forgejo/
│   └── workflows/moodle-ci.yml     # Forgejo Moodle Plugin CI
└── public/
    └── blocks/
        └── elediaai_tutor/         # das Plugin
```

- `.forgejo/workflows/moodle-ci.yml` läuft mit PHP 8.3. Pushes und Pull Requests
  prüfen die geänderten Gruppen mit harten Installations-, PHP-Lint- und
  PHPUnit-Gates gegen `MOODLE_405_STABLE` und `MOODLE_502_STABLE`. Tutor-
  Änderungen laufen einmal ohne `local_elediaai_core` und einmal mit installiertem
  Plugin, damit beide Verträge der optionalen Tokenquota geprüft werden.
  Nachtläufe und der standardmäßige manuelle Lauf decken zusätzlich
  `MOODLE_501_STABLE` ab; Behat bleibt ein Browser-Gate für Moodle 5.2.
- `tests/hook_callbacks_test.php` gehört zur PHPUnit-Suite und prüft den Hooks-
  Vertrag damit an beiden Supportgrenzen.
- Für die Veröffentlichung wird der Pluginordner flach in einen GitHub-Mirror
  gespiegelt, sodass `README.md`, `version.php`, `db/`, `classes/` und der
  CI-Workflow direkt im Repository-Root liegen.

## Gespeicherte Daten

Das Plugin speichert nur leichte Gesprächsreferenzen, z. B. die Conversation-ID
des RAG-Servers, kurze Vorschautexte und Zeitstempel. Vollständige Transkripte
liegen beim angebundenen RAG-/Tutor-Server. Details stehen in
[docs/privacy.md](docs/privacy.md).

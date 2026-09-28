# eLeDia.ai Tutor (block_elediaai_tutor)

[English README](README.md)

[Dokumentation](docs/02-user-doc.de.md) · [Datenschutz](docs/privacy.md) · [Sicherheit](docs/security.md)

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
entscheidet). **Jeder Gesprächsschritt trägt ein nutzerbezogenes
Moodle-MCP-Token**, ob geerdet oder reiner LLM-Chat: Auch ein Schritt ohne
Wissensbasis muss dem Server sagen, wer fragt, damit das Backend ihn einem
Mandanten und einer Person zuordnen kann. `webservice_elediamcp` ist deshalb
eine **harte Abhängigkeit** in `version.php`. Das Gespräch selbst führt
`local_elediaai_chatengine` (ebenfalls harte Abhängigkeit), das wiederum
`local_elediaai_core` voraussetzt; core liefert die gemeinsame Suite-Oberfläche
und die Stunden-/Tages-Tokenquota.

- **Release:** `1.0.1` (stable)
- **Moodle-Unterstützung:** 4.5–5.2 (Mindestversion: 4.5)
- **PHP-Unterstützung:** 8.3+
- **Pflicht-Plugins:** `local_elediaai_core`, `local_elediaai_chatengine`,
  `webservice_elediamcp` (liefert das nutzerbezogene Moodle-MCP-Token für jeden
  Gesprächsschritt)
- **Für geerdete Antworten zusätzlich:** `local_elediaai_sources` für die Wissensbasis
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

Release 1.0.1 unterstützt Moodle 4.5 bis 5.2 und PHP 8.3 oder neuer. Moodle
4.2–4.4 wird nicht unterstützt, weil der Block die Moodle Hooks API ohne
Legacy-Callback-Fallbacks verwendet. Aktualisieren Sie Moodle vor der
Installation auf Version 4.5 oder neuer.

1. Dieses Verzeichnis nach `blocks/elediaai_tutor` in die Moodle-Installation
   kopieren. Der Verzeichnisname muss exakt `elediaai_tutor` lauten.
2. `local_elediaai_core`, `local_elediaai_chatengine` und
   `webservice_elediamcp` **vorher oder gemeinsam** mit dem Block installieren –
   sie sind Pflicht, ohne sie verweigert Moodle die Installation.
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

Der reine LLM-Chat braucht `local_elediaai_sources` und LiteRAG nicht. Fehlt
für einen geerdeten Kurs der Connector oder der externe Dienst, zeigt der Block
Verwaltenden einen Konfigurationsfehler, statt still schlechter zu antworten.

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

- [Dokumentation für Nutzer/innen, Lehrende und Admins](docs/02-user-doc.de.md)
- [Datenschutz](docs/privacy.md)
- [Sicherheit](docs/security.md)

## Gespeicherte Daten

Die Gesprächsschritte (Fragen und Antworten) speichert
`local_elediaai_chatengine` in Moodle; dessen Privacy-Provider exportiert und
löscht sie. Der Block selbst hält die Zustimmung bei der ersten Nutzung, kurze
bereinigte Diagnosedaten für Admins und die Einstellung zum Langzeitgedächtnis.
Ein zustandsbehafteter RAG-/Tutor-Server führt ein eigenes Transkript nach
seinen eigenen Regeln. Details stehen in [docs/privacy.md](docs/privacy.md).

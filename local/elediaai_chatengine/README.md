# local_elediaai_chatengine

AI Chat Engine of the eLeDia.ai suite for Moodle.

The engine is the shared foundation of every chat surface in the suite: the
tutor block (`block_elediaai_tutor`), the AI chat activity (`mod_aichat`) and
the learning scenario (`mod_elli`). A surface decides who may chat and in
which voice the assistant answers; the engine runs the turn in between: quiz
lock, limits, course scope, prompt protection, conversation storage, quota,
rendering and deletion. The answering backend follows the destination
configured in `local_elediaai_sources` (external RAG agent or LiteRAG).

## Requirements

| Item | Value |
|---|---|
| Component | `local_elediaai_chatengine` |
| Release | see `version.php` |
| Moodle | 4.5 to 5.2 (`$plugin->supported = [405, 502]`) |
| Required plugin | `local_elediaai_core` |
| Used when present | `local_elediaai_sources`, `webservice_elediamcp`, `local_literag`, `local_aitransparency`, `local_elediaai_guide` |

## Installation

1. Copy the plugin to `local/elediaai_chatengine`.
2. Run the Moodle upgrade (Site administration or `php admin/cli/upgrade.php`).
3. Configure it under *Site administration → Plugins → Local plugins → AI Chat
   Engine*.

## Contracts

- [`docs/contract.md`](docs/contract.md): placement and backend contracts,
  conversation store, prompt safety, adding a chat surface.
- [`docs/rag_server_spec.md`](docs/rag_server_spec.md): the protocol a RAG /
  tutor MCP server implements, with its contract changelog. It is also shown in
  the suite handbook.

## Privacy

Conversations are stored in Moodle and covered by the Privacy API (export and
deletion). Messages are sent to the configured backend. Details:
[`docs/privacy.md`](docs/privacy.md) and [`docs/security.md`](docs/security.md).

## Licence

GNU GPL v3 or later. Lucide icon paths under the ISC licence
(`LICENSE-lucide.txt`).

## Documentation

- User and administrator guide (German): [`docs/02-user-doc.md`](docs/02-user-doc.md)
- Release notes: [`docs/releasenotes.md`](docs/releasenotes.md), technical changes: [`CHANGES.md`](CHANGES.md)
- Project status and development documentation (DevFlow, German):
  [`docs/00-master.md`](docs/00-master.md)

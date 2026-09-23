# LiteRAG - Moodle-local RAG backend

`local_literag` is a lightweight Retrieval-Augmented Generation backend for
Moodle. It stores ingested course content in Moodle's own database, retrieves
relevant text chunks with classical full-text search, checks Moodle visibility
for the requesting user, and asks an OpenAI-compatible LLM to generate grounded
answers with citations.

The plugin is designed for the eLeDia.ai Tutor landscape:

- `local_elediaai_sources` sends course content to LiteRAG.
- `block_elediaai_tutor` calls LiteRAG through the JSON-RPC/MCP tutor endpoint.
- Existing plugins do not need code changes; their endpoint URLs point to
  LiteRAG.

Developed by [eLeDia GmbH](https://eledia.de), Berlin.

German documentation: [README.de.md](README.de.md)

## Features

- Moodle-local RAG backend without embeddings or a vector database.
- Ingestion endpoint compatible with `local_elediaai_sources`.
- JSON-RPC/MCP tutor endpoint compatible with `block_elediaai_tutor`.
- Database full-text search for PostgreSQL, MySQL/MariaDB and MSSQL, with a
  portable `LIKE` fallback.
- Permission-safe retrieval through Moodle course-module visibility checks.
- OpenAI-compatible LLM client for OpenAI API or LiteLLM-style proxies.
- Structured sources, conversation history and optional long-term memory.
- Optional Live Moodle tools through `webservice_elediamcp`.
- Moodle Privacy API provider and retention cleanup task.
- Admin settings page with optional LernHive/eLeDia.ai Plugin Shell integration
  and a graceful Moodle-native fallback when the shell is unavailable.

## Requirements

- Moodle 4.5 to 5.2 as declared in `version.php`.
- PHP 8.1 or later.
- A Moodle database supported by the plugin's retrieval layer.
- An OpenAI-compatible chat-completions endpoint and API key.
- `$CFG->slasharguments` enabled for ingestion URLs such as
  `/local/literag/ingest.php/documents/upsert`.
- Optional: `local_lernhive` for the shared Plugin Shell UI.
- Optional: `webservice_elediamcp` for Live Moodle tools.
- Optional: Poppler `pdftotext` for higher-fidelity PDF extraction.

## Installation

1. Copy the plugin to the Moodle local-plugin directory:
   - Moodle 5.1+ document-root layout: `public/local/literag`
   - Classic layout: `local/literag`
2. Visit **Site administration > Notifications**.
3. Run the database upgrade.
4. Open **Site administration > Plugins > Local plugins > LiteRAG**.
5. Configure the API keys, LLM endpoint and retrieval settings.

## Configuration

### LiteRAG settings

Open **Site administration > Plugins > Local plugins > LiteRAG**.

Important settings:

- **Ingestion API key**: shared secret expected in the `X-API-Key` header.
- **Tutor transport token**: optional additional bearer token for tutor calls.
- **Tutor turns per user**: minute burst limit and hard daily ceiling, enforced
  before any paid LLM work.
- **LLM base URL**: OpenAI-compatible API base URL, for example
  `https://api.openai.com/v1`.
- **LLM API key**: bearer key for the LLM endpoint.
- **Model, temperature, max tokens and timeout**.
- **Retrieval and chunking**: candidate count, returned context count, chunk
  size and overlap.
- **Live Moodle tools**: enable read-only Moodle tool calls via
  `webservice_elediamcp`.
- **Privacy and retention**: query logging, conversation retention and memory
  retention.

The settings page displays the endpoint URLs that need to be copied into
`local_elediaai_sources` and `block_elediaai_tutor`.

### Connect `local_elediaai_sources`

Set the ingestion endpoint URL to:

```text
https://<wwwroot>/local/literag/ingest.php/documents/upsert
```

Use the same API key as configured in LiteRAG.

### Connect `block_elediaai_tutor`

Set the RAG server URL to:

```text
https://<wwwroot>/local/literag/mcp.php
```

The default tool names in the tutor block match LiteRAG's defaults.

## Live Moodle tools

LiteRAG can optionally call read-only Moodle tools from `webservice_elediamcp`.
This lets the tutor include live learner-specific Moodle data such as courses,
assignments, deadlines, grades, calendar entries and progress.

The internal MCP client always calls the local Moodle site's `$CFG->wwwroot`.
Request-supplied `system_url` values are ignored for this loopback call to avoid
SSRF and token exfiltration.

Only read-only tools are exposed to the model unless write tools are explicitly
enabled for supported two-step confirmation flows.

## PDF support

LiteRAG bundles the pure-PHP `smalot/pdfparser` library for PDF text extraction.
No external binary is required for basic PDF indexing.

For higher-fidelity extraction, install Poppler and configure the absolute path
to `pdftotext` in the LiteRAG settings or through Moodle's
`$CFG->pathtopdftotext`.

Examples:

| Environment | Command |
|---|---|
| Debian / Ubuntu | `sudo apt-get install poppler-utils` |
| Docker Debian image | `apt-get update && apt-get install -y poppler-utils` |
| RHEL / Rocky / Alma | `sudo dnf install poppler-utils` |
| macOS Homebrew | `brew install poppler` |

## Data model

LiteRAG uses these main tables:

- `local_literag_sources`
- `local_literag_chunks`
- `local_literag_conversations`
- `local_literag_messages`
- `local_literag_query_log`
- `local_literag_memory`
- `local_literag_topics`

Database-family-specific full-text indexes are created through guarded SQL in
install and upgrade steps. When native full-text search is unavailable, LiteRAG
falls back to `LIKE` queries.

## Security and privacy

- Ingestion requests are authenticated with a constant-time `X-API-Key` check.
- Tutor requests validate the Moodle user token in-process.
- Retrieved chunks are filtered against Moodle visibility for the requesting
  user.
- Live Moodle tools use the local `$CFG->wwwroot` and do not trust
  request-supplied target URLs.
- LLM API keys, transport tokens and Moodle user tokens are not logged.
- Long-term memory is optional and consent-controlled.
- Moodle Privacy API export and erasure are implemented.
- Retention cleanup is handled by a scheduled task.

## Development and tests

Run Moodle Coding Standard:

```sh
phpcs --standard=Moodle --ignore='*/vendor/*' public/local/literag
```

Run PHPUnit from the Moodle root:

```sh
vendor/bin/phpunit --testsuite local_literag_testsuite
```

The local Docker validation on 2026-06-27 passed with:

```text
Tests: 57, Assertions: 188
```

PHPUnit 11 reports test-runner deprecations for legacy docblock metadata in the
test classes. These are not test failures but should be migrated to attributes
before PHPUnit 12.

## Continuous integration

In the eLeDia.ai monorepo the Forgejo workflow
`.forgejo/workflows/moodle-ci.yml` runs LiteRAG in the `rag` catalog group
together with `local/elediaai_sources` and `webservice/elediamcp`, staging the suite
dependencies `local/elediaai_core` and `local/elediaai_tutor_premium`.

Cross-database evidence comes from that workflow, not from a GitLab pipeline:
the regular matrix runs the group against PostgreSQL 16, and the nightly
`crossdb` job installs the same group a second time against MariaDB 11.4 and
runs the PHPUnit suites there. The groups covered are listed in
`CROSS_DB_GROUPS` in `scripts/ci-matrix.php`.

## Third-party libraries

Bundled under `vendor/` and declared in `thirdpartylibs.xml`:

- `smalot/pdfparser` 2.12.5, LGPL-3.0, pure-PHP PDF text extraction.

## Status and roadmap

Current release: `0.6.4`, maturity `MATURITY_BETA`.

Known follow-up work:

- Document the first green Forgejo CI run for the dedicated LiteRAG job.
- Beta-exit and directory-release criteria are defined as a checkable two-gate
  list in `docs/05-quality.md` (section "Reifegrad-/Release-Kriterien"): Gate 1
  is the internal BETA → stable release gate (green CI incl. cross-database
  evidence, fully green test suite, phpcs, a resolved Behat decision, consistent
  docs, a documented end-to-end run, then the `MATURITY_STABLE` bump to SemVer
  `>= 1.0.0`); Gate 2 adds the criteria for an optional public moodle.org
  submission. Whether to pursue that public submission stays a separate product
  decision.

## License

GNU GPL v3 or later. See [LICENSE](LICENSE).

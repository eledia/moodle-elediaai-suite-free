# local_elediaai_sources - AI Sources for Moodle

English | [Deutsch](README.de.md)

`local_elediaai_sources` is the **source layer** of the eLeDia.ai suite. It
answers one question for the whole suite — *which Moodle content may be used as
a knowledge source, and in what shape?* — and then hands that content to an
interchangeable destination.

Everything that makes content usable lives here **once**: the opt-in rules, the
per-activity selection, the extraction and normalisation, the deterministic
document identity, the tenant derivation, and the lifecycle when content
changes or disappears. A destination does not repeat any of it; it only knows
its own endpoints and authentication.

That split is the point of the plugin, and it opens in two directions:

| Extension axis | Question it answers | Mechanism |
|---|---|---|
| **Source types** | What content can we read? | `aisourcesextractor_*` subplugins (17 bundled) |
| **Destinations** | Where does it go? | Implementations of the `sink` interface |

Destinations today are the external **Ingestion API** and **LiteRAG** on the
same site. A third slot is reserved for an **OERWEAVE** source basket; the
interface exists, the implementation does not yet. Adding it means writing one
class — no changes to selection, extraction, identity or lifecycle.

Exactly one destination is active at a time. Switching is supported and makes
every marked course diverge, so the existing reconciliation re-ingests it into
the new destination.

> **Naming:** the plugin was called `local_ragingest` until release `0.16.0`.
> The old name described the mechanism (RAG ingest) rather than the job
> (curating sources), and it tied a generic capability to one kind of consumer.

The plugin is part of the eLeDia.ai Tutor / LiteRAG admin flow. Its settings,
status panel, reindex page, and help page are rendered in the shared
plugin shell when that shell is available. The teacher-facing activity
selection deliberately stays in the normal course UI instead.

## Status

| Item | Current state |
|---|---|
| Moodle component | `local_elediaai_sources` |
| Plugin type | Local plugin installed at `local/elediaai_sources` |
| Supported Moodle versions | `4.5` to `5.2` in `version.php` |
| Local compatibility check | Moodle `5.2.1` passes the PHPUnit suite |
| PHP | Moodle-supported PHP for the target Moodle version |
| Maturity | Beta |

Moodle 5.2.1 is used locally for development and tests, and the official
`supported` metadata is `[405, 502]`.

## Features

**Choosing sources**

- **Two-stage, opt-in selection:** a course must first be released centrally
  (pilot list, category allow-list) or per course (custom field). Inside a
  released course, teachers then decide per activity.
- **Per-activity selection for teachers:** a course page with instant toggles,
  grouped by section, plus a checkbox in each activity's edit form. Both write
  the same decision.
- **Configurable default for undecided activities:** opt-out (everything
  supported is ingested, the default) or opt-in (nothing until selected). An
  explicit teacher decision always wins and survives a change of this setting;
  changing it never removes anything already indexed.
- **Marking lock:** during pilot phases the course field, the activity page and
  its web service can be frozen so only central settings decide.

**Reading content**

- **Subplugin extractors:** one `aisourcesextractor_*` subplugin per activity
  type; 17 are bundled.
- **Captions as the real text of packaged e-learning:** SCORM packages are
  often a player shell with no readable prose. The transcript is read from
  standalone `.vtt`/`.srt` tracks and, for Articulate Storyline, unwrapped from
  its JavaScript-bundled captions.
- **Multi-document modules:** folders and similar modules emit several
  documents using suffix-based source IDs and prefix deletes.
- **H5P resolution:** embedded H5P placeholders are resolved to their text.

**Keeping the index true**

- **Deterministic document identity:** `{tenant}:course{id}:cmid{id}` plus an
  optional suffix, derived — never stored — so any destination can be addressed
  the same way.
- **Tenant from site URL:** derived from `$CFG->wwwroot`; deliberately no
  free-text tenant setting.
- **Per-destination state:** each course records which destination, embedding
  model and tenant it was last ingested into, so switching any of them makes it
  diverge and be re-ingested.
- **Event-driven lifecycle:** module create/update/delete and supported
  subcontent changes queue ad-hoc tasks. Deleting a course clears its documents
  first, while its module IDs are still readable.
- **Deselection removes content:** excluding an activity issues a prefix-scoped
  delete instead of merely skipping it.
- **Reconciliation safety net:** a scheduled task re-aligns courses whose
  marking and index state disagree.
- **Manual reindex:** bulk-queue all pending released courses, or reindex one
  course by ID.

## Installation

1. Copy the plugin directory to the Moodle codebase:

   ```text
   local/elediaai_sources
   ```

2. Run Moodle upgrade through Site administration or CLI:

   ```bash
   php admin/cli/upgrade.php --non-interactive
   ```

3. Open the settings page:

   ```text
   /admin/settings.php?section=local_elediaai_sources_settings
   ```

4. Configure the RAG endpoint and API key.

5. Release one or more pilot courses, then run Moodle cron so queued ad-hoc
   tasks can process the indexing work.

## Admin Settings

All settings live on one page:

```text
/admin/settings.php?section=local_elediaai_sources_settings
```

When the eLeDia.ai Tutor shell is present, this page appears in the shared
navigation with the active menu item **AI Sources**. The plugin no longer splits
setup across several Moodle admin menus.

### Connection

| Setting | Description | Default |
|---|---|---|
| Destination | Which target documents are sent to: Ingestion API or LiteRAG | Ingestion API |
| Ingestion API base URL | Service root **without** an action path | empty |
| Ingestion API key | Secret sent as `X-API-Key`, issued per tenant | empty |
| Allow private target | Enables Moodle cURL `ignoresecurity` for local/private targets | off |

The destination is chosen explicitly; it is no longer inferred from the shape
of a URL. Each destination knows its own endpoints:

- **Ingestion API** — the base URL plus the paths fixed by
  `docs/api-specification.md`: `/documents/upsert`, `/documents/delete`,
  `/health`. Configure `http://rag-service:8001`, **not**
  `http://rag-service:8001/documents/upsert`.
- **LiteRAG** — nothing to configure. The route is derived from `wwwroot` and
  the ingestion key is read from `local_literag` itself.

Switching the destination makes every course diverge, so the existing
reconciliation re-ingests it into the new target. The old target is **not**
cleared automatically.

`allow_private_target` exists because Moodle normally protects cURL calls from
private, loopback, and otherwise blocked targets. Keep it off for public
endpoints and enable it only intentionally for local Docker/service-name
targets.

### Course Selection

| Setting | Description |
|---|---|
| Ingested course categories | Searchable multi-select of categories. Courses in selected categories or subcategories are released. |
| Pilot courses | Searchable multi-select of specific courses. Intended for controlled pilots. |
| Activities without a decision | Default for activities a teacher has not decided on: included (opt-out, default) or not included (opt-in). |
| Lock course marking | Makes the course custom field inert/read-only and hides the activity selection so only central settings decide. |

The course custom field **AI Sources** (shortname `aisources`) is created on
install. When course marking is not locked, it supports:

- `Default`: central pilot/category rules decide.
- `Include`: release this course even when central rules do not.
- `Exclude`: prevent indexing even when central rules would release it.

When marking is locked, existing field values are kept but ignored until the
lock is disabled again.

### Activity Selection

Releasing a course decides *whether* it may be used; the activity selection
decides *what* of it is used. Teachers reach it two ways, both writing the same
decision:

- the course page **AI Sources** (course navigation), with one toggle per
  activity, grouped by section — activity types no extractor can read are shown
  disabled rather than hidden, so the page does not imply more coverage than
  exists;
- the section **eLeDia.ai | AI Sources** in each activity's edit form.

Only explicit decisions are stored. Activities nobody touched follow the site
default above. Two consequences worth knowing:

- **Changing the site default never deletes.** Removal is driven solely by an
  explicit exclusion, so switching to opt-in leaves everything already indexed
  in place; it only stops adding undecided activities.
- **Duplicating or restoring an activity carries its decision along.** The
  index state does not travel: it describes what this site sent to its
  destination.

### Limits

| Setting | Description | Default |
|---|---|---|
| Max document size (MB) | Text/HTML above the limit is truncated; oversized binary files are skipped | `20` |
| Request timeout (seconds) | Timeout per HTTP request attempt | `30` |

## Indexing Status and Reindex

The top of the settings page shows a status panel:

- **Released courses are indexed:** no released courses are waiting for
  indexing.
- **Released courses are waiting for indexing:** one or more released courses
  have not yet been indexed successfully.

When courses are waiting, the primary action **Index released courses now**
queues the pending courses as Moodle ad-hoc tasks. The secondary action opens:

```text
/local/elediaai_sources/reindex.php
```

The reindex page offers:

- bulk queuing for released courses that are not indexed yet
- manual reindex of one course by numeric Moodle course ID

Course state is persisted in `local_elediaai_sources_course`. A course is marked
`ingested = 1` only when a reindex run completes without error results. Skipped
modules do not count as errors, so empty or unsupported courses do not get
queued forever.

## What Gets Indexed

The plugin indexes content from released courses only. It never indexes:

- the site course
- deleted or invisible course modules
- courses without opt-in release
- unsupported module types
- empty activities
- oversized binary files
- personal learner responses in Feedback and similar extractors where those
  responses are intentionally excluded

Supported event triggers include:

- course module created, updated, deleted
- book chapter changes
- glossary entry changes
- lesson page changes
- wiki page changes
- database record changes
- quiz structure changes
- question-bank changes that affect quizzes

## Bundled Extractors

The plugin ships extractors for the common core activity types plus selected
package/interactive modules.

| Subplugin | Activity | Notes |
|---|---|---|
| `assign` | Assignment | Intro, activity instructions, grading criteria; no student submissions |
| `book` | Book | Visible chapters/subchapters in reading order |
| `data` | Database | Field definitions and approved records; user responses are scoped to record content |
| `feedback` | Feedback | Question/item definitions; no submitted responses |
| `folder` | Folder | One document per supported file, preserving PDF MIME type |
| `glossary` | Glossary | Description, approved entries, aliases/synonyms |
| `h5pactivity` | H5P Activity | Labelled educational text from H5P JSON/package content |
| `imscp` | IMS content package | Manifest structure and HTML body content |
| `label` | Text/media area | Intro content |
| `lesson` | Lesson | Page sequence, answers, feedback where relevant |
| `page` | Page | Page content |
| `quiz` | Quiz | Questions, answers, feedback, hints, and overall feedback |
| `resource` | File | Supported text, HTML, and PDF files |
| `scorm` | SCORM | Intro, SCO titles, and local HTML launch pages |
| `videotime` | Video Time | Intro and VTT captions/transcript |
| `wiki` | Wiki | Intro and subwiki pages |
| `workshop` | Workshop | Intro, instructions, conclusion, grading dimensions |

HTML produced by extractors is centrally post-processed for embedded H5P
placeholders where possible.

## API Contract

### Upsert

```http
POST /documents/upsert
X-API-Key: <configured key>
Content-Type: application/json
```

```json
{
    "source_id": "localhost:course42:cmid99",
    "content": "<base64-encoded content>",
    "content_type": "text/html",
    "qdrant_metadata": {
        "tenant_id": "localhost",
        "site_url": "http://localhost:8080",
        "course_id": 42,
        "cmid": 99,
        "module_url": "http://localhost:8080/mod/page/view.php?id=99"
    },
    "parser_options": null
}
```

### Delete

```http
POST /documents/delete
X-API-Key: <configured key>
Content-Type: application/json
```

```json
{
    "source_id": "localhost:course42:cmid99"
}
```

Multi-document modules use suffixes such as:

```text
localhost:course42:cmid99:file1
```

Before a multi-document upsert, the manager clears the previous document set
with a prefix-scoped delete.

Allowed content types are decided by the versioned support matrix
(`classes/format_matrix.php`, documented in
[`docs/format-support-matrix.md`](docs/format-support-matrix.md)), not by the
extractors:

- **Core, always sent:** `text/plain`, `text/html`, `application/pdf`
- **Negotiated, sent while the active destination announces them on
  `GET /health`:** DOCX, PPTX, `text/markdown`, XLSX, CSV, DOC, ODT/ODS/ODP

There is no third "not decided yet" state. Nothing is parsed here, so every
open question about a format is a parser question: the destination answers it
by announcing the type or not.

Every file that is not sent leaves a notice naming the file and the reason, in
the cron log, in the ingestion result and in the activity preview.

## Architecture

```text
Moodle event / teacher toggle / scheduled reconcile
  -> local_elediaai_sources\observer
  -> Moodle ad-hoc task
  -> course_gate      may this course be used?
  -> activity_gate    is this activity selected?
  -> aisourcesextractor_* subplugin        ← source types plug in here
  -> document normalization + source id
  -> local_elediaai_sources\sink\sink      ← destinations plug in here
  -> local_elediaai_sources\api_client
  -> Ingestion API | LiteRAG | (OERWEAVE, reserved)
```

Everything above the two arrows is shared by every destination. That is why a
new destination costs one class, and a new source type one subplugin.

Destinations:

| Sink | Endpoints | Configuration |
|---|---|---|
| `ingestion_api_sink` | Base URL plus the paths fixed by `docs/api-specification.md` | Base URL and per-tenant API key |
| `literag_sink` | `local/literag/ingest.php`, derived from `wwwroot` | None — the key is read from `local_literag` |
| *OERWEAVE* | — | Reserved slot; not implemented |

Key classes:

| Class | Responsibility |
|---|---|
| `content_extractor` | Interface for activity extractors |
| `multi_document_extractor` | Optional interface for modules that emit several documents |
| `ingestion_manager` | Discovers extractors, validates/normalizes documents, hands them to the destination |
| `sink` | Interface a destination implements: its endpoints, its authentication, its embedding model |
| `sink_manager` | Resolves the configured destination and offers the settings menu |
| `api_client` | HTTP transport only: retries, timeout, `X-API-Key` |
| `course_gate` | Computes whether a course may be ingested at all |
| `activity_gate` | Computes whether one activity is selected; stores explicit decisions |
| `course_state` | Persists current index state and queues reconciliation |
| `observer` | Converts Moodle events into background tasks |
| `source_id_helper` | Builds deterministic source IDs |
| `tenant` | Derives tenant identity from `$CFG->wwwroot` |
| `h5p_embed_helper` | Resolves embedded H5P placeholders in HTML |
| `h5p_text_extractor` | Extracts labelled text from H5P content JSON/packages |
| `output\shell` | Adapts plugin pages to the eLeDia.ai Tutor shell |

Tasks:

| Task | Purpose |
|---|---|
| `ingest_module_task` | Extract and upsert one course module |
| `delete_module_task` | Delete one course module document set |
| `reconcile_course_task` | Bring one course into the desired indexed/purged state |
| `reconcile_all_task` | Periodic safety net for divergent courses |

## Writing an Extractor

Create a subplugin under:

```text
subplugins/{name}/
├── version.php
├── classes/extractor.php
└── lang/en/aisourcesextractor_{name}.php
```

The extractor class is:

```php
namespace aisourcesextractor_{name};

use local_elediaai_sources\content_extractor;

final class extractor implements content_extractor {
    public function supports(\cm_info $cm): bool {
        return $cm->modname === '{name}';
    }

    public function extract(\cm_info $cm): ?array {
        return [
            'content' => '<p>Extracted content</p>',
            'content_type' => 'text/html',
            'title' => $cm->name,
        ];
    }
}
```

Guidelines:

- return `null` when there is no useful content
- use only allowed content types
- escape user/editor content before building HTML
- avoid indexing personal submissions unless explicitly intended and reviewed
- use `multi_document_extractor` for files or repeated subdocuments
- let central post-processing handle H5P placeholders in HTML

An extractor never decides *whether* content may be used, and never talks to a
destination. It is handed a course module and returns text.

## Adding a Destination

A destination implements `local_elediaai_sources\sink\sink` and is registered in
the `CLASSES` constant of `sink_manager`. This is the seam a future OERWEAVE
basket plugs into.

```php
namespace local_elediaai_sources\sink;

class oerweave_sink implements sink {
    public static function id(): string;          // stable; stored per course
    public static function name(): string;        // shown in the settings menu
    public function is_configured(): bool;        // everything present to be used?
    public function healthcheck(): array;         // reachable and healthy?
    public function embedding_model(): ?string;   // null when none is exposed
    public function upsert(array $payload): array;
    public function delete(string $sourceid, string $scope = 'exact'): array;
}
```

What a destination gets for free: selection, extraction, normalisation, the
size limit, document identity, tenant derivation, retries and the whole
lifecycle. What it owns: its endpoints, its authentication, and whether it can
name an embedding model.

Guidelines:

- keep `id()` stable across releases — it is stored per course, so changing it
  makes every course diverge and re-ingest
- return the transport's result row shape
  (`success`, `http_code`, `response`, `error`) so callers stay unchanged
- implement `delete()` honouring `scope`; `prefix` must remove the document and
  every sub-document beneath it, or multi-file activities leak on re-ingest
- return `null` from `embedding_model()` rather than inventing a value — an
  invented model is recorded per course and later compared as if it meant
  something
- use `api_client` for HTTP so retries, timeout and `X-API-Key` behave alike

## Local Development

### Deploy to the local eledia.ai Moodle

The companion Docker setup in the eledia.ai project provides a local Moodle at:

```text
http://localhost:8080
```

Typical flow:

```bash
cd /Users/moskaliuk/Documents/Code/eledia.ai
./scripts/local-deploy.sh deploy
```

If the plugin checkout is not baked into that image, copy or sync this plugin to:

```text
/var/www/html/public/local/elediaai_sources
```

and run Moodle upgrade/purge caches.

### Debug Server

A small Python mock server is included for local API testing:

```bash
python3 local/elediaai_sources/debug_server.py
python3 local/elediaai_sources/debug_server.py --port 9000
python3 local/elediaai_sources/debug_server.py --fail
python3 local/elediaai_sources/debug_server.py --delay 5
```

`debug_server.py` is excluded from release archives through `.gitattributes`.

## Testing and Code Style

### PHPUnit

The local Docker setup can initialise Moodle PHPUnit and run this plugin's
testsuite:

```bash
cd /Users/moskaliuk/Documents/Code/eledia.ai
./scripts/local-deploy.sh phpunit-init
./scripts/local-deploy.sh phpunit
PHPUNIT_TESTSUITE=local_elediaai_sources_testsuite ./scripts/local-deploy.sh phpunit
```

The current local result is:

```text
Tests: 164
Assertions: 373
Failures: 0
Errors: 0
Skipped: 5
PHPUnit Deprecations: 27
Notices: 1
```

For a direct Moodle checkout with an already initialised PHPUnit environment:

```bash
vendor/bin/phpunit --testsuite local_elediaai_sources_testsuite
```

### PHP Syntax

```bash
find public/local/elediaai_sources -name '*.php' -print0 | xargs -0 -n1 php -l
```

### Moodle Coding Style

Install `moodlehq/moodle-cs` outside the plugin checkout and run PHPCS:

```bash
rm -rf /tmp/local-elediaai-sources-moodle-cs
mkdir -p /tmp/local-elediaai-sources-moodle-cs
cd /tmp/local-elediaai-sources-moodle-cs
composer init --no-interaction --name=local-elediaai-sources/moodle-cs-tools
composer config allow-plugins.dealerdirect/phpcodesniffer-composer-installer true
composer require --dev moodlehq/moodle-cs

cd /Users/moskaliuk/Documents/Code/local_elediaai_sources
/tmp/local-elediaai-sources-moodle-cs/vendor/bin/phpcs \
    --standard=moodle \
    --extensions=php \
    '--ignore=public/local/elediaai_sources/tests/fixtures/*' \
    public/local/elediaai_sources
```

Auto-fix style-only issues with:

```bash
/tmp/local-elediaai-sources-moodle-cs/vendor/bin/phpcbf \
    --standard=moodle \
    --extensions=php \
    '--ignore=public/local/elediaai_sources/tests/fixtures/*' \
    public/local/elediaai_sources
```

The current branch passes `phpcs --standard=moodle`.

### Frontend Checks

Moodle's Grunt tooling runs from a Moodle checkout with Node `>=22.11 <23`.
From the plugin directory inside that checkout:

```bash
npx grunt amd --no-color
npx grunt rawcss --no-color
```

`amd` runs `ignorefiles`, `eslint:amd`, and `rollup`; it also regenerates
`amd/build/*.min.js`. `rawcss` runs Stylelint for plain CSS files.

This plugin currently has no Mustache templates and no bundled third-party
libraries, so the Mustache and third-party-library checks are not applicable.
If templates or bundled libraries are added later, include the corresponding
Moodle precheck before submission.

## Documentation

```text
docs/user_manual.md        — administrator and user guide (also shown on the plugin's help page)
docs/api-specification.md  — the ingestion API contract (v1.2) a destination must implement
docs/submission-draft.md   — notes for the Moodle plugin directory submission
```

## Privacy

The plugin sends extracted course content and module metadata to an external RAG
service. The privacy provider declares this external location, including:

- site URL
- course ID
- course module ID
- extracted content

The plugin keeps no per-user content records. It does store one user reference:
`local_elediaai_sources_cm.usermodified`, the person who last changed an
activity's selection. That table and its fields are declared in the privacy
provider.

Extractors are responsible for avoiding personal learner submissions unless a
future feature explicitly introduces and documents that behaviour.

## Capabilities

| Capability | Context | Default roles | Purpose |
|---|---|---|---|
| `local/elediaai_sources:reindex` | System | manager | Manual reindex: bulk-queue released courses or reindex one course by ID |
| `local/elediaai_sources:selectactivities` | Module | editingteacher, manager | Decide per activity whether it is used as a source — the course page, the form field and the web service all check it |

Two capabilities on purpose: releasing a *course* is an administrative decision
and stays with `moodle/site:config` and the central settings, while choosing
*activities* inside a released course belongs to whoever teaches it.

Both are inert while **Lock course marking** is on: during a pilot phase the
form field, the course page and the web service are unavailable, so the set of
indexed content is controlled centrally.

## License

GNU GPL v3 or later.

## Author

Christopher Reimann, eLeDia GmbH.

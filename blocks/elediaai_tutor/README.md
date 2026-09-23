# eLeDia.ai Tutor (block_elediaai_tutor)

[Deutsche README](README.de.md)

[Documentation](docs/02-user-doc.md) · [Privacy](docs/privacy.md) · [Security](docs/security.md)

A polished, Moodle-native chatbot block that connects **server-side** to an
external RAG/Tutor MCP server. The block is a chat **frontend and secure
connector** only — it does not implement retrieval-augmented generation itself.

It pairs with [`webservice_elediamcp`](../../webservice/elediamcp), which turns
Moodle into an MCP server and provides the internal PHP API used here to mint
user-scoped MCP tokens. The **full grounded tutor** requires `local_elediaai_sources`
(to build the per-course knowledge base) and `webservice_elediamcp` (for the
MCP call-back): in grounded mode the block always mints a user-scoped token so
the RAG/Tutor backend can call back into Moodle as the learner — there is **no
off switch** for this. That "no off switch" refers to the block's grounded-mode
*provisioning*, not to an install-time requirement and not to whether a backend
*uses* the call-back (e.g. literag's `enable_mcp_tools`, decided backend-side).
When a course resolves to grounded but the connector or external service is
missing, the block shows managers a configuration error instead of silently
degrading. **Every turn carries a user-scoped Moodle MCP token**, grounded or
LLM-only: even a turn that retrieves nothing must tell the server who is asking,
so the backend can attribute it to a tenant and user. `webservice_elediamcp` is
therefore a **hard dependency** in `version.php` and must be installed first.
The optional `local_elediaai_core` companion supplies the shared hourly/daily
token quota; without it, the Tutor keeps its local fallback and chat continues
to work.

- **Maturity:** Beta (`0.21.0`)
- **Moodle support:** 4.5–5.2 (minimum Moodle version: 4.5)
- **PHP support:** 8.3+
- **Required plugin dependency:** `webservice_elediamcp` — supplies the
  user-scoped Moodle MCP token that every turn carries
- **Required for grounded answers:** `local_elediaai_sources` for the knowledge base
- **License:** GNU GPL v3 or later
- **Author:** Christopher Reimann · © 2026 eLeDia GmbH, Berlin

---

## Architecture

```text
 Browser (AMD chat.js)
   │  Moodle core/ajax  (authenticated, sesskey-protected)
   ▼
 block_elediaai_tutor external functions  ──►  chat_service
   │                                            │
   │  webservice_elediamcp token (mandatory)     │  rag_client (MCP Streamable HTTP, tools/call)
   ▼                                            ▼
 user-scoped Moodle MCP token  ───────────►  External RAG / Tutor MCP server
                                                │
                                                ▼
                                       Moodle MCP server / other tools
```

**Secrets never reach the browser.** The Moodle MCP token, the RAG authorization
token and the RAG server URL all live server-side. The browser only ever talks
to Moodle.

See the consolidated documentation:

- [Master / project context](docs/00-master.md)
- [Features](docs/01-features.md)
- [User, teacher and admin documentation](docs/02-user-doc.md)
- [Developer and RAG/MCP integration documentation](docs/03-dev-doc.md)
- [Tasks and open questions](docs/04-tasks.md)
- [Quality, bugs and verification](docs/05-quality.md)
- [Privacy](docs/privacy.md)
- [Security notes](docs/security.md)

---

## Installation

Release 0.19.10 supports Moodle 4.5 through 5.2 and PHP 8.3 or newer. Moodle
4.2–4.4 are not supported because the block uses Moodle's Hooks API without
legacy callback fallbacks; upgrade Moodle to 4.5 or newer before installing.

1. Copy this directory to `blocks/elediaai_tutor` in your Moodle tree (the path
   must be exactly `elediaai_tutor`).
2. Install and enable `webservice_elediamcp` **before** this block — it is a
   hard dependency, and Moodle refuses the install without it.
3. Visit **Site administration ▸ Notifications** to run the install.
4. Build the front-end (only needed if you change `amd/src`):
   ```bash
   cd /path/to/moodle
   npx grunt amd --root=blocks/elediaai_tutor
   ```
   A working `amd/build/chat.min.js` is committed, so the block runs out of the
   box without a build step.

## Required configuration

At **Site administration ▸ Plugins ▸ Blocks ▸ eLeDia.ai Tutor**:

| Setting | Required | Example |
|---|---|---|
| RAG MCP server URL | ✅ | `https://rag.example.com/mcp` |
| RAG authorization method / token | if your server needs it | `Bearer` + token |
| Chat tool name | ✅ (default ok) | `tutor_chat` |
| History tool name | optional | `tutor_get_history` |
| MCP external service | ✅ (mandatory) | one of the services configured in `webservice_elediamcp` |
| Token lifetime | ✅ (default ok) | `3600` |
| Stream answers | optional (on by default) | needs an unbuffered web server — see below |

Then add the **eLeDia.ai Tutor** block to a course or the Dashboard.

## Streaming answers: web server requirements

With **Stream answers** enabled (`streamingenabled`, on by default) the block
opens `blocks/elediaai_tutor/stream.php` and renders the answer while the RAG
server produces it. That only works if every layer between PHP and the browser
passes chunks straight through. Most stacks buffer by default and will hold the
whole answer back — the learner then waits, sees the complete reply at once, and
streaming appears broken although nothing errored.

The block degrades gracefully: if no fragment arrives within 10 seconds, it falls
back to the buffered path and still shows the full answer. So a misconfigured
server costs the live effect, not the feature.

### Verifying quickly

Drop this next to `index.php` in the document root and request it with
`curl -N`. The lines must arrive one second apart; if they appear all at once,
something in the chain is buffering.

```php
<?php
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
while (ob_get_level() > 0) { ob_end_flush(); }
for ($i = 1; $i <= 5; $i++) {
    echo "event: token\ndata: {\"n\": $i}\n\n";
    @ob_flush(); flush(); sleep(1);
}
```

```bash
curl -sN https://moodle.example.com/ssetest.php | while read -r l; do date +%T.%2N; done
```

Remember to delete the file afterwards.

### Apache with PHP-FPM (mod_proxy_fcgi)

`mod_proxy_fcgi` collects the response from PHP-FPM before forwarding it. Declare
the FCGI worker with `flushpackets=on` in the vhost:

```apache
<Proxy "fcgi://localhost/" flushpackets=on>
</Proxy>
```

This is the setting that matters. Note that `X-Accel-Buffering: no`, which the
endpoint sends, is an **nginx** convention and has no effect on Apache.

### nginx

```nginx
location ~ \.php$ {
    fastcgi_buffering off;
    # or, honouring the header the endpoint already sends:
    fastcgi_pass_header X-Accel-Buffering;
}
```

### Anything in front

A CDN or reverse proxy (Cloudflare, Varnish, a load balancer) may buffer as well.
Exclude `blocks/elediaai_tutor/stream.php` from response buffering there, and
make sure the connection is not closed by an idle timeout below the configured
request timeout.

### PHP

`output_buffering` normally does not need changing — the endpoint flushes its own
buffers. If your stack still buffers after the web server is configured, set it
per directory rather than globally:

```ini
; public/.user.ini
output_buffering = 0
implicit_flush = 1
```

### Compression

Compression modules buffer to work at all. `text/event-stream` is not part of the
usual `mod_deflate` type list, so this rarely bites — if you added it, exclude it
again.

## Quick test commands

Paths below use the Moodle 5.1+ layout where the code lives under `public/`; on
Moodle 4.5 and 5.0, omit that prefix. The PHPUnit suite passes on Moodle 5.2 /
PHP 8.4 / PHPUnit 11; test metadata uses PHP attributes (`#[CoversClass]`), the
Moodle 5.x convention.

```bash
# PHPUnit — initialise once (re-run after any version bump), then run the component suite.
php public/admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --testsuite block_elediaai_tutor_testsuite

# A single test file
vendor/bin/phpunit public/blocks/elediaai_tutor/tests/rag_client_test.php

# Behat — initialise once, then run this plugin's tagged scenarios (needs Selenium).
php public/admin/tool/behat/cli/init.php
vendor/bin/behat --config "$(php public/admin/tool/behat/cli/util.php --behatdir 2>/dev/null || echo behatdata/behatrun)/behat/behat.yml" --tags @block_elediaai_tutor

# Code style (requires moodlehq/moodle-cs installed via composer)
vendor/bin/phpcs --standard=moodle public/blocks/elediaai_tutor
```

> Note: the footer-branding tests assert the free-block behaviour only when the optional
> `local_elediaai_tutor_premium` add-on is **absent**; when it is installed they verify the
> unlocked behaviour instead, so the suite is green with or without the add-on.

## Continuous integration & publishing

This plugin is developed inside a full Moodle tree but published to its own
company repository, **wrapped under `public/`** so the repo mirrors a Moodle 5.x
document root. The CI configuration lives at the repository root (one level above
`public/`), not inside the plugin folder:

```text
<repo root>/
├── .forgejo/
│   └── workflows/moodle-ci.yml     # Forgejo Moodle Plugin CI
└── public/
    └── blocks/
        └── elediaai_tutor/         # the plugin
```

- `.forgejo/workflows/moodle-ci.yml` runs on PHP 8.3. Pushes and pull requests
  run the changed groups with hard install, PHP lint and PHPUnit gates on both
  `MOODLE_405_STABLE` and `MOODLE_502_STABLE`. Tutor changes run once without
  `local_elediaai_core` and once with it to cover both optional-quota contracts.
  Nightly runs and the default manual dispatch cover `MOODLE_405_STABLE`,
  `MOODLE_501_STABLE` and `MOODLE_502_STABLE`; Behat remains a Moodle 5.2
  browser gate.
- `tests/hook_callbacks_test.php` is part of the plugin PHPUnit suite, so the
  Hooks API contract is exercised on both support boundaries.
- For publishing, the plugin folder is flattened into a GitHub mirror so that
  `README.md`, `version.php`, `db/`, `classes/` and the CI workflow sit at
  the repository root, matching the Moodle Plugins Directory layout.

## What it stores

Only lightweight conversation **pointers** (the RAG server's conversation id, a
short last-message preview, timestamps). Full transcripts are owned by the RAG
server. See [docs/privacy.md](docs/privacy.md).

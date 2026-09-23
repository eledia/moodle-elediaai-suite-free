# Manual testing: a local RAG/Tutor MCP server

`tutor_mcp_server.py` is a tiny, dependency-free MCP server you can run on your
machine to exercise `block_elediaai_tutor` end to end without a real RAG backend.

## Run it

```bash
cd blocks/elediaai_tutor/tests/manual
python3 tutor_mcp_server.py
# -> Listening on http://127.0.0.1:8765/mcp
```

Useful flags:

| Flag | Effect |
|---|---|
| `--host 0.0.0.0` | Bind on all interfaces (needed when Moodle runs in Docker — see below). |
| `--port 8765` | Bind port. |
| `--sse` | Reply as `text/event-stream` (tests the block's SSE parser). |
| `--auth-token SECRET` | Require `Authorization: Bearer SECRET` (tests the RAG auth setting). |
| `--no-moodle-callback` | Return self-contained sample answers; do **not** call back into Moodle. |
| `--verbose` | Verbose HTTP logging. |

## Tools it implements

| Tool | Block setting | Purpose |
|---|---|---|
| `tutor_chat` | Chat tool name | Answers a message; tracks turns per conversation; echoes the `ltm_enabled` consent flag. |
| `tutor_get_history` | History tool name | Returns the stored messages of a conversation. |
| `tutor_delete_conversation` | Delete tool name | Deletes a conversation and its messages. |
| `tutor_delete_user_data` | Delete user data tool name | Erases everything (all conversations + demo memory) in one call. |
| `tutor_set_memory_optin` | Memory opt-in tool name | Records long-term memory consent; opting out erases the demo memory items. |

## Point the block at it

**Site administration ▸ Plugins ▸ Blocks ▸ eLeDia.ai Tutor**

| Setting | Value |
|---|---|
| RAG MCP server URL | `http://localhost:8765/mcp` (or `http://host.docker.internal:8765/mcp` in Docker) |
| Allow insecure transport (HTTP) | **Yes** (it's plain HTTP for local dev) |
| Allow private / internal RAG host | **Yes** in Docker / on a private network (see below) |
| Chat tool name | `tutor_chat` |
| History tool name | `tutor_get_history` (optional) |
| Delete tool name | `tutor_delete_conversation` (optional) |
| Delete user data tool name | `tutor_delete_user_data` (optional) |
| Memory opt-in tool name | `tutor_set_memory_optin` (optional) |
| MCP external service | one of the services configured in `webservice_elediamcp` |

If you set `--auth-token`, also set **RAG authorization method = Bearer token**
and paste the same token into **RAG authorization token**.

Then add the **eLeDia.ai Tutor** block to a course and chat.

### Running Moodle in Docker (e.g. moodlehq/moodle-docker)

Inside the web container, `localhost` is the *container*, not your host — so:

1. Start the server on all interfaces: `python3 tutor_mcp_server.py --host 0.0.0.0`.
2. Set **RAG MCP server URL** to `http://host.docker.internal:8765/mcp`.
3. Turn on **Allow private / internal RAG host**. Moodle's cURL security helper
   blocks private IP ranges and non-standard ports by default; this per-block
   opt-in bypasses that check for the configured RAG host only (off by default,
   keep it off in production).

After changing a language string, a setting default or `version.php` you must
purge caches. In Docker:

```bash
docker exec <webserver-container> php /var/www/html/admin/cli/purge_caches.php
```

## What you'll see

- **New conversation** → the next message has no `conversation_id`, so the server
  starts a fresh thread and replies with a welcome that shows the new id.
- **Follow-up in the same conversation** → the reply is headed *"Continuing
  conversation … — turn N"* and quotes your previous message, demonstrating that
  context is kept per conversation. Two different conversations get different
  replies.
- **Delete (history trash icon)** → with **Delete tool name** configured, the
  block calls `tutor_delete_conversation`; the server log shows
  `tutor_delete_conversation conv=… removed=N message(s)` and the local pointer
  is removed too.

The server logs every call (run with `--verbose`), e.g.:

```
tutor_chat conv=conv-ab12 turn=2 course=- token=abc12…  msg='And cellular respiration?'
tutor_delete_conversation conv=conv-ab12 existed=True removed=4 message(s)
```

## What the Moodle callback proves

With the callback **enabled** (the default, i.e. *not* passing
`--no-moodle-callback`) the server takes the **user-scoped Moodle MCP token** the
block sends it and calls back into this site's MCP server at
`{system_url}/webservice/elediamcp/server.php` (Bearer auth) to fetch real data.
So asking *"Which courses am I enrolled in?"* triggers a live `moodle_my_courses`
call and the answer reflects the actual logged-in user's courses — confirming
token provisioning, the RAG call, and the MCP server all work together.

Prerequisites for the callback: web services + the MCP protocol enabled, and the
selected MCP service configured in `webservice_elediamcp`. The server process
must be able to reach the site URL the token belongs to.

## Quick curl checks (no Moodle needed)

```bash
# A chat turn.
curl -s -X POST http://127.0.0.1:8765/mcp -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"tutor_chat",
       "arguments":{"system_url":"https://m","moodle_token":"T","user_message":"hi","conversation_id":"conv-demo"}}}'

# Delete that conversation.
curl -s -X POST http://127.0.0.1:8765/mcp -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"tutor_delete_conversation",
       "arguments":{"conversation_id":"conv-demo"}}}'
```

> This server is for **manual testing only** — it is not part of the plugin
> runtime and ships under `tests/manual/`.

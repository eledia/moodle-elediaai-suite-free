# AI Chat Engine — contracts

> **Audience:** developers adding a chat surface or a chat backend to the suite
> **Component:** `local_elediaai_chatengine`
> **Status:** current as of DEL-517

The engine sits between two contracts. A **placement** is a surface that shows
a chat — a block, an activity. A **backend** is a service that answers. The
engine owns everything in between: the conversation, the safety framing, the
quota, the audit trail.

Both contracts are deliberately narrow. Everything a placement or a backend
does *not* have to implement is something that cannot drift apart between the
three surfaces the suite ships.

---

## 1. Which backend answers

There is no setting for it.

The destination that course material is written to is configured once, in
`local_elediaai_sources`. The engine reads it and answers from the matching
backend. A question is therefore always put to the service that holds the
answer.

A second setting here could disagree with the first, and the way it would
disagree is silently: an empty index looks exactly like a question the material
does not cover.

```
local_elediaai_sources          local_elediaai_chatengine
  sink: 'literag'        ──►      literag_adapter          (same id)
  sink: 'ingestionapi'   ──►      ingestionapi_adapter     (same id)
```

**A new backend arrives as a pair**: a sink over there to write with, an
adapter over here to read with, carrying one id.
`backend_resolver::paired_ids()` reports both halves and a unit test asserts
they match — so a destination added or renamed without its adapter fails
loudly instead of degrading to "no backend available", which on a live site is
indistinguishable from an unconfigured one.

### The one backend that follows no destination

`simulator_adapter` answers from a script instead of a language model, for
working on the chat surfaces: layout, streaming, source cards, error states,
the confirmation card. It has no sink and never will — nothing is ingested for
a backend that invents its answers — so it is switched on by its own admin
setting and, while that is set, it outranks the destination's adapter.

It is left out of `paired_ids()` on purpose. The pairing is a canary for a real
mistake, and a warning that fires on every site running the simulator is one
nobody reads.

Two things keep it from being mistaken for a real backend: every answer carries
a visible marker, and the health report says the simulator is active. It is off
by default, and the surfaces do not know it exists — to them it is an adapter
like any other, which is the whole point of testing against it.

### What *is* configured here

How to *reach* a backend once it has been chosen. The external RAG agent needs
its own address because it is a separate service from the ingestion pipeline:
the pipeline answers `/documents/upsert` beneath the base URL configured in
AI Sources, the agent answers `/mcp` somewhere else entirely. Deriving one from
the other would be the URL guess that DEL-516 removed.

LiteRAG needs no address at all — it runs in this site and is dispatched in
process.

---

## 2. The backend contract

`\local_elediaai_chatengine\adapter\adapter`

| Member | Purpose |
|---|---|
| `id()` | Short identifier, **identical to the sink id** this backend is written by. Stable across releases: it is recorded on every conversation. |
| `name()` | Translated name for settings pages and error messages. |
| `is_available()` | Whether the backend is installed, configured and usable **right now**. Answered without a network call: it decides whether a placement shows a chat box or a configuration notice, on every page load. |
| `capabilities()` | What the backend supports: `supportsungrounded`, `supportstools`, `supportsstreaming`, `stateful`. |
| `chat(chat_request)` | Answer one turn. |
| `call_tool(tool, arguments, userid)` | Call one of the backend's other tools: `history`, `delete`, `deleteuser`, `memoryoptin`, `recluster`. |

### Capabilities are reported, not assumed

A mode a backend does not report is **not offered** — the option disappears
from the settings form rather than turning up as a runtime surprise. If it is
somehow requested anyway, the turn is **refused, not downgraded**: downgrading
would answer the question in a way the placement did not ask for, and nobody
would be told.

### There is no automatic fallback

When the configured backend fails, the turn fails. A silent switch would hand
the user a different model past the quota ledger and past the audit trail, and
they would have no way of telling.

The one exception is a **rejected credential** (401/403): the cached callback
token may have been revoked, so it is dropped and the turn is retried exactly
once. Every other failure — a timeout, a 5xx, an unreadable answer — is not
cured by retrying, and retrying would send the same turn to the backend twice.

### Why there is no core_ai adapter

`generate_text` knows no sessions, demands the full prior context on every
call, and flattens the history into one string. That is the known injection
surface, and it bypasses the usage measurement. `aiprovider_eledia` is
unaffected and continues to serve Questiongen, Coursegen, `qtype_aitext` and
`mod_aifeedback`.

### Both current backends speak the same protocol

`local_literag` implements the same tool catalogue as the external agent
(`rag_server_spec` Part A) and validates the same `webservice_elediamcp`
tokens. The two adapters therefore differ in **transport, not protocol**:
LiteRAG is handed the JSON-RPC request in process, the agent gets it over HTTP.
A site calling itself over HTTP would need a second credential and would fail
wherever its own hostname does not resolve from inside its container.

> **Careful:** LiteRAG's dispatcher resolves tool names against **its own**
> settings. Asking the engine's setting there would let the two drift apart
> into "unknown tool".

---

## 3. The placement contract

`\local_elediaai_chatengine\placement\placement`, found by convention at
`\<component>\chatengine\placement` — the same shape the suite already uses for
`\<component>\elediaai_core\feature_provider`.

| Member | Purpose |
|---|---|
| `component()` | Frankenstyle component. Recorded on every conversation. |
| `context(instanceid)` | The context the chat happens in. |
| `courseid(instanceid)` | The course, or 0 for a site-wide chat. |
| `require_access(instanceid, userid)` | Let this person chat here, or throw. |
| `persona(instanceid)` | The voice to answer in. |
| `system_prompt_id()` | Which base prompt the backend answers inside: `tutor`, `aichat` or `elli`. |
| `mode(instanceid)` | Grounded or ungrounded. |
| `allow_tools(instanceid)` | Whether the backend may act in Moodle on this person's behalf. Travels as the `moodle_tools_enabled` argument (RAG server spec 0.28.0) -- a ban the backend must honour, not a hint it may weigh. |
| `may_clear(instanceid, userid)` | Whether this reader may still discard their own conversation here. False once a surface has taken it as work handed in -- `mod_elli` after submission. The panel hides its clear button on a false, and `clear_conversation` refuses: the endpoint is reachable without the panel. |
| `options(instanceid, userid, clienthints)` | Backend hints this surface adds, given what the client asked for; unknown keys pass through untouched. |
| `daily_limit(instanceid)` | A budget stricter than the site's, or null. |

### A hint is a wish, not an instruction

A placement's interface knows things the shared endpoint cannot infer: which
answer style a learner picked, that a starter question means "do something"
rather than "look something up", which button was pressed on a confirmation
card. They travel as **hints** — a backend may honour or ignore any of them,
and a turn without them is a complete turn.

Both entry points accept them, and both read the same vocabulary
(`\local_elediaai_chatengine\local\hints`), so the buffered call and the
streamed one cannot come to accept different things. Every name and every value
is checked against that vocabulary; anything else is **dropped silently**,
because a client one release behind should still get its answer rather than an
error it cannot act on.

What survives is then **offered to the placement, which has the final word**:

```
client asks  ──►  engine checks the vocabulary  ──►  placement decides
   answerstyle=quiz          answerstyle=quiz            answerstyle=hint
                                                         (this instance locks it)
```

A hint the placement does not answer for stays as the client asked — the
decision on a confirmation card belongs to the panel that showed it, and no
placement should have to repeat it in order to keep it.

This is the same rule the mode already follows. A client that posts something
it may not have is not refused, it is simply not asked: what the turn is
*allowed* to do — the mode, the backend, whether tools may run — is decided
here and never travels on the wire.

### Access control stays with the placement

Each surface already has the capability that fits it — `mod/elli:chat`,
`block/elediaai_tutor:use`. An engine-wide capability would give
administrators a second switch that silently disagrees with the first.

A placement is the only thing standing between a shared endpoint and a course
somebody may not read, so its gates are worth their own tests.

### What the instance id means

Whatever the placement says it means, as long as it separates conversations
that should stay separate.

| Placement | Instance id |
|---|---|
| `block_elediaai_tutor` | the **course**, 0 for the site-wide chat |
| `mod_aichat` | the activity instance |
| `mod_elli` | the scenario instance |

The tutor keys on the course rather than on the block instance because one
block serves both the course chat and the global one; with the block as the
key they would be a single thread.

### The voice and the base prompt are two questions

`persona` says how an answer should sound. `system_prompt_id` says what kind of
conversation this is, and the backend picks the base prompt it embeds the turn
into from that name — a learning assistant, a general assistant, a role-play.

They were one question until this split, and the answer was always "tutor".
Every surface was therefore answered inside the tutor's base prompt, with its
tutoring stance and its tool rules, and a scenario's role instructions arrived
as style guidance for a tutor. No amount of voice guidance turns one into the
other, which is why this could not be fixed in `persona`.

Three rules keep it honest:

- **The vocabulary is closed** (`local\system_prompt`), and it is a wire
  contract, not a Moodle naming convention. It names a *kind* of conversation,
  so two activities of the same kind share an id rather than each adding one.
- **The placement declares it, the engine does not derive it.** Deriving it from
  the component would make the engine guess at a contract it does not own, and
  a placement outside this repository could not say what it is.
- **An unknown id is answered as a tutor, never refused** — on both backends, and
  in the specification. That is what every server did before the argument
  existed, so a newer Moodle naming a surface an older server has not learned
  about still gets an answer.

It is fixed per placement today, which is why it is **not** part of the persona
fingerprint that keys a conversation. Should it ever become configurable, it
has to join that fingerprint in the same change — otherwise a stored thread
would silently continue inside a base prompt its earlier turns never saw.

### Owning a conversation and being billed for it are two questions

They coincide for a logged-in learner and come apart for a guest, who owns the
thread but has no account to issue a callback token for or to bill.
`chat_service::send()` therefore takes an optional `backenduserid` — the
account the backend acts for — separately from the `userid` that owns the
conversation.

---

## 4. The conversation store

One schema for every surface: `local_elediaai_chateng_thread` and
`local_elediaai_chateng_msg`. That is what makes the privacy provider, the
history limit and the retention task exist once instead of four times, and it
is the reason a placement can be thin at all.

Conversations are kept site-side **even when the backend holds its own
transcript**. A learner reopening a chat must not depend on an external service
being reachable, and a site that switches destination must still be able to
show what its users asked.

A thread records the backend it was created against and a fingerprint of the
persona. When either changes, a **new thread is started beside the old one** —
not continued (its earlier turns followed different instructions, and its
conversation id belongs to a service the site may no longer write to) and not
wiped (that would throw away what a person actually asked).

### Which conversation a turn lands in

A turn says one of three things, and a client that offers a "new conversation"
control has to say the third:

| The turn carries | The engine does |
| --- | --- |
| `threadid` > 0 | Resumes that conversation, after proving the caller owns it. |
| Neither | Continues the caller's most recent conversation in this placement, opening one if there is none. |
| `newthread` | Opens a fresh conversation beside the old ones, which are kept. |

Dropping the client's pointer expresses the second, not the third. A panel that
clears its own log and then sends nothing is asking to carry on: the learner
sees an empty chat while the backend is handed the whole previous conversation,
and no second conversation is ever recorded. `newthread` is ignored when
`threadid` names a conversation — naming one is asking for that one.

The conversation is opened by the turn, not by the button, so a learner who
presses "new conversation" and then closes the panel leaves no empty thread
behind.

---

## 5. Prompt safety

Everything a person typed and everything a tool returned is data. Neither is an
instruction, whatever it says about itself.

Two measures apply, deliberately independent:

1. Content is framed in explicit untrusted delimiters with a standing
   instruction to ignore commands inside them.
2. The turn-boundary markers a model recognises — special tokens, this class's
   own delimiters, line-leading role labels in English and German — are broken
   **before** the content reaches the frame.

The framing alone would be enough only if the delimiters could not be forged.
They can be typed, which is why they are removed from the content first: a
message that closes the untrusted block early would put everything after it
back into instruction position.

This is an engine property, not a placement's. A placement that forgot it would
be the one hole that matters.

---

## 6. Adding a chat surface

1. Implement `\<component>\chatengine\placement`.
2. Declare `local_elediaai_chatengine` in `version.php`. Never the other way
   round: the engine does not depend on a placement.
3. Render `local_elediaai_chatengine/chat_panel` through
   `\local_elediaai_chatengine\output\chat_panel`, or keep your own endpoints
   and call `chat_service::send()` from them — `mod_elli` does that because its
   endpoints carry guest sessions and a submission lifecycle the shared endpoint
   knows nothing about, and `block_elediaai_tutor` because its turn has to name
   the surface it came from (see below).

   Keeping your own endpoints means **both** of them. A placement that moves the
   buffered call and leaves the streamed one behind answers differently
   depending on whether streaming is switched on, which is the hardest kind of
   difference to notice. In the browser, override `methods()` for the web
   service names and `streamUrl()` for the streaming endpoint. On the server,
   `\local_elediaai_chatengine\local\stream_runner::run()` owns the headers,
   the session lock, the flushing and the frame names, so your streaming
   endpoint stays a front door rather than a second protocol.

### When one instance id is not enough

The instance id keys the **conversation**. If a placement's configuration lives
somewhere finer-grained than that, the two cannot share the one integer.

`block_elediaai_tutor` is the case: a conversation belongs to the course — its
standalone page answers for any course through `view.php?courseid=`, with no
block instance to key a thread on — while persona, mode, budget and the
answer-style lock live on the block instance. So its turn carries the rendering
context **beside** the instance id, through its own endpoints, and its placement
proves the pairing before believing it (`resolve_scoped_context()`), then keeps
it for the length of the request.

Note what did *not* happen: the instance id kept its meaning, and nothing about
the conversation moved. A placement in the same position should reach for its
own endpoint rather than for a second meaning of `instanceid` — a thread key
that changes meaning orphans every conversation already stored under it.
4. Clean up on deletion. The conversation lives in the engine, but it is still
   the placement's to remove when its instance goes: rows nobody can reach
   would keep turning up in privacy exports.

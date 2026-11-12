# Privacy documentation

The block implements the Moodle Privacy API
(`\block_elediaai_tutor\privacy\provider`).

## Stored locally in Moodle

The **conversation is not the block's**. It lives in
`local_elediaai_chatengine`, together with every other chat surface in the
suite, and that plugin's privacy provider reports, exports and erases it. The
block deliberately does not name it a second time: two providers over the same
rows would export the same turns twice and let one erase report success while
the other still held the data.

> **Note on scope:** the shared store keeps the turns themselves, not just a
> pointer. That is deliberate — a learner reopening a conversation must not
> depend on an external service being reachable. A stateful backend keeps its
> own transcript as well, addressed through an opaque conversation id.

What the block still holds itself:

- `block_elediaai_tutor_consent` — the confirmations on first use, only when
  that is switched on
- `block_elediaai_tutor_diag` — short, redacted admin diagnostics
- the long-term memory preference (a user preference)

All of it is **exported** and **deleted** by the block's privacy provider for
subject access and erasure requests, scoped to the system context.

## Privacy guidelines: provided, not acknowledged

The privacy guidelines fulfil the information duty of Art. 13 GDPR. They are
**provided**, always one click away (the shield in the chat header, under the
dashboard greeting and in the conversation footer), and by default
are **not confirmed**: no checkbox, no button, no gate before the first turn
(ai_suite#203).

Up to 1.0.12 the tutor asked every user to confirm the guidelines before the
first chat turn ("Agree and start"). Since 1.0.13 that is an admin setting,
**Require confirmation on first use** (`requireacknowledgement`), and it is
**off by default**: the GDPR does not ask for a confirmation, a confirmation in
front of the chat can be mistaken for a consent the processing does not rest
on, and a ticked box proves nothing the institution needs. Some institutions
still want a documented acknowledgement, for instance of usage rules or AI
transparency notes; they switch it on. Then:

- the panel asks for the confirmation ("Confirm and start") before the first
  turn, and the placement refuses a turn without one, server-side;
- the confirmation is documented in `block_elediaai_tutor_consent` (one row
  per user: `userid`, `timecreated`) plus the
  `\block_elediaai_tutor\event\consent_given` event;
- it is exported and erased like the rest; after "Delete all my tutor data"
  the user is asked again.

Rows already stored from earlier versions stay and are used again when the
setting is switched on.

The guidelines text shown can be replaced with institution-specific content via
the **Privacy guidelines text** admin setting (the memory opt-in and deletion
controls always remain).

The **long-term memory** opt-in is unaffected: that one is a real consent,
voluntary, off by default and revocable.

## Sent to the external RAG server

Declared as an external location (`rag_server`): your user identity (via a
user-scoped token), your message text, the course context (when provided), and
the conversation id. The RAG server stores and retains the full conversation
according to **its own** policy — document that policy separately for your data
processing records.

## MCP token metadata

The user-scoped Moodle MCP token's own metadata is owned by
`webservice_elediamcp` and is exported/erased by *that* plugin's privacy
provider. The token secret is never persisted by either plugin.

## Question analytics

Earlier releases kept opt-in question analytics in `block_elediaai_tutor_qlog`.
That table is dropped on upgrade. The question statistics now come from the
suite-wide turn store `local_elediaai_core_turn`, which records the asker only
as a pseudonymous key; it is declared, exported and erased by the privacy
provider of `local_elediaai_core`.

When the **Recluster tool name** is additionally configured, a nightly task
re-sends recent logged question texts (without user identities) to the RAG
server to converge their topic labels. This transmits no new data category —
the same question texts already transited the same processor at chat time —
and the server must not retain the batches beyond processing (see the RAG
server specification, section A.6).

## Usage counters (daily quota)

Daily message counters moved to the engine with the conversation
(`local_elediaai_chateng_usage`) — one row per user per day (`userid`,
`daykey`, `messagecount`), incremented on each successful chat turn and used
only to enforce the admin-configured daily message limit. Exported and deleted
by the privacy provider, erased by regular Moodle account deletion, pruned
after 60 days by the daily task, and removed by the explicit self-service
"Delete all my tutor data" control as part of complete erasure.

## User preferences

One user preference is stored: `block_elediaai_tutor_ltm_enabled` — the explicit
opt-in to the optional long-term memory feature. It defaults to **off**, is only
ever changed by the user themselves (audited via the *long-term memory
preference changed* event), and is declared and exported through the Privacy
API. If the configured backend supports long-term memory, the current
preference is sent as a per-request gate and existing memory is included in
external user-data deletion. Moodle stores the preference, but not the memory
content itself.

## In-product privacy controls

The chat header has a **Privacy guidelines** button that shows learners an AI
accuracy warning, what is sent and stored, the long-term memory opt-in, and a
**Delete all my tutor data** action. Deletion removes local conversation
metadata, question analytics, usage counters, diagnostics, the LTM preference
and, if stored, the confirmation on first use.
When the admin has configured a RAG delete tool, deletion is propagated to the
external server too; unsupported or failed external deletion is reported
separately and never blocks the local erase. Every request is recorded via the
*tutor data deletion requested* event with per-storage counters.

Moodle's administrator-driven Privacy API erasure paths use the same remote
deletion service. This applies to a single approved user, a batch, and
system-context deletion. Remote deletion remains best-effort so a RAG outage
cannot block erasure of the local Moodle records.

Regular Moodle account deletion uses the same complete service. The
`before_user_deleted` hook requests external erasure while the user-scoped MCP
token is still valid and removes all local stores. The later `user_deleted`
observer repeats the complete local cleanup only as a fallback if the pre-hook
did not finish. Connector and backend failures are logged in developer mode but
never block Moodle from deleting the account or the plugin from erasing its
local records.

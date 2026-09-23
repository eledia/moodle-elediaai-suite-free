# Privacy documentation

The block implements the Moodle Privacy API
(`\block_elediaai_tutor\privacy\provider`).

## Stored locally in Moodle

Since DEL-517 the **conversation is not the block's**. It lives in
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

- `block_elediaai_tutor_qlog` — opt-in question analytics (questions, never
  answers)
- `block_elediaai_tutor_consent` — the documented acknowledgement
- `block_elediaai_tutor_diag` — short, redacted admin diagnostics
- the long-term memory preference (a user preference)

All of it is **exported** and **deleted** by the block's privacy provider for
subject access and erasure requests, scoped to the system context.

## First-use consent (documented)

Before the first chat turn every user must acknowledge the privacy guidelines
in the chat panel. The guidelines text shown can be replaced with
institution-specific content via the **Privacy guidelines text** admin setting
(the memory opt-in and deletion controls always remain). The acknowledgement
is:

- **Enforced server-side** — the block's placement refuses a turn without a
  consent record before the engine sends anything, so the interface gate cannot
  be bypassed.
- **Documented** in table `block_elediaai_tutor_consent` (one row per user:
  `userid`, `timecreated`) plus an auditable
  `\block_elediaai_tutor\event\consent_given` event in the standard log.
- **Deleted with the user**: the regular Moodle account-deletion flow invokes
  the plugin's complete deletion service before and after the core
  `user_deleted` event; the privacy provider also exports the consent timestamp
  and deletes it on erasure requests. After erasure the gate re-arms and the
  user is asked again.

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

## Question analytics (opt-in)

When the administrator enables **Question analytics**, the questions learners
ask (never the answers) are stored in `block_elediaai_tutor_qlog` together with
course, asker, grounding flag and answer style. The asker id exists so privacy
export/erasure works — teacher reports never display identities. Rows are
pruned by a daily scheduled task after the configurable retention (default
180 days), are included in privacy export/delete, and are wiped by the user's
own "Delete all my tutor data" action. Collection is **off by default**.

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
metadata, question analytics, documented consent, usage counters, diagnostics
and the LTM preference. The first-use consent gate is re-armed immediately.
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

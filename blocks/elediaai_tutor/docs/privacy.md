# Privacy documentation

## Purpose

This document describes how `block_elediaai_tutor` handles personal data: data
flows, purposes, recipients, retention, deletion and open questions. It records
verifiable technical facts. Legal assessments, approvals and the choice of legal
basis remain with the responsible person or the data protection officer.

The block implements the Moodle Privacy API
(`\block_elediaai_tutor\privacy\provider`).

## Scope

- **Product:** eLeDia.ai Tutor (`block_elediaai_tutor`), release 1.0.22
- **Controller:** the institution operating the Moodle site
- **Privacy contact:** set by the operating institution
- **Systems:** Moodle (block, `local_elediaai_chatengine`,
  `local_elediaai_core`, `webservice_elediamcp`) and the connected RAG/Tutor
  service
- **State:** 2026-11-24
- **Last human review:** open (see open questions)

## Data inventory

| Processing | Data subjects | Data categories | Purpose | Legal basis | Source | Recipient | Storage | Retention / deletion |
|---|---|---|---|---|---|---|---|---|
| Chat turn | learners, teachers | message text, course id, conversation id, user identity (user-scoped token) | answer the question | to be decided by the controller | chat panel | RAG/Tutor service (external location `rag_server`) | service, per its own policy; turns in `local_elediaai_chatengine` | engine: retention setting and privacy provider; service: own policy, remote deletion best effort |
| Confirmation on first use (only when switched on) | users | `userid`, `timecreated` | documented acknowledgement of the guidelines | to be decided by the controller | privacy panel | none | `block_elediaai_tutor_consent` | until self-deletion, account deletion or privacy erasure |
| Admin diagnostics | users with failed turns | `userid`, `courseid`, `contextid`, `phase`, `errorcode`, short `detail` | troubleshooting | to be decided by the controller | failed tutor call | administrators | `block_elediaai_tutor_diag` | pruned after 30 days; erased with the user's data |
| Long-term memory preference | users | yes/no | gate for the optional long-term memory | consent (voluntary, off by default, revocable) | privacy panel | RAG/Tutor service (as `ltm_enabled`) | user preference `block_elediaai_tutor_ltm_enabled` | until changed, self-deletion or account deletion |
| Long-term memory view | users | memories and session notes held by the service | let users see what is remembered | as for the memory itself | RAG/Tutor service (`tutor_get_memory`) | the user's browser | not stored in Moodle | not applicable |
| Nightly topic assignment | learners (pseudonymous) | question texts, course name and language, existing labels with examples | topic statistics for teachers | to be decided by the controller | `local_elediaai_core_turn` | RAG/Tutor service | labels in `local_elediaai_core_turn` | service must not retain batches |
| Teacher Copilot | learners (anonymised) | topics and a sample of recent question texts | course improvement for teachers | to be decided by the controller | `local_elediaai_core` insights | RAG/Tutor service | result shown, not stored | not stored |

No passwords, tokens, keys or full personal example data belong in this
document.

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

When the backend offers topic assignment (for the RAG agent: the chat engine
setting **Regroup questions nightly**, off by default), a nightly task
sends logged question texts that have no topic yet (without user identities)
to the RAG server to assign their topic labels; with the optional 14-day full
run, all question texts of the last 30 days. Each call also carries the course
name and language and, per existing label, its count and up to three example
question texts of the same course. This transmits no new data category —
the same question texts already transited the same processor at chat time —
and the server must not retain the batches beyond processing (see the RAG
server specification of `local_elediaai_chatengine`, section A.6).

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
content itself. When learners open the memory view (#238), Moodle reads the
content back from the backend (`tutor_get_memory`, with the learner's own
token), shows it in the browser and keeps nothing of it.

## In-product privacy controls

The chat header has a **Privacy guidelines** button that shows learners an AI
accuracy warning, what is sent and stored, the long-term memory opt-in, and a
**Delete all my tutor data** action. While the chat engine offers long-term
memory, the panel also shows what the tutor has remembered (memories by
category and one note per conversation), and switching memory off asks for
confirmation first, because the backend erases the memory at once and for
good; the confirmation links to that view. Deletion removes local conversation
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

## Data subject rights and transparency

| Requirement | Implementation | Responsible | Evidence | Status |
|---|---|---|---|---|
| Access | Privacy API export of consent, diagnostics and preference; turns by the engine's provider | plugin | `tests/privacy_provider_test.php` | implemented |
| Rectification | memories are corrected through the conversation; no other editable personal data | plugin / service | `feat08` | implemented |
| Erasure | self-service "Delete all my tutor data", Privacy API erasure, account deletion hook; remote deletion best effort | plugin | `tests/deletion_service_test.php`, `tests/privacy_provider_test.php` | implemented |
| Restriction | not applicable: no processing beyond what the user starts | — | — | not applicable |
| Data portability | Privacy API export | plugin | `tests/privacy_provider_test.php` | implemented |
| Objection / withdrawal | long-term memory can be switched off at any time; switching off erases it at the service | plugin / service | `tests/ltm_test.php` | implemented |
| Privacy notice | privacy guidelines one click away; institution text via setting | institution | `feat04` | implemented |

## Risks and data protection impact assessment

| Risk / processing | Likelihood / impact | Measures | DPIA required? | Responsible | Status |
|---|---|---|---|---|---|
| External service keeps transcripts beyond Moodle's control | depends on the service | external location declared; remote deletion tools; document the service's policy in the records of processing | to be decided | controller | open |
| Long-term memory profiles learners | medium | opt-in, off by default, view and erase at any time | to be decided | controller | open |

## Open questions and decisions

| Question / decision | Reference | Decider | Due | Status |
|---|---|---|---|---|
| Legal basis per processing (column "to be decided") | `feat01`, `feat04` | controller / DPO | — | open |
| First human review of this document after the DevFlow adoption | `q04` in `04-tasks.md` | product owner | — | open |

## Review on changes and releases

- [ ] New or changed processing is listed in the data inventory.
- [ ] Privacy notices and consents are updated where needed.
- [ ] Processors and international transfers are checked.
- [ ] Erasure, export and access paths still work.
- [ ] Logs, telemetry and test data contain no unnecessary personal data.
- [ ] Open privacy risks are documented and assessed by a human.
- [ ] Relevant changes are in `CHANGES.md` and `releasenotes.md`.


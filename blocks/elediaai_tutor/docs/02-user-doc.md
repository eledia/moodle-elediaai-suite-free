# User and administration documentation

This document consolidates the formerly separate user, teacher, admin,
troubleshooting and solution-overview documents for the eLeDia.ai Tutor.

---

## Audiences

- **Learners** use the tutor as an AI-assisted chat in Moodle.
- **Teachers** place and configure tutor block instances in courses.
- **Administrators** set up RAG/MCP, limits, data protection and tutor designs
  site-wide.

---

## Product overview

The eLeDia.ai Tutor is a Moodle block for an embedded AI chat. Responses are
not generated in the browser or directly in the block, but server-side via a
configured RAG/Tutor MCP endpoint. Moodle remains the source of trust and
context: the tutor operates with the permissions of the logged-in person and
can only use content that this person is also allowed to see in Moodle.

Depending on the configuration, the tutor can appear as a course block, a docked
panel, a dialog or a fullscreen view. Tutor designs, persona, greeting,
suggested questions and answer style can be customized site-wide and, if
permitted, per block instance.

---

## Learners: using the tutor

1. Open Moodle and sign in.
2. Open a course, the dashboard or a tutor page with the eLeDia.ai Tutor.
3. Accept the data protection notice if you have not already done so.
4. Type a question into the chat field.
5. Read the answer and ask a follow-up question if needed.

Key usage notes:

- **Enter** sends a message, **Shift+Enter** inserts a line break.
- Suggested starter questions appear as chips when they are configured.
- Answer styles can switch between **Explain**, **Hints only** and
  **Quiz me**, provided the site or the block allows it.
- Answers can be copied.
- Failed answers can be retried.
- **New conversation** starts a fresh history.
- When history is enabled, the history icon opens saved conversations.
- In the data protection dialog, users can delete all of their local tutor
  data: conversations, question analytics, consent, usage counters,
  diagnostics and the long-term-memory preference. The first-use consent gate
  is shown again afterwards. External deletion remains best-effort and its
  outcome is reported separately.
- Deleting the Moodle account removes the same local tutor data automatically.
  If the external user-deletion tool is configured, Moodle requests deletion
  of transcripts and long-term memory before invalidating the account token;
  backend failures never prevent the local cleanup.
- In the plugin shell, the standalone tutor preview uses the full shell content
  width. Embedded and shell-less tutor pages keep their compact layout.
- Tutor administration pages use the wide shell layout; the help page remains
  narrower for reading prose.
- The shell's secondary action band appears in the header (Zone A), while the
  information bar below it is reserved for progress and status. Keyboard focus
  remains visibly marked on navigation, header actions and icon actions.

### Course context

Within a course, the tutor answers course-related questions, for example about
activities, materials or next steps. Outside a course it can help more
generally, for instance with Moodle navigation or visible courses. Course chat
only works where the tutor is enabled for the course and course context is
passed in.

Conversation history follows the same scope: global chat lists only global
conversations, while course conversations appear inside their course. If
course access is removed, its conversation metadata and transcripts are no
longer available through the tutor.

---

## Teachers: providing the tutor in a course

1. Turn on editing in the course.
2. Add the **eLeDia.ai Tutor** block.
3. Configure the block via the context menu.
4. Optionally apply, import or export a tutor design.
5. Check the course as a user with `block/elediaai_tutor:use`.

Key block instance settings:

| Setting | Effect |
|---|---|
| Block title | Heading of the block. |
| Display mode | Docked panel, embedded, dialog or fullscreen. |
| Pass course context | Sends the current course ID to the tutor. |
| Fixed course ID | Forces a specific course context, e.g. on the dashboard. |
| Daily message limit | Optional limit for this instance. |
| Greeting and suggested questions | Starter text and clickable starter questions. |
| Persona | Name, role, tone, audience and free-form instructions. |
| Design | Colors, surfaces, typography, message bubbles, launcher, logo and avatar. |
| History | Shows saved conversations when allowed globally. |

Which design and persona fields are visible depends on the site's governance
setting. Locked values always follow the site default.

---

## Administrators: central setup

The central configuration is located under **Site administration > Plugins >
Blocks > eLeDia.ai Tutor** and in the tutor's plugin shell.

### RAG/Tutor MCP server

| Setting | Note |
|---|---|
| RAG MCP server URL | Streamable HTTP MCP endpoint, e.g. `https://rag.example.com/mcp`. |
| RAG authorization method/token | Optional bearer token or custom header; stays server-side. |
| Chat tool name | MCP tool for chat responses, default `tutor_chat`. |
| History/Delete tools | Optional tools for history and deletion. |
| Memory opt-in tool | Optional tool for long-term memory and opt-in sync. |
| Allow insecure transport/private host | For local development or deliberately internal services only. |
| Request timeout/Streaming | Transport behavior for responses. |

### Moodle MCP token

For grounded answers the MCP call-back is **always on** — there is no off
switch. Whenever a course is answered in grounded mode, the block uses
`webservice_elediamcp` to mint a user-scoped Moodle MCP token for the configured
external service, so the RAG/Tutor backend can call back into Moodle as that
learner to fetch their courses, run Moodle AI tools, sync memory or run remote
deletion. Grounded answers therefore require both the connector and a selected
external service.

LLM-only mode is how the tutor answers **without** calling back: it performs no
retrieval, mints no token and needs neither the connector nor a selected
service. It is the separate answer mode used (per the `allowllmonly` admin gate
and the per-instance ragmode) when a course has no knowledge base. Tokens are
provisioned server-side, held briefly in the application cache and never sent to
the browser.

### Behavior and limits

Central options include global chat, course chat, maximum message length,
rate limit, daily message limit, data protection texts, question analysis,
logging verbosity and re-clustering for analysis hotspots.

Each person must accept the data protection notice before their first chat.
Details are in `privacy.md`.

### Tutor designs and profiles

Administrators manage tutor designs under **Tutors** in the plugin shell. There
you can apply, duplicate, import and export presets and your own tutor profiles
to the site or to individual block instances.

The settings include, among others:

- Colors, surfaces, lines, typography and shadows.
- Message bubbles and status states.
- Launcher button, footer, logo and avatar.
- Persona, system prompt, greeting and suggested questions.
- Governance over which values may be overridden per block instance.

---

## Moodle App

Because third-party blocks are not rendered in the Moodle App the way they are
on the web, the tutor provides its own page:

```text
https://YOURSITE/blocks/elediaai_tutor/view.php
https://YOURSITE/blocks/elediaai_tutor/view.php?courseid=N
```

`?embedded=1` uses a reduced layout without Moodle chrome. Login, enrolment,
capabilities, consent and limits apply just as they do on the web.

---

## Troubleshooting

| Symptom | Likely cause | Solution |
|---|---|---|
| Configuration problem in the block | RAG URL missing, or the course resolves to grounded mode but the connector / MCP external service is missing | Set the RAG URL; for grounded answers install `webservice_elediamcp` and select an MCP external service. (LLM-only courses need neither.) |
| Tutor service unavailable | Transport error, wrong URL or server error | Test the URL from the Moodle server's perspective; check HTTP security/curl helper. |
| Unexpected tutor response | RAG server does not return the expected MCP format | Check the server contract in `03-dev-doc.md`. |
| Repeated auth errors | Moodle MCP token invalid or service disabled | Check the MCP service, capabilities and token lifetime. |
| Messages too fast | Rate limit active | Adjust the limit in the settings. |
| Chat JS looks outdated | AMD build or Moodle cache outdated | Rebuild AMD and purge Moodle caches. |
| Course context missing | Course not indexed, course chat off or context passing disabled | Check the course block/instance, RAG ingest and the `Pass course context` setting. |

---

## Accessibility

The interface can be operated by keyboard, new answers are announced to screen
readers and reduced motion is respected. Visible focus states are part of the
UI baseline.

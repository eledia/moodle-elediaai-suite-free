# eLeDia.ai LiteRAG — Help and handbook

## Overview

eLeDia.ai LiteRAG is the Moodle-local RAG backend for the eLeDia.ai Tutor. It
stores indexed course content in the Moodle database, retrieves matching text
passages, checks Moodle visibility for the requesting user and uses an
OpenAI-compatible language model to generate grounded tutor answers with
sources.

LiteRAG is not the learner-facing chat frontend. Learners continue to use the
eLeDia.ai Tutor in courses or on the tutor page. LiteRAG works in the background
as the secure service for retrieval, conversation history, optional long-term
memory and the LLM connection.

## User value

LiteRAG makes tutor answers depend on released Moodle course content and on the
requesting learner's Moodle permissions, rather than only on general model
knowledge.

### Roles and typical tasks

| Role | Typical tasks |
|---|---|
| Learners | Ask questions in the eLeDia.ai Tutor and receive answers grounded in released course content. |
| Teachers | Release course content for indexing and check whether the tutor answers with course context. |
| Administrators | Connect LiteRAG to the LLM, RAG-Ingest, MCP and the tutor; maintain privacy, logging and retention. |

## How LiteRAG works in the tutor setup

LiteRAG connects three parts of the eLeDia.ai Tutor landscape:

| Component | Purpose |
|---|---|
| eLeDia.ai Tutor | Shows the chat in Moodle and calls the LiteRAG `tutor_chat` tool. |
| RAG-Ingest | Sends released Moodle course content to LiteRAG. |
| LiteRAG | Stores, retrieves, filters and answers questions with sources. |
| MCP | Optionally provides live Moodle tools, such as courses, assignments or deadlines. |

The typical flow:

1. RAG-Ingest reads released course content from Moodle.
2. RAG-Ingest sends text and metadata to the LiteRAG ingestion endpoint.
3. LiteRAG stores documents and chunks in Moodle tables.
4. A learner asks a question in the tutor.
5. The tutor calls the LiteRAG MCP endpoint.
6. LiteRAG retrieves relevant chunks and filters them against Moodle permissions.
7. LiteRAG sends question and context to the configured LLM.
8. The tutor displays the answer with sources in Moodle.

## Setup

Open **Site administration > Plugins > Local plugins > eLeDia.ai LiteRAG** or use the LiteRAG tile in the eLeDia.ai Tutor Plugin Shell. Header actions remain in the shell's action area, keyboard focus is visible, and content can use the configured Full Shell width.

### Connection

The **Connection** section contains endpoints and shared secrets.

| Setting | Meaning |
|---|---|
| Ingestion API key | Shared secret sent by RAG-Ingest in the `X-API-Key` header. |
| Tutor transport token | Optional additional bearer token for tutor calls. |
| Disable endpoints | Emergency switch for ingestion and tutor endpoints. |

The page shows the target URLs that must be configured in RAG-Ingest and the tutor:

```text
/local/literag/ingest.php/documents/upsert
/local/literag/mcp.php
```

### LLM

The **LLM (OpenAI-compatible)** section connects the model provider.

Important fields:

- **API base URL**, for example `https://api.openai.com/v1` or a LiteLLM proxy.
- **API key**, stored server-side in Moodle only.
- **Model**, usually a chat-completions model.
- **Temperature**, maximum output tokens and timeout.
- **Allow private/loopback LLM host** only for trusted local or Docker endpoints.

If the tutor cannot generate an answer, first check API key, base URL, model name and timeout.

### Retrieval and chunking

The **Retrieval & chunking** section controls how much content LiteRAG retrieves and sends to the model.

| Setting | Effect |
|---|---|
| Chunk size | Target size of stored text passages. |
| Chunk overlap | Overlap between chunks so context survives boundaries. |
| Candidate chunks | Number of search hits before permission filtering. |
| Context chunks | Number of chunks sent to the LLM as context. |
| LLM reranking | Optional LLM-based reordering of candidates. |
| pdftotext path | Optional Poppler path for higher-fidelity PDF extraction. |

More candidates and more context may improve answers, but increase latency and cost.

### Live Moodle tools

LiteRAG can optionally use read-only Moodle tools from MCP. This lets the tutor combine indexed course content with live data, such as:

- current courses,
- assignments and submission status,
- calendar deadlines,
- grades,
- progress and course activities.

Live Moodle tools require `webservice_elediamcp`. Read-only tools are the normal
production choice. Write tools remain bound to explicit two-step confirmation
flows and should only be enabled deliberately.

### Tool names

Tool names in LiteRAG must match the tool names configured in the tutor.

Important defaults:

| Tool | Purpose |
|---|---|
| `tutor_chat` | Generate an answer for a tutor question. |
| `tutor_get_history` | Retrieve conversation history. |
| `tutor_delete_conversation` | Delete one conversation. |
| `tutor_delete_user_data` | Delete user data. |
| `tutor_set_memory_optin` | Store long-term-memory consent. |

Change these names only when the tutor configuration is changed accordingly.

### Privacy, logging and retention

LiteRAG can be operated with minimal data retention.

Important options:

- Enable long-term memory only when the use case and consent are clearly defined.
- Keep query logging as low as possible when detailed analysis is not needed.
- Set query-log and conversation retention according to the site policy.
- After changing privacy text or backend storage, check the tutor consent flow.

The Moodle Privacy Provider exports and deletes LiteRAG data as part of Moodle privacy processes.

## Indexing course content

LiteRAG does not index courses directly from its own interface. RAG-Ingest handles that process.

Recommended flow:

1. Set the LiteRAG ingestion API key.
2. In RAG-Ingest, configure the LiteRAG ingestion URL and the same API key.
3. Release courses or activities for ingestion.
4. Run RAG-Ingest or wait for the scheduled task.
5. Check the tutor dashboard to confirm the course is indexed.
6. Ask a subject-specific test question in the course.

If a course is not indexed yet, the tutor may still answer generally but cannot provide grounded answers from that course.

## Troubleshooting

### The tutor answers without course sources

Check:

- Has the course been indexed by RAG-Ingest?
- Did documents arrive in LiteRAG?
- Does the tutor pass the course context?
- Does the requesting user have access to the course and activity?
- Are candidate and context chunk counts high enough?

### RAG-Ingest cannot send documents

Check:

- Does the ingestion URL end in `/documents/upsert`?
- Is `$CFG->slasharguments` enabled in Moodle?
- Do the ingestion API keys in RAG-Ingest and LiteRAG match?
- Is **Disable endpoints** off?
- Can the RAG-Ingest server reach LiteRAG?

### The tutor service is unavailable

Check:

- Is the LiteRAG MCP endpoint configured correctly in the tutor?
- Do tool names match in tutor and LiteRAG?
- Is the optional tutor transport token correct?
- Does the LLM connection work?
- Is the timeout sufficient, but still lower than the tutor timeout?

### Answers are slow

Possible causes:

- Too many candidate or context chunks.
- Enabled LLM reranking.
- Slow LLM endpoint.
- Live Moodle tools with many tool rounds.
- PDF text or large course content with unsuitable chunking.

Temporarily reduce candidates, context chunks or tool rounds and check latency again.

## Operations

- Purge Moodle caches after deployments.
- After changing LLM, retrieval or tool names, test one general and one course-specific question.
- Review query logging and retention against privacy requirements regularly.
- Check RAG-Ingest status and course indexing after larger course changes.
- When changing LLM provider or proxy, verify API base URL, model name and token limits.

## Further documentation

- `README.md` in the plugin for installation, CI and technical details.
- `README.de.md` as German reference.
- eLeDia.ai Tutor handbook for the chat UI and tutor profiles.
- RAG-Ingest handbook for course release and indexing workflows.

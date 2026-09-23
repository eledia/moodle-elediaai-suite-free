# Model Context Protocol help

The configuration page uses the wide suite layout. This help page remains
narrower for comfortable reading.

The eLeDia MCP plugin lets approved MCP clients and AI agents connect to Moodle
through a curated tool catalogue. Every request runs as the authenticated Moodle
user and follows Moodle's normal capability, enrolment and visibility checks.

## What administrators configure

Administrators use the MCP configuration page to choose which external services
may issue MCP tokens, define token and CORS policy, set request limits and decide
whether premium MCP tools are available through the optional premium add-on.

## Tokens

MCP clients authenticate with Moodle tokens. Treat every token like a password:
create one token per client, revoke tokens that are no longer needed and prefer
the `Authorization: Bearer` header over token values in URLs.

## Claude Desktop

The MCP page shows a ready-to-use Claude Desktop configuration snippet after a
token is created. Copy the token immediately; Moodle only shows the token value
once.

## Signing in with OAuth (optional)

When the administrator enables the OAuth 2.1 flow, a compliant MCP client can
connect without you copying a token by hand. The client sends you to a Moodle
sign-in page; after you sign in it shows a short consent screen naming the
application. If you approve, Moodle hands the client a token bound to your
account, and the client is connected.

Approving creates an ordinary MCP token, so you stay in control: open
**Preferences → MCP tokens** at any time to see the token (labelled with the
application name) and revoke it, which immediately disconnects that client.
Only sign-in-capable Moodle accounts can approve — guests cannot. If OAuth is
switched off, connect with a manually created token as described above.

## Free and premium tools

Without the premium add-on, MCP exposes the free baseline tools for identity,
course discovery, course content, resources, announcements, calendar, assignments,
grades, progress, quiz information, course search, content search and user
creation where the Moodle user has the required capability.

With the premium add-on feature `mcp_tools`, the full MCP catalogue is available,
including additional communication, forum, submission, course creation and raw
Moodle web service tools when enabled by policy. Premium write tools include
course creation, course updates, manual course enrolment and activity creation
(page, label, url, book, assignment); they use a preview step and only change
Moodle after a second call with `confirm=true`.

## Workflow prompts

MCP clients can show guided slash commands such as **Weekly overview**, **Course
health check**, **Grading session**, **Summarise course materials** and **Create
a course from a document**. The server hides a prompt when one of its required
tools is not available to the token. Selecting a prompt returns instructions to
the client; write tools still require the normal explicit preview/confirmation
step. The document for the course-creation prompt stays on the client and is
never uploaded to this web service.

## AI generation tools (eledia.ai suite)

On sites where the eledia.ai suite is installed, two additional premium tools
appear automatically: `moodle_generate_h5p` (requires `local_elediaai_h5pauthor`)
generates interactive H5P content and publishes it to the content bank or as a
course activity, and `moodle_generate_questions` (requires
`local_elediaai_questiongen`) generates quiz questions into a question bank of
the course. Both run the generation server-side through the Moodle site's
configured AI provider (Site administration > AI) and use the same preview and
`confirm=true` flow as the other write tools.

#!/usr/bin/env python3
# This file is part of Moodle - http://moodle.org/
#
# Moodle is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# Moodle is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

"""
A minimal RAG/Tutor MCP server for manually testing block_elediaai_tutor.

It speaks just enough of the MCP Streamable HTTP transport for the block's
``rag_client`` to talk to it: a single HTTP endpoint that accepts JSON-RPC 2.0
requests and answers ``tools/call`` for the ``tutor_chat`` and
``tutor_get_history`` tools (and, for convenience, ``initialize`` / ``tools/list``).

It is dependency-free (Python 3.8+ standard library only) so you can run it with::

    python3 tutor_mcp_server.py

Point the block at it via Site administration -> Plugins -> Blocks ->
eLeDia.ai Tutor:

    RAG MCP server URL : http://localhost:8765/mcp
    Allow insecure transport (HTTP) : Yes   (since this is plain http for local dev)
    Chat tool name : tutor_chat
    History tool name : tutor_get_history   (optional)

The server keeps conversations in memory and echoes a helpful, Markdown-formatted
answer. When ``--moodle-callback`` is enabled (the default), it also uses the
user-scoped ``moodle_token`` it receives to call back into this Moodle's MCP
server (``{system_url}/webservice/elediamcp/server.php``) and fold real data into
the reply -- which proves the whole token round-trip end to end.

Run ``python3 tutor_mcp_server.py --help`` for all options.
"""

import argparse
import json
import re
import sys
import urllib.request
import urllib.error
import uuid
from datetime import datetime, timezone
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

PROTOCOL_VERSION = "2025-06-18"

# In-memory conversation store: {conversation_id: [{"role": ..., "content": ...}, ...]}.
CONVERSATIONS = {}

# Demo long-term memory state (single-user dev assumption): consent flag plus a
# fake memory count so opt-out/delete visibly "erases" something.
MEMORY = {"enabled": False, "items": 0}

# Populated from CLI args in main().
CONFIG = {
    "auth_token": None,       # If set, require "Authorization: Bearer <token>".
    "sse": False,             # Reply as text/event-stream instead of JSON.
    "moodle_callback": True,  # Call back into the Moodle MCP server with the user token.
    "verbose": False,
}


def log(*args):
    """Print a timestamped log line to stdout."""
    stamp = datetime.now(timezone.utc).strftime("%H:%M:%S")
    print(f"[{stamp}]", *args, flush=True)


# --------------------------------------------------------------------------- #
#  Moodle MCP callback (optional) -- proves the moodle_token works.           #
# --------------------------------------------------------------------------- #

def call_moodle_tool(system_url, moodle_token, tool_name, arguments=None):
    """
    Invoke a tool on this Moodle's MCP server using the user-scoped token.

    Returns the decoded JSON-RPC response dict, or raises on failure.
    """
    endpoint = system_url.rstrip("/") + "/webservice/elediamcp/server.php"
    payload = {
        "jsonrpc": "2.0",
        "id": 1,
        "method": "tools/call",
        "params": {"name": tool_name, "arguments": arguments or {}},
    }
    data = json.dumps(payload).encode("utf-8")
    request = urllib.request.Request(endpoint, data=data, method="POST")
    request.add_header("Content-Type", "application/json")
    request.add_header("Accept", "application/json")
    # The Moodle MCP server prefers a Bearer token in the Authorization header.
    request.add_header("Authorization", "Bearer " + moodle_token)

    with urllib.request.urlopen(request, timeout=20) as response:
        body = response.read().decode("utf-8")
    return json.loads(body)


def extract_moodle_text(rpc_response):
    """Pull the human-readable text out of a Moodle MCP tools/call response."""
    result = (rpc_response or {}).get("result", {})
    parts = []
    for item in result.get("content", []) or []:
        if isinstance(item, dict) and item.get("type") == "text" and item.get("text"):
            parts.append(str(item["text"]))
    return "\n\n".join(parts).strip()


def derive_topic(message):
    """Derive a stable, canonical topic label from the question (demo heuristic).

    A real RAG server should derive this from retrieval (the dominant chunk's
    section/concept) so that rephrasings of the same question always map to the
    same label — that is what makes the teacher analytics hotspots useful.
    """
    lowered = message.lower()
    if any(w in lowered for w in ("photosynthes",)):
        return "Photosynthesis"
    if any(w in lowered for w in ("course", "enrol", "enroll", "kurs")):
        return "Courses & enrolment"
    if any(w in lowered for w in ("assignment", "essay", "due", "deadline", "abgabe", "aufgabe", "week")):
        return "Assignments & deadlines"
    if any(w in lowered for w in ("grade", "mark", "score", "note")):
        return "Grades & progress"
    return "General study help"


def choose_moodle_tool(message):
    """Pick a Moodle MCP tool to demonstrate, based on the user's question."""
    lowered = message.lower()
    if any(word in lowered for word in ("course", "enrol", "enroll", "kurs")):
        return "moodle_my_courses"
    if any(word in lowered for word in ("assignment", "due", "deadline", "week", "task", "homework")):
        return "moodle_my_assignments"
    if any(word in lowered for word in ("grade", "mark", "score", "result")):
        return "moodle_my_grades"
    if any(word in lowered for word in ("calendar", "event", "upcoming", "soon")):
        return "moodle_calendar_upcoming"
    return "moodle_me"


def build_answer(user_message, course_id, system_url, moodle_token, conversation_id, history):
    """Compose a friendly, Markdown-formatted answer that reflects the conversation.

    The reply differs per conversation: a brand-new thread gets a welcome, while
    a continuing thread is acknowledged by turn number and echoes the previous
    user message, so distinct conversations are visibly distinct.
    """
    sources = []
    scope = f"course #{course_id}" if course_id else "all your courses"
    short_id = conversation_id.replace("conv-", "")[:8]
    # history holds prior turns (user+assistant) before this message is appended.
    prior_user_turns = [m for m in history if m.get("role") == "user"]
    turn = len(prior_user_turns) + 1

    if turn == 1:
        parts = [
            "### \U0001F44B Hi, I'm your eLeDia.ai Tutor",
            f"This is the start of a **new conversation** (`{short_id}`), in the context of "
            f"**{scope}**. You asked:\n\n> {user_message}",
        ]
    else:
        last = prior_user_turns[-1]["content"]
        parts = [
            f"### \U0001F501 Continuing conversation `{short_id}` — turn {turn}",
            f"Earlier in *this* conversation you said:\n\n> {last}\n\nNow you ask:\n\n> {user_message}",
            "I'm keeping context across turns, which is why this reply differs from a "
            "brand-new conversation.",
        ]

    if CONFIG["moodle_callback"] and system_url and moodle_token:
        tool = choose_moodle_tool(user_message)
        log(f"  -> calling back into Moodle MCP tool '{tool}'")
        try:
            rpc = call_moodle_tool(system_url, moodle_token, tool)
            if "error" in rpc:
                err = rpc["error"]
                parts.append(
                    "I tried to look that up in Moodle, but the MCP server returned an "
                    f"error (`{err.get('code')}`: {err.get('message')}). Check that web "
                    "services and the MCP protocol are enabled and the token's service is configured."
                )
            else:
                text = extract_moodle_text(rpc) or "_(the tool returned no text content)_"
                snippet = text if len(text) <= 1500 else text[:1500] + "\n\n…(truncated)"
                parts.append(f"Here is what Moodle told me via `{tool}`:\n\n{snippet}")
                sources.append({
                    "title": f"Moodle MCP tool: {tool}",
                    "url": system_url.rstrip("/") + "/webservice/elediamcp/server.php",
                    "snippet": "Live data fetched with your user-scoped token.",
                })
        except urllib.error.HTTPError as exc:
            parts.append(
                f"I could not reach the Moodle MCP server (HTTP {exc.code}). The token may "
                "be invalid/expired, or web services / the MCP protocol may be disabled."
            )
        except Exception as exc:  # noqa: BLE001 - demo server, surface anything.
            parts.append(f"Callback to Moodle failed: `{exc}`.")
    elif turn == 1:
        parts.append(
            "I'm running in **local test mode**, so here is a sample answer to show how "
            "I look and format replies:\n\n"
            "1. I explain things **step by step**.\n"
            "2. I can reference your *courses*, *assignments* and *deadlines*.\n"
            "3. I keep the conversation in context, so you can ask follow-up questions.\n\n"
            "Here is a tiny code example, just to prove formatting works:\n\n"
            "```python\n"
            "print(\"Hello from the eLeDia.ai Tutor!\")\n"
            "```\n\n"
            "> \U0001F4A1 **Tip:** start the server with the Moodle callback enabled to have "
            "me fetch your real course data instead of this sample."
        )
        sources.append({
            "title": "eLeDia.ai – How the tutor works",
            "url": "https://eledia.de",
            "snippet": "Example source card rendered by the test MCP server.",
        })
    else:
        parts.append(
            f"_(local test mode — this is turn **{turn}** of conversation `{short_id}`, "
            f"which has **{turn - 1}** earlier exchange(s).)_"
        )

    parts.append(
        "---\n**Try asking:** *Which courses am I enrolled in?* · "
        "*What's due this week?* · *Show my grades*"
    )
    return "\n\n".join(parts), sources


# --------------------------------------------------------------------------- #
#  MCP method handlers.                                                        #
# --------------------------------------------------------------------------- #

def handle_initialize(_params):
    """Respond to the MCP initialize handshake."""
    return {
        "protocolVersion": PROTOCOL_VERSION,
        "capabilities": {"tools": {}},
        "serverInfo": {"name": "eledia-tutor-test-server", "version": "1.0.0"},
    }


def handle_tools_list(_params):
    """Advertise the two tools the block knows how to use."""
    return {
        "tools": [
            {
                "name": "tutor_chat",
                "description": "Answer a learner's question, optionally in course context.",
                "inputSchema": {
                    "type": "object",
                    "properties": {
                        "system_url": {"type": "string"},
                        "moodle_token": {"type": "string"},
                        "user_message": {"type": "string"},
                        "course_id": {"type": "string"},
                        "conversation_id": {"type": "string"},
                    },
                    "required": ["system_url", "moodle_token", "user_message"],
                },
            },
            {
                "name": "tutor_get_history",
                "description": "Return the stored messages for a conversation.",
                "inputSchema": {
                    "type": "object",
                    "properties": {
                        "system_url": {"type": "string"},
                        "moodle_token": {"type": "string"},
                        "conversation_id": {"type": "string"},
                    },
                    "required": ["conversation_id"],
                },
            },
            {
                "name": "tutor_delete_conversation",
                "description": "Delete a conversation and its stored messages.",
                "inputSchema": {
                    "type": "object",
                    "properties": {
                        "system_url": {"type": "string"},
                        "moodle_token": {"type": "string"},
                        "conversation_id": {"type": "string"},
                    },
                    "required": ["conversation_id"],
                },
            },
            {
                "name": "tutor_delete_user_data",
                "description": "Delete ALL data held for the authenticated user "
                               "(every conversation and any long-term memory). "
                               "Demo server: wipes the whole store.",
                "inputSchema": {
                    "type": "object",
                    "properties": {
                        "system_url": {"type": "string"},
                        "moodle_token": {"type": "string"},
                    },
                    "required": ["moodle_token"],
                },
            },
            {
                "name": "tutor_set_memory_optin",
                "description": "Record the user's long-term memory consent. "
                               "enabled=false also erases stored memories.",
                "inputSchema": {
                    "type": "object",
                    "properties": {
                        "system_url": {"type": "string"},
                        "moodle_token": {"type": "string"},
                        "enabled": {"type": "boolean"},
                    },
                    "required": ["moodle_token", "enabled"],
                },
            },
            {
                "name": "tutor_recluster_questions",
                "description": "Re-derive canonical topic labels for a batch of "
                               "logged questions (service-level; no user token).",
                "inputSchema": {
                    "type": "object",
                    "properties": {
                        "system_url": {"type": "string"},
                        "course_id": {"type": "string"},
                        "existing_labels": {"type": "array", "items": {"type": "string"}},
                        "questions": {"type": "array", "items": {"type": "object"}},
                    },
                    "required": ["questions"],
                },
            },
        ]
    }


def tool_tutor_chat(arguments):
    """Implement the tutor_chat tool."""
    user_message = str(arguments.get("user_message", "")).strip()
    system_url = str(arguments.get("system_url", "")).strip()
    moodle_token = str(arguments.get("moodle_token", "")).strip()
    course_id = str(arguments.get("course_id", "")).strip()
    conversation_id = str(arguments.get("conversation_id", "")).strip() or ("conv-" + uuid.uuid4().hex[:12])

    token_preview = (moodle_token[:6] + "…") if moodle_token else "(none)"
    history = CONVERSATIONS.setdefault(conversation_id, [])
    turn = len([m for m in history if m.get("role") == "user"]) + 1
    ltm = arguments.get("ltm_enabled", None)
    style = arguments.get("answer_style", "explain") or "explain"
    lang = arguments.get("user_lang", "")
    rag = arguments.get("rag_enabled", None)
    log(f"tutor_chat conv={conversation_id} turn={turn} course={course_id or '-'} "
        f"style={style} lang={lang or '-'} ltm={ltm if ltm is not None else 'absent'} "
        f"rag={rag if rag is not None else 'absent'} "
        f"token={token_preview} msg={user_message!r}")

    # Build the answer from the history *before* this turn is recorded.
    answer, sources = build_answer(user_message, course_id, system_url, moodle_token,
                                   conversation_id, history)

    # Honour LLM-only mode: no retrieval, hence no sources.
    if rag is False:
        sources = []
        answer += ("\n\n\U0001F4A1 _LLM-only mode (`rag_enabled: false`): answered from the "
                   "model's general knowledge — no course retrieval performed._")

    # Echo the pedagogical style so style switching is visible in manual tests.
    if style == "hint":
        answer += "\n\n\U0001F9ED _Hint mode: I guide you step by step and never hand over the final solution._"
    elif style == "quiz":
        answer += "\n\n❓ _Quiz mode: I respond with practice questions and check your answers._"
    if lang:
        answer += f"\n\n\U0001F310 _Requested answer language: `{lang}`._"

    # Demonstrate the per-request consent gate: only when this request carries
    # ltm_enabled=true would a real server read/write memory.
    if ltm is True:
        MEMORY["items"] += 1
        answer += ("\n\n\U0001F9E0 _Long-term memory is **active** for this request "
                   f"(demo store now holds {MEMORY['items']} item(s))._")
    elif ltm is False:
        answer += "\n\n\U0001F512 _Long-term memory is **off** for this request; nothing is remembered._"

    history.append({"role": "user", "content": user_message})
    history.append({"role": "assistant", "content": answer})

    return {
        # MCP text content (fallback path in the block's client).
        "content": [{"type": "text", "text": answer}],
        # Structured content (preferred path in the block's client).
        "structuredContent": {
            "answer": answer,
            "conversation_id": conversation_id,
            "sources": sources,
            "topic": derive_topic(user_message),
        },
        "isError": False,
    }


def tool_tutor_get_history(arguments):
    """Implement the tutor_get_history tool."""
    conversation_id = str(arguments.get("conversation_id", "")).strip()
    messages = CONVERSATIONS.get(conversation_id, [])
    log(f"tutor_get_history conv={conversation_id} -> {len(messages)} message(s)")
    return {
        "content": [{"type": "text", "text": json.dumps({"messages": messages})}],
        "structuredContent": {"messages": messages},
        "isError": False,
    }


def tool_tutor_delete_conversation(arguments):
    """Implement the tutor_delete_conversation tool."""
    conversation_id = str(arguments.get("conversation_id", "")).strip()
    existed = conversation_id in CONVERSATIONS
    removed = len(CONVERSATIONS.pop(conversation_id, []))
    log(f"tutor_delete_conversation conv={conversation_id} existed={existed} removed={removed} message(s)")
    message = (
        f"Conversation `{conversation_id}` deleted ({removed} message(s) removed)."
        if existed else
        f"Conversation `{conversation_id}` was not found (already deleted?)."
    )
    return {
        "content": [{"type": "text", "text": message}],
        "structuredContent": {"deleted": existed, "conversation_id": conversation_id},
        "isError": False,
    }


def tool_tutor_recluster_questions(arguments):
    """Implement the tutor_recluster_questions tool (demo registry: derive_topic)."""
    questions = arguments.get("questions") or []
    labels = arguments.get("existing_labels") or []
    topics = []
    for question in questions:
        if not isinstance(question, dict) or "id" not in question:
            continue
        topics.append({"id": question["id"], "topic": derive_topic(str(question.get("text", "")))})
    log(f"tutor_recluster_questions course={arguments.get('course_id', '-')} "
        f"batch={len(questions)} existing_labels={len(labels)} -> {len(topics)} label(s)")
    return {
        "content": [{"type": "text", "text": json.dumps({"topics": topics})}],
        "structuredContent": {"topics": topics},
        "isError": False,
    }


def tool_tutor_delete_user_data(arguments):
    """Implement the tutor_delete_user_data tool (demo: wipes everything)."""
    conv_count = len(CONVERSATIONS)
    mem_count = MEMORY["items"]
    CONVERSATIONS.clear()
    MEMORY["items"] = 0
    log(f"tutor_delete_user_data -> wiped {conv_count} conversation(s), {mem_count} memory item(s)")
    return {
        "content": [{"type": "text", "text":
            f"All user data deleted ({conv_count} conversation(s), {mem_count} memory item(s))."}],
        "structuredContent": {
            "deleted": True,
            "conversations_deleted": conv_count,
            "memories_deleted": mem_count,
        },
        "isError": False,
    }


def tool_tutor_set_memory_optin(arguments):
    """Implement the tutor_set_memory_optin tool."""
    enabled = bool(arguments.get("enabled", False))
    erased = 0
    MEMORY["enabled"] = enabled
    if not enabled:
        erased = MEMORY["items"]
        MEMORY["items"] = 0
    log(f"tutor_set_memory_optin enabled={enabled}"
        + (f" -> erased {erased} memory item(s)" if not enabled else ""))
    return {
        "content": [{"type": "text", "text":
            f"Memory opt-in set to {enabled}" + (f"; {erased} memory item(s) erased." if not enabled else ".")}],
        "structuredContent": {"accepted": True, "enabled": enabled, "memories_deleted": erased},
        "isError": False,
    }


def handle_tools_call(params):
    """Dispatch a tools/call to the implemented tools."""
    name = params.get("name")
    arguments = params.get("arguments", {}) or {}
    if name == "tutor_chat":
        return tool_tutor_chat(arguments)
    if name == "tutor_get_history":
        return tool_tutor_get_history(arguments)
    if name == "tutor_delete_conversation":
        return tool_tutor_delete_conversation(arguments)
    if name == "tutor_delete_user_data":
        return tool_tutor_delete_user_data(arguments)
    if name == "tutor_set_memory_optin":
        return tool_tutor_set_memory_optin(arguments)
    if name == "tutor_recluster_questions":
        return tool_tutor_recluster_questions(arguments)
    raise ValueError(f"Unknown tool: {name}")


METHOD_HANDLERS = {
    "initialize": handle_initialize,
    "tools/list": handle_tools_list,
    "tools/call": handle_tools_call,
}


# --------------------------------------------------------------------------- #
#  HTTP layer.                                                                 #
# --------------------------------------------------------------------------- #

class MCPHandler(BaseHTTPRequestHandler):
    """Handle the MCP Streamable HTTP endpoint."""

    server_version = "eledia-tutor-test/1.0"

    def log_message(self, fmt, *args):  # noqa: A003 - silence default noisy logging.
        if CONFIG["verbose"]:
            super().log_message(fmt, *args)

    def _authorised(self):
        """Enforce the optional bearer token (matches the block's RAG auth setting)."""
        if not CONFIG["auth_token"]:
            return True
        header = self.headers.get("Authorization", "")
        match = re.match(r"Bearer\s+(\S+)", header, re.IGNORECASE)
        return bool(match and match.group(1) == CONFIG["auth_token"])

    def do_GET(self):
        """A friendly hint for humans opening the URL in a browser."""
        self._send_json(200, {"ok": True, "hint": "POST JSON-RPC 2.0 here (MCP tools/call)."})

    def do_POST(self):
        if not self._authorised():
            self._send_json(401, {"jsonrpc": "2.0", "id": None,
                                   "error": {"code": -32001, "message": "Unauthorized"}})
            return

        length = int(self.headers.get("Content-Length", 0) or 0)
        raw = self.rfile.read(length) if length else b""
        try:
            request = json.loads(raw.decode("utf-8")) if raw else {}
        except (ValueError, UnicodeDecodeError):
            self._send_json(400, {"jsonrpc": "2.0", "id": None,
                                  "error": {"code": -32700, "message": "Parse error"}})
            return

        method = request.get("method")
        request_id = request.get("id")

        # Notifications (no id) -- acknowledge with 202 and no body.
        if request_id is None and isinstance(method, str) and method.startswith("notifications/"):
            self.send_response(202)
            self.end_headers()
            return

        handler = METHOD_HANDLERS.get(method)
        if handler is None:
            self._send_rpc(request_id, error={"code": -32601, "message": f"Method not found: {method}"})
            return

        try:
            result = handler(request.get("params", {}) or {})
            self._send_rpc(request_id, result=result)
        except ValueError as exc:
            self._send_rpc(request_id, error={"code": -32602, "message": str(exc)})
        except Exception as exc:  # noqa: BLE001 - demo server.
            log("ERROR:", exc)
            self._send_rpc(request_id, error={"code": -32603, "message": "Internal error"})

    # -- response helpers ---------------------------------------------------- #

    def _send_rpc(self, request_id, result=None, error=None):
        """Send a JSON-RPC envelope as either JSON or an SSE frame."""
        envelope = {"jsonrpc": "2.0", "id": request_id}
        if error is not None:
            envelope["error"] = error
        else:
            envelope["result"] = result

        if CONFIG["sse"]:
            self._send_sse(envelope)
        else:
            self._send_json(200, envelope)

    def _send_json(self, status, payload):
        body = json.dumps(payload).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def _send_sse(self, envelope):
        # Emit a throwaway progress event followed by the real JSON-RPC frame,
        # exercising the block's SSE parser (which keeps the last result frame).
        frames = (
            "event: message\ndata: " + json.dumps({"jsonrpc": "2.0", "method": "progress"}) + "\n\n"
            "event: message\ndata: " + json.dumps(envelope) + "\n\n"
        ).encode("utf-8")
        self.send_response(200)
        self.send_header("Content-Type", "text/event-stream; charset=utf-8")
        self.send_header("Cache-Control", "no-cache")
        self.send_header("Content-Length", str(len(frames)))
        self.end_headers()
        self.wfile.write(frames)


def main():
    parser = argparse.ArgumentParser(description="Minimal RAG/Tutor MCP server for block_elediaai_tutor.")
    parser.add_argument("--host", default="127.0.0.1", help="Bind host (default: 127.0.0.1).")
    parser.add_argument("--port", type=int, default=8765, help="Bind port (default: 8765).")
    parser.add_argument("--path", default="/mcp", help="Endpoint path (informational; all POSTs are handled).")
    parser.add_argument("--auth-token", default=None,
                        help="If set, require 'Authorization: Bearer <token>' (match the block's RAG auth token).")
    parser.add_argument("--sse", action="store_true",
                        help="Reply as text/event-stream (SSE) instead of JSON.")
    parser.add_argument("--no-moodle-callback", action="store_true",
                        help="Do not call back into the Moodle MCP server; return canned answers only.")
    parser.add_argument("--verbose", action="store_true", help="Verbose HTTP logging.")
    args = parser.parse_args()

    CONFIG["auth_token"] = args.auth_token
    CONFIG["sse"] = args.sse
    CONFIG["moodle_callback"] = not args.no_moodle_callback
    CONFIG["verbose"] = args.verbose

    httpd = ThreadingHTTPServer((args.host, args.port), MCPHandler)
    url = f"http://{args.host}:{args.port}{args.path}"
    log("eLeDia.ai Tutor test MCP server")
    log(f"  Listening on    : {url}")
    log(f"  Response format : {'SSE (text/event-stream)' if CONFIG['sse'] else 'JSON'}")
    log(f"  Moodle callback : {'on' if CONFIG['moodle_callback'] else 'off'}")
    log(f"  Auth required   : {'yes (bearer)' if CONFIG['auth_token'] else 'no'}")
    log("  Set the block's 'RAG MCP server URL' to the address above and enable insecure transport.")
    log("  Press Ctrl+C to stop.")
    try:
        httpd.serve_forever()
    except KeyboardInterrupt:
        log("Shutting down.")
        httpd.server_close()


if __name__ == "__main__":
    sys.exit(main())

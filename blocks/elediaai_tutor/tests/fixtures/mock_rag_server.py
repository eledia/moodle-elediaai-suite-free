#!/usr/bin/env python3
"""Minimal RAG/Tutor MCP server that streams, for manual and end-to-end testing.

Speaks just enough of the contract (rag_server_spec Part A/B) to drive the
tutor block: one JSON-RPC ``tools/call`` endpoint that answers either as a
single JSON body or, when the caller accepts ``text/event-stream``, as the
named SSE frames the eLeDia agent uses — ``token`` per fragment, ``final``
with the ordinary response, ``done`` as terminator.

Nothing here talks to a model. The answer is canned, which is the point: this
verifies how Moodle handles a stream, not what an LLM says.

Usage:
    python3 mock_rag_server.py [--port 8099] [--delay 0.1] [--mode MODE]

Modes:
    stream   default; fragments, then the final frame
    json     never streams, replies with a single JSON body
    nofinal  streams fragments and closes without a final frame (failure path)
"""

import argparse
import json
import time
from http.server import BaseHTTPRequestHandler, HTTPServer

ANSWER = "The essay is due on Friday at 23:59."
FRAGMENTS = ["The essay ", "is due ", "on Friday ", "at 23:59."]

CONFIG = {"delay": 0.1, "mode": "stream"}


def _result(conversation_id):
    """Build the structuredContent the block expects."""
    return {
        "structuredContent": {
            "answer": ANSWER,
            "conversation_id": conversation_id,
            "answer_origin": "rag",
            "topic": "Assignment 1",
            "sources": [
                {
                    "title": "Assignment 1",
                    "url": "https://example.com/mod/assign/view.php?id=1",
                    "snippet": "Submission closes Friday 23:59.",
                }
            ],
        }
    }


class Handler(BaseHTTPRequestHandler):
    """Answers tools/call, streaming when the client accepts SSE."""

    protocol_version = "HTTP/1.0"

    def log_message(self, fmt, *args):
        """Stay quiet; the test output is what matters."""
        pass

    def do_POST(self):
        """Handle one JSON-RPC tools/call."""
        length = int(self.headers.get("Content-Length", 0))
        raw = self.rfile.read(length)
        try:
            request = json.loads(raw)
        except ValueError:
            request = {}

        request_id = request.get("id", 1)
        arguments = request.get("params", {}).get("arguments", {})
        conversation_id = arguments.get("conversation_id") or "mock-conv-1"

        accepts_sse = "text/event-stream" in self.headers.get("Accept", "")
        if accepts_sse and CONFIG["mode"] != "json":
            self._stream(request_id, conversation_id)
        else:
            self._json(request_id, conversation_id)

    def _json(self, request_id, conversation_id):
        """Reply with a single JSON body (spec B.2)."""
        body = json.dumps(
            {"jsonrpc": "2.0", "id": request_id, "result": _result(conversation_id)}
        ).encode()
        self.send_response(200)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def _frame(self, event, data):
        """Write one SSE frame and push it out immediately."""
        payload = data if isinstance(data, str) else json.dumps(data)
        self.wfile.write(f"event: {event}\ndata: {payload}\n\n".encode())
        self.wfile.flush()

    def _stream(self, request_id, conversation_id):
        """Reply as named SSE frames (spec B.2.1)."""
        self.send_response(200)
        self.send_header("Content-Type", "text/event-stream; charset=utf-8")
        self.send_header("Cache-Control", "no-cache")
        self.send_header("X-Accel-Buffering", "no")
        self.end_headers()

        for fragment in FRAGMENTS:
            self._frame("token", {"delta": fragment})
            time.sleep(CONFIG["delay"])

        if CONFIG["mode"] != "nofinal":
            self._frame(
                "final",
                {"jsonrpc": "2.0", "id": request_id, "result": _result(conversation_id)},
            )
        self._frame("done", "[DONE]")
        self.close_connection = True


def main():
    """Parse arguments and serve until interrupted."""
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--port", type=int, default=8099)
    parser.add_argument("--delay", type=float, default=0.1)
    parser.add_argument(
        "--mode", choices=["stream", "json", "nofinal"], default="stream"
    )
    args = parser.parse_args()

    CONFIG["delay"] = args.delay
    CONFIG["mode"] = args.mode

    server = HTTPServer(("0.0.0.0", args.port), Handler)
    print(f"mock RAG server on :{args.port} (mode={args.mode}, delay={args.delay}s)")
    server.serve_forever()


if __name__ == "__main__":
    main()

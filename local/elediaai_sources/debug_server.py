#!/usr/bin/env python3
"""
AI Sources Debug Server — mock endpoint for local_elediaai_sources testing.

Listens on port 8001 (configurable) and logs every incoming request
in full detail: headers, payload (with base64 content truncated for
readability), and timing. Responds with 200 OK to all requests.

Usage:
    python3 debug_server.py                     # default: 0.0.0.0:8001
    python3 debug_server.py --port 9000         # custom port
    python3 debug_server.py --fail              # respond with 500 to test retry logic
    python3 debug_server.py --delay 3           # add 3s delay to test timeout handling

Configure the Moodle plugin to point at:
    http://localhost:8001/documents/upsert

Christopher Reimann, eLeDia GmbH — 2026
"""

import argparse
import base64
import json
import sys
import time
from datetime import datetime
from http.server import HTTPServer, BaseHTTPRequestHandler

# ANSI color codes for terminal output.
C_RESET = "\033[0m"
C_BOLD = "\033[1m"
C_GREEN = "\033[32m"
C_YELLOW = "\033[33m"
C_CYAN = "\033[36m"
C_RED = "\033[31m"
C_DIM = "\033[2m"

# Global config set from CLI args.
FAIL_MODE = False
RESPONSE_DELAY = 0
FULL_CONTENT = False
PREVIEW_SIZE = 500


class RAGDebugHandler(BaseHTTPRequestHandler):
    """HTTP request handler that logs full request details."""

    request_count = 0

    def do_POST(self):
        """Handle POST requests (upsert / delete)."""
        RAGDebugHandler.request_count += 1
        req_num = RAGDebugHandler.request_count
        timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S.%f")[:-3]

        # Read body.
        content_length = int(self.headers.get("Content-Length", 0))
        raw_body = self.rfile.read(content_length) if content_length > 0 else b""

        # ── Header ──────────────────────────────────────────────
        print(f"\n{'═' * 72}")
        print(f"{C_BOLD}{C_GREEN}▶ REQUEST #{req_num}{C_RESET}  "
              f"{C_DIM}{timestamp}{C_RESET}")
        print(f"{'─' * 72}")

        # ── Method & Path ───────────────────────────────────────
        print(f"  {C_CYAN}Method:{C_RESET}  {self.command}")
        print(f"  {C_CYAN}Path:{C_RESET}    {self.path}")

        # ── Headers ─────────────────────────────────────────────
        print(f"\n  {C_YELLOW}Headers:{C_RESET}")
        api_key_found = False
        for name, value in self.headers.items():
            display_value = value
            if name.lower() == "x-api-key":
                api_key_found = True
                # Show first/last 4 chars for identification.
                if len(value) > 10:
                    display_value = f"{value[:4]}...{value[-4:]} (len={len(value)})"
            print(f"    {name}: {display_value}")

        if not api_key_found:
            print(f"    {C_RED}⚠ No X-API-Key header found!{C_RESET}")

        # ── Body ────────────────────────────────────────────────
        print(f"\n  {C_YELLOW}Body ({len(raw_body)} bytes):{C_RESET}")
        try:
            payload = json.loads(raw_body)
            self._print_payload(payload)
        except (json.JSONDecodeError, UnicodeDecodeError):
            print(f"    {C_DIM}(raw, non-JSON){C_RESET}")
            preview = raw_body[:200].decode("utf-8", errors="replace")
            print(f"    {preview}")
            if len(raw_body) > 200:
                print(f"    {C_DIM}... ({len(raw_body) - 200} more bytes){C_RESET}")

        # ── Simulate delay ──────────────────────────────────────
        if RESPONSE_DELAY > 0:
            print(f"\n  {C_DIM}⏳ Delaying response by {RESPONSE_DELAY}s...{C_RESET}")
            time.sleep(RESPONSE_DELAY)

        # ── Response ────────────────────────────────────────────
        if FAIL_MODE:
            self._send_response(500, {"error": "Simulated server error"})
            print(f"  {C_RED}◀ RESPONSE: 500 (fail mode){C_RESET}")
        else:
            response_body = {"status": "ok", "request_number": req_num}
            if "/delete" in self.path:
                response_body["action"] = "deleted"
            else:
                response_body["action"] = "upserted"
            self._send_response(200, response_body)
            print(f"  {C_GREEN}◀ RESPONSE: 200 OK{C_RESET}")

        print(f"{'═' * 72}\n")

    def do_GET(self):
        """Health-check endpoint."""
        self._send_response(200, {
            "status": "ok",
            "service": "RAG Debug Server",
            "total_requests": RAGDebugHandler.request_count,
        })
        print(f"{C_DIM}GET {self.path} → 200 OK{C_RESET}")

    def _print_payload(self, payload: dict):
        """Pretty-print a JSON payload, truncating base64 content."""
        display = {}
        for key, value in payload.items():
            if key == "content" and isinstance(value, str) and len(value) > 100:
                # Decode base64 to show content.
                try:
                    decoded = base64.b64decode(value)
                    size_kb = len(decoded) / 1024
                    decoded_text = decoded.decode("utf-8", errors="replace")
                    if FULL_CONTENT:
                        display[key] = (
                            f"<base64, {len(value)} chars → {size_kb:.1f} KB decoded>\n"
                            f"          ┌─ FULL CONTENT ─────────────────────────\n"
                            + "".join(
                                f"          │ {line}\n"
                                for line in decoded_text.splitlines()
                            )
                            + f"          └─ END ({len(decoded_text)} chars) ────"
                        )
                    else:
                        preview = decoded_text[:PREVIEW_SIZE]
                        suffix = "..." if len(decoded_text) > PREVIEW_SIZE else ""
                        display[key] = (
                            f"<base64, {len(value)} chars → {size_kb:.1f} KB decoded>\n"
                            f"          Preview ({PREVIEW_SIZE} chars): {preview!r}{suffix}"
                        )
                except Exception:
                    display[key] = f"<base64, {len(value)} chars>"
            elif key == "qdrant_metadata" and isinstance(value, dict):
                display[key] = value  # Show metadata in full.
            else:
                display[key] = value

        for key, value in display.items():
            if isinstance(value, dict):
                print(f"    {C_CYAN}{key}:{C_RESET}")
                for mk, mv in value.items():
                    print(f"      {mk}: {mv}")
            elif isinstance(value, str) and "\n" in value:
                lines = value.split("\n")
                print(f"    {C_CYAN}{key}:{C_RESET} {lines[0]}")
                for line in lines[1:]:
                    print(f"          {line}")
            else:
                print(f"    {C_CYAN}{key}:{C_RESET} {value}")

    def _send_response(self, code: int, body: dict):
        """Send a JSON response."""
        response_bytes = json.dumps(body, indent=2).encode("utf-8")
        self.send_response(code)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(response_bytes)))
        self.end_headers()
        self.wfile.write(response_bytes)

    def log_message(self, format, *args):
        """Suppress default access log (we handle our own logging)."""
        pass


def main():
    parser = argparse.ArgumentParser(
        description="AI Sources Debug Server for local_elediaai_sources testing"
    )
    parser.add_argument(
        "--port", type=int, default=8001,
        help="Port to listen on (default: 8001)"
    )
    parser.add_argument(
        "--host", type=str, default="0.0.0.0",
        help="Host to bind to (default: 0.0.0.0)"
    )
    parser.add_argument(
        "--fail", action="store_true",
        help="Respond with 500 to all POST requests (test retry logic)"
    )
    parser.add_argument(
        "--delay", type=float, default=0,
        help="Add delay in seconds before responding (test timeout handling)"
    )
    parser.add_argument(
        "--full", action="store_true",
        help="Show full decoded content instead of a preview"
    )
    parser.add_argument(
        "--preview-size", type=int, default=500,
        help="Number of characters to show in content preview (default: 500)"
    )

    args = parser.parse_args()

    global FAIL_MODE, RESPONSE_DELAY, FULL_CONTENT, PREVIEW_SIZE
    FAIL_MODE = args.fail
    RESPONSE_DELAY = args.delay
    FULL_CONTENT = args.full
    PREVIEW_SIZE = args.preview_size

    server = HTTPServer((args.host, args.port), RAGDebugHandler)

    print(f"""
{C_BOLD}╔══════════════════════════════════════════════════════════════╗
║           AI Sources Debug Server                   ║
╚══════════════════════════════════════════════════════════════╝{C_RESET}

  {C_CYAN}Listening:{C_RESET}   http://{args.host}:{args.port}
  {C_CYAN}Upsert URL:{C_RESET}  http://localhost:{args.port}/documents/upsert
  {C_CYAN}Delete URL:{C_RESET}  http://localhost:{args.port}/documents/delete
  {C_CYAN}Fail mode:{C_RESET}   {'ON (500 errors)' if FAIL_MODE else 'OFF (200 OK)'}
  {C_CYAN}Content:{C_RESET}     {'FULL (show all decoded content)' if FULL_CONTENT else f'Preview ({PREVIEW_SIZE} chars)'}
  {C_CYAN}Delay:{C_RESET}       {RESPONSE_DELAY}s

  Configure in Moodle admin → Plugins → Local → AI Sources:
    Endpoint URL: http://localhost:{args.port}/documents/upsert

  Press Ctrl+C to stop.
""")

    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print(f"\n{C_YELLOW}Shutting down... "
              f"Handled {RAGDebugHandler.request_count} request(s).{C_RESET}\n")
        server.server_close()
        sys.exit(0)


if __name__ == "__main__":
    main()

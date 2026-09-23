# Test fixtures

## mock_rag_server.py

A stand-in for the RAG/Tutor MCP server, so the streaming path can be exercised
end to end without a real backend or a model.

```bash
python3 tests/fixtures/mock_rag_server.py --port 8099 --delay 0.2
```

Point the block at it (Site administration → Plugins → Blocks → eLeDia.ai |
Tutor) and allow private hosts, since it runs on a private address:

| Setting | Value |
|---|---|
| `ragserverurl` | `http://host.docker.internal:8099/mcp` (from inside moodle-docker) |
| `allowprivatenetwork` | on |
| `streamingenabled` | on |

Modes:

| `--mode` | Behaviour | Exercises |
|---|---|---|
| `stream` | fragments, then the final frame | the normal streamed turn |
| `json` | single JSON body, never streams | a server that ignores the SSE offer |
| `nofinal` | fragments, then closes | a stream that dies mid-answer |

`--delay` spaces the fragments out; anything from 0.2 s upwards makes it obvious
in the browser whether text really appears progressively or only at the end.

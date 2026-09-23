---
paths:
  - 'tests/Feature/Mcp/**'
---

# Mcp

## Call tools directly; fake contracts with anonymous classes
Test an Mcp tool by instantiating it directly and calling `handle()`: `(new SomeTool(...))->handle(new Request([...]))`, asserting on the returned `Response`/`ResponseFactory` — not by going through the MCP HTTP transport.

Double an injected contract (`RemoteExecutor`, `KnowledgeRetriever`, ...) with an inline anonymous class implementing that interface, not Mockery. Use facade fakes (`Process::fake()`, `Http::fake()`) for framework-level collaborators only.

---
paths:
  - 'resources/views/prompts/**'
---

# Prompts

## Prompt views are model-facing text, not UI
Files here are LLM system prompts rendered by agents (e.g. `PointerAgent::instructions()` renders `prompts.pointer`). They are never shown to a user.

The `resources/views/**` rules do not apply: no design-token utilities, and never wrap the text in `__()` — translating a system prompt would change model behaviour.

`resources/views/prompts` is in `.prettierignore` so the Blade formatter does not reflow the prose. Edit these as documents: keep claims accurate and sourced, since a wrong fact here makes the agent run wrong commands on a customer machine.

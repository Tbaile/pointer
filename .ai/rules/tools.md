---
paths:
  - 'app/Mcp/Tools/**'
---

# Tools

## Validate inline, mirror rules in schema(), annotate behavior
Validate tool input with `$request->validate([...])` inline inside `handle()` — no Form Request classes for Mcp tools.

Define `schema(JsonSchema $schema): array` mirroring the same fields with the fluent JsonSchema builder (`$schema->string()->description(...)->required()`, `Rule::enum()` pairs with `->enum(array_column(Enum::cases(), 'value'))`).

Annotate every Tool class with `#[Title(...)]` and `#[Description(...)]` (heredoc for multi-line), plus the behavior attributes that apply: `#[IsReadOnly]`, `#[IsDestructive(bool)]`, `#[IsIdempotent(bool)]`, `#[IsOpenWorld(bool)]`.

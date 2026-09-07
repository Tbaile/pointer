---
paths:
  - 'resources/css/tokens/**'
---

# Tokens

## Token file shape: :root light, dark media override, @theme inline bridge, @utility
One CSS file per token group. Each file, in order: declare plain `--name` custom properties on `:root` for light, override the same names under `@media (prefers-color-scheme: dark)`, bridge them into Tailwind inside `@theme inline`, then expose the group with `@utility <group>-*` reading `--value(--<group>-*)`.

Values always reference Tailwind primitives (`var(--color-cyan-700)`), never literal hex. Names and values mirror the Figma exports in `figma-tokens/`. Register every new token file in `resources/css/app.css`.

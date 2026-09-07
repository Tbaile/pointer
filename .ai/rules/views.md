---
paths:
  - 'resources/views/**'
---

# Views

## Style with semantic design-token utilities, not raw Tailwind palette
Use the project's token utilities for every color, elevation, border, ring and icon: `text-*`, `surface-*`, `elevation-*`, `border-*`, `ring-*`, `icon-*`, `divide-*` (e.g. `text-primary-neutral`, `surface-background-input`, `elevation-0`, `border-secondary`, `text-danger`).

Never use raw Tailwind palette classes like `bg-blue-600` or `text-gray-500`. If a token you need does not exist, add it in `resources/css/tokens/` rather than hardcoding a palette color.

## Wrap user-facing strings in __() with the English sentence as key
Every user-facing string goes through `__()` using the full English sentence as the key: `{{ __('Remember me') }}`, `:title="__('Sign in')"`. No dotted short keys.

English is the source language, so never create `lang/en.json` or a `lang/en/` directory — translations are added only as `lang/<locale>.json` for other locales.

---
paths:
  - 'resources/views/components/**'
---

# Components

## Anonymous Blade components only; layouts are wrapper components
Build components as anonymous Blade files under `resources/views/components/` declaring `@props([...])`. Do not create class components in `app/View/Components`.

Layouts are components too — `<x-layouts.app>` / `<x-layouts.guest>` render `{{ $slot }}` and take a `:title`. Do not use `@extends`/`@section` or `@include`.

# Before Editing

Never write code from memory of how this project or Laravel works. Before the first Write or Edit of a task, even a small one, do all three steps below, then write.

1. Load every skill that covers what you are about to touch. A change usually needs more than one, e.g. a controller with its test.
2. Open `.ai/rules/index.md`, read every rule file whose glob covers the paths in scope, and grep `.ai/rules` for the change's keywords.
3. Check every framework or package API, helper and assertion with `search-docs` before using it.

When reporting done, list the skills and rule files you loaded, and say which ones you skipped.

# Docker

The application runs only in the containers of `compose.yaml`. Run every command that touches it there, never on the host:

| Command | Run as |
| --- | --- |
| `php`, `artisan`, `composer`, `vendor/bin/*` | `docker compose exec app ...` |
| `npm`, `npx` | `docker compose exec node ...` |

Read-only `git` and `docker` itself stay on the host. If a container is not running or the command fails to reach it, stop and ask the user. Do not start containers yourself and do not fall back to the host.

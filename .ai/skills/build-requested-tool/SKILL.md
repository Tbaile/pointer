---
name: build-requested-tool
description: Turn the open tool requests agents filed through `request-tool` into real Pointer MCP tools, then rewrite the playbooks that worked around them with `run-command`. Use whenever the user asks to build the most requested tool, work through tool requests, add a NethSecurity or NethServer tool for something agents keep doing with `run-command`, or replace `run-command` steps in playbooks, even if they don't name the `tool_requests` table.
---

# Build a requested tool

Agents that lack a capability file a tool request, then fall back to `run-command`. Playbooks record those `run-command` steps. This workflow ships the missing capability as tools and cleans the playbooks up after it.

Requests and playbooks live in the production DB: read and write them through the `pointer-admin` MCP server (super-admin only), never the local DB.

The core idea: **a request names a symptom, not a tool.** "Read Threat Shield IP status and counters" did not become a `threat-shield-ip-status` tool. It became `service-status` (any init script) plus `nft-list` (any nftables object), with the log part left to the existing `read-system-log`. Small generic tools over one system command family each cover the request at hand, sibling requests, and playbook steps nobody asked about yet.

Ask decisions through AskUserQuestion, batched, with your pick first. The user decides; don't build a guess.

## 1. Pick the request

`list-tool-requests` returns the open requests, most voted first.

Ties are common. Ask which one, noting for each how many playbooks lean on `run-command` for it and whether existing tools already cover part of it.

## 2. Read the playbooks

Find every `run-command` step related to the capability with `dump-playbooks {"contains": "run-command"}`, paging with `offset`/`limit` until `total`.

List each command, what the step looks for, and which tool could serve it. This list is what the tools must answer, and it is also the edit list for step 6.

## 3. Design generic tools

Group the commands by the system interface underneath (init scripts, nftables, the system log, ubus, uci, a database CLI), not by feature. One tool per interface. Parameters choose the object (`service`, `object`/`family`/`table`/`name`), not one tool per object.

Before adding a tool:
- Check `app/Mcp/Tools/` for one that already covers the interface (`read-system-log`, `read-logs`, `get-uci-config`, `victoria-query`, ...). Extend or point to it instead of duplicating it.
- Check the real output on a test machine. Ask the user for one if none is known (e.g. a dev box reachable over `ssh root@<ip>`). Run only read-only commands there. Compare candidate commands: output size, text vs JSON, exit codes for "not found" and "not running", and whether a command writes files or does heavy work (banIP's `report` writes to /tmp and walks every set, so it was rejected).

Ask the user about the split, names, and allowed subcommands. Keep tools read-only unless they say otherwise.

## 4. Implement

Read `.ai/rules/index.md` and the rules for `app/Mcp/Tools/**` and `tests/**` first, plus the `mcp-development` skill. `app/Mcp/Tools/NethSecurity/ServiceStatus.php`, `NftList.php` and `ReadSystemLog.php` are the reference shape:

- `php artisan make:mcp-tool <Product>/<Name> --no-interaction`, then fill in. A single ubus call extends `NsApiTool` instead.
- Inject `RemoteExecutor`; validate inline; mirror every field in `schema()` with a description and an example value.
- The command is built by the tool, never passed in. Whitelist verbs with `in:`, restrict names with a regex like `^[A-Za-z0-9_.-]+$`, and `escapeshellarg()` every value.
- Default to the compact form when the full output can explode: `nft -t` hides set elements (16 KB vs 4.9 MB), and logs return the last N lines. Make the big form opt-in.
- Exit codes: when the exit code is the answer (`service x running`), return `Response::structured(['exit_code', 'output'])`. When non-zero means the caller asked for something wrong (unknown nft table), return `Response::error`. An executor exception is always `Response::error`.
- Description: what it returns, which product it works on, how to read the output, and a pointer to the tool for the next level of analysis (`service-status` points to `victoria-query` on `procd_service_running` for history).
- Attributes `#[Title]`, `#[IsReadOnly]`, `#[IsOpenWorld(false)]`. Register the tool in `app/Mcp/Servers/PointerServer.php` next to its siblings.
- Tests: `php artisan make:test --pest Mcp/Tools/<Product>/<Name>Test`. Use an anonymous `RemoteExecutor` that records the command, as in `ReadSystemLogTest.php`. Assert the exact command per input shape, rejection of bad or unsafe input (with the executor never called), and error mapping.
- Run `vendor/bin/pint --dirty --format agent`, the new tests, `composer types:check`, then `composer test`. Then run the exact command strings the tests assert on the test machine.

## 5. Rewrite the playbooks

Replace each `run-command` step a tool now serves, in every playbook, not only the ones tied to the request. Include log greps that `read-system-log` already covered. Keep the "look for" text accurate for the new output. For example, a `grep -c` count became non-zero `counter packets` on drop rules. Watch for semantics that change: `grep -m 3` returns the first matches, `read-system-log` the last ones.

Leave alone the steps no tool covers yet, and the write commands in Fix sections.

The tools ship only after a deploy, so rewrite the playbooks once the user confirms the new tools are live in production. Apply the edits with `update-playbook`, one call per playbook: the whole new body plus the `body_sha1` from `dump-playbooks` as `base_body_sha1`, leaving out title and tags so they stay as they are (verdict and reviewer always do). Make each edit as exact old/new string pairs on the dumped body, each old string occurring once. A playbook with pending proposals is refused: ask the user how to settle them with `settle-playbook-proposal` first. Re-dump the touched playbooks afterwards and check them.

## 6. Close the requests

Close every request the new tools cover with `close-tool-requests`, including siblings beyond the one picked (`service-status` also closed "read a service's live state via its init script"). Confirm the list with the user first.

## 7. Report

Report the tools and their files, which playbook steps changed and which stayed on `run-command` and why, the closed requests, and the commands you ran with their results. Don't commit.

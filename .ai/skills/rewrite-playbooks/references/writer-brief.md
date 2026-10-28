# Brief: write playbook bodies for a rewrite run

Fill in `<run-dir>` and the task keys before handing this to a subagent.

---

You are rewriting playbooks for Pointer, an MCP server that lets AI agents diagnose Nethesis machines (NethSecurity firewalls, NethServer 8 clusters). Playbooks are markdown runbooks that agents get back from `search-playbooks` and follow on a live machine. Your tasks are in `<run-dir>/PLAN.md` under the keys you were given.

Of the Pointer MCP tools, call only `pointer-admin` `dump-playbooks` and `list-playbook-proposals`, which are read-only. Don't edit anything outside `<run-dir>/bodies/`. Write only the files of your own tasks: other agents write the rest in parallel.

Read first:
1. `<run-dir>/PLAN.md`: the decisions, the global rules of this run, and your tasks.
2. `.ai/skills/rewrite-playbooks/references/writing-rules.md`: how a playbook must read.
3. `app/Mcp/Tools/RecordPlaybook.php`: the base layout.
4. The current body of each playbook you touch: `dump-playbooks {"ids": [<ids>]}`, and its pending proposals from `list-playbook-proposals {"status": "pending"}`.
5. For every tool a step calls, its class in `app/Mcp/Tools/**`: the tool name and the exact argument names come from there. If the plan asks for a step no tool can do, keep it on `run-command` and say so in your reply.

For each task write:
- `<run-dir>/bodies/<key>.md`: the whole body, starting with `# <title>` (same as the JSON title).
- `<run-dir>/bodies/<key>.json`: `{"action": "update", "id": <id>, "base_body_sha1": "<from PLAN.md>", "system_type": "<...>", "title": "<...>", "tags": [...]}`. For a new playbook: `{"action": "create", "system_type": "<...>", "title": "<...>", "tags": [...]}`.

Tags: lowercase kebab-case, 1 to 10, reusing existing ones where they fit (the `tags` returned by `dump-playbooks`).

Cross-references use the exact title another playbook will have after this run: take it from the "Titles after the run" table in `PLAN.md`.

Keep what is right in the current body. The plan says what to change; don't rewrite steps it doesn't mention unless they break a writing rule, and say in your reply when you did.

Reply with at most 10 lines: the files written, anything in the plan you couldn't do and why, and any claim you marked `Unverified:` beyond the ones the plan listed.

## Review

The review agent reads `PLAN.md`, `writing-rules.md` and every file in `<run-dir>/bodies/`, and checks:

- every task in `PLAN.md` has both files, and every JSON is valid with the right action, id and `base_body_sha1`
- titles in JSON and in the `# ` line match, and every cross-reference names a title in the "Titles after the run" table
- tool names and arguments exist in `app/Mcp/Tools/**`
- no customer data (names, hostnames, domains, IPs, MACs, usernames, SOS ids, serials) and no secrets
- the claims the plan lists as unverified carry `Unverified:`
- no leftover `run-command` where a tool exists

It fixes small issues in place (typos, a wrong argument name, a stale cross-reference) and lists anything bigger in its reply, by key, without rewriting the body.

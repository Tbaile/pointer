# Brief: analyze Pointer diagnostic sessions

Fill in `<out-dir>`, `<batch>` and the session list before handing this to a subagent.

---

Pointer is an MCP server that gives AI agents read access to remote Nethesis machines (NethSecurity firewalls, OpenWrt based; NethServer 8 clusters) for support diagnosis. Agents search playbooks (markdown runbooks stored in a DB), investigate, then record a new playbook, upvote one, or propose a change to one.

Read-only task: of the Pointer MCP tools, call only `pointer-admin` `dump-playbooks` and `list-playbook-proposals`, and don't touch the repo.

- `dump-playbooks`: the current playbooks (id, tags, body). Page through all of them (`offset`/`limit` until `total`), not just the first page.
- `list-playbook-proposals`: proposed changes, with status, paged the same way.
- `<out-dir>/sessions/<date>_<id>.md`: condensed transcripts. `## USER` is the technician, `## ASSISTANT` the AI, `-> CALL pointer:<tool> {args}` a tool call, `-> RESULT:` its truncated output. Tools were renamed over time (e.g. `record-diagnosis` became `record-playbook`, `tcp-probe` became `nsec-tcp-probe`).

The tools available now are the classes in `app/Mcp/Tools/**` of the Pointer repo (each `#[Description]` says what it does). A `run-command` step a tool now covers should be rewritten to that tool.

For each session assigned to you, read it fully and look for:

1. Start vs end: what the technician first reported (point A) and the root cause found (point B). Would a future agent starting from symptom A find the playbook that holds B? Do its title, tags and Problem match how A was phrased?
2. Where the AI went wrong or needed help: the technician corrected it, gave missing info, rejected a command, or redirected it; the AI hit dead ends, guessed, misread output, or wrote something wrong into a playbook. Quote the technician briefly, without customer data.
3. Playbook quality: wrong steps, steps that led nowhere but got recorded, decisive steps missing, machine-specific values, wrong Fix sections, guesses stated as facts, duplication with other playbooks, generic how-to content that belongs in a how-to playbook.
4. Playbook usage: what `search-playbooks` returned and whether it helped, misled or was ignored; what got upvoted.
5. Tool gaps: each `run-command` and the tool that would replace it now, or "no tool".
6. Security: secrets or credentials printed by a tool or a command.

Don't invent anything. If the transcript is truncated and you can't tell, say so.

Write `<out-dir>/analysis/<batch>.md`, one section per session:

```
## <session file>
- Point A (reported): ...
- Point B (root cause): ... (say if unproven)
- Playbooks touched: searched/found [ids], recorded [id], upvoted [ids], proposed [ids]
- AI mistakes / technician corrections: ...
- Playbook issues (playbook id + the exact text that is wrong or missing): ...
- run-command -> tool replacements: ...
- Security: ...
- Suggested playbook changes: concrete edits (add/remove/rewrite step X in N; merge N into M; split; retitle; retag; new playbook)
```

Then reply with at most 15 lines: the file path and the 3-5 most important findings.

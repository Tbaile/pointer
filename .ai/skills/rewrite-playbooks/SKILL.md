---
name: rewrite-playbooks
description: Review and rewrite the Pointer playbooks stored in the database, using the Claude Code transcripts of past Pointer investigations as evidence. Covers backing up the DB, extracting the sessions that used Pointer tools, analyzing them with subagents, planning merges/splits/retitles/new playbooks, writing the new bodies, and applying them to production through the `pointer-admin` MCP server. Use it whenever the user asks to clean up, compress, merge, audit, fix or rewrite playbooks, review what agents recorded through Pointer, apply pending playbook proposals in bulk, or continue a rewrite run under `.ai/skills/rewrite-playbooks/runs/`, even if they don't say "skill".
---

# Rewrite playbooks

Playbooks (with their tags and proposals) live in the production Pointer DB and are written by agents at the end of an investigation through `record-playbook` and `propose-playbook-change`. Over time they drift: titles name the cause the agent found instead of the symptom the technician reported, guesses get recorded as facts, the same symptom ends up in two playbooks, `run-command` steps stay after a dedicated tool ships, and corrections the technician gave in chat never reach the text. This skill turns the transcripts back into evidence and rewrites the playbooks against it.

All reads and writes go through the `pointer-admin` MCP server (super-admin only): `dump-playbooks`, `list-playbook-proposals`, `settle-playbook-proposal`, `update-playbook`, `create-playbook`, `delete-playbook`. Never the local DB, which isn't production.

The work is split so cheap models can do the bulk of it. A run lives in `runs/<YYYY-MM-DD>/`: the plan is written once, Sonnet subagents write the bodies in parallel, and the lead applies them with the write tools. Nothing is written until the apply step, and the apply step needs the user's go.

Ask the user through AskUserQuestion, batched, with your pick first, whenever a decision is theirs (what to merge, what to drop, whether a guess survives). Don't build a guess.

## Continuing an existing run

If `runs/` has a run whose `PLAN.md` lists tasks without a matching `bodies/<key>.md`, continue it: skip to step 5. Read `PLAN.md` first; its "Decisions" section is what the user already decided, don't ask again.

## 1. Back up the DB

Ask the user to back up the production Postgres and to confirm when it's done. Don't go on without it: the writes in step 6 can't be undone otherwise.

## 2. Collect the evidence

```bash
.ai/skills/rewrite-playbooks/scripts/extract-sessions.sh <out-dir>/sessions
```

Use the session scratchpad as `<out-dir>`, never the repo: the transcripts hold customer data. The script writes one condensed transcript per session that called at least 5 Pointer tools, named `<date>_<session-id-prefix>.md`, and prints which playbooks each session recorded, upvoted or proposed. Sessions where the user was building Pointer itself (tool development, not a machine investigation) show up too: skip them.

Read the current playbooks with `dump-playbooks` and the proposals with `list-playbook-proposals`, paging with `offset`/`limit` until `total`. The subagents read them the same way.

## 3. Analyze the sessions

Fan the transcripts out to Sonnet subagents (`model: sonnet`), 3-5 sessions or ~200 KB each, all in one message. Give each the brief in `references/analysis-brief.md` with the paths filled in. Each writes `analysis/<batch>.md` in the scratchpad and returns a short summary.

Subagents sometimes read only part of the playbooks: when one says so, cross-check its claims about later playbooks yourself.

## 4. Plan

Read every analysis file, then draft the changes per playbook against `references/writing-rules.md`. Present them to the user grouped by kind (merge, split, retitle, unverified claims, content fixes, new playbooks, proposals) and ask the open decisions in one AskUserQuestion batch. Typical ones:

- merged playbooks: delete the absorbed row, or keep it
- unproven claims: mark them `Unverified:`, or drop them
- which lost cases (investigations that never reached a playbook) become new ones

Then write `runs/<date>/PLAN.md` following the shape of an existing run: decisions, global rules for this run, one task per playbook (key, action, base id, base body hash, what to change and why, which evidence), the titles every playbook will have after the run, the agent groups, and what was left out. Write `runs/<date>/manifest.json` with the deletes and the proposal statuses (format below). The plan goes into the repo, so it must not contain customer data, hostnames, IPs, SOS ids, usernames or secrets: describe findings with placeholders.

Security problems seen in the transcripts (a tool printing a secret, an agent dumping credentials) go in the plan's "Out of scope" section and get reported to the user. They are not fixed as part of this skill.

## 5. Write the bodies

One Sonnet subagent per group in `PLAN.md`, all in one message, prompted with `references/writer-brief.md` and the group's task keys. Each writes, per task:

- `runs/<date>/bodies/<key>.md`: the full playbook body in markdown, starting with `# <title>`
- `runs/<date>/bodies/<key>.json`: the metadata

```json
{"action": "update", "id": 16, "base_body_sha1": "17b9672e7084", "system_type": "nethsecurity", "title": "...", "tags": ["wan-down", "mwan3"]}
{"action": "create", "system_type": "nethsecurity", "title": "...", "tags": ["..."]}
```

`base_body_sha1` is the `body_sha1` `dump-playbooks` returned at planning time, listed in `PLAN.md`: `update-playbook` refuses to overwrite a body that changed since (upvotes don't count, they only touch `uses_count`).

Then one review subagent over all bodies (`references/writer-brief.md`, "Review" section): cross-references point at titles that exist after the run, no customer data or secrets, tool names and arguments match `app/Mcp/Tools/**`, every task in `PLAN.md` has its two files. Fix what it reports before applying.

## 6. Apply

Show the user the diff first (`git diff --stat` on the run dir, plus the before/after of any playbook they ask about), and apply only on their go.

Check everything before the first write: both files present per task, body starts with `# <title>`, 1 to 10 tags matching `Playbook::TAG_PATTERN`, title at most 255 chars, every pending proposal on an updated or deleted playbook listed in `manifest.json`. Re-run `dump-playbooks` and compare each `body_sha1` with the plan, so a changed body stops the run before anything is written.

Then apply in this order, one call per entry:

1. `settle-playbook-proposal` for every entry in `manifest.json` `proposals`
2. `update-playbook` for every `update` task, with the body file's content (trimmed, one trailing newline), its title, tags and `base_body_sha1`
3. `create-playbook` for every `create` task
4. `delete-playbook` for every entry in `deletes`, with `merged_into` when it has one

Each write is applied right away, there's no transaction across calls. When one is refused, stop and report what was already applied and what's left. Updates keep the id, `verdict` and `verified_*`. Deleting a playbook cascades its proposals and tag links.

Afterwards re-dump the touched playbooks and spot-check a few against their body files. Rolling back is restoring the backup from step 1: ask the user to do it.

## manifest.json

```json
{
  "deletes": [{"id": 23, "merged_into": 16, "reason": "merged into 16"}],
  "proposals": [{"id": 13, "status": "accepted", "review_note": "Folded into the run rewrite of 21."}]
}
```

`merged_into` is set only when the playbook was merged into another one, which gets its `uses_count`. Proposals are reviewed by the account connected to `pointer-admin`. Every pending proposal on a playbook the run updates or deletes must be listed, so nobody's proposal is overwritten or dropped silently. Proposals on a deleted playbook are deleted with it.

## 7. Report

State the run dir, what was applied (counts per action), the commands run and their output, what was left out and why, and the out-of-scope findings. Don't commit.

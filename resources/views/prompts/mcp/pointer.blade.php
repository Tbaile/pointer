Diagnose the machine behind this Pointer session and the problem I report on it, carrying the investigation through to a conclusion.

You reach the machine only through the Pointer MCP tools. Every command runs on the remote machine through Pointer, never on this computer: do not use your local shell, file tools or web fetches for anything about the machine, including tool output that was saved to a local file because it was too long — run a narrower call instead. If a Pointer tool is missing or fails, stop and tell me instead of falling back to local commands. Before concluding that a tool is missing, load the full list of Pointer tools.

Reply in the language I write in.

First read my request and extract two things: the SOS id of the machine and the problem I describe. Pass that exact SOS id as `sos_id` to every tool that takes one. If my request has no SOS id, ask me for it and do nothing else until I give it. If it has more than one, ask which machine to investigate, unless I said what each one is for.

Then call `what-is-it`, alone. It tells you which product the machine runs. Do not run any other command or search any documentation before you have its answer — the commands, package manager, paths and documentation that apply all depend on it. If it returns an error, the machine is not something Pointer can handle: stop and report the error exactly as returned. Use its answer as `system_type` for every tool that takes one.

If my request describes no problem, tell me which product the machine runs and ask what to investigate. If it leaves out a fact that decides where to look, like when the problem happened or which client or address is affected, ask for it once before digging. Otherwise, do not stop after `what-is-it`: continue straight into the investigation.

1. Call `list-playbook-tags`, then `search-playbooks` with the tags that fit the problem, before searching documentation or running any command, and open the ones whose title matches with `get-playbook`. Playbooks are leads: run the checks whose conditions hold on this machine and judge the results yourself. Search again when you need to do something on the machine that a playbook may already explain, like reading logs or querying a database.
2. Search the documentation of the product the machine runs, to learn how the affected feature works and how it is meant to be configured: `search-nsec-documentation` for `nethsecurity`, `search-ns8-documentation` for `nethserver`, plus `search-nethvoice-documentation` when the problem concerns NethVoice or telephony. Search again whenever a finding raises a question the documentation can answer, and before you suggest turning a feature off, to know what it protects. Never search the documentation of a product the machine does not run.
3. Inspect the machine with the Pointer tools that apply to its product, checking each hypothesis against what the machine actually shows. Before each group of calls, tell me in one line what you are checking and why.
4. Report what you found, in the format below, then stop and wait for me.

While inspecting:

- Tool calls run one at a time on the machine, each with a time limit: two captures never overlap, so do not compare them as if they were simultaneous.
- Prefer the tools that keep history (`victoria-query`, the log tools) over live commands when the problem happened in the past.
- Keep log searches to the time window of the problem and to patterns that match only the service involved. Do not page through logs call after call: narrow the pattern or the window instead.
- An empty result proves something only if the source would have recorded it: check the log level, the retention and the time window before you read silence as "it never happened". A zero counter, an empty `find` or a truncated listing rules nothing out on its own.
- When a playbook or the documentation describes how a check must be run, run it that way: a test run from the wrong place proves nothing.
- Never print secrets: avoid commands and config reads that show private keys, passwords or tokens. When a tool output shows one anyway, do not repeat it and tell me it was exposed.

Report format:

- **Cause**: one or two sentences, opening with how sure you are: confirmed (the machine shows it), probable (the evidence points to it but a check is missing) or guess.
- **Evidence**: what you observed, each item with the tool that showed it. Keep what you inferred apart and say it is inferred.
- **Fix**: the steps for me to apply, least invasive first. You only diagnose: say the fix is not applied, and for each step that changes the configuration, restarts a service or lowers protection, what it affects and how to undo it.
- **Not checked**: what is still open, including any part of my request you did not get to, and the check or fact from me that would settle it.
- **Docs**: the documentation pages that back a claim above, with their URL exactly as returned. If none did, say so.

Write about the machine and the facts, not about the people involved: do not guess what someone meant to do. Leave Pointer bookkeeping (playbook and tool request ids) out of the report.

After the report, list what you would save to the playbooks and ask me whether to go ahead:

- `upvote-playbook` for each playbook whose checks you ran and that helped.
- `propose-playbook-change` when a playbook you followed lacked a step that helped or had one that proved wrong. Prefer this to recording a new playbook that covers the same problem.
- `record-playbook` when no playbook covered the problem, and for a reusable how-to the investigation had to work out.

Save only after I agree, and only what I confirmed or the machine showed: a cause I did not confirm and a fix nobody applied stay out of the body, at most in the `reason` of a proposal. When no check helped, save nothing. Strip personal and customer data from what you save, as the `record-playbook` description says, so the playbook can be reused on any machine. If I correct you after you saved, propose the correction with `propose-playbook-change`.

Do not rely on your own memory or notes: do not read them to guide the investigation and do not save anything to them. What you learn, including the corrections I give you along the way, belongs in the playbooks, where every agent can reuse it.

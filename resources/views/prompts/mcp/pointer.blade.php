Diagnose the machine behind this Pointer session and the problem I report on it, carrying the investigation through to a conclusion.

You reach the machine only through the Pointer MCP tools. Every command runs on the remote machine through Pointer, never on this computer: do not use your local shell or file tools for anything about the machine. If a Pointer tool is missing or fails, stop and tell me instead of falling back to local commands.

First read my request and extract two things: the SOS id of the machine and the problem I describe. Pass that exact SOS id as `sos_id` to every tool that takes one. If my request has no SOS id, ask me for it and do nothing else until I give it.

Then call `what-is-it`. It tells you which product the machine runs. Do not run any other command or search any documentation before you have its answer — the commands, package manager, paths and documentation that apply all depend on it. If it returns an error, the machine is not something Pointer can handle: stop and report the error exactly as returned. Use its answer as `system_type` for every tool that takes one.

If my request describes no problem, tell me which product the machine runs and ask what to investigate. Otherwise, do not stop after `what-is-it`: continue straight into the investigation.

1. Call `list-diagnosis-tags` and then `search-diagnoses` with the tags that fit the problem. Past cases are leads: verify them on this machine before relying on them.
2. Search the documentation of the product the machine runs, to learn how the affected feature works and how it is meant to be configured: `search-nsec-documentation` for `nethsecurity`, `search-ns8-documentation` for `nethserver`, plus `search-nethvoice-documentation` when the problem concerns NethVoice or telephony. Search again whenever a finding raises a question the documentation can answer. Never search the documentation of a product the machine does not run.
3. Inspect the machine with the Pointer tools that apply to its product, checking each hypothesis against what the machine actually shows. Base the conclusion on what you observed, not on what the documentation or past cases say should be there.
4. Report the cause you found, the evidence behind it, and the fix, citing the documentation pages you relied on.

When the investigation of a problem ends — fixed, handed off, or given up — call `record-diagnosis` once for it, also when you found nothing. Strip personal and customer data from what you record, as its description says, so the case can be reused on any machine.

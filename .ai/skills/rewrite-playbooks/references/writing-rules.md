# Playbook writing rules

These come from reviewing what went wrong in real Pointer sessions. They add to the layout in `app/Mcp/Tools/RecordPlaybook.php`, which stays the base format (`# title`, `## Problem` or `## Purpose`, `## Checks` or `## Steps`, `## Fix`).

`search-playbooks` returns up to 5 full bodies into the agent's context, ranked by confirmed verdict, then matching tags, then uses. Every line costs every future investigation, so a playbook earns its length only with checks that discriminate.

## Find it from the symptom

The agent searching is at point A: what the technician reported. The playbook was written at point B: the cause. Write it so A finds it.

- Title: the symptom, in the words a technician or an alert uses (`Root <n> MiB free of <size>` alert, `ServiceDown` for X, "tunnel between two sites down", "high load and slow browsing"). Add the cause after a comma only when it tells two playbooks with the same symptom apart.
- Problem: the symptom first, including the other phrasings seen ("Also reported as ..."), and what the technician typically already tried.
- Tags: symptom and component tags (`high-load`, `wan-down`, `banip`). How-to tags like `nethsecurity-logs` or `ns8-logs` belong only to the how-to playbooks, not to every playbook that reads a log.
- When several playbooks share a symptom, each one says in its first check when to switch to the others, by exact title. A short triage playbook can route a common symptom (e.g. "high load") to the right one.

## Only what the evidence shows

- A claim the session did not prove gets `Unverified:` in front, plus what would confirm it ("Unverified: the CPE does not route the firewall's WAN IP. Confirm by testing a PC with the same static IP."). That covers causes reached by elimination, fixes that were never applied, code behavior that was assumed, and generalizations from one machine ("often", "usually").
- A fix that was applied and checked says so in one plain line, no marker.
- Numbers seen on one machine are examples ("e.g. ~100 new conn/s"), not thresholds.
- Steps that led nowhere don't go in. Dead ends that a future agent is likely to repeat go in as one-line warnings ("`calendar.events_icalendars` is empty for these events: read the ICS from the invite mail").
- Corrections the technician gave in chat go in. They are the knowledge the agent didn't have.

## Tools first

- Use the dedicated tool for every step one exists for (`read-system-log`, `service-status`, `nft-list`, `conntrack-list`, `process-list`, `network-state`, `disk-usage`, `file-checksums`, `kernel-counters`, `ha-status`, `mwan3-status`, `wireguard-status`, `list-modules`, `module-containers`, `module-environment`, `read-logs`, `audit-log`, `cluster-action`, `firewall-status`, the `nsec-*` and `ns8-*` probes). Check the tool's `schema()` in `app/Mcp/Tools/**` for its exact name and arguments: don't write arguments from memory.
- `run-command` stays only where no tool exists. Keep the command short, and say what it reads.
- `read-system-log {"source": "storage"}` scans every weekly file and can time out: narrow the pattern with a month or day prefix, and prefer VictoriaMetrics for anything over a few days.
- `run-command` runs one call at a time with a 30 s cap: captures or probes that must overlap are run by the technician by hand, and the playbook says so.
- A VictoriaMetrics step is a complete `victoria-query` call, not a metric name.

## Ask what the machine can't tell

When the investigation needed something only the technician knows (the username, the device IP and zone, when it last failed, the local time zone of an alert), the first check says to ask for it.

## No customer data, no secrets

- Placeholders for every customer value (`<wan-ip>`, `<username>`, `<customer-domain>`, `<controller-module>`), as `record-playbook` requires.
- Never write a step that sends the machine's credentials somewhere by hand (e.g. `curl -u <system_id>:<secret>`).

## Compact

- One step, one bullet: the call, then `look for:` with what a bad result looks like.
- A step whose answer is in another playbook refers to it by exact title instead of repeating it.
- No background paragraphs the checks don't need. A mechanism the fix depends on gets two or three sentences under `## Background`, nothing more.
- No machine dates, versions or counts in the text unless they identify a known bug ("adblock 4.5.5-r5").

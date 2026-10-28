#!/usr/bin/env bash
# Write a condensed transcript of every Claude Code session of this project that called
# at least MIN_CALLS Pointer tools, and print what each one did to the playbooks.
set -euo pipefail

out=${1:?usage: extract-sessions.sh <out-dir>}
min_calls=${MIN_CALLS:-5}
transcripts=${TRANSCRIPTS:-$HOME/.claude/projects/$(pwd | sed 's#[/.]#-#g')}
mkdir -p "$out"

for file in "$transcripts"/*.jsonl; do
    calls=$(jq -r 'select(.type == "assistant") | .message.content[]? | select(.type == "tool_use") | .name' "$file" 2>/dev/null | grep -c '^mcp__pointer__' || true)
    [ "$calls" -lt "$min_calls" ] && continue

    id=$(basename "$file" .jsonl)
    name="$(stat -c %y "$file" | cut -c1-10)_${id:0:8}.md"

    jq -r '
        def trunc(n): if length > n then .[0:n] + " …[+\(length - n)]" else . end;
        if .type == "user" then
            (.message.content
                | if type == "string" then "## USER\n" + trunc(3000)
                  else (.[]
                      | if .type == "tool_result" then "-> RESULT: " + ((.content | if type == "array" then map(.text? // "") | join("\n") else tostring end) | trunc(1500))
                        elif .type == "text" then "## USER\n" + (.text | trunc(3000))
                        else empty end)
                  end)
        elif .type == "assistant" then
            (.message.content[]?
                | if .type == "text" then "## ASSISTANT\n" + (.text | trunc(2500))
                  elif .type == "tool_use" then "-> CALL " + (.name | sub("mcp__pointer__"; "pointer:")) + " " + ((.input | tostring) | if test("\"body\"") then . else trunc(1200) end)
                  else empty end)
        else empty end' "$file" > "$out/$name" 2>/dev/null

    recorded=$(grep -oE 'Playbook [0-9]+ recorded' "$out/$name" | grep -oE '[0-9]+' | paste -sd, || true)
    upvoted=$(grep -oE '^-> CALL pointer:upvote-playbook \{("[a-z_]+":"[a-z]+",)?"(playbook_)?id":[0-9]+' "$out/$name" | grep -oE '[0-9]+$' | paste -sd, || true)
    proposed=$(grep -oE '^-> CALL pointer:propose-playbook-change \{("[a-z_]+":"[a-z]+",)?"(playbook_)?id":[0-9]+' "$out/$name" | grep -oE '[0-9]+$' | paste -sd, || true)
    echo "$name calls=$calls recorded=[$recorded] upvoted=[$upvoted] proposed=[$proposed]"
done

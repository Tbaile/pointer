<?php

namespace App\Mcp\Tools;

use App\Enums\Permission;
use App\Enums\SystemType;
use App\Models\Playbook;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Title('Record Playbook')]
#[IsDestructive(false)]
#[IsIdempotent(false)]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Record a new playbook. A playbook is either how to diagnose a problem (the checks that helped investigate it) or how to do something on the system that other investigations can reuse (e.g. read the logs of a module, query an app database).
    Call it when the investigation ends, only when no playbook returned by `search-playbooks` covers it; otherwise upvote it with `upvote-playbook` or propose an extension with `propose-playbook-change`. Include only the steps that moved the investigation forward, not the ones that led nowhere. When a step relies on a how-to that already exists as a playbook, refer to it by title instead of restating its commands; when the investigation had to work out a reusable how-to, record it as its own playbook.

    Write `body` in markdown, without repeating the title. A problem playbook is a short decision tree: the checks that tell its causes apart, and the fix for each cause.

    ```
    ## Symptom
    <what the user sees, in their terms, in one or two lines>

    ## Checks
    1. `<tool> {<arguments>}`
       - <result> → <cause>
       - <result> → step 3
    2. `<tool> {<arguments>}`
       - <result> → <cause>

    ## Fix
    - <cause>: <what to do>
    ```

    A how-to has a `## Steps` list instead: the calls to make, in order, and how to read their output.

    Each check names the tool and its arguments (without the SOS id), or the command run with `run-command`, and the results that point to a cause or to the next step. Leave out checks whose result would not change the next step.
    Write only what a machine showed or the user confirmed, never guesses or what this particular case looked like. Don't explain how a tool behaves or fails: its description does that. Keep the body under about 2500 characters.
    - `tags`: short lowercase kebab-case labels for the problem, e.g. `dns-resolution`, `adblock`. Call `list-playbook-tags` first and reuse existing tags when they fit; only invent a new one when none does.
    - Write every field so the playbook applies to any machine: strip personal and customer data, also inside quoted errors and commands. That covers names of people and companies, email addresses, phone numbers, usernames, hostnames, domain names, IP and MAC addresses, serial numbers and secrets. Replace each with a placeholder naming its role, e.g. `<customer-domain>`, `<wan-ip>`, `<lan-subnet>`, `<username>`. Keep the technical details that make the problem recognizable: error messages, versions, service, option and interface names, ports.
    DESCRIPTION)]
class RecordPlaybook extends Tool
{
    /**
     * Determine whether the tool is offered to the user.
     */
    public function shouldRegister(Request $request): bool
    {
        $user = $request->user();

        return $user instanceof User && $user->can(Permission::ManagePlaybooks);
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'system_type' => ['required', Rule::enum(SystemType::class)],
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'tags' => 'required|array|min:1|max:10',
            'tags.*' => 'string|max:50|regex:'.Playbook::TAG_PATTERN,
        ]);

        $playbook = DB::transaction(function () use ($validated, $request): Playbook {
            $playbook = Playbook::create([
                'user_id' => $request->user()?->getAuthIdentifier(),
                'system_type' => $validated['system_type'],
                'title' => $validated['title'],
                'body' => $validated['body'],
            ]);

            foreach (array_unique($validated['tags']) as $name) {
                $playbook->tags()->attach(Tag::firstOrCreate(['name' => $name]));
            }

            return $playbook;
        });

        return Response::text("Playbook {$playbook->id} recorded.");
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'system_type' => $schema->string()
                ->enum(array_column(SystemType::cases(), 'value'))
                ->description('The product the machine runs, as returned by `what-is-it`.')
                ->required(),
            'title' => $schema->string()
                ->description('The problem the playbook investigates or the task it explains, e.g. "DNS resolution not working" or "NS8: read module logs".')
                ->required(),
            'body' => $schema->string()
                ->description('The playbook in markdown, following the layout in the tool description.')
                ->required(),
            'tags' => $schema->array()
                ->items($schema->string()->pattern(trim(Playbook::TAG_PATTERN, '/')))
                ->min(1)
                ->max(10)
                ->description('Lowercase kebab-case labels for the problem or task, reusing existing ones from `list-playbook-tags`.')
                ->required(),
        ];
    }
}

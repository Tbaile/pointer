<?php

namespace App\Mcp\Tools;

use App\Enums\SystemType;
use App\Models\Playbook;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Title('Propose Playbook Change')]
#[IsDestructive(false)]
#[IsIdempotent(false)]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Propose a new body for a playbook found with `search-playbooks`, when it lacked a step that helped this investigation or one of its steps proved wrong. The playbook stays as it is until a technician accepts the proposal.
    Send the whole body: keep its existing steps and add or fix only what this investigation showed, following the guidance and the anonymization rules of `record-playbook`.
    In `reason`, name the check you added or changed and what it revealed. Skip the proposal when `search-playbooks` already lists a pending one with the same reason.
    DESCRIPTION)]
class ProposePlaybookChange extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'system_type' => ['required', Rule::enum(SystemType::class)],
            'id' => ['required', 'integer', Rule::exists(Playbook::class, 'id')->where('system_type', $request->get('system_type'))],
            'body' => 'required|string',
            'tags' => 'array|min:1|max:10',
            'tags.*' => 'string|max:50|regex:'.Playbook::TAG_PATTERN,
            'reason' => 'required|string',
        ]);

        $playbook = Playbook::query()->whereKey($validated['id'])->firstOrFail();

        $proposal = $playbook->proposals()->create([
            'user_id' => $request->user()?->getAuthIdentifier(),
            'body' => $validated['body'],
            'tags' => isset($validated['tags']) ? array_values(array_unique($validated['tags'])) : null,
            'reason' => $validated['reason'],
            'base_body' => $playbook->body,
        ]);

        return Response::text("Proposal {$proposal->id} recorded for playbook {$playbook->id}.");
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
            'id' => $schema->integer()
                ->description('The id of the playbook, as returned by `search-playbooks`.')
                ->required(),
            'body' => $schema->string()
                ->description('The whole proposed body in markdown, existing steps included.')
                ->required(),
            'tags' => $schema->array()
                ->items($schema->string()->pattern(trim(Playbook::TAG_PATTERN, '/')))
                ->min(1)
                ->max(10)
                ->description('The whole proposed set of tags. Omit to keep the current ones.'),
            'reason' => $schema->string()
                ->description('The step you added or changed and what it revealed.')
                ->required(),
        ];
    }
}

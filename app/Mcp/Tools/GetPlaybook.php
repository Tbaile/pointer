<?php

namespace App\Mcp\Tools;

use App\Enums\SystemType;
use App\Models\Playbook;
use App\Models\PlaybookProposal;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Title('Get Playbook')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Read a playbook found with `search-playbooks`: its body and its `pending_proposals`, changes other investigations proposed and a technician has not reviewed yet.
    Open only the playbooks whose title matches the problem.
    DESCRIPTION)]
class GetPlaybook extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'system_type' => ['required', Rule::enum(SystemType::class)],
            'id' => ['required', 'integer', Rule::exists(Playbook::class, 'id')->where('system_type', $request->get('system_type'))],
        ]);

        $playbook = Playbook::query()->with(['tags', 'pendingProposals'])->whereKey($validated['id'])->firstOrFail();

        return Response::structured([
            'id' => $playbook->id,
            'title' => $playbook->title,
            'tags' => $playbook->tagNames(),
            'body' => $playbook->body,
            'uses_count' => $playbook->uses_count,
            'verdict' => $playbook->verdict?->value,
            'updated_at' => $playbook->updated_at?->toDateString(),
            'pending_proposals' => $playbook->pendingProposals->sortByDesc('id')->values()->map(fn (PlaybookProposal $proposal): array => [
                'id' => $proposal->id,
                'reason' => $proposal->reason,
                'created_at' => $proposal->created_at?->toDateString(),
            ])->all(),
        ]);
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
        ];
    }
}

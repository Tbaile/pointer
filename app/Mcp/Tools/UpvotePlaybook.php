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

#[Title('Upvote Playbook')]
#[IsDestructive(false)]
#[IsIdempotent(false)]
#[IsOpenWorld(false)]
#[Description('Add one to the use count of a playbook found with `search-playbooks`, when its checks helped this investigation. Call it at most once per playbook in a session.')]
class UpvotePlaybook extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'system_type' => ['required', Rule::enum(SystemType::class)],
            'id' => ['required', 'integer', Rule::exists(Playbook::class, 'id')->where('system_type', $request->get('system_type'))],
        ]);

        $playbook = Playbook::whereKey($validated['id'])->firstOrFail();
        $playbook->increment('uses_count');

        return Response::text("Playbook {$playbook->id} now used {$playbook->uses_count} times.");
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

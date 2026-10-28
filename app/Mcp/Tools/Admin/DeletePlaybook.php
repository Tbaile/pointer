<?php

namespace App\Mcp\Tools\Admin;

use App\Models\Playbook;
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

#[Title('Delete Playbook')]
#[IsDestructive]
#[IsIdempotent(false)]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Delete a playbook, along with its proposals and tag links. When it was merged into another playbook, pass that one as `merged_into`: it gets this playbook's uses count added.
    Refused while the playbook has pending proposals: settle them first with `settle-playbook-proposal`.
    DESCRIPTION)]
class DeletePlaybook extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', Rule::exists(Playbook::class, 'id')],
            'merged_into' => ['nullable', 'integer', 'different:id', Rule::exists(Playbook::class, 'id')],
        ]);

        return DB::transaction(function () use ($validated): Response {
            $playbook = Playbook::query()->whereKey($validated['id'])->lockForUpdate()->firstOrFail();

            if ($playbook->pendingProposals()->exists()) {
                return Response::error("Playbook {$playbook->id} has pending proposals, settle them first.");
            }

            if (isset($validated['merged_into'])) {
                Playbook::query()->whereKey($validated['merged_into'])->increment('uses_count', $playbook->uses_count);
            }

            $playbook->delete();

            return Response::text("Playbook {$playbook->id} deleted.");
        });
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The id of the playbook to delete, as returned by `dump-playbooks`.')
                ->required(),
            'merged_into' => $schema->integer()
                ->description('The id of the playbook that absorbed this one, e.g. 16.'),
        ];
    }
}

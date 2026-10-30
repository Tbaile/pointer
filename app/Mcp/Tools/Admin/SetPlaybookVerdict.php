<?php

namespace App\Mcp\Tools\Admin;

use App\Actions\SetPlaybookVerdict as SetPlaybookVerdictAction;
use App\Enums\PlaybookVerdict;
use App\Models\Playbook;
use App\Models\User;
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

#[Title('Set Playbook Verdict')]
#[IsDestructive(false)]
#[IsIdempotent]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Set your verdict on a playbook: `confirmed` when its checks and fix are right, `rejected` when they are wrong, `unverified` to clear it. You are recorded as the one who verified it.
    DESCRIPTION)]
class SetPlaybookVerdict extends Tool
{
    public function __construct(private readonly SetPlaybookVerdictAction $setPlaybookVerdict) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', Rule::exists(Playbook::class, 'id')],
            'verdict' => ['required', Rule::in([...array_column(PlaybookVerdict::cases(), 'value'), 'unverified'])],
            'note' => 'nullable|string|max:1000',
        ]);

        $reviewer = $request->user();

        if (! $reviewer instanceof User) {
            return Response::error('Only an authenticated user can set a verdict.');
        }

        $playbook = Playbook::query()->whereKey($validated['id'])->firstOrFail();

        $this->setPlaybookVerdict->handle($playbook, $reviewer, PlaybookVerdict::tryFrom($validated['verdict']), $validated['note'] ?? null);

        return Response::text("Playbook {$playbook->id} {$validated['verdict']}.");
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
                ->description('The id of the playbook, as returned by `dump-playbooks`.')
                ->required(),
            'verdict' => $schema->string()
                ->enum([...array_column(PlaybookVerdict::cases(), 'value'), 'unverified'])
                ->description('The verdict, `unverified` to clear it.')
                ->required(),
            'note' => $schema->string()
                ->description('Why, e.g. "Commands tested on an 8.8.0 unit." Dropped with `unverified`.'),
        ];
    }
}

<?php

namespace App\Mcp\Tools;

use App\Enums\SystemType;
use App\Models\Tag;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
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

#[Title('List Playbook Tags')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description('List the tags used so far on playbooks, most used first. Use them to search playbooks and reuse them when recording a new one.')]
class ListPlaybookTags extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'system_type' => ['nullable', Rule::enum(SystemType::class)],
        ]);

        $systemType = $validated['system_type'] ?? null;

        $playbooksOfSystem = fn (Builder $playbookQuery) => $playbookQuery
            ->when($systemType, fn (Builder $query) => $query->where('system_type', $systemType));

        $tags = Tag::query()
            ->whereHas('playbooks', $playbooksOfSystem)
            ->withCount(['playbooks as uses' => $playbooksOfSystem])
            ->orderByDesc('uses')
            ->orderBy('name')
            ->get();

        return Response::structured([
            'tags' => $tags->map(fn (Tag $tag): array => [
                'tag' => $tag->name,
                'uses' => (int) $tag->getAttribute('uses'),
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
                ->description('Only list tags of playbooks for this product.'),
        ];
    }
}

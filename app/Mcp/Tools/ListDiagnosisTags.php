<?php

namespace App\Mcp\Tools;

use App\Enums\SystemType;
use App\Models\DiagnosisTag;
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

#[Title('List Diagnosis Tags')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description('List the tags used so far in the diagnosis history, most used first. Use them to search past diagnoses and reuse them when recording a new one.')]
class ListDiagnosisTags extends Tool
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

        $tags = DiagnosisTag::query()
            ->select('tag')
            ->selectRaw('count(*) as uses')
            ->when($systemType, fn (Builder $query) => $query
                ->whereHas('diagnosis', fn (Builder $diagnosisQuery) => $diagnosisQuery->where('system_type', $systemType)))
            ->groupBy('tag')
            ->orderByDesc('uses')
            ->orderBy('tag')
            ->toBase()
            ->get();

        return Response::structured([
            'tags' => $tags->map(fn (object $row): array => [
                'tag' => $row->tag,
                'uses' => (int) $row->uses,
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
                ->description('Only list tags of diagnoses on this product.'),
        ];
    }
}

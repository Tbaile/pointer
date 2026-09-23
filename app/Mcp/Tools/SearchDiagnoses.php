<?php

namespace App\Mcp\Tools;

use App\Enums\DiagnosisConfidence;
use App\Enums\DiagnosisVerdict;
use App\Enums\SystemType;
use App\Models\Diagnosis;
use App\Models\DiagnosisCheck;
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

#[Title('Search Past Diagnoses')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Search the history of past diagnoses for cases similar to the current one, on the same product.
    Call it once the user has described the problem, before investigating in depth: a past case may point straight to the cause, or to checks that already proved useless.

    Match by `tags` (take them from `list-diagnosis-tags`) and/or by free text in `query`, matched against symptoms and conclusions.
    Cases a technician confirmed come first, then the ones sharing the most tags. Cases a technician rejected are never returned.
    A past case is a lead, not a proof: verify it on this machine before relying on it.
    DESCRIPTION)]
class SearchDiagnoses extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'system_type' => ['required', Rule::enum(SystemType::class)],
            'tags' => ['required_without:query', 'array'],
            'tags.*' => ['string'],
            'query' => ['required_without:tags', 'string', 'max:200'],
            'limit' => ['integer', 'min:1', 'max:20'],
        ]);

        $tags = $validated['tags'] ?? [];
        $text = $validated['query'] ?? null;

        $diagnoses = Diagnosis::query()
            ->with(['checks', 'tags'])
            ->where('system_type', $validated['system_type'])
            ->where(fn (Builder $query) => $query
                ->whereNull('verdict')
                ->orWhere('verdict', '!=', DiagnosisVerdict::Rejected))
            ->where(function (Builder $query) use ($tags, $text): void {
                if ($tags !== []) {
                    $query->orWhereHas('tags', fn (Builder $tagQuery) => $tagQuery->whereIn('tag', $tags));
                }

                if ($text !== null) {
                    $query->orWhere(fn (Builder $textQuery) => $textQuery
                        ->whereLike('user_symptoms', "%{$text}%")
                        ->orWhereLike('machine_symptoms', "%{$text}%")
                        ->orWhereLike('conclusion', "%{$text}%"));
                }
            })
            ->withCount(['tags as matching_tags_count' => fn (Builder $tagQuery) => $tagQuery->whereIn('tag', $tags)])
            ->orderByRaw('case when verdict = ? then 0 else 1 end', [DiagnosisVerdict::Confirmed->value])
            ->orderByDesc('matching_tags_count')
            ->orderByRaw('case confidence when ? then 0 when ? then 1 else 2 end', [
                DiagnosisConfidence::High->value,
                DiagnosisConfidence::Medium->value,
            ])
            ->latest()
            ->limit($validated['limit'] ?? 5)
            ->get();

        return Response::structured([
            'diagnoses' => $diagnoses->map(fn (Diagnosis $diagnosis): array => [
                'id' => $diagnosis->id,
                'recorded_at' => $diagnosis->created_at?->toDateString(),
                'tags' => $diagnosis->tags->pluck('tag')->all(),
                'user_symptoms' => $diagnosis->user_symptoms,
                'machine_symptoms' => $diagnosis->machine_symptoms,
                'conclusion' => $diagnosis->conclusion,
                'resolution' => $diagnosis->resolution,
                'outcome' => $diagnosis->outcome->value,
                'confidence' => $diagnosis->confidence->value,
                'verdict' => $diagnosis->verdict?->value,
                'checks' => $diagnosis->checks->map(fn (DiagnosisCheck $check): array => [
                    'tool' => $check->tool,
                    'arguments' => $check->arguments,
                    'finding' => $check->finding,
                    'result' => $check->result->value,
                ])->all(),
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
            'tags' => $schema->array()
                ->items($schema->string())
                ->description('Tags describing the problem. Required when `query` is omitted.'),
            'query' => $schema->string()
                ->description('Free text matched against past symptoms and conclusions. Required when `tags` is omitted.'),
            'limit' => $schema->integer()
                ->min(1)
                ->max(20)
                ->description('Maximum number of cases to return. Defaults to 5.'),
        ];
    }
}

<?php

namespace App\Mcp\Tools\Admin;

use App\Enums\SystemType;
use App\Models\Playbook;
use App\Models\Tag;
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

#[Title('Create Playbook')]
#[IsDestructive(false)]
#[IsIdempotent(false)]
#[IsOpenWorld(false)]
#[Description('Create a playbook, recorded as yours and not yet verified.')]
class CreatePlaybook extends Tool
{
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

            $playbook->tags()->sync(array_map(fn (string $name): int => Tag::firstOrCreate(['name' => $name])->id, array_unique($validated['tags'])));

            return $playbook;
        });

        return Response::text("Playbook {$playbook->id} created.");
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
                ->description('The product the playbook is for.')
                ->required(),
            'title' => $schema->string()
                ->description('The title, e.g. "DNS resolution not working".')
                ->required(),
            'body' => $schema->string()
                ->description('The whole body in markdown.')
                ->required(),
            'tags' => $schema->array()
                ->items($schema->string()->pattern(trim(Playbook::TAG_PATTERN, '/')))
                ->min(1)
                ->max(10)
                ->description('Lowercase kebab-case tags, e.g. ["dns-resolution", "adblock"].')
                ->required(),
        ];
    }
}

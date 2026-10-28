<?php

namespace App\Mcp\Tools\Admin;

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

#[Title('Update Playbook')]
#[IsDestructive]
#[IsIdempotent]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Replace the body of a playbook, and its title and tags when given. The verdict and who verified it stay as they are.
    Refused when the body changed since `base_body_sha1` was read with `dump-playbooks`, or while the playbook has pending proposals: settle them first with `settle-playbook-proposal`.
    DESCRIPTION)]
class UpdatePlaybook extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', Rule::exists(Playbook::class, 'id')],
            'base_body_sha1' => 'required|string|size:12',
            'title' => 'string|max:255',
            'body' => 'required|string',
            'tags' => 'array|min:1|max:10',
            'tags.*' => 'string|max:50|regex:'.Playbook::TAG_PATTERN,
        ]);

        return DB::transaction(function () use ($validated): Response {
            $playbook = Playbook::query()->whereKey($validated['id'])->lockForUpdate()->firstOrFail();

            if (substr(sha1($playbook->body), 0, 12) !== $validated['base_body_sha1']) {
                return Response::error("Playbook {$playbook->id} changed since it was read, dump it again.");
            }

            if ($playbook->pendingProposals()->exists()) {
                return Response::error("Playbook {$playbook->id} has pending proposals, settle them first.");
            }

            $playbook->title = $validated['title'] ?? $playbook->title;
            $playbook->body = $validated['body'];
            $playbook->save();

            if (isset($validated['tags'])) {
                $playbook->tags()->sync(array_map(fn (string $name): int => Tag::firstOrCreate(['name' => $name])->id, array_unique($validated['tags'])));
            }

            return Response::text("Playbook {$playbook->id} updated.");
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
                ->description('The id of the playbook, as returned by `dump-playbooks`.')
                ->required(),
            'base_body_sha1' => $schema->string()
                ->description('The `body_sha1` `dump-playbooks` returned for the body this update starts from, e.g. "17b9672e7084".')
                ->required(),
            'title' => $schema->string()
                ->description('The new title. Omit to keep the current one.'),
            'body' => $schema->string()
                ->description('The whole new body in markdown.')
                ->required(),
            'tags' => $schema->array()
                ->items($schema->string()->pattern(trim(Playbook::TAG_PATTERN, '/')))
                ->min(1)
                ->max(10)
                ->description('The whole new set of lowercase kebab-case tags, e.g. ["wan-down", "mwan3"]. Omit to keep the current ones.'),
        ];
    }
}

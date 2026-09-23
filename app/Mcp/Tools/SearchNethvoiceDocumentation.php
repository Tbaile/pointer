<?php

namespace App\Mcp\Tools;

use App\Contracts\KnowledgeRetriever;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Throwable;

#[Title('Search NethVoice Documentation')]
#[IsReadOnly]
#[IsOpenWorld]
#[Description('Search the NethVoice documentation for information relevant to a natural-language query, via semantic retrieval over kapa.ai.')]
class SearchNethvoiceDocumentation extends Tool
{
    public function __construct(private readonly KnowledgeRetriever $retriever) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $query = $request->string('query');

        try {
            $chunks = $this->retriever->retrieve($query);
        } catch (Throwable $exception) {
            return Response::text("Documentation search failed: {$exception->getMessage()}");
        }

        if ($chunks === []) {
            return Response::text('No documentation results found for this query.');
        }

        return Response::text(collect($chunks)
            ->map(fn (array $chunk, int $index): string => sprintf(
                "<result number=\"%d\" url=\"%s\">\n%s\n</result>",
                $index + 1,
                $chunk['source_url'],
                $chunk['content'],
            ))
            ->implode("\n"));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('The natural-language question or topic to search the NethVoice documentation for.')
                ->required(),
        ];
    }
}

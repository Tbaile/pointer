<?php

namespace App\Ai\Tools;

use App\Contracts\KnowledgeRetriever;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

class SearchNsecDocumentation implements Tool
{
    public function __construct(private readonly KnowledgeRetriever $retriever) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Search the NethSecurity documentation for information relevant to a natural-language query, via semantic retrieval over kapa.ai.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $query = $request->string('query');

        try {
            $chunks = $this->retriever->retrieve($query);
        } catch (Throwable $exception) {
            return "Documentation search failed: {$exception->getMessage()}";
        }

        if ($chunks === []) {
            return 'No documentation results found for this query.';
        }

        return collect($chunks)
            ->map(fn (array $chunk, int $index): string => sprintf(
                "<result number=\"%d\" url=\"%s\">\n%s\n</result>",
                $index + 1,
                $chunk['source_url'],
                $chunk['content'],
            ))
            ->implode("\n");
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('The natural-language question or topic to search the NethSecurity documentation for.')
                ->required(),
        ];
    }
}

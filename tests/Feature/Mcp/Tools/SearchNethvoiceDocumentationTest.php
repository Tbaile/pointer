<?php

use App\Contracts\KnowledgeRetriever;
use App\Mcp\Tools\SearchNethvoiceDocumentation;
use Laravel\Mcp\Request;

function fakeNethvoiceRetriever(array $chunks = [], ?Throwable $throws = null): KnowledgeRetriever
{
    return new class($chunks, $throws) implements KnowledgeRetriever
    {
        public function __construct(private array $chunks, private ?Throwable $throws) {}

        public function retrieve(string $query): array
        {
            if ($this->throws) {
                throw $this->throws;
            }

            return $this->chunks;
        }
    };
}

test('it formats retrieved chunks as numbered result tags', function () {
    $tool = new SearchNethvoiceDocumentation(fakeNethvoiceRetriever([
        ['source_url' => 'https://docs.example.com/a', 'content' => 'First chunk.'],
        ['source_url' => 'https://docs.example.com/b', 'content' => 'Second chunk.'],
    ]));

    $result = $tool->handle(new Request(['query' => 'how do I configure a phone extension?']));

    expect((string) $result->content())->toBe(
        "<result number=\"1\" url=\"https://docs.example.com/a\">\nFirst chunk.\n</result>\n".
        "<result number=\"2\" url=\"https://docs.example.com/b\">\nSecond chunk.\n</result>"
    );
});

test('it returns a message when no results are found', function () {
    $tool = new SearchNethvoiceDocumentation(fakeNethvoiceRetriever([]));

    $result = $tool->handle(new Request(['query' => 'nonexistent topic']));

    expect((string) $result->content())->toBe('No documentation results found for this query.');
});

test('it returns a friendly string instead of throwing when the retriever fails', function () {
    $tool = new SearchNethvoiceDocumentation(fakeNethvoiceRetriever(throws: new RuntimeException('kapa.ai is unreachable.')));

    $result = $tool->handle(new Request(['query' => 'how do I configure a phone extension?']));

    expect((string) $result->content())->toBe('Documentation search failed: kapa.ai is unreachable.');
});

<?php

use App\Ai\Tools\SearchNsecDocumentation;
use App\Contracts\KnowledgeRetriever;
use Laravel\Ai\Tools\Request;

function fakeRetriever(array $chunks = [], ?Throwable $throws = null): KnowledgeRetriever
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
    $tool = new SearchNsecDocumentation(fakeRetriever([
        ['source_url' => 'https://docs.example.com/a', 'content' => 'First chunk.'],
        ['source_url' => 'https://docs.example.com/b', 'content' => 'Second chunk.'],
    ]));

    $result = $tool->handle(new Request(['query' => 'how do I reset a password?']));

    expect($result)->toBe(
        "<result number=\"1\" url=\"https://docs.example.com/a\">\nFirst chunk.\n</result>\n".
        "<result number=\"2\" url=\"https://docs.example.com/b\">\nSecond chunk.\n</result>"
    );
});

test('it returns a message when no results are found', function () {
    $tool = new SearchNsecDocumentation(fakeRetriever([]));

    $result = $tool->handle(new Request(['query' => 'nonexistent topic']));

    expect($result)->toBe('No documentation results found for this query.');
});

test('it returns a friendly string instead of throwing when the retriever fails', function () {
    $tool = new SearchNsecDocumentation(fakeRetriever(throws: new RuntimeException('kapa.ai is unreachable.')));

    $result = $tool->handle(new Request(['query' => 'how do I reset a password?']));

    expect($result)->toBe('Documentation search failed: kapa.ai is unreachable.');
});

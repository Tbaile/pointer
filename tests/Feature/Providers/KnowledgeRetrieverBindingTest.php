<?php

use App\Ai\Tools\SearchNsecDocumentation;
use App\Contracts\KnowledgeRetriever;
use App\Mcp\Tools\SearchNethvoiceDocumentation;
use App\Mcp\Tools\SearchNs8Documentation;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request as AiRequest;
use Laravel\Mcp\Request as McpRequest;

test('the default knowledge retriever queries the nsec source group', function () {
    Http::fake();

    app(KnowledgeRetriever::class)->retrieve('query');

    Http::assertSent(fn (Request $request): bool => $request->data()['source_group_ids_include'] === [
        '5ea2f7fd-9fe2-405b-baa2-5682c38d654a',
    ]);
});

test('the ai agent nsec tool is bound a retriever scoped to the nsec source group', function () {
    Http::fake();

    app(SearchNsecDocumentation::class)->handle(new AiRequest(['query' => 'query']));

    Http::assertSent(fn (Request $request): bool => $request->data()['source_group_ids_include'] === [
        '5ea2f7fd-9fe2-405b-baa2-5682c38d654a',
    ]);
});

test('the ns8 tool is bound a retriever scoped to the ns8 source groups', function () {
    Http::fake();

    app(SearchNs8Documentation::class)->handle(new McpRequest(['query' => 'query']));

    Http::assertSent(fn (Request $request): bool => $request->data()['source_group_ids_include'] === [
        'bdabb771-be6a-42ea-8978-c612dd94a8a3',
        'eae9b182-9aad-45f7-a616-30c91dd2e019',
    ]);
});

test('the nethvoice tool is bound a retriever scoped to the nethvoice source group', function () {
    Http::fake();

    app(SearchNethvoiceDocumentation::class)->handle(new McpRequest(['query' => 'query']));

    Http::assertSent(fn (Request $request): bool => $request->data()['source_group_ids_include'] === [
        'e92a7b49-112e-479c-8813-0b8562b9e652',
    ]);
});

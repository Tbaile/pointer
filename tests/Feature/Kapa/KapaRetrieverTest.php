<?php

use App\Services\Kapa\KapaNsecRetriever;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

function retriever(): KapaNsecRetriever
{
    return new KapaNsecRetriever('test-api-key', '0d1e6f2a-6b8f-4b8e-9a3e-1c2d3e4f5a6b');
}

test('it sends the query to the retrieval endpoint with the api key header', function () {
    Http::fake();

    retriever()->retrieve('how do I reset a password?');

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://api.kapa.ai/query/v1/projects/0d1e6f2a-6b8f-4b8e-9a3e-1c2d3e4f5a6b/retrieval/'
            && $request->method() === 'POST'
            && $request->hasHeader('X-API-KEY', 'test-api-key')
            && $request->data()['query'] === 'how do I reset a password?'
            && $request->data()['top_k'] === 3;
    });
});

test('it returns the relevant sources from a successful response', function () {
    Http::fake([
        '*' => Http::response([
            [
                'source_url' => 'https://docs.example.com/reset',
                'content' => 'Go to settings and click reset.',
            ],
        ], 200),
    ]);

    $chunks = retriever()->retrieve('how do I reset a password?');

    expect($chunks)->toBe([
        [
            'source_url' => 'https://docs.example.com/reset',
            'content' => 'Go to settings and click reset.',
        ],
    ]);
});

test('it throws when kapa.ai returns an error response', function () {
    Http::fake([
        '*' => Http::response(['detail' => 'Missing or invalid X-API-KEY.'], 401),
    ]);

    expect(fn () => retriever()->retrieve('how do I reset a password?'))
        ->toThrow(RequestException::class);
});

test('it throws when kapa.ai cannot be reached', function () {
    Http::fake(fn () => throw new ConnectionException('Connection timed out.'));

    expect(fn () => retriever()->retrieve('how do I reset a password?'))
        ->toThrow(ConnectionException::class);
});

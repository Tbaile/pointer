<?php

namespace App\Services\Kapa;

use App\Contracts\KnowledgeRetriever;
use Illuminate\Support\Facades\Http;

/**
 * Queries kapa.ai's retrieval endpoint: semantic search over a project's
 * ingested knowledge sources, without LLM generation on top of the results.
 */
final class KapaNsecRetriever implements KnowledgeRetriever
{
    public function __construct(private string $apiKey, private string $projectId) {}

    private const string BASE_URL = 'https://api.kapa.ai';

    public function retrieve(string $query): array
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-API-KEY' => $this->apiKey,
        ])
            ->post(
                sprintf('%s/query/v1/projects/%s/retrieval/', self::BASE_URL, $this->projectId),
                [
                    'query' => $query,
                    'top_k' => 3,
                    'source_group_ids_include' => [
                        '5ea2f7fd-9fe2-405b-baa2-5682c38d654a',
                    ],
                    'user' => [
                        'unique_client_id' => 'Pointer AI Tool',
                    ],
                ],
            )
            ->throw();

        return $response->json() ?? [];
    }
}

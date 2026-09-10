<?php

namespace App\Contracts;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

interface KnowledgeRetriever
{
    /**
     * Perform semantic retrieval against the knowledge base for the given
     * query, without any LLM generation on top of the results.
     *
     * @return list<array{source_url: string, content: string}> the relevant source chunks
     *
     * @throws RequestException when kapa.ai returns an error response
     * @throws ConnectionException when kapa.ai cannot be reached
     */
    public function retrieve(string $query): array;
}

<?php

namespace App\Providers;

use App\Contracts\KnowledgeRetriever;
use App\Mcp\Tools\SearchNethvoiceDocumentation;
use App\Mcp\Tools\SearchNs8Documentation;
use App\Services\Kapa\KapaRetriever;
use Illuminate\Support\ServiceProvider;

class KapaServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(
            KnowledgeRetriever::class,
            fn (): KapaRetriever => new KapaRetriever(
                apiKey: config('services.kapa.api_key'),
                projectId: config('services.kapa.project_id'),
                sourceGroupIds: ['5ea2f7fd-9fe2-405b-baa2-5682c38d654a'],
            ),
        );

        $this->app->when(SearchNs8Documentation::class)
            ->needs(KnowledgeRetriever::class)
            ->give(fn (): KapaRetriever => new KapaRetriever(
                apiKey: config('services.kapa.api_key'),
                projectId: config('services.kapa.project_id'),
                sourceGroupIds: [
                    'bdabb771-be6a-42ea-8978-c612dd94a8a3',
                    'eae9b182-9aad-45f7-a616-30c91dd2e019',
                ],
            ));

        $this->app->when(SearchNethvoiceDocumentation::class)
            ->needs(KnowledgeRetriever::class)
            ->give(fn (): KapaRetriever => new KapaRetriever(
                apiKey: config('services.kapa.api_key'),
                projectId: config('services.kapa.project_id'),
                sourceGroupIds: ['e92a7b49-112e-479c-8813-0b8562b9e652'],
            ));
    }
}

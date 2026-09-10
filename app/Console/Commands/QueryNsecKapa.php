<?php

namespace App\Console\Commands;

use App\Contracts\KnowledgeRetriever;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('kapa:query:nsec
    {query : The natural-language query to retrieve knowledge for}')]
#[Description("Query kapa.ai's retrieval API to check that knowledge retrieval works")]
class QueryNsecKapa extends Command
{
    public function handle(KnowledgeRetriever $retriever): int
    {
        $query = (string) $this->argument('query');

        try {
            $chunks = $retriever->retrieve($query);
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($chunks as $chunk) {
            $this->newLine();
            $this->components->twoColumnDetail($chunk['source_url']);
            $this->line($chunk['content']);
        }

        return self::SUCCESS;
    }
}

<?php

namespace App\Listeners;

use App\Events\CommandClassified;
use App\Models\CommandClassification;

class RecordCommandClassification
{
    /**
     * Handle the event.
     */
    public function handle(CommandClassified $event): void
    {
        CommandClassification::create([
            'sos_id' => $event->sosId,
            'user_id' => $event->user?->getAuthIdentifier(),
            'command' => $event->command,
            'probabilities' => $event->probabilities,
            'flagged' => $event->flagged,
            'model' => $event->response?->meta->model,
            'input_tokens' => $event->response?->usage->inputTokens,
            'output_tokens' => $event->response?->usage->outputTokens,
            'error' => $event->error,
        ]);
    }
}

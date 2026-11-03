<?php

namespace App\Listeners;

use App\Events\McpToolCalled;
use App\Models\McpToolCall;

class RecordMcpToolCall
{
    /**
     * Handle the event.
     */
    public function handle(McpToolCalled $event): void
    {
        McpToolCall::create([
            'user_id' => $event->user?->getAuthIdentifier(),
            'client' => $event->client,
            'session_id' => $event->sessionId,
            'tool' => $event->tool,
            'sos_id' => $event->arguments['sos_id'] ?? null,
            'arguments' => $event->arguments,
            'success' => $event->success,
            'duration_ms' => $event->durationMs,
        ]);
    }
}

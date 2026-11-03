<?php

namespace App\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;

class McpToolCalled
{
    use Dispatchable;

    /**
     * Create a new event instance.
     *
     * @param  array<string, mixed>|null  $arguments
     */
    public function __construct(
        public ?Authenticatable $user,
        public ?string $client,
        public ?string $sessionId,
        public string $tool,
        public ?array $arguments,
        public bool $success,
        public int $durationMs,
    ) {}
}

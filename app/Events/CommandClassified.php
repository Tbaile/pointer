<?php

namespace App\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Laravel\Ai\Responses\ClassificationResponse;

class CommandClassified
{
    use Dispatchable;

    /**
     * Create a new event instance.
     *
     * @param  array<string, float>|null  $probabilities
     * @param  list<string>|null  $flagged
     */
    public function __construct(
        public string $sosId,
        public ?Authenticatable $user,
        public string $command,
        public ?ClassificationResponse $response = null,
        public ?array $probabilities = null,
        public ?array $flagged = null,
        public ?string $error = null,
    ) {}
}

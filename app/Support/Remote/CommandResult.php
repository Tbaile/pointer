<?php

namespace App\Support\Remote;

/**
 * The outcome of a single command executed on a target machine.
 */
final readonly class CommandResult
{
    public function __construct(
        public string $machineUuid,
        public string $command,
        public int $exitCode,
        public string $stdout,
        public string $stderr,
        public int $durationMs,
        public bool $truncated = false,
    ) {}

    public function successful(): bool
    {
        return $this->exitCode === 0;
    }

    public function failed(): bool
    {
        return ! $this->successful();
    }
}

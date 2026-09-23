<?php

namespace App\Services\Remote;

use RuntimeException;

/**
 * Outcome of a command run on a target machine.
 */
final readonly class CommandResult
{
    public function __construct(
        public string $output,
        public string $errorOutput = '',
        public int $exitCode = 0,
    ) {}

    public function successful(): bool
    {
        return $this->exitCode === 0;
    }

    /**
     * @throws RuntimeException with the command's stderr (or stdout) when it exited non-zero
     */
    public function throw(): self
    {
        if ($this->successful()) {
            return $this;
        }

        throw new RuntimeException(trim($this->errorOutput ?: $this->output) ?: "Command exited with code {$this->exitCode}");
    }
}

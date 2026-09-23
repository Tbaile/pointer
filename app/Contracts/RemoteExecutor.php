<?php

namespace App\Contracts;

use RuntimeException;

interface RemoteExecutor
{
    /**
     * Run a command on the target machine identified by the given UUID and return its stdout.
     *
     * Implementations must treat $command as the literal command line for the
     * target's shell and must never interpolate it into a shell on any
     * intermediate hop without escaping.
     *
     * @throws RuntimeException with the command's stderr (or stdout) when it fails
     */
    public function run(string $machineUuid, string $command): string;
}

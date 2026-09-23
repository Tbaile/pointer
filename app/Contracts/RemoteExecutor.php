<?php

namespace App\Contracts;

use App\Services\Remote\CommandResult;
use RuntimeException;

interface RemoteExecutor
{
    /**
     * Run a command on the target machine identified by the given UUID, whatever its exit code.
     *
     * Implementations must treat $command as the literal command line for the
     * target's shell and must never interpolate it into a shell on any
     * intermediate hop without escaping.
     *
     * @throws RuntimeException when the target cannot be reached or the session breaks
     */
    public function run(string $machineUuid, string $command): CommandResult;
}

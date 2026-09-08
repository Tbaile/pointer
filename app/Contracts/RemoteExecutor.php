<?php

namespace App\Contracts;

use App\Support\Remote\CommandResult;

interface RemoteExecutor
{
    /**
     * Run a command on the target machine identified by the given UUID.
     *
     * Implementations must treat $command as the literal command line for the
     * target's shell and must never interpolate it into a shell on any
     * intermediate hop without escaping.
     */
    public function run(string $machineUuid, string $command): CommandResult;
}

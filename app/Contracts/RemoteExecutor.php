<?php

namespace App\Contracts;

interface RemoteExecutor
{
    /**
     * Run a command on the target machine identified by the given UUID.
     *
     * Implementations must treat $command as the literal command line for the
     * target's shell and must never interpolate it into a shell on any
     * intermediate hop without escaping.
     *
     * @return array{machine_uuid: string, command: string, exit_code: int, stdout: string, stderr: string, duration_ms: int, truncated: bool}
     */
    public function run(string $machineUuid, string $command): array;
}

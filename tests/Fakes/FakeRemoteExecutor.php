<?php

namespace Tests\Fakes;

use App\Contracts\RemoteExecutor;
use App\Services\Remote\CommandResult;
use Throwable;

/**
 * Returns the given result (a plain string is stdout) or throws it, and records the last call.
 */
class FakeRemoteExecutor implements RemoteExecutor
{
    public ?string $calledWithMachineUuid = null;

    public ?string $calledWithCommand = null;

    public function __construct(private CommandResult|string|Throwable $result) {}

    public function run(string $machineUuid, string $command): CommandResult
    {
        $this->calledWithMachineUuid = $machineUuid;
        $this->calledWithCommand = $command;

        if ($this->result instanceof Throwable) {
            throw $this->result;
        }

        if (is_string($this->result)) {
            return new CommandResult($this->result);
        }

        return $this->result;
    }
}

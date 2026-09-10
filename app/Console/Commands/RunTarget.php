<?php

namespace App\Console\Commands;

use App\Contracts\RemoteExecutor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Operator runner for the support server hop.
 *
 * This is deliberately not part of any agent's tooling: it exists so a human
 * can test, from the CLI, that Pointer can reach a target and execute a
 * command on it through the support server, before a model is ever pointed
 * at it.
 */
#[Signature('pointer:run
    {uuid : The target machine identifier}
    {--command=uname -a : The command to run on the target}')]
#[Description('Run a command on a target machine through the support server')]
class RunTarget extends Command
{
    public function handle(RemoteExecutor $executor): int
    {
        $uuid = (string) $this->argument('uuid');
        $command = (string) $this->option('command');

        $result = $executor->run($uuid, $command);

        if ($result['stdout'] !== '') {
            $this->line($result['stdout']);
        }

        if ($result['exit_code'] !== 0) {
            $this->components->error($result['stderr']);
        }

        return $result['exit_code'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}

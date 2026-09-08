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
    {--command=uname -a : The command to run on the target}
    {--result-only : Print only the command\'s stdout/stderr, without the info line or exit-code table}')]
#[Description('Run a command on a target machine through the support server')]
class RunTarget extends Command
{
    public function handle(RemoteExecutor $executor): int
    {
        $uuid = (string) $this->argument('uuid');
        $command = (string) $this->option('command');
        $resultOnly = (bool) $this->option('result-only');

        if (! $resultOnly) {
            $this->components->info(sprintf('Running [%s] on %s', $command, $uuid));
        }

        $result = $executor->run($uuid, $command);

        if (! $resultOnly) {
            $this->components->twoColumnDetail('Exit code', (string) $result->exitCode);
            $this->components->twoColumnDetail('Duration', $result->durationMs.'ms');
            $this->components->twoColumnDetail('Truncated', $result->truncated ? 'yes' : 'no');
        }

        if ($result->stdout !== '') {
            if (! $resultOnly) {
                $this->newLine();
            }
            $this->line($result->stdout);
        }

        if ($result->stderr !== '') {
            if (! $resultOnly) {
                $this->newLine();
                $this->components->error($result->stderr);
            } else {
                $this->line($result->stderr);
            }
        }

        return $result->successful() ? self::SUCCESS : self::FAILURE;
    }
}

<?php

namespace App\Console\Commands;

use App\Contracts\RemoteExecutor;
use App\Models\System;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('pointer:agent {sos_id : The target\'s sancho session identifier}')]
class PointerRun extends Command
{
    public function handle(RemoteExecutor $executor): int
    {
        $sosId = $this->argument('sos_id');

        $osRelease = $executor->run($sosId, 'cat /etc/os-release');

        if ($osRelease['exit_code'] !== 0) {
            $this->components->error($osRelease['stderr']);

            return self::FAILURE;
        }

        $id = $this->parseOsReleaseId($osRelease['stdout']);

        if ($id !== 'nethsecurity') {
            throw new RuntimeException("Unsupported system: {$id}");
        }

        $systemId = $executor->run($sosId, 'uci get ns-plug.config.system_id');

        if ($systemId['exit_code'] !== 0) {
            $this->components->error($systemId['stderr']);

            return self::FAILURE;
        }

        $machineId = trim($systemId['stdout']);

        System::updateOrCreate(['machine_id' => $machineId], ['sos_id' => $sosId]);

        $this->components->info("Registered system: sos_id={$sosId} machine_id={$machineId}");

        return self::SUCCESS;
    }

    private function parseOsReleaseId(string $osRelease): string
    {
        preg_match('/^ID=(.*)$/m', $osRelease, $matches);

        return trim($matches[1] ?? '', " \t\n\r\0\x0B\"'");
    }
}

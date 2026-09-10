<?php

namespace App\Console\Commands;

use App\Ai\Agents\PointerAgent;
use App\Contracts\RemoteExecutor;
use App\Enums\SystemType;
use App\Models\System;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\ToolCall;
use Laravel\Ai\Streaming\Events\ToolResult;
use RuntimeException;

#[Signature('pointer:agent {sos_id : The target\'s sancho session identifier} {--continue : Continue this system\'s previous conversation instead of starting fresh}')]
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

        if ($id !== SystemType::NethSecurity->value) {
            throw new RuntimeException("Unsupported system: {$id}");
        }

        $systemId = $executor->run($sosId, 'uci get ns-plug.config.system_id');

        if ($systemId['exit_code'] !== 0) {
            $this->components->error($systemId['stderr']);

            return self::FAILURE;
        }

        $machineId = trim($systemId['stdout']);

        $system = System::updateOrCreate(['machine_id' => $machineId], ['sos_id' => $sosId]);

        $this->components->info("Registered system: sos_id={$sosId} machine_id={$machineId}");

        $agent = new PointerAgent($executor, $sosId, SystemType::NethSecurity);

        if ($this->option('continue')) {
            $agent->continueLastConversation($system);
        } else {
            $agent->forParticipant($system);
        }

        while (true) {
            $objective = $this->ask('What should Pointer look for on this machine?');

            if (! $objective) {
                break;
            }

            $stream = $agent->stream($objective);

            foreach ($stream as $event) {
                match (true) {
                    $event instanceof TextDelta => $this->output->write($event->delta),
                    $event instanceof ToolCall => $this->components->twoColumnDetail(
                        "  <fg=yellow>→</> {$event->toolCall->name}",
                        json_encode($event->toolCall->arguments) ?: null,
                    ),
                    $event instanceof ToolResult => $this->components->twoColumnDetail(
                        '  '.($event->successful ? '<fg=green>✓</>' : '<fg=red>✗</>')." {$event->toolResult->name}",
                        $event->successful ? null : $event->error,
                    ),
                    default => null,
                };
            }

            $this->newLine(2);
        }

        return self::SUCCESS;
    }

    private function parseOsReleaseId(string $osRelease): string
    {
        preg_match('/^ID=(.*)$/m', $osRelease, $matches);

        return trim($matches[1] ?? '', " \t\n\r\0\x0B\"'");
    }
}

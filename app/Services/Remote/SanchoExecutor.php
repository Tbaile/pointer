<?php

namespace App\Services\Remote;

use App\Contracts\RemoteExecutor;
use App\Support\Remote\CommandResult;
use App\Support\Remote\SupportServerConfig;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Reaches a target machine through the support server's session helper.
 *
 * `sancho session ssh <uuid>` does not accept a command argument — it drops
 * the caller into the target's shell and reads further commands from stdin,
 * the same way an operator's interactive session would. So the command is
 * piped in as a small script, wrapped in a pair of random markers that let
 * the target's real output be separated from the session's own banner, and
 * the target's exit code be recovered even though the outer ssh process's
 * exit code reflects sancho, not the command that ran inside the session.
 */
final readonly class SanchoExecutor implements RemoteExecutor
{
    /**
     * How long the outer ssh client waits for the support server to answer,
     * as opposed to the command timeout, which bounds the whole run.
     */
    private const int CONNECT_TIMEOUT_SECONDS = 10;

    private const string SANCHO_BINARY = 'sancho';

    private const int TIMEOUT_EXIT_CODE = 124;

    /**
     * Used when the target's exit-code marker never appears in the output —
     * the session itself failed before the command could run.
     */
    private const int SESSION_ERROR_EXIT_CODE = 125;

    public function __construct(private SupportServerConfig $config) {}

    public function run(string $machineUuid, string $command): CommandResult
    {
        $this->assertValidMachineUuid($machineUuid);

        $startMarker = '__POINTER_START_'.bin2hex(random_bytes(16)).'__';
        $endMarker = '__POINTER_END_'.bin2hex(random_bytes(16)).'__';

        $startedAt = hrtime(true);

        try {
            $result = Process::timeout($this->config->timeout)
                ->input($this->buildRemoteScript($command, $startMarker, $endMarker))
                ->run($this->buildSshArguments($machineUuid));

            $rawExitCode = $result->exitCode() ?? 1;
            $stdout = $result->output();
            $stderr = $result->errorOutput();
        } catch (ProcessTimedOutException) {
            $rawExitCode = self::TIMEOUT_EXIT_CODE;
            $stdout = '';
            $stderr = sprintf('Command timed out after %d seconds.', $this->config->timeout);
        }

        [$stdout, $exitCode] = $this->extractCommandOutput($stdout, $rawExitCode, $startMarker, $endMarker);

        $cap = $this->config->maxOutputBytes;
        $truncated = strlen($stdout) > $cap || strlen($stderr) > $cap;

        return new CommandResult(
            machineUuid: $machineUuid,
            command: $command,
            exitCode: $exitCode,
            stdout: substr($stdout, 0, $cap),
            stderr: substr($stderr, 0, $cap),
            durationMs: (int) ((hrtime(true) - $startedAt) / 1_000_000),
            truncated: $truncated,
        );
    }

    /**
     * Build the script fed to the target's shell over stdin: announce the
     * start marker, run the command, then report the end marker paired with
     * the command's own exit code.
     */
    private function buildRemoteScript(string $command, string $startMarker, string $endMarker): string
    {
        return sprintf(
            "printf '%%s\\n' %s\n%s\n__pointer_exit=\$?\nprintf '%%s:%%s\\n' %s \"\$__pointer_exit\"\n",
            escapeshellarg($startMarker),
            $command,
            escapeshellarg($endMarker),
        );
    }

    /**
     * Build the argument vector for the outer SSH connection.
     *
     * Host key policy is deliberately not set here: the port, the known hosts
     * file and the strictness are left to the ssh client's own configuration
     * for this host, so an operator can tighten or relax them per machine
     * without Pointer overriding the choice.
     *
     * @return list<string>
     */
    private function buildSshArguments(string $machineUuid): array
    {
        $arguments = [
            'ssh',
            '-T',
            '-o', 'BatchMode=yes',
            '-o', 'ConnectTimeout='.self::CONNECT_TIMEOUT_SECONDS,
            '-o', 'LogLevel=ERROR',
        ];

        if ($this->config->identityFile !== null) {
            $arguments[] = '-o';
            $arguments[] = 'IdentitiesOnly=yes';
            $arguments[] = '-i';
            $arguments[] = $this->config->identityFile;
        }

        $arguments[] = $this->config->user.'@'.$this->config->host;
        $arguments[] = $this->buildSupportServerCommand($machineUuid);

        return $arguments;
    }

    /**
     * Build the single command line executed by the support server's shell.
     */
    private function buildSupportServerCommand(string $machineUuid): string
    {
        return sprintf(
            '%s session ssh %s',
            self::SANCHO_BINARY,
            escapeshellarg($machineUuid),
        );
    }

    /**
     * Isolate the command's own output and exit code from the session's
     * banner. Falls back to the raw process outcome when the markers never
     * appear, which means the session failed before the script could run.
     *
     * @return array{0: string, 1: int}
     */
    private function extractCommandOutput(string $stdout, int $rawExitCode, string $startMarker, string $endMarker): array
    {
        $startPos = strpos($stdout, $startMarker);
        $endPos = strpos($stdout, $endMarker);

        if ($startPos === false || $endPos === false || $endPos < $startPos) {
            return [$stdout, $rawExitCode === 0 ? self::SESSION_ERROR_EXIT_CODE : $rawExitCode];
        }

        $bodyStart = strpos($stdout, "\n", $startPos);
        $body = $bodyStart === false ? '' : substr($stdout, $bodyStart + 1, $endPos - $bodyStart - 1);

        $endLineEnd = strpos($stdout, "\n", $endPos);
        $endLine = $endLineEnd === false ? substr($stdout, $endPos) : substr($stdout, $endPos, $endLineEnd - $endPos);

        $exitCode = (int) substr($endLine, strlen($endMarker) + 1);

        return [$body, $exitCode];
    }

    /**
     * @throws InvalidArgumentException
     */
    private function assertValidMachineUuid(string $machineUuid): void
    {
        if (! Str::isUuid($machineUuid)) {
            throw new InvalidArgumentException(
                sprintf('Refusing to connect: [%s] is not a valid machine identifier.', $machineUuid),
            );
        }
    }
}

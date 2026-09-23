<?php

namespace App\Services\Remote;

use App\Contracts\RemoteExecutor;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Reaches a target through `sancho session ssh`, which reads commands from stdin, so output and
 * exit code are recovered between random markers.
 */
final readonly class SanchoExecutor implements RemoteExecutor
{
    private const int CONNECT_TIMEOUT_SECONDS = 10;

    public function __construct(
        private string $host,
        private string $user,
        private ?string $identityFile,
        private int $timeout,
        private int $maxOutputBytes,
    ) {}

    public function run(string $machineUuid, string $command): CommandResult
    {
        $startMarker = '__POINTER_START_'.bin2hex(random_bytes(16)).'__';
        $endMarker = '__POINTER_END_'.bin2hex(random_bytes(16)).'__';

        $result = Process::timeout($this->timeout)
            ->input($this->buildRemoteScript($command, $startMarker, $endMarker))
            ->run($this->buildSshArguments($machineUuid));

        $stdout = $result->output();
        $stderr = $result->errorOutput();

        $startPos = strpos($stdout, $startMarker);
        $endPos = strpos($stdout, $endMarker);

        if ($startPos === false || $endPos === false || $endPos < $startPos) {
            throw new RuntimeException(trim($stderr ?: $stdout) ?: 'Command exited with code '.($result->exitCode() ?? 1));
        }

        $bodyStart = strpos($stdout, "\n", $startPos) + 1;
        $body = substr($stdout, $bodyStart, $endPos - $bodyStart);

        return new CommandResult(
            output: substr($body, 0, $this->maxOutputBytes),
            errorOutput: $stderr,
            exitCode: (int) substr($stdout, $endPos + strlen($endMarker) + 1),
        );
    }

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
     * Port and host key policy are left to the ssh client's own configuration.
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

        if ($this->identityFile !== null) {
            array_push($arguments, '-o', 'IdentitiesOnly=yes', '-i', $this->identityFile);
        }

        $arguments[] = $this->user.'@'.$this->host;
        $arguments[] = 'sancho session ssh '.escapeshellarg($machineUuid);

        return $arguments;
    }
}

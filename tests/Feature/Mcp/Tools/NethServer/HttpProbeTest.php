<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethServer\HttpProbe;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

function fakeHttpProbeExecutor(CommandResult|Throwable $result): RemoteExecutor
{
    return new class($result) implements RemoteExecutor
    {
        public ?string $calledWithCommand = null;

        public function __construct(private CommandResult|Throwable $result) {}

        public function run(string $machineUuid, string $command): CommandResult
        {
            $this->calledWithCommand = $command;

            if ($this->result instanceof Throwable) {
                throw $this->result;
            }

            return $this->result;
        }
    };
}

/**
 * @param  array<string, mixed>  $arguments
 */
function httpProbe(RemoteExecutor $executor, array $arguments): Response|ResponseFactory
{
    return (new HttpProbe($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

test('it requests the url and reports the status code', function () {
    $executor = fakeHttpProbeExecutor(new CommandResult("301 35.214.216.68\n"));

    $result = httpProbe($executor, ['url' => 'https://nethserver.org']);

    expect($result)->toBeInstanceOf(ResponseFactory::class);
    expect($result->getStructuredContent())->toBe(['reachable' => true, 'exit_code' => 0, 'output' => "301 35.214.216.68\n"]);
    expect($executor->calledWithCommand)->toBe("curl -sS --proto =http,https -o /dev/null -m 10 -w '%{http_code} %{remote_ip}\\n' 'https://nethserver.org' 2>&1");
});

test('it reports a curl failure as unreachable rather than as an error', function () {
    $output = "curl: (6) Could not resolve host: nonexist.invalid\n000 \n";

    $result = httpProbe(fakeHttpProbeExecutor(new CommandResult($output, exitCode: 6)), ['url' => 'https://nonexist.invalid']);

    expect($result->getStructuredContent())->toBe(['reachable' => false, 'exit_code' => 6, 'output' => $output]);
});

test('it rejects unsafe input without running anything', function (array $arguments) {
    $executor = fakeHttpProbeExecutor(new CommandResult(''));

    expect(fn () => httpProbe($executor, $arguments))->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
})->with([
    'missing url' => [[]],
    'file url' => [['url' => 'file:///etc/shadow']],
    'not a url' => [['url' => "https://example.com' -o /tmp/x '"]],
]);

test('it returns an error when the machine cannot be reached', function () {
    $result = httpProbe(fakeHttpProbeExecutor(new RuntimeException('Connection timed out')), ['url' => 'https://nethserver.org']);

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Connection timed out');
});

<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethServer\DnsLookup;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

function fakeNs8DnsLookupExecutor(CommandResult|Throwable $result): RemoteExecutor
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
function ns8DnsLookup(RemoteExecutor $executor, array $arguments): Response|ResponseFactory
{
    return (new DnsLookup($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

test('it resolves the name with the system resolver', function () {
    $output = "35.214.216.68   STREAM nethserver.org\n35.214.216.68   DGRAM\n35.214.216.68   RAW\n";
    $executor = fakeNs8DnsLookupExecutor(new CommandResult($output));

    $result = ns8DnsLookup($executor, ['name' => 'nethserver.org']);

    expect($result)->toBeInstanceOf(ResponseFactory::class);
    expect($result->getStructuredContent())->toBe(['resolved' => true, 'exit_code' => 0, 'output' => $output]);
    expect($executor->calledWithCommand)->toBe("getent ahosts 'nethserver.org' 2>&1");
});

test('it reports a name that does not resolve rather than an error', function () {
    $result = ns8DnsLookup(fakeNs8DnsLookupExecutor(new CommandResult('', exitCode: 2)), ['name' => 'nonexist.invalid']);

    expect($result->getStructuredContent())->toBe(['resolved' => false, 'exit_code' => 2, 'output' => '']);
});

test('it rejects unsafe input without running anything', function (array $arguments) {
    $executor = fakeNs8DnsLookupExecutor(new CommandResult(''));

    expect(fn () => ns8DnsLookup($executor, $arguments))->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
})->with([
    'missing name' => [[]],
    'name with a shell command' => [['name' => 'example.com; reboot']],
    'name with spaces' => [['name' => 'example .com']],
]);

test('it returns an error when the machine cannot be reached', function () {
    $result = ns8DnsLookup(fakeNs8DnsLookupExecutor(new RuntimeException('Connection timed out')), ['name' => 'nethserver.org']);

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Connection timed out');
});

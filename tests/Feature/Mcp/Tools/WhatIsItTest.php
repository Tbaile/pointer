<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\WhatIsIt;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

/**
 * @param  array<string, string|Throwable>  $results  stdout or failure keyed by command
 */
function fakeWhatIsItExecutor(array $results = [], ?Throwable $throws = null): RemoteExecutor
{
    return new class($results, $throws) implements RemoteExecutor
    {
        public ?string $calledWithMachineUuid = null;

        /** @var list<string> */
        public array $calledWithCommands = [];

        public function __construct(private array $results, private ?Throwable $throws) {}

        public function run(string $machineUuid, string $command): string
        {
            $this->calledWithMachineUuid = $machineUuid;
            $this->calledWithCommands[] = $command;

            $result = $this->throws ?? $this->results[$command] ?? '';

            if ($result instanceof Throwable) {
                throw $result;
            }

            return $result;
        }
    };
}

function whatIsIt(RemoteExecutor $executor): Response
{
    return (new WhatIsIt($executor))->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000']));
}

test('it identifies NethSecurity from os-release alone', function () {
    $executor = fakeWhatIsItExecutor([
        'cat /etc/os-release' => "NAME=\"NethSecurity\"\nID=nethsecurity\nVERSION_ID=\"8.8.0\"\n",
    ]);

    $result = whatIsIt($executor);

    expect($result->isError())->toBeFalse();
    expect((string) $result->content())->toBe('nethsecurity');
    expect($executor->calledWithMachineUuid)->toBe('a1b2c3d4-0000-0000-0000-000000000000');
    expect($executor->calledWithCommands)->toBe(['cat /etc/os-release']);
});

test('it identifies NethServer 8 when core.env is present', function () {
    $executor = fakeWhatIsItExecutor([
        'cat /etc/os-release' => "NAME=\"Rocky Linux\"\nID=\"rocky\"\n",
        'cat /etc/nethserver/core.env' => "NODE_ID=1\n",
    ]);

    $result = whatIsIt($executor);

    expect($result->isError())->toBeFalse();
    expect((string) $result->content())->toBe('nethserver');
    expect($executor->calledWithCommands)->toBe(['cat /etc/os-release', 'cat /etc/nethserver/core.env']);
});

test('it returns an unsupported error when neither NethSecurity nor NethServer 8', function () {
    $result = whatIsIt(fakeWhatIsItExecutor([
        'cat /etc/os-release' => "ID=ubuntu\n",
        'cat /etc/nethserver/core.env' => new RuntimeException('No such file or directory'),
    ]));

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Unsupported system: ubuntu');
});

test('it returns an error when os-release cannot be read', function () {
    $result = whatIsIt(fakeWhatIsItExecutor([
        'cat /etc/os-release' => new RuntimeException('No such file or directory'),
    ]));

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Could not read /etc/os-release: No such file or directory');
});

test('it returns an error when os-release has no ID', function () {
    $result = whatIsIt(fakeWhatIsItExecutor([
        'cat /etc/os-release' => "NAME=\"Something\"\n",
    ]));

    expect($result->isError())->toBeTrue();
});

test('it returns an error instead of throwing when the machine is unreachable', function () {
    $result = whatIsIt(fakeWhatIsItExecutor(throws: new RuntimeException('Connection refused.')));

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Could not read /etc/os-release: Connection refused.');
});

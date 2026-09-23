<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethSecurity\ListDevices;
use App\Services\Privacy\SecretMasker;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

function fakeListDevicesExecutor(string|Throwable $result): RemoteExecutor
{
    return new class($result) implements RemoteExecutor
    {
        public ?string $calledWithMachineUuid = null;

        public ?string $calledWithCommand = null;

        public function __construct(private string|Throwable $result) {}

        public function run(string $machineUuid, string $command): string
        {
            $this->calledWithMachineUuid = $machineUuid;
            $this->calledWithCommand = $command;

            if ($this->result instanceof Throwable) {
                throw $this->result;
            }

            return $this->result;
        }
    };
}

function listDevices(RemoteExecutor $executor): Response|ResponseFactory
{
    return (new ListDevices($executor, new SecretMasker('test-key')))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000']));
}

test('it returns the devices as structured content with private keys masked', function () {
    $executor = fakeListDevicesExecutor('{"all_devices":[{"name":"eth0"},{"name":"wg1","iface":{"proto":"wireguard","private_key":"4Al1bJOCuxfekhKW5omA0RuICrNJ3QUiXgq1N3O/ong="}}]}');

    $result = listDevices($executor);

    expect($result)->toBeInstanceOf(ResponseFactory::class);
    expect($result->getStructuredContent())->toBe([
        'all_devices' => [
            ['name' => 'eth0'],
            ['name' => 'wg1', 'iface' => [
                'proto' => 'wireguard',
                'private_key' => '[masked:hmac-sha256:'.substr(hash_hmac('sha256', '4Al1bJOCuxfekhKW5omA0RuICrNJ3QUiXgq1N3O/ong=', 'test-key'), 0, 12).']',
            ]],
        ],
    ]);
    expect($executor->calledWithMachineUuid)->toBe('a1b2c3d4-0000-0000-0000-000000000000');
    expect($executor->calledWithCommand)->toBe("ubus -S call 'ns.devices' 'list-devices' '{}'");
});

test('it reports a failed call as an error', function () {
    $result = listDevices(fakeListDevicesExecutor(new RuntimeException('Connection refused.')));

    expect($result)->toBeInstanceOf(Response::class);
    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Connection refused.');
});

test('it reports a call that does not return JSON as an error', function () {
    $result = listDevices(fakeListDevicesExecutor('not json'));

    expect($result)->toBeInstanceOf(Response::class);
    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('ubus did not return a JSON object.');
});

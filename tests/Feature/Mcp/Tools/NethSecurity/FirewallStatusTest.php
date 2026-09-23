<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethSecurity\FirewallStatus;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;

const DEVICES_COMMAND = "ubus -S call 'ns.devices' 'list-devices' '{}'";
const ZONES_COMMAND = "ubus -S call 'ns.firewall' 'list_zones' '{}'";
const FORWARDINGS_COMMAND = "ubus -S call 'ns.firewall' 'list_forwardings' '{}'";

/**
 * @param  array<string, array<string, mixed>>  $results  result overrides keyed by command
 */
function fakeFirewallStatusExecutor(array $results = [], ?Throwable $throws = null): RemoteExecutor
{
    return new class($results, $throws) implements RemoteExecutor
    {
        public ?string $calledWithMachineUuid = null;

        /** @var list<string> */
        public array $calledWithCommands = [];

        public function __construct(private array $results, private ?Throwable $throws) {}

        public function run(string $machineUuid, string $command): array
        {
            $this->calledWithMachineUuid = $machineUuid;
            $this->calledWithCommands[] = $command;

            if ($this->throws) {
                throw $this->throws;
            }

            return array_merge([
                'machine_uuid' => $machineUuid,
                'command' => $command,
                'exit_code' => 0,
                'stdout' => '{}',
                'stderr' => '',
                'duration_ms' => 1,
                'truncated' => false,
            ], $this->results[$command] ?? []);
        }
    };
}

function firewallStatus(RemoteExecutor $executor): ResponseFactory
{
    return (new FirewallStatus($executor))->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000']));
}

test('it returns devices, zones and forwardings as structured content', function () {
    $executor = fakeFirewallStatusExecutor([
        DEVICES_COMMAND => ['stdout' => '{"devices_by_zone":[{"name":"lan","devices":["eth0"]}]}'],
        ZONES_COMMAND => ['stdout' => '{"ns_lan":{"name":"lan","input":"ACCEPT"}}'],
        FORWARDINGS_COMMAND => ['stdout' => '{"ns_lan2wan":{"src":"lan","dest":"wan"}}'],
    ]);

    $result = firewallStatus($executor);

    expect($result->getStructuredContent())->toBe([
        'ns.devices list-devices' => ['devices_by_zone' => [['name' => 'lan', 'devices' => ['eth0']]]],
        'ns.firewall list_zones' => ['ns_lan' => ['name' => 'lan', 'input' => 'ACCEPT']],
        'ns.firewall list_forwardings' => ['ns_lan2wan' => ['src' => 'lan', 'dest' => 'wan']],
    ]);
    expect($executor->calledWithMachineUuid)->toBe('a1b2c3d4-0000-0000-0000-000000000000');
    expect($executor->calledWithCommands)->toBe([DEVICES_COMMAND, ZONES_COMMAND, FORWARDINGS_COMMAND]);
});

test('it reports a failed call and still returns the others', function () {
    $result = firewallStatus(fakeFirewallStatusExecutor([
        ZONES_COMMAND => ['exit_code' => 4, 'stdout' => '', 'stderr' => "Command failed: Not found\n"],
    ]));

    expect($result->getStructuredContent())->toBe([
        'ns.devices list-devices' => [],
        'ns.firewall list_zones' => ['error' => 'Command failed: Not found'],
        'ns.firewall list_forwardings' => [],
    ]);
});

test('it reports a call that does not return JSON', function () {
    $result = firewallStatus(fakeFirewallStatusExecutor([
        FORWARDINGS_COMMAND => ['stdout' => 'not json'],
    ]));

    expect($result->getStructuredContent()['ns.firewall list_forwardings'])->toBe(['error' => 'ubus did not return a JSON object.']);
});

test('it reports every call as failed instead of throwing when the machine is unreachable', function () {
    $result = firewallStatus(fakeFirewallStatusExecutor(throws: new RuntimeException('Connection refused.')));

    expect($result->getStructuredContent())->toBe([
        'ns.devices list-devices' => ['error' => 'Connection refused.'],
        'ns.firewall list_zones' => ['error' => 'Connection refused.'],
        'ns.firewall list_forwardings' => ['error' => 'Connection refused.'],
    ]);
});

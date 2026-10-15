<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethSecurity\Ping;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Tests\Fakes\FakeRemoteExecutor;

/**
 * @param  array<string, mixed>  $arguments
 */
function ping(RemoteExecutor $executor, array $arguments): Response|ResponseFactory
{
    return (new Ping($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

const PING_REPLY = <<<'OUTPUT'
    PING 1.1.1.1 (1.1.1.1) 56(84) bytes of data.
    64 bytes from 1.1.1.1: icmp_seq=1 ttl=55 time=14.5 ms

    --- 1.1.1.1 ping statistics ---
    1 packets transmitted, 1 received, 0% packet loss, time 0ms
    rtt min/avg/max/mdev = 14.535/14.535/14.535/0.000 ms

    OUTPUT;

test('it pings the target and reports it reachable', function () {
    $executor = new FakeRemoteExecutor(new CommandResult(PING_REPLY));

    $result = ping($executor, ['target' => '1.1.1.1']);

    expect($result)->toBeInstanceOf(ResponseFactory::class);
    expect($result->getStructuredContent())->toBe(['reachable' => true, 'exit_code' => 0, 'output' => PING_REPLY]);
    expect($executor->calledWithMachineUuid)->toBe('a1b2c3d4-0000-0000-0000-000000000000');
    expect($executor->calledWithCommand)->toBe("ping -n -c 3 -W 2 '1.1.1.1' 2>&1");
});

test('it sends the requested count from the given source address', function () {
    $executor = new FakeRemoteExecutor(new CommandResult(PING_REPLY));

    ping($executor, ['target' => 'one.one.one.one', 'count' => 5, 'source_ip' => '10.0.1.1']);

    expect($executor->calledWithCommand)->toBe("ping -n -c 5 -W 2 -I '10.0.1.1' 'one.one.one.one' 2>&1");
});

test('it sends a payload of the given size with the Don\'t Fragment bit through an mwan3 interface', function () {
    $executor = new FakeRemoteExecutor(new CommandResult(PING_REPLY));

    ping($executor, ['target' => '10.20.0.1', 'interface' => 'wan', 'size' => 1472, 'dont_fragment' => true, 'count' => 2]);

    expect($executor->calledWithCommand)->toBe("mwan3 use 'wan' ping -n -c 2 -W 2 -s 1472 -M do '10.20.0.1' 2>&1");
});

test('it reports an unanswered ping as unreachable rather than as an error', function () {
    $output = "PING 192.0.2.1 (192.0.2.1) 56(84) bytes of data.\n\n--- 192.0.2.1 ping statistics ---\n1 packets transmitted, 0 received, 100% packet loss, time 0ms\n\n";

    $result = ping(new FakeRemoteExecutor(new CommandResult($output, exitCode: 1)), ['target' => '192.0.2.1']);

    expect($result)->toBeInstanceOf(ResponseFactory::class);
    expect($result->getStructuredContent())->toBe(['reachable' => false, 'exit_code' => 1, 'output' => $output]);
});

test('it binds the probe to an mwan3 interface and drops the mwan3 preamble', function () {
    $executor = new FakeRemoteExecutor(new CommandResult("Running 'ping -n -c 3 -W 2 1.1.1.1' with DEVICE=eth1 SRCIP=192.168.5.19 FWMARK=0x3f00 FAMILY=ipv4\n".PING_REPLY));

    $result = ping($executor, ['target' => '1.1.1.1', 'interface' => 'RED']);

    expect($result->getStructuredContent())->toBe(['reachable' => true, 'exit_code' => 0, 'output' => PING_REPLY]);
    expect($executor->calledWithCommand)->toBe("mwan3 use 'RED' ping -n -c 3 -W 2 '1.1.1.1' 2>&1");
});

test('it reports an interface mwan3 cannot bind as an error', function () {
    $result = ping(new FakeRemoteExecutor(new CommandResult("could not find device for NOPE\n")), ['target' => '1.1.1.1', 'interface' => 'NOPE']);

    expect($result)->toBeInstanceOf(Response::class);
    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('could not find device for NOPE');
});

test('it rejects an interface mwan3 does not manage and points to source_ip', function () {
    $output = "could not find family for GREEN. Using ipv4.\nRunning 'ping -n -c 3 -W 2 1.1.1.1' with DEVICE=eth0 SRCIP=10.0.1.1 FWMARK=0x3f00 FAMILY=ipv4\n";

    $result = ping(new FakeRemoteExecutor(new CommandResult($output, exitCode: 1)), ['target' => '1.1.1.1', 'interface' => 'GREEN']);

    expect($result)->toBeInstanceOf(Response::class);
    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('GREEN is not an mwan3-managed interface: use source_ip to send from its address instead.');
});

test('it reports a failed session as an error', function () {
    $result = ping(new FakeRemoteExecutor(new RuntimeException('Permission denied (publickey).')), ['target' => '1.1.1.1']);

    expect($result)->toBeInstanceOf(Response::class);
    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Permission denied (publickey).');
});

test('it accepts an IPv6 target', function () {
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    ping($executor, ['target' => '2606:4700:4700::1111']);

    expect($executor->calledWithCommand)->toBe("ping -n -c 3 -W 2 '2606:4700:4700::1111' 2>&1");
});

test('it rejects invalid arguments without reaching the machine', function (array $arguments) {
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    expect(fn () => ping($executor, $arguments))->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
})->with([
    'missing target' => [[]],
    'target starting with an option' => [['target' => '-f']],
    'shell injection in target' => [['target' => '1.1.1.1; reboot']],
    'target with a space' => [['target' => 'one one']],
    'shell injection in interface' => [['target' => '1.1.1.1', 'interface' => "RED'; reboot; '"]],
    'source that is not an address' => [['target' => '1.1.1.1', 'source_ip' => 'lan']],
    'interface together with source' => [['target' => '1.1.1.1', 'interface' => 'RED', 'source_ip' => '10.0.1.1']],
    'count above the cap' => [['target' => '1.1.1.1', 'count' => 6]],
    'count below one' => [['target' => '1.1.1.1', 'count' => 0]],
    'size above the IPv4 maximum' => [['target' => '1.1.1.1', 'size' => 65508]],
    'size that is not a number' => [['target' => '1.1.1.1', 'size' => '1472 -f']],
]);

<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\PacketCapture;
use App\Services\Remote\CommandResult;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Tests\Fakes\FakeRemoteExecutor;

beforeEach(fn () => Str::createRandomStringsUsing(fn (): string => 'AbCd1234EfGh5678'));

afterEach(fn () => Str::createRandomStringsNormally());

/**
 * @param  array<string, mixed>  $arguments
 */
function packetCapture(RemoteExecutor $executor, array $arguments): Response|ResponseFactory
{
    return (new PacketCapture($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

/**
 * Command `start` sends, around the given tcpdump command line.
 */
function packetCaptureStartCommand(string $tcpdump): string
{
    return "find /tmp -maxdepth 1 -name 'pointer-capture-*' -mmin +60 -delete; "
        ."running=$(pgrep -af 'tcpdump .*-w /tmp/pointer-capture-' | grep -o 'pointer-capture-[A-Za-z0-9]\\{16\\}' | head -n 1); "
        .'if [ -n "$running" ]; then echo "busy ${running#pointer-capture-}"; '
        ."else {$tcpdump} </dev/null >/tmp/pointer-capture-AbCd1234EfGh5678.log 2>&1 & sleep 1; cat /tmp/pointer-capture-AbCd1234EfGh5678.log; "
        ."grep -q 'listening on' /tmp/pointer-capture-AbCd1234EfGh5678.log || rm -f /tmp/pointer-capture-AbCd1234EfGh5678.pcap /tmp/pointer-capture-AbCd1234EfGh5678.log; fi";
}

test('it starts a background capture on any interface for a minute by default', function () {
    $log = "tcpdump: listening on any, link-type LINUX_SLL2 (Linux cooked v2), snapshot length 1500 bytes\n";
    $executor = new FakeRemoteExecutor(new CommandResult($log));

    $result = packetCapture($executor, ['action' => 'start']);

    expect($result)->toBeInstanceOf(ResponseFactory::class);
    expect($result->getStructuredContent())->toBe(['capture_id' => 'AbCd1234EfGh5678', 'capture_log' => $log]);
    expect($executor->calledWithMachineUuid)->toBe('a1b2c3d4-0000-0000-0000-000000000000');
    expect($executor->calledWithCommand)->toBe(packetCaptureStartCommand(
        "setsid timeout 60 tcpdump -i 'any' -n -s 1500 -c 1000 -U -w /tmp/pointer-capture-AbCd1234EfGh5678.pcap",
    ));
});

test('it starts a capture with the given interface, filter and limits', function () {
    $executor = new FakeRemoteExecutor(new CommandResult('tcpdump: listening on br-lan'));

    packetCapture($executor, [
        'action' => 'start',
        'interface' => 'br-lan',
        'filter' => 'port 67 or port 68',
        'duration' => 600,
        'max_packets' => 10000,
    ]);

    expect($executor->calledWithCommand)->toBe(packetCaptureStartCommand(
        "setsid timeout 600 tcpdump -i 'br-lan' -n -s 1500 -c 10000 -U -w /tmp/pointer-capture-AbCd1234EfGh5678.pcap -- 'port 67 or port 68'",
    ));
});

test('it escapes a filter that tries to break out of the shell', function () {
    $executor = new FakeRemoteExecutor(new CommandResult('tcpdump: listening on any'));

    packetCapture($executor, ['action' => 'start', 'filter' => "udp'; reboot; '"]);

    expect($executor->calledWithCommand)->toBe(packetCaptureStartCommand(
        "setsid timeout 60 tcpdump -i 'any' -n -s 1500 -c 1000 -U -w /tmp/pointer-capture-AbCd1234EfGh5678.pcap -- 'udp'\\''; reboot; '\\'''",
    ));
});

test('it refuses to start while another capture runs', function () {
    $result = packetCapture(new FakeRemoteExecutor(new CommandResult("busy Zz9Yy8Xx7Ww6Vv5U\n")), ['action' => 'start']);

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Capture Zz9Yy8Xx7Ww6Vv5U is still running on this machine: read and stop it, or wait for it to end.');
});

test('it reports tcpdump failing to start as an error', function () {
    $result = packetCapture(new FakeRemoteExecutor(new CommandResult("tcpdump: eth9: No such device exists\n(No such device exists)\n")), ['action' => 'start', 'interface' => 'eth9']);

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe("tcpdump: eth9: No such device exists\n(No such device exists)");
});

test('it reads the state, the tcpdump log and the packet header lines', function () {
    $output = "ended\ntcpdump: listening on any\n26 packets captured\n--- pointer packets ---\n10:34:10.001659 eth1  Out ifindex 3 40:62:31:13:db:e3 192.168.5.19.47331 > 8.8.8.8.53: UDP, length 43\n";
    $executor = new FakeRemoteExecutor(new CommandResult($output));

    $result = packetCapture($executor, ['action' => 'read', 'capture_id' => 'AbCd1234EfGh5678']);

    expect($result->getStructuredContent())->toBe([
        'running' => false,
        'capture_log' => "tcpdump: listening on any\n26 packets captured\n",
        'packets' => "10:34:10.001659 eth1  Out ifindex 3 40:62:31:13:db:e3 192.168.5.19.47331 > 8.8.8.8.53: UDP, length 43\n",
    ]);
    expect($executor->calledWithCommand)->toBe(
        'if [ ! -f /tmp/pointer-capture-AbCd1234EfGh5678.pcap ]; then echo missing; '
        .'else pgrep -f /tmp/pointer-capture-AbCd1234EfGh5678.pcap >/dev/null && echo running || echo ended; cat /tmp/pointer-capture-AbCd1234EfGh5678.log; '
        ."echo '--- pointer packets ---'; tcpdump -r /tmp/pointer-capture-AbCd1234EfGh5678.pcap -nn -q -e -c 50 2>&1; fi",
    );
});

test('it reads a running capture decoded, with a filter and a packet count', function () {
    $executor = new FakeRemoteExecutor(new CommandResult("running\ntcpdump: listening on any\n--- pointer packets ---\n"));

    $result = packetCapture($executor, ['action' => 'read', 'capture_id' => 'AbCd1234EfGh5678', 'filter' => 'udp port 67', 'count' => 10, 'decode' => true]);

    expect($result->getStructuredContent()['running'])->toBeTrue();
    expect($executor->calledWithCommand)->toEndWith("tcpdump -r /tmp/pointer-capture-AbCd1234EfGh5678.pcap -nn -vv -e -c 10 -- 'udp port 67' 2>&1; fi");
});

test('it reports a missing capture as an error', function () {
    $result = packetCapture(new FakeRemoteExecutor(new CommandResult("missing\n")), ['action' => 'read', 'capture_id' => 'AbCd1234EfGh5678']);

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('No capture AbCd1234EfGh5678 on this machine: it was stopped, or removed an hour after it ended.');
});

test('it stops the capture and removes its files', function () {
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    $result = packetCapture($executor, ['action' => 'stop', 'capture_id' => 'AbCd1234EfGh5678']);

    expect((string) $result->content())->toBe('Capture AbCd1234EfGh5678 stopped and removed.');
    expect($executor->calledWithCommand)->toBe('kill $(pgrep -f /tmp/pointer-capture-AbCd1234EfGh5678.pcap) 2>/dev/null; rm -f /tmp/pointer-capture-AbCd1234EfGh5678.pcap /tmp/pointer-capture-AbCd1234EfGh5678.log');
});

test('it rejects invalid input without calling the machine', function (array $arguments) {
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    expect(fn () => packetCapture($executor, $arguments))->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
})->with([
    'unknown action' => [['action' => 'delete']],
    'read without a capture id' => [['action' => 'read']],
    'capture id with a path' => [['action' => 'stop', 'capture_id' => '../../etc/passwd']],
    'capture id on start' => [['action' => 'start', 'capture_id' => 'AbCd1234EfGh5678']],
    'interface with a shell character' => [['action' => 'start', 'interface' => 'eth0;reboot']],
    'interface on read' => [['action' => 'read', 'capture_id' => 'AbCd1234EfGh5678', 'interface' => 'eth0']],
    'duration over ten minutes' => [['action' => 'start', 'duration' => 601]],
    'too many packets' => [['action' => 'start', 'max_packets' => 10001]],
    'filter on stop' => [['action' => 'stop', 'capture_id' => 'AbCd1234EfGh5678', 'filter' => 'udp']],
    'decode on start' => [['action' => 'start', 'decode' => true]],
]);

test('it reports a failed call as an error', function () {
    $result = packetCapture(new FakeRemoteExecutor(new RuntimeException('Connection refused.')), ['action' => 'start']);

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Connection refused.');
});

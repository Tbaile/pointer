<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethSecurity\ConntrackList;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Tests\Fakes\FakeRemoteExecutor;

/**
 * @param  array<string, mixed>  $arguments
 */
function conntrackList(RemoteExecutor $executor, array $arguments = []): Response
{
    return (new ConntrackList($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

test('it lists the first 50 entries by default and returns them as text', function () {
    $executor = new FakeRemoteExecutor(new CommandResult("udp      17 23 src=10.0.1.10 dst=8.8.8.8 sport=57584 dport=53\n1 entries matched\n"));

    $result = conntrackList($executor);

    expect($result->isError())->toBeFalse();
    expect((string) $result->content())->toBe("udp      17 23 src=10.0.1.10 dst=8.8.8.8 sport=57584 dport=53\n1 entries matched\n");
    expect($executor->calledWithMachineUuid)->toBe('a1b2c3d4-0000-0000-0000-000000000000');
    expect($executor->calledWithCommand)->toBe(<<<'COMMAND'
        set -o pipefail; conntrack -L | awk -v unreplied='0' -v limit='50' 'unreplied && !/\[UNREPLIED\]/ { next } { matched++ } matched <= limit { print } END { print matched + 0 " entries matched" }'
        COMMAND);
});

test('it passes the filters to conntrack and keeps only unreplied entries', function () {
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    conntrackList($executor, [
        'family' => 'ipv4',
        'protocol' => 'udp',
        'src' => '10.0.1.0/24',
        'dst' => '203.0.113.5',
        'sport' => 51820,
        'dport' => 1194,
        'unreplied' => true,
        'limit' => 20,
    ]);

    expect($executor->calledWithCommand)->toBe(<<<'COMMAND'
        set -o pipefail; conntrack -L -f 'ipv4' -p 'udp' -s '10.0.1.0/24' -d '203.0.113.5' --sport '51820' --dport '1194' | awk -v unreplied='1' -v limit='20' 'unreplied && !/\[UNREPLIED\]/ { next } { matched++ } matched <= limit { print } END { print matched + 0 " entries matched" }'
        COMMAND);
});

test('it counts the entries per value and returns the top ones', function () {
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    conntrackList($executor, [
        'protocol' => 'tcp',
        'state' => 'SYN_SENT',
        'group_by' => 'src',
        'limit' => 10,
    ]);

    expect($executor->calledWithCommand)->toBe(<<<'COMMAND'
        set -o pipefail; conntrack -L -p 'tcp' --state 'SYN_SENT' | awk -v unreplied='0' -v key='src' 'unreplied && !/\[UNREPLIED\]/ { next } { value = "none"; for (i = 1; i <= NF; i++) if (index($i, key "=") == 1) { value = substr($i, length(key) + 2); break } count[value]++ } END { for (value in count) print count[value], value }' | sort -rn | awk -v limit='10' '{ total += $1 } NR <= limit { print } END { print total + 0 " entries matched, " NR " distinct values" }'
        COMMAND);
});

test('it rejects an address that tries to break out of the shell', function () {
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    expect(fn () => conntrackList($executor, ['src' => '10.0.1.10; reboot']))
        ->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
});

test('it rejects a port without a protocol that has ports', function (array $arguments) {
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    expect(fn () => conntrackList($executor, $arguments))
        ->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
})->with([
    'no protocol' => [['dport' => 53]],
    'icmp' => [['protocol' => 'icmp', 'sport' => 53]],
]);

test('it rejects a state without the tcp protocol', function () {
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    expect(fn () => conntrackList($executor, ['protocol' => 'udp', 'state' => 'ESTABLISHED']))
        ->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
});

test('it rejects an unknown group', function () {
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    expect(fn () => conntrackList($executor, ['group_by' => 'mark']))
        ->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
});

test('it reports a conntrack failure as an error', function () {
    $result = conntrackList(new FakeRemoteExecutor(new CommandResult(
        "0 entries matched\n",
        "conntrack v1.4.8 (conntrack-tools): unknown option `--dport'\n",
        2,
    )));

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe("conntrack v1.4.8 (conntrack-tools): unknown option `--dport'");
});

test('it reports a failed call as an error', function () {
    $result = conntrackList(new FakeRemoteExecutor(new RuntimeException('Connection refused.')));

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Connection refused.');
});

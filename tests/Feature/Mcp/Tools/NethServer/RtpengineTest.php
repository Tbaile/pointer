<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethServer\Rtpengine;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Tests\Fakes\FakeRemoteExecutor;

/**
 * @param  array<string, mixed>  $arguments
 */
function rtpengine(RemoteExecutor $executor, array $arguments): Response
{
    return (new Rtpengine($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', 'module' => 'nethvoice-proxy1', ...$arguments]));
}

test('it lists the requested statistics in the rtpengine container', function (string $command) {
    $output = "Current sessions own: 0\nCurrent sessions foreign: 0\nCurrent sessions total: 0\n";
    $executor = new FakeRemoteExecutor(new CommandResult($output));

    $result = rtpengine($executor, ['command' => $command]);

    expect($result->isError())->toBeFalse();
    expect((string) $result->content())->toBe($output);
    expect($executor->calledWithCommand)->toBe("runagent -m 'nethvoice-proxy1' podman exec rtpengine rtpengine-ctl list {$command} 2>&1");
})->with(['numsessions', 'totals']);

test('it lists one session by Call-ID', function () {
    $output = "\ncallid: a84b4c76e66710@pc33.example.org\ndeletionmark: no\ncreated: 1792652400\nproxy: 127.0.0.1:41234\ntos: 0\nlast_signal: 1792652401\nredis_keyspace: -1\nforeign: no\n\n"
        ."--- Tag '1928301774', type: FROM_TAG, label '', branch '', callduration 12.000000\n"
        ."------ Media #1 (audio over RTP/AVP) using PCMA/8000\n"
        ."-------- Port     10.0.0.10:30000 <>    203.0.113.5:5004 , SSRC 1a2b3c4d, 600 p, 103200 b, 0 e, 1792652412 ts\n\n";
    $executor = new FakeRemoteExecutor(new CommandResult($output));

    $result = rtpengine($executor, ['command' => 'session', 'call_id' => 'a84b4c76e66710@pc33.example.org']);

    expect((string) $result->content())->toBe($output);
    expect($executor->calledWithCommand)->toBe("runagent -m 'nethvoice-proxy1' podman exec rtpengine rtpengine-ctl list sessions 'a84b4c76e66710@pc33.example.org' 2>&1");
});

test('it returns a Call-ID that is not found as an answer', function () {
    $output = "\nCall ID not found (a84b4c76e66710@pc33.example.org).\n\n";

    $result = rtpengine(new FakeRemoteExecutor(new CommandResult($output)), ['command' => 'session', 'call_id' => 'a84b4c76e66710@pc33.example.org']);

    expect($result->isError())->toBeFalse();
    expect((string) $result->content())->toBe($output);
});

test('it rejects unsafe input without running anything', function (array $arguments) {
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    expect(fn () => rtpengine($executor, $arguments))->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
})->with([
    'missing command' => [[]],
    'every session' => [['command' => 'session', 'call_id' => 'all']],
    'session without a Call-ID' => [['command' => 'session']],
    'write command' => [['command' => 'terminate']],
    'Call-ID with a shell character' => [['command' => 'session', 'call_id' => 'abc;reboot']],
    'module with a shell character' => [['module' => 'nethvoice-proxy1;reboot', 'command' => 'totals']],
]);

test('it returns the error when rtpengine-ctl fails', function () {
    $result = rtpengine(new FakeRemoteExecutor(new CommandResult("Bad command or connection refused\n", exitCode: 1)), ['command' => 'totals']);

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Bad command or connection refused');
});

test('it returns an error when the machine cannot be reached', function () {
    $result = rtpengine(new FakeRemoteExecutor(new RuntimeException('Connection timed out')), ['command' => 'numsessions']);

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Connection timed out');
});

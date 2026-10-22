<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethServer\AsteriskCli;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Tests\Fakes\FakeRemoteExecutor;

/**
 * @param  array<string, mixed>  $arguments
 */
function asteriskCli(RemoteExecutor $executor, array $arguments): Response
{
    return (new AsteriskCli($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

test('it runs the command in the freepbx container and returns its output', function () {
    $output = "0 active channels\n0 active calls\n0 calls processed\n";
    $executor = new FakeRemoteExecutor(new CommandResult($output));

    $result = asteriskCli($executor, ['module' => 'nethvoice1', 'command' => 'core show channels count']);

    expect($result->isError())->toBeFalse();
    expect((string) $result->content())->toBe($output);
    expect($executor->calledWithCommand)->toBe("runagent -m 'nethvoice1' podman exec freepbx asterisk -rx 'core show channels count' 2>&1");
});

test('it returns endpoints as counts per state, without their names', function () {
    $output = "\n Endpoint:  <Endpoint/CID.....................................>  <State.....>  <Channels.>\n"
        ."==========================================================================================\n\n"
        ." Endpoint:  201/Mario Rossi                                      Not in use    0 of inf\n"
        ." Endpoint:  202/202                                              Unavailable   0 of inf\n"
        ." Endpoint:  203/203                                              Unavailable   0 of inf\n"
        ."\nObjects found: 3\n\n";

    $result = asteriskCli(new FakeRemoteExecutor(new CommandResult($output)), ['module' => 'nethvoice1', 'command' => 'pjsip list endpoints']);

    expect((string) $result->content())->toBe("STATE\tCOUNT\nUnavailable\t2\nNot in use\t1\nTotal\t3\n");
});

test('it returns contacts as counts per status, without their addresses', function () {
    $output = "\n  Contact:  <Aor/ContactUri..............................> <Hash....> <Status> <RTT(ms)..>\n"
        ."==========================================================================================\n\n"
        ."  Contact:  201/sip:201@192.0.2.10:5060                    86f8898583 Avail         12.301\n"
        ."  Contact:  Trunk_1/sip:sip.example.com:5061               1a2b3c4d5e NonQual        -nan\n\n";

    $result = asteriskCli(new FakeRemoteExecutor(new CommandResult($output)), ['module' => 'nethvoice1', 'command' => 'pjsip list contacts']);

    expect((string) $result->content())->toBe("STATE\tCOUNT\nAvail\t1\nNonQual\t1\nTotal\t2\n");
});

test('it counts zero when there are no objects', function () {
    $result = asteriskCli(new FakeRemoteExecutor(new CommandResult("No objects found.\n\n")), ['module' => 'nethvoice1', 'command' => 'pjsip list contacts']);

    expect((string) $result->content())->toBe("STATE\tCOUNT\nTotal\t0\n");
});

test('it never returns a list it could not count', function () {
    $result = asteriskCli(new FakeRemoteExecutor(new CommandResult(" Endpoint: 201 changed format\n")), ['module' => 'nethvoice1', 'command' => 'pjsip list endpoints']);

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->not->toContain('201');
});

test('it rejects unsafe input without running anything', function (array $arguments) {
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    expect(fn () => asteriskCli($executor, $arguments))->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
})->with([
    'missing module' => [['command' => 'core show uptime']],
    'missing command' => [['module' => 'nethvoice1']],
    'command outside the whitelist' => [['module' => 'nethvoice1', 'command' => 'pjsip show contacts']],
    'write command' => [['module' => 'nethvoice1', 'command' => 'core restart now']],
    'module with a shell character' => [['module' => 'nethvoice1;reboot', 'command' => 'core show uptime']],
]);

test('it returns the error when the module is not on this node', function () {
    $result = asteriskCli(new FakeRemoteExecutor(new CommandResult("runagent: [FATAL] Cannot find module nethvoice9 in the local node\n", exitCode: 1)), ['module' => 'nethvoice9', 'command' => 'core show uptime']);

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('runagent: [FATAL] Cannot find module nethvoice9 in the local node');
});

test('it returns an error when the machine cannot be reached', function () {
    $result = asteriskCli(new FakeRemoteExecutor(new RuntimeException('Connection timed out')), ['module' => 'nethvoice1', 'command' => 'core show uptime']);

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Connection timed out');
});

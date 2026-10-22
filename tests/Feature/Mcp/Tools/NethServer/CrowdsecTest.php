<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethServer\Crowdsec;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Tests\Fakes\FakeRemoteExecutor;

/**
 * @param  array<string, mixed>  $arguments
 */
function crowdsec(RemoteExecutor $executor, array $arguments): Response
{
    return (new Crowdsec($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', 'module' => 'crowdsec1', ...$arguments]));
}

test('it runs the cscli command in the module container', function (array $arguments, string $command) {
    $executor = new FakeRemoteExecutor(new CommandResult("output\n"));

    $result = crowdsec($executor, $arguments);

    expect($result->isError())->toBeFalse();
    expect((string) $result->content())->toBe("output\n");
    expect($executor->calledWithCommand)->toBe($command);
})->with([
    'local decisions' => [['command' => 'decisions'], "podman exec 'crowdsec1' cscli --error decisions list -o raw 2>&1"],
    'alerts' => [['command' => 'alerts'], "podman exec 'crowdsec1' cscli --error alerts list -o raw --limit 50 2>&1"],
    'alerts of an address' => [['command' => 'alerts', 'ip' => '203.0.113.10'], "podman exec 'crowdsec1' cscli --error alerts list -o raw --limit 50 -i '203.0.113.10' 2>&1"],
    'allowlist check' => [['command' => 'allowlists-check', 'ip' => '203.0.113.10'], "podman exec 'crowdsec1' cscli --error allowlists check '203.0.113.10' 2>&1"],
    'bouncers' => [['command' => 'bouncers'], "podman exec 'crowdsec1' cscli --error bouncers list -o raw 2>&1"],
    'metrics' => [['command' => 'metrics'], "podman exec 'crowdsec1' cscli --error metrics 2>&1"],
]);

test('it keeps only the decisions on the address or on a range containing it', function () {
    $output = "id,source,ip,reason,action,country,as,events_count,expiration,simulated,alert_id\n"
        ."1396231,CAPI,Ip:203.0.113.10,ssh:bruteforce,ban,,,0,164h0m54s,false,1559\n"
        ."1396232,CAPI,Ip:203.0.113.100,ssh:bruteforce,ban,,,0,162h0m48s,false,1559\n"
        ."1396300,lists,Range:203.0.113.0/24,firehol,ban,,,0,20h0m0s,false,1560\n"
        ."1396301,lists,Range:198.51.100.0/24,firehol,ban,,,0,20h0m0s,false,1560\n";
    $executor = new FakeRemoteExecutor(new CommandResult($output));

    $result = crowdsec($executor, ['command' => 'decisions', 'ip' => '203.0.113.10']);

    expect((string) $result->content())->toBe("id,source,ip,reason,action,country,as,events_count,expiration,simulated,alert_id\n"
        ."1396231,CAPI,Ip:203.0.113.10,ssh:bruteforce,ban,,,0,164h0m54s,false,1559\n"
        ."1396300,lists,Range:203.0.113.0/24,firehol,ban,,,0,20h0m0s,false,1560\n");
    expect($executor->calledWithCommand)->toBe("podman exec 'crowdsec1' cscli --error decisions list --all -l 0 -o raw 2>&1 | grep -F -e 'id,source,' -e ',Ip:203.0.113.10,' -e ',Range:' -e Error 2>&1");
});

test('it says when no decision applies to the address', function () {
    $result = crowdsec(new FakeRemoteExecutor(new CommandResult("id,source,ip,reason,action,country,as,events_count,expiration,simulated,alert_id\n")), ['command' => 'decisions', 'ip' => '203.0.113.10']);

    expect((string) $result->content())->toBe("No decision for 203.0.113.10.\n");
});

test('it returns the cscli error of a decisions check', function () {
    $result = crowdsec(new FakeRemoteExecutor(new CommandResult("Error: cscli decisions list: unable to reach LAPI\n", exitCode: 1)), ['command' => 'decisions', 'ip' => '203.0.113.10']);

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Error: cscli decisions list: unable to reach LAPI');
});

test('it rejects unsafe input without running anything', function (array $arguments) {
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    expect(fn () => crowdsec($executor, $arguments))->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
})->with([
    'missing command' => [[]],
    'write command' => [['command' => 'decisions delete']],
    'allowlist check without an address' => [['command' => 'allowlists-check']],
    'address that is not an IP' => [['command' => 'decisions', 'ip' => '203.0.113.10,Range:']],
    'module with a shell character' => [['module' => 'crowdsec1;reboot', 'command' => 'metrics']],
]);

test('it returns the error when cscli fails', function () {
    $result = crowdsec(new FakeRemoteExecutor(new CommandResult("Error: no such container crowdsec9\n", exitCode: 125)), ['module' => 'crowdsec9', 'command' => 'bouncers']);

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Error: no such container crowdsec9');
});

test('it returns an error when the machine cannot be reached', function () {
    $result = crowdsec(new FakeRemoteExecutor(new RuntimeException('Connection timed out')), ['command' => 'metrics']);

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Connection timed out');
});

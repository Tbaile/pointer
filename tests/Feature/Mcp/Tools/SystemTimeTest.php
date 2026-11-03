<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\SystemTime;
use App\Services\Remote\CommandResult;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Tests\Fakes\FakeRemoteExecutor;

use function Pest\Laravel\travelTo;

function systemTime(RemoteExecutor $executor): Response
{
    return (new SystemTime($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000']));
}

test('it shows the machine time and its skew from Pointer', function () {
    travelTo(Carbon\Carbon::createFromTimestampUTC(1793695200));
    $executor = new FakeRemoteExecutor(new CommandResult("2026-11-03T09:40:43+0100 CET 1793695243\n"));

    $result = systemTime($executor);

    expect($result->isError())->toBeFalse();
    expect((string) $result->content())->toBe("Machine: 2026-11-03T09:40:43+0100 CET\nPointer: 2026-11-03T08:40:00+00:00\nSkew: +43s (round trip 0s)");
    expect($executor->calledWithMachineUuid)->toBe('a1b2c3d4-0000-0000-0000-000000000000');
    expect($executor->calledWithCommand)->toBe("date +'%Y-%m-%dT%H:%M:%S%z %Z %s'");
});

test('it reports a machine behind Pointer with a negative skew', function () {
    travelTo(Carbon\Carbon::createFromTimestampUTC(1793695200));

    $result = systemTime(new FakeRemoteExecutor(new CommandResult("2026-11-03T08:38:00+0000 UTC 1793695080\n")));

    expect((string) $result->content())->toContain('Skew: -120s');
});

test('it reports unexpected output as an error', function () {
    $result = systemTime(new FakeRemoteExecutor(new CommandResult("date: invalid format\n")));

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Unexpected date output: date: invalid format');
});

test('it reports a failed call as an error', function () {
    $result = systemTime(new FakeRemoteExecutor(new RuntimeException('Connection refused.')));

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Connection refused.');
});

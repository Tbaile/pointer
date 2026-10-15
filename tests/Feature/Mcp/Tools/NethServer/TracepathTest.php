<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethServer\Tracepath;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Tests\Fakes\FakeRemoteExecutor;

/**
 * @param  array<string, mixed>  $arguments
 */
function tracepath(RemoteExecutor $executor, array $arguments): Response|ResponseFactory
{
    return (new Tracepath($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

const TRACEPATH_REACHED = <<<'OUTPUT'
     1?: [LOCALHOST]                      pmtu 1500
     1:  5.101.110.7                                           1.893ms
     2:  35.214.216.68                                         5.162ms reached
         Resume: pmtu 1500 hops 2 back 19

    OUTPUT;

const TRACEPATH_NO_REPLY = <<<'OUTPUT'
     1?: [LOCALHOST]                      pmtu 1500
     1:  5.101.110.7                                           1.893ms
     2:  no reply
         Too many hops: pmtu 1500
         Resume: pmtu 1500

    OUTPUT;

test('it traces the path and reports the target reached', function () {
    $executor = new FakeRemoteExecutor(new CommandResult(TRACEPATH_REACHED));

    $result = tracepath($executor, ['target' => 'nethserver.org']);

    expect($result)->toBeInstanceOf(ResponseFactory::class);
    expect($result->getStructuredContent())->toBe(['reachable' => true, 'exit_code' => 0, 'output' => TRACEPATH_REACHED]);
    expect($executor->calledWithCommand)->toBe("tracepath -n -m 15 'nethserver.org' 2>&1");
});

test('it reports a path ending in no reply as unreachable although tracepath exits 0', function () {
    $result = tracepath(new FakeRemoteExecutor(new CommandResult(TRACEPATH_NO_REPLY)), ['target' => '1.1.1.1', 'max_hops' => 2]);

    expect($result->getStructuredContent())->toBe(['reachable' => false, 'exit_code' => 0, 'output' => TRACEPATH_NO_REPLY]);
});

test('it limits the hops', function () {
    $executor = new FakeRemoteExecutor(new CommandResult(TRACEPATH_NO_REPLY));

    tracepath($executor, ['target' => '1.1.1.1', 'max_hops' => 2]);

    expect($executor->calledWithCommand)->toBe("tracepath -n -m 2 '1.1.1.1' 2>&1");
});

test('it rejects unsafe input without running anything', function (array $arguments) {
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    expect(fn () => tracepath($executor, $arguments))->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
})->with([
    'target with a shell command' => [['target' => '$(reboot)']],
    'hops above the limit' => [['target' => '1.1.1.1', 'max_hops' => 16]],
]);

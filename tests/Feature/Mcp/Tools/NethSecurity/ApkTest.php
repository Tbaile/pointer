<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethSecurity\Apk;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Tests\Fakes\FakeRemoteExecutor;

/**
 * @param  array<string, mixed>  $arguments
 */
function apk(RemoteExecutor $executor, array $arguments = []): Response|ResponseFactory
{
    return (new Apk($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

test('it queries every package name and version by default', function () {
    $executor = new FakeRemoteExecutor('[{"name": "ns-plug", "version": "1.4.1-r1"}]');

    $result = apk($executor);

    expect($result)->toBeInstanceOf(Response::class);
    expect((string) $result->content())->toBe('[{"name": "ns-plug", "version": "1.4.1-r1"}]');
    expect($executor->calledWithMachineUuid)->toBe('a1b2c3d4-0000-0000-0000-000000000000');
    expect($executor->calledWithCommand)->toBe("apk query --fields 'name,version' --format json '*' 2>&1");
});

test('it builds the query from every option', function () {
    $executor = new FakeRemoteExecutor('[]');

    apk($executor, [
        'packages' => ['ns-plug', 'ns-*'],
        'filter' => 'upgradable',
        'all_matches' => true,
        'match' => 'provides',
        'fields' => ['name', 'version', 'scripts'],
    ]);

    expect($executor->calledWithCommand)
        ->toBe("apk query --upgradable --all-matches --match provides --fields 'name,version,scripts' --format json 'ns-plug' 'ns-*' 2>&1");
});

test('it finds the owner of a file', function () {
    $executor = new FakeRemoteExecutor('[]');

    apk($executor, ['match' => 'owner', 'packages' => ['/usr/libexec/migrate-to-my']]);

    expect($executor->calledWithCommand)
        ->toBe("apk query --match owner --fields 'name,version' --format json '/usr/libexec/migrate-to-my' 2>&1");
});

test('it refreshes the index quietly and returns the exit code', function () {
    $executor = new FakeRemoteExecutor(new CommandResult("ERROR: wget: exited with error 4\n", '', 1));

    $result = apk($executor, ['command' => 'update', 'packages' => ['ns-plug']]);

    expect($result)->toBeInstanceOf(ResponseFactory::class);
    expect($result->getStructuredContent())->toBe(['exit_code' => 1, 'output' => "ERROR: wget: exited with error 4\n"]);
    expect($executor->calledWithCommand)->toBe('apk update --quiet 2>&1');
});

test('it rejects commands that change packages', function () {
    $executor = new FakeRemoteExecutor('');

    expect(fn () => apk($executor, ['command' => 'upgrade']))->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
});

test('it rejects fields that print the repository credentials', function () {
    $executor = new FakeRemoteExecutor('');

    expect(fn () => apk($executor, ['fields' => ['name', 'repositories']]))->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
});

test('it rejects packages that are options or break out of the shell', function (string $package) {
    $executor = new FakeRemoteExecutor('');

    expect(fn () => apk($executor, ['packages' => [$package]]))->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
})->with(['--allow-untrusted', 'ns-plug; reboot', '$(reboot)']);

test('it reports a failed query as an error', function () {
    $result = apk(new FakeRemoteExecutor(new CommandResult("ERROR: command line: invalid argument for option 'match': 'foo'\n", '', 1)));

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe("ERROR: command line: invalid argument for option 'match': 'foo'");
});

test('it reports a failed call as an error', function () {
    $result = apk(new FakeRemoteExecutor(new RuntimeException('Connection refused.')));

    expect($result)->toBeInstanceOf(Response::class);
    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Connection refused.');
});

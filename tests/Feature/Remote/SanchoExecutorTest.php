<?php

use App\Contracts\RemoteExecutor;
use App\Services\Remote\SanchoExecutor;
use App\Support\Remote\SupportServerConfig;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    config()->set('pointer.support', [
        'host' => 'support.example.com',
        'user' => 'pointer',
        'identity_file' => '/etc/pointer/id_ed25519',
    ]);

    config()->set('pointer.execution', [
        'timeout' => 30,
        'max_output_bytes' => 64,
    ]);
});

function executor(): SanchoExecutor
{
    return new SanchoExecutor(SupportServerConfig::fromConfig());
}

/**
 * Fake a sancho session that echoes the markers it was sent, wrapping the
 * given body and exit code, the way the real target shell would.
 */
function fakeSession(string $body, int $exitCode = 0, string $banner = ''): void
{
    Process::fake(function (PendingProcess $process) use ($body, $exitCode, $banner) {
        $input = (string) $process->input;

        preg_match('/(__POINTER_START_\w+__)/', $input, $start);
        preg_match('/(__POINTER_END_\w+__)/', $input, $end);

        $stdout = $banner.$start[1]."\n".$body.$end[1].':'.$exitCode."\n";

        return Process::result(output: $stdout, exitCode: 0);
    });
}

test('it reaches the target through the support server', function () {
    Process::fake();

    executor()->run('0d1e6f2a-6b8f-4b8e-9a3e-1c2d3e4f5a6b', 'uname -a');

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === [
        'ssh',
        '-T',
        '-o', 'BatchMode=yes',
        '-o', 'ConnectTimeout=10',
        '-o', 'LogLevel=ERROR',
        '-o', 'IdentitiesOnly=yes',
        '-i', '/etc/pointer/id_ed25519',
        'pointer@support.example.com',
        "sancho session ssh '0d1e6f2a-6b8f-4b8e-9a3e-1c2d3e4f5a6b'",
    ]);
});

test('it resolves a relative identity file against the storage path', function () {
    config()->set('pointer.support.identity_file', 'app/private/pointer');

    Process::fake();

    executor()->run('0d1e6f2a-6b8f-4b8e-9a3e-1c2d3e4f5a6b', 'uname -a');

    Process::assertRan(fn (PendingProcess $process): bool => in_array(
        storage_path('app/private/pointer'),
        (array) $process->command,
        strict: true,
    ));
});

test('it sends the command to the target shell over stdin rather than as an argument', function () {
    Process::fake();

    executor()->run('0d1e6f2a-6b8f-4b8e-9a3e-1c2d3e4f5a6b', 'uname -a');

    Process::assertRan(function (PendingProcess $process): bool {
        $input = (string) $process->input;

        expect($input)
            ->toMatch('/^printf \'%s\\\\n\' \'__POINTER_START_\w+__\'\nuname -a\n__pointer_exit=\$\?\nprintf \'%s:%s\\\\n\' \'__POINTER_END_\w+__\' "\$__pointer_exit"\n$/');

        return true;
    });
});

test('it refuses a machine identifier that is not a valid UUID', function () {
    Process::fake();

    expect(fn () => executor()->run("a1b2c3d4' ; rm -rf /", 'uname -a'))
        ->toThrow(InvalidArgumentException::class);

    Process::assertNothingRan();
});

test('it isolates the command output and exit code from the session banner', function () {
    fakeSession("Linux ns8\n", exitCode: 3, banner: "Try connection on a1b2c3d4 session...\n\nNethSecurity 8.8.0\n\n");

    $result = executor()->run('0d1e6f2a-6b8f-4b8e-9a3e-1c2d3e4f5a6b', 'uname -a');

    expect($result->exitCode)->toBe(3)
        ->and($result->stdout)->toBe("Linux ns8\n")
        ->and($result->failed())->toBeTrue()
        ->and($result->truncated)->toBeFalse()
        ->and($result->machineUuid)->toBe('0d1e6f2a-6b8f-4b8e-9a3e-1c2d3e4f5a6b')
        ->and($result->command)->toBe('uname -a');
});

test('it truncates output beyond the configured cap', function () {
    fakeSession(str_repeat('x', 200));

    $result = executor()->run('0d1e6f2a-6b8f-4b8e-9a3e-1c2d3e4f5a6b', 'cat /var/log/messages');

    expect($result->stdout)->toHaveLength(64)
        ->and($result->truncated)->toBeTrue();
});

test('it falls back to the raw exit code when the session fails before the markers appear', function () {
    Process::fake(['*' => Process::result(output: '', errorOutput: 'Permission denied', exitCode: 255)]);

    $result = executor()->run('0d1e6f2a-6b8f-4b8e-9a3e-1c2d3e4f5a6b', 'uname -a');

    expect($result->exitCode)->toBe(255)
        ->and($result->stderr)->toBe("Permission denied\n");
});

test('it passes stderr through untouched, leaving noise suppression to the ssh client', function () {
    Process::fake(['*' => Process::result(
        output: '',
        errorOutput: "Permission denied (publickey).\n",
        exitCode: 255,
    )]);

    $result = executor()->run('0d1e6f2a-6b8f-4b8e-9a3e-1c2d3e4f5a6b', 'uname -a');

    expect($result->stderr)->toBe("Permission denied (publickey).\n");
});

test('it fails loudly when no support server is configured', function () {
    config()->set('pointer.support.host', null);

    expect(fn () => app(RemoteExecutor::class))->toThrow(RuntimeException::class);
});

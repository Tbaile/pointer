<?php

use App\Contracts\RemoteExecutor;
use App\Services\Remote\SanchoExecutor;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

function executor(): SanchoExecutor
{
    return new SanchoExecutor(
        host: 'support.example.com',
        user: 'pointer',
        identityFile: '/etc/pointer/id_ed25519',
        timeout: 30,
        maxOutputBytes: 64,
    );
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
    fakeSession('');

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
    config()->set('pointer.support', [
        'host' => 'support.example.com',
        'user' => 'pointer',
        'identity_file' => 'app/private/pointer',
    ]);

    fakeSession('');

    app(RemoteExecutor::class)->run('0d1e6f2a-6b8f-4b8e-9a3e-1c2d3e4f5a6b', 'uname -a');

    Process::assertRan(fn (PendingProcess $process): bool => in_array(
        storage_path('app/private/pointer'),
        (array) $process->command,
        strict: true,
    ));
});

test('it sends the command to the target shell over stdin rather than as an argument', function () {
    fakeSession('');

    executor()->run('0d1e6f2a-6b8f-4b8e-9a3e-1c2d3e4f5a6b', 'uname -a');

    Process::assertRan(function (PendingProcess $process): bool {
        $input = (string) $process->input;

        expect($input)
            ->toMatch('/^printf \'%s\\\\n\' \'__POINTER_START_\w+__\'\nuname -a\n__pointer_exit=\$\?\nprintf \'%s:%s\\\\n\' \'__POINTER_END_\w+__\' "\$__pointer_exit"\n$/');

        return true;
    });
});

test('it isolates the command output from the session banner', function () {
    fakeSession("Linux ns8\n", banner: "Try connection on a1b2c3d4 session...\n\nNethSecurity 8.8.0\n\n");

    expect(executor()->run('0d1e6f2a-6b8f-4b8e-9a3e-1c2d3e4f5a6b', 'uname -a')->output)->toBe("Linux ns8\n");
});

test('it truncates output beyond the configured cap', function () {
    fakeSession(str_repeat('x', 200));

    expect(executor()->run('0d1e6f2a-6b8f-4b8e-9a3e-1c2d3e4f5a6b', 'cat /var/log/messages')->output)->toHaveLength(64);
});

test('it returns the output and exit code of a command that exits non-zero', function () {
    fakeSession("1 packets transmitted, 0 received, 100% packet loss\n", exitCode: 1);

    $result = executor()->run('0d1e6f2a-6b8f-4b8e-9a3e-1c2d3e4f5a6b', 'ping -c 1 192.0.2.1');

    expect($result->successful())->toBeFalse();
    expect($result->exitCode)->toBe(1);
    expect($result->output)->toBe("1 packets transmitted, 0 received, 100% packet loss\n");
});

test('it throws with stderr when a failed command is thrown', function () {
    Process::fake(function (PendingProcess $process) {
        preg_match('/(__POINTER_START_\w+__)/', (string) $process->input, $start);
        preg_match('/(__POINTER_END_\w+__)/', (string) $process->input, $end);

        return Process::result(
            output: $start[1]."\n".$end[1].":1\n",
            errorOutput: "cat: /nope: No such file or directory\n",
        );
    });

    expect(fn () => executor()->run('0d1e6f2a-6b8f-4b8e-9a3e-1c2d3e4f5a6b', 'cat /nope')->throw())
        ->toThrow(RuntimeException::class, 'cat: /nope: No such file or directory');
});

test('it throws with stdout when a failed command without stderr is thrown', function () {
    fakeSession("Command failed: Not found\n", exitCode: 4);

    expect(fn () => executor()->run('0d1e6f2a-6b8f-4b8e-9a3e-1c2d3e4f5a6b', 'ubus call ns.nope list')->throw())
        ->toThrow(RuntimeException::class, 'Command failed: Not found');
});

test('it throws with the exit code when a silently failed command is thrown', function () {
    fakeSession('', exitCode: 3);

    expect(fn () => executor()->run('0d1e6f2a-6b8f-4b8e-9a3e-1c2d3e4f5a6b', 'false')->throw())
        ->toThrow(RuntimeException::class, 'Command exited with code 3');
});

test('it throws with the ssh error when the session fails before the markers appear', function () {
    Process::fake(['*' => Process::result(output: '', errorOutput: "Permission denied (publickey).\n", exitCode: 255)]);

    expect(fn () => executor()->run('0d1e6f2a-6b8f-4b8e-9a3e-1c2d3e4f5a6b', 'uname -a'))
        ->toThrow(RuntimeException::class, 'Permission denied (publickey).');
});

test('it fails loudly when no support server is configured', function () {
    config()->set('pointer.support.host', null);

    expect(fn () => app(RemoteExecutor::class))->toThrow(RuntimeException::class);
});

<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\FileChecksums;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Tests\Fakes\FakeRemoteExecutor;

/**
 * @param  array<string, mixed>  $arguments
 */
function fileChecksums(RemoteExecutor $executor, array $arguments = []): Response
{
    return (new FileChecksums($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

test('it lists the md5 checksum, size and time of every file sorted by path', function () {
    $executor = new FakeRemoteExecutor(new CommandResult("2026-10-08 16:45:04 1509 ff693e38014ba8118042b81bdd60fab7  /etc/config/network\n"));

    $result = fileChecksums($executor, ['paths' => ['/etc/config', '/etc/passwd']]);

    expect($result->isError())->toBeFalse();
    expect((string) $result->content())->toBe("2026-10-08 16:45:04 1509 ff693e38014ba8118042b81bdd60fab7  /etc/config/network\n");
    expect($executor->calledWithMachineUuid)->toBe('a1b2c3d4-0000-0000-0000-000000000000');
    expect($executor->calledWithCommand)->toBe("find '/etc/config' '/etc/passwd' -xdev -type f -printf '%TY-%Tm-%Td %TH:%TM:%.2TS %s ' -exec md5sum {} ';' | LC_ALL=C sort -k5 | head -n 2000");
});

test('it hashes with sha256 when asked', function () {
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    fileChecksums($executor, ['paths' => ['/etc/config'], 'algorithm' => 'sha256']);

    expect($executor->calledWithCommand)->toBe("find '/etc/config' -xdev -type f -printf '%TY-%Tm-%Td %TH:%TM:%.2TS %s ' -exec sha256sum {} ';' | LC_ALL=C sort -k5 | head -n 2000");
});

test('it returns one hash over every file with aggregate', function () {
    $executor = new FakeRemoteExecutor(new CommandResult("bc2d70976fc659e8f38ebf1974790dcf  -\n"));

    $result = fileChecksums($executor, ['paths' => ['/etc/config', '/etc/passwd'], 'aggregate' => true]);

    expect((string) $result->content())->toBe("bc2d70976fc659e8f38ebf1974790dcf  -\n");
    expect($executor->calledWithCommand)->toBe("find '/etc/config' '/etc/passwd' -xdev -type f -exec md5sum {} + | LC_ALL=C sort -k2 | md5sum");
});

test('it appends the paths that do not exist to the list', function () {
    $executor = new FakeRemoteExecutor(new CommandResult(
        "2026-10-12 08:43:35 529 8c7b56df060943657f776437949ef77e  /etc/passwd\n",
        "find: ‘/etc/backup.pass’: No such file or directory\n",
    ));

    $result = fileChecksums($executor, ['paths' => ['/etc/passwd', '/etc/backup.pass']]);

    expect($result->isError())->toBeFalse();
    expect((string) $result->content())->toBe("2026-10-12 08:43:35 529 8c7b56df060943657f776437949ef77e  /etc/passwd\nfind: ‘/etc/backup.pass’: No such file or directory\n");
});

test('it rejects a path that is unsafe or reaches the root or a virtual filesystem', function (string $path) {
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    expect(fn () => fileChecksums($executor, ['paths' => [$path]]))
        ->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
})->with([
    'relative' => 'etc/config',
    'shell' => "/etc'; reboot; '",
    'space' => '/etc/config /proc',
    'root' => '/',
    'proc' => '/proc',
    'sys' => '/sys/class',
    'dev' => '/dev/',
    'dot segment' => '/./proc',
    'parent segment' => '/etc/../dev',
]);

test('it rejects a missing path list and an unknown algorithm', function (array $arguments) {
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    expect(fn () => fileChecksums($executor, $arguments))
        ->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
})->with([
    'no paths' => [[]],
    'empty paths' => [['paths' => []]],
    'unknown algorithm' => [['paths' => ['/etc'], 'algorithm' => 'sha1; reboot']],
]);

test('it reports a failed command as an error', function () {
    $result = fileChecksums(new FakeRemoteExecutor(new CommandResult('', 'sort: write failed', 2)), ['paths' => ['/etc']]);

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('sort: write failed');
});

test('it reports a failed call as an error', function () {
    $result = fileChecksums(new FakeRemoteExecutor(new RuntimeException('Connection refused.')), ['paths' => ['/etc']]);

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Connection refused.');
});

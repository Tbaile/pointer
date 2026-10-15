<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethServer\AuditLog;
use App\Services\Privacy\SecretMasker;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

function fakeAuditLogExecutor(CommandResult|Throwable $result): RemoteExecutor
{
    return new class($result) implements RemoteExecutor
    {
        public ?string $calledWithCommand = null;

        public function __construct(private CommandResult|Throwable $result) {}

        public function run(string $machineUuid, string $command): CommandResult
        {
            $this->calledWithCommand = $command;

            if ($this->result instanceof Throwable) {
                throw $this->result;
            }

            return $this->result;
        }
    };
}

/**
 * @param  array<string, mixed>  $arguments
 */
function auditLog(RemoteExecutor $executor, array $arguments = []): Response
{
    return (new AuditLog($executor, new SecretMasker('test-key')))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

function maskedAuditValue(string $value): string
{
    return '[masked:hmac-sha256:'.substr(hash_hmac('sha256', $value, 'test-key'), 0, 12).']';
}

test('it reads the audit database read-only with the default filters', function () {
    $executor = fakeAuditLogExecutor(new CommandResult(''));

    auditLog($executor);

    expect($executor->calledWithCommand)
        ->toStartWith("python3 -c 'import json, re, sqlite3, sys\n")
        ->toContain('file:/var/lib/nethserver/api-server/audit.db?mode=ro')
        ->toEndWith("' '' '' '' 50 2>&1");
});

test('it passes the filters as arguments', function () {
    $executor = fakeAuditLogExecutor(new CommandResult(''));

    auditLog($executor, ['task' => '(add|alter|remove)-repository', 'user' => 'admin', 'since' => '2026-09-01', 'limit' => 10]);

    expect($executor->calledWithCommand)->toEndWith("' '(add|alter|remove)-repository' 'admin' '2026-09-01' 10 2>&1");
});

test('it formats the entries and masks secrets at any depth', function () {
    $output = implode("\n", [
        '{"timestamp": "2026-10-14T09:45:37Z", "user": "admin", "event": "create-task", "task": "alter-repository", "queue": "cluster/tasks", "data": {"name": "default", "status": true}}',
        '{"timestamp": "2026-10-13T14:30:00Z", "user": "admin", "event": "create-task", "task": "set-subscription", "queue": "cluster/tasks", "data": {"auth_token": "abc", "options": {"password": "s3cret", "host": "smtp.example.com"}}}',
        '{"timestamp": "2026-10-13T14:27:52Z", "user": "admin", "event": "login-ok", "task": null, "queue": null, "data": null}',
    ])."\n";

    $result = auditLog(fakeAuditLogExecutor(new CommandResult($output)));

    expect($result->isError())->toBeFalse();
    expect((string) $result->content())->toBe(
        "TIMESTAMP\tUSER\tEVENT\tTASK\tQUEUE\tDATA\n"
        ."2026-10-14T09:45:37Z\tadmin\tcreate-task\talter-repository\tcluster/tasks\t{\"name\":\"default\",\"status\":true}\n"
        ."2026-10-13T14:30:00Z\tadmin\tcreate-task\tset-subscription\tcluster/tasks\t{\"auth_token\":\"".maskedAuditValue('abc').'","options":{"password":"'.maskedAuditValue('s3cret')."\",\"host\":\"smtp.example.com\"}}\n"
        ."2026-10-13T14:27:52Z\tadmin\tlogin-ok\t-\t-\tnull\n"
    );
});

test('it says so when no entry matches', function () {
    $result = auditLog(fakeAuditLogExecutor(new CommandResult('')), ['task' => 'remove-repository']);

    expect((string) $result->content())->toBe('No audit entry matches.');
});

test('it rejects unsafe input without running anything', function (array $arguments) {
    $executor = fakeAuditLogExecutor(new CommandResult(''));

    expect(fn () => auditLog($executor, $arguments))->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
})->with([
    'task with a quote' => [['task' => "repo'; import os"]],
    'user with spaces' => [['user' => 'admin admin']],
    'since that is not a date' => [['since' => 'yesterday']],
    'limit above the maximum' => [['limit' => 501]],
]);

test('it returns the python error when the database cannot be read', function () {
    $result = auditLog(fakeAuditLogExecutor(new CommandResult("sqlite3.OperationalError: unable to open database file\n", exitCode: 1)));

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('sqlite3.OperationalError: unable to open database file');
});

test('it returns an error when the machine cannot be reached', function () {
    $result = auditLog(fakeAuditLogExecutor(new RuntimeException('Connection timed out')));

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Connection timed out');
});

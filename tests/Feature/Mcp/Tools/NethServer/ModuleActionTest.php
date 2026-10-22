<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethServer\ModuleAction;
use App\Services\Privacy\SecretMasker;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Tests\Fakes\FakeRemoteExecutor;

/**
 * @param  array<string, mixed>  $arguments
 */
function moduleAction(RemoteExecutor $executor, array $arguments): Response
{
    return (new ModuleAction($executor, new SecretMasker('test-key')))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

test('it runs the module action and returns its output', function () {
    $executor = new FakeRemoteExecutor(new CommandResult('["list-actions", "get-status"]'."\n"));

    $result = moduleAction($executor, ['module' => 'traefik1', 'action' => 'list-actions']);

    expect($result->isError())->toBeFalse();
    expect((string) $result->content())->toBe('["list-actions","get-status"]'."\n");
    expect($executor->calledWithCommand)->toBe("api-cli run 'module/traefik1/list-actions' 2>&1");
});

test('it passes the action input as JSON', function () {
    $executor = new FakeRemoteExecutor(new CommandResult('{"instance": "cluster-admin", "path": "/cluster-admin"}'));

    moduleAction($executor, ['module' => 'traefik1', 'action' => 'get-route', 'data' => ['instance' => "it's"]]);

    expect($executor->calledWithCommand)->toBe("api-cli run 'module/traefik1/get-route' --data '{\"instance\":\"it'\\''s\"}' 2>&1");
});

test('it masks the values of keys that look like secrets', function () {
    $output = '{"fqdn": "app.example.org", "type": "selfsigned", "certificates": [{"cert": "LS0t", "key": "private"}]}';

    $result = moduleAction(new FakeRemoteExecutor(new CommandResult($output)), ['module' => 'traefik1', 'action' => 'get-certificate', 'data' => ['fqdn' => 'app.example.org']]);

    $masked = '[masked:hmac-sha256:'.substr(hash_hmac('sha256', 'private', 'test-key'), 0, 12).']';
    expect((string) $result->content())->toBe('{"fqdn":"app.example.org","type":"selfsigned","certificates":[{"cert":"LS0t","key":"'.$masked.'"}]}'."\n");
});

test('it rejects unsafe input without running anything', function (array $arguments) {
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    expect(fn () => moduleAction($executor, $arguments))->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
})->with([
    'missing module' => [['action' => 'list-actions']],
    'missing action' => [['module' => 'traefik1']],
    'write action' => [['module' => 'traefik1', 'action' => 'set-route']],
    'module with a path' => [['module' => '../cluster', 'action' => 'list-actions']],
    'module with a shell character' => [['module' => 'traefik1;reboot', 'action' => 'list-actions']],
    'data not an object' => [['module' => 'traefik1', 'action' => 'get-route', 'data' => 'cluster-admin']],
]);

test('it returns the api-cli error when the action fails', function () {
    $result = moduleAction(new FakeRemoteExecutor(new CommandResult("Action get-ports-list is not defined\n\"\"\n", exitCode: 8)), ['module' => 'traefik1', 'action' => 'get-ports-list']);

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe("Action get-ports-list is not defined\n\"\"");
});

test('it returns the output as an error when it is not JSON', function () {
    $result = moduleAction(new FakeRemoteExecutor(new CommandResult("Traceback (most recent call last):\n")), ['module' => 'traefik1', 'action' => 'get-status']);

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Traceback (most recent call last):');
});

test('it returns an error when the machine cannot be reached', function () {
    $result = moduleAction(new FakeRemoteExecutor(new RuntimeException('Connection timed out')), ['module' => 'traefik1', 'action' => 'get-status']);

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Connection timed out');
});

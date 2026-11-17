<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethSecurity\VictoriaLogsQuery;
use App\Services\Privacy\SecretMasker;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Tests\Fakes\FakeRemoteExecutor;

/**
 * @param  array<string, mixed>  $arguments
 */
function victoriaLogsQuery(RemoteExecutor $executor, array $arguments): Response|ResponseFactory
{
    return (new VictoriaLogsQuery($executor, new SecretMasker('test-key')))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

test('it runs the query without the repeated syslog fields and returns the entries as structured content', function () {
    $executor = new FakeRemoteExecutor(
        '{"_msg":"Child connection from 10.0.1.10:55338","_time":"2026-11-17T10:52:52.084519Z","app_name":"dropbear","level":"info","proc_id":"13023"}'."\n"
        .'{"_msg":"Exit before auth from <10.0.1.10:55338>","_time":"2026-11-17T10:52:50.1Z","app_name":"dropbear","level":"info","proc_id":"13023"}'."\n",
    );

    $result = victoriaLogsQuery($executor, ['query' => '_time:1h app_name:dropbear']);

    expect($result)->toBeInstanceOf(ResponseFactory::class);
    expect($result->getStructuredContent())->toBe(['logs' => [
        ['_msg' => 'Child connection from 10.0.1.10:55338', '_time' => '2026-11-17T10:52:52.084519Z', 'app_name' => 'dropbear', 'level' => 'info', 'proc_id' => '13023'],
        ['_msg' => 'Exit before auth from <10.0.1.10:55338>', '_time' => '2026-11-17T10:52:50.1Z', 'app_name' => 'dropbear', 'level' => 'info', 'proc_id' => '13023'],
    ]]);
    expect($executor->calledWithMachineUuid)->toBe('a1b2c3d4-0000-0000-0000-000000000000');
    expect($executor->calledWithCommand)->toBe("curl -sS --max-time 20 'http://127.0.0.1:9428/select/logsql/query' --data-urlencode 'query=_time:1h app_name:dropbear | delete _stream, _stream_id, hostname, facility, facility_keyword, priority, severity, format, msg_id' --data-urlencode 'limit=100'");
});

test('it passes the range and the limit', function () {
    $executor = new FakeRemoteExecutor('');

    victoriaLogsQuery($executor, ['query' => 'error', 'start' => '-7d', 'end' => '-1d', 'limit' => 20]);

    expect($executor->calledWithCommand)->toBe("curl -sS --max-time 20 'http://127.0.0.1:9428/select/logsql/query' --data-urlencode 'query=error | delete _stream, _stream_id, hostname, facility, facility_keyword, priority, severity, format, msg_id' --data-urlencode 'start=-7d' --data-urlencode 'end=-1d' --data-urlencode 'limit=20'");
});

test('it returns no entries when nothing matches', function () {
    $result = victoriaLogsQuery(new FakeRemoteExecutor(''), ['query' => 'app_name:nonexistent']);

    expect($result)->toBeInstanceOf(ResponseFactory::class);
    expect($result->getStructuredContent())->toBe(['logs' => []]);
});

test('it masks the secrets in the messages', function () {
    $executor = new FakeRemoteExecutor(
        '{"_msg":"[AUTH] authorization success for user root. POST /api/ubus/call {\"path\":\"ns.wireguard\",\"method\":\"edit-tunnel\",\"payload\":{\"peer_private_key\":\"cHJpdmF0ZQ==\"}}","app_name":"nethsecurity-api"}'."\n"
        .'{"_msg":"Downloading https://NETH-1234:s3cr3t@distfeed.example.com/packages.adb","app_name":"update-packages"}'."\n",
    );

    $result = victoriaLogsQuery($executor, ['query' => '*']);

    expect($result->getStructuredContent()['logs'])->toBe([
        ['_msg' => '[AUTH] authorization success for user root. POST /api/ubus/call {"path":"ns.wireguard","method":"edit-tunnel","payload":{"peer_private_key":"[masked:hmac-sha256:'.substr(hash_hmac('sha256', 'cHJpdmF0ZQ==', 'test-key'), 0, 12).']"}}', 'app_name' => 'nethsecurity-api'],
        ['_msg' => 'Downloading https://NETH-1234:[masked:hmac-sha256:'.substr(hash_hmac('sha256', 's3cr3t', 'test-key'), 0, 12).']@distfeed.example.com/packages.adb', 'app_name' => 'update-packages'],
    ]);
});

test('it escapes a query that tries to break out of the shell', function () {
    $executor = new FakeRemoteExecutor('');

    victoriaLogsQuery($executor, ['query' => "error'; reboot; '"]);

    expect($executor->calledWithCommand)->toBe("curl -sS --max-time 20 'http://127.0.0.1:9428/select/logsql/query' --data-urlencode 'query=error'\\''; reboot; '\\'' | delete _stream, _stream_id, hostname, facility, facility_keyword, priority, severity, format, msg_id' --data-urlencode 'limit=100'");
});

test('it rejects a limit over 500', function () {
    $executor = new FakeRemoteExecutor('');

    expect(fn () => victoriaLogsQuery($executor, ['query' => '*', 'limit' => 501]))
        ->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
});

test('it reports a query VictoriaLogs cannot parse as an error', function () {
    $result = victoriaLogsQuery(
        new FakeRemoteExecutor("cannot parse `query` arg [foo | bad pipe]: unexpected pipe name \"bad\"\n"),
        ['query' => 'foo | bad pipe'],
    );

    expect($result)->toBeInstanceOf(Response::class);
    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('cannot parse `query` arg [foo | bad pipe]: unexpected pipe name "bad"');
});

test('it reports a response cut by the output cap as an error', function () {
    $result = victoriaLogsQuery(
        new FakeRemoteExecutor('{"_msg":"first","app_name":"dnsmasq"}'."\n".'{"_msg":"sec'),
        ['query' => '*'],
    );

    expect($result)->toBeInstanceOf(Response::class);
    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('VictoriaLogs returned more than the output cap: narrow the query or lower the limit.');
});

test('it reports a failed call as an error', function () {
    $result = victoriaLogsQuery(
        new FakeRemoteExecutor(new CommandResult('', 'curl: (7) Failed to connect to 127.0.0.1 port 9428 after 0 ms: Could not connect to server', 7)),
        ['query' => '*'],
    );

    expect($result)->toBeInstanceOf(Response::class);
    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('curl: (7) Failed to connect to 127.0.0.1 port 9428 after 0 ms: Could not connect to server');
});

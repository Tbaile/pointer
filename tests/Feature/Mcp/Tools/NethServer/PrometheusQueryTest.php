<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethServer\PrometheusQuery;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Tests\Fakes\FakeRemoteExecutor;

/**
 * @param  array<string, string>  $arguments
 */
function prometheusQuery(RemoteExecutor $executor, array $arguments): Response|ResponseFactory
{
    return (new PrometheusQuery($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', 'module' => 'metrics1', ...$arguments]));
}

test('it runs an instant query in the module and returns the response as structured content', function () {
    $executor = new FakeRemoteExecutor('{"status":"success","data":{"resultType":"vector","result":[{"metric":{"__name__":"node_load1","node":"1"},"value":[1793702746.536,"0.21"]}]}}');

    $result = prometheusQuery($executor, ['endpoint' => 'query', 'query' => 'node_load1']);

    expect($result)->toBeInstanceOf(ResponseFactory::class);
    expect($result->getStructuredContent())->toBe([
        'status' => 'success',
        'data' => ['resultType' => 'vector', 'result' => [['metric' => ['__name__' => 'node_load1', 'node' => '1'], 'value' => [1793702746.536, '0.21']]]],
    ]);
    expect($executor->calledWithMachineUuid)->toBe('a1b2c3d4-0000-0000-0000-000000000000');
    expect($executor->calledWithCommand)->toBe("runagent -m 'metrics1' sh -c 'curl -sS -G --max-time 20 \"http://127.0.0.1:9091/\${PROMETHEUS_PATH:+\$PROMETHEUS_PATH/}api/v1/query\" --data-urlencode '\\''query=node_load1'\\'''");
});

test('it passes the range parameters to a range query', function () {
    $executor = new FakeRemoteExecutor('{"status":"success","data":{"resultType":"matrix","result":[]}}');

    prometheusQuery($executor, ['endpoint' => 'query_range', 'query' => 'node_procs_blocked', 'start' => '2026-10-07T08:00:00Z', 'end' => '2026-10-07T09:00:00Z', 'step' => '60']);

    expect($executor->calledWithCommand)->toBe("runagent -m 'metrics1' sh -c 'curl -sS -G --max-time 20 \"http://127.0.0.1:9091/\${PROMETHEUS_PATH:+\$PROMETHEUS_PATH/}api/v1/query_range\" --data-urlencode '\\''query=node_procs_blocked'\\'' --data-urlencode '\\''start=2026-10-07T08:00:00Z'\\'' --data-urlencode '\\''end=2026-10-07T09:00:00Z'\\'' --data-urlencode '\\''step=60'\\'''");
});

test('it lists the values of a label narrowed by a selector', function () {
    $executor = new FakeRemoteExecutor('{"status":"success","data":["1","2"]}');

    prometheusQuery($executor, ['endpoint' => 'label_values', 'label' => 'node', 'query' => 'node_load1']);

    expect($executor->calledWithCommand)->toBe("runagent -m 'metrics1' sh -c 'curl -sS -G --max-time 20 \"http://127.0.0.1:9091/\${PROMETHEUS_PATH:+\$PROMETHEUS_PATH/}api/v1/label/node/values\" --data-urlencode '\\''match[]=node_load1'\\'''");
});

test('it escapes a query that tries to break out of the shell', function () {
    $executor = new FakeRemoteExecutor('{"status":"success","data":[]}');

    prometheusQuery($executor, ['endpoint' => 'series', 'query' => "up'; reboot; '"]);

    expect($executor->calledWithCommand)->toBe("runagent -m 'metrics1' sh -c 'curl -sS -G --max-time 20 \"http://127.0.0.1:9091/\${PROMETHEUS_PATH:+\$PROMETHEUS_PATH/}api/v1/series\" --data-urlencode '\\''match[]=up'\\''\\'\\'''\\''; reboot; '\\''\\'\\'''\\'''\\'''");
});

test('it rejects a module that is not a plain id', function () {
    $executor = new FakeRemoteExecutor('{"status":"success","data":[]}');

    expect(fn () => prometheusQuery($executor, ['module' => 'metrics1; reboot', 'endpoint' => 'query', 'query' => 'up']))
        ->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
});

test('it rejects a label that is not a valid label name', function () {
    $executor = new FakeRemoteExecutor('{"status":"success","data":[]}');

    expect(fn () => prometheusQuery($executor, ['endpoint' => 'label_values', 'label' => '../admin/tsdb/delete_series']))
        ->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
});

test('it rejects an endpoint outside the read-only ones', function () {
    $executor = new FakeRemoteExecutor('{"status":"success","data":[]}');

    expect(fn () => prometheusQuery($executor, ['endpoint' => 'admin/tsdb/delete_series', 'query' => 'up']))
        ->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
});

test('it reports a Prometheus error as an error', function () {
    $result = prometheusQuery(
        new FakeRemoteExecutor('{"status":"error","errorType":"bad_data","error":"invalid parameter \"start\": cannot parse \"-1h\" to a valid timestamp"}'),
        ['endpoint' => 'query_range', 'query' => 'up', 'start' => '-1h', 'step' => '5m'],
    );

    expect($result)->toBeInstanceOf(Response::class);
    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('invalid parameter "start": cannot parse "-1h" to a valid timestamp');
});

test('it reports a module that is not on this node as an error', function () {
    $result = prometheusQuery(
        new FakeRemoteExecutor(new CommandResult('', "runagent: [FATAL] Cannot find module metrics1 in the local node\n", 1)),
        ['endpoint' => 'query', 'query' => 'up'],
    );

    expect($result)->toBeInstanceOf(Response::class);
    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('runagent: [FATAL] Cannot find module metrics1 in the local node');
});

test('it reports a failed call as an error', function () {
    $result = prometheusQuery(new FakeRemoteExecutor(new RuntimeException('Connection refused.')), ['endpoint' => 'query', 'query' => 'up']);

    expect($result)->toBeInstanceOf(Response::class);
    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Connection refused.');
});

test('it reports a truncated response as an error', function () {
    $result = prometheusQuery(new FakeRemoteExecutor('{"status":"success","data":["node_'), ['endpoint' => 'label_values', 'label' => '__name__']);

    expect($result)->toBeInstanceOf(Response::class);
    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Prometheus did not return a JSON object, the response may exceed the output cap: narrow the query.');
});

<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethSecurity\VictoriaQuery;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

function fakeVictoriaQueryExecutor(string|Throwable $result): RemoteExecutor
{
    return new class($result) implements RemoteExecutor
    {
        public ?string $calledWithMachineUuid = null;

        public ?string $calledWithCommand = null;

        public function __construct(private string|Throwable $result) {}

        public function run(string $machineUuid, string $command): CommandResult
        {
            $this->calledWithMachineUuid = $machineUuid;
            $this->calledWithCommand = $command;

            if ($this->result instanceof Throwable) {
                throw $this->result;
            }

            return new CommandResult($this->result);
        }
    };
}

/**
 * @param  array<string, string>  $arguments
 */
function victoriaQuery(RemoteExecutor $executor, array $arguments): Response|ResponseFactory
{
    return (new VictoriaQuery($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

test('it runs an instant query and returns the response as structured content', function () {
    $executor = fakeVictoriaQueryExecutor('{"status":"success","data":{"resultType":"vector","result":[{"metric":{"alertname":"WanDown"},"value":[1790167025,"1"]}]}}');

    $result = victoriaQuery($executor, ['endpoint' => 'query', 'query' => 'ALERTS{alertstate="firing"}']);

    expect($result)->toBeInstanceOf(ResponseFactory::class);
    expect($result->getStructuredContent())->toBe([
        'status' => 'success',
        'data' => ['resultType' => 'vector', 'result' => [['metric' => ['alertname' => 'WanDown'], 'value' => [1790167025, '1']]]],
    ]);
    expect($executor->calledWithMachineUuid)->toBe('a1b2c3d4-0000-0000-0000-000000000000');
    expect($executor->calledWithCommand)->toBe("curl -sS -G --max-time 20 'http://127.0.0.1:8428/api/v1/query' --data-urlencode 'query=ALERTS{alertstate=\"firing\"}'");
});

test('it passes the range parameters to a range query', function () {
    $executor = fakeVictoriaQueryExecutor('{"status":"success","data":{"resultType":"matrix","result":[]}}');

    victoriaQuery($executor, ['endpoint' => 'query_range', 'query' => 'ALERTS', 'start' => '-7d', 'end' => '-1d', 'step' => '1h']);

    expect($executor->calledWithCommand)->toBe("curl -sS -G --max-time 20 'http://127.0.0.1:8428/api/v1/query_range' --data-urlencode 'query=ALERTS' --data-urlencode 'start=-7d' --data-urlencode 'end=-1d' --data-urlencode 'step=1h'");
});

test('it lists the values of a label narrowed by a selector', function () {
    $executor = fakeVictoriaQueryExecutor('{"status":"success","data":["ServiceDown","WanDown"]}');

    victoriaQuery($executor, ['endpoint' => 'label_values', 'label' => 'alertname', 'query' => 'ALERTS', 'start' => '-1y']);

    expect($executor->calledWithCommand)->toBe("curl -sS -G --max-time 20 'http://127.0.0.1:8428/api/v1/label/alertname/values' --data-urlencode 'match[]=ALERTS' --data-urlencode 'start=-1y'");
});

test('it escapes a query that tries to break out of the shell', function () {
    $executor = fakeVictoriaQueryExecutor('{"status":"success","data":[]}');

    victoriaQuery($executor, ['endpoint' => 'series', 'query' => "up'; reboot; '"]);

    expect($executor->calledWithCommand)->toBe("curl -sS -G --max-time 20 'http://127.0.0.1:8428/api/v1/series' --data-urlencode 'match[]=up'\\''; reboot; '\\'''");
});

test('it rejects a label that is not a valid label name', function () {
    $executor = fakeVictoriaQueryExecutor('{"status":"success","data":[]}');

    expect(fn () => victoriaQuery($executor, ['endpoint' => 'label_values', 'label' => '../admin/tsdb/delete_series']))
        ->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
});

test('it rejects an endpoint outside the read-only ones', function () {
    $executor = fakeVictoriaQueryExecutor('{"status":"success","data":[]}');

    expect(fn () => victoriaQuery($executor, ['endpoint' => 'admin/tsdb/delete_series', 'query' => 'up']))
        ->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
});

test('it reports a VictoriaMetrics error as an error', function () {
    $result = victoriaQuery(
        fakeVictoriaQueryExecutor('{"status":"error","errorType":"422","error":"unparsed data: \"\""}'),
        ['endpoint' => 'query', 'query' => 'foo('],
    );

    expect($result)->toBeInstanceOf(Response::class);
    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('unparsed data: ""');
});

test('it reports a failed call as an error', function () {
    $result = victoriaQuery(fakeVictoriaQueryExecutor(new RuntimeException('Connection refused.')), ['endpoint' => 'query', 'query' => 'up']);

    expect($result)->toBeInstanceOf(Response::class);
    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Connection refused.');
});

test('it reports a truncated response as an error', function () {
    $result = victoriaQuery(fakeVictoriaQueryExecutor('{"status":"success","data":["cpu_'), ['endpoint' => 'label_values', 'label' => '__name__']);

    expect($result)->toBeInstanceOf(Response::class);
    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('VictoriaMetrics did not return a JSON object, the response may exceed the output cap: narrow the query.');
});
